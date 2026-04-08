@extends('layouts.admin')

@section('title', '系统设置 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 系统设置')

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

        .page-header-actions .button {
            min-width: 140px;
        }

        .settings-shell {
            display: grid;
            gap: 18px;
            width: 100%;
            max-width: 1120px;
            margin: 0 auto;
        }

        .settings-nav {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            padding: 8px;
            border: 1px solid #eef2f6;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
            width: fit-content;
            max-width: 100%;
        }

        .settings-nav-button {
            min-height: 40px;
            padding: 0 18px;
            border: 1px solid transparent;
            border-radius: 12px;
            background: transparent;
            color: #6b7280;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.18s ease, color 0.18s ease, border-color 0.18s ease;
        }

        .settings-nav-button:hover {
            background: #f8fafc;
            color: #374151;
        }

        .settings-nav-button.is-active {
            background: rgba(0, 80, 179, 0.08);
            border-color: rgba(0, 80, 179, 0.12);
            color: var(--primary);
        }

        .settings-panel {
            display: none;
            padding: 22px 24px 24px;
            border: 1px solid #eef2f6;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
            width: 100%;
            max-width: 1120px;
            margin: 0 auto;
        }

        .settings-panel.is-active {
            display: block;
        }

        .settings-panel-title {
            margin: 0;
            color: #262626;
            font-size: 17px;
            line-height: 1.5;
            font-weight: 700;
        }

        .settings-panel-desc {
            margin-top: 6px;
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.7;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 430px));
            gap: 18px 22px;
            margin-top: 18px;
            justify-content: center;
            max-width: 100%;
        }

        .settings-field {
            display: grid;
            gap: 8px;
            min-width: 0;
        }

        .settings-field.is-full {
            grid-column: 1 / -1;
            max-width: 882px;
            width: 100%;
            justify-self: center;
        }

        .settings-label {
            color: #4b5563;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 700;
        }

        .settings-note {
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.7;
        }

        .settings-media-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 430px));
            gap: 18px 22px;
            margin-top: 18px;
            justify-content: center;
            max-width: 100%;
        }

        .settings-media-card {
            display: grid;
            gap: 14px;
            min-width: 0;
            padding: 18px;
            border: 1px solid #eef2f6;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            max-width: 430px;
            width: 100%;
        }

        .settings-media-title {
            color: #1f2937;
            font-size: 15px;
            line-height: 1.5;
            font-weight: 700;
        }

        .settings-media-preview {
            min-height: 108px;
            display: grid;
            place-items: center;
            padding: 18px;
            border: 1px dashed #dbe4ee;
            border-radius: 14px;
            background:
                radial-gradient(circle at top center, rgba(0, 80, 179, 0.05), transparent 48%),
                linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            overflow: hidden;
        }

        .settings-media-preview.is-favicon {
            min-height: 92px;
        }

        .settings-media-preview img {
            display: block;
            width: auto;
            max-width: 100%;
            max-height: 44px;
            object-fit: contain;
        }

        .settings-media-preview.is-favicon img {
            max-width: 40px;
            max-height: 40px;
        }

        .settings-media-empty {
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.7;
            text-align: center;
        }

        .settings-media-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .settings-media-actions .button {
            min-height: 36px;
            padding: 0 14px;
            font-size: 13px;
            border-radius: 10px;
        }

        .settings-file-input {
            display: none;
        }

        .settings-inline-note {
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.7;
            word-break: break-all;
        }

        .settings-access-card {
            display: grid;
            gap: 16px;
            padding: 18px;
            margin-top: 18px;
            border: 1px solid #eef2f6;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            max-width: 782px;
            width: 100%;
        }

        .settings-field > .field,
        .settings-field > .textarea {
            max-width: 100%;
        }

        .setting-toggle-field {
            display: grid;
            gap: 10px;
            grid-column: 1 / -1;
        }

        .settings-toggle-grid {
            grid-column: 1 / -1;
            display: grid;
            gap: 10px;
            max-width: 100%;
        }

        .settings-toggle-shell {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px 22px;
        }

        .settings-toggle-shell .setting-toggle-field {
            grid-column: auto;
        }

        .setting-toggle-row {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            align-items: center;
            gap: 16px;
            padding: 14px 16px;
            border: 1px solid #eef1f5;
            border-radius: 14px;
            background: #ffffff;
        }

        .setting-toggle-control {
            position: relative;
            width: 52px;
            height: 30px;
            flex-shrink: 0;
            overflow: hidden;
        }

        .setting-toggle-input {
            position: absolute;
            opacity: 0;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            cursor: pointer;
            z-index: 2;
        }

        .setting-toggle-track {
            position: relative;
            width: 52px;
            height: 30px;
            border-radius: 999px;
            border: 1px solid #d8dee8;
            background: #eef2f7;
            display: inline-flex;
            align-items: center;
            flex-shrink: 0;
            transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .setting-toggle-track::after {
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

        .setting-toggle-copy {
            display: grid;
            gap: 2px;
        }

        .setting-toggle-text {
            color: #374151;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.5;
        }

        .setting-toggle-desc {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.7;
        }

        .setting-toggle-control:has(.setting-toggle-input:checked) .setting-toggle-track {
            background: rgba(0, 71, 171, 0.16);
            border-color: rgba(0, 71, 171, 0.24);
        }

        .setting-toggle-control:has(.setting-toggle-input:checked) .setting-toggle-track::after {
            transform: translateX(22px);
            background: var(--primary, #0047AB);
        }

        .setting-toggle-control:has(.setting-toggle-input:checked) + .setting-toggle-copy .setting-toggle-state {
            color: var(--primary, #0047AB);
        }

        .setting-toggle-state {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 0 10px;
            border-radius: 999px;
            background: #f5f7fb;
            color: #667085;
            font-size: 12px;
            font-weight: 700;
            width: fit-content;
            margin-bottom: 6px;
        }

        @media (max-width: 900px) {
            .settings-grid,
            .settings-media-grid,
            .settings-toggle-grid {
                grid-template-columns: 1fr;
            }

            .settings-toggle-shell {
                grid-template-columns: 1fr;
            }

            .settings-nav,
            .settings-panel {
                width: 100%;
                max-width: 100%;
            }

            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">系统设置</h2>
            <div class="page-header-desc">集中维护后台基础信息、资源库上传限制和后台总开关。</div>
        </div>
        <div class="page-header-actions">
            <button class="button" type="submit" form="system-settings-form">保存设置</button>
        </div>
    </section>

    <form id="system-settings-form" method="POST" action="{{ route('admin.platform.settings.update') }}" class="settings-shell" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="current_tab" id="current_tab" value="{{ $activeTab ?? 'basic' }}">

        <div class="settings-nav" role="tablist" aria-label="系统设置分组">
            <button class="settings-nav-button is-active" type="button" data-settings-tab-trigger="basic" role="tab" aria-selected="true">基础设置</button>
            <button class="settings-nav-button" type="button" data-settings-tab-trigger="upload" role="tab" aria-selected="false">资源库上传设置</button>
            <button class="settings-nav-button" type="button" data-settings-tab-trigger="security" role="tab" aria-selected="false">安护盾</button>
            <button class="settings-nav-button" type="button" data-settings-tab-trigger="access" role="tab" aria-selected="false">后台开关</button>
        </div>

        <section class="settings-panel is-active" id="basic" data-settings-tab-panel="basic" role="tabpanel">
            <h3 class="settings-panel-title">基础设置</h3>
            <div class="settings-panel-desc">用于后台品牌展示和基础版本信息。</div>

            <div class="settings-grid">
                <label class="settings-field">
                    <span class="settings-label">系统名称</span>
                    <input class="field" type="text" name="system_name" value="{{ old('system_name', $settings['system_name']) }}" maxlength="100">
                </label>

                <label class="settings-field">
                    <span class="settings-label">系统版本号</span>
                    <input class="field" type="text" name="system_version" value="{{ old('system_version', $settings['system_version']) }}" maxlength="50">
                </label>
            </div>

            <div class="settings-media-grid">
                <div class="settings-media-card">
                    <div class="settings-media-title">后台 Logo</div>
                    <div class="settings-media-preview" data-system-preview="logo">
                        @if (! empty($settings['admin_logo']))
                            <img src="{{ $settings['admin_logo'] }}" alt="后台 Logo 预览" data-system-preview-image="logo">
                        @else
                            <div class="settings-media-empty" data-system-preview-empty="logo">当前还没有设置后台 Logo</div>
                        @endif
                    </div>
                    <input class="settings-file-input" type="file" name="admin_logo_file" id="admin_logo_file" accept="image/*">
                    <input type="hidden" name="admin_logo_clear" id="admin_logo_clear" value="0">
                    <div class="settings-media-actions">
                        <label class="button secondary" for="admin_logo_file">选择文件</label>
                        <button class="button secondary" type="button" data-system-clear-trigger="logo">清除</button>
                    </div>
                    <div class="settings-inline-note" data-system-note="logo">{{ $settings['admin_logo'] ?: '推荐显示高度控制在 36px 内。' }}</div>
                </div>

                <div class="settings-media-card">
                    <div class="settings-media-title">后台 ICO / Favicon</div>
                    <div class="settings-media-preview is-favicon" data-system-preview="favicon">
                        @if (! empty($settings['admin_favicon']))
                            <img src="{{ $settings['admin_favicon'] }}" alt="后台 ICO 预览" data-system-preview-image="favicon">
                        @else
                            <div class="settings-media-empty" data-system-preview-empty="favicon">当前还没有设置后台 ICO</div>
                        @endif
                    </div>
                    <input class="settings-file-input" type="file" name="admin_favicon_file" id="admin_favicon_file" accept=".ico,image/png">
                    <input type="hidden" name="admin_favicon_clear" id="admin_favicon_clear" value="0">
                    <div class="settings-media-actions">
                        <label class="button secondary" for="admin_favicon_file">选择文件</label>
                        <button class="button secondary" type="button" data-system-clear-trigger="favicon">清除</button>
                    </div>
                    <div class="settings-inline-note" data-system-note="favicon">{{ $settings['admin_favicon'] ?: '建议使用清晰的小图标素材。' }}</div>
                </div>
            </div>
        </section>

        <section class="settings-panel" id="upload" data-settings-tab-panel="upload" role="tabpanel">
            <h3 class="settings-panel-title">资源库上传设置</h3>
            <div class="settings-panel-desc">这里的限制会直接影响附件管理、资源库和编辑器图片上传。</div>

            <div class="settings-grid">
                <label class="settings-field is-full">
                    <span class="settings-label">允许上传的文件类型</span>
                    <input class="field" type="text" name="attachment_allowed_extensions" value="{{ old('attachment_allowed_extensions', $settings['attachment_allowed_extensions']) }}">
                    <span class="settings-note">多个类型用英文逗号分隔，例如：jpg,jpeg,png,gif,webp,pdf,docx,zip</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">资源库单文件最大体积（MB）</span>
                    <input class="field" type="number" name="attachment_max_size_mb" min="1" max="1024" value="{{ old('attachment_max_size_mb', $settings['attachment_max_size_mb']) }}">
                </label>

                <label class="settings-field">
                    <span class="settings-label">编辑器图片最大体积（MB）</span>
                    <input class="field" type="number" name="attachment_image_max_size_mb" min="1" max="512" value="{{ old('attachment_image_max_size_mb', $settings['attachment_image_max_size_mb']) }}">
                </label>

                <label class="settings-field">
                    <span class="settings-label">图片最大宽度（px）</span>
                    <input class="field" type="number" name="attachment_image_max_width" min="100" max="20000" value="{{ old('attachment_image_max_width', $settings['attachment_image_max_width']) }}">
                </label>

                <label class="settings-field">
                    <span class="settings-label">图片最大高度（px）</span>
                    <input class="field" type="number" name="attachment_image_max_height" min="100" max="20000" value="{{ old('attachment_image_max_height', $settings['attachment_image_max_height']) }}">
                </label>

                <div class="settings-toggle-grid">
                    <span class="settings-label">图片处理开关</span>
                    <div class="settings-toggle-shell">
                        <div class="settings-field setting-toggle-field">
                            <div class="setting-toggle-row">
                                <div class="setting-toggle-control">
                                    <input class="setting-toggle-input" id="attachment_image_auto_resize" type="checkbox" name="attachment_image_auto_resize" value="1" @checked(old('attachment_image_auto_resize', $settings['attachment_image_auto_resize']))>
                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                </div>
                                <div class="setting-toggle-copy" aria-hidden="true">
                                    <span class="setting-toggle-text">图片超限自动缩小</span>
                                    <span class="setting-toggle-state" id="attachment_image_auto_resize_label">{{ old('attachment_image_auto_resize', $settings['attachment_image_auto_resize']) ? '已开启' : '未开启' }}</span>
                                    <span class="setting-toggle-desc">开启后会在上传时按比例缩小超限图片，再保存压缩后的文件。</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-field setting-toggle-field">
                            <div class="setting-toggle-row">
                                <div class="setting-toggle-control">
                                    <input class="setting-toggle-input" id="attachment_image_auto_compress" type="checkbox" name="attachment_image_auto_compress" value="1" @checked(old('attachment_image_auto_compress', $settings['attachment_image_auto_compress']))>
                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                </div>
                                <div class="setting-toggle-copy" aria-hidden="true">
                                    <span class="setting-toggle-text">支持格式自动压缩</span>
                                    <span class="setting-toggle-state" id="attachment_image_auto_compress_label">{{ old('attachment_image_auto_compress', $settings['attachment_image_auto_compress']) ? '已开启' : '未开启' }}</span>
                                    <span class="setting-toggle-desc">开启后，支持压缩的图片会按“图片质量压缩”设置自动重编码。</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <label class="settings-field">
                    <span class="settings-label">图片质量压缩（1-100）</span>
                    <input class="field" type="number" name="attachment_image_quality" min="1" max="100" value="{{ old('attachment_image_quality', $settings['attachment_image_quality']) }}">
                </label>
            </div>
        </section>

        <section class="settings-panel" id="security" data-settings-tab-panel="security" role="tabpanel">
            <h3 class="settings-panel-title">安护盾</h3>
            <div class="settings-panel-desc">平台统一控制站点基础拦截规则。站点端只查看统计和最近拦截记录，不提供设置入口。</div>

            <div class="settings-grid">
                <div class="settings-toggle-grid">
                    <span class="settings-label">防护总开关</span>
                    <div class="settings-toggle-shell">
                        <div class="settings-field setting-toggle-field">
                            <div class="setting-toggle-row">
                                <div class="setting-toggle-control">
                                    <input class="setting-toggle-input" id="security_site_protection_enabled" type="checkbox" name="security_site_protection_enabled" value="1" @checked(old('security_site_protection_enabled', $settings['security_site_protection_enabled']))>
                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                </div>
                                <div class="setting-toggle-copy" aria-hidden="true">
                                    <span class="setting-toggle-text">启用站点安护盾</span>
                                    <span class="setting-toggle-state" id="security_site_protection_enabled_label">{{ old('security_site_protection_enabled', $settings['security_site_protection_enabled']) ? '已开启' : '未开启' }}</span>
                                    <span class="setting-toggle-desc">开启后，会在前台统一拦截恶意扫描、注入尝试和异常高频刷新。</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-toggle-grid">
                    <span class="settings-label">拦截规则</span>
                    <div class="settings-toggle-shell">
                        <div class="settings-field setting-toggle-field">
                            <div class="setting-toggle-row">
                                <div class="setting-toggle-control">
                                    <input class="setting-toggle-input" id="security_block_bad_path_enabled" type="checkbox" name="security_block_bad_path_enabled" value="1" @checked(old('security_block_bad_path_enabled', $settings['security_block_bad_path_enabled']))>
                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                </div>
                                <div class="setting-toggle-copy" aria-hidden="true">
                                    <span class="setting-toggle-text">恶意扫描路径拦截</span>
                                    <span class="setting-toggle-state" id="security_block_bad_path_enabled_label">{{ old('security_block_bad_path_enabled', $settings['security_block_bad_path_enabled']) ? '已开启' : '未开启' }}</span>
                                    <span class="setting-toggle-desc">用于拦截扫描 .env、wp-admin、phpunit 等高风险路径的请求。</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-field setting-toggle-field">
                            <div class="setting-toggle-row">
                                <div class="setting-toggle-control">
                                    <input class="setting-toggle-input" id="security_block_sql_injection_enabled" type="checkbox" name="security_block_sql_injection_enabled" value="1" @checked(old('security_block_sql_injection_enabled', $settings['security_block_sql_injection_enabled']))>
                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                </div>
                                <div class="setting-toggle-copy" aria-hidden="true">
                                    <span class="setting-toggle-text">SQL 注入拦截</span>
                                    <span class="setting-toggle-state" id="security_block_sql_injection_enabled_label">{{ old('security_block_sql_injection_enabled', $settings['security_block_sql_injection_enabled']) ? '已开启' : '未开启' }}</span>
                                    <span class="setting-toggle-desc">针对明显的注入关键字做基础拦截，减少低级攻击命中业务层。</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-field setting-toggle-field">
                            <div class="setting-toggle-row">
                                <div class="setting-toggle-control">
                                    <input class="setting-toggle-input" id="security_block_xss_enabled" type="checkbox" name="security_block_xss_enabled" value="1" @checked(old('security_block_xss_enabled', $settings['security_block_xss_enabled']))>
                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                </div>
                                <div class="setting-toggle-copy" aria-hidden="true">
                                    <span class="setting-toggle-text">XSS 拦截</span>
                                    <span class="setting-toggle-state" id="security_block_xss_enabled_label">{{ old('security_block_xss_enabled', $settings['security_block_xss_enabled']) ? '已开启' : '未开启' }}</span>
                                    <span class="setting-toggle-desc">对脚本注入和常见事件属性注入做基础识别和拦截。</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-field setting-toggle-field">
                            <div class="setting-toggle-row">
                                <div class="setting-toggle-control">
                                    <input class="setting-toggle-input" id="security_block_path_traversal_enabled" type="checkbox" name="security_block_path_traversal_enabled" value="1" @checked(old('security_block_path_traversal_enabled', $settings['security_block_path_traversal_enabled']))>
                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                </div>
                                <div class="setting-toggle-copy" aria-hidden="true">
                                    <span class="setting-toggle-text">路径穿越拦截</span>
                                    <span class="setting-toggle-state" id="security_block_path_traversal_enabled_label">{{ old('security_block_path_traversal_enabled', $settings['security_block_path_traversal_enabled']) ? '已开启' : '未开启' }}</span>
                                    <span class="setting-toggle-desc">用于拦截 ../ 这类越级访问尝试，减少探测系统目录的请求。</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-field setting-toggle-field">
                            <div class="setting-toggle-row">
                                <div class="setting-toggle-control">
                                    <input class="setting-toggle-input" id="security_block_bad_upload_enabled" type="checkbox" name="security_block_bad_upload_enabled" value="1" @checked(old('security_block_bad_upload_enabled', $settings['security_block_bad_upload_enabled']))>
                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                </div>
                                <div class="setting-toggle-copy" aria-hidden="true">
                                    <span class="setting-toggle-text">可疑上传拦截</span>
                                    <span class="setting-toggle-state" id="security_block_bad_upload_enabled_label">{{ old('security_block_bad_upload_enabled', $settings['security_block_bad_upload_enabled']) ? '已开启' : '未开启' }}</span>
                                    <span class="setting-toggle-desc">遇到 .php、.jsp 这类危险后缀上传请求时，直接拒绝并计入安全统计。</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-toggle-grid">
                    <span class="settings-label">频繁刷新防护</span>
                    <div class="settings-toggle-shell">
                        <div class="settings-field setting-toggle-field">
                            <div class="setting-toggle-row">
                                <div class="setting-toggle-control">
                                    <input class="setting-toggle-input" id="security_rate_limit_enabled" type="checkbox" name="security_rate_limit_enabled" value="1" @checked(old('security_rate_limit_enabled', $settings['security_rate_limit_enabled']))>
                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                </div>
                                <div class="setting-toggle-copy" aria-hidden="true">
                                    <span class="setting-toggle-text">启用频繁刷新防护</span>
                                    <span class="setting-toggle-state" id="security_rate_limit_enabled_label">{{ old('security_rate_limit_enabled', $settings['security_rate_limit_enabled']) ? '已开启' : '未开启' }}</span>
                                    <span class="setting-toggle-desc">超过阈值后会短时间拒绝重复访问，并记录为频繁刷新拦截。</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <label class="settings-field">
                    <span class="settings-label">统计窗口秒数</span>
                    <input class="field" type="number" name="security_rate_limit_window_seconds" min="1" max="300" value="{{ old('security_rate_limit_window_seconds', $settings['security_rate_limit_window_seconds']) }}">
                    <span class="settings-note">例如 10，表示按 10 秒为一个统计窗口。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">普通页面最大请求次数</span>
                    <input class="field" type="number" name="security_rate_limit_max_requests" min="1" max="1000" value="{{ old('security_rate_limit_max_requests', $settings['security_rate_limit_max_requests']) }}">
                    <span class="settings-note">超过次数后会触发频繁刷新拦截。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">敏感页面最大请求次数</span>
                    <input class="field" type="number" name="security_rate_limit_sensitive_max_requests" min="1" max="500" value="{{ old('security_rate_limit_sensitive_max_requests', $settings['security_rate_limit_sensitive_max_requests']) }}">
                    <span class="settings-note">留言提交、工资查询等高风险入口建议用更低阈值。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">最近事件保留条数</span>
                    <input class="field" type="number" name="security_event_retention_limit" min="20" max="1000" value="{{ old('security_event_retention_limit', $settings['security_event_retention_limit']) }}">
                    <span class="settings-note">每个站点只保留最近这么多条拦截记录。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">统计保留天数</span>
                    <input class="field" type="number" name="security_stats_retention_days" min="7" max="3650" value="{{ old('security_stats_retention_days', $settings['security_stats_retention_days']) }}">
                    <span class="settings-note">超过这个天数的安全统计会自动裁剪。</span>
                </label>
            </div>
        </section>

        <section class="settings-panel" id="access" data-settings-tab-panel="access" role="tabpanel">
            <h3 class="settings-panel-title">后台开关</h3>
            <div class="settings-panel-desc">关闭后只影响后台登录和后台访问，站点前台不受影响。超级管理员始终可以进入后台。</div>

            <div class="settings-access-card">
                <div class="settings-field setting-toggle-field">
                    <span class="settings-label">后台状态</span>
                    <div class="setting-toggle-row">
                        <div class="setting-toggle-control">
                            <input class="setting-toggle-input" id="admin_enabled" type="checkbox" name="admin_enabled" value="1" @checked(old('admin_enabled', $settings['admin_enabled']))>
                            <span class="setting-toggle-track" aria-hidden="true"></span>
                        </div>
                        <div class="setting-toggle-copy" aria-hidden="true">
                            <span class="setting-toggle-text">后台访问开关</span>
                            <span class="setting-toggle-state" id="admin_enabled_label">{{ old('admin_enabled', $settings['admin_enabled']) ? '已开启' : '未开启' }}</span>
                            <span class="setting-toggle-desc">关闭后仅超级管理员可进入后台，站点前台访问不受影响。</span>
                        </div>
                    </div>
                </div>

                <label class="settings-field is-full">
                    <span class="settings-label">关闭提示信息</span>
                    <textarea class="field textarea" name="admin_disabled_message" rows="3">{{ old('admin_disabled_message', $settings['admin_disabled_message']) }}</textarea>
                    <span class="settings-note">后台关闭后，非超级管理员会看到这条提示。</span>
                </label>
            </div>
        </section>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const allowedTabs = ['basic', 'upload', 'security', 'access'];
            const currentTabInput = document.getElementById('current_tab');

            const syncSwitch = () => {
                const input = document.getElementById('admin_enabled');
                const label = document.getElementById('admin_enabled_label');
                const resizeInput = document.getElementById('attachment_image_auto_resize');
                const resizeLabel = document.getElementById('attachment_image_auto_resize_label');
                const compressInput = document.getElementById('attachment_image_auto_compress');
                const compressLabel = document.getElementById('attachment_image_auto_compress_label');
                const securitySwitches = [
                    'security_site_protection_enabled',
                    'security_block_bad_path_enabled',
                    'security_block_sql_injection_enabled',
                    'security_block_xss_enabled',
                    'security_block_path_traversal_enabled',
                    'security_block_bad_upload_enabled',
                    'security_rate_limit_enabled',
                ];

                if (input && label) {
                    label.textContent = input.checked ? '已开启' : '未开启';
                }

                if (resizeInput && resizeLabel) {
                    resizeLabel.textContent = resizeInput.checked ? '已开启' : '未开启';
                }

                if (compressInput && compressLabel) {
                    compressLabel.textContent = compressInput.checked ? '已开启' : '未开启';
                }

                securitySwitches.forEach((name) => {
                    const switchInput = document.getElementById(name);
                    const switchLabel = document.getElementById(`${name}_label`);

                    if (switchInput && switchLabel) {
                        switchLabel.textContent = switchInput.checked ? '已开启' : '未开启';
                    }
                });
            };

            const activateTab = (tab, syncUrl = true) => {
                const normalizedTab = allowedTabs.includes(tab) ? tab : 'basic';

                document.querySelectorAll('[data-settings-tab-trigger]').forEach((button) => {
                    const isActive = button.getAttribute('data-settings-tab-trigger') === normalizedTab;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                document.querySelectorAll('[data-settings-tab-panel]').forEach((panel) => {
                    panel.classList.toggle('is-active', panel.getAttribute('data-settings-tab-panel') === normalizedTab);
                });

                if (currentTabInput) {
                    currentTabInput.value = normalizedTab;
                }

                if (syncUrl) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', normalizedTab);
                    window.history.replaceState({}, '', url.toString());
                }
            };

            const renderAssetPreview = (slot) => {
                const fileInput = document.getElementById(`admin_${slot}_file`);
                const preview = document.querySelector(`[data-system-preview="${slot}"]`);
                const note = document.querySelector(`[data-system-note="${slot}"]`);
                const clearInput = document.getElementById(`admin_${slot}_clear`);

                if (!preview || !note || !clearInput) {
                    return;
                }

                let image = preview.querySelector(`[data-system-preview-image="${slot}"]`);
                let empty = preview.querySelector(`[data-system-preview-empty="${slot}"]`);
                const initialImage = image?.getAttribute('src') || '';

                if (clearInput.value === '1' || initialImage === '') {
                    if (image) {
                        image.remove();
                    }

                    if (!empty) {
                        empty = document.createElement('div');
                        empty.className = 'settings-media-empty';
                        empty.setAttribute('data-system-preview-empty', slot);
                        empty.textContent = slot === 'logo' ? '当前还没有设置后台 Logo' : '当前还没有设置后台 ICO';
                        preview.appendChild(empty);
                    }

                    note.textContent = slot === 'logo'
                        ? '推荐显示高度控制在 36px 内。'
                        : '建议使用清晰的小图标素材。';
                    return;
                }

                if (empty) {
                    empty.remove();
                }

                if (!image) {
                    image = document.createElement('img');
                    image.setAttribute('data-system-preview-image', slot);
                    image.alt = slot === 'logo' ? '后台 Logo 预览' : '后台 ICO 预览';
                    preview.appendChild(image);
                }

                image.src = initialImage;
                note.textContent = initialImage;
            };

            document.querySelectorAll('[data-settings-tab-trigger]').forEach((button) => {
                button.addEventListener('click', () => {
                    activateTab(button.getAttribute('data-settings-tab-trigger') || 'basic');
                });
            });

            ['logo', 'favicon'].forEach((slot) => {
                const fileInput = document.getElementById(`admin_${slot}_file`);
                const clearInput = document.getElementById(`admin_${slot}_clear`);
                const clearTrigger = document.querySelector(`[data-system-clear-trigger="${slot}"]`);

                fileInput?.addEventListener('change', () => {
                    const preview = document.querySelector(`[data-system-preview="${slot}"]`);
                    const note = document.querySelector(`[data-system-note="${slot}"]`);

                    if (!fileInput || !preview || !note) {
                        return;
                    }

                    const file = fileInput.files?.[0];
                    let image = preview.querySelector(`[data-system-preview-image="${slot}"]`);
                    let empty = preview.querySelector(`[data-system-preview-empty="${slot}"]`);

                    if (!file) {
                        return;
                    }

                    if (empty) {
                        empty.remove();
                    }

                    if (!image) {
                        image = document.createElement('img');
                        image.setAttribute('data-system-preview-image', slot);
                        image.alt = slot === 'logo' ? '后台 Logo 预览' : '后台 ICO 预览';
                        preview.appendChild(image);
                    }

                    image.src = URL.createObjectURL(file);
                    note.textContent = file.name;
                    clearInput.value = '0';
                });

                clearTrigger?.addEventListener('click', () => {
                    if (!fileInput || !clearInput) {
                        return;
                    }

                    fileInput.value = '';
                    clearInput.value = '1';
                    const existingImage = document.querySelector(`[data-system-preview="${slot}"] [data-system-preview-image="${slot}"]`);
                    existingImage?.setAttribute('src', '');
                    renderAssetPreview(slot);
                });

                renderAssetPreview(slot);
            });

            document.getElementById('admin_enabled')?.addEventListener('change', syncSwitch);
            document.getElementById('attachment_image_auto_resize')?.addEventListener('change', syncSwitch);
            document.getElementById('attachment_image_auto_compress')?.addEventListener('change', syncSwitch);
            [
                'security_site_protection_enabled',
                'security_block_bad_path_enabled',
                'security_block_sql_injection_enabled',
                'security_block_xss_enabled',
                'security_block_path_traversal_enabled',
                'security_block_bad_upload_enabled',
                'security_rate_limit_enabled',
            ].forEach((id) => {
                document.getElementById(id)?.addEventListener('change', syncSwitch);
            });
            activateTab(@json($activeTab ?? 'basic'), false);
            syncSwitch();
        })();
    </script>
@endpush
