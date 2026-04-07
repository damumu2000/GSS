<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Support\Modules\ModuleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(
        protected ModuleManager $moduleManager
    ) {
    }

    protected function findRole(int $siteId, string $roleId): ?object
    {
        return DB::table('site_roles')
            ->where(function ($query) use ($siteId): void {
                $query->whereNull('site_id')
                    ->orWhere('site_id', $siteId);
            })
            ->where('id', $roleId)
            ->first();
    }

    protected function isLockedRole(?object $role): bool
    {
        return $role && $role->code === 'site_admin';
    }

    protected function isBuiltInRole(?object $role): bool
    {
        return $role && in_array($role->code, ['site_admin', 'editor', 'reviewer', 'uploader', 'template_editor'], true);
    }

    public function create(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'site.user.manage');

        return view('admin.site.roles.create', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
        ]);
    }

    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'site.user.manage');
        $currentUserRoleId = (int) DB::table('site_user_roles')
            ->where('site_id', $currentSite->id)
            ->where('user_id', $request->user()->id)
            ->value('role_id');

        $siteRoles = $this->siteRolesQuery($currentSite->id)
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'name', 'code', 'description'])
            ->map(function ($role) use ($currentSite, $currentUserRoleId) {
                $permissionNames = DB::table('site_role_permissions')
                    ->join('site_permissions', 'site_permissions.id', '=', 'site_role_permissions.permission_id')
                    ->where('site_role_permissions.site_id', $currentSite->id)
                    ->where('site_role_permissions.role_id', $role->id)
                    ->where('site_permissions.code', '!=', 'module.use')
                    ->orderBy('site_permissions.module')
                    ->orderBy('site_permissions.id')
                    ->pluck('site_permissions.name')
                    ->all();

                $role->permission_count = count($permissionNames);
                $role->permission_names = $permissionNames;
                $role->user_count = (int) DB::table('site_user_roles')
                    ->where('site_id', $currentSite->id)
                    ->where('role_id', $role->id)
                    ->count();
                $role->is_self_role = (int) $role->id === $currentUserRoleId;

                return $role;
            });

        return view('admin.site.roles.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'siteRoles' => $siteRoles,
        ]);
    }

    public function edit(Request $request, string $roleId): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'site.user.manage');

        $this->moduleManager->synchronize();
        $role = $this->findRole($currentSite->id, $roleId);
        abort_unless($role, 404);

        $permissions = DB::table('site_permissions')
            ->orderBy('module')
            ->orderBy('id')
            ->get(['id', 'module', 'name', 'code']);

        $moduleSpecificPermissions = $permissions
            ->filter(fn ($permission): bool => $permission->module === 'module' && $permission->code !== 'module.use')
            ->values();

        $generalPermissions = $permissions
            ->reject(fn ($permission): bool => $permission->module === 'module')
            ->groupBy('module');

        $moduleDefinitions = $this->moduleManager
            ->all()
            ->keyBy(fn (array $module): string => (string) $module['code']);

        $modulePermissionGroups = $moduleSpecificPermissions
            ->groupBy(fn ($permission): string => Str::before((string) $permission->code, '.'))
            ->map(function (Collection $modulePermissions, string $moduleCode) use ($moduleDefinitions): array {
                $moduleDefinition = $moduleDefinitions->get($moduleCode);

                return [
                    'code' => $moduleCode,
                    'name' => is_array($moduleDefinition) ? (string) $moduleDefinition['name'] : Str::headline($moduleCode),
                    'permissions' => $modulePermissions->values(),
                ];
            })
            ->values();

        $selectedPermissionIds = DB::table('site_role_permissions')
            ->where('site_id', $currentSite->id)
            ->where('role_id', $role->id)
            ->pluck('permission_id')
            ->all();

        $visibleSelectedPermissionIds = DB::table('site_role_permissions')
            ->join('site_permissions', 'site_permissions.id', '=', 'site_role_permissions.permission_id')
            ->where('site_role_permissions.site_id', $currentSite->id)
            ->where('site_role_permissions.role_id', $role->id)
            ->where('site_permissions.code', '!=', 'module.use')
            ->pluck('site_role_permissions.permission_id')
            ->all();

        return view('admin.site.roles.edit', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'role' => $role,
            'isLockedRole' => $this->isLockedRole($role),
            'groupedPermissions' => $generalPermissions,
            'modulePermissionGroups' => $modulePermissionGroups,
            'selectedPermissionIds' => $selectedPermissionIds,
            'visibleSelectedPermissionIds' => $visibleSelectedPermissionIds,
            'moduleLabels' => config('cms.permission_modules'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'site.user.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:site_roles,code'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'code.regex' => '角色标识只能使用小写字母、数字和下划线，且必须以字母开头。',
            'code.unique' => '该角色标识已存在，请更换后重试。',
        ]);

        $roleId = DB::table('site_roles')->insertGetId([
            'site_id' => $currentSite->id,
            'name' => trim($validated['name']),
            'code' => trim($validated['code']),
            'description' => trim($validated['description'] ?? '') ?: null,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logOperation(
            'site',
            'site_role',
            'create',
            $currentSite->id,
            $request->user()->id,
            'role',
            $roleId,
            ['role_code' => $validated['code'], 'role_name' => $validated['name']],
            $request,
        );

        return redirect()->route('admin.site-roles.edit', $roleId)->with('status', '操作角色已创建，请继续配置权限。');
    }

    public function update(Request $request, string $roleId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'site.user.manage');

        $role = $this->findRole($currentSite->id, $roleId);
        abort_unless($role, 404);
        if ($this->isLockedRole($role)) {
            return redirect()->route('admin.site-roles.edit', $role->id)->with('status', '站点管理员为系统内置核心角色，不支持编辑。');
        }

        $validated = $request->validate([
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:site_permissions,id'],
        ]);

        $permissionIds = DB::table('site_permissions')
            ->whereIn('id', $validated['permission_ids'] ?? [])
            ->pluck('id')
            ->all();

        $hasModuleSpecificPermission = DB::table('site_permissions')
            ->whereIn('id', $permissionIds)
            ->where('module', 'module')
            ->where('code', '!=', 'module.use')
            ->exists();

        if ($hasModuleSpecificPermission) {
            $moduleUsePermissionId = DB::table('site_permissions')
                ->where('code', 'module.use')
                ->value('id');

            if ($moduleUsePermissionId) {
                $permissionIds[] = (int) $moduleUsePermissionId;
                $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
            }
        }

        DB::transaction(function () use ($role, $permissionIds, $currentSite): void {
            DB::table('site_role_permissions')
                ->where('site_id', $currentSite->id)
                ->where('role_id', $role->id)
                ->delete();

            foreach ($permissionIds as $permissionId) {
                DB::table('site_role_permissions')->insert([
                    'site_id' => $currentSite->id,
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->logOperation(
            'site',
            'site_role',
            'update',
            $currentSite->id,
            $request->user()->id,
            'role',
            $role->id,
            ['role_code' => $role->code, 'permission_ids' => $permissionIds],
            $request,
        );

        return redirect()->route('admin.site-roles.edit', $role->id)->with('status', '操作角色权限已更新。');
    }

    public function destroy(Request $request, string $roleId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'site.user.manage');

        $role = $this->findRole($currentSite->id, $roleId);
        abort_unless($role, 404);
        if ($this->isBuiltInRole($role)) {
            $message = $this->isLockedRole($role)
                ? '站点管理员为系统内置核心角色，不支持删除。'
                : '系统内置角色不支持删除。';

            return redirect()->route('admin.site-roles.index')->with('status', $message);
        }

        $usageCount = DB::table('site_user_roles')
            ->where('site_id', $currentSite->id)
            ->where('role_id', $role->id)
            ->count();

        if ($usageCount > 0) {
            return redirect()->route('admin.site-roles.index')->with('status', '该角色已分配给操作员，暂时不能删除。');
        }

        DB::transaction(function () use ($role, $currentSite): void {
            DB::table('site_role_permissions')->where('site_id', $currentSite->id)->where('role_id', $role->id)->delete();
            DB::table('site_roles')->where('id', $role->id)->delete();
        });

        $this->logOperation(
            'site',
            'site_role',
            'delete',
            $currentSite->id,
            $request->user()->id,
            'role',
            $role->id,
            ['role_code' => $role->code, 'role_name' => $role->name],
            $request,
        );

        return redirect()->route('admin.site-roles.index')->with('status', '操作角色已删除。');
    }
}
