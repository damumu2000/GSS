@extends('layouts.admin')

@section('title', '留言详情 - ' . ($settings['name'] ?? '留言板') . ' - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / ' . ($settings['name'] ?? '留言板') . ' / 留言详情')

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
        .page-header-title { margin: 0; color: #262626; font-size: 20px; line-height: 1.4; font-weight: 700; }
        .page-header-desc { margin-top: 8px; color: #8c8c8c; font-size: 14px; line-height: 1.7; }
        .page-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .guestbook-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 18px;
            align-items: start;
        }
        .guestbook-stack {
            display: grid;
            gap: 18px;
        }
        .guestbook-panel {
            padding: 22px 22px 24px;
            border: 1px solid #eef2f6;
            border-radius: 22px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }
        .guestbook-section-title { margin: 0; color: #1f2937; font-size: 17px; line-height: 1.5; font-weight: 700; }
        .guestbook-section-desc { margin-top: 6px; color: #8c8c8c; font-size: 13px; line-height: 1.7; }
        .guestbook-section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .guestbook-meta {
            display: grid;
            gap: 14px;
        }
        .guestbook-meta-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px 16px;
            margin-top: 16px;
        }
        .guestbook-meta-row {
            display: grid;
            gap: 4px;
            padding: 12px 14px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #edf2f7;
        }
        .guestbook-meta-label { color: #98a2b3; font-size: 12px; line-height: 1.5; font-weight: 700; }
        .guestbook-meta-value { color: #1f2937; font-size: 14px; line-height: 1.8; word-break: break-word; }
        .guestbook-content {
            margin-top: 16px;
            padding: 18px 18px 16px;
            border-radius: 16px;
            border: 1px solid #edf2f7;
            background: #fbfdff;
            color: #344054;
            font-size: 14px;
            line-height: 1.9;
            white-space: pre-wrap;
        }
        .guestbook-editor {
            margin-top: 16px;
        }
        .guestbook-editor[hidden] {
            display: none;
        }
        .guestbook-display[hidden] {
            display: none;
        }
        .guestbook-textarea-wrap {
            position: relative;
            margin-top: 14px;
        }
        .guestbook-textarea-counter {
            position: absolute;
            right: 14px;
            bottom: 12px;
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 0 8px;
            border-radius: 999px;
            background: rgba(255,255,255,0.92);
            color: #98a2b3;
            font-size: 12px;
            line-height: 1;
            font-weight: 700;
            pointer-events: none;
            transition: color 0.18s ease, background 0.18s ease;
        }
        .guestbook-textarea-counter.is-near-limit {
            color: #b45309;
            background: rgba(254, 243, 199, 0.92);
        }
        .guestbook-textarea-counter.is-over-limit {
            color: #dc2626;
            background: rgba(254, 226, 226, 0.92);
        }
        .guestbook-badges { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; }
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
        .guestbook-form-note {
            margin-top: 14px;
            padding: 12px 14px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #edf2f7;
            color: #667085;
            font-size: 13px;
            line-height: 1.7;
        }
        .guestbook-collapsible {
            margin-top: 14px;
            border-radius: 14px;
            border: 1px solid #edf2f7;
            background: #f8fafc;
            overflow: hidden;
        }
        .guestbook-collapsible summary {
            list-style: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            color: #475467;
            font-size: 13px;
            line-height: 1.7;
            font-weight: 700;
        }
        .guestbook-collapsible summary::-webkit-details-marker { display: none; }
        .guestbook-collapsible summary::after {
            content: '';
            width: 10px;
            height: 10px;
            border-right: 2px solid #98a2b3;
            border-bottom: 2px solid #98a2b3;
            transform: rotate(45deg);
            transition: transform 0.18s ease;
            flex-shrink: 0;
            margin-top: -4px;
        }
        .guestbook-collapsible[open] summary::after {
            transform: rotate(225deg);
            margin-top: 2px;
        }
        .guestbook-collapsible-body {
            padding: 0 14px 14px;
            color: #667085;
            font-size: 13px;
            line-height: 1.8;
            white-space: pre-wrap;
        }
        .guestbook-edit-button {
            min-height: 34px;
            padding: 0 14px;
            border-radius: 10px;
        }
        @media (max-width: 980px) {
            .guestbook-shell { grid-template-columns: 1fr; }
            .guestbook-meta-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 640px) {
            .guestbook-meta-grid { grid-template-columns: 1fr; }
        }
    </style>
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
                <div class="guestbook-meta-grid" style="margin-top: 16px;">
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
                    <div class="guestbook-textarea-wrap" style="margin-top: 14px;">
                        <textarea class="field" name="reply_content" rows="10" maxlength="1000" data-textarea-limit="1000" placeholder="请输入回复内容，留空则保持待办理状态。">{{ old('reply_content', $message['reply_content']) }}</textarea>
                        <span class="guestbook-textarea-counter" data-textarea-counter>0 / 1000</span>
                    </div>
                </div>
                <div class="action-row" style="margin-top:18px;">
                    <button class="button" type="submit">保存回复</button>
                </div>
            </div>

        </section>
        </div>
    </form>
    <script>
        (() => {
            const textareas = Array.from(document.querySelectorAll('textarea[data-textarea-limit]'));
            if (textareas.length === 0) {
                return;
            }

            const syncCounter = (textarea) => {
                const counter = textarea.parentElement?.querySelector('[data-textarea-counter]');
                if (!counter) {
                    return;
                }

                const limit = Number.parseInt(textarea.getAttribute('data-textarea-limit') || '1000', 10);
                const length = Array.from(textarea.value || '').length;
                counter.textContent = `${length} / ${limit}`;
                counter.classList.toggle('is-near-limit', length >= Math.max(0, limit - 120) && length <= limit);
                counter.classList.toggle('is-over-limit', length > limit);
            };

            textareas.forEach((textarea) => {
                textarea.addEventListener('input', () => syncCounter(textarea));
                syncCounter(textarea);
            });

            document.querySelectorAll('[data-toggle-editor]').forEach((button) => {
                const editLabel = button.getAttribute('data-label-edit') || '编辑';
                const cancelLabel = button.getAttribute('data-label-cancel') || '取消编辑';
                const field = button.getAttribute('data-toggle-editor');
                const display = document.querySelector(`[data-editor-display="${field}"]`);
                const editor = document.querySelector(`[data-editor-field="${field}"]`);

                const syncButtonState = () => {
                    if (!editor) {
                        return;
                    }

                    const editing = !editor.hidden;
                    button.textContent = editing ? cancelLabel : editLabel;
                };

                syncButtonState();

                button.addEventListener('click', () => {
                    if (!editor) {
                        return;
                    }

                    const editing = !editor.hidden;

                    if (editing) {
                        editor.hidden = true;
                        if (display) {
                            display.hidden = false;
                        }
                        syncButtonState();
                        return;
                    }

                    if (display) {
                        display.hidden = true;
                    }

                    editor.hidden = false;
                    const textarea = editor.querySelector('textarea');
                    if (textarea) {
                        textarea.focus();
                        textarea.setSelectionRange(textarea.value.length, textarea.value.length);
                    }
                    syncButtonState();
                });
            });
        })();
    </script>
@endsection
