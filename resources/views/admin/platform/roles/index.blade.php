@extends('layouts.admin')

@section('title', '平台角色管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 平台角色管理')

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

        .page-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .platform-role-list {
            display: grid;
            gap: 14px;
        }

        .platform-role-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 22px 24px;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
            transition: background 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        .platform-role-item:hover {
            background: var(--surface-hover);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
            transform: translateY(-1px);
        }

        .platform-role-main {
            flex: 1;
            min-width: 0;
            display: grid;
            gap: 12px;
        }

        .platform-role-title-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .platform-role-name {
            color: #262626;
            font-size: 17px;
            line-height: 1.45;
            font-weight: 700;
        }

        .platform-role-code {
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

        .platform-role-desc {
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.7;
        }

        .platform-role-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .platform-role-metric {
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

        .platform-role-metric.self {
            background: #fff7ed;
            color: #c2410c;
        }

        .platform-role-action {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #4b5563;
            font-size: 14px;
            font-weight: 600;
        }

        .platform-role-action:hover {
            color: #262626;
        }

        .platform-role-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .platform-role-delete {
            color: #8c8c8c;
        }

        .platform-role-delete:hover {
            color: #ff7875;
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">平台角色管理</h2>
            <div class="page-header-desc">集中管理平台角色权限，控制平台工作台、站点管理和平台功能访问。</div>
        </div>
        <div class="page-header-actions">
            <a class="button" href="{{ route('admin.platform.roles.create') }}">新增平台角色</a>
        </div>
    </section>

    <section class="platform-role-list">
        @foreach ($platformRoles as $platformRole)
            <article class="platform-role-item">
                <div class="platform-role-main">
                    <div class="platform-role-title-row">
                        <div class="platform-role-name">{{ $platformRole->name }}</div>
                        <span class="platform-role-code">{{ $platformRole->code }}</span>
                    </div>
                    <div class="platform-role-desc">{{ $platformRole->description ?: '未填写平台角色说明。' }}</div>
                    <div class="platform-role-meta">
                        <span class="platform-role-metric">{{ (int) $platformRole->permission_count }} 项平台权限</span>
                        <span class="platform-role-metric">{{ (int) $platformRole->user_count }} 位管理员</span>
                        @if ($platformRole->is_self_role)
                            <span class="platform-role-metric self">当前登录角色</span>
                        @endif
                        @if ($platformRole->is_locked_role)
                            <span class="platform-role-metric self">系统内置核心角色</span>
                        @endif
                    </div>
                </div>

                <div class="platform-role-actions">
                    <a class="platform-role-action" href="{{ route('admin.platform.roles.edit', $platformRole->id) }}">{{ $platformRole->is_locked_role ? '查看权限' : '配置权限' }}</a>
                    @if (! $platformRole->is_locked_role && (int) $platformRole->user_count === 0)
                        <button
                            class="icon-button platform-role-delete js-platform-role-delete"
                            type="button"
                            data-tooltip="删除平台角色"
                            data-form-id="delete-platform-role-{{ $platformRole->id }}"
                            aria-label="删除平台角色 {{ $platformRole->name }}"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                        </button>
                        <form id="delete-platform-role-{{ $platformRole->id }}" method="POST" action="{{ route('admin.platform.roles.destroy', $platformRole->id) }}" style="display: none;">
                            @csrf
                        </form>
                    @endif
                </div>
            </article>
        @endforeach
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            document.querySelectorAll('.js-platform-role-delete').forEach((button) => {
                button.addEventListener('click', () => {
                    const formId = button.dataset.formId;
                    const form = formId ? document.getElementById(formId) : null;

                    if (!form || typeof window.showConfirmDialog !== 'function') {
                        return;
                    }

                    window.showConfirmDialog({
                        title: '确认删除平台角色？',
                        text: '删除后该平台角色及其权限绑定将被清除，操作不可恢复。',
                        confirmText: '确定删除',
                        onConfirm: () => form.submit(),
                    });
                });
            });
        })();
    </script>
@endpush
