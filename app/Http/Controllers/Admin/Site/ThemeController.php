<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Support\AttachmentUsageTracker;
use App\Support\Site as SitePath;
use App\Support\SiteStorageUsage;
use App\Support\ThemeTags;
use App\Support\ThemeTemplateEngine;
use App\Support\ThemeTemplateLocator;
use App\Support\ThemeTemplateScaffold;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use stdClass;

class ThemeController extends Controller
{
    /**
     * Display themes available to the current site.
     */
    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.use');
        $viewErrors = session('errors');
        $createTemplateModalOpen = $viewErrors
            ? $viewErrors->has('name') || $viewErrors->has('template_key')
            : false;
        $siteTemplates = DB::table('site_templates')
            ->where('site_id', $currentSite->id)
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [(int) ($currentSite->active_site_template_id ?? 0)])
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'template_key',
                'status',
                'created_at',
                'updated_at',
            ])
            ->map(function (object $siteTemplate) use ($currentSite): object {
                $templateCount = ThemeTemplateLocator::availableTemplatesForSite($currentSite->id, (string) $siteTemplate->template_key)->count();
                $siteTemplate->template_count = $templateCount;
                $siteTemplate->has_templates = $templateCount > 0;

                return $siteTemplate;
            });

        $activeTemplateKey = $this->activeSiteTemplateKey($currentSite);
        $activeTemplateItem = $siteTemplates->firstWhere('template_key', $activeTemplateKey);
        $orphanTemplates = $this->orphanTemplateDirectories(
            (string) $currentSite->site_key,
            $siteTemplates->pluck('template_key')->map(fn ($key): string => trim((string) $key))
        )->map(function (string $templateKey): object {
            return (object) [
                'name' => $templateKey,
                'template_key' => $templateKey,
                'template_count' => 0,
                'has_templates' => false,
            ];
        });

        return view('admin.site.themes.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'siteTemplates' => $siteTemplates,
            'activeTemplateKey' => $activeTemplateKey,
            'activeTemplateItem' => $activeTemplateItem,
            'orphanTemplates' => $orphanTemplates,
            'createTemplateModalOpen' => $createTemplateModalOpen,
        ]);
    }

    /**
     * Update the active theme for the current site.
     */
    public function update(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.use');

        $validated = $request->validate([
            'site_template_id' => ['required', 'integer'],
        ]);

        $siteTemplate = DB::table('site_templates')
            ->where('site_id', $currentSite->id)
            ->where('id', (int) $validated['site_template_id'])
            ->first();

        abort_unless($siteTemplate, 404);

        DB::table('sites')
            ->where('id', $currentSite->id)
            ->update([
                'active_site_template_id' => $siteTemplate->id,
                'updated_at' => now(),
            ]);

        $this->logOperation(
            'site',
            'theme',
            'activate_template',
            $currentSite->id,
            $request->user()->id,
            'site_template',
            $siteTemplate->id,
            ['template_key' => $siteTemplate->template_key],
            $request,
        );

        return redirect()
            ->route('admin.themes.index')
            ->with('status', '站点模版已启用。');
    }

    public function store(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^(?!\s)(?!.*\s$)(?!.*<[^>]+>).*$/u',
                Rule::unique('site_templates', 'name')->where(fn ($query) => $query->where('site_id', $currentSite->id)),
            ],
            'template_key' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('site_templates', 'template_key')->where(fn ($query) => $query->where('site_id', $currentSite->id)),
            ],
        ], [
            'name.regex' => '模版名称不能包含首尾空格或 HTML 标签。',
            'name.unique' => '当前站点已存在同名模版，请更换模版名称。',
            'template_key.min' => '模版标识至少需要 3 个字符。',
            'template_key.regex' => '模版标识只能使用小写字母、数字和中划线。',
            'template_key.unique' => '当前站点已存在相同模版标识，请更换后再试。',
        ], [
            'name' => '模版名称',
            'template_key' => '模版标识',
        ]);

        $siteTemplateId = DB::transaction(function () use ($currentSite, $validated, $request): int {
            $site = DB::table('sites')
                ->where('id', $currentSite->id)
                ->lockForUpdate()
                ->first(['id', 'site_key', 'template_limit']);

            abort_unless($site, 404);

            $currentCount = (int) DB::table('site_templates')
                ->where('site_id', $site->id)
                ->count();

            if ($currentCount >= (int) $site->template_limit) {
                throw ValidationException::withMessages([
                    'template_key' => '当前站点模版数量已达上限，请先删除未使用模版后再新增。',
                ]);
            }

            $siteTemplateId = (int) DB::table('site_templates')->insertGetId([
                'site_id' => $site->id,
                'name' => trim((string) $validated['name']),
                'template_key' => trim((string) $validated['template_key']),
                'status' => 1,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->ensureInitialSiteTemplateFiles((string) $site->site_key, trim((string) $validated['template_key']));

            return $siteTemplateId;
        });

        $this->logOperation(
            'site',
            'theme',
            'create_site_template',
            $currentSite->id,
            $request->user()->id,
            'site_template',
            $siteTemplateId,
            ['name' => $validated['name'], 'template_key' => $validated['template_key']],
            $request,
        );

        return redirect()
            ->route('admin.themes.index')
            ->with('status', '站点模版已创建。');
    }

    public function destroy(Request $request, string $siteTemplateId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');

        $siteTemplate = DB::table('site_templates')
            ->where('site_id', $currentSite->id)
            ->where('id', (int) $siteTemplateId)
            ->first();

        abort_unless($siteTemplate, 404);

        if ((int) $currentSite->active_site_template_id === (int) $siteTemplate->id) {
            return redirect()
                ->route('admin.themes.index')
                ->withErrors(['template' => '当前启用中的模版不能删除，请先切换到其他模版。']);
        }

        $templateCount = (int) DB::table('site_templates')
            ->where('site_id', $currentSite->id)
            ->count();

        if ($templateCount <= 1) {
            return redirect()
                ->route('admin.themes.index')
                ->withErrors(['template' => '站点至少需要保留一个模版。']);
        }

        DB::transaction(function () use ($siteTemplate, $currentSite, $request): void {
            DB::table('site_templates')
                ->where('id', (int) $siteTemplate->id)
                ->delete();

            $root = SitePath::siteTemplateRoot((string) $currentSite->site_key, (string) $siteTemplate->template_key);

            if (File::isDirectory($root)) {
                File::deleteDirectory($root);
            }
        });

        $this->logOperation(
            'site',
            'theme',
            'delete_site_template',
            $currentSite->id,
            $request->user()->id,
            'site_template',
            (int) $siteTemplate->id,
            ['template_key' => $siteTemplate->template_key],
            $request,
        );

        return redirect()
            ->route('admin.themes.index')
            ->with('status', '站点模版已删除。');
    }

    public function destroyOrphan(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');

        $validated = $request->validate([
            'template_key' => ['required', 'string', 'max:120'],
        ], [], [
            'template_key' => '异常模版目录',
        ]);

        $templateKey = trim((string) ($validated['template_key'] ?? ''));

        if ($templateKey === '' || str_contains($templateKey, '/') || str_contains($templateKey, '\\') || in_array($templateKey, ['.', '..'], true)) {
            return redirect()
                ->route('admin.themes.index')
                ->withErrors(['template' => '异常模版目录参数不合法，无法删除。']);
        }

        $existsInDb = DB::table('site_templates')
            ->where('site_id', (int) $currentSite->id)
            ->where('template_key', $templateKey)
            ->exists();

        if ($existsInDb) {
            return redirect()
                ->route('admin.themes.index')
                ->withErrors(['template' => '该目录已存在于模板库，请使用模板卡片的删除操作。']);
        }

        $themeRoot = SitePath::root((string) $currentSite->site_key).DIRECTORY_SEPARATOR.'theme';
        $resolvedThemeRoot = realpath($themeRoot);
        $targetPath = $themeRoot.DIRECTORY_SEPARATOR.$templateKey;
        $resolvedTargetPath = realpath($targetPath);

        if (
            ! is_string($resolvedThemeRoot) ||
            ! is_string($resolvedTargetPath) ||
            ! is_dir($resolvedTargetPath) ||
            ! str_starts_with($resolvedTargetPath, $resolvedThemeRoot.DIRECTORY_SEPARATOR)
        ) {
            return redirect()
                ->route('admin.themes.index')
                ->withErrors(['template' => '异常模版目录不存在或无法访问。']);
        }

        File::deleteDirectory($resolvedTargetPath);

        $this->logOperation(
            'site',
            'theme',
            'delete_orphan_site_template',
            $currentSite->id,
            $request->user()->id,
            'site_template_orphan',
            null,
            ['template_key' => $templateKey],
            $request,
        );

        return redirect()
            ->route('admin.themes.index')
            ->with('status', '异常模版目录已删除。');
    }

    /**
     * Display the source editor for the active theme home template.
     */
    public function editor(Request $request): View|RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');
        $siteTemplate = $this->resolveEditableSiteTemplate($request, $currentSite);
        if ($siteTemplate instanceof RedirectResponse) {
            return $siteTemplate;
        }
        $siteTemplateId = (int) $siteTemplate->id;
        $themeCode = trim((string) $siteTemplate->template_key);
        $templates = $this->availableTemplates($currentSite->id, $themeCode);
        if ($templates->isEmpty()) {
            return redirect()
                ->route('admin.themes.index')
                ->withErrors(['theme' => '当前模版未提供可编辑的模板文件，请先补充模板文件后再进入模板编辑。']);
        }
        $template = $this->selectedTemplate($request, $templates);
        $templateMeta = $templates->firstWhere('key', $template);
        $workspacePanel = in_array((string) $request->query('panel', 'editor'), ['editor', 'create', 'snapshots'], true)
            ? (string) $request->query('panel', 'editor')
            : 'editor';

        $paths = $this->themePaths($currentSite->id, $themeCode, $template);
        $templateSource = $paths['existing_override'] && File::exists($paths['existing_override'])
            ? File::get($paths['existing_override'])
            : (File::exists($paths['default']) ? File::get($paths['default']) : '');
        $latestVersion = $this->latestTemplateVersion($currentSite->id, $themeCode, $template);
        $templateHistory = $this->templateHistory($currentSite->id, $themeCode, $template);
        $compareVersion = $this->selectedTemplateVersion($request, $templateHistory);
        $diffRows = $compareVersion
            ? $this->buildTemplateDiffRows(
                $templateSource,
                $compareVersion->template_source,
                ($compareVersion->source_type ?? null) === 'missing'
            )
            : [];

        $assetKeyword = trim((string) $request->query('asset_keyword', ''));
        $assetType = strtolower((string) $request->query('asset_type', 'all'));
        $themeAssetsAll = collect();
        $themeAssets = collect();
        $themeAssetPage = max(1, (int) $request->query('asset_page', 1));
        $themeAssetPerPage = 9;
        $themeAssetsModalMode = in_array((string) $request->query('open_assets_mode', 'manage'), ['manage', 'insert'], true)
            ? (string) $request->query('open_assets_mode', 'manage')
            : 'manage';
        $themeAssetPaginator = new LengthAwarePaginator([], 0, $themeAssetPerPage, $themeAssetPage, [
            'pageName' => 'asset_page',
            'path' => route('admin.themes.editor', array_filter([
                'site_template_id' => $siteTemplateId,
                'template' => $template,
                'panel' => $workspacePanel !== 'editor' ? $workspacePanel : null,
            ])),
            'query' => array_merge($request->except('page'), [
                'open_assets' => 1,
                'open_assets_mode' => $themeAssetsModalMode,
                'asset_keyword' => $assetKeyword !== '' ? $assetKeyword : null,
                'asset_type' => $assetType !== '' ? $assetType : null,
            ]),
        ]);

        if ($request->boolean('open_assets')) {
            $themeAssetsAll = $this->themeAssets($currentSite->id, $themeCode);
            $themeAssets = $this->filterThemeAssets($themeAssetsAll, $assetKeyword, $assetType);
            $themeAssetPaginator = new LengthAwarePaginator(
                $themeAssets->slice(($themeAssetPage - 1) * $themeAssetPerPage, $themeAssetPerPage)->values(),
                $themeAssets->count(),
                $themeAssetPerPage,
                $themeAssetPage,
                $themeAssetPaginator->getOptions(),
            );
        }
        $themeAssetBytes = SiteStorageUsage::themeAssetBytes($currentSite);
        $totalStorageBytes = SiteStorageUsage::totalBytes($currentSite);
        $storageLimitMb = SiteStorageUsage::storageLimitMb((int) $currentSite->id);
        $storageLimitBytes = SiteStorageUsage::storageLimitBytes((int) $currentSite->id);
        $hasStorageLimit = $storageLimitMb > 0;
        $totalStorageUsagePercent = $hasStorageLimit && $storageLimitBytes > 0
            ? min(100, round(($totalStorageBytes / $storageLimitBytes) * 100, 1))
            : 100.0;
        $themeAssetUsagePercent = $hasStorageLimit && $storageLimitBytes > 0
            ? min(100, round(($themeAssetBytes / $storageLimitBytes) * 100, 1))
            : ($totalStorageBytes > 0 ? min(100, round(($themeAssetBytes / max(1, $totalStorageBytes)) * 100, 1)) : 0.0);

        return view('admin.site.themes.editor', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'siteTemplateId' => $siteTemplateId,
            'themeCode' => $themeCode,
            'themeName' => trim((string) ($siteTemplate->name ?? '')) !== '' ? trim((string) $siteTemplate->name) : $this->templateDisplayName($currentSite->id, $themeCode),
            'workspacePanel' => $workspacePanel,
            'template' => $template,
            'currentTemplate' => $template,
            'templates' => $templates,
            'templateGroups' => $this->groupTemplatesForWorkspace($templates),
            'templateMeta' => $templateMeta,
            'templateTitle' => (string) ($this->templateCustomTitle($currentSite->id, $themeCode, $template) ?? ''),
            'templateSource' => $templateSource,
            'templateSourceFieldLabel' => $this->editorSourceFieldLabel($templateMeta),
            'latestTemplateVersion' => $latestVersion,
            'themeAssets' => $themeAssetPaginator,
            'themeAssetsTotalCount' => $themeAssetsAll->count(),
            'themeAssetsFilteredCount' => $themeAssets->count(),
            'themeAssetUsageLabel' => SiteStorageUsage::formatBytes($themeAssetBytes),
            'totalStorageUsageLabel' => SiteStorageUsage::formatBytes($totalStorageBytes),
            'storageLimitLabel' => $storageLimitMb > 0 ? SiteStorageUsage::formatBytes($storageLimitBytes) : '不限',
            'storageRemainingLabel' => $storageLimitMb > 0 ? SiteStorageUsage::formatBytes(max(0, $storageLimitBytes - $totalStorageBytes)) : '不限',
            'themeAssetsModalMode' => $themeAssetsModalMode,
            'hasStorageLimit' => $hasStorageLimit,
            'assetKeyword' => $assetKeyword,
            'assetType' => $assetType,
            'totalStorageUsagePercent' => $totalStorageUsagePercent,
            'themeAssetUsagePercent' => $themeAssetUsagePercent,
            'templateHistory' => $templateHistory,
            'compareVersion' => $compareVersion,
            'diffRows' => $diffRows,
            'templateQuickGuideUrl' => url('/docs/theme-template-development-guide.html'),
        ]);
    }

    public function snapshots(Request $request): View|RedirectResponse
    {
        $request->query->set('panel', 'snapshots');

        return $this->editor($request);
    }

    public function createTemplateForm(Request $request): View|RedirectResponse
    {
        $request->query->set('panel', 'create');

        return $this->editor($request);
    }

    /**
     * Update the override template source for the active theme.
     */
    public function updateEditor(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');
        $siteTemplate = $this->resolveEditableSiteTemplate($request, $currentSite);
        if ($siteTemplate instanceof RedirectResponse) {
            return $siteTemplate;
        }
        $siteTemplateId = (int) $siteTemplate->id;
        $themeCode = trim((string) $siteTemplate->template_key);
        $templates = $this->availableTemplates($currentSite->id, $themeCode);
        $template = $this->selectedTemplate($request, $templates);

        $validated = $request->validate([
            'template' => ['required', 'string'],
            'template_title' => ['nullable', 'string', 'max:10'],
            'template_source' => ['required', 'string'],
        ], [
            'template_title.max' => '模板标题不能超过 10 个字。',
        ], [
            'template' => '模板文件',
            'template_title' => '模板标题',
            'template_source' => '模板源码',
        ]);

        $paths = $this->themePaths($currentSite->id, $themeCode, $template);
        try {
            $this->validateEditorSource($currentSite, $themeCode, $template, $validated['template_source']);
        } catch (\Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['template_source' => $exception->getMessage()]);
        }

        $this->snapshotTemplateState($currentSite->id, $themeCode, $template, 'edit_template', $request->user()->id);
        File::ensureDirectoryExists(dirname($paths['override']));
        File::put($paths['override'], $validated['template_source']);
        $this->persistTemplateTitle($currentSite->id, $themeCode, $template, $validated['template_title'] ?? null);
        $this->clearLegacyTemplateAttachmentRelations($currentSite->id, $themeCode, $template);

        $this->logOperation(
            'site',
            'theme',
            'edit_template',
            $currentSite->id,
            $request->user()->id,
            'theme',
            null,
            ['code' => $themeCode, 'template' => $template],
            $request,
        );

        return redirect()
            ->route('admin.themes.editor', ['site_template_id' => $siteTemplateId, 'template' => $template])
            ->with('keep_theme_editor_open', true)
            ->with('status', '模板源码已保存。');
    }

    public function createTemplate(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');
        $siteTemplate = $this->resolveEditableSiteTemplate($request, $currentSite);
        if ($siteTemplate instanceof RedirectResponse) {
            return $siteTemplate;
        }
        $siteTemplateId = (int) $siteTemplate->id;
        $themeCode = trim((string) $siteTemplate->template_key);

        $validated = $request->validateWithBag('createTemplate', [
            'template_prefix' => ['required', 'string', Rule::in(['list', 'detail', 'page', 'tag', 'css', 'js'])],
            'template_suffix' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/'],
            'template_title' => ['required', 'string', 'max:10'],
            'template_source' => ['nullable', 'string', 'max:200000'],
        ], [
            'template_suffix.max' => '模板标识不能超过 40 个字符。',
            'template_suffix.regex' => "模板标识格式不正确。\n允许填写：小写字母、数字、中划线（-）和下划线（_），且不能以符号开头或结尾。",
            'template_title.max' => '模板标题不能超过 10 个字。',
        ], [
            'template_prefix' => '模板类型',
            'template_suffix' => '模板标识',
            'template_title' => '模板标题',
            'template_source' => '模板源码',
        ]);

        $template = $this->resolveRequestedTemplateName($validated);

        if ($template === '') {
            throw ValidationException::withMessages([
                'template_suffix' => "请先填写模板标识。\n允许填写：小写字母、数字、中划线（-）和下划线（_），且不能以符号开头或结尾。",
            ])->errorBag('createTemplate');
        }

        if (strlen($template) > 60) {
            throw ValidationException::withMessages([
                'template_suffix' => '模板文件名过长，请控制在 60 个字符以内。',
            ])->errorBag('createTemplate');
        }

        $paths = $this->themePaths($currentSite->id, $themeCode, $template);

        if (File::exists($paths['default']) || $paths['existing_override']) {
            return back()
                ->withInput()
                ->withErrors(['template_suffix' => '该模板文件已存在，请更换模板标识。'], 'createTemplate');
        }

        $templateSource = trim((string) ($validated['template_source'] ?? ''));

        try {
            $this->validateEditorSource($currentSite, $themeCode, $template, $templateSource);
        } catch (\Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['template_source' => $exception->getMessage()], 'createTemplate');
        }

        $this->snapshotTemplateState($currentSite->id, $themeCode, $template, 'create_template', $request->user()->id);
        File::ensureDirectoryExists(dirname($paths['override']));
        File::put($paths['override'], $templateSource);
        $this->persistTemplateTitle($currentSite->id, $themeCode, $template, $validated['template_title'] ?? null);
        $this->clearLegacyTemplateAttachmentRelations($currentSite->id, $themeCode, $template);

        $this->logOperation(
            'site',
            'theme',
            'create_template',
            $currentSite->id,
            $request->user()->id,
            'theme',
            null,
            ['code' => $themeCode, 'template' => $template],
            $request,
        );

        return redirect()
            ->route('admin.themes.editor', ['site_template_id' => $siteTemplateId, 'template' => $template])
            ->with('status', '自定义模板已创建。');
    }

    public function deleteTemplate(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');
        $siteTemplate = $this->resolveEditableSiteTemplate($request, $currentSite);
        if ($siteTemplate instanceof RedirectResponse) {
            return $siteTemplate;
        }
        $siteTemplateId = (int) $siteTemplate->id;
        $themeCode = trim((string) $siteTemplate->template_key);
        $templates = $this->availableTemplates($currentSite->id, $themeCode);
        $template = $this->selectedTemplate($request, $templates);
        $templateMeta = $templates->firstWhere('key', $template);

        abort_unless(($templateMeta['source'] ?? null) === 'custom', 404);

        $paths = $this->themePaths($currentSite->id, $themeCode, $template);
        abort_unless($paths['existing_override'] && File::exists($paths['existing_override']), 404);

        $this->snapshotTemplateState($currentSite->id, $themeCode, $template, 'delete_template', $request->user()->id);
        $this->clearLegacyTemplateAttachmentRelations($currentSite->id, $themeCode, $template);
        File::delete($paths['existing_override']);
        $this->deleteTemplateMeta($currentSite->id, $themeCode, $template);

        $this->logOperation(
            'site',
            'theme',
            'delete_template',
            $currentSite->id,
            $request->user()->id,
            'theme',
            null,
            ['code' => $themeCode, 'template' => $template],
            $request,
        );

        return redirect()
            ->route('admin.themes.editor', ['site_template_id' => $siteTemplateId])
            ->with('status', '自定义模板已删除。');
    }

    public function rollbackTemplate(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');
        $siteTemplate = $this->resolveEditableSiteTemplate($request, $currentSite);
        if ($siteTemplate instanceof RedirectResponse) {
            return $siteTemplate;
        }
        $siteTemplateId = (int) $siteTemplate->id;
        $themeCode = trim((string) $siteTemplate->template_key);

        $templates = $this->availableTemplates($currentSite->id, $themeCode);
        $template = $this->selectedTemplate($request, $templates);
        $versionId = (int) $request->input('version_id', 0);
        $version = $versionId > 0
            ? $this->templateHistory($currentSite->id, $themeCode, $template)->firstWhere('id', $versionId)
            : $this->latestTemplateVersion($currentSite->id, $themeCode, $template);

        if (! $version) {
            return redirect()
                ->route('admin.themes.editor', ['site_template_id' => $siteTemplateId, 'template' => $template])
                ->withErrors(['template' => '当前模板暂无可回滚的历史版本。']);
        }

        $paths = $this->themePaths($currentSite->id, $themeCode, $template);
        $this->snapshotTemplateState($currentSite->id, $themeCode, $template, 'rollback_template', $request->user()->id);

        if (in_array($version->source_type, ['override', 'custom'], true) && $version->template_source !== null) {
            File::ensureDirectoryExists(dirname($paths['override']));
            File::put($paths['override'], $version->template_source);
        } else {
            File::delete($paths['override']);
        }

        $this->clearLegacyTemplateAttachmentRelations($currentSite->id, $themeCode, $template);

        DB::table('site_template_versions')
            ->where('id', $version->id)
            ->update([
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);

        $this->logOperation(
            'site',
            'theme',
            'rollback_template',
            $currentSite->id,
            $request->user()->id,
            'theme',
            null,
            ['code' => $themeCode, 'template' => $template, 'version_id' => $version->id],
            $request,
        );

        return redirect()
            ->route('admin.themes.editor', ['site_template_id' => $siteTemplateId, 'template' => $template])
            ->with('status', '模板已回滚到上一版。');
    }

    public function deleteSnapshot(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');
        $siteTemplate = $this->resolveEditableSiteTemplate($request, $currentSite);
        if ($siteTemplate instanceof RedirectResponse) {
            return $siteTemplate;
        }
        $siteTemplateId = (int) $siteTemplate->id;
        $themeCode = trim((string) $siteTemplate->template_key);

        $templates = $this->availableTemplates($currentSite->id, $themeCode);
        $template = $this->selectedTemplate($request, $templates);
        $versionId = (int) $request->input('version_id', 0);

        abort_unless($versionId > 0, 404);

        $snapshot = $this->templateHistory($currentSite->id, $themeCode, $template)
            ->firstWhere('id', $versionId);

        abort_unless($snapshot, 404);

        DB::table('site_template_versions')
            ->where('id', $versionId)
            ->delete();

        $this->logOperation(
            'site',
            'theme',
            'delete_template_snapshot',
            $currentSite->id,
            $request->user()->id,
            'theme',
            null,
            ['code' => $themeCode, 'template' => $template, 'version_id' => $versionId],
            $request,
        );

        return redirect()
            ->route('admin.themes.snapshots', ['site_template_id' => $siteTemplateId, 'template' => $template])
            ->with('status', '模板快照已删除。');
    }

    public function toggleSnapshotFavorite(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');
        $siteTemplate = $this->resolveEditableSiteTemplate($request, $currentSite);
        if ($siteTemplate instanceof RedirectResponse) {
            return $siteTemplate;
        }
        $siteTemplateId = (int) $siteTemplate->id;
        $themeCode = trim((string) $siteTemplate->template_key);

        $templates = $this->availableTemplates($currentSite->id, $themeCode);
        $template = $this->selectedTemplate($request, $templates);
        $versionId = (int) $request->input('version_id', 0);

        abort_unless($versionId > 0, 404);

        $snapshot = $this->templateHistory($currentSite->id, $themeCode, $template)
            ->firstWhere('id', $versionId);

        abort_unless($snapshot, 404);

        $nextFavoriteState = ! (bool) ($snapshot->is_favorite ?? false);

        DB::table('site_template_versions')
            ->where('id', $versionId)
            ->update([
                'is_favorite' => $nextFavoriteState ? 1 : 0,
                'updated_at' => now(),
            ]);

        $this->logOperation(
            'site',
            'theme',
            $nextFavoriteState ? 'favorite_template_snapshot' : 'unfavorite_template_snapshot',
            $currentSite->id,
            $request->user()->id,
            'theme',
            null,
            ['code' => $themeCode, 'template' => $template, 'version_id' => $versionId],
            $request,
        );

        return redirect()
            ->route('admin.themes.snapshots', ['site_template_id' => $siteTemplateId, 'template' => $template, 'version' => $request->input('version') ?: null])
            ->with('status', $nextFavoriteState ? '模板快照已收藏。' : '模板快照已取消收藏。');
    }

    public function uploadAsset(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');
        $siteTemplate = $this->resolveEditableSiteTemplate($request, $currentSite);
        if ($siteTemplate instanceof RedirectResponse) {
            return $siteTemplate;
        }
        $siteTemplateId = (int) $siteTemplate->id;
        $themeCode = trim((string) $siteTemplate->template_key);

        $validated = $request->validateWithBag('themeAssets', [
            'asset' => ['required', 'file', 'max:10240'],
            'replace_asset_path' => ['nullable', 'string'],
        ], [
            'asset.max' => '模板资源文件不能超过 10MB。',
        ], [
            'asset' => '模板资源文件',
        ]);

        /** @var UploadedFile $file */
        $file = $validated['asset'];
        $replaceAssetPath = ThemeTemplateLocator::normalizeAssetPath((string) ($validated['replace_asset_path'] ?? ''));
        $filename = $this->sanitizeThemeAssetFilename($file);
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, $this->themeUploadAssetExtensions(), true)) {
            return back()
                ->withErrors(['asset' => '仅支持上传图片、字体或 JSON 资源文件。'], 'themeAssets');
        }

        $destinationRoot = ThemeTemplateLocator::overrideRoot(SitePath::key($currentSite), $themeCode);
        $logAction = 'upload_theme_asset';
        $logPath = 'assets/'.$filename;

        if ($replaceAssetPath !== null && str_starts_with($replaceAssetPath, 'assets/')) {
            $targetExtension = strtolower((string) pathinfo($replaceAssetPath, PATHINFO_EXTENSION));

            if ($targetExtension !== $extension) {
                return back()
                    ->withErrors(['asset' => '替换模板资源时，请上传相同扩展名的文件。'], 'themeAssets');
            }

            $overrideTarget = $destinationRoot.DIRECTORY_SEPARATOR.$replaceAssetPath;
            $existingOverrideBytes = File::exists($overrideTarget) && File::isFile($overrideTarget)
                ? (int) File::size($overrideTarget)
                : 0;

            $this->validateThemeAssetReplacementStorageLimit((int) $currentSite->id, (int) $file->getSize(), $existingOverrideBytes);

            $destination = $overrideTarget;
            $logAction = 'replace_theme_asset';
            $logPath = $replaceAssetPath;
        } else {
            $filename = $this->uniqueThemeAssetFilename((int) $currentSite->id, $themeCode, $filename);
            $this->validateThemeAssetStorageLimit((int) $currentSite->id, (int) $file->getSize());
            $destination = $destinationRoot.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.$filename;
            $logPath = 'assets/'.$filename;
        }

        File::ensureDirectoryExists(dirname($destination));
        $file->move(dirname($destination), basename($destination));

        $this->logOperation(
            'site',
            'theme',
            $logAction,
            $currentSite->id,
            $request->user()->id,
            'theme',
            null,
            ['code' => $themeCode, 'path' => $logPath],
            $request,
        );

        return redirect()
            ->route('admin.themes.editor', [
                'site_template_id' => $siteTemplateId,
                'template' => (string) $request->input('template', 'home'),
                'open_assets' => 1,
            ])
            ->with('status', $logAction === 'replace_theme_asset' ? '模板资源已替换。' : '模板资源已上传。');
    }

    public function deleteAsset(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');
        $siteTemplate = $this->resolveEditableSiteTemplate($request, $currentSite);
        if ($siteTemplate instanceof RedirectResponse) {
            return $siteTemplate;
        }
        $siteTemplateId = (int) $siteTemplate->id;
        $themeCode = trim((string) $siteTemplate->template_key);

        $assetPath = ThemeTemplateLocator::normalizeAssetPath((string) $request->input('asset_path', ''));
        abort_unless($assetPath !== null && str_starts_with($assetPath, 'assets/'), 404);

        $overridePath = ThemeTemplateLocator::overrideRoot(SitePath::key($currentSite), $themeCode).DIRECTORY_SEPARATOR.$assetPath;
        abort_unless(File::exists($overridePath) && File::isFile($overridePath), 404);

        File::delete($overridePath);

        $this->logOperation(
            'site',
            'theme',
            'delete_theme_asset',
            $currentSite->id,
            $request->user()->id,
            'theme',
            null,
            ['code' => $themeCode, 'path' => $assetPath],
            $request,
        );

        return redirect()
            ->route('admin.themes.editor', [
                'site_template_id' => $siteTemplateId,
                'template' => (string) $request->input('template', 'home'),
                'open_assets' => 1,
            ])
            ->with('status', '模板资源已删除。');
    }

    /**
     * Resolve default and override paths for the active theme editor file.
     *
     * @return array<string, string>
     */
    protected function themePaths(int $siteId, string $themeCode, string $template): array
    {
        $siteKey = SitePath::key($siteId);

        return [
            'default' => ThemeTemplateLocator::defaultEditorFilePath($themeCode, $template),
            'override' => ThemeTemplateLocator::overrideEditorFilePath($siteKey, $themeCode, $template),
            'existing_override' => ThemeTemplateLocator::existingEditorOverridePath($siteKey, $themeCode, $template),
        ];
    }

    /**
     * Resolve the selected editable template.
     */
    protected function selectedTemplate(Request $request, Collection $templates): string
    {
        $availableFiles = $templates->pluck('key')->all();
        $template = (string) $request->query('template', $request->input('template', $availableFiles[0] ?? 'home'));

        abort_unless(in_array($template, $availableFiles, true), 404);

        return $template;
    }

    /**
     * List available editable theme files.
     *
     * @return \Illuminate\Support\Collection<int, array<string, string>>
     */
    protected function availableTemplates(int $siteId, string $themeCode): Collection
    {
        return ThemeTemplateLocator::availableEditorFilesForSite($siteId, $themeCode);
    }

    protected function latestTemplateVersion(int $siteId, string $themeCode, string $template): ?object
    {
        $siteTemplateId = $this->resolveSiteTemplateId($siteId, $themeCode);

        return DB::table('site_template_versions')
            ->where('site_template_id', $siteTemplateId)
            ->where('template_name', $template)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();
    }

    protected function templateHistory(int $siteId, string $themeCode, string $template): Collection
    {
        $siteTemplateId = $this->resolveSiteTemplateId($siteId, $themeCode);

        return DB::table('site_template_versions')
            ->where('site_template_id', $siteTemplateId)
            ->where('template_name', $template)
            ->whereNull('consumed_at')
            ->orderByDesc('is_favorite')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    protected function selectedTemplateVersion(Request $request, Collection $history): ?object
    {
        $versionId = (int) $request->query('version', 0);

        if ($versionId <= 0) {
            return null;
        }

        return $history->firstWhere('id', $versionId);
    }

    protected function themeAssets(int $siteId, string $themeCode): Collection
    {
        $siteKey = SitePath::key($siteId);
        $items = collect();

        $collectFiles = function (string $root, string $source) use ($items, $themeCode, $siteKey): void {
            if (! File::isDirectory($root)) {
                return;
            }

            collect(File::allFiles($root))
                ->filter(fn ($file): bool => in_array(strtolower($file->getExtension()), $this->themeUploadAssetExtensions(), true))
                ->each(function ($file) use ($root, $source, $items, $themeCode, $siteKey): void {
                    $relative = str_replace('\\', '/', ltrim(str_replace($root, '', $file->getPathname()), DIRECTORY_SEPARATOR));
                    $assetPath = 'assets/'.$relative;
                    $extension = strtolower((string) $file->getExtension());
                    $assetType = match (true) {
                        in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true) => 'image',
                        in_array($extension, ['woff', 'woff2'], true) => 'font',
                        $extension === 'json' => 'json',
                        default => 'file',
                    };
                    $dimensions = $this->themeAssetDimensions($file->getPathname(), $extension);
                    $version = implode('-', [
                        (string) $file->getMTime(),
                        (string) $file->getSize(),
                    ]);
                    $updatedTimestamp = (int) $file->getMTime();
                    $updatedLabel = date('Y-m-d H:i', $updatedTimestamp);

                    $items->put($assetPath, [
                        'path' => $assetPath,
                        'name' => $file->getFilename(),
                        'source' => $source,
                        'url' => route('site.theme-asset', [
                            'theme' => $themeCode,
                            'path' => $assetPath,
                            'site' => $siteKey,
                            'v' => $version,
                        ]),
                        'size' => $file->getSize(),
                        'size_label' => $this->formatThemeAssetSize((int) $file->getSize()),
                        'extension' => $extension,
                        'kind' => $this->themeAssetKind($extension),
                        'asset_type' => $assetType,
                        'dimensions_label' => $dimensions,
                        'updated_at' => $updatedTimestamp,
                        'updated_label' => $updatedLabel,
                        'is_previewable_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true),
                        'show_large_image_warning' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true) && (int) $file->getSize() > (300 * 1024),
                    ]);
                });
        };

        $collectFiles(ThemeTemplateLocator::defaultRoot($themeCode).DIRECTORY_SEPARATOR.'assets', 'default');
        $collectFiles(ThemeTemplateLocator::overrideRoot($siteKey, $themeCode).DIRECTORY_SEPARATOR.'assets', 'override');

        return $items->sort(function (array $left, array $right): int {
            $leftSource = ($left['source'] ?? 'default') === 'override' ? 0 : 1;
            $rightSource = ($right['source'] ?? 'default') === 'override' ? 0 : 1;

            if ($leftSource !== $rightSource) {
                return $leftSource <=> $rightSource;
            }

            $leftUpdated = (int) ($left['updated_at'] ?? 0);
            $rightUpdated = (int) ($right['updated_at'] ?? 0);
            if ($leftUpdated !== $rightUpdated) {
                return $rightUpdated <=> $leftUpdated;
            }

            return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        })->values();
    }

    protected function filterThemeAssets(Collection $assets, string $keyword, string $type): Collection
    {
        $normalizedKeyword = mb_strtolower(trim($keyword));
        $normalizedType = strtolower(trim($type));

        return $assets->filter(function (array $asset) use ($normalizedKeyword, $normalizedType): bool {
            if ($normalizedType !== '' && $normalizedType !== 'all') {
                $assetType = strtolower((string) ($asset['asset_type'] ?? 'file'));
                if ($assetType !== $normalizedType) {
                    return false;
                }
            }

            if ($normalizedKeyword === '') {
                return true;
            }

            $name = mb_strtolower((string) ($asset['name'] ?? ''));
            $path = mb_strtolower((string) ($asset['path'] ?? ''));

            return str_contains($name, $normalizedKeyword) || str_contains($path, $normalizedKeyword);
        })->values();
    }

    protected function validateThemeAssetStorageLimit(int $siteId, int $incomingBytes): void
    {
        $limitMb = SiteStorageUsage::storageLimitMb($siteId);

        if ($limitMb <= 0) {
            return;
        }

        $limitBytes = SiteStorageUsage::storageLimitBytes($siteId);
        $site = DB::table('sites')->where('id', $siteId)->first(['id', 'site_key']);

        if (! $site) {
            return;
        }

        $usedBytes = SiteStorageUsage::totalBytes($site);
        $remainingBytes = max(0, $limitBytes - $usedBytes);

        if (($usedBytes + $incomingBytes) <= $limitBytes) {
            return;
        }

        throw ValidationException::withMessages([
            'asset' => sprintf(
                '当前站点总容量不足，剩余 %s，本次模板资源需要 %s。',
                SiteStorageUsage::formatBytes($remainingBytes),
                SiteStorageUsage::formatBytes($incomingBytes),
            ),
        ])->errorBag('themeAssets');
    }

    protected function validateThemeAssetReplacementStorageLimit(int $siteId, int $incomingBytes, int $currentBytes): void
    {
        $limitMb = SiteStorageUsage::storageLimitMb($siteId);

        if ($limitMb <= 0) {
            return;
        }

        $limitBytes = SiteStorageUsage::storageLimitBytes($siteId);
        $site = DB::table('sites')->where('id', $siteId)->first(['id', 'site_key']);

        if (! $site) {
            return;
        }

        $usedBytes = SiteStorageUsage::totalBytes($site);
        $projectedBytes = max(0, $usedBytes - $currentBytes) + $incomingBytes;
        $remainingBytes = max(0, $limitBytes - max(0, $usedBytes - $currentBytes));

        if ($projectedBytes <= $limitBytes) {
            return;
        }

        throw ValidationException::withMessages([
            'asset' => sprintf(
                '当前站点总容量不足，替换后剩余 %s，本次模板资源需要 %s。',
                SiteStorageUsage::formatBytes($remainingBytes),
                SiteStorageUsage::formatBytes($incomingBytes),
            ),
        ])->errorBag('themeAssets');
    }

    protected function groupTemplatesForWorkspace(Collection $templates): Collection
    {
        $groups = collect([
            ['key' => 'templates', 'title' => '模板文件', 'items' => collect()],
            ['key' => 'tags', 'title' => '模版标签', 'items' => collect()],
            ['key' => 'styles', 'title' => 'CSS 文件', 'items' => collect()],
            ['key' => 'scripts', 'title' => 'JS 文件', 'items' => collect()],
        ])->keyBy('key');

        $templates->each(function (array $template) use ($groups): void {
            $file = (string) ($template['file'] ?? $template['key'] ?? '');
            $groupKey = $this->templateWorkspaceGroupKey($file);

            $group = $groups->get($groupKey);
            $group['items']->push($template);
            $groups->put($groupKey, $group);
        });

        $templateGroup = $groups->get('templates');
        if ($templateGroup) {
            $templateGroup['items'] = $templateGroup['items']
                ->sortBy(fn (array $template): string => (string) ($template['sort_key'] ?? $template['file'] ?? $template['key'] ?? ''))
                ->values();
            $groups->put('templates', $templateGroup);
        }

        $tagGroup = $groups->get('tags');
        if ($tagGroup) {
            $tagGroup['items'] = $tagGroup['items']
                ->sortBy(fn (array $template): string => (string) ($template['sort_key'] ?? $template['file'] ?? $template['key'] ?? ''))
                ->values();
            $groups->put('tags', $tagGroup);
        }

        $styleGroup = $groups->get('styles');
        if ($styleGroup) {
            $styleGroup['items'] = $styleGroup['items']
                ->sortBy(fn (array $template): string => (string) ($template['sort_key'] ?? $template['file'] ?? $template['key'] ?? ''))
                ->values();
            $groups->put('styles', $styleGroup);
        }

        $scriptGroup = $groups->get('scripts');
        if ($scriptGroup) {
            $scriptGroup['items'] = $scriptGroup['items']
                ->sortBy(fn (array $template): string => (string) ($template['sort_key'] ?? $template['file'] ?? $template['key'] ?? ''))
                ->values();
            $groups->put('scripts', $scriptGroup);
        }

        return $groups
            ->values()
            ->filter(fn (array $group): bool => $group['items']->isNotEmpty())
            ->values();
    }

    protected function snapshotTemplateState(int $siteId, string $themeCode, string $template, string $action, ?int $userId = null): void
    {
        $paths = $this->themePaths($siteId, $themeCode, $template);
        $siteTemplateId = $this->resolveSiteTemplateId($siteId, $themeCode);
        $sourceType = 'missing';
        $source = null;

        if ($paths['existing_override'] && File::exists($paths['existing_override'])) {
            $sourceType = File::exists($paths['default']) ? 'override' : 'custom';
            $source = File::get($paths['existing_override']);
        } elseif (File::exists($paths['default'])) {
            $sourceType = 'default';
            $source = File::get($paths['default']);
        }

        DB::table('site_template_versions')->insert([
            'site_template_id' => $siteTemplateId,
            'template_name' => $template,
            'source_type' => $sourceType,
            'template_source' => $source,
            'action' => $action,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $snapshotIdsToDelete = DB::table('site_template_versions')
            ->where('site_template_id', $siteTemplateId)
            ->where('template_name', $template)
            ->whereNull('consumed_at')
            ->orderByDesc('is_favorite')
            ->orderByDesc('id')
            ->pluck('id')
            ->slice(5)
            ->values();

        if ($snapshotIdsToDelete->isNotEmpty()) {
            DB::table('site_template_versions')
                ->whereIn('id', $snapshotIdsToDelete->all())
                ->delete();
        }
    }

    protected function templateCustomTitle(int $siteId, string $themeCode, string $template): ?string
    {
        $siteTemplateId = $this->resolveSiteTemplateId($siteId, $themeCode);

        return DB::table('site_template_meta')
            ->where('site_template_id', $siteTemplateId)
            ->where('template_name', $template)
            ->value('title');
    }

    protected function persistTemplateTitle(int $siteId, string $themeCode, string $template, ?string $title): void
    {
        $title = trim((string) $title);
        $siteTemplateId = $this->resolveSiteTemplateId($siteId, $themeCode);

        DB::table('site_template_meta')->updateOrInsert(
            [
                'site_template_id' => $siteTemplateId,
                'template_name' => $template,
            ],
            [
                'title' => $title !== '' ? $title : null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    protected function deleteTemplateMeta(int $siteId, string $themeCode, string $template): void
    {
        $siteTemplateId = $this->resolveSiteTemplateId($siteId, $themeCode);

        DB::table('site_template_meta')
            ->where('site_template_id', $siteTemplateId)
            ->where('template_name', $template)
            ->delete();
    }

    protected function activeSiteTemplateKey(object $site): string
    {
        return $this->siteThemeCode($site);
    }

    protected function templateDisplayName(int $siteId, string $themeCode): string
    {
        $themeName = DB::table('site_templates')
            ->where('site_id', $siteId)
            ->where('template_key', $themeCode)
            ->value('name');

        return is_string($themeName) && trim($themeName) !== '' ? trim($themeName) : $themeCode;
    }

    protected function resolveEditableSiteTemplate(Request $request, object $site): stdClass|RedirectResponse
    {
        $requestedTemplateId = (int) $request->input('site_template_id', $request->query('site_template_id', 0));

        if ($requestedTemplateId <= 0) {
            $requestedTemplateId = (int) ($site->active_site_template_id ?? 0);
        }

        if ($requestedTemplateId <= 0) {
            return redirect()
                ->route('admin.themes.index')
                ->withErrors(['theme' => '当前站点尚未启用模版，请先在模板管理中启用一个可用模版。']);
        }

        $siteTemplate = DB::table('site_templates')
            ->where('site_id', (int) $site->id)
            ->where('id', $requestedTemplateId)
            ->first(['id', 'site_id', 'name', 'template_key', 'status']);

        if (! $siteTemplate || trim((string) ($siteTemplate->template_key ?? '')) === '') {
            return redirect()
                ->route('admin.themes.index')
                ->withErrors(['theme' => '目标模版不存在或已被删除，请刷新后重试。']);
        }

        return $siteTemplate;
    }

    protected function resolveRequestedTemplateName(array $validated): string
    {
        $prefix = trim((string) ($validated['template_prefix'] ?? ''));
        $suffix = strtolower(trim((string) ($validated['template_suffix'] ?? '')));
        $allowedTplPrefixes = ['list', 'detail', 'page', 'tag'];

        if ($suffix === '') {
            return '';
        }

        return match ($prefix) {
            'css' => $suffix.'.css',
            'js' => $suffix.'.js',
            default => in_array($prefix, $allowedTplPrefixes, true) ? $prefix.'-'.$suffix : '',
        };
    }

    protected function templateWorkspaceGroupKey(string $file): string
    {
        if (str_ends_with($file, '.tpl') && str_starts_with(pathinfo($file, PATHINFO_FILENAME), 'tag-')) {
            return 'tags';
        }

        $extension = ThemeTemplateLocator::editorExtension($file);

        if ($extension === 'css') {
            return 'styles';
        }

        if ($extension === 'js') {
            return 'scripts';
        }

        return 'templates';
    }

    protected function editorSourceFieldLabel(?array $templateMeta): string
    {
        return match ((string) ($templateMeta['extension'] ?? 'tpl')) {
            'css' => 'CSS 样式源码',
            'js' => 'JS 脚本源码',
            default => 'TPL 模板源码',
        };
    }

    protected function validateEditorSource(object $currentSite, string $themeCode, string $template, string $source): void
    {
        $lineFromOffset = static function (int $offset) use ($source): int {
            $offset = max(0, min($offset, strlen($source)));

            return substr_count(substr($source, 0, $offset), "\n") + 1;
        };
        $throwLineError = static function (string $message, int $offset) use ($lineFromOffset): void {
            throw new InvalidArgumentException($message.'（第 '.$lineFromOffset($offset).' 行）');
        };

        if (preg_match('/<\?/', $source, $match, PREG_OFFSET_CAPTURE) === 1) {
            $throwLineError('模板源码中不允许包含 PHP 代码标签。', (int) ($match[0][1] ?? 0));
        }

        if (ThemeTemplateLocator::editorExtension($template) !== 'tpl') {
            return;
        }

        if (preg_match('/(?:https?:)?\/\/[^\s"\')<>]*\/site-media\/|\/site-media\//i', $source, $match, PREG_OFFSET_CAPTURE) === 1) {
            $throwLineError('模板源码中不再支持站点资源，请改用当前主题的模板资源。', (int) ($match[0][1] ?? 0));
        }

        if (preg_match('/<script\b(?![^>]*\bsrc\s*=)[^>]*>/i', $source, $match, PREG_OFFSET_CAPTURE) === 1) {
            $throwLineError('模板源码中不允许使用内联 script，请改用主题脚本文件并通过 themeScript 引入。', (int) ($match[0][1] ?? 0));
        }

        if (preg_match('/<style\b[^>]*>/i', $source, $match, PREG_OFFSET_CAPTURE) === 1) {
            $throwLineError('模板源码中不允许使用内联 style，请改用主题样式文件并通过 themeStyle 引入。', (int) ($match[0][1] ?? 0));
        }

        if (preg_match('/\sstyle\s*=/i', $source, $match, PREG_OFFSET_CAPTURE) === 1) {
            $throwLineError('模板源码中不允许使用内联 style 属性，请改用样式类名和主题样式文件。', (int) ($match[0][1] ?? 0));
        }

        if (preg_match('/\son[a-z]+\s*=/i', $source, $match, PREG_OFFSET_CAPTURE) === 1) {
            $throwLineError('模板源码中不允许使用内联事件属性，请改用主题脚本文件绑定交互。', (int) ($match[0][1] ?? 0));
        }

        $tags = new ThemeTags($currentSite, collect(), collect());
        $engine = new ThemeTemplateEngine(SitePath::key($currentSite), $themeCode, $tags);
        $engine->validateSource($source);
    }

    protected function clearLegacyTemplateAttachmentRelations(int $siteId, string $themeCode, string $template): void
    {
        $siteTemplateId = $this->resolveSiteTemplateId($siteId, $themeCode);
        $templateMetaId = (int) DB::table('site_template_meta')
            ->where('site_template_id', $siteTemplateId)
            ->where('template_name', $template)
            ->value('id');

        if ($templateMetaId <= 0) {
            return;
        }

        $attachmentIds = DB::table('attachment_relations')
            ->join('attachments', 'attachments.id', '=', 'attachment_relations.attachment_id')
            ->where('attachments.site_id', $siteId)
            ->where('attachment_relations.relation_type', 'theme_template')
            ->where('attachment_relations.relation_id', $templateMetaId)
            ->distinct()
            ->pluck('attachment_relations.attachment_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::table('attachment_relations')
            ->where('relation_type', 'theme_template')
            ->where('relation_id', $templateMetaId)
            ->delete();

        if ($attachmentIds !== []) {
            (new AttachmentUsageTracker())->rebuildForAttachmentIds($attachmentIds, $siteId);
        }
    }

    /**
     * @return list<string>
     */
    protected function themeUploadAssetExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'woff', 'woff2', 'json'];
    }

    protected function themeAssetKind(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' => '图片',
            'woff', 'woff2' => '字体',
            'json' => '配置',
            default => strtoupper($extension ?: 'FILE'),
        };
    }

    protected function formatThemeAssetSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1).' MB';
        }

        return number_format($bytes / 1024, 1).' KB';
    }

    protected function themeAssetDimensions(string $path, string $extension): ?string
    {
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return null;
        }

        $size = @getimagesize($path);

        if (! is_array($size) || ! isset($size[0], $size[1])) {
            return null;
        }

        return sprintf('%d×%d', (int) $size[0], (int) $size[1]);
    }

    protected function sanitizeThemeAssetFilename(UploadedFile $file): string
    {
        $originalName = trim((string) $file->getClientOriginalName());
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $basename = strtolower((string) pathinfo($originalName, PATHINFO_FILENAME));
        $basename = preg_replace('/[^a-z0-9_-]+/', '-', $basename) ?? '';
        $basename = trim($basename, '-_');

        if ($basename === '' || $extension === '') {
            throw ValidationException::withMessages([
                'asset' => '模板资源文件名不合法，请重新选择文件。',
            ])->errorBag('themeAssets');
        }

        return $basename.'.'.$extension;
    }

    protected function resolveSiteTemplateId(int $siteId, string $themeCode): int
    {
        return (int) DB::table('site_templates')
            ->where('site_id', $siteId)
            ->where('template_key', $themeCode)
            ->value('id');
    }

    protected function ensureInitialSiteTemplateFiles(string $siteKey, string $templateKey): void
    {
        $root = SitePath::siteTemplateRoot($siteKey, $templateKey);
        ThemeTemplateScaffold::copyDefaultFiles($root);
    }

    /**
     * @param Collection<int, string> $templateKeys
     * @return Collection<int, string>
     */
    protected function orphanTemplateDirectories(string $siteKey, Collection $templateKeys): Collection
    {
        $themeRoot = SitePath::root($siteKey).DIRECTORY_SEPARATOR.'theme';

        if (! File::isDirectory($themeRoot)) {
            return collect();
        }

        $templateKeySet = $templateKeys
            ->map(fn (string $templateKey): string => trim($templateKey))
            ->filter(fn (string $templateKey): bool => $templateKey !== '')
            ->values();

        return collect(File::directories($themeRoot))
            ->map(fn (string $path): string => trim((string) basename($path)))
            ->filter(fn (string $templateKey): bool => $templateKey !== '' && $templateKey !== '.' && $templateKey !== '..')
            ->reject(fn (string $templateKey): bool => $templateKeySet->contains($templateKey))
            ->sort()
            ->values();
    }

    protected function uniqueThemeAssetFilename(int $siteId, string $themeCode, string $filename): string
    {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $basename = (string) pathinfo($filename, PATHINFO_FILENAME);
        $existingPaths = $this->themeAssets($siteId, $themeCode)
            ->pluck('path')
            ->map(fn (string $path): string => strtolower($path))
            ->flip();

        $candidate = $filename;
        $counter = 2;

        while ($existingPaths->has(strtolower('assets/'.$candidate))) {
            $candidate = $basename.'-'.$counter;
            if ($extension !== '') {
                $candidate .= '.'.$extension;
            }
            $counter++;
        }

        return $candidate;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildTemplateDiffRows(string $currentSource, ?string $historySource, bool $historyMissing = false): array
    {
        $currentLines = preg_split("/\r\n|\n|\r/", $currentSource) ?: [''];
        $historyLines = $historyMissing
            ? []
            : (preg_split("/\r\n|\n|\r/", (string) ($historySource ?? '')) ?: ['']);

        $currentCount = count($currentLines);
        $historyCount = count($historyLines);
        $lcs = array_fill(0, $currentCount + 1, array_fill(0, $historyCount + 1, 0));

        for ($i = $currentCount - 1; $i >= 0; $i--) {
            for ($j = $historyCount - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $currentLines[$i] === $historyLines[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $rows = [];
        $i = 0;
        $j = 0;
        $currentLineNumber = 1;
        $historyLineNumber = 1;

        while ($i < $currentCount || $j < $historyCount) {
            if ($i < $currentCount && $j < $historyCount && $currentLines[$i] === $historyLines[$j]) {
                $rows[] = [
                    'current_line_no' => $currentLineNumber++,
                    'history_line_no' => $historyLineNumber++,
                    'current_content' => $currentLines[$i],
                    'history_content' => $historyLines[$j],
                    'is_changed' => false,
                ];
                $i++;
                $j++;
                continue;
            }

            if ($j >= $historyCount || ($i < $currentCount && $lcs[$i + 1][$j] >= $lcs[$i][$j + 1])) {
                $rows[] = [
                    'current_line_no' => $currentLineNumber++,
                    'history_line_no' => null,
                    'current_content' => $currentLines[$i] ?? '',
                    'history_content' => '',
                    'is_changed' => true,
                ];
                $i++;
                continue;
            }

            $rows[] = [
                'current_line_no' => null,
                'history_line_no' => $historyLineNumber++,
                'current_content' => '',
                'history_content' => $historyLines[$j] ?? '',
                'is_changed' => true,
            ];
            $j++;
        }

        if ($historyMissing && $rows === []) {
            $rows[] = [
                'current_line_no' => null,
                'history_line_no' => null,
                'current_content' => '',
                'history_content' => '',
                'is_changed' => true,
                'history_empty_note' => '该历史版本表示创建前为空白状态。',
            ];
        } elseif ($historyMissing) {
            $rows[0]['history_empty_note'] = '该历史版本表示创建前为空白状态。';
        }

        return $this->mergeAdjacentChangedRows($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function mergeAdjacentChangedRows(array $rows): array
    {
        $merged = [];
        $index = 0;
        $count = count($rows);

        while ($index < $count) {
            $current = $rows[$index];
            $next = $rows[$index + 1] ?? null;

            if (
                !empty($current['is_changed']) &&
                !empty($next['is_changed']) &&
                empty($current['is_placeholder']) &&
                empty($next['is_placeholder']) &&
                (($current['current_line_no'] ?? null) === null xor ($current['history_line_no'] ?? null) === null) &&
                (($next['current_line_no'] ?? null) === null xor ($next['history_line_no'] ?? null) === null)
            ) {
                $currentHasCurrent = ($current['current_line_no'] ?? null) !== null;
                $nextHasCurrent = ($next['current_line_no'] ?? null) !== null;

                if ($currentHasCurrent !== $nextHasCurrent) {
                    $left = $currentHasCurrent ? $current : $next;
                    $right = $currentHasCurrent ? $next : $current;

                    $merged[] = [
                        'current_line_no' => $left['current_line_no'] ?? null,
                        'history_line_no' => $right['history_line_no'] ?? null,
                        'current_content' => $left['current_content'] ?? '',
                        'history_content' => $right['history_content'] ?? '',
                        'is_changed' => true,
                    ];
                    $index += 2;
                    continue;
                }
            }

            $merged[] = $current;
            $index++;
        }

        return $merged;
    }

}
