<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use App\Support\Modules\ModuleManager;
use App\Support\Site as SitePath;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->leftJoin('themes', 'themes.id', '=', 'sites.default_theme_id')
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
                        ->orWhere('themes.name', 'like', '%'.$keyword.'%')
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
                'sites.opened_at',
                'sites.expires_at',
                'themes.name as theme_name',
                'sites.logo',
                'sites.favicon',
                'sites.remark',
                'site_domain_summary.primary_domain',
                'site_domain_summary.domain_count',
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
        $this->moduleManager->synchronize();

        $themes = DB::table('themes')
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $candidateAdmins = $this->candidateSiteAdmins();

        return view('admin.platform.sites.create', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'themes' => $themes,
            'modules' => $this->moduleManager->bindableSiteModules(),
            'candidateAdmins' => $candidateAdmins,
            'attachmentStorageLimitMb' => (string) old('attachment_storage_limit_mb', '0'),
            'selectedThemeIds' => collect(old('theme_ids', []))->map(fn ($id) => (int) $id)->all(),
            'selectedModuleIds' => collect(old('module_ids', []))->map(fn ($id) => (int) $id)->all(),
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

        $themes = DB::table('themes')
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

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

        $selectedThemeIds = DB::table('site_theme_bindings')
            ->where('site_id', $siteId)
            ->pluck('theme_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selectedModuleIds = DB::table('site_module_bindings')
            ->where('site_id', $siteId)
            ->pluck('module_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $attachmentStorageLimitMb = (string) (DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'attachment.storage_limit_mb')
            ->value('setting_value') ?? '0');

        $editableModules = $this->moduleManager->bindableSiteModules()
            ->concat($this->moduleManager->boundSiteModules((int) $siteId, false))
            ->keyBy('id')
            ->sortBy([
                ['status', 'desc'],
                ['sort', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        return view('admin.platform.sites.edit', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'site' => $site,
            'domains' => $domains,
            'themes' => $themes,
            'modules' => $editableModules,
            'candidateAdmins' => $candidateAdmins,
            'attachmentStorageLimitMb' => (string) old('attachment_storage_limit_mb', $attachmentStorageLimitMb),
            'selectedThemeIds' => collect(old('theme_ids', $selectedThemeIds))->map(fn ($id) => (int) $id)->all(),
            'selectedModuleIds' => collect(old('module_ids', $selectedModuleIds))->map(fn ($id) => (int) $id)->all(),
            'selectedSiteAdminIds' => collect(old('site_admin_ids', $selectedSiteAdminIds))->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePlatform($request, 'site.manage');
        $this->moduleManager->synchronize();
        $validated = $this->validateSite($request);
        $resolvedDefaultThemeId = $this->resolveDefaultThemeIdFromBindings(
            $validated['theme_ids'] ?? [],
            null,
        );

        $siteId = DB::table('sites')->insertGetId([
            'name' => $validated['name'],
            'site_key' => $validated['site_key'],
            'status' => (int) ($validated['status'] ?? 1),
            'default_theme_id' => $resolvedDefaultThemeId,
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

        $this->syncSiteDomains($siteId, $validated['domains'] ?? '');
        $this->syncDefaultSiteRolePermissions((int) $siteId);
        $this->syncSiteAdmins($siteId, $validated['site_admin_ids'] ?? []);
        $this->syncSiteThemeBindings((int) $siteId, $validated['theme_ids'] ?? []);
        $this->syncSiteModuleBindings((int) $siteId, $validated['module_ids'] ?? []);
        $this->syncSiteSettings((int) $siteId, [
            'attachment.storage_limit_mb' => (string) ($validated['attachment_storage_limit_mb'] ?? 0),
        ], (int) $request->user()->id);

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
        $this->moduleManager->synchronize();
        $site = DB::table('sites')->where('id', $siteId)->first();
        abort_unless($site, 404);

        $request->merge([
            'site_key' => (string) $site->site_key,
        ]);

        $validated = $this->validateSite($request, $siteId);
        $resolvedDefaultThemeId = $this->resolveDefaultThemeIdFromBindings(
            $validated['theme_ids'] ?? [],
            (int) ($site->default_theme_id ?? 0),
        );

        DB::table('sites')->where('id', $siteId)->update([
            'name' => $validated['name'],
            'site_key' => $validated['site_key'],
            'status' => (int) ($validated['status'] ?? 1),
            'default_theme_id' => $resolvedDefaultThemeId,
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
        $this->syncSiteThemeBindings((int) $siteId, $validated['theme_ids'] ?? []);
        $this->syncSiteModuleBindings((int) $siteId, $validated['module_ids'] ?? []);
        $this->syncSiteSettings((int) $siteId, [
            'attachment.storage_limit_mb' => (string) ($validated['attachment_storage_limit_mb'] ?? 0),
        ], (int) $request->user()->id);

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
            'domains' => ['nullable', 'string', 'max:2000'],
            'theme_ids' => ['nullable', 'array'],
            'theme_ids.*' => ['integer', 'exists:themes,id'],
            'module_ids' => ['nullable', 'array'],
            'module_ids.*' => ['integer', 'exists:modules,id'],
            'logo' => ['nullable', 'string', 'max:255'],
            'favicon' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9\-\+\s()#]{6,50}$/'],
            'contact_email' => ['nullable', 'email:filter', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'attachment_storage_limit_mb' => ['nullable', 'integer', 'min:0', 'max:1048576'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_keywords' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'opened_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:opened_at'],
            'remark' => ['nullable', 'string', 'max:10000'],
            'site_admin_ids' => ['nullable', 'array'],
            'site_admin_ids.*' => ['integer', 'exists:users,id'],
        ], [
            'name.required' => '该项为必填项，请填写内容。',
            'site_key.required' => '该项为必填项，请填写内容。',
            'site_key.regex' => '站点标识只能使用小写字母、数字和中划线，且必须以字母或数字开头。',
            'site_key.unique' => '该站点标识已存在，请更换后重试。',
            'status.in' => '站点状态参数无效，请刷新页面后重试。',
            'theme_ids.*.exists' => '所选绑定主题不存在，请刷新页面后重试。',
            'module_ids.*.exists' => '所选绑定模块不存在，请刷新页面后重试。',
            'contact_phone.regex' => '联系电话格式不正确，请输入有效的电话或手机号。',
            'contact_email.email' => '联系邮箱格式不正确，请重新填写。',
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

            $themeIds = collect($request->input('theme_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            $moduleIds = collect($request->input('module_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            if ($moduleIds->isNotEmpty()) {
                $allowedModuleIds = DB::table('modules')
                    ->whereIn('id', $moduleIds->all())
                    ->where('scope', 'site')
                    ->where(function ($query) use ($siteId): void {
                        $query->where('status', 1);

                        if ($siteId) {
                            $query->orWhereIn('id', function ($subQuery) use ($siteId): void {
                                $subQuery->select('module_id')
                                    ->from('site_module_bindings')
                                    ->where('site_id', (int) $siteId);
                            });
                        }
                    })
                    ->pluck('id')
                    ->count();

                if ($allowedModuleIds !== $moduleIds->count()) {
                    $validator->errors()->add('module_ids', $siteId
                        ? '绑定模块中包含未启用且当前站点未绑定的模块，或模块不属于站点端，请刷新页面后重试。'
                        : '绑定模块中包含未启用或不属于站点端的模块，请刷新页面后重试。');
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
     * @param  array<int, mixed>  $themeIds
     */
    protected function syncSiteThemeBindings(int $siteId, array $themeIds): void
    {
        $resolvedThemeIds = collect($themeIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        DB::table('site_theme_bindings')->where('site_id', $siteId)->delete();

        if ($resolvedThemeIds === []) {
            return;
        }

        $timestamp = now();

        DB::table('site_theme_bindings')->insert(
            collect($resolvedThemeIds)->map(fn (int $themeId): array => [
                'site_id' => $siteId,
                'theme_id' => $themeId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all()
        );
    }

    /**
     * @param  array<int, mixed>  $moduleIds
     */
    protected function syncSiteModuleBindings(int $siteId, array $moduleIds): void
    {
        $resolvedModuleIds = collect($moduleIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        DB::table('site_module_bindings')->where('site_id', $siteId)->delete();

        if ($resolvedModuleIds === []) {
            return;
        }

        $timestamp = now();

        DB::table('site_module_bindings')->insert(
            collect($resolvedModuleIds)->map(fn (int $moduleId): array => [
                'site_id' => $siteId,
                'module_id' => $moduleId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all()
        );
    }

    /**
     * @param  array<int, mixed>  $themeIds
     */
    protected function resolveDefaultThemeIdFromBindings(array $themeIds, ?int $currentDefaultThemeId = null): ?int
    {
        $resolvedThemeIds = collect($themeIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($resolvedThemeIds->isEmpty()) {
            return null;
        }

        if ($currentDefaultThemeId && $resolvedThemeIds->contains($currentDefaultThemeId)) {
            return $currentDefaultThemeId;
        }

        return (int) $resolvedThemeIds->first();
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
