@extends('layouts.admin')

@section('title', '编辑平台角色 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 平台角色管理 / 角色详情')

@php
    $selectedPermissionIds = collect(old('permission_ids', $selectedPermissionIds ?? []))->map(fn ($id) => (int) $id)->all();
    $moduleLabels = array_merge(config('cms.permission_modules', []), [
        'platform_role' => '平台角色',
    ]);
    $moduleOrder = [
        'platform' => 10,
        'site' => 20,
        'user' => 30,
        'platform_role' => 40,
        'system' => 50,
        'module' => 60,
        'theme' => 70,
        'database' => 80,
        'log' => 90,
    ];
    $permissionCodeOrder = [
        'platform.view' => 10,
        'site.manage' => 20,
        'platform.user.manage' => 30,
        'platform.role.manage' => 40,
        'system.check.view' => 45,
        'system.setting.manage' => 50,
        'module.manage' => 60,
        'theme.market.manage' => 70,
        'database.manage' => 80,
        'platform.log.view' => 90,
    ];
    $permissionsByModule = collect($permissions)
        ->sortBy(function ($permission) use ($moduleOrder, $permissionCodeOrder) {
            return [
                $moduleOrder[$permission->module] ?? 999,
                $permissionCodeOrder[$permission->code] ?? 999,
                $permission->id,
            ];
        })
        ->groupBy('module');
    $selectedPermissionCount = count($selectedPermissionIds);
    $moduleIcons = [
        'database' => '<svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6"/><path d="M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/></svg>',
        'log' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5l3 2"/><path d="M20 12a8 8 0 1 1-2.34-5.66"/><path d="M20 4v5h-5"/></svg>',
        'module' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h7v5H4z"/><path d="M13 7h7v5h-7z"/><path d="M4 14h7v5H4z"/><path d="M13 14h7v5h-7z"/></svg>',
        'platform' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v10H4z"/><path d="M8 19h8"/><path d="M12 15v4"/></svg>',
        'platform_role' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/><path d="M20 20a8 8 0 1 0-16 0"/><path d="M19 8h3"/><path d="M20.5 6.5v3"/></svg>',
        'site' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.5 12 4l9 6.5"/><path d="M5 9.5V20h14V9.5"/><path d="M9 20v-5h6v5"/></svg>',
        'system' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15.5A3.5 3.5 0 1 0 12 8.5a3.5 3.5 0 0 0 0 7z"/><path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a2 2 0 1 1-4 0v-.2a1 1 0 0 0-.6-.9 1 1 0 0 0-1.1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a2 2 0 1 1 0-4h.2a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1 1 0 0 0 1.1.2 1 1 0 0 0 .6-.9V4a2 2 0 1 1 4 0v.2a1 1 0 0 0 .6.9 1 1 0 0 0 1.1-.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1 1 0 0 0-.2 1.1 1 1 0 0 0 .9.6H20a2 2 0 1 1 0 4h-.2a1 1 0 0 0-.9.6z"/></svg>',
        'theme' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="M9 10h6"/><path d="M9 14h3"/></svg>',
        'user' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><path d="M9.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/><path d="M20 8v6"/><path d="M23 11h-6"/></svg>',
    ];
@endphp

@push('styles')
    <link rel="stylesheet" href="/css/platform-roles.css">
@endpush

@section('content')
    <section class="page-header">
        <div>
            <div class="page-header-title-row">
                <h2 class="page-header-title">{{ $role->name }}</h2>
                <span class="page-header-tag">{{ $role->code }}</span>
            </div>
            <div class="page-header-desc">{{ $role->description ?: '当前平台角色用于控制平台工作台、站点管理、管理员管理、主题市场、公告与平台日志访问。' }}</div>
            @if ($isLockedRole)
                <div class="role-readonly-notice">总管理员为系统内置核心角色，仅支持查看当前平台权限配置，不支持直接编辑。</div>
            @endif
        </div>
        <div class="page-header-actions">
            <a class="button secondary" href="{{ route('admin.platform.roles.index') }}">返回平台角色</a>
            @unless ($isLockedRole)
                <button class="button" type="submit" form="platform-role-edit-form" data-loading-text="保存中...">保存平台角色</button>
            @endunless
        </div>
    </section>

    <form id="platform-role-edit-form" method="POST" action="{{ route('admin.platform.roles.update', $role->id) }}">
        @csrf

        <section class="role-card">
            <div class="role-card-header">
                <div>
                    <h3 class="role-card-title">平台角色配置</h3>
                    <div class="role-card-subtitle">用于控制平台级菜单、页面访问和操作权限。</div>
                </div>
                <div class="role-card-actions">
                    <span class="role-card-meta">{{ count($selectedPermissionIds) }} 项平台权限</span>
                    <span class="role-card-meta">{{ count($memberNames) }} 位管理员</span>
                    @if ($isSelfRole)
                        <span class="role-card-meta self">当前登录角色</span>
                    @endif
                </div>
            </div>

            <div class="field-grid">
                <label class="field-group">
                    <span class="field-label">角色名称</span>
                    <input class="field @error('name') is-error @enderror" type="text" name="name" value="{{ old('name', $role->name) }}" @disabled($isLockedRole)>
                    @error('name')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="field-group">
                    <span class="field-label">角色说明</span>
                    <input class="field @error('description') is-error @enderror" type="text" name="description" value="{{ old('description', $role->description) }}" @disabled($isLockedRole)>
                    @error('description')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </label>
            </div>

            @error('permission_ids')
                <div class="u-pad-top-10-24">
                    <span class="form-error">{{ $message }}</span>
                </div>
            @enderror

            @foreach ($permissionsByModule as $module => $modulePermissions)
                <div class="permission-row">
                    <div class="permission-module">
                        <span class="permission-module-icon">{!! $moduleIcons[$module] ?? $moduleIcons['platform'] !!}</span>
                        <div>
                            <div class="permission-module-title">{{ $moduleLabels[$module] ?? $module }}</div>
                            <div class="permission-module-meta">{{ count($modulePermissions) }} 项平台权限</div>
                        </div>
                    </div>

                    <div class="permission-points">
                        @foreach ($modulePermissions as $permission)
                            <label class="permission-point">
                                <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array((int) $permission->id, $selectedPermissionIds, true)) @disabled($isLockedRole || ($isSelfRole && !((int) auth()->id() === (int) config('cms.super_admin_user_id', 1))))>
                                <span class="permission-check"></span>
                                <span class="permission-label">{{ $permission->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="platform-role-summary">
                @if ($isLockedRole)
                    总管理员为系统内置核心角色，仅支持查看，不支持编辑平台权限。
                @elseif ($isSelfRole && !((int) auth()->id() === (int) config('cms.super_admin_user_id', 1)))
                    当前登录账号不能修改自己所属的平台角色权限，请由系统级总管理员操作。
                @else
                    保存后，所有绑定该平台角色的管理员会立即获得新的平台级菜单与访问范围。
                @endif
            </div>

            <div class="member-list">
                @forelse ($memberNames as $memberName)
                    <span class="member-chip">{{ $memberName }}</span>
                @empty
                    <span class="member-chip">当前暂无绑定管理员</span>
                @endforelse
            </div>
        </section>
    </form>
@endsection
