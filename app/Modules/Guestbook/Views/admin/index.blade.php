@extends('layouts.admin')

@section('title', ($settings['name'] ?? '留言板') . ' - 功能模块 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / ' . ($settings['name'] ?? '留言板'))

@push('styles')
    @include('admin.site._custom_select_styles')
    <link rel="stylesheet" href="{{ asset('css/guestbook-admin-index.css') }}">
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
