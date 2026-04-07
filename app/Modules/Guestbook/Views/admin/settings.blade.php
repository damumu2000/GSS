@extends('layouts.admin')

@section('title', '留言板设置 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 留言板设置')

@push('styles')
    @include('admin.site._custom_select_styles')
    <style>
        @include('admin.site.attachments._attachment_library_styles')

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

        .page-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .guestbook-settings-subactions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .guestbook-settings-shell {
            display: grid;
            gap: 18px;
            width: 100%;
        }

        .guestbook-settings-panel {
            padding: 22px 24px 24px;
            border: 1px solid #eef2f6;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }

        .guestbook-settings-title {
            margin: 0;
            color: #262626;
            font-size: 17px;
            line-height: 1.5;
            font-weight: 700;
        }

        .guestbook-settings-desc {
            margin-top: 6px;
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.7;
        }

        .guestbook-settings-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px 22px;
            margin-top: 18px;
        }

        .guestbook-settings-field {
            display: grid;
            gap: 8px;
            min-width: 0;
        }

        .guestbook-settings-field--wide {
            align-self: start;
        }

        .guestbook-settings-field--inline {
            grid-template-columns: 88px minmax(220px, 320px);
            gap: 8px;
            align-items: center;
            grid-column: 1 / -1;
            justify-content: start;
        }

        .guestbook-settings-field--inline .guestbook-settings-label {
            margin: 0;
        }

        .guestbook-settings-field--inline .guestbook-settings-error {
            grid-column: 2;
        }

        .guestbook-settings-label {
            color: #4b5563;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 700;
        }

        .guestbook-settings-note {
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.7;
        }

        .guestbook-theme-selector {
            grid-column: 1 / -1;
            display: grid;
            gap: 12px;
            margin-top: -4px;
        }

        .guestbook-theme-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .guestbook-theme-option {
            position: relative;
            display: block;
            cursor: pointer;
        }

        .guestbook-theme-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .guestbook-theme-card {
            display: block;
            height: 100%;
            padding: 13px 13px 12px;
            border: 1px solid var(--theme-border, #e8edf3);
            border-radius: 14px;
            background: linear-gradient(180deg, var(--theme-soft, #ffffff) 0%, #ffffff 88%);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.03);
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease, background 0.18s ease;
        }

        .guestbook-theme-option:hover .guestbook-theme-card {
            border-color: var(--theme-primary, #d7e3f4);
            box-shadow: 0 12px 22px rgba(15, 23, 42, 0.06);
            transform: translateY(-1px);
        }

        .guestbook-theme-option input:checked + .guestbook-theme-card {
            border-color: var(--theme-primary, rgba(0, 71, 171, 0.26));
            box-shadow: 0 0 0 3px var(--theme-ring, rgba(0, 71, 171, 0.10));
        }

        .guestbook-theme-option input:focus-visible + .guestbook-theme-card {
            box-shadow: 0 0 0 3px var(--theme-ring-strong, rgba(0, 71, 171, 0.14));
        }

        .guestbook-theme-header {
            display: grid;
            gap: 10px;
            align-items: flex-start;
        }

        .guestbook-theme-copy {
            display: block;
            min-width: 0;
            flex: 1;
        }

        .guestbook-theme-name {
            display: block;
            color: var(--theme-text, #1f2937);
            font-size: 13px;
            line-height: 1.5;
            font-weight: 700;
        }

        .guestbook-theme-desc {
            display: block;
            margin-top: 4px;
            color: var(--theme-muted, #8c8c8c);
            font-size: 11px;
            line-height: 1.65;
        }

        .guestbook-theme-palette {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .guestbook-theme-swatch {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            border: 1px solid rgba(15, 23, 42, 0.06);
        }

        .guestbook-settings-editor {
            grid-column: 1 / -1;
            display: grid;
            gap: 12px;
            margin-top: 12px;
        }

        .guestbook-settings-media-card {
            grid-column: 1 / -1;
            display: grid;
            gap: 12px;
            margin-top: 2px;
        }

        .guestbook-settings-media-preview {
            position: relative;
            display: block;
            min-height: 220px;
            padding: 22px 24px;
            border: 1px dashed #cfe0f6;
            border-radius: 18px;
            background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%);
            overflow: hidden;
            cursor: pointer;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
            text-align: left;
        }

        .guestbook-settings-media-clear {
            position: absolute;
            top: 14px;
            right: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border: 0;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.7);
            color: #ffffff;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            z-index: 4;
            transition: background 0.18s ease, transform 0.18s ease, opacity 0.18s ease;
        }

        .guestbook-settings-media-clear:hover {
            background: rgba(15, 23, 42, 0.82);
            transform: scale(1.04);
        }

        .guestbook-settings-media-clear[hidden] {
            display: none !important;
        }

        .guestbook-settings-media-preview:hover {
            border-color: rgba(0, 71, 171, 0.22);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
            transform: translateY(-1px);
        }

        .guestbook-settings-media-preview.is-filled {
            border-style: solid;
            border-color: #dce6f2;
            background: #f8fbff;
        }

        .guestbook-settings-media-preview.is-filled::before {
            content: '';
            position: absolute;
            inset: 16px 18px 16px auto;
            width: min(34%, 280px);
            border-radius: 16px;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.94) 0%, rgba(255, 255, 255, 0.34) 42%, rgba(255, 255, 255, 0.02) 100%);
            z-index: 2;
            pointer-events: none;
        }

        .guestbook-settings-media-image {
            position: absolute;
            top: 16px;
            right: 18px;
            bottom: 16px;
            width: min(34%, 280px);
            height: calc(100% - 32px);
            object-fit: cover;
            display: block;
            border-radius: 16px;
            z-index: 1;
            opacity: 0.94;
            -webkit-mask-image: linear-gradient(90deg, transparent 0%, rgba(0, 0, 0, 0.28) 22%, rgba(0, 0, 0, 0.86) 58%, #000 100%);
            mask-image: linear-gradient(90deg, transparent 0%, rgba(0, 0, 0, 0.28) 22%, rgba(0, 0, 0, 0.86) 58%, #000 100%);
        }

        .guestbook-settings-media-image[hidden] {
            display: none !important;
        }

        .guestbook-settings-media-copy {
            position: relative;
            z-index: 3;
            display: grid;
            gap: 14px;
            max-width: min(66%, 720px);
            min-height: 176px;
            align-content: center;
        }

        .guestbook-settings-media-title {
            color: #1f2937;
            font-size: 15px;
            line-height: 1.5;
            font-weight: 700;
        }

        .guestbook-settings-media-desc {
            color: #98a2b3;
            font-size: 13px;
            line-height: 1.85;
        }

        .guestbook-settings-media-empty {
            display: grid;
            gap: 6px;
            justify-items: start;
            padding: 0;
            text-align: left;
            color: #98a2b3;
            font-size: 13px;
            line-height: 1.7;
        }

        .guestbook-settings-media-empty strong {
            color: #475467;
            font-size: 14px;
        }

        @media (max-width: 900px) {
            .guestbook-settings-media-preview {
                min-height: 180px;
                padding: 18px 20px;
            }

            .guestbook-settings-media-preview.is-filled::before,
            .guestbook-settings-media-image {
                display: none;
            }

            .guestbook-settings-media-copy {
                max-width: 100%;
                min-height: 0;
            }
        }

        .guestbook-settings-editor textarea {
            min-height: 168px;
            resize: vertical;
        }

        .guestbook-notice-textarea {
            min-height: 220px;
        }

        .tox-tinymce {
            margin-top: 8px;
            border: 1px solid #e5e6eb !important;
            border-radius: 8px !important;
            overflow: hidden;
            box-shadow: none !important;
            transition: border-color 0.18s ease, box-shadow 0.18s ease !important;
        }

        .tox-tinymce:hover {
            border-color: #e5e6eb !important;
        }

        .tox-tinymce:focus-within {
            border-color: #e5e6eb !important;
            box-shadow: none !important;
        }

        .tox .tox-editor-header {
            position: sticky;
            top: 0;
            z-index: 4;
            background: #ffffff !important;
        }

        .guestbook-settings-toggle-group {
            display: grid;
            gap: 14px;
            grid-column: 1 / -1;
        }

        .guestbook-settings-toggle-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            align-items: stretch;
        }

        .guestbook-settings-toggle-row {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            align-items: center;
            gap: 16px;
            padding: 14px 16px;
            border: 1px solid #eef1f5;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
        }

        .guestbook-settings-toggle-control {
            position: relative;
            width: 52px;
            height: 30px;
            flex-shrink: 0;
            overflow: hidden;
        }

        .guestbook-settings-toggle-input {
            position: absolute;
            opacity: 0;
            inset: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            cursor: pointer;
            z-index: 2;
        }

        .guestbook-settings-toggle-track {
            position: relative;
            width: 52px;
            height: 30px;
            border-radius: 999px;
            border: 1px solid #d8dee8;
            background: #eef2f7;
            display: inline-flex;
            align-items: center;
            transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .guestbook-settings-toggle-track::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 22px;
            height: 22px;
            border-radius: 999px;
            background: #ffffff;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.14);
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .guestbook-settings-toggle-copy {
            display: grid;
            gap: 2px;
        }

        .guestbook-settings-toggle-text {
            color: #374151;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.5;
        }

        .guestbook-settings-toggle-desc {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.7;
        }

        .guestbook-settings-toggle-row:hover .guestbook-settings-toggle-track {
            border-color: rgba(0, 71, 171, 0.22);
        }

        .guestbook-settings-toggle-control:has(.guestbook-settings-toggle-input:checked) .guestbook-settings-toggle-track {
            background: rgba(0, 71, 171, 0.12);
            border-color: rgba(0, 71, 171, 0.28);
            box-shadow: 0 0 0 3px rgba(0, 71, 171, 0.08);
        }

        .guestbook-settings-toggle-control:has(.guestbook-settings-toggle-input:checked) .guestbook-settings-toggle-track::after {
            transform: translateX(22px);
            background: var(--primary, #0047AB);
        }

        .guestbook-settings-toggle-control:has(.guestbook-settings-toggle-input:focus-visible) .guestbook-settings-toggle-track {
            box-shadow: 0 0 0 3px rgba(0, 71, 171, 0.10);
            border-color: rgba(0, 71, 171, 0.3);
        }

        .guestbook-settings-error {
            color: #d92d20;
            font-size: 12px;
            line-height: 1.7;
        }

        @media (max-width: 1080px) {
            .guestbook-settings-grid {
                grid-template-columns: 1fr;
            }

            .guestbook-settings-toggle-grid {
                grid-template-columns: 1fr;
            }

            .guestbook-theme-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .guestbook-theme-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script src="/vendor/tinymce/tinymce.min.js"></script>
    <script>
        let cmsAttachments = [];
        const attachmentLibraryWorkspaceAccess = @json($attachmentLibraryWorkspaceAccess);
        const attachmentDeleteUrlTemplate = @json(route('admin.attachments.destroy', ['attachment' => '__ATTACHMENT__']));
        const attachmentUsageUrlTemplate = @json(route('admin.attachments.usages', ['attachment' => '__ATTACHMENT__']));

        @include('admin.site.attachments._attachment_library_script')

        if (window.tinymce) {
            window.tinymce.init({
                selector: 'textarea.guestbook-notice-rich-editor',
                min_height: 200,
                height: 260,
                language: 'zh_CN',
                language_url: '/vendor/tinymce/langs/zh_CN.js',
                menubar: false,
                branding: false,
                promotion: false,
                license_key: 'gpl',
                convert_urls: false,
                relative_urls: false,
                plugins: 'code textcolor',
                toolbar: 'undo redo | fontsize | bold italic underline | forecolor backcolor | removeformat code',
                font_size_formats: '12px 14px 16px 18px 20px 24px 28px 32px',
                content_style: 'body { font-family: PingFang SC, Microsoft YaHei, sans-serif; font-size: 14px; line-height: 1.8; }',
                setup(editor) {
                    editor.on('change input undo redo', () => editor.save());
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const noticeImageInput = document.getElementById('notice_image');
            const noticeImagePreview = document.querySelector('[data-notice-image-preview]');
            const noticeImagePreviewImage = document.querySelector('[data-notice-image-preview-image]');
            const noticeImagePlaceholder = document.querySelector('[data-notice-image-placeholder]');
            const noticeImageStatus = document.querySelector('[data-notice-image-status]');
            const noticeImagePrimaryText = document.querySelector('[data-notice-image-primary]');
            const noticeImageSecondaryText = document.querySelector('[data-notice-image-secondary]');
            const noticeImageClearInlineButton = document.querySelector('[data-notice-image-clear-inline]');
            const noticeImageOpenButtons = document.querySelectorAll('[data-notice-image-open]');

            const syncNoticeImageCopy = (hasImage) => {
                if (noticeImageStatus) {
                    noticeImageStatus.textContent = hasImage ? '已设置背景图' : '未设置背景图';
                }

                if (noticeImagePrimaryText) {
                    noticeImagePrimaryText.textContent = hasImage
                        ? '当前已选择发布须知背景图，前台会在发布须知右侧以渐隐背景方式展示。'
                        : '从站点资源库选择一张图片，前台会在发布须知右侧以渐隐背景方式展示。';
                }

                if (noticeImageSecondaryText) {
                    noticeImageSecondaryText.textContent = hasImage
                        ? '点击预览区域可重新选择图片，右上角的 × 可清除当前背景图。'
                        : '选中图片后，这里会模拟前台发布须知的右侧渐隐背景效果。';
                }
            };

            const syncNoticeImagePreview = () => {
                if (!noticeImageInput || !noticeImagePreview || !noticeImagePreviewImage || !noticeImagePlaceholder) {
                    return;
                }

                const value = noticeImageInput.value.trim();

                if (!value) {
                    noticeImagePreview.classList.remove('is-filled');
                    noticeImagePreviewImage.hidden = true;
                    noticeImagePreviewImage.onerror = null;
                    noticeImagePreviewImage.removeAttribute('src');
                    noticeImagePlaceholder.hidden = false;
                    syncNoticeImageCopy(false);
                    noticeImageClearInlineButton?.setAttribute('hidden', 'hidden');
                    return;
                }

                noticeImagePreview.classList.add('is-filled');
                noticeImagePreviewImage.onerror = () => {
                    noticeImagePreview.classList.remove('is-filled');
                    noticeImagePreviewImage.hidden = true;
                    noticeImagePreviewImage.removeAttribute('src');
                    noticeImagePlaceholder.hidden = false;
                    syncNoticeImageCopy(false);
                    noticeImageClearInlineButton?.setAttribute('hidden', 'hidden');
                };
                noticeImagePreviewImage.src = value;
                noticeImagePreviewImage.hidden = false;
                noticeImagePlaceholder.hidden = true;
                syncNoticeImageCopy(true);
                noticeImageClearInlineButton?.removeAttribute('hidden');
            };

            const openNoticeImageLibrary = () => {
                window.openSiteAttachmentLibrary?.({
                    mode: 'picker',
                    context: 'guestbook',
                    imageOnly: true,
                    onSelect(attachment) {
                        if (!noticeImageInput) {
                            return;
                        }

                        noticeImageInput.value = attachment.url || '';
                        syncNoticeImagePreview();
                    },
                });
            };

            noticeImageOpenButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    openNoticeImageLibrary();
                });
            });

            noticeImagePreview?.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openNoticeImageLibrary();
                }
            });

            noticeImageClearInlineButton?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();

                if (!noticeImageInput) {
                    return;
                }

                noticeImageInput.value = '';
                syncNoticeImagePreview();
            });

            noticeImageInput?.addEventListener('input', syncNoticeImagePreview);
            syncNoticeImagePreview();
        });
    </script>
