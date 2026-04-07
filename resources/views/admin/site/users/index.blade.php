@extends('layouts.admin')

@section('title', '操作员管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 操作员管理')

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

        .filters-card {
            padding: 16px 18px;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
            margin-bottom: 16px;
        }

        .filters-card .filters {
            gap: 12px;
            margin-bottom: 0;
            grid-template-columns: minmax(220px, 320px) 160px 180px auto auto;
            align-items: end;
        }

        .filters-card .field {
            height: 38px;
            padding: 0 12px;
            border: 1px solid #e5e6eb;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 400;
            line-height: 38px;
            box-shadow: none;
            background: #ffffff;
        }

        .filters-card label {
            display: block;
            margin-bottom: 6px;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 400;
        }

        .filters-card .field:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 71, 171, 0.12);
        }

        .custom-select {
            position: relative;
        }

        .custom-select-native {
            position: absolute;
            inset: 0;
            opacity: 0;
            pointer-events: none;
        }

        .custom-select-trigger {
            width: 100%;
            height: 38px;
            padding: 0 36px 0 12px;
            border: 1px solid #e5e6eb;
            border-radius: 8px;
            background: #ffffff;
            color: #595959;
            font: inherit;
            font-size: 13px;
            font-weight: 400;
            line-height: 38px;
            text-align: left;
            cursor: pointer;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
            position: relative;
        }

        .custom-select-trigger:hover {
            border-color: #d9d9d9;
        }

        .custom-select.is-open .custom-select-trigger,
        .custom-select-trigger:focus-visible {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 71, 171, 0.12);
        }

        .custom-select-trigger::after {
            content: "";
            position: absolute;
            right: 12px;
            top: 50%;
            width: 12px;
            height: 12px;
            transform: translateY(-50%);
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12' fill='none'%3E%3Cpath d='M3 4.5L6 7.5L9 4.5' stroke='%2398A2B3' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center/12px 12px no-repeat;
            transition: transform 0.18s ease;
        }

        .custom-select.is-open .custom-select-trigger::after {
            transform: translateY(-50%) rotate(180deg);
        }

        .custom-select-panel {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            padding: 6px;
            border: 1px solid #f0f0f0;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
            opacity: 0;
            pointer-events: none;
            transform: translateY(-2px) scale(0.95);
            transform-origin: top center;
            transition: opacity 0.16s ease, transform 0.16s ease;
            z-index: 1500;
        }

        .custom-select.is-open .custom-select-panel {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0) scale(1);
        }

        .custom-select-option {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: #595959;
            font: inherit;
            font-size: 13px;
            font-weight: 400;
            line-height: 1.4;
            text-align: left;
            cursor: pointer;
        }

        .custom-select-option:hover {
            background: #f5f7fa;
            color: #374151;
        }

        .custom-select-option.is-active {
            background: #f5f7fa;
            color: #374151;
        }

        .custom-select-check {
            width: 14px;
            height: 14px;
            stroke: #4b5563;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            opacity: 0;
            flex-shrink: 0;
        }

        .custom-select-option.is-active .custom-select-check {
            opacity: 1;
        }

        .filters-card .button.neutral-action,
        .filters-card .button.neutral-action:visited {
            height: 38px;
            min-width: 84px;
            padding: 0 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            line-height: 38px;
        }

        .filter-actions-inline {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-list-shell {
            overflow: hidden;
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid #edf1f5;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
        }

        .user-list-header,
        .user-item {
            display: grid;
            grid-template-columns: minmax(0, 2.2fr) minmax(170px, 1.1fr) minmax(220px, 1.35fr) minmax(130px, 0.78fr) 110px 130px;
            gap: 16px;
            align-items: center;
            padding: 16px 22px;
        }

        .user-list-header {
            border-bottom: 1px solid #eef1f4;
            background: #fbfcfd;
        }

        .user-list-title {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.5;
            font-weight: 700;
        }

        .user-list {
            display: grid;
        }

        .user-item {
            position: relative;
            border-bottom: 1px solid #f2f4f7;
            transition: background-color 0.18s ease, border-color 0.18s ease;
        }

        .user-item:last-child {
            border-bottom: 0;
        }

        .user-item:hover {
            background: #fafcff;
            box-shadow: inset 2px 0 0 var(--primary);
        }

        .user-main {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .user-avatar {
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
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .user-avatar::after {
            content: "";
            position: absolute;
            right: 1px;
            bottom: 1px;
            width: 9px;
            height: 9px;
            border-radius: 999px;
            border: 2px solid #ffffff;
            background: #52c41a;
            box-shadow: 0 0 0 0 rgba(82, 196, 26, 0.30);
            animation: user-status-pulse 1.9s ease-out infinite;
        }

        .user-avatar.is-offline::after {
            background: #cbd5e1;
            box-shadow: none;
            animation: none;
        }

        .user-name {
            color: var(--color-text-main);
            font-size: 14px;
            line-height: 1.5;
            font-weight: 600;
        }

        .user-copy {
            min-width: 0;
            display: grid;
            gap: 1px;
            flex: 1;
        }

        .user-account {
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.5;
        }

        .user-contact {
            display: grid;
            gap: 8px;
            min-width: 0;
        }

        .user-contact-item {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .user-meta-label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #98a2b3;
            font-size: 11px;
            line-height: 1.4;
            font-weight: 600;
        }

        .user-meta-label svg {
            width: 13px;
            height: 13px;
            stroke: #c0c7d1;
            fill: none;
            stroke-width: 1.9;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
        }

        .user-meta-value {
            color: #4b5563;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 500;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-meta-value.muted {
            color: #9ca3af;
            font-weight: 400;
        }

        .user-role-stack,
        .user-status-stack,
        .user-created-stack {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .user-status-stack {
            max-width: 150px;
        }

        .user-role-pill {
            max-width: 100%;
            color: #667085;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-status-meta {
            color: #475467;
            font-size: 13px;
            line-height: 1.6;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
        }

        .user-created-date {
            color: #475467;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 500;
        }

        .user-edit-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 96px;
            justify-self: end;
            border-radius: 10px;
        }

        .user-edit-button svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .empty-state {
            padding: 40px 24px;
            text-align: center;
            color: #8c8c8c;
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #f0f0f0;
        }

        @keyframes user-status-pulse {
            0% { box-shadow: 0 0 0 0 rgba(82, 196, 26, 0.30); }
            70% { box-shadow: 0 0 0 5px rgba(82, 196, 26, 0); }
            100% { box-shadow: 0 0 0 0 rgba(82, 196, 26, 0); }
        }

        .user-pagination {
            margin-top: 16px;
        }

        .user-pagination nav {
            width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            padding-top: 2px;
        }

        .user-pagination .pagination-shell {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: nowrap;
            min-width: max-content;
        }

        .user-pagination .pagination-pages {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-pagination .pagination-button,
        .user-pagination .pagination-page,
        .user-pagination .pagination-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            height: 32px;
            min-width: 32px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            color: #4b5563;
            font-size: 13px;
            line-height: 1;
            text-decoration: none;
            transition: all 0.2s;
        }

        .user-pagination .pagination-page {
            width: 32px;
            padding: 0;
        }

        .user-pagination .pagination-button {
            border: 0;
            background: transparent;
            min-width: auto;
            padding: 0 4px;
            color: #4b5563;
        }

        .user-pagination .pagination-button:hover,
        .user-pagination .pagination-page:hover {
            transform: translateY(-1px);
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .user-pagination .pagination-button:hover {
            background: transparent;
            border-color: transparent;
            color: #262626;
        }

        .user-pagination .pagination-page.is-active,
        .user-pagination .pagination-page.is-active:visited {
            border-color: #374151 !important;
            background: #374151 !important;
            color: #ffffff !important;
            font-weight: 600;
            transform: none;
        }

        .user-pagination .pagination-button.is-disabled,
        .user-pagination .pagination-page.is-disabled,
        .user-pagination .pagination-ellipsis {
            color: #c0c4cc;
            cursor: not-allowed;
        }

        .user-pagination .pagination-button.is-disabled:hover,
        .user-pagination .pagination-page.is-disabled:hover {
            transform: none;
            background: #ffffff;
            border-color: #e5e7eb;
        }

        .user-pagination .pagination-button.is-disabled,
        .user-pagination .pagination-button.is-disabled:hover {
            background: transparent;
            border-color: transparent;
        }

        .user-pagination .pagination-icon {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        @media (max-width: 768px) {
            .page-header {
                margin: -24px -18px 20px;
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
            }

            .user-list-header {
                display: none;
            }

            .user-list-shell {
                border-radius: 14px;
            }

            .user-item {
                grid-template-columns: 1fr;
                gap: 14px;
                padding: 18px;
                transform: none !important;
                box-shadow: none !important;
            }

            .filters-card .filters {
                grid-template-columns: 1fr;
            }

            .user-edit-button {
                justify-self: start;
            }
        }
    </style>
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
    <script>
        (() => {
            document.querySelectorAll('[data-select]').forEach((selectRoot) => {
                const nativeSelect = selectRoot.querySelector('.custom-select-native');
                const trigger = selectRoot.querySelector('[data-select-trigger]');
                const panel = selectRoot.querySelector('[data-select-panel]');

                if (!nativeSelect || !trigger || !panel) {
                    return;
                }

                const render = () => {
                    const options = Array.from(nativeSelect.options);
                    const selectedOption = options[nativeSelect.selectedIndex] ?? options[0];
                    trigger.textContent = selectedOption?.textContent?.trim() || '';
                    panel.innerHTML = options.map((option) => {
                        const isActive = option.selected ? 'is-active' : '';
                        return `
                            <button class="custom-select-option ${isActive}" type="button" data-value="${option.value}" role="option" aria-selected="${option.selected ? 'true' : 'false'}">
                                <span>${option.textContent}</span>
                                <svg class="custom-select-check" viewBox="0 0 16 16" aria-hidden="true"><path d="m3.5 8 2.5 2.5 6-6"/></svg>
                            </button>
                        `;
                    }).join('');
                };

                render();

                trigger.addEventListener('click', () => {
                    document.querySelectorAll('[data-select].is-open').forEach((item) => {
                        if (item !== selectRoot) {
                            item.classList.remove('is-open');
                            item.querySelector('[data-select-trigger]')?.setAttribute('aria-expanded', 'false');
                        }
                    });

                    const open = !selectRoot.classList.contains('is-open');
                    selectRoot.classList.toggle('is-open', open);
                    trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
                });

                panel.addEventListener('click', (event) => {
                    const option = event.target.closest('.custom-select-option');
                    if (!option) {
                        return;
                    }

                    nativeSelect.value = option.dataset.value ?? '';
                    nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    render();
                    selectRoot.classList.remove('is-open');
                    trigger.setAttribute('aria-expanded', 'false');
                });

                nativeSelect.addEventListener('change', render);
            });

            document.addEventListener('click', (event) => {
                document.querySelectorAll('[data-select].is-open').forEach((selectRoot) => {
                    if (!selectRoot.contains(event.target)) {
                        selectRoot.classList.remove('is-open');
                        selectRoot.querySelector('[data-select-trigger]')?.setAttribute('aria-expanded', 'false');
                    }
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    document.querySelectorAll('[data-select].is-open').forEach((selectRoot) => {
                        selectRoot.classList.remove('is-open');
                        selectRoot.querySelector('[data-select-trigger]')?.setAttribute('aria-expanded', 'false');
                    });
                }
            });
        })();
    </script>
@endpush
