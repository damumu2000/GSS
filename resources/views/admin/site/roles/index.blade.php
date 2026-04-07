@extends('layouts.admin')

@section('title', '操作角色管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 操作角色管理')

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

        .page-header-title {
            margin: 0;
            color: #262626;
            font-size: 20px;
            line-height: 1.4;
            font-weight: 700;
        }

        .page-header-desc {
            margin-top: 8px;
            color: #8c8c8c;
            font-size: 14px;
            line-height: 1.7;
        }

        .role-list {
            display: grid;
            gap: 14px;
        }

        .role-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 20px 22px;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
            transition: background 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        .role-item:hover {
            background: var(--surface-hover);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
            transform: translateY(-1px);
        }

        .role-item-main {
            min-width: 0;
            flex: 1;
            display: grid;
            gap: 12px;
        }

        .role-item-top {
            min-width: 0;
        }

        .role-title-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .role-name {
            color: #262626;
            font-size: 16px;
            line-height: 1.5;
            font-weight: 700;
        }

        .role-code {
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

        .role-desc {
            margin-top: 4px;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.7;
        }

        .role-meta-row {
            display: flex;
            flex-wrap: nowrap;
            gap: 12px 16px;
            align-items: center;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .role-meta-row::-webkit-scrollbar {
            display: none;
        }

        .role-metric {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 12px;
            line-height: 1;
            font-weight: 600;
        }

        .role-metric.self {
            background: #fff7ed;
            color: #c2410c;
        }

        .mini-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .mini-tag { display: inline-flex; }
        .mini-tag.more { display: inline-flex; }

        .mini-tags .badge-soft {
            background: #f3f4f6 !important;
            color: #4b5563 !important;
        }

        .mini-tags .badge-soft.muted {
            background: #f3f4f6 !important;
            color: #6b7280 !important;
        }

        .scope-meta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.6;
        }

        .scope-meta svg,
        .action-link svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .role-item-action {
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }

        .action-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #4b5563 !important;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
        }

        .action-link:hover {
            color: #262626 !important;
        }

        .page-header .button {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
            box-shadow: none;
        }

        .page-header .button:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .empty-state {
            padding: 40px 24px;
            text-align: center;
            color: #8c8c8c;
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #f0f0f0;
        }

        @media (max-width: 768px) {
            .page-header {
                margin: -24px -18px 20px;
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
            }

            .role-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .role-item-action {
                width: 100%;
                align-items: flex-start;
            }
        }
    </style>
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
    <script>
        (() => {
            document.querySelectorAll('.js-role-delete-trigger').forEach((button) => {
                button.addEventListener('click', () => {
                    const formId = button.dataset.formId;
                    const form = formId ? document.getElementById(formId) : null;

                    if (!form || typeof window.showConfirmDialog !== 'function') {
                        return;
                    }

                    window.showConfirmDialog({
                        title: '确认删除角色？',
                        text: '删除后该角色下的用户权限将失效，且操作不可恢复。',
                        confirmText: '确定删除',
                        onConfirm: () => form.submit(),
                    });
                });
            });
        })();
    </script>
@endpush
