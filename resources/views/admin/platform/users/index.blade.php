@extends('layouts.admin')

@section('title', '平台管理员 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 平台管理员')

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

        .platform-user-list-shell {
            overflow: hidden;
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid #edf1f5;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
        }

        .platform-user-list-header,
        .platform-user-item {
            display: grid;
            grid-template-columns: minmax(0, 2.1fr) minmax(180px, 1fr) minmax(240px, 1.3fr) minmax(130px, 0.8fr) 110px;
            gap: 16px;
            align-items: center;
            padding: 16px 22px;
        }

        .platform-user-list-header {
            border-bottom: 1px solid #eef1f4;
            background: #fbfcfd;
        }

        .platform-user-list-title {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.5;
            font-weight: 700;
        }

        .platform-user-list {
            display: grid;
        }

        .platform-user-item {
            position: relative;
            border-bottom: 1px solid #f2f4f7;
            transition: background-color 0.18s ease, border-color 0.18s ease;
        }

        .platform-user-item:last-child {
            border-bottom: 0;
        }

        .platform-user-item:hover {
            background: #fafcff;
            box-shadow: inset 2px 0 0 var(--primary);
        }

        .platform-user-main {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .platform-user-avatar {
            position: relative;
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f5f7fb;
            color: #64748b;
            font-size: 18px;
            font-weight: 700;
            flex-shrink: 0;
            border: 1px solid #e7ebf1;
        }

        .platform-user-avatar::after {
            content: "";
            position: absolute;
            right: 1px;
            bottom: 1px;
            width: 10px;
            height: 10px;
            border-radius: 999px;
            border: 2px solid #ffffff;
            background: #52c41a;
            box-shadow: 0 0 0 0 rgba(82, 196, 26, 0.28);
            animation: platform-user-status-pulse 1.8s ease-out infinite;
        }

        .platform-user-avatar.is-offline::after {
            background: #cbd5e1;
            box-shadow: none;
            animation: none;
        }

        @keyframes platform-user-status-pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(82, 196, 26, 0.28);
            }
            70% {
                box-shadow: 0 0 0 5px rgba(82, 196, 26, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(82, 196, 26, 0);
            }
        }

        .platform-user-copy {
            min-width: 0;
            display: grid;
            gap: 1px;
            flex: 1;
        }

        .platform-user-name {
            color: var(--color-text-main);
            font-size: 14px;
            line-height: 1.5;
            font-weight: 600;
        }

        .platform-user-account {
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.5;
        }

        .platform-user-role-stack,
        .platform-user-contact,
        .platform-user-status-stack {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .platform-user-role-pill,
        .platform-user-status-meta {
            color: #475467;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .platform-user-role-pill.muted,
        .platform-user-contact-value.muted {
            color: #9ca3af;
            font-weight: 400;
        }

        .platform-user-contact {
            gap: 8px;
        }

        .platform-user-contact-item {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .platform-user-meta-label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #98a2b3;
            font-size: 11px;
            line-height: 1.4;
            font-weight: 600;
        }

        .platform-user-meta-label svg {
            width: 13px;
            height: 13px;
            stroke: #c0c7d1;
            fill: none;
            stroke-width: 1.9;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
        }

        .platform-user-contact-value {
            color: #4b5563;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 500;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .platform-user-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            min-width: 0;
        }

        .action-icon-link {
            color: var(--primary-dark);
        }

        .action-icon-link:hover {
            color: var(--primary);
        }

        .action-icon-danger {
            color: #8c8c8c;
        }

        .action-icon-danger:hover {
            color: #ff7875;
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

            .platform-user-list-header {
                display: none;
            }

            .platform-user-item {
                grid-template-columns: 1fr;
                gap: 14px;
                padding: 18px;
                transform: none !important;
                box-shadow: none !important;
            }

            .platform-user-actions {
                justify-content: flex-start;
            }
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">平台管理员</h2>
            <div class="page-header-desc">维护平台级账号、联系方式和平台角色，统一控制跨站点与平台管理能力。</div>
        </div>
        <div>
            <a class="button" href="{{ route('admin.platform.users.create') }}">新增平台管理员</a>
        </div>
    </section>

    @if ($platformUsers->isEmpty())
        <div class="empty-state">当前还没有可维护的平台管理员账号。</div>
    @else
        <section class="platform-user-list-shell">
            <div class="platform-user-list-header">
                <div class="platform-user-list-title">平台管理员</div>
                <div class="platform-user-list-title">所属角色</div>
                <div class="platform-user-list-title">联系方式</div>
                <div class="platform-user-list-title">账号状态</div>
                <div class="platform-user-list-title">操作</div>
            </div>

            <div class="platform-user-list">
                @foreach ($platformUsers as $platformUser)
                    @php
                        $avatar = mb_substr($platformUser->name ?: $platformUser->username, 0, 1);
                        $roleNames = collect(explode('、', (string) $platformUser->role_names))->filter()->values();
                        $roleName = $roleNames->first();
                    @endphp

                    <article class="platform-user-item">
                        <div class="platform-user-main">
                            <span class="platform-user-avatar {{ $platformUser->status ? '' : 'is-offline' }}">{{ $avatar }}</span>
                            <div class="platform-user-copy">
                                <div class="platform-user-name">{{ $platformUser->name ?: $platformUser->username }}</div>
                                <div class="platform-user-account">{{ '@' . $platformUser->username }}</div>
                            </div>
                        </div>

                        <div class="platform-user-role-stack">
                            @if ($roleName)
                                <span class="platform-user-role-pill">{{ $roleName }}</span>
                            @else
                                <span class="platform-user-role-pill muted">未分配角色</span>
                            @endif
                        </div>

                        <div class="platform-user-contact">
                            <div class="platform-user-contact-item">
                                <span class="platform-user-meta-label">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.8 19.8 0 0 1 3.09 4.18 2 2 0 0 1 5.08 2h3a2 2 0 0 1 2 1.72l.41 2.87a2 2 0 0 1-.57 1.72L8.09 10.91a16 16 0 0 0 5 5l2.6-1.83a2 2 0 0 1 1.72-.57l2.87.41A2 2 0 0 1 22 16.92Z"/></svg>
                                    手机
                                </span>
                                <span class="platform-user-contact-value {{ $platformUser->mobile ? '' : 'muted' }}">{{ $platformUser->mobile ?: '未记录' }}</span>
                            </div>
                            <div class="platform-user-contact-item">
                                <span class="platform-user-meta-label">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
                                    邮箱
                                </span>
                                <span class="platform-user-contact-value {{ $platformUser->email ? '' : 'muted' }}">{{ $platformUser->email ?: '未记录' }}</span>
                            </div>
                        </div>

                        <div class="platform-user-status-stack">
                            <span class="platform-user-status-meta">{{ $platformUser->status ? '启用中' : '已停用' }}</span>
                        </div>

                        <div class="platform-user-actions">
                            <a class="icon-button action-icon-link" href="{{ route('admin.platform.users.edit', $platformUser->id) }}" data-tooltip="编辑平台管理员" aria-label="编辑平台管理员 {{ $platformUser->name ?: $platformUser->username }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>
                            </a>
                            @if ((int) auth()->id() !== (int) $platformUser->id)
                                <button
                                    class="icon-button action-icon-danger js-platform-user-delete"
                                    type="button"
                                    data-tooltip="删除平台管理员"
                                    data-form-id="delete-platform-user-{{ $platformUser->id }}"
                                    aria-label="删除平台管理员 {{ $platformUser->name ?: $platformUser->username }}"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                </button>
                                <form id="delete-platform-user-{{ $platformUser->id }}" method="POST" action="{{ route('admin.platform.users.destroy', $platformUser->id) }}" style="display: none;">
                                    @csrf
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection

@push('scripts')
    <script>
        (() => {
            document.querySelectorAll('.js-platform-user-delete').forEach((button) => {
                button.addEventListener('click', () => {
                    const formId = button.dataset.formId;
                    const form = formId ? document.getElementById(formId) : null;

                    if (!form || typeof window.showConfirmDialog !== 'function') {
                        return;
                    }

                    window.showConfirmDialog({
                        title: '确认删除平台管理员？',
                        text: '删除后该账号将失去平台管理能力，且操作不可恢复。',
                        confirmText: '确定删除',
                        onConfirm: () => form.submit(),
                    });
                });
            });
        })();
    </script>
@endpush
