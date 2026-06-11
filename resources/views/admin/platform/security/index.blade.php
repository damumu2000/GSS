@extends('layouts.admin')

@section('title', '安护盾 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 平台 / 安护盾')

@push('styles')
    <link rel="stylesheet" href="/css/platform-settings.css">
    <link rel="stylesheet" href="/css/platform-security-overview.css">
@endpush

@push('scripts')
    <script src="/js/platform-security.js" defer></script>
@endpush

@section('content')
    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">安护盾</h2>
            <div class="page-header-desc">统一管理全平台基础防护规则，并查看各站点近 7 天的拦截态势。</div>
        </div>
        <div class="page-header-actions">
            <button class="button" type="submit" form="platform-security-settings-form">保存设置</button>
        </div>
    </section>

    <section class="platform-security-metrics">
        <article class="platform-security-card">
            <div class="platform-security-label">今日拦截</div>
            <div class="platform-security-value">{{ number_format($overview['today_blocked']) }}</div>
            <div class="platform-security-note">全平台今天命中的总拦截次数</div>
        </article>
        <article class="platform-security-card">
            <div class="platform-security-label">近 7 天拦截</div>
            <div class="platform-security-value">{{ number_format($overview['seven_day_blocked']) }}</div>
            <div class="platform-security-note">最近 7 天全平台累计拦截</div>
        </article>
        <article class="platform-security-card">
            <div class="platform-security-label">近 7 天高危</div>
            <div class="platform-security-value">{{ number_format($overview['seven_day_high_risk']) }}</div>
            <div class="platform-security-note">高危和严重规则命中总数</div>
        </article>
        <article class="platform-security-card">
            <div class="platform-security-label">当前封禁 IP</div>
            <div class="platform-security-value">{{ number_format($overview['active_blocked_ips']) }}</div>
            <div class="platform-security-note">当前仍在封禁中的异常来源</div>
        </article>
    </section>

    <section class="platform-security-workspace" data-platform-security-tabs>
        <div class="platform-security-tabs" role="tablist" aria-label="安护盾管理">
            <button class="platform-security-tab is-active" id="platform-security-tab-settings" type="button" role="tab" aria-selected="true" aria-controls="platform-security-panel-settings" data-platform-security-tab-trigger="settings">
                <span>防护设置</span>
                <strong>规则与阈值</strong>
            </button>
            <button class="platform-security-tab" id="platform-security-tab-sites" type="button" role="tab" aria-selected="false" aria-controls="platform-security-panel-sites" data-platform-security-tab-trigger="sites">
                <span>站点态势</span>
                <strong>{{ number_format($overview['active_sites']) }} 个站点</strong>
            </button>
            <button class="platform-security-tab" id="platform-security-tab-events" type="button" role="tab" aria-selected="false" aria-controls="platform-security-panel-events" data-platform-security-tab-trigger="events">
                <span>高危记录</span>
                <strong>近 7 天</strong>
            </button>
        </div>

        <div class="platform-security-tab-panel is-active" id="platform-security-panel-settings" role="tabpanel" aria-labelledby="platform-security-tab-settings" data-platform-security-tab-panel="settings">
            <form id="platform-security-settings-form" method="POST" action="{{ route('admin.platform.security.settings.update') }}" class="settings-shell platform-security-settings">
                @csrf

                <section class="settings-panel is-active">
            <h3 class="settings-panel-title">防护设置</h3>
            <div class="settings-panel-desc">平台统一控制站点基础拦截规则。普通频繁刷新只做短时限制，连续恶意攻击会按站点与 IP 进入长期封禁。</div>

            <div class="settings-grid">
                <div class="settings-toggle-grid">
                    <span class="settings-label">防护总开关</span>
                    <div class="settings-toggle-shell">
                        <div class="settings-field setting-toggle-field">
                            <div class="setting-toggle-row">
                                <div class="setting-toggle-control">
                                    <input class="setting-toggle-input" id="security_site_protection_enabled" type="checkbox" name="security_site_protection_enabled" value="1" @checked(old('security_site_protection_enabled', $securitySettings['security_site_protection_enabled']))>
                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                </div>
                                <div class="setting-toggle-copy" aria-hidden="true">
                                    <span class="setting-toggle-text">启用站点安护盾</span>
                                    <span class="setting-toggle-state" id="security_site_protection_enabled_label">{{ old('security_site_protection_enabled', $securitySettings['security_site_protection_enabled']) ? '已开启' : '未开启' }}</span>
                                    <span class="setting-toggle-desc">开启后，会在前台统一拦截恶意扫描、注入尝试和异常高频刷新。</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-toggle-grid">
                    <span class="settings-label">拦截规则</span>
                    <div class="settings-toggle-shell">
                        @foreach ([
                            'security_block_bad_path_enabled' => ['恶意扫描路径拦截', '用于拦截扫描 .env、wp-admin、phpunit 等高风险路径的请求。'],
                            'security_block_sql_injection_enabled' => ['SQL 注入拦截', '针对明显的注入关键字做基础拦截，减少低级攻击命中业务层。'],
                            'security_block_xss_enabled' => ['XSS 拦截', '对脚本注入和常见事件属性注入做基础识别和拦截。'],
                            'security_block_path_traversal_enabled' => ['路径穿越拦截', '用于拦截 ../ 这类越级访问尝试，减少探测系统目录的请求。'],
                            'security_block_bad_upload_enabled' => ['可疑上传拦截', '遇到 .php、.jsp 这类危险后缀上传请求时，直接拒绝并计入安全统计。'],
                            'security_block_bad_client_enabled' => ['脚本扫描器识别', '识别 sqlmap、nuclei、nikto、curl、python-requests 等明显脚本扫描客户端。'],
                            'security_block_bad_method_enabled' => ['异常请求方法拦截', '拦截 TRACE、TRACK、CONNECT、DEBUG 这类常见扫描探测方法。'],
                            'security_block_bad_payload_enabled' => ['异常参数防护', '拦截参数数量异常、单个参数超长等脚本探测和 payload 灌入。'],
                        ] as $field => [$label, $desc])
                            <div class="settings-field setting-toggle-field">
                                <div class="setting-toggle-row">
                                    <div class="setting-toggle-control">
                                        <input class="setting-toggle-input" id="{{ $field }}" type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $securitySettings[$field]))>
                                        <span class="setting-toggle-track" aria-hidden="true"></span>
                                    </div>
                                    <div class="setting-toggle-copy" aria-hidden="true">
                                        <span class="setting-toggle-text">{{ $label }}</span>
                                        <span class="setting-toggle-state" id="{{ $field }}_label">{{ old($field, $securitySettings[$field]) ? '已开启' : '未开启' }}</span>
                                        <span class="setting-toggle-desc">{{ $desc }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <label class="settings-field">
                    <span class="settings-label">最大参数数量</span>
                    <input class="field" type="number" name="security_payload_max_fields" min="10" max="1000" value="{{ old('security_payload_max_fields', $securitySettings['security_payload_max_fields']) }}">
                    <span class="settings-note">单次请求参数数量超过该值时触发异常参数拦截。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">单参数最大长度</span>
                    <input class="field" type="number" name="security_payload_max_value_length" min="256" max="20000" value="{{ old('security_payload_max_value_length', $securitySettings['security_payload_max_value_length']) }}">
                    <span class="settings-note">单个参数值过长时触发拦截，单位为字符。</span>
                </label>

                <div class="settings-toggle-grid">
                    <span class="settings-label">频繁刷新防护</span>
                    <div class="settings-toggle-shell">
                        <div class="settings-field setting-toggle-field">
                            <div class="setting-toggle-row">
                                <div class="setting-toggle-control">
                                    <input class="setting-toggle-input" id="security_rate_limit_enabled" type="checkbox" name="security_rate_limit_enabled" value="1" @checked(old('security_rate_limit_enabled', $securitySettings['security_rate_limit_enabled']))>
                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                </div>
                                <div class="setting-toggle-copy" aria-hidden="true">
                                    <span class="setting-toggle-text">启用频繁刷新防护</span>
                                    <span class="setting-toggle-state" id="security_rate_limit_enabled_label">{{ old('security_rate_limit_enabled', $securitySettings['security_rate_limit_enabled']) ? '已开启' : '未开启' }}</span>
                                    <span class="setting-toggle-desc">优先按同一浏览器设备统计，公网 IP 仅做高阈值兜底；不参与 24 小时恶意封禁判断。</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <label class="settings-field">
                    <span class="settings-label">统计窗口秒数</span>
                    <input class="field" type="number" name="security_rate_limit_window_seconds" min="1" max="300" value="{{ old('security_rate_limit_window_seconds', $securitySettings['security_rate_limit_window_seconds']) }}">
                    <span class="settings-note">例如 10，表示按 10 秒为一个统计窗口。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">普通页面最大请求次数</span>
                    <input class="field" type="number" name="security_rate_limit_max_requests" min="1" max="1000" value="{{ old('security_rate_limit_max_requests', $securitySettings['security_rate_limit_max_requests']) }}">
                    <span class="settings-note">同一浏览器设备超过次数后会触发频繁刷新拦截；同公网大量异常访问仍会被兜底限制。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">敏感页面最大请求次数</span>
                    <input class="field" type="number" name="security_rate_limit_sensitive_max_requests" min="1" max="500" value="{{ old('security_rate_limit_sensitive_max_requests', $securitySettings['security_rate_limit_sensitive_max_requests']) }}">
                    <span class="settings-note">留言提交、工资查询等高风险入口建议用更低阈值。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">超限限制时长（秒）</span>
                    <input class="field" type="number" name="security_rate_limit_block_seconds" min="0" max="86400" value="{{ old('security_rate_limit_block_seconds', $securitySettings['security_rate_limit_block_seconds']) }}">
                    <span class="settings-note">短时限制时长。填 0 表示只按窗口拦截，不进入短时封禁。</span>
                </label>

                <div class="settings-toggle-grid">
                    <span class="settings-label">扫描试探防护</span>
                    <div class="settings-toggle-shell">
                        <div class="settings-field setting-toggle-field">
                            <div class="setting-toggle-row">
                                <div class="setting-toggle-control">
                                    <input class="setting-toggle-input" id="security_scan_probe_enabled" type="checkbox" name="security_scan_probe_enabled" value="1" @checked(old('security_scan_probe_enabled', $securitySettings['security_scan_probe_enabled']))>
                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                </div>
                                <div class="setting-toggle-copy" aria-hidden="true">
                                    <span class="setting-toggle-text">启用扫描试探防护</span>
                                    <span class="setting-toggle-state" id="security_scan_probe_enabled_label">{{ old('security_scan_probe_enabled', $securitySettings['security_scan_probe_enabled']) ? '已开启' : '未开启' }}</span>
                                    <span class="setting-toggle-desc">同一站点同一 IP 短时间多次命中扫描、注入、路径穿越或可疑上传时，会先进入短时限制。</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <label class="settings-field">
                    <span class="settings-label">扫描统计窗口（秒）</span>
                    <input class="field" type="number" name="security_scan_probe_window_seconds" min="10" max="86400" value="{{ old('security_scan_probe_window_seconds', $securitySettings['security_scan_probe_window_seconds']) }}">
                    <span class="settings-note">例如 300，表示在 5 分钟内累计命中扫描类规则达到阈值后触发临时限制。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">扫描触发阈值（次）</span>
                    <input class="field" type="number" name="security_scan_probe_threshold" min="1" max="100" value="{{ old('security_scan_probe_threshold', $securitySettings['security_scan_probe_threshold']) }}">
                    <span class="settings-note">同一 IP 在统计窗口内多次命中扫描类规则，达到这个次数后进入限制状态。</span>
                </label>

                <div class="settings-toggle-grid">
                    <span class="settings-label">连续攻击封禁</span>
                    <div class="settings-toggle-shell">
                        <div class="settings-field setting-toggle-field">
                            <div class="setting-toggle-row">
                                <div class="setting-toggle-control">
                                    <input class="setting-toggle-input" id="security_malicious_auto_block_enabled" type="checkbox" name="security_malicious_auto_block_enabled" value="1" @checked(old('security_malicious_auto_block_enabled', $securitySettings['security_malicious_auto_block_enabled']))>
                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                </div>
                                <div class="setting-toggle-copy" aria-hidden="true">
                                    <span class="setting-toggle-text">启用长期恶意封禁</span>
                                    <span class="setting-toggle-state" id="security_malicious_auto_block_enabled_label">{{ old('security_malicious_auto_block_enabled', $securitySettings['security_malicious_auto_block_enabled']) ? '已开启' : '未开启' }}</span>
                                    <span class="setting-toggle-desc">只统计扫描、注入、XSS、路径穿越、可疑上传、脚本扫描器和异常参数，不统计普通刷新。</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <label class="settings-field">
                    <span class="settings-label">连续攻击统计窗口（秒）</span>
                    <input class="field" type="number" name="security_malicious_auto_block_window_seconds" min="60" max="86400" value="{{ old('security_malicious_auto_block_window_seconds', $securitySettings['security_malicious_auto_block_window_seconds']) }}">
                    <span class="settings-note">默认 3600 秒，即 1 小时。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">连续攻击触发阈值（次）</span>
                    <input class="field" type="number" name="security_malicious_auto_block_threshold" min="3" max="100" value="{{ old('security_malicious_auto_block_threshold', $securitySettings['security_malicious_auto_block_threshold']) }}">
                    <span class="settings-note">默认 10 次，达到后按站点与 IP 封禁。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">连续攻击封禁时长（秒）</span>
                    <input class="field" type="number" name="security_malicious_auto_block_seconds" min="60" max="604800" value="{{ old('security_malicious_auto_block_seconds', $securitySettings['security_malicious_auto_block_seconds']) }}">
                    <span class="settings-note">默认 86400 秒，即 24 小时。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">IP 白名单</span>
                    <textarea class="field textarea" name="security_ip_allowlist" rows="4" placeholder="每行一个 IP 或 IPv4 网段，例如 192.168.1.10">{{ old('security_ip_allowlist', $securitySettings['security_ip_allowlist']) }}</textarea>
                    <span class="settings-note">白名单来源会跳过安护盾拦截，建议只填写可信办公出口或监测服务 IP。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">IP 黑名单</span>
                    <textarea class="field textarea" name="security_ip_blocklist" rows="4" placeholder="每行一个 IP 或 IPv4 网段，例如 203.0.113.0/24">{{ old('security_ip_blocklist', $securitySettings['security_ip_blocklist']) }}</textarea>
                    <span class="settings-note">黑名单来源会优先拦截，并在站点安护盾记录为黑名单 IP 拦截。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">最近事件保留条数</span>
                    <input class="field" type="number" name="security_event_retention_limit" min="20" max="1000" value="{{ old('security_event_retention_limit', $securitySettings['security_event_retention_limit']) }}">
                    <span class="settings-note">每个站点只保留最近这么多条拦截记录。</span>
                </label>

                <label class="settings-field">
                    <span class="settings-label">统计保留天数</span>
                    <input class="field" type="number" name="security_stats_retention_days" min="7" max="3650" value="{{ old('security_stats_retention_days', $securitySettings['security_stats_retention_days']) }}">
                    <span class="settings-note">超过这个天数的安全统计会自动裁剪。</span>
                </label>
            </div>
                </section>
            </form>
        </div>

        <div class="platform-security-tab-panel" id="platform-security-panel-sites" role="tabpanel" aria-labelledby="platform-security-tab-sites" data-platform-security-tab-panel="sites" hidden>
            <section class="platform-security-panel">
                <div class="platform-security-panel-head">
                    <h3 class="platform-security-panel-title">站点态势</h3>
                    <div class="platform-security-panel-meta">共 {{ number_format($overview['active_sites']) }} 个站点近 7 天有安护盾数据</div>
                </div>

                <div class="platform-security-table-wrap">
                    <table class="platform-security-table">
                        <thead>
                        <tr>
                            <th>站点</th>
                            <th>今日拦截</th>
                            <th>近 7 天</th>
                            <th>可疑 IP</th>
                            <th>当前封禁</th>
                            <th>主要类型</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($overview['site_rows'] as $row)
                            <tr>
                                <td>
                                    <div class="platform-security-site-name">{{ $row['site_name'] }}</div>
                                    <div class="platform-security-site-key">{{ $row['site_key'] }}</div>
                                </td>
                                <td>{{ number_format($row['today_blocked']) }}</td>
                                <td>{{ number_format($row['seven_day_blocked']) }}</td>
                                <td>{{ number_format($row['suspicious_ip_count']) }}</td>
                                <td>{{ number_format($row['blocked_ip_count']) }}</td>
                                <td>{{ $row['top_rule_label'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="platform-security-empty">当前还没有站点级安护盾统计。</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="platform-security-tab-panel" id="platform-security-panel-events" role="tabpanel" aria-labelledby="platform-security-tab-events" data-platform-security-tab-panel="events" hidden>
            <section class="platform-security-panel">
                <div class="platform-security-panel-head">
                    <h3 class="platform-security-panel-title">最近高危记录</h3>
                    <div class="platform-security-panel-meta">仅展示近 7 天高危和严重命中</div>
                </div>

                <div class="platform-security-events">
                    @forelse ($overview['recent_high_risk_events'] as $event)
                        <article class="platform-security-event">
                            <div class="platform-security-event-top">
                                <div class="platform-security-event-title">{{ $event['rule_label'] }}</div>
                                <div class="platform-security-event-time">{{ $event['created_at_label'] }}</div>
                            </div>
                            <div class="platform-security-event-meta">
                                <span>{{ $event['site_name'] }}</span>
                                <a class="platform-security-event-link" href="{{ route('admin.platform.security.ip-detail', ['site_id' => $event['site_id'], 'client_ip' => $event['client_ip']]) }}">{{ $event['client_ip'] }}</a>
                                <span>{{ $event['region_name'] ?? '未知来源' }}</span>
                                <span>{{ $event['request_path'] }}</span>
                            </div>
                        </article>
                    @empty
                        <div class="platform-security-empty">当前还没有跨站点高危记录。</div>
                    @endforelse
                </div>
            </section>
        </div>
    </section>
@endsection
