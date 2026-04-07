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
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-start;
            padding: 24px 32px;
            margin: -28px -28px 24px;
            background: #ffffff;
            border-bottom: 1px solid #f0f0f0;
        }

        .page-header-title-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .page-header-title {
            margin: 0;
            color: #262626;
            font-size: 20px;
            line-height: 1.4;
            font-weight: 700;
        }

        .page-header-tag {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            background: #f5f5f5;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1;
            font-weight: 500;
        }

        .page-header-desc {
            margin-top: 8px;
            color: #8c8c8c;
            font-size: 14px;
            line-height: 1.7;
        }

        .page-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .role-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
            overflow: hidden;
        }

        .role-card-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            padding: 20px 24px 14px;
            border-bottom: 1px solid #f0f0f0;
        }

        .role-card-title {
            margin: 0;
            color: #262626;
            font-size: 16px;
            line-height: 1.25;
            font-weight: 700;
        }

        .role-card-subtitle {
            margin-top: 5px;
            color: #999999;
            font-size: 12px;
            line-height: 1.6;
        }

        .role-card-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .role-card-meta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 12px;
            line-height: 1;
            font-weight: 600;
            white-space: nowrap;
        }

        .role-card-meta.self {
            background: #fff7ed;
            color: #c2410c;
        }

        .role-readonly-notice {
            margin-top: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #fff7e6;
            color: #ad6800;
            font-size: 12px;
            line-height: 1.7;
        }

        .platform-role-summary {
            padding: 14px 24px;
            border-top: 1px solid #f0f0f0;
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.7;
        }

        .field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            padding: 20px 24px 0;
        }

        .field-group {
            display: grid;
            gap: 8px;
        }

        .field-label {
            color: #8c8c8c;
            font-size: 13px;
            font-weight: 600;
        }

        .field {
            width: 100%;
            height: 38px;
            padding: 0 14px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            color: #262626;
            font-size: 14px;
        }

        .field.is-error {
            border-color: #ff4d4f;
            box-shadow: 0 0 0 3px rgba(255, 77, 79, 0.12);
        }

        .field-note {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.6;
        }

        .form-error {
            color: #ff4d4f;
            font-size: 12px;
            line-height: 1.6;
        }

        .permission-row {
            display: flex;
            gap: 22px;
            align-items: flex-start;
            padding: 16px 24px;
            border-bottom: 1px solid #f5f5f5;
        }

        .permission-row:last-child {
            border-bottom: 0;
        }

        .permission-module {
            display: flex;
            width: 160px;
            gap: 12px;
            align-items: flex-start;
            flex-shrink: 0;
        }

        .permission-module-icon {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            color: #4b5563;
            flex-shrink: 0;
        }

        .permission-module-icon svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.9;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .permission-module-title {
            color: #262626;
            font-size: 15px;
            font-weight: 600;
            line-height: 1.45;
        }

        .permission-module-meta {
            margin-top: 3px;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.6;
        }

        .permission-points {
            flex: 1;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            min-width: 0;
            justify-content: start;
            padding-top: 1px;
        }

        .permission-point {
            position: relative;
            min-width: 164px;
            max-width: 220px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 11px;
            border-radius: 10px;
            background: #f8fafc;
            color: #595959;
            box-shadow: inset 0 0 0 1px #e5e7eb;
            transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
            cursor: pointer;
        }

        .permission-point:hover {
            background: var(--primary-soft);
            color: #374151;
            box-shadow: inset 0 0 0 1px rgba(0, 71, 171, 0.12);
        }

        .permission-point input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .permission-check {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            background: #ffffff;
            box-shadow: inset 0 0 0 1px #d1d5db;
            position: relative;
            flex-shrink: 0;
            transition: background 0.18s ease, box-shadow 0.18s ease;
        }

        .permission-check::after {
            content: "";
            position: absolute;
            left: 4px;
            top: 2px;
            width: 4px;
            height: 7px;
            border-right: 2px solid #ffffff;
            border-bottom: 2px solid #ffffff;
            transform: rotate(45deg);
            opacity: 0;
            transition: opacity 0.18s ease;
        }

        .permission-point:has(input:checked) {
            background: var(--tag-bg);
            color: var(--tag-text);
            box-shadow: inset 0 0 0 1px var(--primary-border-soft);
        }

        .permission-point input:checked + .permission-check {
            background: var(--primary);
            box-shadow: inset 0 0 0 1px var(--primary);
        }

        .permission-point input:checked + .permission-check::after {
            opacity: 1;
        }

        .permission-label {
            font-size: 13px;
            line-height: 1.45;
            font-weight: 500;
            color: inherit;
        }

        .member-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 0 24px 20px;
        }

        .member-chip {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 12px;
            font-weight: 600;
        }

        @media (max-width: 960px) {
            .field-grid {
                grid-template-columns: 1fr;
            }

            .permission-module {
                width: 100%;
            }

            .permission-row {
                flex-direction: column;
                gap: 18px;
            }
        }
    </style>
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
                <div style="padding: 10px 24px 0;">
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