@endpush

@section('content')
    <form method="post" action="{{ route('admin.guestbook.settings.update') }}">
        @csrf

        <header class="page-header">
            <div>
                <h1 class="page-header-title">留言板设置</h1>
                <div class="page-header-desc">统一维护留言板名称、前台展示规则和访客提交校验方式。</div>
            </div>
            <div class="page-header-actions">
                <a class="button secondary" href="{{ $guestbookPreviewUrl }}" target="_blank" rel="noopener">前台预览</a>
                <a class="button secondary" href="{{ route('admin.guestbook.index') }}">返回留言列表</a>
                <button class="button" type="submit">保存设置</button>
            </div>
        </header>

        <div class="guestbook-settings-shell">
            <section class="guestbook-settings-panel">
                <h2 class="guestbook-settings-title">基础设置</h2>

                <div class="guestbook-settings-grid">
                    <label class="guestbook-settings-field guestbook-settings-field--inline guestbook-settings-field--wide">
                        <span class="guestbook-settings-label">留言板名称</span>
                        <input class="field" type="text" name="name" value="{{ old('name', $settings['name']) }}" placeholder="如 校长留言板">
                        @error('name')<div class="guestbook-settings-error">{{ $message }}</div>@enderror
                    </label>

                    <div class="guestbook-settings-toggle-group">
                        <div class="guestbook-settings-toggle-grid">
                            <label class="guestbook-settings-toggle-row">
                                <span class="guestbook-settings-toggle-control">
                                    <input class="guestbook-settings-toggle-input" type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings['enabled']) === '1')>
                                    <span class="guestbook-settings-toggle-track"></span>
                                </span>
                                <span class="guestbook-settings-toggle-copy">
                                    <span class="guestbook-settings-toggle-text">启用留言板</span>
                                    <span class="guestbook-settings-toggle-desc">关闭后前台留言列表和提交页都会停止访问。</span>
                                </span>
                            </label>

                            <label class="guestbook-settings-toggle-row">
                                <span class="guestbook-settings-toggle-control">
                                    <input class="guestbook-settings-toggle-input" type="checkbox" name="captcha_enabled" value="1" @checked(old('captcha_enabled', $settings['captcha_enabled']) === '1')>
                                    <span class="guestbook-settings-toggle-track"></span>
                                </span>
                                <span class="guestbook-settings-toggle-copy">
                                    <span class="guestbook-settings-toggle-text">前台启用验证码</span>
                                    <span class="guestbook-settings-toggle-desc">开启后访客提交留言时需要输入图形验证码。</span>
                                </span>
                            </label>

                            <label class="guestbook-settings-toggle-row">
                                <span class="guestbook-settings-toggle-control">
                                    <input class="guestbook-settings-toggle-input" type="checkbox" name="show_name" value="1" @checked(old('show_name', $settings['show_name']) === '1')>
                                    <span class="guestbook-settings-toggle-track"></span>
                                </span>
                                <span class="guestbook-settings-toggle-copy">
                                    <span class="guestbook-settings-toggle-text">前台是否显示全名</span>
                                    <span class="guestbook-settings-toggle-desc">关闭后会自动对留言姓名做脱敏处理，例如“张三”显示为“张***”。</span>
                                </span>
                            </label>

                            <label class="guestbook-settings-toggle-row">
                                <span class="guestbook-settings-toggle-control">
                                    <input class="guestbook-settings-toggle-input" type="checkbox" name="show_after_reply" value="1" @checked(old('show_after_reply', $settings['show_after_reply']) === '1')>
                                    <span class="guestbook-settings-toggle-track"></span>
                                </span>
                                <span class="guestbook-settings-toggle-copy">
                                    <span class="guestbook-settings-toggle-text">回复后才在前台展示</span>
                                    <span class="guestbook-settings-toggle-desc">开启后，仅已回复且公开的留言会出现在前台列表与详情页。</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

            </section>

            <section class="guestbook-settings-panel">
                <div class="guestbook-settings-grid">
                    <div class="guestbook-theme-selector">
                        <span class="guestbook-settings-label">模板选择</span>
                        <div class="guestbook-theme-grid">
                            @foreach ($themeOptions as $themeCode => $theme)
                                <label class="guestbook-theme-option">
                                    <input type="radio" name="theme" value="{{ $themeCode }}" @checked($settings['theme'] === $themeCode)>
                                    <div
                                        class="guestbook-theme-card"
                                        style="
                                            --theme-primary: {{ $theme['profile']['primary'] ?? '#0050b3' }};
                                            --theme-soft: {{ $theme['profile']['hero_border'] ?? 'rgba(0, 80, 179, 0.08)' }};
                                            --theme-border: {{ $theme['profile']['primary_border'] ?? 'rgba(0, 80, 179, 0.12)' }};
                                            --theme-ring: {{ $theme['profile']['primary_soft'] ?? 'rgba(0, 80, 179, 0.10)' }};
                                            --theme-ring-strong: {{ $theme['profile']['primary_soft'] ?? 'rgba(0, 80, 179, 0.14)' }};
                                            --theme-text: {{ $theme['profile']['text'] ?? '#1f2937' }};
                                            --theme-muted: {{ $theme['profile']['muted'] ?? '#8c8c8c' }};
                                        "
                                    >
                                        <div class="guestbook-theme-header">
                                            <div class="guestbook-theme-copy">
                                                <div class="guestbook-theme-name">{{ $theme['label'] }}</div>
                                                <div class="guestbook-theme-desc">{{ $theme['description'] }}</div>
                                            </div>
                                            <div class="guestbook-theme-palette" aria-hidden="true">
                                                @foreach ($theme['swatches'] as $swatch)
                                                    <span class="guestbook-theme-swatch" style="background: {{ $swatch }};"></span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('theme')<div class="guestbook-settings-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="guestbook-settings-editor">
                        <span class="guestbook-settings-label">发布须知</span>
                        <textarea
                            class="field textarea guestbook-notice-textarea guestbook-notice-rich-editor"
                            name="notice"
                            rows="7"
                            maxlength="1000"
                            placeholder="用于前台展示留言板发布须知或说明内容"
                        >{{ old('notice', $settings['notice']) }}</textarea>
                        <span class="guestbook-settings-note">前台会按设置内容展示这段须知说明。</span>
                        @error('notice')<div class="guestbook-settings-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="guestbook-settings-media-card">
                        <span class="guestbook-settings-label">发布须知背景图</span>
                        <input type="hidden" name="notice_image" id="notice_image" value="{{ old('notice_image', $settings['notice_image']) }}">
                        <div class="guestbook-settings-media-preview" data-notice-image-open data-notice-image-preview role="button" tabindex="0" aria-label="选择发布须知背景图">
                            <button class="guestbook-settings-media-clear" type="button" data-notice-image-clear-inline hidden aria-label="清除背景图">×</button>
                            <img class="guestbook-settings-media-image" data-notice-image-preview-image alt="发布须知背景图预览" hidden>
                            <span class="guestbook-settings-media-copy">
                                <span class="guestbook-settings-media-title">前台效果预览</span>
                                <span class="guestbook-settings-media-empty" data-notice-image-placeholder>
                                    <strong data-notice-image-status>未设置背景图</strong>
                                    <span data-notice-image-primary>从站点资源库选择一张图片，前台会在发布须知右侧以渐隐背景方式展示。</span>
                                </span>
                                <span class="guestbook-settings-media-desc" data-notice-image-secondary>选中图片后，这里会模拟前台发布须知的右侧渐隐背景效果。</span>
                            </span>
                        </div>
                        @error('notice_image')<div class="guestbook-settings-error">{{ $message }}</div>@enderror
                    </div>
                </div>

            </section>
        </div>
    </form>

    @include('admin.site.attachments._attachment_library_modal')
@endsection
