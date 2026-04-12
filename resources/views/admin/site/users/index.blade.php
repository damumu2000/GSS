@extends('layouts.admin')

@section('title', '操作员管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 操作员管理')

@push('styles')
    <link rel="stylesheet" href="/css/site-users.css">
@endpush

@section('content')
    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">操作员管理</h2>
            <div class="page-header-desc">为站点分配独立账号、操作角色和联系方式，并通过统一后台进行内容维护。</div>
        </div>
        <div class="topbar-right">
            <a class="button" href="{{ route('admin.site-users.create') }}">新增操作员</a>
        </div>
    </section>

    <section class="filters-card">
        <form method="GET" action="{{ route('admin.site-users.index') }}" class="filters">
            <div>
                <label for="keyword">搜索用户</label>
                <input class="field" id="keyword" type="text" name="keyword" value="{{ $keyword }}" placeholder="用户名、姓名、邮箱、手机号">
            </div>
            <div>
                <label for="status">状态</label>
                <div class="custom-select" data-select>
                    <select class="custom-select-native" id="status" name="status">
                        <option value="">全部状态</option>
                        <option value="1" @selected($selectedStatus === '1')>启用</option>
                        <option value="0" @selected($selectedStatus === '0')>停用</option>
                    </select>
                    <button class="custom-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">全部状态</button>
                    <div class="custom-select-panel" data-select-panel role="listbox"></div>
                </div>
            </div>
            <div>
                <label for="role_id">角色</label>
                <div class="custom-select" data-select>
                    <select class="custom-select-native" id="role_id" name="role_id">
                        <option value="">全部角色</option>
                        @foreach ($siteRoles as $siteRole)
                            <option value="{{ $siteRole->id }}" @selected($selectedRoleId === (string) $siteRole->id)>{{ $siteRole->name }}</option>
                        @endforeach
                    </select>
                    <button class="custom-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">全部角色</button>
                    <div class="custom-select-panel" data-select-panel role="listbox"></div>
                </div>
            </div>
            <div class="filter-actions-inline">
                <button class="button neutral-action" type="submit">筛选</button>
                <a class="button neutral-action" href="{{ route('admin.site-users.index') }}">重置</a>
            </div>
        </form>
    </section>

    @if ($siteUsers->isEmpty())
        <div class="empty-state">当前站点还没有独立账号。</div>
    @else
        <section class="user-list-shell">
            <div class="user-list-header">
                <div class="user-list-title">操作员</div>
                <div class="user-list-title">所属角色</div>
                <div class="user-list-title">联系方式</div>
                <div class="user-list-title">登录 IP</div>
                <div class="user-list-title">最后登录</div>
                <div class="user-list-title">操作</div>
            </div>

            <div class="user-list">
                @foreach ($siteUsers as $siteUser)
                    @php
                        $roleNames = $siteUser->role_names ? explode('、', $siteUser->role_names) : [];
                        $visibleRoleNames = array_slice($roleNames, 0, 3);
                        $remainingRoleCount = max(count($roleNames) - count($visibleRoleNames), 0);
                        $avatar = mb_substr($siteUser->name ?: $siteUser->username, 0, 1);
                        $lastLoginAt = $siteUser->last_login_at ? \Illuminate\Support\Carbon::parse($siteUser->last_login_at)->format('Y-m-d H:i') : '未登录';
                    @endphp

                    <article class="user-item">
                        <div class="user-main">
                            <span class="user-avatar {{ $siteUser->status ? '' : 'is-offline' }}">
                                @if (!empty($siteUser->avatar))
                                    <img src="{{ $siteUser->avatar }}" alt="{{ $siteUser->name ?: $siteUser->username }}">
                                @else
                                    {{ $avatar }}
                                @endif
                            </span>
                            <div class="user-copy">
                                <div class="user-name">{{ $siteUser->name ?: $siteUser->username }}</div>
                                <div class="user-account">{{ $siteUser->username }}</div>
                            </div>
                        </div>

                        <div class="user-role-stack">
                            @if (! empty($visibleRoleNames))
                                <span class="user-role-pill">{{ implode('、', $visibleRoleNames) }}@if ($remainingRoleCount > 0) +{{ $remainingRoleCount }}@endif</span>
                            @else
                                <span class="user-meta-value muted">未分配角色</span>
                            @endif
                        </div>

                        <div class="user-contact">
                            <div class="user-contact-item">
                                <span class="user-meta-label">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.8 19.8 0 0 1 3.09 4.18 2 2 0 0 1 5.08 2h3a2 2 0 0 1 2 1.72l.41 2.87a2 2 0 0 1-.57 1.72L8.09 10.91a16 16 0 0 0 5 5l2.6-1.83a2 2 0 0 1 1.72-.57l2.87.41A2 2 0 0 1 22 16.92Z"/></svg>
                                    手机
                                </span>
                                <span class="user-meta-value {{ $siteUser->mobile ? '' : 'muted' }}">{{ $siteUser->mobile ?: '未记录' }}</span>
                            </div>
                            <div class="user-contact-item">
                                <span class="user-meta-label">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
                                    邮箱
                                </span>
                                <span class="user-meta-value {{ $siteUser->email ? '' : 'muted' }}">{{ $siteUser->email ?: '未记录' }}</span>
                            </div>
                        </div>

                        <div class="user-status-stack">
                            <span class="user-status-meta">{{ $siteUser->last_login_ip ?: '未记录' }}</span>
                        </div>

                        <div class="user-created-stack">
                            <span class="user-created-date">{{ $lastLoginAt }}</span>
                        </div>

                        <a class="button neutral-action user-edit-button" href="{{ route('admin.site-users.edit', $siteUser->id) }}" aria-label="编辑用户 {{ $siteUser->name ?: $siteUser->username }}">
                            编辑用户
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        </a>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="pagination user-pagination">{{ $siteUsers->links() }}</div>
    @endif
@endsection

@push('scripts')
    <script src="/js/site-users-index.js"></script>
@endpush
