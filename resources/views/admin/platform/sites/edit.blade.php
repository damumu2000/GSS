@extends('layouts.admin')

@section('title', '编辑站点 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 站点管理 / 编辑站点')

@php
    $openedAtValue = old('opened_at', optional($site->opened_at ? \Illuminate\Support\Carbon::parse($site->opened_at) : null)?->format('Y-m-d'));
    $expiresAtValue = old('expires_at', optional($site->expires_at ? \Illuminate\Support\Carbon::parse($site->expires_at) : null)?->format('Y-m-d'));
    $expiresDiffDays = $site->expires_at
        ? now()->diffInDays(\Illuminate\Support\Carbon::parse($site->expires_at), false)
        : null;
    $expiresSoon = $site->expires_at
        ? $expiresDiffDays !== null && $expiresDiffDays <= 30 && $expiresDiffDays >= 0
        : false;
    $expiresExpired = $site->expires_at
        ? $expiresDiffDays !== null && $expiresDiffDays < 0
        : false;
    $selectedSiteAdminIds = collect($selectedSiteAdminIds ?? [])->map(fn ($id) => (int) $id)->all();
    $domainRows = collect(preg_split('/\r\n|\r|\n/', (string) old('domains', $domains->pluck('domain')->implode("\n")), -1, PREG_SPLIT_NO_EMPTY))
        ->map(fn ($domain) => trim($domain))
        ->filter()
        ->values();

    if ($domainRows->isEmpty()) {
        $domainRows = collect(['']);
    }

    $attachmentUsage = $attachmentUsage ?? [];
    $managedAttachmentCount = (int) ($attachmentUsage['managed_count'] ?? 0);
    $managedAttachmentBytes = \App\Support\SiteStorageUsage::formatBytes((int) ($attachmentUsage['managed_bytes'] ?? 0));
    $legacyAttachmentCount = (int) ($attachmentUsage['legacy_count'] ?? 0);
    $legacyAttachmentBytes = \App\Support\SiteStorageUsage::formatBytes((int) ($attachmentUsage['legacy_bytes'] ?? 0));
    $totalAttachmentBytes = \App\Support\SiteStorageUsage::formatBytes((int) ($attachmentUsage['total_bytes'] ?? 0));
    $attachmentLimitBytes = (int) ($attachmentUsage['limit_bytes'] ?? 0);
    $attachmentLimitLabel = $attachmentLimitBytes > 0 ? \App\Support\SiteStorageUsage::formatBytes($attachmentLimitBytes) : '不限';
    $legacyAttachmentScannedAt = $attachmentUsage['legacy_scanned_at'] ?? null;
    $hasLegacyAttachments = (bool) ($attachmentUsage['has_legacy'] ?? false);
    $hasLegacyAttachmentDirectory = (bool) ($attachmentUsage['has_legacy_directory'] ?? false);

    $moduleTabHasErrors = $errors->has('module')
        || $errors->has('module_id')
        || $errors->has('is_trial')
        || $errors->has('is_paused');
    $activeEditTab = request()->query('tab') === 'modules' || $moduleTabHasErrors ? 'modules' : 'basic';
@endphp

