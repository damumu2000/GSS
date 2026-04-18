<?php

namespace App\Support\Modules;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModuleManager
{
    protected ?Collection $manifestCache = null;

    protected ?Collection $allCache = null;

    public function __construct(
        protected ModuleRegistry $registry
    ) {
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function all(): Collection
    {
        if ($this->allCache instanceof Collection) {
            return $this->allCache;
        }

        $manifests = $this->manifests()->keyBy('code');

        $rows = DB::table('modules')
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->keyBy('code');

        $resolved = $manifests
            ->map(function (array $manifest, string $code) use ($rows): array {
                $row = $rows->get($code);

                return $this->mergeManifestWithRow($manifest, $row);
            })
            ->merge(
                $rows
                    ->reject(fn ($row, string $code): bool => $manifests->has($code))
                    ->map(fn ($row) => $this->missingManifestModule($row))
                    ->values()
            )
            ->sortBy([
                ['sort', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        return $this->allCache = $resolved;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function bindableSiteModules(): Collection
    {
        return $this->all()
            ->filter(fn (array $module): bool => $module['scope'] === 'site' && $module['status'] && ! ($module['missing_manifest'] ?? false) && ! ($module['invalid_manifest'] ?? false))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function boundSiteModules(int $siteId, bool $enabledOnly = true): Collection
    {
        $modules = $this->all()->keyBy('id');

        return DB::table('site_module_bindings')
            ->where('site_id', $siteId)
            ->orderBy('module_id')
            ->get(['id', 'module_id', 'is_trial', 'is_paused'])
            ->map(function ($row) use ($modules): ?array {
                $module = $modules->get((int) $row->module_id);

                if (! is_array($module)) {
                    return null;
                }

                $module['site_module_binding_id'] = (int) ($row->id ?? 0);
                $module['binding_is_trial'] = (bool) ($row->is_trial ?? false);
                $module['binding_is_paused'] = (bool) ($row->is_paused ?? false);

                return $module;
            })
            ->filter()
            ->when($enabledOnly, fn (Collection $collection) => $collection->filter(fn (array $module): bool => $module['status'] && ! ($module['missing_manifest'] ?? false) && ! ($module['invalid_manifest'] ?? false)))
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        return $this->all()->firstWhere('code', $code);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toggleStatus(string $code): ?array
    {
        $module = $this->findByCode($code);

        if (! $module) {
            return null;
        }

        DB::table('modules')
            ->where('code', $code)
            ->update([
                'status' => ! $module['status'],
                'updated_at' => now(),
            ]);

        $this->allCache = null;

        return $this->findByCode($code);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function synchronize(): Collection
    {
        $this->manifestCache = null;
        $this->allCache = null;

        $manifests = $this->manifests();
        $syncableManifests = $manifests
            ->reject(fn (array $manifest): bool => ($manifest['invalid_manifest'] ?? false) === true)
            ->values();
        $existingRows = DB::table('modules')
            ->when(
                $syncableManifests->isNotEmpty(),
                fn ($query) => $query->whereIn('code', $syncableManifests->pluck('code')->all())
            )
            ->get()
            ->keyBy('code');

        foreach ($syncableManifests as $manifest) {
            $existing = $existingRows->get($manifest['code']);
            $payload = [
                'name' => $manifest['name'],
                'version' => $manifest['version'],
                'scope' => $manifest['scope'],
                'author' => $manifest['author'] ?: null,
                'platform_entry_route' => $manifest['platform_entry_route'],
                'site_entry_route' => $manifest['site_entry_route'],
                'description' => $manifest['description'] ?: null,
                'status' => $existing ? (bool) $existing->status : $manifest['default_enabled'],
                'sort' => (int) $manifest['sort'],
            ];

            if (! $existing) {
                DB::table('modules')->insert(array_merge($payload, [
                    'code' => $manifest['code'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
                continue;
            }

            $hasChanges = (string) $existing->name !== (string) $payload['name']
                || (string) ($existing->version ?? '') !== (string) ($payload['version'] ?? '')
                || (string) ($existing->scope ?? '') !== (string) $payload['scope']
                || (string) ($existing->author ?? '') !== (string) ($payload['author'] ?? '')
                || (string) ($existing->platform_entry_route ?? '') !== (string) ($payload['platform_entry_route'] ?? '')
                || (string) ($existing->site_entry_route ?? '') !== (string) ($payload['site_entry_route'] ?? '')
                || (string) ($existing->description ?? '') !== (string) ($payload['description'] ?? '')
                || (int) ($existing->sort ?? 0) !== (int) $payload['sort'];

            if ($hasChanges) {
                DB::table('modules')
                    ->where('id', (int) $existing->id)
                    ->update(array_merge($payload, [
                        'updated_at' => now(),
                    ]));
            }
        }

        $this->syncDeclaredPermissions($syncableManifests);
        $this->cleanupStaleDeclaredPermissions($syncableManifests);

        return $manifests;
    }

    /**
     * @return array<int, string>
     */
    public function currentSiteModulePermissionCodes(): array
    {
        return $this->all()
            ->filter(fn (array $module): bool => $module['scope'] === 'site' && ! ($module['missing_manifest'] ?? false) && ! ($module['invalid_manifest'] ?? false))
            ->flatMap(fn (array $module): array => array_values(array_filter($module['permissions'] ?? [], fn ($permission): bool => is_string($permission) && trim($permission) !== '')))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  object|null  $row
     * @return array<string, mixed>
     */
    protected function mergeManifestWithRow(array $manifest, object|null $row): array
    {
        return array_merge($manifest, [
            'id' => (int) ($row->id ?? 0),
            'name' => (string) ($row->name ?? $manifest['name']),
            'version' => (string) ($row->version ?? $manifest['version']),
            'scope' => (string) ($row->scope ?? $manifest['scope']),
            'author' => (string) ($row->author ?? $manifest['author']),
            'platform_entry_route' => $row->platform_entry_route ?? $manifest['platform_entry_route'],
            'site_entry_route' => $row->site_entry_route ?? $manifest['site_entry_route'],
            'description' => (string) ($row->description ?? $manifest['description']),
            'status' => (bool) ($row->status ?? $manifest['default_enabled']),
            'sort' => (int) ($row->sort ?? $manifest['sort']),
            'entry_permission' => $this->resolveEntryPermission($manifest),
            'missing_manifest' => false,
            'invalid_manifest' => (bool) ($manifest['invalid_manifest'] ?? false),
            'manifest_error' => $manifest['manifest_error'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function missingManifestModule(object $row): array
    {
        $fallbackPath = app_path('Modules/'.$row->code);

        return [
            'id' => (int) ($row->id ?? 0),
            'name' => (string) ($row->name ?? $row->code ?? '未命名模块'),
            'code' => (string) ($row->code ?? ''),
            'version' => (string) ($row->version ?? '1.0.0'),
            'scope' => (string) ($row->scope ?? 'site'),
            'author' => (string) ($row->author ?? ''),
            'platform_entry_route' => $row->platform_entry_route ?? null,
            'site_entry_route' => $row->site_entry_route ?? null,
            'description' => (string) ($row->description ?? ''),
            'status' => (bool) ($row->status ?? false),
            'sort' => (int) ($row->sort ?? 0),
            'settings' => [],
            'permissions' => [],
            'notes' => ['模块目录或 module.json 已缺失，当前仅保留数据库记录，请检查模块文件。'],
            'path' => $fallbackPath,
            'manifest_path' => $fallbackPath.'/module.json',
            'files' => [],
            'entry_permission' => null,
            'missing_manifest' => true,
            'invalid_manifest' => false,
            'manifest_error' => null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $manifests
     */
    protected function syncDeclaredPermissions(Collection $manifests): void
    {
        foreach ($manifests as $manifest) {
            foreach ($manifest['permissions'] as $permissionCode) {
                if (! is_string($permissionCode) || trim($permissionCode) === '') {
                    continue;
                }

                if (($manifest['scope'] ?? 'site') === 'platform') {
                    $this->syncPermissionDefinition(
                        'platform_permissions',
                        $permissionCode,
                        [
                            'module' => 'module',
                            'name' => $this->permissionLabel($manifest['name'], $permissionCode),
                            'description' => sprintf('%s模块权限：%s', $manifest['name'], $permissionCode),
                        ],
                    );

                    $this->backfillPlatformDefaultRolePermission($permissionCode);

                    continue;
                }

                $permissionWasCreated = $this->syncPermissionDefinition(
                    'site_permissions',
                    $permissionCode,
                    [
                        'module' => 'module',
                        'name' => $this->permissionLabel($manifest['name'], $permissionCode),
                        'description' => sprintf('%s模块权限：%s', $manifest['name'], $permissionCode),
                    ],
                );

                if ($permissionWasCreated) {
                    $this->backfillSiteAdminPermission($permissionCode);
                }
            }
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $manifests
     */
    protected function cleanupStaleDeclaredPermissions(Collection $manifests): void
    {
        $manifestSiteCodes = $manifests
            ->where('scope', 'site')
            ->pluck('code')
            ->filter(fn ($code): bool => is_string($code) && $code !== '')
            ->values()
            ->all();
        $manifestPlatformCodes = $manifests
            ->where('scope', 'platform')
            ->pluck('code')
            ->filter(fn ($code): bool => is_string($code) && $code !== '')
            ->values()
            ->all();

        $declaredSitePermissionCodes = $manifests
            ->where('scope', 'site')
            ->flatMap(fn (array $manifest): array => array_values(array_filter($manifest['permissions'] ?? [], fn ($permission): bool => is_string($permission) && trim($permission) !== '')))
            ->unique()
            ->values()
            ->all();
        $declaredPlatformPermissionCodes = $manifests
            ->where('scope', 'platform')
            ->flatMap(fn (array $manifest): array => array_values(array_filter($manifest['permissions'] ?? [], fn ($permission): bool => is_string($permission) && trim($permission) !== '')))
            ->unique()
            ->values()
            ->all();

        $knownSiteModuleCodes = DB::table('modules')
            ->where('scope', 'site')
            ->pluck('code')
            ->filter(fn ($code): bool => is_string($code) && $code !== '')
            ->values()
            ->all();
        $knownPlatformModuleCodes = DB::table('modules')
            ->where('scope', 'platform')
            ->pluck('code')
            ->filter(fn ($code): bool => is_string($code) && $code !== '')
            ->values()
            ->all();

        $this->cleanupPermissionTable(
            permissionTable: 'site_permissions',
            rolePermissionTable: 'site_role_permissions',
            declaredPermissionCodes: $declaredSitePermissionCodes,
            activeManifestModuleCodes: $manifestSiteCodes,
            knownModuleCodes: $knownSiteModuleCodes,
            reservedPermissionCodes: ['module.use'],
        );

        $this->cleanupPermissionTable(
            permissionTable: 'platform_permissions',
            rolePermissionTable: 'platform_role_permissions',
            declaredPermissionCodes: $declaredPlatformPermissionCodes,
            activeManifestModuleCodes: $manifestPlatformCodes,
            knownModuleCodes: $knownPlatformModuleCodes,
            reservedPermissionCodes: ['module.manage'],
        );
    }

    /**
     * @param  array<int, string>  $declaredPermissionCodes
     * @param  array<int, string>  $activeManifestModuleCodes
     * @param  array<int, string>  $knownModuleCodes
     * @param  array<int, string>  $reservedPermissionCodes
     */
    protected function cleanupPermissionTable(
        string $permissionTable,
        string $rolePermissionTable,
        array $declaredPermissionCodes,
        array $activeManifestModuleCodes,
        array $knownModuleCodes,
        array $reservedPermissionCodes,
    ): void {
        $stalePermissionIds = DB::table($permissionTable)
            ->where('module', 'module')
            ->get(['id', 'code'])
            ->filter(function ($permission) use ($declaredPermissionCodes, $activeManifestModuleCodes, $knownModuleCodes, $reservedPermissionCodes): bool {
                $permissionCode = (string) ($permission->code ?? '');

                if ($permissionCode === '' || in_array($permissionCode, $reservedPermissionCodes, true)) {
                    return false;
                }

                if (in_array($permissionCode, $declaredPermissionCodes, true)) {
                    return false;
                }

                $moduleCode = Str::before($permissionCode, '.');

                if ($moduleCode === '') {
                    return true;
                }

                return in_array($moduleCode, $activeManifestModuleCodes, true)
                    || ! in_array($moduleCode, $knownModuleCodes, true);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values()
            ->all();

        if ($stalePermissionIds === []) {
            return;
        }

        DB::table($rolePermissionTable)
            ->whereIn('permission_id', $stalePermissionIds)
            ->delete();

        DB::table($permissionTable)
            ->whereIn('id', $stalePermissionIds)
            ->delete();
    }

    protected function resolveEntryPermission(array $manifest): ?string
    {
        $permissions = collect($manifest['permissions'] ?? [])
            ->filter(fn ($permission) => is_string($permission) && $permission !== '')
            ->values();

        return $permissions->first(fn (string $permission): bool => str_ends_with($permission, '.view'))
            ?? $permissions->first(fn (string $permission): bool => str_ends_with($permission, '.manage'))
            ?? $permissions->first();
    }

    protected function permissionLabel(string $moduleName, string $permissionCode): string
    {
        $action = match (strrchr($permissionCode, '.') ?: '') {
            '.view' => '查看',
            '.manage' => '管理',
            '.reply' => '回复',
            '.setting' => '配置',
            '.export' => '导出',
            '.audit' => '审核',
            default => '使用',
        };

        return sprintf('%s%s', $action, $moduleName);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function syncPermissionDefinition(string $table, string $code, array $payload): bool
    {
        $existing = DB::table($table)->where('code', $code)->first();

        if (! $existing) {
            DB::table($table)->insert(array_merge($payload, [
                'code' => $code,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            return true;
        }

        $hasChanges = (string) ($existing->module ?? '') !== (string) ($payload['module'] ?? '')
            || (string) ($existing->name ?? '') !== (string) ($payload['name'] ?? '')
            || (string) ($existing->description ?? '') !== (string) ($payload['description'] ?? '');

        if ($hasChanges) {
            DB::table($table)
                ->where('id', (int) $existing->id)
                ->update(array_merge($payload, [
                    'updated_at' => now(),
                ]));
        }

        return false;
    }

    protected function backfillSiteAdminPermission(string $permissionCode): void
    {
        $siteAdminRoleId = (int) DB::table('site_roles')
            ->where('code', 'site_admin')
            ->whereNull('site_id')
            ->value('id');

        $permissionId = (int) DB::table('site_permissions')
            ->where('code', $permissionCode)
            ->value('id');

        if (! $siteAdminRoleId || ! $permissionId) {
            return;
        }

        DB::table('sites')
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($siteId) use ($siteAdminRoleId, $permissionId): void {
                DB::table('site_role_permissions')->updateOrInsert(
                    [
                        'site_id' => (int) $siteId,
                        'role_id' => $siteAdminRoleId,
                        'permission_id' => $permissionId,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });
    }

    protected function backfillPlatformDefaultRolePermission(string $permissionCode): void
    {
        $permissionId = (int) DB::table('platform_permissions')
            ->where('code', $permissionCode)
            ->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('platform_roles')
            ->whereIn('code', ['super_admin', 'platform_admin'])
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($roleId) use ($permissionId): void {
                DB::table('platform_role_permissions')->updateOrInsert(
                    [
                        'role_id' => (int) $roleId,
                        'permission_id' => $permissionId,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });
    }

    protected function manifests(): Collection
    {
        if ($this->manifestCache instanceof Collection) {
            return $this->manifestCache;
        }

        return $this->manifestCache = $this->registry->manifests();
    }
}
