@extends('layouts.admin')

@section('title', '留言详情 - ' . ($settings['name'] ?? '留言板') . ' - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / ' . ($settings['name'] ?? '留言板') . ' / 留言详情')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/guestbook-admin-show.css') }}">
@endpush

@section('content')
    <header class="page-header">
        <div>
            <h1 class="page-header-title">留言详情</h1>
            <div class="page-header-desc">查看留言详情，并在下方完成回复。前台展示会根据当前留言板规则自动判断。</div>
        </div>
        <div class="page-header-actions">
            <a class="button secondary" href="{{ $guestbookPreviewUrl }}" target="_blank" rel="noopener">前台预览</a>
            <a class="button secondary" href="{{ route('admin.guestbook.index') }}">返回列表</a>
        </div>
    </header>

    <form method="post" action="{{ route('admin.guestbook.update', $message['id']) }}">
        @csrf
        <div class="guestbook-shell">
            <section class="guestbook-stack">
            <div class="guestbook-panel">
                <h2 class="guestbook-section-title">ID:{{ $message['display_no'] }} 留言状态</h2>
                <div class="guestbook-section-desc">当前留言处理状态、浏览状态和前台展示结果会在这里同步显示。</div>
                <div class="guestbook-badges">
                    <span class="guestbook-badge {{ $message['is_read'] ? 'is-info' : 'is-warning' }}">{{ $message['read_label'] }}</span>
                    <span class="guestbook-badge {{ $message['status'] === 'replied' ? 'is-success' : 'is-warning' }}">{{ $message['status_label'] }}</span>
                    <span class="guestbook-badge {{ $message['is_public'] ? 'is-success' : '' }}">{{ $message['visibility_label'] }}</span>
                </div>
            </div>

            <div class="guestbook-panel">
                <h2 class="guestbook-section-title">留言信息</h2>
                <div class="guestbook-meta-grid">
                    <div class="guestbook-meta-row"><div class="guestbook-meta-label">称呼</div><div class="guestbook-meta-value">{{ $message['name'] }}</div></div>
                    <div class="guestbook-meta-row"><div class="guestbook-meta-label">联系电话</div><div class="guestbook-meta-value">{{ $message['phone'] }}</div></div>
                    <div class="guestbook-meta-row"><div class="guestbook-meta-label">留言时间</div><div class="guestbook-meta-value">{{ $message['created_at_label'] }}</div></div>
                    <div class="guestbook-meta-row"><div class="guestbook-meta-label">最近回复</div><div class="guestbook-meta-value">{{ $message['replied_at_label'] !== '' ? $message['replied_at_label'] : '尚未回复' }}</div></div>
                </div>
            </div>

            <div class="guestbook-panel">
                <div class="guestbook-section-head">
                    <h2 class="guestbook-section-title">留言内容</h2>
                    @if ($canManageMessage)
                        <button class="button secondary guestbook-edit-button" type="button" data-toggle-editor="content" data-label-edit="编辑" data-label-cancel="取消编辑">编辑</button>
                    @endif
                </div>
                <div class="guestbook-display" data-editor-display="content">
                    <div class="guestbook-content">{{ old('content', $message['content']) }}</div>
                </div>
                @if ($canManageMessage)
                    <div class="guestbook-editor" data-editor-field="content" @if (! $errors->has('content')) hidden @endif>
                        <div class="guestbook-textarea-wrap">
                            <textarea class="field" name="content" rows="12" maxlength="1000" data-textarea-limit="1000" placeholder="请输入留言内容。">{{ old('content', $message['content']) }}</textarea>
                            <span class="guestbook-textarea-counter" data-textarea-counter>0 / 1000</span>
                        </div>
                    </div>
                @else
                    <input type="hidden" name="content" value="{{ old('content', $message['content']) }}">
                @endif
                @error('content')<div class="error">{{ $message }}</div>@enderror
                @if ($message['original_content'] !== '')
                    <details class="guestbook-collapsible">
                        <summary>原始留言信息（该留言内容已被修改）</summary>
                        <div class="guestbook-collapsible-body">{{ $message['original_content'] }}</div>
                    </details>
                @endif
            </div>

            <div class="guestbook-panel">
                <div class="guestbook-section-head">
                    <h2 class="guestbook-section-title">回复办理</h2>
                    <button class="button secondary guestbook-edit-button" type="button" data-toggle-editor="reply_content" data-label-edit="编辑" data-label-cancel="取消编辑">编辑</button>
                </div>
                <div class="guestbook-display" data-editor-display="reply_content" @if ($message['reply_content'] === '' && ! $errors->has('reply_content')) hidden @endif>
                    <div class="guestbook-content" data-editor-display-content="reply_content">{{ old('reply_content', $message['reply_content']) !== '' ? old('reply_content', $message['reply_content']) : '尚未回复' }}</div>
                </div>
                <div class="guestbook-editor" data-editor-field="reply_content" @if ($message['reply_content'] !== '' && ! $errors->has('reply_content')) hidden @endif>
                    <div class="guestbook-textarea-wrap">
                        <textarea class="field" name="reply_content" rows="10" maxlength="1000" data-textarea-limit="1000" placeholder="请输入回复内容，留空则保持待办理状态。">{{ old('reply_content', $message['reply_content']) }}</textarea>
                        <span class="guestbook-textarea-counter" data-textarea-counter>0 / 1000</span>
                    </div>
                </div>
                <div class="action-row guestbook-action-row">
                    <button class="button" type="submit">保存回复</button>
                </div>
            </div>

        </section>
        </div>
    </form>
    <script src="{{ asset('js/guestbook-admin-show.js') }}"></script>
@endsection
