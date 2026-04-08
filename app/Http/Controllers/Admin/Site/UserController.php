<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserAttachmentRelationSync;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class UserController extends Controller
{
    protected function canEditOwnProfile(Request $request, int $siteId, int $userId): bool
    {
        if ((int) $request->user()->id !== $userId) {
            return false;
        }

        return DB::table('site_user_roles')
            ->where('site_id', $siteId)
            ->where('user_id', $userId)
            ->exists();
    }

    protected function syncRequestedSiteContext(Request $request): void
    {
        if (! $request->filled('site_id') || ! $this->isPlatformAdmin($request->user()->id)) {
            return;
        }

        $site = $this->adminSites($request->user()->id)
            ->firstWhere('id', (int) $request->query('site_id'));

        if ($site) {
            $request->session()->put('current_site_id', $site->id);
        }
    }

    protected function groupConcatNamesExpression(string $column, string $alias): \Illuminate\Database\Query\Expression
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return DB::raw("GROUP_CONCAT({$column}, '、') AS {$alias}");
        }

        return DB::raw("GROUP_CONCAT({$column} ORDER BY {$column} SEPARATOR '、') AS {$alias}");
    }

    public function create(Request $request): View
    {
        $this->syncRequestedSiteContext($request);
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'site.user.manage');
        $channels = DB::table('channels')
            ->where('site_id', $currentSite->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'type', 'parent_id', 'depth', 'sort']);

        $contentManageRoleIds = $this->siteRoleIdsWithPermission($currentSite->id, 'content.manage');

        $siteRoles = $this->siteRolesQuery($currentSite->id)
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'name', 'code', 'description'])
            ->map(function ($role) use ($contentManageRoleIds) {
                $role->can_manage_content = in_array((int) $role->id, $contentManageRoleIds, true);

                return $role;
            });

        $selectedRoleId = (int) preg_replace('/^site:/', '', (string) old('role_id', ''));

        return view('admin.site.users.create', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'siteRoles' => $siteRoles,
            'channels' => $this->flattenManagedChannels($channels),
            'selectedRoleId' => $selectedRoleId,
            'selectedChannelIds' => array_values(array_unique(array_filter(array_map('intval', is_array(old('channel_ids')) ? old('channel_ids') : [])))),
            'selectedRoleCanManageContent' => in_array($selectedRoleId, $contentManageRoleIds, true),
            'contentManageRoleIds' => $contentManageRoleIds,
            'avatarAttachmentWorkspaceAccess' => $this->canAccessAttachmentWorkspace((int) $request->user()->id, (int) $currentSite->id)
                || $this->canManageSiteUsers((int) $request->user()->id, (int) $currentSite->id),
            'canManageRoleSelection' => true,
            'canManageStatusSelection' => true,
        ]);
    }

    public function index(Request $request): View
    {
        $this->syncRequestedSiteContext($request);
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'site.user.manage');
        $keyword = trim((string) $request->query('keyword', ''));
        $status = (string) $request->query('status', '');
        $roleId = (string) $request->query('role_id', '');

        $siteUsers = DB::table('users')
            ->join('site_user_roles', function ($join) use ($currentSite): void {
                $join->on('site_user_roles.user_id', '=', 'users.id')
                    ->where('site_user_roles.site_id', '=', $currentSite->id);
            })
            ->leftJoin('site_roles', 'site_roles.id', '=', 'site_user_roles.role_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('platform_user_roles')
                    ->join('platform_roles', 'platform_roles.id', '=', 'platform_user_roles.role_id')
                    ->whereColumn('platform_user_roles.user_id', 'users.id');
            })
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery->where('users.username', 'like', '%'.$keyword.'%')
                        ->orWhere('users.name', 'like', '%'.$keyword.'%')
                        ->orWhere('users.email', 'like', '%'.$keyword.'%')
                        ->orWhere('users.mobile', 'like', '%'.$keyword.'%');
                });
            })
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('users.status', (int) $status);
            })
            ->when($roleId !== '', function ($query) use ($roleId): void {
                $query->where('site_user_roles.role_id', $roleId);
            })
            ->select(
                'users.id',
                'users.username',
                'users.name',
                'users.email',
                'users.mobile',
                'users.avatar',
                'users.status',
                'users.last_login_at',
                'users.last_login_ip',
                'users.created_at',
                $this->groupConcatNamesExpression('site_roles.name', 'role_names')
            )
            ->groupBy('users.id', 'users.username', 'users.name', 'users.email', 'users.mobile', 'users.avatar', 'users.status', 'users.last_login_at', 'users.last_login_ip', 'users.created_at')
            ->orderByDesc('users.created_at')
            ->orderByDesc('users.id')
            ->paginate(9)
            ->withQueryString();

        $contentManageRoleIds = $this->siteRoleIdsWithPermission($currentSite->id, 'content.manage');

        $siteRoles = $this->siteRolesQuery($currentSite->id)
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'name', 'code', 'description'])
            ->map(function ($role) use ($contentManageRoleIds) {
                $role->can_manage_content = in_array((int) $role->id, $contentManageRoleIds, true);

                return $role;
            });

        return view('admin.site.users.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'siteUsers' => $siteUsers,
            'siteRoles' => $siteRoles,
            'keyword' => $keyword,
            'selectedStatus' => $status,
            'selectedRoleId' => $roleId,
        ]);
    }

    public function edit(Request $request, string $userId): View
    {
        $this->syncRequestedSiteContext($request);
        $currentSite = $this->currentSite($request);
        $user = User::query()->find($userId);
        abort_unless($user, 404);

        if (! $this->canEditOwnProfile($request, $currentSite->id, (int) $user->id)) {
            $this->authorizeSite($request, $currentSite->id, 'site.user.manage');
        }

        $exists = DB::table('site_user_roles')
            ->where('site_id', $currentSite->id)
            ->where('user_id', $user->id)
            ->exists();
        abort_unless($exists, 404);
        abort_if($this->isPlatformIdentity($user->id), 404);

        $contentManageRoleIds = $this->siteRoleIdsWithPermission($currentSite->id, 'content.manage');

        $siteRoles = $this->siteRolesQuery($currentSite->id)
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'name', 'code', 'description'])
            ->map(function ($role) use ($contentManageRoleIds) {
                $role->can_manage_content = in_array((int) $role->id, $contentManageRoleIds, true);

                return $role;
            });

        $channels = DB::table('channels')
            ->where('site_id', $currentSite->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'type', 'parent_id', 'depth', 'sort']);

        $existingChannelIds = DB::table('site_user_channels')
            ->where('site_id', $currentSite->id)
            ->where('user_id', $user->id)
            ->pluck('channel_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selectedRoles = DB::table('site_user_roles')
            ->where('site_id', $currentSite->id)
            ->where('user_id', $user->id)
            ->pluck('role_id')
            ->all();

        return view('admin.site.users.edit', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'user' => $user,
            'siteRoles' => $siteRoles,
            'channels' => $this->flattenManagedChannels($channels),
            'selectedRoles' => $selectedRoles,
            'selectedRoleId' => (function () use ($selectedRoles) {
                $oldRoleValue = request()->old('role_id');

                if (is_string($oldRoleValue) && preg_match('/^site:(\d+)$/', $oldRoleValue, $matches)) {
                    return (string) ($matches[1] ?? '');
                }

                return (string) ($selectedRoles[0] ?? '');
            })(),
            'selectedChannelIds' => array_values(array_unique(array_filter(array_map('intval', is_array(old('channel_ids')) ? old('channel_ids') : $existingChannelIds)))),
            'selectedRoleCanManageContent' => in_array((int) ($selectedRoles[0] ?? 0), $contentManageRoleIds, true),
            'contentManageRoleIds' => $contentManageRoleIds,
            'avatarAttachmentWorkspaceAccess' => $this->canAccessAttachmentWorkspace((int) $request->user()->id, (int) $currentSite->id)
                || $this->canManageSiteUsers((int) $request->user()->id, (int) $currentSite->id),
            'isSelfEditing' => (int) $request->user()->id === (int) $user->id,
            'canManageRoleSelection' => (int) $request->user()->id !== (int) $user->id,
            'canManageStatusSelection' => (int) $request->user()->id !== (int) $user->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'site.user.manage');
        $validated = $this->validateUser($request, $currentSite->id, (string) $currentSite->site_key, requireRoleSelection: true, canManageStatusSelection: true);

        $user = DB::transaction(function () use ($validated, $currentSite) {
            $user = User::query()->create([
                'username' => $validated['username'],
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'mobile' => $validated['mobile'] ?? null,
                'avatar' => $validated['avatar'] ?? null,
                'remark' => $validated['remark'] ?? null,
                'status' => (int) ($validated['status'] ?? 1),
                'password' => $validated['password'],
            ]);

            $this->syncSiteRoles($currentSite->id, $user->id, $validated['role_id'] ?? null);
            $this->syncSiteChannels(
                $currentSite->id,
                $user->id,
                $this->roleCanManageContent($currentSite->id, (int) ($validated['role_id'] ?? 0))
                    ? $this->resolveSiteChannelIds($currentSite->id, $validated['channel_ids'] ?? [])
                    : [],
            );
            (new UserAttachmentRelationSync())->syncForUser($currentSite->id, $user->id);

            return $user;
        });

        $this->logOperation(
            'site',
            'site_user',
            'create',
            $currentSite->id,
            $request->user()->id,
            'user',
            $user->id,
            ['username' => $user->username],
            $request,
        );

        return redirect()->route('admin.site-users.index')->with('status', '站点账号已创建。');
    }

    public function update(Request $request, string $userId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $user = User::query()->find($userId);
        abort_unless($user, 404);

        if (! $this->canEditOwnProfile($request, $currentSite->id, (int) $user->id)) {
            $this->authorizeSite($request, $currentSite->id, 'site.user.manage');
        }

        $exists = DB::table('site_user_roles')
            ->where('site_id', $currentSite->id)
            ->where('user_id', $user->id)
            ->exists();
        abort_unless($exists, 404);
        abort_if($this->isPlatformIdentity($user->id), 404);

        $canManageRoleSelection = (int) $request->user()->id !== (int) $user->id;
        $canManageStatusSelection = (int) $request->user()->id !== (int) $user->id;
        $validated = $this->validateUser(
            $request,
            $currentSite->id,
            (string) $currentSite->site_key,
            $userId,
            $canManageRoleSelection,
            $canManageStatusSelection,
        );

        DB::transaction(function () use ($validated, $currentSite, $user, $canManageRoleSelection, $canManageStatusSelection): void {
            $payload = [
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'mobile' => $validated['mobile'] ?? null,
                'avatar' => $validated['avatar'] ?? null,
                'remark' => $validated['remark'] ?? null,
            ];

            if ($canManageRoleSelection) {
                $payload['username'] = $validated['username'];
            }

            if ($canManageStatusSelection) {
                $payload['status'] = (int) ($validated['status'] ?? 1);
            }

            if (! empty($validated['password'])) {
                $payload['password'] = $validated['password'];
            }

            $user->update($payload);
            if ($canManageRoleSelection) {
                $this->syncSiteRoles($currentSite->id, $user->id, $validated['role_id'] ?? null);
            }

            $effectiveRoleId = $canManageRoleSelection
                ? (int) ($validated['role_id'] ?? 0)
                : (int) DB::table('site_user_roles')
                    ->where('site_id', $currentSite->id)
                    ->where('user_id', $user->id)
                    ->value('role_id');

            if ($canManageRoleSelection) {
                $this->syncSiteChannels(
                    $currentSite->id,
                    $user->id,
                    $this->roleCanManageContent($currentSite->id, $effectiveRoleId)
                        ? $this->resolveSiteChannelIds($currentSite->id, $validated['channel_ids'] ?? [])
                        : [],
                );
            }
            (new UserAttachmentRelationSync())->syncForUser($currentSite->id, $user->id);
        });

        $this->logOperation(
            'site',
            'site_user',
            'update',
            $currentSite->id,
            $request->user()->id,
            'user',
            $user->id,
            ['username' => $user->username],
            $request,
        );

        return redirect()->route('admin.site-users.edit', [
            'user' => $user->id,
            'site_id' => $currentSite->id,
        ])->with('status', '站点账号已更新。');
    }

    public function destroy(Request $request, string $userId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'site.user.manage');
        $user = User::query()->find($userId);
        abort_unless($user, 404);

        abort_if((int) $request->user()->id === (int) $user->id, 422, '不能删除当前登录账号。');

        $exists = DB::table('site_user_roles')
            ->where('site_id', $currentSite->id)
            ->where('user_id', $user->id)
            ->exists();
        abort_unless($exists, 404);
        abort_if($this->isPlatformIdentity($user->id), 404);

        DB::transaction(function () use ($user): void {
            $siteIds = DB::table('site_user_roles')
                ->where('user_id', $user->id)
                ->pluck('site_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

            foreach ($siteIds as $siteId) {
                (new UserAttachmentRelationSync())->clearForUser($siteId, $user->id);
            }

            DB::table('site_user_roles')
                ->where('user_id', $user->id)
                ->delete();

            User::query()->whereKey($user->id)->delete();
        });

        $this->logOperation(
            'site',
            'site_user',
            'delete',
            $currentSite->id,
            $request->user()->id,
            'user',
            $user->id,
            ['username' => $user->username],
            $request,
        );

        return redirect()->route('admin.site-users.index')->with('status', '操作员账号已删除。');
    }

    protected function validateUser(
        Request $request,
        int $siteId,
        string $siteKey,
        ?string $userId = null,
        bool $requireRoleSelection = true,
        bool $canManageStatusSelection = true
    ): array
    {
        $usernameRule = 'unique:users,username';
        $emailRule = 'nullable|email|max:255|unique:users,email';

        if ($userId) {
            $usernameRule .= ','.$userId;
            $emailRule = 'nullable|email|max:255|unique:users,email,'.$userId;
        }

        $rules = [
            'username' => ['required', 'string', 'min:4', 'max:32', 'regex:/^[A-Za-z][A-Za-z0-9_-]{3,31}$/', $usernameRule],
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'email' => explode('|', $emailRule),
            'mobile' => ['nullable', 'string', 'regex:/^[0-9+\\-\\s()#]{6,50}$/'],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'remark' => ['nullable', 'string', 'max:10000'],
        ];

        if ($canManageStatusSelection) {
            $rules['status'] = ['required', 'integer', 'in:0,1'];
        } else {
            $rules['status'] = ['nullable', 'integer', 'in:0,1'];
        }

        if ($requireRoleSelection) {
            $rules['role_id'] = ['required', 'string', 'regex:/^site:\d+$/'];
        } else {
            $rules['role_id'] = ['nullable', 'string', 'regex:/^site:\d+$/'];
        }

        $rules['channel_ids'] = ['nullable', 'array'];
        $rules['channel_ids.*'] = ['integer'];

        $rules['password'] = $userId
            ? ['nullable', 'string', 'min:8']
            : ['required', 'string', 'min:8'];

        $validator = Validator::make($request->all(), $rules, [
            'username.required' => '该项为必填项，请填写内容。',
            'username.min' => '用户名至少需要 4 个字符。',
            'username.max' => '用户名不能超过 32 个字符。',
            'username.regex' => '用户名需以字母开头，可使用字母、数字、下划线或中划线。',
            'username.unique' => '该用户名已存在，请更换后再试。',
            'name.required' => '该项为必填项，请填写内容。',
            'name.min' => '姓名至少需要 2 个字符。',
            'name.max' => '姓名不能超过 50 个字符。',
            'email.email' => '邮箱格式不正确，请重新填写。',
            'email.max' => '邮箱长度不能超过 255 个字符。',
            'email.unique' => '该邮箱已存在，请更换后再试。',
            'mobile.regex' => '手机号格式不正确，请输入有效的手机号或联系电话。',
            'avatar.max' => '头像地址过长，请重新上传头像。',
            'channel_ids.*.integer' => '所选可管理栏目无效，请重新选择。',
            'remark.max' => '备注信息不能超过 10000 个字符。',
            'status.required' => '请选择账号状态。',
            'status.in' => '账号状态无效，请重新选择。',
            'role_id.required' => '请选择一个操作角色。',
            'role_id.regex' => '所选操作角色无效或不属于当前站点。',
            'password.required' => '该项为必填项，请填写内容。',
            'password.min' => '密码至少需要 8 个字符。',
        ]);

        $allowedRoleIds = $this->siteRolesQuery($siteId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $allowedChannelIds = DB::table('channels')
            ->where('site_id', $siteId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $validator->after(function ($validator) use ($request, $allowedRoleIds, $allowedChannelIds, $requireRoleSelection, $siteId, $siteKey): void {
            $rawRoleId = is_scalar($request->input('role_id')) ? (string) $request->input('role_id') : '';

            if ($rawRoleId === '') {
                if ($requireRoleSelection) {
                    $validator->errors()->add('role_id', '请选择一个操作角色。');
                    return;
                }
            } else {
                if (! preg_match('/^site:(\d+)$/', $rawRoleId, $matches)) {
                    $validator->errors()->add('role_id', '所选操作角色无效或不属于当前站点。');

                    return;
                }

                $submittedRoleId = (int) ($matches[1] ?? 0);

                if ($submittedRoleId < 1 || ! in_array($submittedRoleId, $allowedRoleIds, true)) {
                    $validator->errors()->add('role_id', '所选操作角色无效或不属于当前站点。');
                }
            }

            $submittedChannelIds = collect(is_array($request->input('channel_ids')) ? $request->input('channel_ids') : [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if ($submittedChannelIds !== []) {
                $invalidChannelIds = array_values(array_diff($submittedChannelIds, $allowedChannelIds));

                if ($invalidChannelIds !== []) {
                    $validator->errors()->add('channel_ids', '所选可管理栏目无效或不属于当前站点。');
                }
            }

            $rawAvatar = trim((string) $request->input('avatar', ''));

            if ($rawAvatar !== '') {
                $avatarPath = parse_url($rawAvatar, PHP_URL_PATH);
                $avatarPath = is_string($avatarPath) ? trim($avatarPath) : $rawAvatar;
                $avatarPath = trim(html_entity_decode($avatarPath, ENT_QUOTES | ENT_HTML5));

                $matchesCurrentSiteMedia = preg_match('#^/site-media/'.preg_quote($siteKey, '#').'/.+#', $avatarPath) === 1;
                $matchesTestSiteMedia = app()->environment('testing')
                    && preg_match('#^/site-media/tests/attachments/'.preg_quote((string) $siteId, '#').'/.+#', $avatarPath) === 1;

                if (! $matchesCurrentSiteMedia && ! $matchesTestSiteMedia) {
                    $validator->errors()->add('avatar', '头像资源无效，请重新从当前站点资源库选择。');
                }
            }
        });

        $validated = $validator->validate();
        preg_match('/^site:(\d+)$/', (string) ($validated['role_id'] ?? ''), $matches);
        $validated['role_id'] = (int) ($matches[1] ?? 0);
        $validated['channel_ids'] = array_map(
            'intval',
            is_array($validated['channel_ids'] ?? null) ? ($validated['channel_ids'] ?? []) : [],
        );

        return $validated;
    }

    /**
     * Flatten channels into a tree-ordered collection with hierarchy metadata.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $channels
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function flattenManagedChannels(Collection $channels): Collection
    {
        $childrenByParent = $channels->groupBy(function (object $channel): int {
            return (int) ($channel->parent_id ?? 0);
        });

        $walk = function (int $parentId, int $depth = 0, array $ancestorLines = []) use (&$walk, $childrenByParent): array {
            $items = $childrenByParent->get($parentId, collect())->values();
            $flattened = [];

            foreach ($items as $index => $channel) {
                $isLast = $index === $items->count() - 1;
                $channel->tree_depth = $depth;
                $channel->tree_is_last = $isLast;
                $channel->tree_ancestors = $ancestorLines;
                $channel->tree_has_children = $childrenByParent->has((int) $channel->id);
                $flattened[] = $channel;

                $nextAncestorLines = $ancestorLines;
                $nextAncestorLines[] = ! $isLast;

                foreach ($walk((int) $channel->id, $depth + 1, $nextAncestorLines) as $child) {
                    $flattened[] = $child;
                }
            }

            return $flattened;
        };

        return collect($walk(0));
    }

    protected function syncSiteRoles(int $siteId, int $userId, ?int $roleId): void
    {
        $roleId = $this->siteRolesQuery($siteId)
            ->where('id', $roleId)
            ->value('id');

        DB::table('site_user_roles')
            ->where('site_id', $siteId)
            ->where('user_id', $userId)
            ->delete();

        if ($roleId) {
            DB::table('site_user_roles')->insert([
                'site_id' => $siteId,
                'user_id' => $userId,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Determine whether a role can manage content.
     */
    protected function roleCanManageContent(int $siteId, int $roleId): bool
    {
        if ($roleId < 1) {
            return false;
        }

        return DB::table('site_role_permissions')
            ->join('site_permissions', 'site_permissions.id', '=', 'site_role_permissions.permission_id')
            ->join('site_roles', 'site_roles.id', '=', 'site_role_permissions.role_id')
            ->where('site_role_permissions.site_id', $siteId)
            ->where(function ($query) use ($siteId): void {
                $query->whereNull('site_roles.site_id')
                    ->orWhere('site_roles.site_id', $siteId);
            })
            ->where('site_role_permissions.role_id', $roleId)
            ->where('site_permissions.code', 'content.manage')
            ->exists();
    }

    /**
     * Resolve role ids with a given permission.
     *
     * @return array<int, int>
     */
    protected function siteRoleIdsWithPermission(int $siteId, string $permissionCode): array
    {
        return DB::table('site_role_permissions')
            ->join('site_permissions', 'site_permissions.id', '=', 'site_role_permissions.permission_id')
            ->join('site_roles', 'site_roles.id', '=', 'site_role_permissions.role_id')
            ->where('site_role_permissions.site_id', $siteId)
            ->where(function ($query) use ($siteId): void {
                $query->whereNull('site_roles.site_id')
                    ->orWhere('site_roles.site_id', $siteId);
            })
            ->where('site_permissions.code', $permissionCode)
            ->distinct()
            ->pluck('site_role_permissions.role_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Sync the operator's manageable channels.
     *
     * @param  array<int, int|string>  $channelIds
     * @return array<int, int>
     */
    protected function resolveSiteChannelIds(int $siteId, array $channelIds): array
    {
        $channelIds = array_values(array_unique(array_filter(array_map('intval', $channelIds))));

        if ($channelIds !== []) {
            return DB::table('channels')
                ->where('site_id', $siteId)
                ->whereIn('id', $channelIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return [];
    }

    protected function syncSiteChannels(int $siteId, int $userId, array $channelIds): void
    {
        $channelIds = array_values(array_unique(array_filter(array_map('intval', $channelIds))));

        DB::table('site_user_channels')
            ->where('site_id', $siteId)
            ->where('user_id', $userId)
            ->delete();

        foreach ($channelIds as $channelId) {
            DB::table('site_user_channels')->insert([
                'site_id' => $siteId,
                'user_id' => $userId,
                'channel_id' => $channelId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function isPlatformIdentity(int $userId): bool
    {
        return DB::table('platform_user_roles')
            ->join('platform_roles', 'platform_roles.id', '=', 'platform_user_roles.role_id')
            ->where('platform_user_roles.user_id', $userId)
            ->exists();
    }
}
