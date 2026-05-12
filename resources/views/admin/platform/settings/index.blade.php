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
            <button class="settings-nav-button" type="button" data-settings-tab-trigger="mail" role="tab" aria-selected="false">邮件服务</button>
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

        <section class="settings-panel" id="mail" data-settings-tab-panel="mail" role="tabpanel">
            <h3 class="settings-panel-title">邮件服务</h3>
            <div class="settings-panel-desc">平台统一维护发信通道，后续留言提醒、业务通知和系统邮件都复用这里的配置。建议先保存配置，再发送测试邮件。</div>

            <div class="settings-grid settings-mail-grid">
                <div class="settings-toggle-grid">
                    <span class="settings-label">邮件服务总开关</span>
                    <div class="settings-toggle-shell">
                        <div class="settings-field setting-toggle-field">
                            <div class="setting-toggle-row">
                                <div class="setting-toggle-control">
                                    <input class="setting-toggle-input" id="mail_enabled" type="checkbox" name="mail_enabled" value="1" @checked(old('mail_enabled', $settings['mail_enabled']))>
                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                </div>
                                <div class="setting-toggle-copy" aria-hidden="true">
                                    <span class="setting-toggle-text">启用平台邮件服务</span>
                                    <span class="setting-toggle-state" id="mail_enabled_label">{{ old('mail_enabled', $settings['mail_enabled']) ? '已开启' : '未开启' }}</span>
                                    <span class="setting-toggle-desc">关闭后系统不会主动发出业务通知邮件。建议先完成 SMTP 配置并测试成功后再开启。</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <label class="settings-field">
                    <span class="settings-label">发信方式</span>
                    <select class="field settings-select" name="mail_driver">
                        <option value="smtp" @selected(old('mail_driver', $settings['mail_driver']) === 'smtp')>SMTP</option>
                        <option value="log" @selected(old('mail_driver', $settings['mail_driver']) === 'log')>仅写日志</option>
                    </select>
                    <span class="settings-note">`SMTP` 用于真实发送邮件；`仅写日志` 适合本地联调或上线前验证调用链路。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">加密方式</span>
                    <select class="field settings-select" name="mail_encryption">
                        <option value="" @selected(old('mail_encryption', $settings['mail_encryption']) === '')>无</option>
                        <option value="ssl" @selected(old('mail_encryption', $settings['mail_encryption']) === 'ssl')>SSL</option>
                        <option value="tls" @selected(old('mail_encryption', $settings['mail_encryption']) === 'tls')>TLS</option>
                    </select>
                </label>

                <label class="settings-field">
                    <span class="settings-label">SMTP 主机</span>
                    <input class="field" type="text" name="mail_host" value="{{ old('mail_host', $settings['mail_host']) }}" maxlength="255" placeholder="smtp.example.com">
                </label>

                <label class="settings-field">
                    <span class="settings-label">SMTP 端口</span>
                    <input class="field" type="number" name="mail_port" min="1" max="65535" value="{{ old('mail_port', $settings['mail_port']) }}">
                </label>

                <label class="settings-field">
                    <span class="settings-label">SMTP 用户名</span>
                    <input class="field" type="text" name="mail_username" value="{{ old('mail_username', $settings['mail_username']) }}" maxlength="255" autocomplete="off">
                </label>

                <label class="settings-field">
                    <span class="settings-label">SMTP 密码</span>
                    <input class="field" type="password" name="mail_password" value="" maxlength="255" autocomplete="new-password" placeholder="{{ $settings['mail_password_configured'] ? '已设置，如需修改请重新输入' : '请输入 SMTP 密码' }}">
                    <span class="settings-note">{{ $settings['mail_password_configured'] ? '当前已保存密码，留空则保持不变。' : '密码会加密保存，不会在页面明文回显。' }}</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">发件邮箱</span>
                    <input class="field" type="email" name="mail_from_address" value="{{ old('mail_from_address', $settings['mail_from_address']) }}" maxlength="100" placeholder="no-reply@example.com">
                </label>

                <label class="settings-field">
                    <span class="settings-label">发件名称</span>
                    <input class="field" type="text" name="mail_from_name" value="{{ old('mail_from_name', $settings['mail_from_name']) }}" maxlength="100" placeholder="站点通知">
                </label>

                <label class="settings-field">
                    <span class="settings-label">回复邮箱</span>
                    <input class="field" type="email" name="mail_reply_to_address" value="{{ old('mail_reply_to_address', $settings['mail_reply_to_address']) }}" maxlength="100" placeholder="reply@example.com">
                </label>

                <label class="settings-field">
                    <span class="settings-label">连接超时（秒）</span>
                    <input class="field" type="number" name="mail_timeout_seconds" min="1" max="60" value="{{ old('mail_timeout_seconds', $settings['mail_timeout_seconds']) }}">
                </label>

                <div class="settings-toggle-grid">
                    <span class="settings-label">发送保护</span>
                    <div class="settings-toggle-shell">
                        <div class="settings-field setting-toggle-field">
                            <div class="setting-toggle-row">
                                <div class="setting-toggle-control">
                                    <input class="setting-toggle-input" id="mail_rate_limit_enabled" type="checkbox" name="mail_rate_limit_enabled" value="1" @checked(old('mail_rate_limit_enabled', $settings['mail_rate_limit_enabled']))>
                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                </div>
                                <div class="setting-toggle-copy" aria-hidden="true">
                                    <span class="setting-toggle-text">启用邮件发送限流</span>
                                    <span class="setting-toggle-state" id="mail_rate_limit_enabled_label">{{ old('mail_rate_limit_enabled', $settings['mail_rate_limit_enabled']) ? '已开启' : '未开启' }}</span>
                                    <span class="setting-toggle-desc">所有模块后续统一走平台邮件限流。模板层不提供发信能力，避免因循环渲染或异常请求触发批量发信。</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <label class="settings-field">
                    <span class="settings-label">平台限流窗口（秒）</span>
                    <input class="field" type="number" name="mail_rate_limit_window_seconds" min="10" max="3600" value="{{ old('mail_rate_limit_window_seconds', $settings['mail_rate_limit_window_seconds']) }}">
                    <span class="settings-note">用于平台总发送、单站点和单场景的统一统计窗口。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">平台总发送上限</span>
                    <input class="field" type="number" name="mail_rate_limit_global_max" min="1" max="10000" value="{{ old('mail_rate_limit_global_max', $settings['mail_rate_limit_global_max']) }}">
                    <span class="settings-note">同一统计窗口内，整个平台最多允许发送的邮件数量。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">单站点发送上限</span>
                    <input class="field" type="number" name="mail_rate_limit_site_max" min="1" max="10000" value="{{ old('mail_rate_limit_site_max', $settings['mail_rate_limit_site_max']) }}">
                    <span class="settings-note">单个站点在同一统计窗口内最多允许发送的邮件数量。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">单场景发送上限</span>
                    <input class="field" type="number" name="mail_rate_limit_scene_max" min="1" max="10000" value="{{ old('mail_rate_limit_scene_max', $settings['mail_rate_limit_scene_max']) }}">
                    <span class="settings-note">例如留言通知、找回密码、测试发送等，每个场景独立计数。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">收件人限流窗口（秒）</span>
                    <input class="field" type="number" name="mail_rate_limit_recipient_window_seconds" min="60" max="86400" value="{{ old('mail_rate_limit_recipient_window_seconds', $settings['mail_rate_limit_recipient_window_seconds']) }}">
                    <span class="settings-note">用于防止同一收件邮箱在短时间内被连续轰炸。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">单收件人发送上限</span>
                    <input class="field" type="number" name="mail_rate_limit_recipient_max" min="1" max="10000" value="{{ old('mail_rate_limit_recipient_max', $settings['mail_rate_limit_recipient_max']) }}">
                    <span class="settings-note">同一收件邮箱在收件人限流窗口内最多允许收到的邮件数量。</span>
                </label>

                <div class="settings-field is-full">
                    <div class="settings-status-card">
                        <div class="settings-status-title">当前状态</div>
                        <div class="settings-status-body">
                            @if (! old('mail_enabled', $settings['mail_enabled']))
                                邮件服务当前未开启。保存完整配置并开启后，业务模块才可以调用发信能力。
                            @elseif (old('mail_driver', $settings['mail_driver']) === 'log')
                                当前使用“仅写日志”模式。测试发送会写入日志，不会真实投递到邮箱。
                            @elseif ($settings['mail_host'] !== '' && $settings['mail_from_address'] !== '' && $settings['mail_from_name'] !== '')
                                当前已具备基础 SMTP 配置，可保存后发送测试邮件进一步验证连通性。
                            @else
                                当前 SMTP 配置仍不完整，请补全主机、端口和发件人信息后再测试。
                            @endif
                            <br>
                            @if (old('mail_rate_limit_enabled', $settings['mail_rate_limit_enabled']))
                                当前已启用平台邮件限流：{{ old('mail_rate_limit_window_seconds', $settings['mail_rate_limit_window_seconds']) }} 秒内平台最多 {{ old('mail_rate_limit_global_max', $settings['mail_rate_limit_global_max']) }} 封、单站点 {{ old('mail_rate_limit_site_max', $settings['mail_rate_limit_site_max']) }} 封、单场景 {{ old('mail_rate_limit_scene_max', $settings['mail_rate_limit_scene_max']) }} 封；{{ old('mail_rate_limit_recipient_window_seconds', $settings['mail_rate_limit_recipient_window_seconds']) }} 秒内同一收件人最多 {{ old('mail_rate_limit_recipient_max', $settings['mail_rate_limit_recipient_max']) }} 封。
                            @else
                                当前未启用邮件发送限流，建议开启，避免模块异常时短时间内重复发信。
                            @endif
                        </div>
                    </div>
                </div>

                <div class="settings-field is-full">
                    <div class="settings-status-card">
                        <div class="settings-status-title">队列执行状态</div>
                        <div class="settings-status-body">
                            当前队列连接：{{ $mailDiagnostics['queue_connection'] }}
                            <br>
                            @if (! $mailDiagnostics['requires_worker'])
                                worker 状态：当前模式不需要独立 worker
                            @elseif ($mailDiagnostics['worker_active'])
                                worker 状态：运行中
                            @else
                                worker 状态：未检测到活跃 worker
                            @endif
                            <br>
                            待执行任务：{{ $mailDiagnostics['pending_jobs'] ?? '—' }}　
                            失败任务：{{ $mailDiagnostics['failed_jobs'] ?? '—' }}
                            @if ($mailDiagnostics['last_seen_at'] !== '')
                                <br>
                                最近心跳：{{ $mailDiagnostics['last_seen_at'] }}
                            @endif
                            <br>
                            {{ $mailDiagnostics['message'] }}
                            @if ($mailDiagnostics['suggestion'] !== '')
                                <br>
                                建议：{{ $mailDiagnostics['suggestion'] }}
                            @endif
                        </div>
                    </div>
                </div>

                @if (is_array($mailLastFailure ?? null))
                    <div class="settings-field is-full">
                        <div class="settings-status-card">
                            <div class="settings-status-title">最近失败摘要</div>
                            <div class="settings-status-body">
                                {{ (string) ($mailLastFailure['occurred_at'] ?? '') }} · {{ (string) ($mailLastFailure['type'] ?? '') }}
                                <br>
                                {{ (string) ($mailLastFailure['message'] ?? '') }}
                            </div>
                        </div>
                    </div>
                @endif

                <div class="settings-field is-full">
                    <div class="settings-test-card">
                        <div class="settings-test-head">
                            <div>
                                <div class="settings-test-title">测试发送</div>
                                <div class="settings-test-desc">这里读取的是当前已保存配置。修改完参数后，请先点击顶部“保存设置”，再发测试邮件。</div>
                            </div>
                            <div class="settings-test-actions">
                                <input class="field" type="email" name="mail_test_to" form="system-mail-test-form" value="{{ old('mail_test_to') }}" maxlength="100" placeholder="test@example.com">
                                <button class="button secondary" type="submit" form="system-mail-test-form">发送测试邮件</button>
                            </div>
                        </div>
                    </div>
                </div>
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

    <form id="system-mail-test-form" method="POST" action="{{ route('admin.platform.settings.mail-test') }}">
        @csrf
    </form>
@endsection

@push('scripts')
    <script src="/js/platform-settings.js"></script>
@endpush
