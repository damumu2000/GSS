@extends('layouts.admin')

@section('title', '编辑操作角色 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 操作角色管理 / 角色详情')

@push('styles')
    <link rel="stylesheet" href="/css/site-roles.css">
@endpush

@section('content')
    @php
        $moduleIcons = [
            'attachment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/><path d="M9 14h6"/><path d="M9 18h4"/></svg>',
            'channel' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h7v5H4z"/><path d="M13 6h7v5h-7z"/><path d="M4 13h7v5H4z"/><path d="M13 13h7v5h-7z"/></svg>',
            'content' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4h9l3 3v13H6z"/><path d="M9 11h6"/><path d="M9 15h6"/><path d="M9 7h3"/></svg>',
            'log' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5l3 2"/><path d="M20 12a8 8 0 1 1-2.34-5.66"/><path d="M20 4v5h-5"/></svg>',
            'security' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v6c0 5 3.2 8.6 7 10 3.8-1.4 7-5 7-10V6z"/><path d="m9.5 12 1.7 1.7 3.3-3.4"/></svg>',
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
                    <div class="helper-text u-text-muted">保存后，该角色对应账号在当前站点内会立即按这里的权限生效。</div>
                </div>
            </form>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="/js/site-roles-edit.js"></script>
@endpush
