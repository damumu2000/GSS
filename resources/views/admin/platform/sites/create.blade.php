@extends('layouts.admin')

@section('title', '新增站点 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 站点管理 / 新增站点')

@php
    $openedAtValue = old('opened_at', now()->format('Y-m-d'));
    $expiresAtValue = old('expires_at', '');
    $selectedSiteAdminIds = collect($selectedSiteAdminIds ?? [])->map(fn ($id) => (int) $id)->all();
    $domainRows = collect(preg_split('/\r\n|\r|\n/', (string) old('domains', ''), -1, PREG_SPLIT_NO_EMPTY))
        ->map(fn ($domain) => trim($domain))
        ->filter()
        ->values();

    if ($domainRows->isEmpty()) {
        $domainRows = collect(['']);
    }
@endphp

@push('styles')
    @include('admin.platform.sites._form_styles')
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">新增站点</h2>
            <div class="page-header-desc">创建新的学校站点，并配置绑定域名、站点管理员和模板数量上限。</div>
        </div>
        <div class="topbar-right">
            <a class="button secondary" href="{{ route('admin.platform.sites.index') }}">返回站点管理</a>
            <button class="button" type="submit" form="site-create-form" data-loading-text="创建中...">创建站点</button>
        </div>
    </section>

    <section class="site-form-card">
        <form
            id="site-create-form"
            method="POST"
            action="{{ route('admin.platform.sites.store') }}"
            data-platform-site-form
            data-validation-errors='@json($errors->all())'
        >
            @csrf

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
                                        <input class="field @error('name') is-error @enderror" id="name" type="text" name="name" value="{{ old('name') }}" @error('name') aria-invalid="true" @enderror>
                                        @error('name')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">站点标识（增加后无法修改）</span>
                                        <input class="field @error('site_key') is-error @enderror" id="site_key" type="text" name="site_key" value="{{ old('site_key') }}" @error('site_key') aria-invalid="true" @enderror>
                                        @error('site_key')
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
                                        <input class="field @error('template_limit') is-error @enderror" id="template_limit" type="number" name="template_limit" min="1" max="50" step="1" value="{{ old('template_limit', 1) }}" @error('template_limit') aria-invalid="true" @enderror>
                                        @error('template_limit')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">站点状态</span>
                                        <div class="custom-select @error('status') is-error @enderror" data-custom-select>
                                            <select class="custom-select-native" id="status" name="status" data-select-native @error('status') aria-invalid="true" @enderror>
                                                <option value="1" @selected((string) old('status', '1') === '1')>开启</option>
                                                <option value="0" @selected((string) old('status') === '0')>关闭</option>
                                            </select>
                                            <button class="custom-select-trigger" type="button" data-select-trigger aria-expanded="false">
                                                <span data-select-label>开启</span>
                                            </button>
                                            <div class="custom-select-panel">
                                                <button class="custom-select-option @if ((string) old('status', '1') === '1') is-active @endif" type="button" data-select-option data-value="1">
                                                    <span>开启</span>
                                                    <svg class="custom-select-check" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5 6.5 11.5 12.5 4.5"/></svg>
                                                </button>
                                                <button class="custom-select-option @if ((string) old('status') === '0') is-active @endif" type="button" data-select-option data-value="0">
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
                                    <textarea class="domain-editor-hidden" id="domains-bottom" name="domains" data-domain-hidden @error('domains') aria-invalid="true" @enderror>{{ old('domains') }}</textarea>
                                </div>
                                @error('domains')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
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
                                    <input class="field @error('seo_title') is-error @enderror" id="seo_title" type="text" name="seo_title" value="{{ old('seo_title') }}" @error('seo_title') aria-invalid="true" @enderror>
                                    @error('seo_title')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="field-group">
                                    <span class="field-label">SEO 关键词</span>
                                    <input class="field @error('seo_keywords') is-error @enderror" id="seo_keywords" type="text" name="seo_keywords" value="{{ old('seo_keywords') }}" @error('seo_keywords') aria-invalid="true" @enderror>
                                    @error('seo_keywords')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="field-group">
                                    <span class="field-label">SEO 描述</span>
                                    <textarea class="field textarea @error('seo_description') is-error @enderror" id="seo_description" name="seo_description" @error('seo_description') aria-invalid="true" @enderror>{{ old('seo_description') }}</textarea>
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
                                    <textarea class="field textarea site-remark-textarea site-remark-rich-editor @error('remark') is-error @enderror" id="remark" name="remark" @error('remark') aria-invalid="true" @enderror>{{ old('remark') }}</textarea>
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
                                <div class="status-monitor-badge {{ (string) old('status', '1') === '0' ? 'is-offline' : '' }}">
                                    {{ (string) old('status', '1') === '0' ? '关闭' : '开启中' }}
                                </div>
                                <div class="status-monitor-meta">
                                    <div class="status-monitor-row">
                                        <span class="status-monitor-label">开通时间</span>
                                        <span class="status-monitor-value">{{ $openedAtValue ?: '未设置' }}</span>
                                    </div>
                                    <div class="status-monitor-row">
                                        <span class="status-monitor-label">到期时间</span>
                                        <span class="status-monitor-value">{{ $expiresAtValue ?: '未设置' }}</span>
                                    </div>
                                </div>
                                <div class="site-form-grid status-monitor-fields">
                                    <label class="field-group">
                                        <span class="field-label">开通时间</span>
                                        <input class="field @error('opened_at') is-error @enderror" type="date" name="opened_at" value="{{ $openedAtValue }}" @error('opened_at') aria-invalid="true" @enderror>
                                        @error('opened_at')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">到期时间</span>
                                        <input class="field @error('expires_at') is-error @enderror" type="date" name="expires_at" value="{{ $expiresAtValue }}" @error('expires_at') aria-invalid="true" @enderror>
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
                                        <div class="brand-asset-card is-logo" data-media-uploader data-media-action-text="更换图片" data-media-slot="logo" data-media-upload-url="{{ route('admin.platform.sites.media-upload') }}">
                                            <input class="site-media-hidden-input" id="logo" type="hidden" name="logo" value="{{ old('logo') }}" data-media-value>
                                            <input class="site-media-file-overlay" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,.ico" data-media-file>
                                            <img alt="站点 Logo 预览" data-media-preview-image hidden>
                                        <div class="brand-asset-placeholder is-logo" data-media-preview-placeholder>
                                                <svg class="brand-asset-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M8 15h8"/><path d="M8 11h8"/><path d="M8 8h5"/></svg>
                                            </div>
                                            <div class="brand-asset-overlay"><span data-media-action-label>更换图片</span></div>
                                            <button class="brand-asset-clear" type="button" data-media-clear hidden>清除</button>
                                        </div>

                                        <div class="brand-asset-card is-icon" data-media-uploader data-media-action-text="更换图片" data-media-slot="favicon" data-media-upload-url="{{ route('admin.platform.sites.media-upload') }}">
                                            <input class="site-media-hidden-input" id="favicon" type="hidden" name="favicon" value="{{ old('favicon') }}" data-media-value>
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
                                    <input class="field @error('contact_phone') is-error @enderror" id="contact_phone" type="text" name="contact_phone" value="{{ old('contact_phone') }}" @error('contact_phone') aria-invalid="true" @enderror>
                                    @error('contact_phone')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="field-group">
                                    <span class="field-label">联系邮箱</span>
                                    <input class="field @error('contact_email') is-error @enderror" id="contact_email" type="text" name="contact_email" value="{{ old('contact_email') }}" @error('contact_email') aria-invalid="true" @enderror>
                                    @error('contact_email')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="field-group">
                                    <span class="field-label">站点地址</span>
                                    <input class="field @error('address') is-error @enderror" id="address" type="text" name="address" value="{{ old('address') }}" @error('address') aria-invalid="true" @enderror>
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
                <div class="field-note">创建成功后，站点将立即出现在平台列表中，并可进入该站点继续完善内容与模板。</div>
                <a class="button secondary" href="{{ route('admin.platform.sites.index') }}">取消</a>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
    @include('admin.platform.sites._form_scripts')
@endpush
