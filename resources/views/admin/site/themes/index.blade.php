@extends('layouts.admin')

@section('title', '模板管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 模板管理')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/site-themes-index.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/site-themes-index.js') }}" defer></script>
@endpush

@section('content')
    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">模板管理</h2>
            <div class="theme-risk-notice" role="note" aria-label="模板管理风险提示">
                <span class="theme-risk-notice-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 8v5"/><path d="M12 16.5h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.72 3h16.92a2 2 0 0 0 1.72-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                </span>
                <span class="theme-risk-notice-text">本模版支持个性化定制，建议由专业UI设计师操作，不当修改可能破坏页面布局。</span>
            </div>
        </div>
        <div class="topbar-right">
            <a class="button theme-preview-button" href="{{ \App\Support\SiteFrontendUrl::homeUrl($currentSite) }}" target="_blank">预览前台</a>
            <button class="button" type="button" data-open-template-create-modal>新增站点模板</button>
        </div>
    </section>

    @if ($errors->has('template'))
        <section class="theme-callout is-error">
            <div class="theme-callout-title">当前操作未完成</div>
            <div class="theme-callout-text">{{ $errors->first('template') }}</div>
        </section>
    @endif

    @if ($siteTemplates->isEmpty())
        <section class="empty-state">
            <h3 class="empty-state-title">暂无可用模板</h3>
            <div class="empty-state-desc">请先新增站点模板，再进入模板编辑完善页面。</div>
        </section>
    @else
        @php
            $renderTemplateCard = function ($siteTemplate, bool $isActive = false, bool $isOrphan = false) {
                ob_start();
        @endphp
                <article class="theme-card{{ $isOrphan ? ' is-orphan' : '' }}">
                    <div class="theme-cover">
                        <div class="theme-cover-placeholder">{{ $siteTemplate->name }}</div>
                    </div>

                    <div class="theme-card-header">
                        <span class="theme-card-accent theme-fixed-accent"></span>
                        <div class="theme-card-title">站点模板</div>
                        @if ($isActive)
                            <span class="theme-status-dot" aria-hidden="true"></span>
                        @endif
                    </div>

                    <div class="theme-body">
                        <div class="theme-name-row">
                            <h3 class="theme-name">{{ $siteTemplate->name }}</h3>
                            @if (! $isActive)
                                @php
                                    $deleteFormId = $isOrphan ? 'theme-delete-orphan-form-'.md5((string) $siteTemplate->template_key) : 'theme-delete-form-'.$siteTemplate->id;
                                @endphp
                                <form id="{{ $deleteFormId }}" method="POST" action="{{ $isOrphan ? route('admin.themes.destroy-orphan') : route('admin.themes.destroy', $siteTemplate->id) }}">
                                    @csrf
                                    @if ($isOrphan)
                                        <input type="hidden" name="template_key" value="{{ $siteTemplate->template_key }}">
                                    @endif
                                    <button
                                        class="theme-delete-inline"
                                        type="button"
                                        data-open-template-delete-modal
                                        data-delete-form-id="{{ $deleteFormId }}"
                                        data-template-name="{{ $siteTemplate->name }}"
                                        data-template-key="{{ $siteTemplate->template_key }}"
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6"/><path d="M4 7h16"/><path d="M18 7l-1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L6 7"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                        <span>删除</span>
                                    </button>
                                </form>
                            @endif
                        </div>

                        <div class="theme-meta">
                            <div class="theme-meta-row">
                                <span class="theme-code">模板标识：{{ $siteTemplate->template_key }}</span>
                                @if ($isActive)
                                    <span class="badge theme-enabled-badge">已启用</span>
                                @elseif ($isOrphan)
                                    <span class="badge theme-enabled-badge is-orphan">异常模板</span>
                                @endif
                                <span class="theme-stat-badge{{ empty($siteTemplate->has_templates) ? ' is-muted' : '' }}">
                                    @if ($isOrphan)
                                        目录存在未入库
                                    @else
                                        {{ empty($siteTemplate->has_templates) ? '暂无模板文件' : '模板 '.(int) $siteTemplate->template_count.' 个' }}
                                    @endif
                                </span>
                            </div>
                        </div>

                        @if (! $isOrphan)
                            <div class="theme-actions">
                                @if (! $isActive)
                                    <form method="POST" action="{{ route('admin.themes.update') }}">
                                        @csrf
                                        <input type="hidden" name="site_template_id" value="{{ $siteTemplate->id }}">
                                        <button class="button secondary theme-action-link theme-enable-button" type="submit">启用模板</button>
                                    </form>
                                @endif
                                <a class="button theme-action-link theme-editor-button" href="{{ route('admin.themes.editor', ['site_template_id' => $siteTemplate->id]) }}">编辑模板</a>
                            </div>
                        @else
                            <div class="theme-actions">
                                <span class="theme-orphan-tip">异常模板仅支持删除</span>
                            </div>
                        @endif
                    </div>
                </article>
        @php
                return ob_get_clean();
            };
        @endphp

        <section class="theme-section">
            <div class="theme-section-head">
                <div>
                    <h3 class="theme-section-title">模版库</h3>
                </div>
                <div class="theme-section-meta">
                    <span class="theme-section-meta-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M7 4v6"/><path d="M17 4v6"/><rect x="4" y="7" width="16" height="13" rx="2"/></svg>
                    </span>
                    <span>当前站点共 {{ $siteTemplates->count() }} 个模板，最多可创建 {{ (int) ($currentSite->template_limit ?? 1) }} 个。</span>
                </div>
            </div>

            <div class="theme-gallery">
                @foreach ($siteTemplates as $siteTemplate)
                    {!! $renderTemplateCard($siteTemplate, (int) $siteTemplate->id === (int) ($activeTemplateItem->id ?? 0)) !!}
                @endforeach
            </div>
        </section>

        @if (!empty($orphanTemplates) && $orphanTemplates->isNotEmpty())
            <section class="theme-section">
                <div class="theme-section-head">
                    <div>
                        <h3 class="theme-section-title">异常模板</h3>
                    </div>
                    <div class="theme-section-meta">
                        <span class="theme-section-meta-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M12 8v5"/><path d="M12 16.5h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.72 3h16.92a2 2 0 0 0 1.72-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                        </span>
                        <span>磁盘目录存在但未入库，仅可删除以清理脏数据。</span>
                    </div>
                </div>
                <div class="theme-gallery">
                    @foreach ($orphanTemplates as $orphanTemplate)
                        {!! $renderTemplateCard($orphanTemplate, false, true) !!}
                    @endforeach
                </div>
            </section>
        @endif
    @endif

    <section class="theme-create-modal @if($createTemplateModalOpen) is-open @endif" data-template-create-modal>
        <div class="theme-create-modal-backdrop" data-close-template-create-modal></div>
        <div class="theme-create-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="theme-create-modal-title">
            <div class="theme-create-modal-head">
                <div>
                    <div class="theme-create-modal-title" id="theme-create-modal-title">新增站点模板</div>
                    <div class="theme-create-modal-desc">新增模版后会默认生成一个首页模板，可继续进入源码编辑完善内容。</div>
                </div>
                <button class="theme-create-modal-close" type="button" data-close-template-create-modal aria-label="关闭新增模板弹窗">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <form class="theme-create-form" method="POST" action="{{ route('admin.themes.store') }}" novalidate>
                @csrf
                <label class="field-group">
                    <span class="field-label">模版名称</span>
                    <input class="field @error('name') is-error @enderror" type="text" name="name" value="{{ old('name') }}" maxlength="100" placeholder="如 政教展示模板" autocomplete="off">
                    @error('name')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </label>
                <label class="field-group">
                    <span class="field-label">模版标识</span>
                    <input class="field @error('template_key') is-error @enderror" type="text" name="template_key" value="{{ old('template_key') }}" minlength="3" maxlength="50" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="如 gov-portal" autocomplete="off" spellcheck="false">
                    @error('template_key')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </label>
                <div class="theme-create-modal-note">提交前会校验名称、标识格式、同站点唯一性和数量上限。</div>
                <div class="theme-create-modal-actions">
                    <button class="button secondary" type="button" data-close-template-create-modal>取消</button>
                    <button class="button" type="submit">新增模板</button>
                </div>
            </form>
        </div>
    </section>

    <section class="theme-delete-modal" data-template-delete-modal>
        <div class="theme-delete-modal-backdrop" data-close-template-delete-modal></div>
        <div class="theme-delete-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="theme-delete-modal-title">
            <div class="theme-delete-modal-head">
                <div class="theme-delete-modal-title" id="theme-delete-modal-title">确认删除模板</div>
                <button class="theme-delete-modal-close" type="button" data-close-template-delete-modal aria-label="关闭删除模板弹窗">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="theme-delete-modal-body">
                <div class="theme-delete-modal-warning">
                    删除后将移除该模板目录下的源码与资源文件，操作不可恢复。
                </div>
                <div class="theme-delete-modal-target">
                    <div><strong>模板名称：</strong><span data-delete-template-name>-</span></div>
                    <div><strong>模板标识：</strong><span data-delete-template-key>-</span></div>
                </div>
                <div class="theme-delete-modal-note">
                    为避免前台渲染风险，请确认该模板不再使用、且内容已完成备份后再执行删除。
                </div>
            </div>
            <div class="theme-delete-modal-actions">
                <button class="button secondary" type="button" data-close-template-delete-modal>取消</button>
                <button class="button is-danger" type="button" data-confirm-template-delete>确认删除</button>
            </div>
        </div>
    </section>
@endsection
