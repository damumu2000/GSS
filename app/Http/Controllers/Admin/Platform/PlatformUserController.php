<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlatformUserController extends Controller
{
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
        $this->authorizePlatform($request, 'platform.user.manage');

        $platformUsers = $this->platformUsersQuery()
            ->when(! $this->isSuperAdmin($request->user()->id), function ($query): void {
                $query->where('users.id', '!=', $this->superAdminUserId());
            })
            ->select(
                'users.id',
                'users.username',
                'users.name',
                'users.email',
                'users.mobile',
                'users.status',
                $this->groupConcatNamesExpression('platform_roles.name', 'role_names')
            )
            ->groupBy('users.id', 'users.username', 'users.name', 'users.email', 'users.mobile', 'users.status')
            ->orderBy('users.id')
            ->get();

        return view('admin.platform.users.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'platformUsers' => $platformUsers,
        ]);
    }

    public function create(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizePlatform($request, 'platform.user.manage');

        $platformRoles = DB::table('platform_roles')
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'code', 'name']);

        return view('admin.platform.users.create', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'platformRoles' => $platformRoles,
        ]);
    }

    public function edit(Request $request, string $userId): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizePlatform($request, 'platform.user.manage');
        $user = User::query()->find($userId);
        abort_unless($user, 404);
        abort_unless($this->isPlatformIdentity($user->id), 404);
        abort_unless($this->canManagePlatformIdentity($request->user()->id, $user->id), 404);

        $platformRoles = DB::table('platform_roles')
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'code', 'name']);

        $selectedRoleId = (int) DB::table('platform_user_roles')
            ->where('platform_user_roles.user_id', $userId)
            ->orderBy('platform_user_roles.id')
            ->value('role_id');

        $superAdminRoleId = $this->platformRoleIdByCode('super_admin');
        $isSuperAdmin = $this->isSuperAdmin($user->id);

        if ($isSuperAdmin && $superAdminRoleId) {
            $selectedRoleId = $superAdminRoleId;
        }

        return view('admin.platform.users.edit', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'user' => $user,
            'platformRoles' => $platformRoles,
            'selectedRoleId' => $selectedRoleId,
            'superAdminRoleId' => $superAdminRoleId,
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePlatform($request, 'platform.user.manage');
        $validated = $this->validateUser($request);
        $roleId = $this->resolvePlatformRoleId($validated['role_id'] ?? null, null);

        $user = User::query()->create([
            'username' => $validated['username'],
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'status' => 1,
            'password' => $validated['password'],
        ]);

        $this->syncRole($user->id, $roleId);

        $this->logOperation(
            'platform',
            'user',
            'create',
            null,
            $request->user()->id,
            'user',
            $user->id,
            ['username' => $user->username],
            $request,
        );

        return redirect()->route('admin.platform.users.index')->with('status', '平台管理员已创建。');
    }

    public function update(Request $request, string $userId): RedirectResponse
    {
        $this->authorizePlatform($request, 'platform.user.manage');
        $user = User::query()->find($userId);
        abort_unless($user, 404);
        abort_unless($this->isPlatformIdentity($user->id), 404);
        abort_unless($this->canManagePlatformIdentity($request->user()->id, $user->id), 404);

        $validated = $this->validateUser($request, $userId);
        $roleId = $this->resolvePlatformRoleId($validated['role_id'] ?? null, $user);
        $currentRoleId = (int) DB::table('platform_user_roles')
            ->where('user_id', $user->id)
            ->value('role_id');

        if ((int) $request->user()->id === (int) $user->id && $currentRoleId > 0 && $roleId !== $currentRoleId) {
            throw ValidationException::withMessages([
                'role_id' => '不能修改当前登录账号的平台角色。',
            ]);
        }

        $payload = [
            'username' => $validated['username'],
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'status' => 1,
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $user->update($payload);
        $this->syncRole($user->id, $roleId);

        $this->logOperation(
            'platform',
            'user',
            'update',
            null,
            $request->user()->id,
            'user',
            $user->id,
            ['username' => $user->username],
            $request,
        );

        return redirect()->route('admin.platform.users.index')->with('status', '平台管理员已更新。');
    }

    public function destroy(Request $request, string $userId): RedirectResponse
    {
        $this->authorizePlatform($request, 'platform.user.manage');
        $user = User::query()->find($userId);
        abort_unless($user, 404);
        abort_unless($this->isPlatformIdentity($user->id), 404);
        abort_unless($this->canManagePlatformIdentity($request->user()->id, $user->id), 404);

        if ((int) $request->user()->id === (int) $user->id) {
            return redirect()->route('admin.platform.users.index')->with('status', '不能删除当前登录账号。');
        }

        $siteRoleCount = DB::table('site_user_roles')
            ->where('user_id', $user->id)
            ->count();

        if ($siteRoleCount > 0) {
            return redirect()->route('admin.platform.users.index')->with('status', '该平台管理员仍关联站点角色，暂时不能删除。');
        }

        DB::transaction(function () use ($user): void {
            DB::table('platform_user_roles')->where('user_id', $user->id)->delete();
            DB::table('users')->where('id', $user->id)->delete();
        });

        $this->logOperation(
            'platform',
            'user',
            'delete',
            null,
            $request->user()->id,
            'user',
            $user->id,
            ['username' => $user->username],
            $request,
        );

        return redirect()->route('admin.platform.users.index')->with('status', '平台管理员已删除。');
    }

    protected function validateUser(Request $request, ?string $userId = null): array
    {
        $usernameRule = Rule::unique('users', 'username');
        $emailRule = Rule::unique('users', 'email');

        if ($userId) {
            $usernameRule = $usernameRule->ignore($userId);
            $emailRule = $emailRule->ignore($userId);
        }

        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9_-]{3,31}$/', $usernameRule],
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'email' => ['nullable', 'email:filter', 'max:255', $emailRule],
            'mobile' => ['nullable', 'string', 'max:50', 'regex:/^[0-9\-\+\s()#]{6,50}$/'],
            'role_id' => [
                'nullable',
                'integer',
                Rule::exists('platform_roles', 'id')->where(fn ($query) => $query->where('status', 1)),
            ],
            'role_ids' => ['nullable', 'array', 'max:1'],
            'role_ids.*' => [
                'integer',
                Rule::exists('platform_roles', 'id')->where(fn ($query) => $query->where('status', 1)),
            ],
            'password' => $userId
            ? ['nullable', 'string', 'min:8']
            : ['required', 'string', 'min:8'],
        ], [
            'username.required' => '请填写用户名。',
            'username.regex' => '用户名需以字母开头，可使用字母、数字、下划线或短横线，长度 4-32 位。',
            'username.unique' => '该用户名已存在，请更换后重试。',
            'name.required' => '请填写姓名。',
            'name.min' => '姓名至少需要 2 个字符。',
            'name.max' => '姓名不能超过 50 个字符。',
            'email.email' => '邮箱格式不正确，请重新填写。',
            'email.max' => '邮箱长度不能超过 255 个字符。',
            'email.unique' => '该邮箱已存在，请更换后重试。',
            'mobile.regex' => '手机号格式不正确，请输入有效的电话或手机号。',
            'role_id.integer' => '请选择一个平台角色。',
            'role_id.exists' => '所选平台角色无效或已停用，请刷新页面后重试。',
            'role_ids.array' => '请选择一个平台角色。',
            'role_ids.max' => '请选择一个平台角色。',
            'role_ids.*.exists' => '所选平台角色无效或已停用，请刷新页面后重试。',
            'password.required' => '请设置初始密码。',
            'password.min' => $userId ? '重置密码至少需要 8 位。' : '初始密码至少需要 8 位。',
        ]);

        $validator->after(function ($validator) use ($request): void {
            $roleId = $request->input('role_id');
            $roleIds = $request->input('role_ids', []);

            $normalizedRoleId = is_scalar($roleId) && trim((string) $roleId) !== ''
                ? (int) $roleId
                : (is_array($roleIds) && ! empty($roleIds) ? (int) ($roleIds[0] ?? 0) : 0);

            if ($normalizedRoleId <= 0) {
                $validator->errors()->add('role_id', '请选择一个平台角色。');
            }
        });

        $validated = $validator->validate();

        if (! isset($validated['role_id']) && ! empty($validated['role_ids'])) {
            $validated['role_id'] = (int) ($validated['role_ids'][0] ?? 0);
        }

        if (empty($validated['role_id'])) {
            throw ValidationException::withMessages([
                'role_id' => '请选择一个平台角色。',
            ]);
        }

        return $validated;
    }

    protected function syncRole(int $userId, int $roleId): void
    {
        DB::table('platform_user_roles')->where('user_id', $userId)->delete();
        DB::table('site_user_roles')->where('user_id', $userId)->delete();

        DB::table('platform_user_roles')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function resolvePlatformRoleId(mixed $roleId, ?User $user = null): int
    {
        $superAdminRoleId = $this->platformRoleIdByCode('super_admin');

        if ($user && $this->isSuperAdmin($user->id) && $superAdminRoleId) {
            return $superAdminRoleId;
        }

        return (int) $roleId;
    }
    protected function platformUsersQuery()
    {
        return DB::table('users')
            ->join('platform_user_roles', 'platform_user_roles.user_id', '=', 'users.id')
            ->join('platform_roles', function ($join): void {
                $join->on('platform_roles.id', '=', 'platform_user_roles.role_id');
            });
    }

    protected function canManagePlatformIdentity(int $actorId, int $targetUserId): bool
    {
        if ($targetUserId === $this->superAdminUserId() && ! $this->isSuperAdmin($actorId)) {
            return false;
        }

        return true;
    }
}
