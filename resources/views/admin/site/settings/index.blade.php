@extends('layouts.admin')

@section('title', '站点设置 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 站点设置')

@php
    $attachmentStorageLimitMb = (int) ($settings['attachment.storage_limit_mb'] ?? 0);
    $attachmentStorageLimitLabel = $attachmentStorageLimitMb > 0
        ? number_format($attachmentStorageLimitMb) . ' MB'
        : '不限';
@endphp

@push('styles')
    @include('admin.platform.sites._form_styles')
    <style>
        .setting-layout-grid {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
            gap: 20px;
            align-items: start;
        }

        .setting-column {
            display: grid;
            gap: 18px;
            min-width: 0;
        }

        .site-form-card .form-error {
            display: none;
        }

        .site-overview-card .site-module-body {
            gap: 14px;
            padding: 16px;
        }

        .site-overview-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 999px;
            background: var(--tag-bg);
            color: var(--tag-text);
            font-size: 14px;
            font-weight: 700;
        }

        .site-overview-divider {
            border-top: 1px dashed #f0f0f0;
        }

        .site-overview-meta {
            display: grid;
            gap: 12px;
        }

        .site-overview-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .site-overview-label {
            color: #8c8c8c;
            font-size: 13px;
        }

        .site-overview-value {
            color: #262626;
            font-size: 13px;
            font-weight: 600;
            text-align: right;
        }

        .domain-readonly-panel {
            padding: 16px;
            border-radius: 8px;
            background: #fafafa;
            border: 1px solid #f0f0f0;
            display: grid;
            gap: 12px;
        }

        .domain-readonly-desc {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.8;
        }

        .domain-readonly-list {
            display: grid;
            gap: 10px;
        }

        .domain-readonly-item {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 40px;
            padding: 0 12px;
            border-radius: 8px;
            background: #ffffff;
            border: 1px solid #f3f4f6;
        }

        .domain-readonly-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: var(--primary-soft);
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .domain-readonly-icon svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .domain-readonly-value {
            color: #262626;
            font-size: 13px;
            font-weight: 600;
            word-break: break-all;
        }

        .site-setting-footer-note {
            color: #bfbfbf;
            font-size: 12px;
            line-height: 1.8;
        }

        .setting-toggle-field {
            display: grid;
            gap: 10px;
            grid-column: 1 / -1;
        }

        .setting-toggle-stack {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            align-items: stretch;
        }

        .setting-toggle-row {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            align-items: center;
            gap: 16px;
            padding: 14px 16px;
            border: 1px solid #eef1f5;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
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

        .setting-toggle-switch:hover .setting-toggle-track {
            border-color: rgba(0, 71, 171, 0.22);
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

        @media (max-width: 1024px) {
            .setting-layout-grid {
                grid-template-columns: 1fr;
            }

            .setting-toggle-stack {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">站点设置</h2>
            <div class="page-header-desc">维护站点基础信息、备案信息、联系方式和搜索优化配置。</div>
        </div>
        <div class="topbar-right">
            <a class="button secondary" href="{{ route('site.home', ['site' => $currentSite->site_key]) }}" target="_blank">预览前台</a>
            <button class="button" type="submit" form="site-setting-form" data-loading-text="保存中...">保存站点设置</button>
        </div>
    </section>

    <section class="site-form-card">
        <form id="site-setting-form" method="POST" action="{{ route('admin.settings.update') }}" novalidate>
            @csrf
            <input id="logo" type="hidden" name="logo" value="{{ old('logo', $currentSite->logo) }}">
            <input id="favicon" type="hidden" name="favicon" value="{{ old('favicon', $currentSite->favicon) }}">

            <div class="site-form-body">
                <div class="setting-layout-grid">
                    <div class="setting-column">
                        <section class="site-module">
                            <div class="site-module-header">
                                <span class="site-module-accent"></span>
                                <div class="site-module-title">基础配置</div>
                            </div>
                            <div class="site-module-body">
                                <div class="site-form-grid">
                                    <label class="field-group">
                                        <span class="field-label">站点名称</span>
                                        <input class="field @error('name') is-error @enderror" id="name" type="text" name="name" value="{{ old('name', $currentSite->name) }}" @error('name') aria-invalid="true" @enderror>
                                        @error('name')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">备案号</span>
                                        <input class="field @error('filing_number') is-error @enderror" id="filing_number" type="text" name="filing_number" value="{{ old('filing_number', $settings['site.filing_number'] ?? '') }}" @error('filing_number') aria-invalid="true" @enderror>
                                        @error('filing_number')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    <div class="field-group setting-toggle-field">
                                        <span class="field-label">内容与附件控制</span>
                                        <div class="setting-toggle-stack">
                                            <div class="setting-toggle-row">
                                                <input type="hidden" name="article_requires_review" value="0">
                                                <div class="setting-toggle-control">
                                                    <input class="setting-toggle-input" id="article_requires_review" type="checkbox" name="article_requires_review" value="1" @checked(old('article_requires_review', ($settings['content.article_requires_review'] ?? '0') === '1'))>
                                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                                </div>
                                                <div class="setting-toggle-copy" aria-hidden="true">
                                                    <span class="setting-toggle-text">文章审核功能</span>
                                                    <span class="setting-toggle-state" data-toggle-state-for="article_requires_review">{{ old('article_requires_review', ($settings['content.article_requires_review'] ?? '0') === '1') ? '已开启' : '未开启' }}</span>
                                                    <span class="setting-toggle-desc">开启文章审核后，发布的文章必须通过审核前台才能正常显示</span>
                                                </div>
                                            </div>
                                            @error('article_requires_review')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror

                                            <div class="setting-toggle-row">
                                                <input type="hidden" name="article_share_enabled" value="0">
                                                <div class="setting-toggle-control">
                                                    <input class="setting-toggle-input" id="article_share_enabled" type="checkbox" name="article_share_enabled" value="1" @checked(old('article_share_enabled', ($settings['content.article_share_enabled'] ?? '0') === '1'))>
                                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                                </div>
                                                <div class="setting-toggle-copy" aria-hidden="true">
                                                    <span class="setting-toggle-text">文章共享</span>
                                                    <span class="setting-toggle-state" data-toggle-state-for="article_share_enabled">{{ old('article_share_enabled', ($settings['content.article_share_enabled'] ?? '0') === '1') ? '已开启' : '未开启' }}</span>
                                                    <span class="setting-toggle-desc">开启后，操作员可查看其权限栏目下的全部文章；关闭后，仅可查看其权限栏目下自己发布的文章。</span>
                                                </div>
                                            </div>
                                            @error('article_share_enabled')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror

                                            <div class="setting-toggle-row">
                                                <input type="hidden" name="attachment_share_enabled" value="0">
                                                <div class="setting-toggle-control">
                                                    <input class="setting-toggle-input" id="attachment_share_enabled" type="checkbox" name="attachment_share_enabled" value="1" @checked(old('attachment_share_enabled', ($settings['attachment.share_enabled'] ?? '0') === '1'))>
                                                    <span class="setting-toggle-track" aria-hidden="true"></span>
                                                </div>
                                                <div class="setting-toggle-copy" aria-hidden="true">
                                                    <span class="setting-toggle-text">附件共享</span>
                                                    <span class="setting-toggle-state" data-toggle-state-for="attachment_share_enabled">{{ old('attachment_share_enabled', ($settings['attachment.share_enabled'] ?? '0') === '1') ? '已开启' : '未开启' }}</span>
                                                    <span class="setting-toggle-desc">关闭后，普通操作员只能查看和使用自己上传的附件；站点管理员和具备附件管理权限的角色不受影响。</span>
                                                </div>
                                            </div>
                                            @error('attachment_share_enabled')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </div>
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
                                <label class="field-group">
                                    <span class="field-label">联系电话</span>
                                    <input class="field @error('contact_phone') is-error @enderror" id="contact_phone" type="text" name="contact_phone" value="{{ old('contact_phone', $currentSite->contact_phone) }}" @error('contact_phone') aria-invalid="true" @enderror>
                                    @error('contact_phone')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="field-group">
                                    <span class="field-label">联系邮箱</span>
                                    <input class="field @error('contact_email') is-error @enderror" id="contact_email" type="text" name="contact_email" value="{{ old('contact_email', $currentSite->contact_email) }}" @error('contact_email') aria-invalid="true" @enderror>
                                    @error('contact_email')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="field-group">
                                    <span class="field-label">学校地址</span>
                                    <input class="field @error('address') is-error @enderror" id="address" type="text" name="address" value="{{ old('address', $currentSite->address) }}" @error('address') aria-invalid="true" @enderror>
                                    @error('address')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </label>
                            </div>
                        </section>

                        <section class="site-module">
                            <div class="site-module-header">
                                <span class="site-module-accent"></span>
                                <div class="site-module-title">SEO 设置</div>
                            </div>
                            <div class="site-module-body">
                                <label class="field-group">
                                    <span class="field-label">SEO 标题</span>
                                    <input class="field @error('seo_title') is-error @enderror" id="seo_title" type="text" name="seo_title" value="{{ old('seo_title', $currentSite->seo_title) }}" @error('seo_title') aria-invalid="true" @enderror>
                                    @error('seo_title')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="field-group">
                                    <span class="field-label">SEO 关键词</span>
                                    <input class="field @error('seo_keywords') is-error @enderror" id="seo_keywords" type="text" name="seo_keywords" value="{{ old('seo_keywords', $currentSite->seo_keywords) }}" @error('seo_keywords') aria-invalid="true" @enderror>
                                    @error('seo_keywords')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="field-group">
                                    <span class="field-label">SEO 描述</span>
                                    <textarea class="field textarea @error('seo_description') is-error @enderror" id="seo_description" name="seo_description" @error('seo_description') aria-invalid="true" @enderror>{{ old('seo_description', $currentSite->seo_description) }}</textarea>
                                    @error('seo_description')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </label>
                            </div>
                        </section>

                    </div>

                    <div class="setting-column">
                        <section class="site-module site-overview-card">
                            <div class="site-module-header">
                                <span class="site-module-accent"></span>
                                <div class="site-module-title">站点概览</div>
                            </div>
                            <div class="site-module-body">
                                <div class="site-overview-badge">当前站点运行中</div>
                                <div class="site-overview-divider"></div>
                                <div class="site-overview-meta">
                                    <div class="site-overview-row">
                                        <span class="site-overview-label">当前站点</span>
                                        <span class="site-overview-value">{{ $currentSite->name }}</span>
                                    </div>
                                    <div class="site-overview-row">
                                        <span class="site-overview-label">到期时间</span>
                                        <span class="site-overview-value">
                                            {{ $currentSite->expires_at ? \Illuminate\Support\Carbon::parse($currentSite->expires_at)->format('Y-m-d') : '未设置' }}
                                        </span>
                                    </div>
                                    <div class="site-overview-row">
                                        <span class="site-overview-label">站点标识</span>
                                        <span class="site-overview-value">{{ $currentSite->site_key }}</span>
                                    </div>
                                    <div class="site-overview-row">
                                        <span class="site-overview-label">已绑定域名</span>
                                        <span class="site-overview-value">{{ $domains->count() }}</span>
                                    </div>
                                    <div class="site-overview-row">
                                        <span class="site-overview-label">资源库总容量上限</span>
                                        <span class="site-overview-value">{{ $attachmentStorageLimitLabel }}</span>
                                    </div>
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
                                    <div class="brand-asset-card is-logo" data-media-uploader data-media-action-text="更换图片" data-media-slot="logo" data-media-site-id="{{ $currentSite->id }}">
                                        <input class="site-media-hidden-input" type="hidden" value="{{ old('logo', $currentSite->logo) }}" data-media-value data-media-target="#logo">
                                        <input class="site-media-file-overlay" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,.ico" data-media-file>
                                        <img alt="站点 Logo 预览" data-media-preview-image hidden>
                                        <div class="brand-asset-placeholder is-logo" data-media-preview-placeholder>
                                            <svg class="brand-asset-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M8 15h8"/><path d="M8 11h8"/><path d="M8 8h5"/></svg>
                                        </div>
                                        <div class="brand-asset-overlay"><span data-media-action-label>更换图片</span></div>
                                        <button class="brand-asset-clear" type="button" data-media-clear hidden>清除</button>
                                    </div>

                                    <div class="brand-asset-card is-icon" data-media-uploader data-media-action-text="更换图片" data-media-slot="favicon" data-media-site-id="{{ $currentSite->id }}">
                                        <input class="site-media-hidden-input" type="hidden" value="{{ old('favicon', $currentSite->favicon) }}" data-media-value data-media-target="#favicon">
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
                                <div class="site-module-title">绑定域名</div>
                            </div>
                            <div class="site-module-body">
                                <div class="domain-readonly-panel">
                                    <div class="domain-readonly-desc">这里仅展示当前站点已绑定域名，如需修改，请联系你的官方专属服务人员。</div>

                                    @if ($domains->isEmpty())
                                        <div class="field-note">当前站点暂未绑定域名。</div>
                                    @else
                                        <div class="domain-readonly-list">
                                            @foreach ($domains as $domain)
                                                <div class="domain-readonly-item">
                                                    <span class="domain-readonly-icon">
                                                        <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M2 8c0-2.8 2.2-5 5-5h2c2.8 0 5 2.2 5 5s-2.2 5-5 5H7c-2.8 0-5-2.2-5-5Z"/><path d="M5.5 8h5"/></svg>
                                                    </span>
                                                    <span class="domain-readonly-value">{{ $domain->domain }}</span>
                                                    @if ($domain->is_primary)
                                                        <span class="badge-soft">主域名</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            <div class="site-form-footer">
                <div class="site-setting-footer-note">保存成功后，新的站点基础信息会立即同步到当前站点的前台展示和后台识别中。</div>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('site-setting-form');

            if (! form) {
                return;
            }

            const fields = {
                name: document.getElementById('name'),
                filing_number: document.getElementById('filing_number'),
                contact_phone: document.getElementById('contact_phone'),
                contact_email: document.getElementById('contact_email'),
            };

            const validators = {
                name: (value) => value.trim() !== '' ? '' : '请填写站点名称。',
                contact_phone: (value) => value === '' || /^[0-9\-+\s()#]{6,50}$/.test(value) ? '' : '联系电话格式不正确，请输入有效的电话或手机号。',
                contact_email: (value) => value === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? '' : '联系邮箱格式不正确，请重新填写。',
                filing_number: (value) => value === '' || /^[A-Za-z0-9\u4E00-\u9FA5\-\(\)（）〔〕[\]【】\/\s]+$/u.test(value) ? '' : '备案号格式不正确，请仅使用中文、字母、数字、空格及常见连接符。',
            };

            const clearFieldError = (field) => {
                if (! field) {
                    return;
                }

                field.classList.remove('is-error');
                field.removeAttribute('aria-invalid');
            };

            const setFieldError = (field) => {
                if (! field) {
                    return;
                }

                field.classList.add('is-error');
                field.setAttribute('aria-invalid', 'true');
            };

            Object.entries(fields).forEach(([key, field]) => {
                if (! field) {
                    return;
                }

                const validateCurrentField = () => {
                    const validator = validators[key];

                    if (! validator) {
                        return;
                    }

                    const message = validator(field.value.trim());

                    if (message === '') {
                        clearFieldError(field);
                    }
                };

                field.addEventListener('input', validateCurrentField);
                field.addEventListener('blur', validateCurrentField);
            });

            document.querySelectorAll('.setting-toggle-input').forEach((toggle) => {
                const state = document.querySelector(`[data-toggle-state-for="${toggle.id}"]`);

                if (! state) {
                    return;
                }

                const syncState = () => {
                    state.textContent = toggle.checked ? '已开启' : '未开启';
                };

                toggle.addEventListener('change', syncState);
                syncState();
            });

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const getMediaParts = (uploaderRoot) => {
                if (!(uploaderRoot instanceof HTMLElement)) {
                    return null;
                }

                const hiddenInput = uploaderRoot.querySelector('[data-media-value]');
                const targetSelector = hiddenInput?.getAttribute('data-media-target') ?? '';
                const actualInput = targetSelector ? document.querySelector(targetSelector) : null;
                const fileInput = uploaderRoot.querySelector('[data-media-file]');
                const uploadButton = uploaderRoot;
                const uploadLabel = uploaderRoot.querySelector('[data-media-action-label]');
                const clearButton = uploaderRoot.querySelector('[data-media-clear]');
                const image = uploaderRoot.querySelector('[data-media-preview-image]');
                const placeholder = uploaderRoot.querySelector('[data-media-preview-placeholder]');

                if (
                    !(hiddenInput instanceof HTMLInputElement)
                    || !(actualInput instanceof HTMLInputElement)
                    || !(fileInput instanceof HTMLInputElement)
                    || !(uploadButton instanceof HTMLElement)
                    || !(image instanceof HTMLImageElement)
                    || !(placeholder instanceof Element)
                ) {
                    return null;
                }

                return { hiddenInput, actualInput, fileInput, uploadButton, uploadLabel, clearButton, image, placeholder };
            };

            const syncMediaPreview = (uploaderRoot) => {
                const parts = getMediaParts(uploaderRoot);
                if (!parts) {
                    return;
                }

                const { hiddenInput, actualInput, uploadLabel, clearButton, image, placeholder } = parts;
                const value = actualInput.value.trim() || hiddenInput.value.trim();
                const hasValue = value !== '';

                hiddenInput.value = value;
                actualInput.value = value;
                image.hidden = !hasValue;
                placeholder.hidden = hasValue;
                image.style.display = hasValue ? 'block' : 'none';

                if (hasValue) {
                    image.src = value;
                } else {
                    image.removeAttribute('src');
                }

                if (clearButton instanceof HTMLButtonElement) {
                    clearButton.hidden = !hasValue;
                }

                if (uploadLabel instanceof HTMLElement) {
                    uploadLabel.textContent = uploaderRoot.dataset.mediaActionText ?? '更换图片';
                }
            };

            document.querySelectorAll('[data-media-uploader]').forEach((uploaderRoot) => {
                const parts = getMediaParts(uploaderRoot);
                if (!parts) {
                    return;
                }

                parts.image.addEventListener('error', () => {
                    parts.image.hidden = true;
                    parts.placeholder.hidden = false;
                    parts.image.style.display = 'none';
                    parts.image.removeAttribute('src');
                });

                syncMediaPreview(uploaderRoot);
            });

            document.addEventListener('click', (event) => {
                const clearButton = event.target instanceof HTMLElement ? event.target.closest('[data-media-clear]') : null;
                if (!(clearButton instanceof HTMLButtonElement)) {
                    return;
                }

                event.preventDefault();
                const uploaderRoot = clearButton.closest('[data-media-uploader]');
                const parts = getMediaParts(uploaderRoot);
                if (!parts) {
                    return;
                }

                parts.hiddenInput.value = '';
                parts.actualInput.value = '';
                parts.fileInput.value = '';
                syncMediaPreview(uploaderRoot);
            });

            document.addEventListener('change', async (event) => {
                const fileInput = event.target instanceof HTMLInputElement && event.target.matches('[data-media-file]')
                    ? event.target
                    : null;

                if (!fileInput) {
                    return;
                }

                const uploaderRoot = fileInput.closest('[data-media-uploader]');
                const parts = getMediaParts(uploaderRoot);
                if (!parts) {
                    return;
                }

                const file = fileInput.files?.[0];
                if (!file) {
                    return;
                }

                const mediaSlot = uploaderRoot.dataset.mediaSlot ?? '';
                if (mediaSlot === '') {
                    window.showMessage?.('图片上传配置缺失，请刷新页面后重试。', 'error');
                    fileInput.value = '';
                    return;
                }

                const originalText = parts.uploadLabel instanceof HTMLElement ? parts.uploadLabel.textContent : '';
                parts.uploadButton.classList.add('is-uploading');
                if (parts.uploadLabel instanceof HTMLElement) {
                    parts.uploadLabel.textContent = '上传中...';
                }

                try {
                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('slot', mediaSlot);

                    const response = await fetch('{{ route('admin.settings.media-upload') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });

                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok || !payload.url) {
                        throw new Error(payload.message || '图片上传失败');
                    }

                    parts.hiddenInput.value = payload.url;
                    parts.actualInput.value = payload.url;
                    syncMediaPreview(uploaderRoot);
                } catch (error) {
                    window.showMessage?.(error.message || '图片上传失败', 'error');
                } finally {
                    parts.uploadButton.classList.remove('is-uploading');
                    if (parts.uploadLabel instanceof HTMLElement) {
                        parts.uploadLabel.textContent = originalText;
                    }
                    fileInput.value = '';
                }
            });

            form.addEventListener('submit', (event) => {
                const messages = [];
                let firstInvalid = null;

                Object.values(fields).forEach((field) => clearFieldError(field));

                Object.entries(fields).forEach(([key, field]) => {
                    if (! field) {
                        return;
                    }

                    const validator = validators[key];
                    const value = field.value.trim();
                    const message = validator ? validator(value) : '';

                    if (message !== '') {
                        setFieldError(field);
                        messages.push(message);
                        firstInvalid = firstInvalid || field;
                    }
                });

                if (messages.length > 0) {
                    event.preventDefault();
                    showMessage([...new Set(messages)].join('，'), 'error');
                    firstInvalid?.focus();
                    firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        })();

        @if ($errors->any())
            (() => {
                const messages = @json($errors->all());

                if (Array.isArray(messages) && messages.length > 0) {
                    showMessage([...new Set(messages)].join('，'), 'error');
                }
            })();
        @endif
    </script>
@endpush
