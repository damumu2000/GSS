@extends('layouts.admin')

@section('title', '平台管理员 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 平台管理员')

@push('styles')
    <link rel="stylesheet" href="/css/platform-users-index.css">
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
                                <form id="delete-platform-user-{{ $platformUser->id }}" method="POST" action="{{ route('admin.platform.users.destroy', $platformUser->id) }}" class="u-hidden-form">
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
    <script src="/js/platform-users-index.js"></script>
@endpush
