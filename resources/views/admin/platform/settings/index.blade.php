@extends('layouts.admin')

@section('title', '系统设置 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 系统设置')

@push('styles')
    <link rel="stylesheet" href="/css/platform-settings.css">
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

    <form id="system-settings-form" method="POST" action="{{ route('admin.platform.settings.update') }}" class="settings-shell" enctype="multipart/form-data" data-active-tab="{{ $activeTab ?? 'basic' }}">
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
    <script src="/js/platform-settings.js"></script>
@endpush
