@extends('layouts.admin')

@section('title', '编辑操作角色 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 操作角色管理 / 角色详情')

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

        .page-header-main {
            min-width: 0;
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
            justify-content: flex-end;
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

        .role-readonly-notice {
            margin-top: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #fff7e6;
            color: #ad6800;
            font-size: 12px;
            line-height: 1.7;
        }

        .role-summary-bar {
            padding: 12px 24px 14px;
            border-top: 1px solid #f0f0f0;
        }

        .role-summary-text {
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.7;
        }

        .role-summary-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.2;
            font-weight: 600;
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

        .permission-subgroup-header {
            padding: 20px 24px 14px;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
            background: #ffffff;
        }

        .permission-subgroup-title {
            margin: 0;
            color: #262626;
            font-size: 16px;
            line-height: 1.25;
            font-weight: 700;
        }

        .permission-module {
            width: 160px;
            flex-shrink: 0;
            display: flex;
            gap: 12px;
            align-items: flex-start;
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
            padding-top: 1px;
            justify-content: flex-start;
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

        .permission-label {
            color: inherit;
            font-size: 13px;
            line-height: 1.45;
            font-weight: 500;
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

        .channel-panel {
            margin: 0 24px 24px;
            padding: 14px 16px;
            border-radius: 14px;
            background: #fafafa;
        }

        .channel-panel-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 14px;
        }

        .channel-panel-title {
            margin: 0;
            color: #262626;
            font-size: 15px;
            line-height: 1.4;
        }

        .channel-panel-desc {
            margin-top: 4px;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.6;
        }

        .channel-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-start;
        }

        .channel-option {
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            flex: 0 0 auto;
            min-height: 44px;
            max-width: 220px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #f8fafc;
            color: #595959;
            box-shadow: inset 0 0 0 1px #e5e7eb;
            transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
            cursor: pointer;
        }

        .channel-option:hover {
            background: var(--primary-soft);
            color: #374151;
            box-shadow: inset 0 0 0 1px rgba(0, 71, 171, 0.12);
        }

        .channel-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .channel-copy {
            min-width: 0;
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .channel-icon {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            color: #4b5563;
            flex-shrink: 0;
        }

        .channel-icon svg {
            width: 12px;
            height: 12px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .channel-name {
            color: inherit;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.45;
            white-space: nowrap;
        }

        .channel-option:has(input:checked) {
            background: var(--tag-bg);
            color: var(--tag-text);
            box-shadow: inset 0 0 0 1px var(--primary-border-soft);
        }

        .channel-option input:checked + .permission-check {
            background: var(--primary);
            box-shadow: inset 0 0 0 1px var(--primary);
        }

        .channel-option input:checked + .permission-check::after {
            opacity: 1;
        }

        .role-submit-bar {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            padding: 14px 24px 18px;
            border-top: 1px solid #f0f0f0;
        }

        @media (max-width: 1280px) {
            .role-summary-bar {
                flex-direction: column;
                align-items: flex-start;
            }

            .permission-row {
                flex-direction: column;
                gap: 18px;
            }

            .permission-module {
                width: 100%;
            }

        }

        @media (max-width: 768px) {
            .page-header {
                margin: -24px -18px 20px;
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
            }

            .role-card-header,
            .role-submit-bar {
                padding-left: 18px;
                padding-right: 18px;
            }

            .role-summary-bar {
                padding-left: 18px;
                padding-right: 18px;
            }

            .role-card-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .permission-row {
                padding-left: 18px;
                padding-right: 18px;
            }

            .channel-panel {
                margin-left: 18px;
                margin-right: 18px;
            }

            .channel-option {
                max-width: none;
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $moduleIcons = [
            'attachment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/><path d="M9 14h6"/><path d="M9 18h4"/></svg>',
            'channel' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h7v5H4z"/><path d="M13 6h7v5h-7z"/><path d="M4 13h7v5H4z"/><path d="M13 13h7v5h-7z"/></svg>',
            'content' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4h9l3 3v13H6z"/><path d="M9 11h6"/><path d="M9 15h6"/><path d="M9 7h3"/></svg>',
            'log' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5l3 2"/><path d="M20 12a8 8 0 1 1-2.34-5.66"/><path d="M20 4v5h-5"/></svg>',
            'setting' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15.5A3.5 3.5 0 1 0 12 8.5a3.5 3.5 0 0 0 0 7z"/><path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a2 2 0 1 1-4 0v-.2a1 1 0 0 0-.6-.9 1 1 0 0 0-1.1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a2 2 0 1 1 0-4h.2a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1 1 0 0 0 1.1.2 1 1 0 0 0 .6-.9V4a2 2 0 1 1 4 0v.2a1 1 0 0 0 .6.9 1 1 0 0 0 1.1-.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1 1 0 0 0-.2 1.1 1 1 0 0 0 .9.6H20a2 2 0 1 1 0 4h-.2a1 1 0 0 0-.9.6z"/></svg>',
            'theme' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="M9 10h6"/><path d="M9 14h3"/></svg>',
            'user' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><path d="M9.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/><path d="M20 8v6"/><path d="M23 11h-6"/></svg>',
        ];
        $selectedPermissionCount = count($visibleSelectedPermissionIds);
    @endphp

    <section class="page-header">
        <div class="page-header-main">
            <div class="page-header-title-row">
                <h2 class="page-header-title">{{ $role->name }}</h2>
                <span class="page-header-tag">{{ $role->code }}</span>
            </div>
            <div class="page-header-desc">当前站点：{{ $currentSite->name }}，请在这里维护该角色的功能权限配置。</div>
            @if ($isLockedRole)
                <div class="role-readonly-notice">站点管理员为系统内置核心角色，仅支持查看当前权限配置，不支持直接编辑或删除。</div>
            @endif
        </div>
        <div class="page-header-actions">
            <a class="button secondary" href="{{ route('admin.site-roles.index') }}">返回操作角色管理</a>
            @unless ($isLockedRole)
                <button class="button" type="submit" form="site-role-form" data-loading-text="保存中...">保存角色配置</button>
            @endunless
        </div>
    </section>

    <div>
        <section class="role-card">
                <div class="role-card-header">
                    <div>
                        <h3 class="role-card-title">权限配置</h3>
                        <div class="role-card-subtitle">{{ $role->description ?: '按模块分配后台能力，统一维护该角色的后台使用范围。' }}</div>
                    </div>
                    <div class="role-card-actions">
                        <span class="role-card-meta">{{ $selectedPermissionCount }} 项功能</span>
                        @unless ($isLockedRole)
                            <button class="button secondary js-select-all" type="button">全部勾选</button>
                            <button class="button secondary js-select-none" type="button">清空权限</button>
                        @endunless
                    </div>
                </div>

            <form id="site-role-form" method="POST" action="{{ route('admin.site-roles.update', $role->id) }}">
                @csrf

                <div class="role-card-body">
                    <section class="role-summary-bar">
                        <div class="role-summary-text">当前角色已分配 {{ $selectedPermissionCount }} 项权限。</div>
                    </section>

                    @foreach ($groupedPermissions as $module => $permissions)
                        <section class="permission-row">
                            <div class="permission-module">
                                <span class="permission-module-icon">{!! $moduleIcons[$module] ?? $moduleIcons['content'] !!}</span>
                                <div>
                                    <div class="permission-module-title">{{ $moduleLabels[$module] ?? $module }}</div>
                                    <div class="permission-module-meta">{{ $permissions->count() }} 项权限</div>
                                </div>
                            </div>

                            <div class="permission-points">
                                @foreach ($permissions as $permission)
                                    <label class="permission-point" data-role-module="{{ $role->id }}-{{ $module }}">
                                        <input
                                            type="checkbox"
                                            name="permission_ids[]"
                                            value="{{ $permission->id }}"
                                            @checked(in_array($permission->id, $selectedPermissionIds, true))
                                            @disabled($isLockedRole)
                                        >
                                        <span class="permission-check" aria-hidden="true"></span>
                                        <span class="permission-label">{{ $permission->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </section>
                    @endforeach

                    @if (! empty($modulePermissionGroups) && collect($modulePermissionGroups)->isNotEmpty())
                        <section class="permission-subgroup-header">
                            <h4 class="permission-subgroup-title">功能模块权限配置</h4>
                        </section>

                        @foreach ($modulePermissionGroups as $modulePermissionGroup)
                            <section class="permission-row">
                                <div class="permission-module">
                                    <span class="permission-module-icon">{!! $moduleIcons['theme'] !!}</span>
                                    <div>
                                        <div class="permission-module-title">{{ $modulePermissionGroup['name'] }}</div>
                                        <div class="permission-module-meta">{{ $modulePermissionGroup['permissions']->count() }} 项权限</div>
                                    </div>
                                </div>

                                <div class="permission-points">
                                    @foreach ($modulePermissionGroup['permissions'] as $permission)
                                        <label class="permission-point" data-role-module="{{ $role->id }}-module-{{ $modulePermissionGroup['code'] }}">
                                            <input
                                                type="checkbox"
                                                name="permission_ids[]"
                                                value="{{ $permission->id }}"
                                                @checked(in_array($permission->id, $selectedPermissionIds, true))
                                                @disabled($isLockedRole)
                                            >
                                            <span class="permission-check" aria-hidden="true"></span>
                                            <span class="permission-label">{{ $permission->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    @endif
                </div>

                <div class="role-submit-bar">
                    <div class="helper-text" style="color: #8c8c8c;">保存后，该角色对应账号在当前站点内会立即按这里的权限生效。</div>
                </div>
            </form>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelector('.js-select-all')?.addEventListener('click', () => {
            document
                .querySelectorAll('input[name="permission_ids[]"]')
                .forEach((checkbox) => {
                    checkbox.checked = true;
                });
        });

        document.querySelector('.js-select-none')?.addEventListener('click', () => {
            document
                .querySelectorAll('input[name="permission_ids[]"]')
                .forEach((checkbox) => {
                    checkbox.checked = false;
                });
        });
    </script>
@endpush
