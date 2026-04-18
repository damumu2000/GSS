<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlatformRoleController extends Controller
{
    protected function isBuiltInRole(?object $role): bool
    {
        return $role && in_array($role->code, ['super_admin', 'platform_admin'], true);
    }

    protected function isLockedRole(?object $role): bool
    {
        return $role && $role->code === 'super_admin';
    }

    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizePlatform($request, 'platform.role.manage');

        $roles = DB::table('platform_roles')
            ->leftJoin('platform_role_permissions', 'platform_role_permissions.role_id', '=', 'platform_roles.id')
            ->leftJoin('platform_permissions', 'platform_permissions.id', '=', 'platform_role_permissions.permission_id')
            ->leftJoin('platform_user_roles', 'platform_user_roles.role_id', '=', 'platform_roles.id')
            ->select(
                'platform_roles.id',
                'platform_roles.name',
                'platform_roles.code',
                'platform_roles.description',
                'platform_roles.status',
                DB::raw('COUNT(DISTINCT platform_role_permissions.permission_id) AS permission_count'),
                DB::raw('COUNT(DISTINCT platform_user_roles.user_id) AS user_count')
            )
            ->groupBy(
                'platform_roles.id',
                'platform_roles.name',
                'platform_roles.code',
                'platform_roles.description',
                'platform_roles.status'
            )
            ->orderBy('platform_roles.id')
            ->get()
            ->map(function ($role) {
                $role->is_self_role = $this->isCurrentUsersPlatformRole((int) auth()->id(), (int) $role->id);
                $role->is_locked_role = $this->isLockedRole($role);

                return $role;
            });

        return view('admin.platform.roles.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'platformRoles' => $roles,
        ]);
    }

    public function edit(Request $request, string $roleId): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizePlatform($request, 'platform.role.manage');

        $role = DB::table('platform_roles')->where('id', $roleId)->first();
        abort_unless($role, 404);

        $permissions = DB::table('platform_permissions')
            ->orderBy('module')
            ->orderBy('id')
            ->get(['id', 'module', 'name', 'code']);

        $selectedPermissionIds = DB::table('platform_role_permissions')
            ->where('role_id', $role->id)
            ->pluck('permission_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $memberNames = DB::table('platform_user_roles')
            ->join('users', 'users.id', '=', 'platform_user_roles.user_id')
            ->where('platform_user_roles.role_id', $role->id)
            ->orderBy('users.id')
            ->get(['users.name', 'users.username'])
            ->map(fn ($user) => $user->name ?: $user->username)
            ->all();

        return view('admin.platform.roles.edit', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'role' => $role,
            'permissions' => $permissions,
            'selectedPermissionIds' => $selectedPermissionIds,
            'memberNames' => $memberNames,
            'isSelfRole' => $this->isCurrentUsersPlatformRole($request->user()->id, (int) $role->id),
            'isLockedRole' => $this->isLockedRole($role),
        ]);
    }

    public function create(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizePlatform($request, 'platform.role.manage');

        return view('admin.platform.roles.create', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePlatform($request, 'platform.role.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:platform_roles,code'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'code.regex' => '角色标识只能使用小写字母、数字和下划线，且必须以字母开头。',
            'code.unique' => '该角色标识已存在，请更换后重试。',
        ]);

        $roleId = DB::table('platform_roles')->insertGetId([
            'name' => trim($validated['name']),
            'code' => trim($validated['code']),
            'description' => trim($validated['description'] ?? '') ?: null,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logOperation(
            'platform',
            'platform_role',
            'create',
            null,
            $request->user()->id,
            'platform_role',
            $roleId,
            ['role_code' => $validated['code'], 'role_name' => $validated['name']],
            $request,
        );

        return redirect()->route('admin.platform.roles.edit', $roleId)->with('status', '平台角色已创建，请继续配置权限。');
    }

    public function update(Request $request, string $roleId): RedirectResponse
    {
        $this->authorizePlatform($request, 'platform.role.manage');

        $role = DB::table('platform_roles')->where('id', $roleId)->first();
        abort_unless($role, 404);

        if ($this->isLockedRole($role)) {
            return redirect()->route('admin.platform.roles.edit', $role->id)->with('status', '总管理员为系统内置核心角色，不支持编辑。');
        }

        if (! $this->isSuperAdmin($request->user()->id) && $this->isCurrentUsersPlatformRole($request->user()->id, (int) $role->id)) {
            throw ValidationException::withMessages([
                'permission_ids' => '不能修改当前登录账号所属的平台角色权限。',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => [
                'integer',
                Rule::exists('platform_permissions', 'id'),
            ],
        ]);

        DB::table('platform_roles')
            ->where('id', $role->id)
            ->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'updated_at' => now(),
            ]);

        DB::transaction(function () use ($role, $validated): void {
            DB::table('platform_role_permissions')->where('role_id', $role->id)->delete();

            foreach (array_unique($validated['permission_ids'] ?? []) as $permissionId) {
                DB::table('platform_role_permissions')->insert([
                    'role_id' => $role->id,
                    'permission_id' => (int) $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->logOperation(
            'platform',
            'platform_role',
            'update',
            null,
            $request->user()->id,
            'platform_role',
            (int) $role->id,
            ['role' => $role->code],
            $request,
        );

        return redirect()->route('admin.platform.roles.index')->with('status', '平台角色权限已更新。');
    }

    public function destroy(Request $request, string $roleId): RedirectResponse
    {
        $this->authorizePlatform($request, 'platform.role.manage');

        $role = DB::table('platform_roles')->where('id', $roleId)->first();
        abort_unless($role, 404);

        if ($this->isBuiltInRole($role)) {
            $message = $this->isLockedRole($role)
                ? '总管理员为系统内置核心角色，不支持删除。'
                : '系统内置平台角色不支持删除。';

            return redirect()->route('admin.platform.roles.index')->with('status', $message);
        }

        $usageCount = DB::table('platform_user_roles')
            ->where('role_id', $role->id)
            ->count();

        if ($usageCount > 0) {
            return redirect()->route('admin.platform.roles.index')->with('status', '该平台角色已分配给管理员，暂时不能删除。');
        }

        DB::transaction(function () use ($role): void {
            DB::table('platform_role_permissions')->where('role_id', $role->id)->delete();
            DB::table('platform_roles')->where('id', $role->id)->delete();
        });

        $this->logOperation(
            'platform',
            'platform_role',
            'delete',
            null,
            $request->user()->id,
            'platform_role',
            $role->id,
            ['role_code' => $role->code, 'role_name' => $role->name],
            $request,
        );

        return redirect()->route('admin.platform.roles.index')->with('status', '平台角色已删除。');
    }

    protected function isCurrentUsersPlatformRole(int $userId, int $roleId): bool
    {
        return (int) DB::table('platform_user_roles')
            ->where('user_id', $userId)
            ->value('role_id') === $roleId;
    }
}
