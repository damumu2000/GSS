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
    <link rel="stylesheet" href="/css/site-settings.css">
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
        <form id="site-setting-form" method="POST" action="{{ route('admin.settings.update') }}" novalidate data-validation-errors='@json($errors->all())' data-media-upload-url="{{ route('admin.settings.media-upload') }}">
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
    <script src="/js/site-settings.js"></script>
@endpush
