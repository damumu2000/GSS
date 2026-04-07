<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Support\Site as SitePath;
use App\Support\ThemeTemplateAttachmentRelationSync;
use App\Support\ThemeTags;
use App\Support\ThemeTemplateEngine;
use App\Support\ThemeTemplateLocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ThemeController extends Controller
{
    /**
     * Display themes available to the current site.
     */
    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.use');

        $themes = DB::table('themes')
            ->join('site_theme_bindings', function ($join) use ($currentSite): void {
                $join->on('site_theme_bindings.theme_id', '=', 'themes.id')
                    ->where('site_theme_bindings.site_id', '=', $currentSite->id);
            })
            ->leftJoin('theme_versions', function ($join): void {
                $join->on('theme_versions.theme_id', '=', 'themes.id')
                    ->where('theme_versions.is_current', '=', 1);
            })
            ->orderByRaw('CASE WHEN themes.id = ? THEN 0 ELSE 1 END', [(int) $currentSite->default_theme_id])
            ->orderBy('themes.name')
            ->get([
                'themes.id',
                'themes.name',
                'themes.code',
                'themes.description',
                'themes.cover_image',
                'theme_versions.version',
            ])
            ->map(function (object $theme) use ($currentSite): object {
                $templateCount = ThemeTemplateLocator::availableTemplatesForSite($currentSite->id, (string) $theme->code)->count();
                $theme->template_count = $templateCount;
                $theme->has_templates = $templateCount > 0;

                return $theme;
            });

        $activeTheme = $this->activeBoundThemeCode($currentSite);
        $activeThemeItem = $themes->firstWhere('code', $activeTheme);
        $libraryThemes = $themes
            ->reject(fn (object $theme): bool => $activeTheme !== '' && $theme->code === $activeTheme)
            ->values();

        return view('admin.site.themes.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'themes' => $themes,
            'activeTheme' => $activeTheme,
            'activeThemeItem' => $activeThemeItem,
            'libraryThemes' => $libraryThemes,
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
            'theme_code' => ['required', 'string', 'max:50'],
        ]);

        $theme = DB::table('themes')
            ->join('site_theme_bindings', function ($join) use ($currentSite): void {
                $join->on('site_theme_bindings.theme_id', '=', 'themes.id')
                    ->where('site_theme_bindings.site_id', '=', $currentSite->id);
            })
            ->where('code', $validated['theme_code'])
            ->first(['themes.*']);

        abort_unless($theme, 404);

        DB::table('sites')
            ->where('id', $currentSite->id)
            ->update([
                'default_theme_id' => $theme->id,
                'updated_at' => now(),
            ]);

        $this->logOperation(
            'site',
            'theme',
            'switch',
            $currentSite->id,
            $request->user()->id,
            'theme',
            $theme->id,
            ['code' => $theme->code],
            $request,
        );

        return redirect()
            ->route('admin.themes.index')
            ->with('status', '站点主题已切换。');
    }

    /**
     * Display the source editor for the active theme home template.
     */
    public function editor(Request $request): View|RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');
        $themeCode = $this->requireCurrentBoundTheme($currentSite);
        if ($themeCode instanceof RedirectResponse) {
            return $themeCode;
        }
        $templates = $this->availableTemplates($currentSite->id, $themeCode);
        if ($templates->isEmpty()) {
            return redirect()
                ->route('admin.themes.index')
                ->withErrors(['theme' => '当前启用主题未提供可编辑的模板文件，请先补充主题模板后再进入模板编辑。']);
        }
        $template = $this->selectedTemplate($request, $templates);
        $templateMeta = $templates->firstWhere('file', $template);
        $workspacePanel = in_array((string) $request->query('panel', 'editor'), ['editor', 'create', 'snapshots'], true)
            ? (string) $request->query('panel', 'editor')
            : 'editor';

        $paths = $this->themePaths($currentSite->id, $themeCode, $template);
        $templateSource = $paths['existing_override'] && File::exists($paths['existing_override'])
            ? File::get($paths['existing_override'])
            : (File::exists($paths['default']) ? File::get($paths['default']) : '');
        $latestVersion = $this->latestTemplateVersion($currentSite->id, $themeCode, $template);
        $starterOptionGroups = $this->starterTemplateOptionGroups($templates, $template);
        $starterOptions = $starterOptionGroups
            ->flatMap(fn (array $group): Collection => collect($group['items']))
            ->values();
        $starterRecommendations = $this->starterTemplateRecommendations($templates, $template);
        $templateHistory = $this->templateHistory($currentSite->id, $themeCode, $template);
        $compareVersion = $this->selectedTemplateVersion($request, $templateHistory);
        $diffRows = $compareVersion
            ? $this->buildTemplateDiffRows(
                $templateSource,
                $compareVersion->template_source,
                ($compareVersion->source_type ?? null) === 'missing'
            )
            : [];

        return view('admin.site.themes.editor', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'themeCode' => $themeCode,
            'themeName' => $this->themeDisplayName($themeCode),
            'workspacePanel' => $workspacePanel,
            'template' => $template,
            'currentTemplate' => $template,
            'templates' => $templates,
            'templateGroups' => $this->groupTemplatesForWorkspace($templates),
            'templateMeta' => $templateMeta,
            'templateTitle' => (string) ($this->templateCustomTitle($currentSite->id, $themeCode, $template) ?? ''),
            'templateSource' => $templateSource,
            'latestTemplateVersion' => $latestVersion,
            'starterOptionGroups' => $starterOptionGroups,
            'starterOptions' => $starterOptions,
            'starterRecommendations' => $starterRecommendations,
            'templateHistory' => $templateHistory,
            'compareVersion' => $compareVersion,
            'diffRows' => $diffRows,
            'attachmentLibraryWorkspaceAccess' => $this->canAccessAttachmentWorkspace((int) $request->user()->id, (int) $currentSite->id),
            'templateQuickGuideUrl' => url('/docs/theme-template-quick-reference.html'),
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
        $themeCode = $this->requireCurrentBoundTheme($currentSite);
        if ($themeCode instanceof RedirectResponse) {
            return $themeCode;
        }
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
        $tags = new ThemeTags($currentSite, collect(), collect());
        $engine = new ThemeTemplateEngine(SitePath::key($currentSite), $themeCode, $tags);

        try {
            $engine->validateSource($validated['template_source']);
        } catch (\Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['template_source' => $exception->getMessage()]);
        }

        $inaccessibleAttachmentIds = $this->inaccessibleTemplateAttachmentIds(
            (int) $currentSite->id,
            (int) $request->user()->id,
            (string) $validated['template_source'],
        );

        if ($inaccessibleAttachmentIds !== []) {
            return back()
                ->withInput()
                ->withErrors(['template_source' => '模板源码中包含不可访问的站点资源，请重新从可用资源中选择。']);
        }

        $this->snapshotTemplateState($currentSite->id, $themeCode, $template, 'edit_template', $request->user()->id);
        File::ensureDirectoryExists(dirname($paths['override']));
        File::put($paths['override'], $validated['template_source']);
        $this->persistTemplateTitle($currentSite->id, $themeCode, $template, $validated['template_title'] ?? null);
        (new ThemeTemplateAttachmentRelationSync())->syncForTemplate(
            $currentSite->id,
            $themeCode,
            $template,
            $validated['template_source']
        );

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
            ->route('admin.themes.editor', ['template' => $template])
            ->with('status', '模板源码已保存。');
    }

    public function createTemplate(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');
        $themeCode = $this->requireCurrentBoundTheme($currentSite);
        if ($themeCode instanceof RedirectResponse) {
            return $themeCode;
        }

        $availableTemplates = $this->availableTemplates($currentSite->id, $themeCode);
        $validated = $request->validateWithBag('createTemplate', [
            'template_prefix' => ['nullable', 'string', Rule::in(['list', 'detail', 'page'])],
            'template_suffix' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/'],
            'template_title' => ['required', 'string', 'max:10'],
            'starter_template' => ['nullable', 'string', 'max:60'],
            'current_template' => ['nullable', 'string', 'max:60'],
            'template_source' => ['nullable', 'string', 'max:200000'],
        ], [
            'template_suffix.max' => '模板标识不能超过 40 个字符。',
            'template_suffix.regex' => "模板标识格式不正确。\n允许填写：小写字母、数字、中划线（-）和下划线（_），且不能以符号开头或结尾。",
            'template_title.max' => '模板标题不能超过 10 个字。',
        ], [
            'template_prefix' => '模板类型',
            'template_suffix' => '模板标识',
            'template_title' => '模板标题',
            'starter_template' => '基础内容',
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

        $starterTemplate = trim((string) ($validated['starter_template'] ?? 'blank'));
        $starterChoices = $availableTemplates->pluck('file')->push('blank')->push('current')->unique()->all();

        if (! in_array($starterTemplate, $starterChoices, true)) {
            throw ValidationException::withMessages([
                'starter_template' => '所选基础内容无效，请重新选择。',
            ])->errorBag('createTemplate');
        }

        $templateSource = trim((string) ($validated['template_source'] ?? ''));

        if ($templateSource === '') {
            $templateSource = $this->starterTemplateSource(
                $currentSite->id,
                $themeCode,
                $starterTemplate,
                (string) ($validated['current_template'] ?? ''),
                $availableTemplates,
            );
        }

        $tags = new ThemeTags($currentSite, collect(), collect());
        $engine = new ThemeTemplateEngine(SitePath::key($currentSite), $themeCode, $tags);

        try {
            $engine->validateSource($templateSource);
        } catch (\Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['template_source' => $exception->getMessage()], 'createTemplate');
        }

        $inaccessibleAttachmentIds = $this->inaccessibleTemplateAttachmentIds(
            (int) $currentSite->id,
            (int) $request->user()->id,
            $templateSource,
        );

        if ($inaccessibleAttachmentIds !== []) {
            return back()
                ->withInput()
                ->withErrors(['template_source' => '模板源码中包含不可访问的站点资源，请重新从可用资源中选择。'], 'createTemplate');
        }

        $this->snapshotTemplateState($currentSite->id, $themeCode, $template, 'create_template', $request->user()->id);
        File::ensureDirectoryExists(dirname($paths['override']));
        File::put($paths['override'], $templateSource);
        $this->persistTemplateTitle($currentSite->id, $themeCode, $template, $validated['template_title'] ?? null);
        (new ThemeTemplateAttachmentRelationSync())->syncForTemplate(
            $currentSite->id,
            $themeCode,
            $template,
            $templateSource
        );

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
            ->route('admin.themes.editor', ['template' => $template])
            ->with('status', '自定义模板已创建。');
    }

    public function resetTemplate(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');
        $themeCode = $this->requireCurrentBoundTheme($currentSite);
        if ($themeCode instanceof RedirectResponse) {
            return $themeCode;
        }
        $templates = $this->availableTemplates($currentSite->id, $themeCode);
        $template = $this->selectedTemplate($request, $templates);
        $templateMeta = $templates->firstWhere('file', $template);

        abort_unless(($templateMeta['source'] ?? null) === 'override', 404);

        $paths = $this->themePaths($currentSite->id, $themeCode, $template);
        abort_unless($paths['existing_override'] && File::exists($paths['existing_override']), 404);

        $this->snapshotTemplateState($currentSite->id, $themeCode, $template, 'reset_template', $request->user()->id);
        File::delete($paths['existing_override']);
        (new ThemeTemplateAttachmentRelationSync())->syncForTemplate(
            $currentSite->id,
            $themeCode,
            $template,
            File::exists($paths['default']) ? File::get($paths['default']) : ''
        );

        $this->logOperation(
            'site',
            'theme',
            'reset_template',
            $currentSite->id,
            $request->user()->id,
            'theme',
            null,
            ['code' => $themeCode, 'template' => $template],
            $request,
        );

        return redirect()
            ->route('admin.themes.editor', ['template' => $template])
            ->with('status', '站点自定义模板已恢复为平台默认版本。');
    }

    public function deleteTemplate(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');
        $themeCode = $this->requireCurrentBoundTheme($currentSite);
        if ($themeCode instanceof RedirectResponse) {
            return $themeCode;
        }
        $templates = $this->availableTemplates($currentSite->id, $themeCode);
        $template = $this->selectedTemplate($request, $templates);
        $templateMeta = $templates->firstWhere('file', $template);

        abort_unless(($templateMeta['source'] ?? null) === 'custom', 404);

        $paths = $this->themePaths($currentSite->id, $themeCode, $template);
        abort_unless($paths['existing_override'] && File::exists($paths['existing_override']), 404);

        $this->snapshotTemplateState($currentSite->id, $themeCode, $template, 'delete_template', $request->user()->id);
        (new ThemeTemplateAttachmentRelationSync())->clearForTemplate($currentSite->id, $themeCode, $template);
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
            ->route('admin.themes.editor')
            ->with('status', '自定义模板已删除。');
    }

    public function rollbackTemplate(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');
        $themeCode = $this->requireCurrentBoundTheme($currentSite);
        if ($themeCode instanceof RedirectResponse) {
            return $themeCode;
        }

        $templates = $this->availableTemplates($currentSite->id, $themeCode);
        $template = $this->selectedTemplate($request, $templates);
        $versionId = (int) $request->input('version_id', 0);
        $version = $versionId > 0
            ? $this->templateHistory($currentSite->id, $themeCode, $template)->firstWhere('id', $versionId)
            : $this->latestTemplateVersion($currentSite->id, $themeCode, $template);

        if (! $version) {
            return redirect()
                ->route('admin.themes.editor', ['template' => $template])
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

        (new ThemeTemplateAttachmentRelationSync())->syncForTemplate(
            $currentSite->id,
            $themeCode,
            $template,
            in_array($version->source_type, ['override', 'custom'], true)
                ? (string) ($version->template_source ?? '')
                : (File::exists($paths['default']) ? File::get($paths['default']) : '')
        );

        DB::table('site_theme_template_versions')
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
            ->route('admin.themes.editor', ['template' => $template])
            ->with('status', '模板已回滚到上一版。');
    }

    public function deleteSnapshot(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');
        $themeCode = $this->requireCurrentBoundTheme($currentSite);
        if ($themeCode instanceof RedirectResponse) {
            return $themeCode;
        }

        $templates = $this->availableTemplates($currentSite->id, $themeCode);
        $template = $this->selectedTemplate($request, $templates);
        $versionId = (int) $request->input('version_id', 0);

        abort_unless($versionId > 0, 404);

        $snapshot = $this->templateHistory($currentSite->id, $themeCode, $template)
            ->firstWhere('id', $versionId);

        abort_unless($snapshot, 404);

        DB::table('site_theme_template_versions')
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
            ->route('admin.themes.snapshots', ['template' => $template])
            ->with('status', '模板快照已删除。');
    }

    public function toggleSnapshotFavorite(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'theme.edit');
        $themeCode = $this->requireCurrentBoundTheme($currentSite);
        if ($themeCode instanceof RedirectResponse) {
            return $themeCode;
        }

        $templates = $this->availableTemplates($currentSite->id, $themeCode);
        $template = $this->selectedTemplate($request, $templates);
        $versionId = (int) $request->input('version_id', 0);

        abort_unless($versionId > 0, 404);

        $snapshot = $this->templateHistory($currentSite->id, $themeCode, $template)
            ->firstWhere('id', $versionId);

        abort_unless($snapshot, 404);

        $nextFavoriteState = ! (bool) ($snapshot->is_favorite ?? false);

        DB::table('site_theme_template_versions')
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
            ->route('admin.themes.snapshots', ['template' => $template, 'version' => $request->input('version') ?: null])
            ->with('status', $nextFavoriteState ? '模板快照已收藏。' : '模板快照已取消收藏。');
    }

    /**
     * Resolve default and override paths for the active theme template.
     *
     * @return array<string, string>
     */
    protected function themePaths(int $siteId, string $themeCode, string $template): array
    {
        $siteKey = SitePath::key($siteId);

        return [
            'default' => ThemeTemplateLocator::defaultPath($themeCode, $template),
            'override' => ThemeTemplateLocator::overridePath($siteKey, $themeCode, $template),
            'existing_override' => ThemeTemplateLocator::existingOverridePath($siteKey, $themeCode, $template),
        ];
    }

    /**
     * Resolve the selected editable template.
     */
    protected function selectedTemplate(Request $request, Collection $templates): string
    {
        $availableFiles = $templates->pluck('file')->all();
        $template = (string) $request->query('template', $request->input('template', $availableFiles[0] ?? 'home'));

        abort_unless(in_array($template, $availableFiles, true), 404);

        return $template;
    }

    /**
     * List available editable templates.
     *
     * @return \Illuminate\Support\Collection<int, array<string, string>>
     */
    protected function availableTemplates(int $siteId, string $themeCode): Collection
    {
        return ThemeTemplateLocator::availableTemplatesForSite($siteId, $themeCode);
    }

    protected function latestTemplateVersion(int $siteId, string $themeCode, string $template): ?object
    {
        return DB::table('site_theme_template_versions')
            ->where('site_id', $siteId)
            ->where('theme_code', $themeCode)
            ->where('template_name', $template)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();
    }

    protected function templateHistory(int $siteId, string $themeCode, string $template): Collection
    {
        return DB::table('site_theme_template_versions')
            ->where('site_id', $siteId)
            ->where('theme_code', $themeCode)
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

    protected function groupTemplatesForWorkspace(Collection $templates): Collection
    {
        $groups = collect([
            ['key' => 'home', 'title' => '首页模板', 'items' => collect()],
            ['key' => 'list', 'title' => '列表模板', 'items' => collect()],
            ['key' => 'detail', 'title' => '详情模板', 'items' => collect()],
            ['key' => 'page', 'title' => '单页模板', 'items' => collect()],
            ['key' => 'other', 'title' => '其他模板', 'items' => collect()],
            ['key' => 'shared', 'title' => '公共模板', 'items' => collect()],
        ])->keyBy('key');

        $templates->each(function (array $template) use ($groups): void {
            $file = (string) ($template['file'] ?? '');
            $groupKey = $this->templateWorkspaceGroupKey($file);

            $group = $groups->get($groupKey);
            $group['items']->push($template);
            $groups->put($groupKey, $group);
        });

        $sharedGroup = $groups->get('shared');
        if ($sharedGroup) {
            $sharedGroup['items'] = $sharedGroup['items']
                ->sortBy(fn (array $template): int => match ((string) ($template['file'] ?? '')) {
                    'top' => 0,
                    'foot' => 1,
                    default => 99,
                })
                ->values();
            $groups->put('shared', $sharedGroup);
        }

        return $groups
            ->values()
            ->filter(fn (array $group): bool => $group['items']->isNotEmpty())
            ->values();
    }

    protected function snapshotTemplateState(int $siteId, string $themeCode, string $template, string $action, ?int $userId = null): void
    {
        $paths = $this->themePaths($siteId, $themeCode, $template);
        $sourceType = 'missing';
        $source = null;

        if ($paths['existing_override'] && File::exists($paths['existing_override'])) {
            $sourceType = File::exists($paths['default']) ? 'override' : 'custom';
            $source = File::get($paths['existing_override']);
        } elseif (File::exists($paths['default'])) {
            $sourceType = 'default';
            $source = File::get($paths['default']);
        }

        DB::table('site_theme_template_versions')->insert([
            'site_id' => $siteId,
            'theme_code' => $themeCode,
            'template_name' => $template,
            'source_type' => $sourceType,
            'template_source' => $source,
            'action' => $action,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $snapshotIdsToDelete = DB::table('site_theme_template_versions')
            ->where('site_id', $siteId)
            ->where('theme_code', $themeCode)
            ->where('template_name', $template)
            ->whereNull('consumed_at')
            ->orderByDesc('is_favorite')
            ->orderByDesc('id')
            ->pluck('id')
            ->slice(5)
            ->values();

        if ($snapshotIdsToDelete->isNotEmpty()) {
            DB::table('site_theme_template_versions')
                ->whereIn('id', $snapshotIdsToDelete->all())
                ->delete();
        }
    }

    protected function templateCustomTitle(int $siteId, string $themeCode, string $template): ?string
    {
        return DB::table('site_theme_template_meta')
            ->where('site_id', $siteId)
            ->where('theme_code', $themeCode)
            ->where('template_name', $template)
            ->value('title');
    }

    protected function persistTemplateTitle(int $siteId, string $themeCode, string $template, ?string $title): void
    {
        $title = trim((string) $title);
        DB::table('site_theme_template_meta')->updateOrInsert(
            [
                'site_id' => $siteId,
                'theme_code' => $themeCode,
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
        DB::table('site_theme_template_meta')
            ->where('site_id', $siteId)
            ->where('theme_code', $themeCode)
            ->where('template_name', $template)
            ->delete();
    }

    /**
     * @return array<int, int>
     */
    protected function inaccessibleTemplateAttachmentIds(int $siteId, int $userId, string $templateSource): array
    {
        $attachmentIds = (new ThemeTemplateAttachmentRelationSync())->extractAttachmentIds($siteId, $templateSource);

        if ($attachmentIds === []) {
            return [];
        }

        $visibleAttachmentIds = $this->visibleAttachmentIds($siteId, $userId, $attachmentIds);

        return array_values(array_diff($attachmentIds, $visibleAttachmentIds));
    }

    protected function activeBoundThemeCode(object $site): string
    {
        if (empty($site->default_theme_id)) {
            return '';
        }

        return (string) DB::table('themes')
            ->join('site_theme_bindings', function ($join) use ($site): void {
                $join->on('site_theme_bindings.theme_id', '=', 'themes.id')
                    ->where('site_theme_bindings.site_id', '=', $site->id);
            })
            ->where('themes.id', (int) $site->default_theme_id)
            ->value('themes.code');
    }

    protected function themeDisplayName(string $themeCode): string
    {
        $themeName = DB::table('themes')
            ->where('code', $themeCode)
            ->value('name');

        return is_string($themeName) && trim($themeName) !== '' ? trim($themeName) : $themeCode;
    }

    protected function requireCurrentBoundTheme(object $site): string|RedirectResponse
    {
        $themeCode = $this->activeBoundThemeCode($site);

        if ($themeCode !== '') {
            return $themeCode;
        }

        return redirect()
            ->route('admin.themes.index')
            ->withErrors(['theme' => '当前站点尚未启用主题，请先在模板管理中启用一个已绑定主题。']);
    }

    protected function resolveRequestedTemplateName(array $validated): string
    {
        $prefix = trim((string) ($validated['template_prefix'] ?? ''));
        $suffix = strtolower(trim((string) ($validated['template_suffix'] ?? '')));

        if ($suffix === '') {
            return '';
        }

        return $prefix.'-'.$suffix;
    }

    protected function starterTemplateOptionGroups(Collection $templates, string $currentTemplate): Collection
    {
        $selectedTemplateLabel = $templates->firstWhere('file', $currentTemplate)['label'] ?? $currentTemplate;
        $currentTemplateGroupKey = $this->templateWorkspaceGroupKey($currentTemplate);

        $quickStartItems = collect([
            ['value' => 'blank', 'label' => '空白模板骨架', 'group_key' => 'blank'],
            ['value' => 'current', 'label' => '复制当前模板 · '.$selectedTemplateLabel.'（'.$currentTemplate.'.tpl）', 'group_key' => $currentTemplateGroupKey],
        ]);

        $groupedTemplateItems = $this->groupTemplatesForWorkspace(
            $templates->reject(fn (array $templateItem): bool => $templateItem['file'] === $currentTemplate)->values()
        )->map(fn (array $group): array => [
            'key' => $group['key'],
            'title' => $group['title'],
            'items' => $group['items']->map(fn (array $templateItem): array => [
                'value' => $templateItem['file'],
                'label' => $templateItem['label'].'（'.$templateItem['file'].'.tpl）',
                'group_key' => $group['key'],
            ])->values()->all(),
        ]);

        return collect([
            [
                'key' => 'quick-start',
                'title' => '快速开始',
                'items' => $quickStartItems->all(),
            ],
        ])->merge($groupedTemplateItems)->values();
    }

    protected function starterTemplateRecommendations(Collection $templates, string $currentTemplate): array
    {
        $groupedTemplates = $this->groupTemplatesForWorkspace($templates)
            ->keyBy('key');
        $currentTemplateLabel = $templates->firstWhere('file', $currentTemplate)['label'] ?? $currentTemplate;
        $currentTemplateGroupKey = $this->templateWorkspaceGroupKey($currentTemplate);

        return collect([
            'list' => '列表模板',
            'detail' => '详情模板',
            'page' => '单页模板',
        ])->mapWithKeys(function (string $prefixLabel, string $prefix) use ($groupedTemplates, $currentTemplate, $currentTemplateGroupKey, $currentTemplateLabel): array {
            $targetGroupKey = $prefix;
            $recommended = null;

            if ($currentTemplateGroupKey === $targetGroupKey) {
                $recommended = [
                    'value' => 'current',
                    'label' => '复制当前模板 · '.$currentTemplateLabel.'（'.$currentTemplate.'.tpl）',
                    'group_key' => $targetGroupKey,
                ];
            } else {
                $groupItems = collect($groupedTemplates->get($targetGroupKey)['items'] ?? []);
                $targetTemplate = $groupItems->first();

                if (is_array($targetTemplate)) {
                    $recommended = [
                        'value' => (string) ($targetTemplate['file'] ?? ''),
                        'label' => (string) ($targetTemplate['label'] ?? '').'（'.(string) ($targetTemplate['file'] ?? '').'.tpl）',
                        'group_key' => $targetGroupKey,
                    ];
                }
            }

            return [$prefix => [
                'prefix' => $prefix,
                'prefix_label' => $prefixLabel,
                'group_key' => $targetGroupKey,
                'recommended' => $recommended,
            ]];
        })->all();
    }

    protected function templateWorkspaceGroupKey(string $file): string
    {
        return match (true) {
            $file === 'home' => 'home',
            in_array($file, ['top', 'foot'], true) => 'shared',
            $file === 'list' || str_starts_with($file, 'list-') => 'list',
            $file === 'detail' || str_starts_with($file, 'detail-') => 'detail',
            $file === 'page' || str_starts_with($file, 'page-') => 'page',
            default => 'other',
        };
    }

    protected function starterTemplateSource(
        int $siteId,
        string $themeCode,
        string $starterTemplate,
        string $currentTemplate,
        Collection $availableTemplates,
    ): string {
        if ($starterTemplate === 'blank') {
            return "<section class=\"template-block\">\n    <div class=\"template-block__inner\">\n        <h2>自定义模板内容</h2>\n    </div>\n</section>\n";
        }

        if ($starterTemplate === 'current') {
            $currentTemplate = trim($currentTemplate);

            if ($currentTemplate === '' || ! $availableTemplates->pluck('file')->contains($currentTemplate)) {
                throw ValidationException::withMessages([
                    'starter_template' => '当前模板不可用，请改选其他基础内容。',
                ])->errorBag('createTemplate');
            }

            $currentPaths = $this->themePaths($siteId, $themeCode, $currentTemplate);

            if ($currentPaths['existing_override'] && File::exists($currentPaths['existing_override'])) {
                return File::get($currentPaths['existing_override']);
            }

            if (File::exists($currentPaths['default'])) {
                return File::get($currentPaths['default']);
            }
        }

        if (! $availableTemplates->pluck('file')->contains($starterTemplate)) {
            throw ValidationException::withMessages([
                'starter_template' => '所选基础内容不存在，请重新选择。',
            ])->errorBag('createTemplate');
        }

        $starterPaths = $this->themePaths($siteId, $themeCode, $starterTemplate);

        if ($starterPaths['existing_override'] && File::exists($starterPaths['existing_override'])) {
            return File::get($starterPaths['existing_override']);
        }

        if (File::exists($starterPaths['default'])) {
            return File::get($starterPaths['default']);
        }

        throw ValidationException::withMessages([
            'starter_template' => '所选基础内容暂时不可读取，请稍后重试。',
        ])->errorBag('createTemplate');
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
