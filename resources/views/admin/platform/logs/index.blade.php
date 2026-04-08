@extends('layouts.admin')

@section('title', ($pageTitle ?? '操作日志') . ' - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / ' . ($pageTitle ?? '操作日志'))

@php
    $actionMap = [
        'create' => '新增',
        'update' => '更新',
        'delete' => '删除',
        'switch' => '切换',
        'restore' => '恢复',
        'upload' => '上传',
        'publish' => '发布',
        'disable' => '停用',
        'enable' => '启用',
        'login' => '登录',
        'logout' => '退出',
        'setting' => '配置',
        'restore_template' => '还原模板',
        'upload_library' => '上传资源',
        'upload_image' => '上传图片',
        'upload_media' => '上传媒体',
        'bulk_delete' => '批量删除',
        'bulk_restore' => '批量恢复',
        'bulk_publish' => '批量发布',
        'edit_template' => '编辑模板',
        'remove' => '删除',
        'create_site' => '新增站点',
        'update_site' => '更新站点',
        'delete_site' => '删除站点',
        'switch_theme' => '切换主题',
        'save_theme' => '保存模板',
        'update_settings' => '更新设置',
        'update_permissions' => '更新权限',
        'submit_review' => '提交审核',
        'approve_content' => '审核通过',
        'reject_content' => '驳回内容',
    ];

    $actionOptions = [
        '' => '全部动作',
        'create' => '新增',
        'update' => '更新',
        'delete' => '删除',
        'switch' => '切换',
        'restore' => '恢复',
        'upload' => '上传',
        'publish' => '发布',
        'disable' => '停用',
        'enable' => '启用',
        'login' => '登录',
        'logout' => '退出',
        'setting' => '配置',
        'restore_template' => '还原模板',
        'upload_library' => '上传资源',
        'upload_image' => '上传图片',
        'upload_media' => '上传媒体',
        'bulk_delete' => '批量删除',
        'bulk_restore' => '批量恢复',
        'bulk_publish' => '批量发布',
        'edit_template' => '编辑模板',
        'remove' => '删除',
        'create_site' => '新增站点',
        'update_site' => '更新站点',
        'delete_site' => '删除站点',
        'switch_theme' => '切换主题',
        'save_theme' => '保存模板',
        'update_settings' => '更新设置',
        'update_permissions' => '更新权限',
        'submit_review' => '提交审核',
        'approve_content' => '审核通过',
        'reject_content' => '驳回内容',
    ];