@push('styles')
    @include('admin.site._custom_select_styles')
    <link rel="stylesheet" href="{{ asset('css/platform-site-modules.css') }}">
    @include('admin.platform.sites._form_styles')
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">编辑站点</h2>
            <div class="page-header-desc">当前正在维护 {{ $site->name }} 的基础信息、绑定域名、站点管理员和模板数量上限。</div>
        </div>
        <div class="topbar-right">
            <a class="button secondary" href="{{ route('admin.platform.sites.index') }}">返回站点管理</a>
            <button class="button" type="submit" form="site-edit-form" data-loading-text="保存中...">保存站点信息</button>
        </div>
    </section>

    <section class="site-edit-tabs" data-site-edit-tabs data-active-tab="{{ $activeEditTab }}">
        <button class="site-edit-tab @if ($activeEditTab === 'basic') is-active @endif" type="button" data-site-edit-tab-trigger="basic" aria-pressed="{{ $activeEditTab === 'basic' ? 'true' : 'false' }}">基础设置</button>
        <button class="site-edit-tab @if ($activeEditTab === 'modules') is-active @endif" type="button" data-site-edit-tab-trigger="modules" aria-pressed="{{ $activeEditTab === 'modules' ? 'true' : 'false' }}">模块绑定</button>
    </section>

    <section class="site-form-card @if ($activeEditTab !== 'basic') is-hidden @endif" data-site-edit-tab-panel="basic">
        <form
            id="site-edit-form"
            method="POST"
            action="{{ route('admin.platform.sites.update', $site->id) }}"
            data-platform-site-form
            data-validation-errors='@json($errors->all())'
        >
            @csrf
            <input type="hidden" name="site_key" value="{{ old('site_key', $site->site_key) }}">
            <input type="hidden" name="site_admin_ids_present" value="1">

            <div class="site-form-body">
                <div class="site-layout-grid">
                    <div class="site-column">
                        <section class="site-module">
                            <div class="site-module-header">
                                <span class="site-module-accent"></span>
                                <div class="site-module-title">基础配置</div>
                            </div>
                            <div class="site-module-body">
                                <div class="site-form-grid">
                                    <label class="field-group">
                                        <span class="field-label">站点名称</span>
                                        <input class="field @error('name') is-error @enderror" id="name" type="text" name="name" value="{{ old('name', $site->name) }}" @error('name') aria-invalid="true" @enderror>
                                        @error('name')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">资源库容量限制（MB），0为不限制</span>
                                        <input class="field @error('attachment_storage_limit_mb') is-error @enderror" id="attachment_storage_limit_mb" type="number" name="attachment_storage_limit_mb" min="0" step="1" value="{{ $attachmentStorageLimitMb }}" @error('attachment_storage_limit_mb') aria-invalid="true" @enderror>
                                        @error('attachment_storage_limit_mb')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">模板数量上限</span>
                                        <input class="field @error('template_limit') is-error @enderror" id="template_limit" type="number" name="template_limit" min="1" max="50" step="1" value="{{ old('template_limit', $site->template_limit ?? 1) }}" @error('template_limit') aria-invalid="true" @enderror>
                                        @error('template_limit')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">站点状态</span>
                                        <div class="custom-select @error('status') is-error @enderror" data-custom-select>
                                            <select class="custom-select-native" id="status" name="status" data-select-native @error('status') aria-invalid="true" @enderror>
                                                <option value="1" @selected((string) old('status', (string) $site->status) === '1')>开启</option>
                                                <option value="0" @selected((string) old('status', (string) $site->status) === '0')>关闭</option>
                                            </select>
                                            <button class="custom-select-trigger" type="button" data-select-trigger aria-expanded="false">
                                                <span data-select-label>{{ (string) old('status', (string) $site->status) === '0' ? '关闭' : '开启' }}</span>
                                            </button>
                                            <div class="custom-select-panel">
                                                <button class="custom-select-option @if ((string) old('status', (string) $site->status) === '1') is-active @endif" type="button" data-select-option data-value="1">
                                                    <span>开启</span>
                                                    <svg class="custom-select-check" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5 6.5 11.5 12.5 4.5"/></svg>
                                                </button>
                                                <button class="custom-select-option @if ((string) old('status', (string) $site->status) === '0') is-active @endif" type="button" data-select-option data-value="0">
                                                    <span>关闭</span>
                                                    <svg class="custom-select-check" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5 6.5 11.5 12.5 4.5"/></svg>
                                                </button>
                                            </div>
                                        </div>
                                        @error('status')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>
                                </div>

                                <div class="field-group">
                                    <span class="field-label">站点管理员</span>
                                    <div class="admin-picker" data-admin-picker>
                                        <button class="admin-picker-trigger" type="button" data-admin-picker-trigger aria-expanded="false">
                                            <span class="admin-picker-summary" data-admin-picker-summary></span>
                                        </button>
                                        <div class="admin-picker-panel">
                                            <div class="admin-picker-search">
                                                <input class="field" type="text" value="" placeholder="搜索管理员姓名、账号或邮箱" data-admin-picker-search-input>
                                            </div>
                                            <div class="admin-picker-list">
                                                @foreach ($candidateAdmins as $candidateAdmin)
                                                    @php
                                                        $title = $candidateAdmin->name ?: $candidateAdmin->username;
                                                        $desc = $candidateAdmin->email ?: ('@'.$candidateAdmin->username);
                                                        $keywords = strtolower($title.' '.$candidateAdmin->username.' '.$desc);
                                                    @endphp
                                                    <label class="admin-picker-option" data-admin-picker-option data-label="{{ $title }}" data-keywords="{{ $keywords }}">
                                                        <input type="checkbox" name="site_admin_ids[]" value="{{ $candidateAdmin->id }}" @checked(in_array((int) $candidateAdmin->id, $selectedSiteAdminIds, true))>
                                                        <span class="admin-picker-option-main">
                                                            <span class="admin-picker-option-title">{{ $title }}</span>
                                                            <span class="admin-picker-option-desc">{{ $desc }}</span>
                                                        </span>
                                                        <span class="admin-picker-option-check"></span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <div class="admin-picker-empty" data-admin-picker-empty hidden>没有匹配的管理员，请换个关键词试试。</div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </section>

                        <section class="site-module">
                            <div class="site-module-header">
                                <span class="site-module-accent"></span>
                                <div class="site-module-title">域名绑定</div>
                            </div>
                            <div class="site-module-body">
                                <div class="domain-editor @error('domains') is-error @enderror" data-domain-editor>
                                    <div class="domain-editor-header">
                                        <span class="domain-editor-title">第一条自动识别为主域名，其他域名将作为附加域名保存。</span>
                                        <button class="button secondary" type="button" data-domain-add>新增域名</button>
                                    </div>
                                    <div class="domain-editor-list" data-domain-list>
                                        @foreach ($domainRows as $domainRow)
                                            <div class="domain-editor-row" data-domain-row>
                                                <span class="domain-editor-badge {{ $loop->first ? '' : 'is-secondary' }}" data-domain-badge>{{ $loop->first ? '主域名' : '附加域名' }}</span>
                                                <input class="field" type="text" value="{{ $domainRow }}" placeholder="如 site.test" data-domain-input>
                                                <button class="domain-editor-remove" type="button" data-domain-remove data-tooltip="删除该域名">
                                                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M2.5 4.5h11"/><path d="M6.5 2.5h3"/><path d="M5 6.5v5"/><path d="M8 6.5v5"/><path d="M11 6.5v5"/><path d="M4.5 4.5l.5 8a1 1 0 0 0 1 .9h4a1 1 0 0 0 1-.9l.5-8"/></svg>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                    <textarea class="domain-editor-hidden" id="domains" name="domains" data-domain-hidden @error('domains') aria-invalid="true" @enderror>{{ old('domains', $domains->pluck('domain')->implode("\n")) }}</textarea>
                                </div>
                                @error('domains')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </div>
                        </section>

                        <section class="site-module">
                            <div class="site-module-header">
                                <span class="site-module-accent"></span>
                                <div class="site-module-title">安护盾例外</div>
                            </div>
                            <div class="site-module-body">
                                <label class="field-group">
                                    <span class="field-label">防护模式</span>
                                    <select class="field @error('security_mode') is-error @enderror" id="security_mode" name="security_mode" @error('security_mode') aria-invalid="true" @enderror>
                                        <option value="observe" @selected(($securityMode ?? 'standard') === 'observe')>观察模式：只记录，不拦截</option>
                                        <option value="standard" @selected(($securityMode ?? 'standard') === 'standard')>标准模式：常规防护</option>
                                        <option value="strict" @selected(($securityMode ?? 'standard') === 'strict')>严格模式：收紧频率和扫描阈值</option>
                                        <option value="custom" @selected(($securityMode ?? 'standard') === 'custom')>自定义模式：按站点单独阈值</option>
                                    </select>
                                    @error('security_mode')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </label>

                                <div class="field-group">
                                    <span class="field-label">后台入口路径</span>
                                    <input class="field @error('admin_entry_path') is-error @enderror" id="admin_entry_path" type="text" name="admin_entry_path" value="{{ $adminEntryPath }}" autocomplete="off" @error('admin_entry_path') aria-invalid="true" @enderror>
                                    @error('admin_entry_path')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="field-group">
                                    <span class="field-label">自定义普通页面频率阈值</span>
                                    <input class="field @error('security_custom_rate_limit_max_requests') is-error @enderror" id="security_custom_rate_limit_max_requests" type="number" name="security_custom_rate_limit_max_requests" min="1" max="10000" step="1" value="{{ $securityCustomRateLimitMaxRequests ?? '' }}" @error('security_custom_rate_limit_max_requests') aria-invalid="true" @enderror>
                                    <span class="field-note">仅在自定义模式下生效，表示同一来源在窗口期内允许的普通页面请求次数。</span>
                                    @error('security_custom_rate_limit_max_requests')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="field-group">
                                    <span class="field-label">自定义敏感页面频率阈值</span>
                                    <input class="field @error('security_custom_rate_limit_sensitive_max_requests') is-error @enderror" id="security_custom_rate_limit_sensitive_max_requests" type="number" name="security_custom_rate_limit_sensitive_max_requests" min="1" max="10000" step="1" value="{{ $securityCustomRateLimitSensitiveMaxRequests ?? '' }}" @error('security_custom_rate_limit_sensitive_max_requests') aria-invalid="true" @enderror>
                                    <span class="field-note">仅在自定义模式下生效，不能高于普通页面频率阈值。</span>
                                    @error('security_custom_rate_limit_sensitive_max_requests')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="field-group">
                                    <span class="field-label">自定义扫描试探阈值</span>
                                    <input class="field @error('security_custom_scan_probe_threshold') is-error @enderror" id="security_custom_scan_probe_threshold" type="number" name="security_custom_scan_probe_threshold" min="1" max="100" step="1" value="{{ $securityCustomScanProbeThreshold ?? '' }}" @error('security_custom_scan_probe_threshold') aria-invalid="true" @enderror>
                                    <span class="field-note">仅在自定义模式下生效，达到该次数后升级为扫描试探超限。</span>
                                    @error('security_custom_scan_probe_threshold')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="field-group">
                                    <span class="field-label">IP 白名单</span>
                                    <textarea class="field textarea @error('security_ip_allowlist') is-error @enderror" id="security_ip_allowlist" name="security_ip_allowlist" rows="4" @error('security_ip_allowlist') aria-invalid="true" @enderror>{{ $securityIpAllowlist }}</textarea>
                                    <span class="field-note">每行一个 IP 或 IPv4 CIDR。命中后当前站点跳过安护盾规则，平台全局黑名单仍优先。</span>
                                    @error('security_ip_allowlist')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="field-group">
                                    <span class="field-label">IP 黑名单</span>
                                    <textarea class="field textarea @error('security_ip_blocklist') is-error @enderror" id="security_ip_blocklist" name="security_ip_blocklist" rows="4" @error('security_ip_blocklist') aria-invalid="true" @enderror>{{ $securityIpBlocklist }}</textarea>
                                    <span class="field-note">每行一个 IP 或 IPv4 CIDR。命中后仅拦截当前站点，不影响其他站点。</span>
                                    @error('security_ip_blocklist')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="field-group">
                                    <span class="field-label">路径白名单</span>
                                    <textarea class="field textarea @error('security_path_allowlist') is-error @enderror" id="security_path_allowlist" name="security_path_allowlist" rows="5" @error('security_path_allowlist') aria-invalid="true" @enderror>{{ $securityPathAllowlist }}</textarea>
                                    <span class="field-note">每行一个以 `/` 开头的站内路径。命中后会跳过安护盾规则，适合可信回调、联调入口或历史兼容路径。</span>
                                    @error('security_path_allowlist')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="field-group">
                                    <span class="field-label">规则例外</span>
                                    <textarea class="field textarea @error('security_rule_exceptions') is-error @enderror" id="security_rule_exceptions" name="security_rule_exceptions" rows="5" @error('security_rule_exceptions') aria-invalid="true" @enderror>{{ $securityRuleExceptions }}</textarea>
                                    <span class="field-note">每行一个规则码：`bad_path`、`sql_injection`、`xss`、`path_traversal`、`bad_upload`、`rate_limit`、`probe_abuse`、`ip_blocklist`、`bad_client`、`bad_method`、`bad_payload`。</span>
                                    @error('security_rule_exceptions')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </section>

                        <section class="site-module">
                            <div class="site-module-header">
                                <span class="site-module-accent"></span>
                                <div class="site-module-title">SEO 设置</div>
                            </div>
                            <div class="site-module-body">
                                <div class="field-group">
                                    <span class="field-label">SEO 标题</span>
                                    <input class="field @error('seo_title') is-error @enderror" id="seo_title" type="text" name="seo_title" value="{{ old('seo_title', $site->seo_title) }}" @error('seo_title') aria-invalid="true" @enderror>
                                    @error('seo_title')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="field-group">
                                    <span class="field-label">SEO 关键词</span>
                                    <input class="field @error('seo_keywords') is-error @enderror" id="seo_keywords" type="text" name="seo_keywords" value="{{ old('seo_keywords', $site->seo_keywords) }}" @error('seo_keywords') aria-invalid="true" @enderror>
                                    @error('seo_keywords')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="field-group">
                                    <span class="field-label">SEO 描述</span>
                                    <textarea class="field textarea @error('seo_description') is-error @enderror" id="seo_description" name="seo_description" @error('seo_description') aria-invalid="true" @enderror>{{ old('seo_description', $site->seo_description) }}</textarea>
                                    @error('seo_description')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </section>

                        <section class="site-module">
                            <div class="site-module-header">
                                <span class="site-module-accent"></span>
                                <div class="site-module-title">备注内容</div>
                            </div>
                            <div class="site-module-body">
                                <div class="field-group">
                                    <textarea class="field textarea site-remark-textarea site-remark-rich-editor @error('remark') is-error @enderror" id="remark" name="remark" @error('remark') aria-invalid="true" @enderror>{{ old('remark', $site->remark) }}</textarea>
                                    <span class="field-note">用于记录站点交付说明、运维备注或服务信息，支持精简富文本格式。</span>
                                    @error('remark')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="site-column">
                        <section class="site-module status-monitor-card">
                            <div class="site-module-header">
                                <span class="site-module-accent"></span>
                                <div class="site-module-title">状态监控</div>
                            </div>
                            <div class="site-module-body">
                                <div class="status-monitor-badge {{ (string) old('status', (string) $site->status) === '0' ? 'is-offline' : '' }}">
                                    {{ (string) old('status', (string) $site->status) === '0' ? '关闭' : '开启中' }}
                                </div>
                                <div class="status-monitor-inline">
                                    <span class="status-monitor-label">站点标识</span>
                                    <span class="status-monitor-inline-value">{{ $site->site_key }}</span>
                                </div>
                                <div class="status-monitor-divider"></div>
                                <div class="status-monitor-meta">
                                    <div class="status-monitor-row">
                                        <span class="status-monitor-label">开通时间</span>
                                        <span class="status-monitor-value">{{ $openedAtValue ?: '未设置' }}</span>
                                    </div>
                                    <div class="status-monitor-row">
                                        <span class="status-monitor-label">到期时间</span>
                                        <span class="status-monitor-value {{ $expiresExpired ? 'is-danger' : ($expiresSoon ? 'is-warning' : '') }}">{{ $expiresAtValue ?: '未设置' }}</span>
                                    </div>
                                    <div class="status-monitor-row">
                                        <span class="status-monitor-label">资源库附件</span>
                                        <span class="status-monitor-value">{{ $managedAttachmentCount }} 个文件 · {{ $managedAttachmentBytes }}</span>
                                    </div>
                                    @if ($hasLegacyAttachments)
                                        <div class="status-monitor-row">
                                            <span class="status-monitor-label">旧附件占用</span>
                                            <span class="status-monitor-value">{{ $legacyAttachmentCount }} 个文件 · {{ $legacyAttachmentBytes }}</span>
                                        </div>
                                    @endif
                                    <div class="status-monitor-row">
                                        <span class="status-monitor-label">总资源占用</span>
                                        <span class="status-monitor-value">{{ $totalAttachmentBytes }} / {{ $attachmentLimitLabel }}</span>
                                    </div>
                                    @if ($hasLegacyAttachments || $hasLegacyAttachmentDirectory)
                                        <div class="status-monitor-row status-monitor-row-actions">
                                            <span class="status-monitor-label">
                                                旧附件统计
                                                @if ($legacyAttachmentScannedAt)
                                                    · {{ $legacyAttachmentScannedAt->format('Y-m-d H:i') }}
                                                @endif
                                            </span>
                                            <button class="button secondary" type="submit" form="legacy-attachment-refresh-form" data-loading-text="刷新中...">刷新旧附件统计</button>
                                        </div>
                                    @endif
                                </div>
                                <div class="site-form-grid status-monitor-fields">
                                    <label class="field-group">
                                        <span class="field-label">开通时间</span>
                                        <input class="field @error('opened_at') is-error @enderror" type="date" name="opened_at" value="{{ $openedAtValue }}" min="1000-01-01" max="9999-12-31" @error('opened_at') aria-invalid="true" @enderror>
                                        @error('opened_at')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">到期时间</span>
                                        <input class="field @error('expires_at') is-error @enderror" type="date" name="expires_at" value="{{ $expiresAtValue }}" min="1000-01-01" max="9999-12-31" @error('expires_at') aria-invalid="true" @enderror>
                                        @error('expires_at')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>
                                </div>
                            </div>
                        </section>

                        <section class="site-module">
                            <div class="site-module-header">
                                <span class="site-module-accent"></span>
                                <div class="site-module-title">网站logo &amp; 图标favicon</div>
                            </div>
                            <div class="site-module-body">
                                <div class="brand-assets-row">
                                    <div class="brand-asset-card is-logo" data-media-uploader data-media-action-text="更换图片" data-media-slot="logo" data-media-site-id="{{ $site->id }}" data-media-upload-url="{{ route('admin.platform.sites.media-upload') }}">
                                        <input class="site-media-hidden-input" id="logo" type="hidden" name="logo" value="{{ old('logo', $site->logo) }}" data-media-value>
                                        <input class="site-media-file-overlay" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,.ico" data-media-file>
                                        <img alt="站点 Logo 预览" data-media-preview-image hidden>
                                        <div class="brand-asset-placeholder is-logo" data-media-preview-placeholder>
                                            <svg class="brand-asset-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M8 15h8"/><path d="M8 11h8"/><path d="M8 8h5"/></svg>
                                        </div>
                                        <div class="brand-asset-overlay"><span data-media-action-label>更换图片</span></div>
                                        <button class="brand-asset-clear" type="button" data-media-clear hidden>清除</button>
                                    </div>

                                    <div class="brand-asset-card is-icon" data-media-uploader data-media-action-text="更换图片" data-media-slot="favicon" data-media-site-id="{{ $site->id }}" data-media-upload-url="{{ route('admin.platform.sites.media-upload') }}">
                                        <input class="site-media-hidden-input" id="favicon" type="hidden" name="favicon" value="{{ old('favicon', $site->favicon) }}" data-media-value>
                                        <input class="site-media-file-overlay" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,.ico" data-media-file>
                                        <img alt="站点图标预览" data-media-preview-image hidden>
                                        <div class="brand-asset-placeholder is-icon" data-media-preview-placeholder>
                                            <svg class="brand-asset-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l2.5 2.5"/></svg>
                                        </div>
                                        <div class="brand-asset-overlay"><span data-media-action-label>更换图片</span></div>
                                        <button class="brand-asset-clear" type="button" data-media-clear hidden>清除</button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="site-module">
                            <div class="site-module-header">
                                <span class="site-module-accent"></span>
                                <div class="site-module-title">联系信息</div>
                            </div>
                            <div class="site-module-body">
                                <div class="field-group">
                                    <span class="field-label">联系电话</span>
                                    <input class="field @error('contact_phone') is-error @enderror" id="contact_phone" type="text" name="contact_phone" value="{{ old('contact_phone', $site->contact_phone) }}" @error('contact_phone') aria-invalid="true" @enderror>
                                    @error('contact_phone')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="field-group">
                                    <span class="field-label">联系邮箱</span>
                                    <input class="field @error('contact_email') is-error @enderror" id="contact_email" type="text" name="contact_email" value="{{ old('contact_email', $site->contact_email) }}" @error('contact_email') aria-invalid="true" @enderror>
                                    @error('contact_email')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="field-group">
                                    <span class="field-label">站点地址</span>
                                    <input class="field @error('address') is-error @enderror" id="address" type="text" name="address" value="{{ old('address', $site->address) }}" @error('address') aria-invalid="true" @enderror>
                                    @error('address')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </section>

                    </div>
                </div>
            </div>

            <div class="site-form-footer">
            </div>
        </form>
        @if ($hasLegacyAttachments || $hasLegacyAttachmentDirectory)
            <form id="legacy-attachment-refresh-form" method="POST" action="{{ route('admin.platform.sites.legacy-attachments.refresh', $site->id) }}">
                @csrf
            </form>
        @endif
    </section>

    <section class="site-form-card @if ($activeEditTab !== 'modules') is-hidden @endif" data-site-edit-tab-panel="modules">
        <div class="site-form-body site-modules-body">
            @include('admin.platform.sites._modules_panel', ['embeddedModuleUi' => true])
        </div>
    </section>
@endsection

@push('scripts')
    @include('admin.platform.sites._form_scripts')
    @include('admin.site._custom_select_scripts')
    <script src="{{ asset('js/platform-site-modules.js') }}" defer></script>
    <script src="{{ asset('js/platform-site-edit-tabs.js') }}" defer></script>
@endpush
