@extends('layouts.admin')

@section('title', ($settings['name'] ?? '留言板') . ' - 功能模块 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / ' . ($settings['name'] ?? '留言板'))

@push('styles')
    @include('admin.site._custom_select_styles')
    <style>
        .guestbook-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            padding: 24px 32px;
            margin: -28px -28px 24px;
            background: #fff;
            border-bottom: 1px solid #f0f0f0;
        }
        .guestbook-header-title { margin: 0; color: #262626; font-size: 20px; line-height: 1.4; font-weight: 700; }
        .guestbook-header-desc { margin-top: 8px; color: #8c8c8c; font-size: 14px; line-height: 1.75; }
        .guestbook-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
        .guestbook-stat-card {
            padding: 16px 18px;
            border: 1px solid #eef2f6;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }
        .guestbook-stat-label { color: #98a2b3; font-size: 12px; line-height: 1.6; font-weight: 700; }
        .guestbook-stat-value { margin-top: 6px; color: #1f2937; font-size: 24px; line-height: 1.2; font-weight: 700; }
        .guestbook-filter-card,
        .guestbook-list-card {
            padding: 20px 22px;
            border: 1px solid #eef2f6;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }
        .guestbook-list-card {
            margin-top: 20px;
        }
        .guestbook-filter-form {
            display: grid;
            grid-template-columns: minmax(300px, 1.7fr) repeat(2, minmax(170px, 0.9fr)) auto;
            gap: 16px 14px;
            align-items: end;
        }
        .guestbook-filter-item {
            display: grid;
            gap: 8px;
            min-width: 0;
        }
        .guestbook-filter-item label,
        .guestbook-filter-item .field-label {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 600;
            white-space: nowrap;
            margin: 0;
        }
        .guestbook-filter-item .site-select {
            width: 100%;
        }
        .guestbook-filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: flex-end;
            min-width: max-content;
        }
        .guestbook-filter-actions .button,
        .guestbook-filter-actions .button.secondary {
            min-height: 40px;
            padding: 0 16px;
            border-radius: 12px;
        }
        .guestbook-list { display: grid; gap: 14px; }
        .guestbook-list-card + .guestbook-list-card { margin-top: 18px; }
        .guestbook-item {
            display: grid;
            gap: 14px;
            padding: 18px;
            border: 1px solid #eef2f6;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        }
        .guestbook-item-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
        }
        .guestbook-item-title {
            color: #111827;
            font-size: 16px;
            line-height: 1.65;
            font-weight: 700;
        }
        .guestbook-item-title-sub {
            color: #98a2b3;
            font-size: 14px;
            line-height: 1.7;
            font-weight: 500;
        }
        .guestbook-item-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.6;
            align-items: center;
        }
        .guestbook-item-phone {
            color: #98a2b3;
        }
        .guestbook-visibility-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 24px;
            padding: 0 10px;
            border-radius: 999px;
            font-size: 12px;
            line-height: 1;
            font-weight: 700;
            white-space: nowrap;
        }
        .guestbook-visibility-badge.is-public {
            background: rgba(16, 185, 129, 0.10);
            color: #059669;
        }
        .guestbook-visibility-badge.is-hidden {
            background: rgba(148, 163, 184, 0.14);
            color: #64748b;
        }
        .guestbook-item-summary {
            color: #4b5563;
            font-size: 14px;
            line-height: 1.85;
        }
        .guestbook-item-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .guestbook-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .guestbook-badge {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 0 12px;
            border-radius: 999px;
            background: #f5f7fa;
            color: #667085;
            font-size: 12px;
            font-weight: 700;
        }
        .guestbook-badge.is-success { background: rgba(16, 185, 129, 0.10); color: #059669; }
        .guestbook-badge.is-warning { background: rgba(245, 158, 11, 0.12); color: #b45309; }
        .guestbook-badge.is-info { background: rgba(0, 80, 179, 0.08); color: #0050b3; }
        .guestbook-action-hint {
            display: inline-flex;
            align-items: center;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #edf2f7;
            color: #667085;
            font-size: 13px;
            line-height: 1.6;
            font-weight: 600;
        }
        .guestbook-empty {
            padding: 44px 24px;
            border-radius: 16px;
            border: 1px dashed #dbe4ee;
            background: #fff;
            color: #8c8c8c;
            text-align: center;
        }
        .guestbook-pagination {
            margin-top: 18px;
        }
        .guestbook-pagination .pagination-shell {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: nowrap;
            min-width: max-content;
        }
        .guestbook-pagination .pagination-pages {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .guestbook-pagination .pagination-button,
        .guestbook-pagination .pagination-page,
        .guestbook-pagination .pagination-ellipsis {
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
        .guestbook-pagination .pagination-page {
            width: 32px;
            padding: 0;
        }
        .guestbook-pagination .pagination-button {
            border: 0;
            background: transparent;
            min-width: auto;
            padding: 0 4px;
            color: #4b5563;
        }
        .guestbook-pagination .pagination-button:hover,
        .guestbook-pagination .pagination-page:hover {
            transform: translateY(-1px);
            background: #f9fafb;
            border-color: #d1d5db;
        }
        .guestbook-pagination .pagination-button:hover {
            background: transparent;
            border-color: transparent;
            color: #262626;
        }
        .guestbook-pagination .pagination-page.is-active,
        .guestbook-pagination .pagination-page.is-active:visited {
            border-color: #374151 !important;
            background: #374151 !important;
            color: #ffffff !important;
            font-weight: 600;
            transform: none;
        }
        .guestbook-pagination .pagination-button.is-disabled,
        .guestbook-pagination .pagination-page.is-disabled,
        .guestbook-pagination .pagination-ellipsis {
            color: #c0c4cc;
            cursor: not-allowed;
        }
        .guestbook-pagination .pagination-button.is-disabled:hover,
        .guestbook-pagination .pagination-page.is-disabled:hover {
            transform: none;
            background: #ffffff;
            border-color: #e5e7eb;
        }
        .guestbook-pagination .pagination-button.is-disabled,
        .guestbook-pagination .pagination-button.is-disabled:hover {
            background: transparent;
            border-color: transparent;
        }
        .guestbook-pagination .pagination-icon {
            width: 14px;
            height: 14px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
        }
        @media (max-width: 1200px) {
            .guestbook-filter-form { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .guestbook-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .guestbook-filter-actions { justify-content: flex-start; min-width: 0; }
        }
        @media (max-width: 768px) {
            .guestbook-header { margin: -24px -18px 20px; padding: 18px; flex-direction: column; align-items: flex-start; }
            .guestbook-stats,
            .guestbook-filter-form { grid-template-columns: 1fr; }
            .guestbook-filter-actions { flex-wrap: wrap; }
        }
    </style>
@endpush

@push('scripts')
    @include('admin.site._custom_select_scripts')
@endpush

@section('content')
    <section class="guestbook-header">
        <div>
            <h2 class="guestbook-header-title">{{ $settings['name'] ?? '留言板' }}</h2>
            <div class="guestbook-header-desc">统一查看本站留言、筛选处理状态，并对留言进行回复和公开展示管理。</div>
        </div>
        <div class="page-header-actions">
            <a class="button secondary" href="{{ $guestbookPreviewUrl }}" target="_blank" rel="noopener">前台预览</a>
            @if ($canManageSettings)
                <a class="button secondary" href="{{ route('admin.guestbook.settings') }}">留言板设置</a>
            @endif
        </div>
    </section>

    <section class="guestbook-stats">
        <div class="guestbook-stat-card"><div class="guestbook-stat-label">留言总数</div><div class="guestbook-stat-value">{{ $stats['total'] }}</div></div>
        <div class="guestbook-stat-card"><div class="guestbook-stat-label">待办理</div><div class="guestbook-stat-value">{{ $stats['pending'] }}</div></div>
        <div class="guestbook-stat-card"><div class="guestbook-stat-label">已办理</div><div class="guestbook-stat-value">{{ $stats['replied'] }}</div></div>
        <div class="guestbook-stat-card"><div class="guestbook-stat-label">未浏览</div><div class="guestbook-stat-value">{{ $stats['unread'] }}</div></div>
    </section>

    <section class="guestbook-filter-card">
        <form method="get" class="guestbook-filter-form">
            <div class="guestbook-filter-item">
                <div class="field-label">搜索</div>
                <input class="field" type="text" name="keyword" value="{{ $keyword }}" placeholder="搜索称呼、电话、留言内容">
            </div>
            <div class="guestbook-filter-item">
                <div class="field-label">浏览状态</div>
                <div class="site-select" data-site-select>
                    <select class="field site-select-native" name="read_status">
                        <option value="">全部</option>
                        <option value="unread" @selected($readStatus === 'unread')>未浏览</option>
                        <option value="read" @selected($readStatus === 'read')>已浏览</option>
                    </select>
                    <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">
                        {{ $readStatus === 'unread' ? '未浏览' : ($readStatus === 'read' ? '已浏览' : '全部') }}
                    </button>
                    <div class="site-select-panel" data-select-panel role="listbox"></div>
                </div>
            </div>
            <div class="guestbook-filter-item">
                <div class="field-label">办理状态</div>
                <div class="site-select" data-site-select>
                    <select class="field site-select-native" name="reply_status">
                        <option value="">全部</option>
                        <option value="pending" @selected($replyStatus === 'pending')>待办理</option>
                        <option value="replied" @selected($replyStatus === 'replied')>已办理</option>
                    </select>
                    <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">
                        {{ $replyStatus === 'pending' ? '待办理' : ($replyStatus === 'replied' ? '已办理' : '全部') }}
                    </button>
                    <div class="site-select-panel" data-select-panel role="listbox"></div>
                </div>
            </div>
            <div class="guestbook-filter-actions">
                <button class="button" type="submit">筛选</button>
                <a class="button secondary" href="{{ route('admin.guestbook.index') }}">重置</a>
            </div>
        </form>
    </section>

    <section class="guestbook-list-card">
        @if ($messages->isEmpty())
            <div class="guestbook-empty">当前没有符合条件的留言记录。</div>
        @else
            <div class="guestbook-list">
                @foreach ($messages as $message)
                    <article class="guestbook-item">
                        <div class="guestbook-item-head">
                            <div>
                                <div class="guestbook-item-title">
                                    ID:{{ $message['display_no'] }} {{ $message['name'] }}
                                    <span class="guestbook-item-title-sub">· {{ $message['phone'] }} · {{ $message['created_at_label'] }}</span>
                                </div>
                                <div class="guestbook-item-meta">
                                </div>
                            </div>
                            <div class="guestbook-badges">
                                <span class="guestbook-badge {{ $message['is_read'] ? 'is-info' : 'is-warning' }}">{{ $message['read_label'] }}</span>
                                <span class="guestbook-badge {{ $message['status'] === 'replied' ? 'is-success' : 'is-warning' }}">{{ $message['status_label'] }}</span>
                                <span class="guestbook-visibility-badge {{ $message['is_public'] ? 'is-public' : 'is-hidden' }}">{{ $message['is_public'] ? '已公开' : '未公开' }}</span>
                            </div>
                        </div>
                        <div class="guestbook-item-summary">{{ $message['summary'] }}</div>
                        <div class="guestbook-item-actions">
                            <div class="guestbook-action-hint">{{ $message['reply_content'] !== '' ? '已回复，可进入详情继续调整留言内容和回复内容。' : '尚未回复，可进入详情页办理。' }}</div>
                            <a class="button secondary" href="{{ route('admin.guestbook.show', $message['id']) }}">查看 / 回复</a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="guestbook-pagination">
                {{ $messages->links() }}
            </div>
        @endif
    </section>
@endsection
