@extends('layouts.admin')

@section('title', '留言板设置 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 留言板设置')

@push('styles')
    @include('admin.site._custom_select_styles')
    @include('admin.site.attachments._attachment_library_styles')
    <link rel="stylesheet" href="{{ asset('css/guestbook-admin-settings.css') }}">
@endpush

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script src="/vendor/tinymce/tinymce.min.js"></script>
    @include('admin.site.attachments._attachment_library_scripts')
    <script src="{{ asset('js/guestbook-admin-settings.js') }}"></script>
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
                                    <div class="guestbook-theme-card guestbook-theme-card--{{ $themeCode }}">
                                        <div class="guestbook-theme-header">
                                            <div class="guestbook-theme-copy">
                                                <div class="guestbook-theme-name">{{ $theme['label'] }}</div>
                                                <div class="guestbook-theme-desc">{{ $theme['description'] }}</div>
                                            </div>
                                            <div class="guestbook-theme-palette" aria-hidden="true">
                                                @foreach ($theme['swatches'] as $swatchIndex => $swatch)
                                                    <span class="guestbook-theme-swatch guestbook-theme-swatch--{{ $themeCode }}-{{ $swatchIndex + 1 }}"></span>
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