@endphp

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

        .log-filters-card {
            padding: 16px;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: var(--shadow);
            margin-bottom: 16px;
        }

        .log-filters-card .filters {
            margin-bottom: 0;
            align-items: end;
            grid-template-columns: minmax(240px, 280px) 160px 180px 180px auto;
        }

        .log-filters-card label {
            display: block;
            margin-bottom: 6px;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.4;
        }

        .log-filters-card .custom-select {
            position: relative;
        }

        .log-filters-card .custom-select-native {
            position: absolute;
            inset: 0;
            opacity: 0;
            pointer-events: none;
        }

        .log-filters-card .custom-select-trigger {
            width: 100%;
            min-height: 38px;
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

        .log-filters-card .custom-select-trigger:hover {
            border-color: #d9d9d9;
        }

        .log-filters-card .custom-select.is-open .custom-select-trigger,
        .log-filters-card .custom-select-trigger:focus-visible {
            outline: none;
            border-color: #9ca3af;
            box-shadow: 0 0 0 3px rgba(156, 163, 175, 0.14);
        }

        .log-filters-card .custom-select-trigger::after {
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

        .log-filters-card .custom-select.is-open .custom-select-trigger::after {
            transform: translateY(-50%) rotate(180deg);
        }

        .log-filters-card .custom-select-panel {
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

        .log-filters-card .custom-select-search {
            width: 100%;
            height: 34px;
            margin-bottom: 6px;
            padding: 0 10px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #ffffff;
            color: #4b5563;
            font: inherit;
            font-size: 13px;
            line-height: 34px;
        }

        .log-filters-card .custom-select-search:focus {
            outline: none;
            border-color: #9ca3af;
            box-shadow: 0 0 0 3px rgba(156, 163, 175, 0.14);
        }

        .log-filters-card .custom-select-options {
            display: flex;
            flex-direction: column;
            gap: 2px;
            max-height: 260px;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 2px;
        }

        .log-filters-card .custom-select-empty {
            display: none;
            padding: 10px 12px;
            color: #9ca3af;
            font-size: 12px;
            line-height: 1.5;
        }

        .log-filters-card .custom-select.is-filtering-empty .custom-select-empty {
            display: block;
        }

        .log-filters-card .custom-select.is-open .custom-select-panel {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0) scale(1);
        }

        .log-filters-card .custom-select-option {
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

        .log-filters-card .custom-select-option:hover,
        .log-filters-card .custom-select-option.is-active {
            background: #f5f7fa;
            color: #374151;
        }

        .log-filters-card .custom-select-check {
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

        .log-filters-card .custom-select-option.is-active .custom-select-check {
            opacity: 1;
        }

        .log-table-panel {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .log-table-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 18px 20px 14px;
        }

        .log-table-title {
            margin: 0;
            font-size: 18px;
            line-height: 1.35;
            color: var(--text);
        }

        .log-table-head .badge {
            background: #f3f4f6 !important;
            color: #4b5563 !important;
            border: 1px solid #e5e7eb !important;
            box-shadow: none !important;
        }

        .log-table-wrap {
            overflow-x: auto;
        }

        .log-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .log-table th,
        .log-table td {
            padding: 12px 16px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }

        .log-table th {
            background: #f9fafb;
            color: #8c8c8c;
            font-weight: 600;
        }

        .log-table tbody tr:hover {
            background: #fcfcfd;
        }

        .log-badge {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 0 10px;
            border-radius: 4px;
            background: #f5f7fa;
            color: #4b5563;
            font-size: 12px;
            line-height: 24px;
            font-weight: 600;
        }

        .log-action {
            color: #262626;
            font-weight: 600;
        }

        .log-table th:nth-child(1),
        .log-table td:nth-child(1) {
            width: 180px;
        }

        .log-table th:nth-child(2),
        .log-table td:nth-child(2) {
            width: 88px;
        }

        .log-table th:nth-child(3),
        .log-table td:nth-child(3) {
            width: 120px;
        }

        .log-table th:nth-child(4),
        .log-table td:nth-child(4) {
            width: 120px;
        }

        .log-table th:nth-child(5),
        .log-table td:nth-child(5) {
            width: 140px;
        }

        .log-table th:nth-child(6),
        .log-table td:nth-child(6) {
            width: 220px;
        }

        .log-table td {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .log-pagination {
            padding: 16px 20px 20px;
        }

        .log-pagination nav {
            width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            padding-top: 2px;
        }

        .log-pagination .pagination-shell {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: nowrap;
            min-width: max-content;
        }

        .log-pagination .pagination-pages {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .log-pagination .pagination-button,
        .log-pagination .pagination-page,
        .log-pagination .pagination-ellipsis {
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

        .log-pagination .pagination-page {
            width: 32px;
            padding: 0;
        }

        .log-pagination .pagination-button {
            border: 0;
            background: transparent;
            min-width: auto;
            padding: 0 4px;
            color: #4b5563;
        }

        .log-pagination .pagination-button:hover,
        .log-pagination .pagination-page:hover {
            transform: translateY(-1px);
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .log-pagination .pagination-button:hover {
            background: transparent;
            border-color: transparent;
            color: #262626;
        }

        .log-pagination .pagination-page.is-active,
        .log-pagination .pagination-page.is-active:visited {
            border-color: #374151 !important;
            background: #374151 !important;
            color: #ffffff !important;
            font-weight: 600;
            transform: none;
        }

        .log-pagination .pagination-button.is-disabled,
        .log-pagination .pagination-page.is-disabled,
        .log-pagination .pagination-ellipsis {
            color: #c0c4cc;
            cursor: not-allowed;
        }

        .log-pagination .pagination-button.is-disabled:hover,
        .log-pagination .pagination-page.is-disabled:hover {
            transform: none;
            background: #ffffff;
            border-color: #e5e7eb;
        }

        .log-pagination .pagination-button.is-disabled,
        .log-pagination .pagination-button.is-disabled:hover {
            background: transparent;
            border-color: transparent;
        }

        .log-pagination .pagination-icon {
            width: 14px;
            height: 14px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
        }

        .log-filters-card .button.neutral-action,
        .log-filters-card .button.neutral-action:visited {
            min-width: 88px;
        }

        @media (max-width: 960px) {
            .log-filters-card .filters {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 720px) {
            .page-header {
                margin: -24px -18px 20px;
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
            }

            .log-filters-card .filters {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">{{ $pageTitle ?? '操作日志' }}</h2>
            <div class="page-header-desc">{{ $pageDescription ?? '展示平台级和当前站点相关的最近操作。' }}</div>
        </div>
    </section>

    <section class="log-filters-card">
        <form method="GET" action="{{ $formRoute ?? route('admin.logs.index') }}" class="filters">
            <div>
                <label for="keyword">关键词</label>
                <input class="field" id="keyword" type="text" name="keyword" value="{{ $keyword }}" placeholder="模块、动作、目标类型、操作者">
            </div>
            @if (($showScopeFilter ?? true) === true)
                <div>
                    <label for="scope">范围</label>
                    <div class="custom-select" data-select>
                        <select class="custom-select-native" id="scope" name="scope">
                            <option value="">全部范围</option>
                            <option value="platform" @selected($selectedScope === 'platform')>平台</option>
                            <option value="site" @selected($selectedScope === 'site')>站点</option>
                        </select>
                        <button class="custom-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">全部范围</button>
                        <div class="custom-select-panel" data-select-panel role="listbox"></div>
                    </div>
                </div>
            @endif
            <div>
                <label for="module">模块</label>
                <div class="custom-select" data-select>
                    <select class="custom-select-native" id="module" name="module">
                        <option value="">全部模块</option>
                        @foreach ($moduleOptions as $moduleOption)
                            <option value="{{ $moduleOption }}" @selected($selectedModule === $moduleOption)>{{ $moduleOption }}</option>
                        @endforeach
                    </select>
                    <button class="custom-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">全部模块</button>
                    <div class="custom-select-panel" data-select-panel role="listbox"></div>
                </div>
            </div>
            <div>
                <label for="action">动作</label>
                <div class="custom-select" data-select>
                    <select class="custom-select-native" id="action" name="action">
                        @foreach ($actionOptions as $actionValue => $actionLabel)
                            <option value="{{ $actionValue }}" @selected($selectedAction === $actionValue)>{{ $actionLabel }}</option>
                        @endforeach
                    </select>
                    <button class="custom-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">全部动作</button>
                    <div class="custom-select-panel" data-select-panel role="listbox"></div>
                </div>
            </div>
            <div class="filter-actions">
                <button class="button neutral-action" type="submit">查询</button>
            </div>
        </form>
    </section>

    <section class="log-table-panel">
        <div class="log-table-head">
            <h3 class="log-table-title">最近日志</h3>
            <span class="badge">{{ $logs->total() }} 条</span>
        </div>

        <div class="log-table-wrap">
            <table class="log-table">
                <thead>
                <tr>
                    <th>时间</th>
                    <th>范围</th>
                    <th>模块</th>
                    <th>动作</th>
                    <th>操作者</th>
                    <th>目标</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at }}</td>
                        <td><span class="log-badge">{{ $log->scope === 'platform' ? '平台' : '站点' }}</span></td>
                        <td><span class="log-badge">{{ $log->module ?: '-' }}</span></td>
                        <td class="log-action">{{ $actionMap[$log->action] ?? str_replace('_', ' ', $log->action) }}</td>
                        <td>{{ $log->user_name ?: $log->username ?: '系统' }}</td>
                        <td>{{ $log->target_display ?? (($log->target_type ?: '-') . ($log->target_id ? '#'.$log->target_id : '')) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty">当前筛选条件下没有操作日志。</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination log-pagination">{{ $logs->links() }}</div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            document.querySelectorAll('.log-filters-card [data-select]').forEach((selectRoot) => {
                const nativeSelect = selectRoot.querySelector('.custom-select-native');
                const trigger = selectRoot.querySelector('[data-select-trigger]');
                const panel = selectRoot.querySelector('[data-select-panel]');

                if (!nativeSelect || !trigger || !panel) {
                    return;
                }

                const shouldEnableSearch = nativeSelect.options.length > 8;
                const searchInput = shouldEnableSearch ? document.createElement('input') : null;
                const optionsWrap = document.createElement('div');
                const emptyState = document.createElement('div');

                optionsWrap.className = 'custom-select-options';
                emptyState.className = 'custom-select-empty';
                emptyState.textContent = '没有匹配的动作';

                const buildOptions = () => {
                    panel.innerHTML = '';

                    if (searchInput) {
                        searchInput.type = 'search';
                        searchInput.className = 'custom-select-search';
                        searchInput.placeholder = nativeSelect.id === 'action' ? '搜索动作' : '搜索';
                        panel.appendChild(searchInput);
                    }

                    panel.appendChild(optionsWrap);
                    panel.appendChild(emptyState);
                    optionsWrap.innerHTML = '';

                    Array.from(nativeSelect.options).forEach((option) => {
                        const optionButton = document.createElement('button');
                        optionButton.type = 'button';
                        optionButton.className = 'custom-select-option';
                        optionButton.dataset.value = option.value;

                        if (option.selected) {
                            optionButton.classList.add('is-active');
                            trigger.textContent = option.textContent;
                        }

                        optionButton.innerHTML = `
                            <span>${option.textContent}</span>
                            <svg class="custom-select-check" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5 6.5 11.5 12.5 4.5"/></svg>
                        `;

                        optionButton.addEventListener('click', () => {
                            nativeSelect.value = option.value;
                            Array.from(nativeSelect.options).forEach((nativeOption) => {
                                nativeOption.selected = nativeOption.value === option.value;
                            });
                            buildOptions();
                            closeSelect();
                        });

                        optionsWrap.appendChild(optionButton);
                    });

                    if (searchInput) {
                        searchInput.value = '';
                        filterOptions('');
                    }
                };

                const filterOptions = (keyword) => {
                    const normalizedKeyword = keyword.trim().toLowerCase();
                    let visibleCount = 0;

                    optionsWrap.querySelectorAll('.custom-select-option').forEach((optionButton) => {
                        const text = optionButton.textContent?.toLowerCase() ?? '';
                        const matched = normalizedKeyword === '' || text.includes(normalizedKeyword);
                        optionButton.hidden = !matched;

                        if (matched) {
                            visibleCount += 1;
                        }
                    });

                    selectRoot.classList.toggle('is-filtering-empty', visibleCount === 0);
                };

                const closeSelect = () => {
                    selectRoot.classList.remove('is-open');
                    trigger.setAttribute('aria-expanded', 'false');
                };

                trigger.addEventListener('click', () => {
                    const nextState = !selectRoot.classList.contains('is-open');
                    document.querySelectorAll('.log-filters-card [data-select].is-open').forEach((openSelect) => {
                        openSelect.classList.remove('is-open');
                        const openTrigger = openSelect.querySelector('[data-select-trigger]');
                        openTrigger?.setAttribute('aria-expanded', 'false');
                    });
                    if (nextState) {
                        selectRoot.classList.add('is-open');
                        trigger.setAttribute('aria-expanded', 'true');
                        if (searchInput) {
                            queueMicrotask(() => searchInput.focus());
                        }
                    }
                });

                document.addEventListener('click', (event) => {
                    if (!selectRoot.contains(event.target)) {
                        closeSelect();
                    }
                });

                buildOptions();

                if (searchInput) {
                    searchInput.addEventListener('input', () => {
                        filterOptions(searchInput.value);
                    });
                }
            });
        })();
    </script>
@endpush
