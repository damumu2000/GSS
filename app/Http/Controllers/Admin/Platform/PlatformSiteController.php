<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use App\Support\AttachmentUsageTracker;
use App\Support\Modules\ModuleManager;
use App\Support\Site as SitePath;
use App\Support\ThemeTemplateScaffold;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlatformSiteController extends Controller
{
    public function __construct(
        protected ModuleManager $moduleManager
    ) {
    }

    protected function groupConcatNamesExpression(string $column, string $alias): \Illuminate\Database\Query\Expression
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return DB::raw("GROUP_CONCAT({$column}, '、') AS {$alias}");
        }

        return DB::raw("GROUP_CONCAT({$column} ORDER BY {$column} SEPARATOR '、') AS {$alias}");
    }

    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizePlatform($request, 'site.manage');
        $keyword = trim((string) $request->query('keyword', ''));
        $runStatus = trim((string) $request->query('run_status', ''));
        $expiresSoon = trim((string) $request->query('expires_soon', ''));

        $siteAdminRoleId = (int) DB::table('site_roles')
            ->where('code', 'site_admin')
            ->value('id');

        $managedSites = DB::table('sites')
            ->leftJoinSub(
                DB::table('site_domains')
                    ->select('site_id', DB::raw('MIN(CASE WHEN is_primary = 1 THEN domain END) AS primary_domain'), DB::raw('COUNT(*) AS domain_count'))
                    ->groupBy('site_id'),
                'site_domain_summary',
                'site_domain_summary.site_id',
                '=',
                'sites.id',
            )
            ->leftJoinSub(
                DB::table('site_templates')
                    ->select('site_id', DB::raw('COUNT(*) AS template_count'))
                    ->groupBy('site_id'),
                'site_template_summary',
                'site_template_summary.site_id',
                '=',
                'sites.id',
            )
            ->leftJoinSub(
                DB::table('site_module_bindings')
                    ->join('modules', 'modules.id', '=', 'site_module_bindings.module_id')
                    ->select(
                        'site_module_bindings.site_id',
                        $this->groupConcatNamesExpression('modules.name', 'module_names'),
                    )
                    ->groupBy('site_module_bindings.site_id'),
                'site_module_summary',
                'site_module_summary.site_id',
                '=',
                'sites.id',
            )
            ->leftJoinSub(
                DB::table('site_user_roles')
                    ->join('users', 'users.id', '=', 'site_user_roles.user_id')
                    ->where('site_user_roles.role_id', $siteAdminRoleId)
                    ->select(
                        'site_user_roles.site_id',
                        $this->groupConcatNamesExpression("COALESCE(NULLIF(users.name, ''), users.username)", 'admin_names'),
                    )
                    ->groupBy('site_user_roles.site_id'),
                'site_admin_summary',
                'site_admin_summary.site_id',
                '=',
                'sites.id',
            )
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery->where('sites.name', 'like', '%'.$keyword.'%')
                        ->orWhere('sites.site_key', 'like', '%'.$keyword.'%')
                        ->orWhere('site_domain_summary.primary_domain', 'like', '%'.$keyword.'%')
                        ->orWhere('site_admin_summary.admin_names', 'like', '%'.$keyword.'%');
                });
            })
            ->when(in_array($runStatus, ['1', '0'], true), function ($query) use ($runStatus): void {
                $query->where('sites.status', (int) $runStatus);
            })
            ->when($expiresSoon === '1', function ($query): void {
                $today = now()->startOfDay();
                $thirtyDaysLater = now()->addDays(30)->endOfDay();

                $query->whereNotNull('sites.expires_at')
                    ->whereBetween('sites.expires_at', [$today, $thirtyDaysLater])
                    ->orderBy('sites.expires_at');
            })
            ->when($expiresSoon !== '1', function ($query): void {
                $query->orderByRaw('CASE WHEN sites.expires_at IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('sites.id');
            })
            ->get([
                'sites.id',
                'sites.name',
                'sites.site_key',
                'sites.status',
                'sites.template_limit',
                'sites.opened_at',
                'sites.expires_at',
                'sites.logo',
                'sites.favicon',
                'sites.remark',
                'site_domain_summary.primary_domain',
                'site_domain_summary.domain_count',
                'site_template_summary.template_count',
                'site_module_summary.module_names',
                'site_admin_summary.admin_names',
            ]);

        return view('admin.platform.sites.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'managedSites' => $managedSites,
            'keyword' => $keyword,
            'runStatus' => $runStatus,
            'expiresSoon' => $expiresSoon,
        ]);
    }

    public function create(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizePlatform($request, 'site.manage');

        $candidateAdmins = $this->candidateSiteAdmins();

        return view('admin.platform.sites.create', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'candidateAdmins' => $candidateAdmins,
            'attachmentStorageLimitMb' => (string) old('attachment_storage_limit_mb', '0'),
            'selectedSiteAdminIds' => collect(old('site_admin_ids', [$request->user()->id]))->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function edit(Request $request, string $siteId): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizePlatform($request, 'site.manage');
        $this->moduleManager->synchronize();
        $site = DB::table('sites')->where('id', $siteId)->first();
        abort_unless($site, 404);

        $domains = DB::table('site_domains')
            ->where('site_id', $siteId)
            ->orderByDesc('is_primary')
            ->orderBy('domain')
            ->get();

        $candidateAdmins = $this->candidateSiteAdmins();

        $siteAdminRoleId = (int) DB::table('site_roles')
            ->where('code', 'site_admin')
            ->value('id');

        $selectedSiteAdminIds = DB::table('site_user_roles')
            ->where('site_id', $siteId)
            ->where('role_id', $siteAdminRoleId)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $attachmentStorageLimitMb = (string) (DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'attachment.storage_limit_mb')
            ->value('setting_value') ?? '0');

        $boundModules = DB::table('site_module_bindings')
            ->join('modules', 'modules.id', '=', 'site_module_bindings.module_id')
            ->where('site_module_bindings.site_id', (int) $siteId)
            ->orderBy('modules.sort')
            ->orderBy('modules.name')
            ->get([
                'modules.id',
                'modules.name',
                'modules.code',
                'site_module_bindings.is_trial',
                'site_module_bindings.is_paused',
                'site_module_bindings.created_at',
                'site_module_bindings.updated_at',
            ]);

        $boundModuleIds = $boundModules
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $availableModules = $this->moduleManager
            ->bindableSiteModules()
            ->reject(fn (array $module): bool => in_array((int) ($module['id'] ?? 0), $boundModuleIds, true))
            ->values();

        return view('admin.platform.sites.edit', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'site' => $site,
            'domains' => $domains,
            'candidateAdmins' => $candidateAdmins,
            'boundModules' => $boundModules,
            'availableModules' => $availableModules,
            'attachmentStorageLimitMb' => (string) old('attachment_storage_limit_mb', $attachmentStorageLimitMb),
            'selectedSiteAdminIds' => collect(old('site_admin_ids', $selectedSiteAdminIds))->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePlatform($request, 'site.manage');
        $validated = $this->validateSite($request);
        $siteId = DB::transaction(function () use ($validated, $request): int {
            $siteId = DB::table('sites')->insertGetId([
                'name' => $validated['name'],
                'site_key' => $validated['site_key'],
                'status' => (int) ($validated['status'] ?? 1),
                'template_limit' => (int) ($validated['template_limit'] ?? 1),
                'active_site_template_id' => null,
                'logo' => $validated['logo'] ?? null,
                'favicon' => $validated['favicon'] ?? null,
                'remark' => $validated['remark'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'contact_email' => $validated['contact_email'] ?? null,
                'address' => $validated['address'] ?? null,
                'seo_title' => $validated['seo_title'] ?? $validated['name'],
                'seo_keywords' => $validated['seo_keywords'] ?? null,
                'seo_description' => $validated['seo_description'] ?? null,
                'created_at' => now(),
                'opened_at' => $validated['opened_at'] ?? now(),
                'expires_at' => $validated['expires_at'] ?? null,
                'updated_at' => now(),
            ]);

            $templateId = $this->createInitialSiteTemplate((int) $siteId, (string) $validated['site_key'], (int) $request->user()->id);

            DB::table('sites')->where('id', $siteId)->update([
                'active_site_template_id' => $templateId,
                'updated_at' => now(),
            ]);

            $this->syncSiteDomains($siteId, $validated['domains'] ?? '');
            $this->syncDefaultSiteRolePermissions((int) $siteId);
            $this->syncSiteAdmins($siteId, $validated['site_admin_ids'] ?? []);
            $this->syncSiteSettings((int) $siteId, [
                'attachment.storage_limit_mb' => (string) ($validated['attachment_storage_limit_mb'] ?? 0),
            ], (int) $request->user()->id);

            return (int) $siteId;
        });

        $this->logOperation(
            'platform',
            'site',
            'create',
            (int) $siteId,
            $request->user()->id,
            'site',
            (int) $siteId,
            ['name' => $validated['name'], 'site_key' => $validated['site_key']],
            $request,
        );

        return redirect()->route('admin.platform.sites.edit', $siteId)->with('status', '站点已创建。');
    }

    public function update(Request $request, string $siteId): RedirectResponse
    {
        $this->authorizePlatform($request, 'site.manage');
        $site = DB::table('sites')->where('id', $siteId)->first();
        abort_unless($site, 404);

        $request->merge([
            'site_key' => (string) $site->site_key,
        ]);

        $validated = $this->validateSite($request, $siteId);

        DB::transaction(function () use ($siteId, $site, $validated, $request): void {
            DB::table('sites')->where('id', $siteId)->update([
                'name' => $validated['name'],
                'site_key' => $validated['site_key'],
                'status' => (int) ($validated['status'] ?? 1),
                'template_limit' => (int) ($validated['template_limit'] ?? 1),
                'logo' => $validated['logo'] ?? null,
                'favicon' => $validated['favicon'] ?? null,
                'remark' => $validated['remark'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'contact_email' => $validated['contact_email'] ?? null,
                'address' => $validated['address'] ?? null,
                'seo_title' => $validated['seo_title'] ?? $validated['name'],
                'seo_keywords' => $validated['seo_keywords'] ?? null,
                'seo_description' => $validated['seo_description'] ?? null,
                'opened_at' => $validated['opened_at'] ?? $site->opened_at,
                'expires_at' => $validated['expires_at'] ?? null,
                'updated_at' => now(),
            ]);

            $this->syncSiteDomains($siteId, $validated['domains'] ?? '');
            $this->syncSiteAdmins($siteId, $validated['site_admin_ids'] ?? []);
            $this->syncSiteModules((int) $siteId, $validated['module_ids'] ?? []);
            $this->syncSiteSettings((int) $siteId, [
                'attachment.storage_limit_mb' => (string) ($validated['attachment_storage_limit_mb'] ?? 0),
            ], (int) $request->user()->id);
        });

        $this->logOperation(
            'platform',
            'site',
            'update',
            (int) $siteId,
            $request->user()->id,
            'site',
            (int) $siteId,
            ['name' => $validated['name'], 'site_key' => $validated['site_key']],
            $request,
        );

        return redirect()->route('admin.platform.sites.edit', $siteId)->with('status', '站点信息已更新。');
    }

    public function modules(Request $request, string $siteId): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizePlatform($request, 'site.manage');
        $this->moduleManager->synchronize();

        $site = DB::table('sites')->where('id', (int) $siteId)->first();
        abort_unless($site, 404);

        $boundModules = DB::table('site_module_bindings')
            ->join('modules', 'modules.id', '=', 'site_module_bindings.module_id')
            ->where('site_module_bindings.site_id', (int) $siteId)
            ->orderBy('modules.sort')
            ->orderBy('modules.name')
            ->get([
                'modules.id',
                'modules.name',
                'modules.code',
                'modules.status',
                'site_module_bindings.is_trial',
                'site_module_bindings.is_paused',
                'site_module_bindings.created_at',
                'site_module_bindings.updated_at',
            ]);

        $boundModuleIds = $boundModules
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $availableModules = $this->moduleManager
            ->bindableSiteModules()
            ->reject(fn (array $module): bool => in_array((int) ($module['id'] ?? 0), $boundModuleIds, true))
            ->values();

        return view('admin.platform.sites.modules', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'site' => $site,
            'boundModules' => $boundModules,
            'availableModules' => $availableModules,
        ]);
    }

    public function addModule(Request $request, string $siteId): RedirectResponse
    {
        $this->authorizePlatform($request, 'site.manage');
        $this->moduleManager->synchronize();

        $site = DB::table('sites')->where('id', (int) $siteId)->first();
        abort_unless($site, 404);

        $validated = $request->validate([
            'module_id' => ['required', 'integer', 'exists:modules,id'],
            'is_trial' => ['nullable', 'boolean'],
            'is_paused' => ['nullable', 'boolean'],
        ], [], [
            'module_id' => '模块',
        ]);

        $module = $this->moduleManager->all()->firstWhere('id', (int) $validated['module_id']);
        abort_unless(is_array($module), 404);

        if (
            ($module['scope'] ?? 'site') !== 'site'
            || ! ($module['status'] ?? false)
            || ($module['missing_manifest'] ?? false)
            || ($module['invalid_manifest'] ?? false)
        ) {
            return $this->moduleManagementRedirect($request, (int) $siteId)
                ->withErrors(['module' => '该模块当前不可绑定，请先确认模块状态、作用域和模块文件是否正常。']);
        }

        $bindingQuery = DB::table('site_module_bindings')
            ->where('site_id', (int) $siteId)
            ->where('module_id', (int) $validated['module_id']);

        if ($bindingQuery->exists()) {
            $bindingQuery->update([
                'is_trial' => $request->boolean('is_trial'),
                'is_paused' => $request->boolean('is_paused'),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('site_module_bindings')->insert([
                'site_id' => (int) $siteId,
                'module_id' => (int) $validated['module_id'],
                'is_trial' => $request->boolean('is_trial'),
                'is_paused' => $request->boolean('is_paused'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->logOperation(
            'platform',
            'site',
            'add_site_module_binding',
            (int) $siteId,
            $request->user()->id,
            'site_module_binding',
            (int) $validated['module_id'],
            [
                'module_code' => (string) $module['code'],
                'is_trial' => $request->boolean('is_trial'),
                'is_paused' => $request->boolean('is_paused'),
            ],
            $request,
        );

        return $this->moduleManagementRedirect($request, (int) $siteId)
            ->with('status', '模块已添加到当前站点。');
    }

    public function updateModuleBinding(Request $request, string $siteId, string $moduleId): RedirectResponse
    {
        $this->authorizePlatform($request, 'site.manage');
        $site = DB::table('sites')->where('id', (int) $siteId)->first();
        abort_unless($site, 404);

        $request->validate([
            'is_trial' => ['nullable', 'boolean'],
            'is_paused' => ['nullable', 'boolean'],
        ]);

        $bindingExists = DB::table('site_module_bindings')
            ->where('site_id', (int) $siteId)
            ->where('module_id', (int) $moduleId)
            ->exists();

        abort_unless($bindingExists, 404);

        DB::table('site_module_bindings')
            ->where('site_id', (int) $siteId)
            ->where('module_id', (int) $moduleId)
            ->update([
                'is_trial' => $request->boolean('is_trial'),
                'is_paused' => $request->boolean('is_paused'),
                'updated_at' => now(),
            ]);

        $moduleCode = (string) (DB::table('modules')->where('id', (int) $moduleId)->value('code') ?? '');

        $this->logOperation(
            'platform',
            'site',
            'update_site_module_binding',
            (int) $siteId,
            $request->user()->id,
            'site_module_binding',
            (int) $moduleId,
            [
                'module_code' => $moduleCode,
                'is_trial' => $request->boolean('is_trial'),
                'is_paused' => $request->boolean('is_paused'),
            ],
            $request,
        );

        return $this->moduleManagementRedirect($request, (int) $siteId)
            ->with('status', '模块状态已更新。');
    }

    public function removeModule(Request $request, string $siteId, string $moduleId): RedirectResponse
    {
        $this->authorizePlatform($request, 'site.manage');
        $this->moduleManager->synchronize();

        $site = DB::table('sites')->where('id', (int) $siteId)->first();
        abort_unless($site, 404);

        $binding = DB::table('site_module_bindings')
            ->where('site_id', (int) $siteId)
            ->where('module_id', (int) $moduleId)
            ->first();
        abort_unless($binding, 404);

        $module = $this->moduleManager->all()->firstWhere('id', (int) $moduleId);
        abort_unless(is_array($module), 404);

        $deletedStats = DB::transaction(function () use ($siteId, $moduleId, $module): array {
            $deletedStats = $this->cleanupSiteModuleData((int) $siteId, $module);

            DB::table('site_module_bindings')
                ->where('site_id', (int) $siteId)
                ->where('module_id', (int) $moduleId)
                ->delete();

            return $deletedStats;
        });

        (new AttachmentUsageTracker())->rebuildAll((int) $siteId);

        $this->logOperation(
            'platform',
            'site',
            'remove_site_module_binding',
            (int) $siteId,
            $request->user()->id,
            'site_module_binding',
            (int) $moduleId,
            [
                'module_code' => (string) ($module['code'] ?? ''),
                'cleanup_tables' => $deletedStats['tables'] ?? [],
                'cleanup_settings' => (int) ($deletedStats['settings'] ?? 0),
                'cleanup_relations' => (int) ($deletedStats['relations'] ?? 0),
            ],
            $request,
        );

        return $this->moduleManagementRedirect($request, (int) $siteId)
            ->with('status', '模块已移除，所属站点模块数据已同步清理。');
    }

    protected function moduleManagementRedirect(Request $request, int $siteId): RedirectResponse
    {
        if ((string) $request->input('_module_ui') === 'embedded') {
            return redirect()->route('admin.platform.sites.edit', ['site' => $siteId, 'tab' => 'modules']);
        }

        return redirect()->route('admin.platform.sites.modules', $siteId);
    }

    public function mediaUpload(Request $request): JsonResponse
    {
        $this->authorizePlatform($request, 'site.manage');

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp,ico'],
            'slot' => ['required', 'string', 'in:logo,favicon'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'site_key' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9][a-z0-9\-]*$/'],
        ], [], [
            'file' => '图片文件',
            'slot' => '图片位置',
            'site_id' => '站点编号',
            'site_key' => '站点标识',
        ]);

        $file = $validated['file'];
        $slot = (string) $validated['slot'];
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $siteId = isset($validated['site_id']) ? (int) $validated['site_id'] : null;
        $siteKey = isset($validated['site_key']) ? (string) $validated['site_key'] : null;
        $directory = SitePath::brandMediaRelative($siteId ? $this->resolveSiteKey($siteId) : ($siteKey ?: 'default'));
        $filename = $slot.'.'.$extension;

        $this->deleteSlotVariants($directory, $slot);

        $path = $file->storeAs($directory, $filename, 'site');
        $url = SitePath::urlForStoredPath($path);

        $this->logOperation(
            'platform',
            'site',
            'upload_media',
            null,
            $request->user()->id,
            'site_media',
            null,
            ['name' => $file->getClientOriginalName(), 'url' => $url],
            $request,
        );

        return response()->json([
            'url' => $url,
        ]);
    }

    protected function deleteSlotVariants(string $directory, string $slot): void
    {
        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'] as $extension) {
            $candidate = $directory.'/'.$slot.'.'.$extension;
            if (Storage::disk('site')->exists($candidate)) {
                Storage::disk('site')->delete($candidate);
            }
        }
    }

    protected function validateSite(Request $request, ?string $siteId = null): array
    {
        $request->merge($this->sanitizeSiteInput($request));

        $siteKeyRule = 'unique:sites,site_key';
        if ($siteId) {
            $siteKeyRule .= ','.$siteId;
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'site_key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9][a-z0-9\-]*$/', $siteKeyRule],
            'status' => ['nullable', 'integer', 'in:0,1'],
            'template_limit' => [$siteId ? 'nullable' : 'required', 'integer', 'min:1', 'max:50'],
            'domains' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'string', 'max:255'],
            'favicon' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9\-\+\s()#]{6,50}$/'],
            'contact_email' => ['nullable', 'email:filter', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'attachment_storage_limit_mb' => ['nullable', 'integer', 'min:0', 'max:1048576'],
            'module_ids' => ['nullable', 'array'],
            'module_ids.*' => ['integer', 'exists:modules,id'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_keywords' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'opened_at' => ['nullable', 'date_format:Y-m-d'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:opened_at'],
            'remark' => ['nullable', 'string', 'max:10000'],
            'site_admin_ids' => ['nullable', 'array'],
            'site_admin_ids.*' => ['integer', 'exists:users,id'],
        ], [
            'name.required' => '该项为必填项，请填写内容。',
            'site_key.required' => '该项为必填项，请填写内容。',
            'site_key.regex' => '站点标识只能使用小写字母、数字和中划线，且必须以字母或数字开头。',
            'site_key.unique' => '该站点标识已存在，请更换后重试。',
            'status.in' => '站点状态参数无效，请刷新页面后重试。',
            'contact_phone.regex' => '联系电话格式不正确，请输入有效的电话或手机号。',
            'contact_email.email' => '联系邮箱格式不正确，请重新填写。',
            'opened_at.date_format' => '开通时间格式不正确，请使用 4 位年份日期。',
            'expires_at.date_format' => '到期时间格式不正确，请使用 4 位年份日期。',
            'expires_at.after_or_equal' => '到期时间不能早于开通时间。',
            'site_admin_ids.*.exists' => '所选站点管理员不存在，请刷新页面后重试。',
        ]);

        $validator->after(function ($validator) use ($request, $siteId): void {
            $domains = $this->parseDomains((string) $request->input('domains', ''));
            foreach (['logo', 'favicon'] as $imageField) {
                $value = trim((string) $request->input($imageField, ''));
                if ($value !== '' && ! $this->isValidAssetPath($value)) {
                    $validator->errors()->add($imageField, '图片地址格式不正确，请填写以 / 开头的站内路径或完整的 http/https 地址。');
                }
            }

            foreach ($domains as $domain) {
                if (! $this->isValidDomain($domain)) {
                    $validator->errors()->add('domains', '绑定域名格式不正确，请逐行输入纯域名，例如 site.test。');
                    break;
                }
            }

            if (count($domains) !== count(array_unique(array_map('mb_strtolower', $domains)))) {
                $validator->errors()->add('domains', '绑定域名中存在重复项，请检查后重试。');
            }

            if ($domains === [] && trim((string) $request->input('domains', '')) !== '') {
                $validator->errors()->add('domains', '绑定域名格式不正确，请逐行输入纯域名，例如 site.test。');
            }

            if ($validator->errors()->has('domains') || $domains === []) {
                return;
            }

            $domainConflictQuery = DB::table('site_domains')->whereIn('domain', $domains);

            if ($siteId) {
                $domainConflictQuery->where('site_id', '!=', $siteId);
            }

            if ($domainConflictQuery->exists()) {
                $validator->errors()->add('domains', '存在已被其他站点占用的域名，请检查后重试。');
            }

            $siteAdminIds = collect($request->input('site_admin_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            if ($siteAdminIds->isNotEmpty()) {
                $platformIdentityIds = DB::table('platform_user_roles')
                    ->join('platform_roles', 'platform_roles.id', '=', 'platform_user_roles.role_id')
                    ->whereIn('platform_user_roles.user_id', $siteAdminIds->all())
                    ->distinct()
                    ->pluck('platform_user_roles.user_id');

                if ($platformIdentityIds->isNotEmpty()) {
                    $validator->errors()->add('site_admin_ids', '平台管理员与操作员为两套独立体系，不能直接绑定为站点管理员。');
                }
            }

        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * @return array<string, mixed>
     */
    protected function sanitizeSiteInput(Request $request): array
    {
        $plainTextFields = [
            'name',
            'site_key',
            'logo',
            'favicon',
            'contact_phone',
            'contact_email',
            'address',
            'seo_title',
            'seo_keywords',
            'seo_description',
        ];

        $sanitized = [];

        foreach ($plainTextFields as $field) {
            $value = $request->input($field);

            if (! is_string($value)) {
                $sanitized[$field] = $value;
                continue;
            }

            $cleaned = $this->sanitizePlainText($value);

            if ($field === 'site_key' && is_string($cleaned)) {
                $cleaned = Str::lower($cleaned);
            }

            $sanitized[$field] = $cleaned;
        }

        $sanitized['domains'] = $this->sanitizeDomainsText($request->input('domains'));
        $sanitized['remark'] = $this->sanitizeRichText($request->input('remark'));

        return $sanitized;
    }

    protected function sanitizePlainText(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $cleaned = preg_replace('/[\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}\x{200B}-\x{200D}\x{FEFF}]+/u', '', $value) ?? $value;
        $cleaned = preg_replace('/[ \t]+/u', ' ', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        return $cleaned === '' ? null : $cleaned;
    }

    protected function sanitizeDomainsText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];

        $domains = collect($lines)
            ->map(function (mixed $domain): string {
                $cleaned = (string) ($this->sanitizePlainText((string) $domain) ?? '');

                return Str::lower($cleaned);
            })
            ->filter()
            ->unique()
            ->values();

        if ($domains->isEmpty()) {
            return null;
        }

        return $domains->implode("\n");
    }

    protected function sanitizeRichText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $wrappedHtml = '<!DOCTYPE html><html><body><div id="site-remark-root">'.$trimmed.'</div></body></html>';
        $document = new DOMDocument('1.0', 'UTF-8');

        libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            mb_convert_encoding($wrappedHtml, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        if (! $loaded) {
            return strip_tags($trimmed);
        }

        $root = $document->getElementById('site-remark-root');
        if (! $root instanceof DOMElement) {
            return strip_tags($trimmed);
        }

        foreach (iterator_to_array($root->childNodes) as $childNode) {
            $this->sanitizeRichTextNode($childNode);
        }

        $html = '';
        foreach ($root->childNodes as $childNode) {
            $html .= $document->saveHTML($childNode);
        }

        $html = trim($html);

        return $html === '' ? null : $html;
    }

    protected function sanitizeRichTextNode(DOMNode $node): void
    {
        if ($node->nodeType === XML_COMMENT_NODE) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if ($node->nodeType === XML_TEXT_NODE) {
            $node->nodeValue = preg_replace(
                '/[\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}\x{200B}-\x{200D}\x{FEFF}]+/u',
                '',
                $node->nodeValue ?? '',
            ) ?? ($node->nodeValue ?? '');

            return;
        }

        if (! $node instanceof DOMElement) {
            $node->parentNode?->removeChild($node);

            return;
        }

        $allowedTags = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'blockquote', 'a'];
        $tagName = Str::lower($node->tagName);

        if (! in_array($tagName, $allowedTags, true)) {
            $this->unwrapDomNode($node);

            return;
        }

        foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
            if (! $attribute) {
                continue;
            }

            $attributeName = Str::lower($attribute->nodeName);

            if ($tagName === 'a' && in_array($attributeName, ['href', 'target', 'rel'], true)) {
                continue;
            }

            $node->removeAttribute($attribute->nodeName);
        }

        if ($tagName === 'a') {
            $href = trim((string) $node->getAttribute('href'));

            if ($href === '' || ! $this->isSafeLinkHref($href)) {
                $node->removeAttribute('href');
            }

            $target = trim((string) $node->getAttribute('target'));
            if (! in_array($target, ['_blank', '_self'], true)) {
                $node->removeAttribute('target');
            }

            if ($node->getAttribute('target') === '_blank') {
                $node->setAttribute('rel', 'noopener noreferrer');
            } else {
                $node->removeAttribute('rel');
            }
        }

        foreach (iterator_to_array($node->childNodes) as $childNode) {
            $this->sanitizeRichTextNode($childNode);
        }
    }

    protected function unwrapDomNode(DOMElement $node): void
    {
        $parent = $node->parentNode;
        if (! $parent) {
            return;
        }

        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    protected function isSafeLinkHref(string $href): bool
    {
        $normalized = Str::lower(trim($href));

        return str_starts_with($normalized, '/')
            || str_starts_with($normalized, '#')
            || str_starts_with($normalized, 'http://')
            || str_starts_with($normalized, 'https://')
            || str_starts_with($normalized, 'mailto:')
            || str_starts_with($normalized, 'tel:');
    }

    protected function syncSiteDomains(int|string $siteId, string $domainsText): void
    {
        $domains = $this->parseDomains($domainsText);

        DB::table('site_domains')->where('site_id', $siteId)->delete();

        foreach ($domains as $index => $domain) {
            DB::table('site_domains')->insert([
                'site_id' => $siteId,
                'domain' => $domain,
                'is_primary' => $index === 0,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $module
     * @return array{tables: array<string, int>, settings: int, relations: int}
     */
    protected function cleanupSiteModuleData(int $siteId, array $module): array
    {
        $moduleCode = trim((string) ($module['code'] ?? ''));
        $tableStats = [];

        foreach ((array) ($module['tables'] ?? []) as $table) {
            $tableName = trim((string) $table);

            if (
                $tableName === ''
                || ! Str::startsWith($tableName, 'module_')
                || ! Schema::hasTable($tableName)
                || ! Schema::hasColumn($tableName, 'site_id')
            ) {
                continue;
            }

            $deleted = DB::table($tableName)->where('site_id', $siteId)->delete();
            $tableStats[$tableName] = (int) $deleted;
        }

        $deletedSettings = 0;
        if ($moduleCode !== '') {
            $deletedSettings = DB::table('site_settings')
                ->where('site_id', $siteId)
                ->where('setting_key', 'like', 'module.'.$moduleCode.'.%')
                ->delete();
        }

        $deletedRelations = 0;
        if ($moduleCode === 'guestbook') {
            $siteAttachmentIds = DB::table('attachments')
                ->where('site_id', $siteId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($siteAttachmentIds !== []) {
                $deletedRelations = DB::table('attachment_relations')
                    ->whereIn('attachment_id', $siteAttachmentIds)
                    ->where('relation_type', 'guestbook_setting')
                    ->delete();
            }
        }

        return [
            'tables' => $tableStats,
            'settings' => (int) $deletedSettings,
            'relations' => (int) $deletedRelations,
        ];
    }

    protected function createInitialSiteTemplate(int $siteId, string $siteKey, int $userId): int
    {
        $templateId = DB::table('site_templates')->insertGetId([
            'site_id' => $siteId,
            'name' => '默认模板',
            'template_key' => 'default',
            'status' => 1,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateRoot = SitePath::siteTemplateRoot($siteKey, 'default');
        ThemeTemplateScaffold::copyDefaultFiles($templateRoot);

        return (int) $templateId;
    }

    /**
     * Parse multi-line domains text into unique domain list.
     *
     * @return array<int, string>
     */
    protected function parseDomains(string $domainsText): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $domainsText) ?: [])
            ->map(fn ($domain) => trim((string) $domain))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function isValidDomain(string $domain): bool
    {
        return (bool) preg_match(
            '/^(?=.{1,253}$)(?:(?!-)[a-z0-9-]{1,63}(?<!-)\.)+(?!-)[a-z0-9-]{2,63}(?<!-)$/i',
            $domain,
        );
    }

    protected function isValidAssetPath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://');
    }

    protected function syncDefaultSiteRolePermissions(int $siteId): void
    {
        $defaultSiteRolePermissions = config('cms.default_site_role_permissions', []);

        foreach ($defaultSiteRolePermissions as $roleCode => $permissionCodes) {
            $roleId = DB::table('site_roles')
                ->where('code', $roleCode)
                ->whereNull('site_id')
                ->value('id');

            if (! $roleId) {
                continue;
            }

            $resolvedPermissionCodes = collect($permissionCodes);

            if ($roleCode === 'site_admin') {
                $resolvedPermissionCodes = $resolvedPermissionCodes
                    ->merge($this->moduleManager->currentSiteModulePermissionCodes())
                    ->unique()
                    ->values();
            }

            foreach ($resolvedPermissionCodes as $permissionCode) {
                $permissionId = DB::table('site_permissions')
                    ->where('code', $permissionCode)
                    ->value('id');

                if (! $permissionId) {
                    continue;
                }

                DB::table('site_role_permissions')->updateOrInsert(
                    ['site_id' => $siteId, 'role_id' => $roleId, 'permission_id' => $permissionId],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    protected function syncSiteAdmins(int|string $siteId, array $userIds): void
    {
        $siteAdminRoleId = (int) DB::table('site_roles')
            ->where('code', 'site_admin')
            ->value('id');

        if (! $siteAdminRoleId) {
            return;
        }

        $siteId = (int) $siteId;
        $userIds = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        DB::table('site_user_roles')
            ->where('site_id', $siteId)
            ->where('role_id', $siteAdminRoleId)
            ->delete();

        foreach ($userIds as $userId) {
            DB::table('site_user_roles')->insert([
                'site_id' => $siteId,
                'user_id' => $userId,
                'role_id' => $siteAdminRoleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<int, mixed>  $moduleIds
     */
    protected function syncSiteModules(int $siteId, array $moduleIds): void
    {
        $selectedModuleIds = collect($moduleIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($selectedModuleIds->isEmpty()) {
            DB::table('site_module_bindings')
                ->where('site_id', $siteId)
                ->delete();

            return;
        }

        DB::table('site_module_bindings')
            ->where('site_id', $siteId)
            ->whereNotIn('module_id', $selectedModuleIds->all())
            ->delete();

        foreach ($selectedModuleIds as $moduleId) {
            $exists = DB::table('site_module_bindings')
                ->where('site_id', $siteId)
                ->where('module_id', $moduleId)
                ->exists();

            if ($exists) {
                DB::table('site_module_bindings')
                    ->where('site_id', $siteId)
                    ->where('module_id', $moduleId)
                    ->update([
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('site_module_bindings')->insert([
                'site_id' => $siteId,
                'module_id' => $moduleId,
                'is_trial' => false,
                'is_paused' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param array<string, string> $settings
     */
    protected function syncSiteSettings(int $siteId, array $settings, int $updatedBy): void
    {
        foreach ($settings as $key => $value) {
            DB::table('site_settings')->updateOrInsert(
                ['site_id' => $siteId, 'setting_key' => $key],
                [
                    'setting_value' => $value,
                    'autoload' => 1,
                    'updated_by' => $updatedBy,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    protected function candidateSiteAdmins()
    {
        return DB::table('users')
            ->leftJoin('site_user_roles', 'site_user_roles.user_id', '=', 'users.id')
            ->leftJoin('site_roles', 'site_roles.id', '=', 'site_user_roles.role_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('platform_user_roles')
                    ->join('platform_roles', 'platform_roles.id', '=', 'platform_user_roles.role_id')
                    ->whereColumn('platform_user_roles.user_id', 'users.id');
            })
            ->select(
                'users.id',
                'users.username',
                'users.name',
                'users.email',
                $this->groupConcatNamesExpression('site_roles.name', 'role_names'),
            )
            ->groupBy('users.id', 'users.username', 'users.name', 'users.email')
            ->orderBy('users.id')
            ->get();
    }

    protected function resolveSiteKey(int $siteId): string
    {
        return SitePath::key($siteId);
    }
}
