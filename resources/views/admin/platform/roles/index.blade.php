@extends('layouts.admin')

@section('title', '平台角色管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 平台角色管理')

@push('styles')
    <link rel="stylesheet" href="/css/platform-roles.css">
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
                        <form id="delete-platform-role-{{ $platformRole->id }}" method="POST" action="{{ route('admin.platform.roles.destroy', $platformRole->id) }}" class="u-hidden-form">
                            @csrf
                        </form>
                    @endif
                </div>
            </article>
        @endforeach
    </section>
@endsection

@push('scripts')
    <script src="/js/platform-roles-index.js"></script>
@endpush
