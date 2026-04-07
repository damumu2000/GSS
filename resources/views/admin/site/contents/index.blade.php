@extends('layouts.admin')

@section('title', $typeLabel . '管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / ' . $typeLabel . '管理')

@push('styles')
    @include('admin.site._custom_select_styles')
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

        .panel.content-page-panel {
            overflow: visible;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
            padding: 0;
        }

        .content-record-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 34px;
            padding: 0 14px;
            border-radius: 999px;
            background: #f5f7fb;
            color: #4b5563;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .content-filter-card {
            padding: 20px 22px;
            border: 1px solid #eef1f5;
            border-radius: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
            margin-bottom: 0;
        }

        .content-filter-grid {
            display: grid;
            grid-template-columns: minmax(260px, 1.15fr) repeat(2, minmax(180px, 0.82fr)) auto;
            gap: 16px;
            align-items: end;
        }

        .content-filter-grid label {
            display: block;
            margin-bottom: 8px;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 600;
        }

        .content-filter-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: flex-end;
            padding-bottom: 2px;
        }

        .content-bulk-form {
            display: flex;
            align-items: center;
            gap: 12px;
            justify-content: flex-start;
            flex-wrap: nowrap;
        }

        .content-bulk-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 22px 6px 12px;
            position: relative;
            z-index: 20;
        }

        .content-bulk-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .content-table-wrap {
            border: 1px solid #eef1f5;
            border-radius: 20px;
            overflow: hidden;
            background: #ffffff;
            margin-top: 0;
        }

        .content-list-panel {
            padding: 20px 22px;
            border: 1px solid #eef1f5;
            border-radius: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
            margin-top: 18px;
        }

        .content-list-panel .panel-header {
            margin-bottom: 18px;
        }

        .content-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .content-table thead th {
            padding: 18px 16px;
            background: #fafbfc;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 700;
            letter-spacing: 0.02em;
            border-bottom: 1px solid #eef1f5;
            text-align: left;
        }

        .content-table tbody td {
            padding: 18px 16px;
            border-bottom: 1px solid #f3f4f6;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.6;
            vertical-align: middle;
        }

        .content-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .content-table th:nth-child(1),
        .content-table td:nth-child(1) {
            width: 38px;
            text-align: center;
        }

        .content-table th:nth-child(2),
        .content-table td:nth-child(2) {
            width: 44%;
            padding-left: 4px;
        }

        .content-table th:nth-child(3),
        .content-table td:nth-child(3) {
            width: 14%;
        }

        .content-table th:nth-child(4),
        .content-table td:nth-child(4) {
            width: 12%;
        }

        .content-table th:nth-child(5),
        .content-table td:nth-child(5) {
            width: 16%;
        }

        .content-table th:nth-child(6),
        .content-table td:nth-child(6) {
            width: 14%;
        }

        .content-checkbox {
            display: block;
            width: 16px;
            height: 16px;
            margin: 0 auto;
            border-radius: 4px;
        }

        .content-title-wrap {
            min-width: 0;
        }

        .content-title-row {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .content-title-meta {
            padding-left: 32px;
        }

        .content-drag-handle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 8px;
            color: #94a3b8;
            cursor: grab;
            flex: 0 0 auto;
            transition: background 0.18s ease, color 0.18s ease;
        }

        .content-drag-handle:hover {
            background: var(--primary-soft);
            color: var(--primary);
        }

        .content-drag-handle:active {
            cursor: grabbing;
        }

        .content-drag-handle svg {
            width: 14px;
            height: 14px;
            fill: currentColor;
        }

        .content-row-ghost td {
            background: color-mix(in srgb, var(--primary, #0047AB) 8%, #ffffff) !important;
        }

        .content-row-chosen td {
            background: color-mix(in srgb, var(--primary, #0047AB) 12%, #ffffff) !important;
        }

        .content-row-drag td {
            opacity: 0.96;
        }

        tr[data-content-row].is-saving td {
            background: color-mix(in srgb, var(--primary, #0047AB) 5%, #ffffff) !important;
        }

        .content-title {
            color: #1f2937;
            font-size: 15px;
            line-height: 1.6;
            font-weight: 400;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            overflow: hidden;
        }

        .content-title-text {
            min-width: 0;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .content-title-flags {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex: 0 0 auto;
        }

        .content-title-flag {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 7px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
            box-shadow: inset 0 0 0 1px transparent;
        }

        .content-title-flag.is-top {
            background: rgba(0, 71, 171, 0.08);
            color: var(--primary, #0047AB);
            box-shadow: inset 0 0 0 1px rgba(0, 71, 171, 0.12);
        }

        .content-title-flag.is-recommend {
            background: rgba(245, 158, 11, 0.12);
            color: #c2410c;
            box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.18);
        }

        .content-summary {
            margin-top: 6px;
            color: #9aa1ab;
            font-size: 13px;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .content-review-note {
            margin-top: 6px;
            color: #b45309;
            font-size: 12px;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .content-pending-note {
            margin-top: 6px;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.6;
        }

        .content-channel-tag {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            color: #4b5563;
            font-size: 13px;
            font-weight: 600;
            min-height: auto;
            padding: 0;
            border-radius: 0;
            background: transparent;
            max-width: 100%;
            cursor: default;
        }

        .content-channel-tag-label {
            display: inline-block;
            max-width: 100%;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .content-channel-tag-more {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 18px;
            margin-left: 6px;
            padding: 0 6px;
            border-radius: 999px;
            background: rgba(0, 71, 171, 0.08);
            color: var(--primary, #0047AB);
            font-size: 10px;
            line-height: 18px;
            font-weight: 700;
            letter-spacing: 0.01em;
            box-shadow: inset 0 0 0 1px rgba(0, 71, 171, 0.12);
            vertical-align: middle;
        }

        .content-status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 28px;
            padding: 0 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .content-status-pill.is-published {
            background: rgba(34, 197, 94, 0.1);
            color: #15803d;
        }

        .content-status-pill.is-pending {
            background: rgba(245, 158, 11, 0.12);
            color: #b45309;
        }

        .content-status-pill.is-draft {
            background: #f3f4f6;
            color: #6b7280;
        }

        .content-status-pill.is-rejected {
            background: rgba(239, 68, 68, 0.1);
            color: #c2410c;
        }

        .content-date {
            display: inline-grid;
            gap: 2px;
            color: #6b7280;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.4;
        }

        .content-date-day {
            color: #4b5563;
        }

        .content-date-time {
            color: #9aa1ab;
            font-size: 12px;
            font-weight: 500;
        }

        .content-date.is-muted {
            color: #a0a7b4;
            font-weight: 500;
        }

        .content-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: flex-start;
            white-space: nowrap;
        }

        .content-action-link,
        .content-action-danger {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            border: 1px solid #e7ebf1;
            background: #fff;
            color: #667085;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: border-color 0.2s ease, background 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }

        .content-action-link svg,
        .content-action-danger svg {
            width: 17px;
            height: 17px;
            stroke: currentColor;
            stroke-width: 1.9;
            fill: none;
        }

        .content-action-link:hover {
            border-color: #d8e0ea;
            background: #f8fafc;
            color: #344054;
            transform: translateY(-1px);
        }

        .content-action-danger {
            padding: 0;
            cursor: pointer;
        }

        .content-action-danger:hover {
            border-color: rgba(239, 68, 68, 0.18);
            background: rgba(239, 68, 68, 0.06);
            color: #ef4444;
            transform: translateY(-1px);
        }

        .content-pagination {
            padding: 18px 6px 4px;
        }

        .content-pagination nav {
            width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            padding-top: 2px;
        }

        .content-pagination .pagination-shell {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: nowrap;
            min-width: max-content;
        }

        .content-pagination .pagination-pages {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .content-pagination .pagination-button,
        .content-pagination .pagination-page,
        .content-pagination .pagination-ellipsis {
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

        .content-pagination .pagination-page {
            width: 32px;
            padding: 0;
        }

        .content-pagination .pagination-button {
            border: 0;
            background: transparent;
            min-width: auto;
            padding: 0 4px;
            color: #4b5563;
        }

        .content-pagination .pagination-button:hover,
        .content-pagination .pagination-page:hover {
            transform: translateY(-1px);
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .content-pagination .pagination-button:hover {
            background: transparent;
            border-color: transparent;
            color: #262626;
        }

        .content-pagination .pagination-page.is-active,
        .content-pagination .pagination-page.is-active:visited {
            border-color: #374151 !important;
            background: #374151 !important;
            color: #ffffff !important;
            font-weight: 600;
            transform: none;
        }

        .content-pagination .pagination-button.is-disabled,
        .content-pagination .pagination-page.is-disabled,
        .content-pagination .pagination-ellipsis {
            color: #c0c4cc;
            cursor: not-allowed;
        }

        .content-pagination .pagination-button.is-disabled:hover,
        .content-pagination .pagination-page.is-disabled:hover {
            transform: none;
            background: #ffffff;
            border-color: #e5e7eb;
        }

        .content-pagination .pagination-button.is-disabled,
        .content-pagination .pagination-button.is-disabled:hover {
            background: transparent;
            border-color: transparent;
        }

        .content-pagination .pagination-icon {
            width: 14px;
            height: 14px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
        }

        @media (max-width: 720px) {
            .page-header {
                margin: -24px -18px 20px;
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 1180px) {
            .content-filter-grid {
                grid-template-columns: 1fr 1fr;
            }

            .content-filter-actions {
                grid-column: 1 / -1;
                justify-content: flex-start;
            }

            .content-bulk-row {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">{{ $typeLabel }}管理</h2>
            <div class="page-header-desc">用于统一管理{{ $typeLabel }}的查询、筛选与批量操作。</div>
        </div>
        <div class="topbar-right">
            <a class="button" href="{{ $type === 'page' ? route('admin.pages.create') : route('admin.articles.create') }}">新建{{ $typeLabel }}</a>
        </div>
    </section>

    <section class="panel content-page-panel">
        <form method="GET" action="{{ $type === 'page' ? route('admin.pages.index') : route('admin.articles.index') }}" class="content-filter-card">
            <div class="content-filter-grid">
                <div>
                    <label for="keyword">搜索{{ $typeLabel }}</label>
                    <input class="field" id="keyword" type="text" name="keyword" value="{{ $keyword }}" placeholder="标题或摘要关键词">
                </div>
                <div>
                    <label for="channel_filter">所属栏目</label>
                    <div class="site-select channel-parent-select" data-site-select>
                        <select id="channel_filter" name="channel_id" class="field site-select-native">
                            <option value="">全部栏目</option>
                            @foreach ($channels as $channel)
                                <option
                                    value="{{ $channel->id }}"
                                    data-depth="{{ (int) ($channel->tree_depth ?? 0) }}"
                                    data-has-children="{{ !empty($channel->tree_has_children) ? '1' : '0' }}"
                                    @selected($selectedChannelId === (string) $channel->id)
                                >{{ $channel->name }}</option>
                            @endforeach
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ collect($channels)->firstWhere('id', (int) $selectedChannelId)?->name ?? '全部栏目' }}</button>
                        <div class="site-select-panel" data-select-panel role="listbox"></div>
                    </div>
                </div>
                <div>
                    <label for="status_filter">{{ $statusFilterLabel ?? '发布状态' }}</label>
                    <div class="site-select" data-site-select>
                        <select id="status_filter" name="status" class="field site-select-native">
                            <option value="">全部状态</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ $statuses[$selectedStatus] ?? '全部状态' }}</button>
                        <div class="site-select-panel" data-select-panel role="listbox"></div>
                    </div>
                </div>
                <div class="content-filter-actions">
                    <button class="button neutral-action" type="submit">筛选</button>
                    <a class="button neutral-action" href="{{ $type === 'page' ? route('admin.pages.index') : route('admin.articles.index') }}">重置</a>
                </div>
            </div>
        </form>

        <div class="content-list-panel">
            @if ($contents->isEmpty())
                <div class="empty">当前条件下没有{{ $typeLabel }}记录，点击右上角“新建{{ $typeLabel }}”开始录入。</div>
            @else
                <div class="content-table-wrap">
                <table class="content-table">
                    <thead>
                    <tr>
                        <th></th>
                        <th>标题</th>
                        <th>栏目</th>
                        <th>状态</th>
                        <th>发布时间</th>
                        <th>操作</th>
                    </tr>
                    </thead>
                    <tbody
                        @if ($type === 'article')
                            data-content-reorder-url="{{ route('admin.articles.reorder') }}"
                        @endif
                    >
                    @foreach ($contents as $content)
                        @php
                            $channelNames = collect($content->channel_names ?? [])->filter()->values();
                            $primaryChannelName = $channelNames->first() ?: '未归类';
                            $extraChannelCount = max($channelNames->count() - 1, 0);
                            $channelTooltip = $channelNames->isNotEmpty()
                                ? $channelNames->implode('、')
                                : '未归类';
                            $titleStyle = [];

                            if (! empty($content->title_color)) {
                                $titleStyle[] = 'color: '.$content->title_color;
                            }

                            if (! empty($content->title_bold)) {
                                $titleStyle[] = 'font-weight: 700';
                            }

                            if (! empty($content->title_italic)) {
                                $titleStyle[] = 'font-style: italic';
                            }
                        @endphp
                        <tr
                            @if ($type === 'article')
                                data-content-row
                                data-content-id="{{ $content->id }}"
                            @endif
                        >
                            <td>
                                <input class="content-checkbox" type="checkbox" name="ids[]" value="{{ $content->id }}" form="content-bulk-form">
                            </td>
                            <td>
                                <div class="content-title-wrap">
                                    <div class="content-title-row">
                                        @if ($type === 'article')
                                            <span class="content-drag-handle" aria-label="拖拽排序" data-tooltip="拖拽排序">
                                                <svg viewBox="0 0 20 20" aria-hidden="true">
                                                    <circle cx="6" cy="5" r="1.4"></circle>
                                                    <circle cx="6" cy="10" r="1.4"></circle>
                                                    <circle cx="6" cy="15" r="1.4"></circle>
                                                    <circle cx="14" cy="5" r="1.4"></circle>
                                                    <circle cx="14" cy="10" r="1.4"></circle>
                                                    <circle cx="14" cy="15" r="1.4"></circle>
                                                </svg>
                                            </span>
                                        @endif
                                        <div class="content-title" data-tooltip="ID {{ $content->id }} · {{ $content->title }}">
                                            <span class="content-title-text" style="{{ implode('; ', $titleStyle) }}">{{ $content->title }}</span>
                                            @if ($type === 'article' && ($content->is_top || $content->is_recommend))
                                                <span class="content-title-flags">
                                                    @if ($content->is_top)
                                                        <span class="content-title-flag is-top">顶</span>
                                                    @endif
                                                    @if ($content->is_recommend)
                                                        <span class="content-title-flag is-recommend">精</span>
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    @if (!empty($content->summary) || ($content->status === 'rejected' && !empty($content->latest_reject_reason)) || $content->status === 'pending')
                                        <div class="content-title-meta">
                                            @if (!empty($content->summary))
                                                <div class="content-summary">{{ $content->summary }}</div>
                                            @endif
                                            @if ($content->status === 'rejected' && !empty($content->latest_reject_reason))
                                                <div class="content-review-note">最近驳回：{{ $content->latest_reject_reason }}@if((int) ($content->reject_count ?? 0) > 1) · 共 {{ (int) $content->reject_count }} 次 @endif</div>
                                            @elseif ($content->status === 'pending')
                                                <div class="content-pending-note">已提交审核，待审核人处理后才会正式上线。</div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="content-channel-tag" data-tooltip="{{ $channelTooltip }}">
                                    <span class="content-channel-tag-label">{{ $primaryChannelName }}</span>
                                    @if ($extraChannelCount > 0)
                                        <span class="content-channel-tag-more">+{{ $extraChannelCount }}</span>
                                    @endif
                                </span>
                            </td>
                            <td>
                                @php
                                    $statusClass = match ($content->status) {
                                        'published' => 'is-published',
                                        'pending' => 'is-pending',
                                        'rejected' => 'is-rejected',
                                        default => 'is-draft',
                                    };
                                    $statusLabel = $statuses[$content->status]
                                        ?? match ($content->status) {
                                            'draft' => '草稿',
                                            'pending' => '待审核',
                                            'published' => '已发布',
                                            'offline' => '已下线',
                                            'rejected' => '已驳回',
                                            default => $content->status,
                                        };
                                @endphp
                                <span class="content-status-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td>
                                @if (!empty($content->published_at))
                                    <span class="content-date">
                                        <span class="content-date-day">{{ \Illuminate\Support\Carbon::parse($content->published_at)->format('Y-m-d') }}</span>
                                        <span class="content-date-time">{{ \Illuminate\Support\Carbon::parse($content->published_at)->format('H:i:s') }}</span>
                                    </span>
                                @else
                                    <span class="content-date is-muted">未发布</span>
                                @endif
                            </td>
                            <td>
                                <div class="content-actions">
                                    <a class="content-action-link" href="{{ ($type === 'page' ? route('admin.pages.edit', $content->id) : route('admin.articles.edit', $content->id)) . '?return_to=' . urlencode(request()->fullUrl()) }}" aria-label="编辑" data-tooltip="编辑">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </a>
                                    <form id="delete-content-{{ $content->id }}" method="POST" action="{{ $type === 'page' ? route('admin.pages.destroy', $content->id) : route('admin.articles.destroy', $content->id) }}">
                                        @csrf
                                        <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                        <button class="content-action-danger js-content-delete" type="button" data-form-id="delete-content-{{ $content->id }}" aria-label="删除" data-tooltip="删除">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>

                <div class="content-bulk-row">
                    <div class="content-bulk-left">
                        @php
                            $primaryBulkLabel = ($type === 'article' && $articleRequiresReview)
                                ? '批量提交审核'
                                : '批量发布';
                        @endphp
                        <button class="button neutral-action" type="button" id="content-bulk-toggle-all">全选</button>
                        <div>
                            <div class="site-select field-sm is-dropup" data-site-select style="min-width: 168px;">
                                <select id="content_bulk_action" name="action" class="field field-sm site-select-native" form="content-bulk-form">
                                    @if ($canPublish)
                                        <option value="publish">{{ $primaryBulkLabel }}</option>
                                    @endif
                                    <option value="offline">批量下线</option>
                                    <option value="delete">批量删除</option>
                                </select>
                                <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ $canPublish ? $primaryBulkLabel : '批量下线' }}</button>
                                <div class="site-select-panel" data-select-panel role="listbox"></div>
                            </div>
                        </div>
                        <form id="content-bulk-form" method="POST" action="{{ $type === 'page' ? route('admin.pages.bulk') : route('admin.articles.bulk') }}" class="content-bulk-form">
                            @csrf
                            <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                            <button class="button neutral-action js-bulk-submit" type="button">批量操作</button>
                        </form>
                    </div>
                    <span class="content-record-badge">{{ $contents->total() }} 条记录</span>
                </div>

                <div class="content-pagination">{{ $contents->links() }}</div>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
    @if ($type === 'article')
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
    @endif
    <script>
        (() => {
            const toggleAllButton = document.getElementById('content-bulk-toggle-all');
            const checkboxes = Array.from(document.querySelectorAll('.content-checkbox'));
            const bulkButton = document.querySelector('.js-bulk-submit');
            const bulkForm = document.getElementById('content-bulk-form');
            const tbody = document.querySelector('tbody[data-content-reorder-url]');
            const reorderUrl = tbody?.dataset.contentReorderUrl;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            if (toggleAllButton && checkboxes.length > 0) {
                const syncToggleLabel = () => {
                    const allChecked = checkboxes.every((checkbox) => checkbox.checked);
                    toggleAllButton.textContent = allChecked ? '取消全选' : '全选';
                };

                toggleAllButton.addEventListener('click', () => {
                    const allChecked = checkboxes.every((checkbox) => checkbox.checked);
                    checkboxes.forEach((checkbox) => {
                        checkbox.checked = !allChecked;
                    });
                    syncToggleLabel();
                });

                checkboxes.forEach((checkbox) => {
                    checkbox.addEventListener('change', syncToggleLabel);
                });

                syncToggleLabel();
            }

            if (bulkButton && bulkForm) {
                bulkButton.addEventListener('click', () => {
                    if (typeof window.showConfirmDialog !== 'function') {
                        bulkForm.submit();
                        return;
                    }

                    window.showConfirmDialog({
                        title: '确认执行批量操作？',
                        text: '批量操作将立即对已勾选内容生效，请确认后继续。',
                        confirmText: '确认执行',
                        onConfirm: () => bulkForm.submit(),
                    });
                });
            }

            document.querySelectorAll('.js-content-delete').forEach((button) => {
                button.addEventListener('click', () => {
                    const formId = button.dataset.formId;
                    const form = formId ? document.getElementById(formId) : null;

                    if (!form || typeof window.showConfirmDialog !== 'function') {
                        return;
                    }

                    window.showConfirmDialog({
                        title: '确认删除这条记录？',
                        text: '删除后内容会进入回收站，可在回收站中继续处理。',
                        confirmText: '确认删除',
                        onConfirm: () => form.submit(),
                    });
                });
            });

            if (tbody && reorderUrl && window.Sortable) {
                const getVisibleOrderedIds = () => Array.from(tbody.querySelectorAll('tr[data-content-row]'))
                    .map((row) => Number(row.dataset.contentId));

                const saveReorder = async (orderedIds) => {
                    const response = await fetch(reorderUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ ordered_ids: orderedIds }),
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(payload.message || '文章排序保存失败，请稍后重试。');
                    }

                    return payload;
                };

                Sortable.create(tbody, {
                    animation: 180,
                    handle: '.content-drag-handle',
                    draggable: 'tr[data-content-row]',
                    ghostClass: 'content-row-ghost',
                    chosenClass: 'content-row-chosen',
                    dragClass: 'content-row-drag',
                    async onEnd(event) {
                        const row = event.item;
                        const beforeIds = event.oldIndex === event.newIndex ? null : true;

                        if (!beforeIds) {
                            return;
                        }

                        const orderedIds = getVisibleOrderedIds();
                        row.classList.add('is-saving');

                        try {
                            const payload = await saveReorder(orderedIds);
                            window.showMessage?.(payload.message || '文章排序已保存。');
                        } catch (error) {
                            window.showMessage?.(error.message || '文章排序保存失败，页面将刷新恢复。', 'error');
                            window.setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } finally {
                            row.classList.remove('is-saving');
                        }
                    },
                });
            }
        })();
    </script>
@endpush
