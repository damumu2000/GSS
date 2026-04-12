@extends('layouts.admin')

@section('title', '操作角色管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 操作角色管理')

@push('styles')
    <link rel="stylesheet" href="/css/site-roles.css">
@endpush

@section('content')
    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">操作角色管理</h2>
            <div class="page-header-desc">通过角色列表快速查看每个角色的职责与核心权限，再进入详情进行精细配置。</div>
        </div>
        <div>
            <a class="button" href="{{ route('admin.site-roles.create') }}">新增操作角色</a>
        </div>
    </section>

    @if ($siteRoles->isEmpty())
        <div class="empty-state">当前站点还没有可维护的角色。</div>
    @else
        <section class="role-list">
            @foreach ($siteRoles as $siteRole)
                @php
                    $permissionNames = collect($siteRole->permission_names ?? []);
                    $visiblePermissionNames = $permissionNames->take(5);
                    $remainingPermissionCount = max($permissionNames->count() - $visiblePermissionNames->count(), 0);
                    $isLockedRole = $siteRole->code === 'site_admin';
                    $isBuiltInRole = in_array($siteRole->code, ['site_admin', 'editor', 'reviewer', 'uploader', 'template_editor'], true);
                @endphp

                <article class="role-item">
                    <div class="role-item-main">
                        <div class="role-item-top">
                            <div class="role-title-row">
                                <div class="role-name">{{ $siteRole->name }}</div>
                                <span class="role-code">{{ $siteRole->code }}</span>
                            </div>
                            <div class="role-desc">{{ $siteRole->description ?: '未填写角色说明。' }}</div>
                        </div>

                        <div class="role-meta-row">
                            <span class="role-metric">{{ (int) $siteRole->user_count }} 位管理员</span>
                            @if ($siteRole->is_self_role)
                                <span class="role-metric self">当前登录角色</span>
                            @endif
                            @if ($isLockedRole)
                                <span class="role-metric self">系统内置核心角色</span>
                            @endif
                            @if ($isLockedRole)
                                <span class="role-metric">拥有所有权限</span>
                            @else
                                <div class="mini-tags">
                                    @foreach ($visiblePermissionNames as $permissionName)
                                        <span class="mini-tag badge-soft">{{ $permissionName }}</span>
                                    @endforeach
                                    @if ($remainingPermissionCount > 0)
                                        <span class="mini-tag more badge-soft muted">其余 {{ $remainingPermissionCount }} 项</span>
                                    @endif
                                </div>
                            @endif

                        </div>
                    </div>

                    <div class="role-item-action">
                        <a class="action-link" href="{{ route('admin.site-roles.edit', $siteRole->id) }}">
                            <span>{{ $isLockedRole ? '查看权限' : '配置权限' }}</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                        </a>
                        @unless ($isBuiltInRole)
                            <button
                                class="icon-button danger js-role-delete-trigger"
                                type="button"
                                aria-label="删除角色 {{ $siteRole->name }}"
                                data-role-name="{{ $siteRole->name }}"
                                data-form-id="delete-role-{{ $siteRole->id }}"
                                data-tooltip="删除角色"
                                title="删除角色"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                            </button>
                            <form id="delete-role-{{ $siteRole->id }}" method="POST" action="{{ route('admin.site-roles.destroy', $siteRole->id) }}">
                                @csrf
                            </form>
                        @endunless
                    </div>
                </article>
            @endforeach
        </section>
    @endif
@endsection

@push('scripts')
    <script src="/js/site-roles-index.js"></script>
@endpush
