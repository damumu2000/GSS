@extends('layouts.admin')

@section('title', '模板管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 模板管理')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/site-themes-index.css') }}">
@endpush

@section('content')
    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">模板管理</h2>
            <div class="theme-risk-notice" role="note" aria-label="模板管理风险提示">
                <span class="theme-risk-notice-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 8v5"/><path d="M12 16.5h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.72 3h16.92a2 2 0 0 0 1.72-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                </span>
                <span class="theme-risk-notice-text">此功能涉及代码和设计，仅限专业前端开发人员操作，操作前请详细阅读模版帮助文档。</span>
            </div>
        </div>
        <div class="topbar-right">
            <a class="button theme-preview-button" href="{{ route('site.home', ['site' => $currentSite->site_key]) }}" target="_blank">{{ $activeTheme === '' ? '查看前台状态' : '预览前台' }}</a>
        </div>
    </section>

    @if ($errors->has('theme'))
        <section class="theme-callout is-error">
            <div class="theme-callout-title">暂时无法进入模板源码编辑</div>
            <div class="theme-callout-text">{{ $errors->first('theme') }}</div>
        </section>
    @endif

    @if ($themes->isNotEmpty() && $activeTheme === '')
        <section class="theme-callout is-warning">
            <div class="theme-callout-title">当前站点尚未启用主题</div>
            <div class="theme-callout-text">已经绑定的主题会显示在下方，但在正式启用之前，前台会继续停留在未绑定主题提示页。</div>
        </section>
    @endif

    @if ($themes->isEmpty())
        <section class="empty-state">
            <h3 class="empty-state-title">暂无可用主题</h3>
            <div class="empty-state-desc">当前站点还没有绑定主题，请先到平台站点管理中绑定主题。</div>
        </section>
    @else
        @php
            $renderThemeCard = function ($theme, bool $isActive = false) {
                $description = $theme->description ?: '默认学校主题。可用于学校官网首页、栏目页、详情页和单页面展示。';
                ob_start();
        @endphp
                <article class="theme-card">
                    <div class="theme-cover">
                        @if (! empty($theme->cover_image))
                            <img src="{{ $theme->cover_image }}" alt="{{ $theme->name }} 封面图">
                        @else
                            <div class="theme-cover-placeholder">{{ $theme->name }}</div>
                        @endif
                    </div>

                    <div class="theme-card-header">
                        <span class="theme-card-accent theme-fixed-accent"></span>
                        <div class="theme-card-title">{{ $isActive ? '已启用主题' : '主题库' }}</div>
                        @if ($isActive)
                            <span class="theme-status-dot" aria-hidden="true"></span>
                        @endif
                    </div>

                    <div class="theme-body">
                        <div class="theme-name-row">
                            <h3 class="theme-name">{{ $theme->name }}</h3>
                            @if($theme->version)
                                <span class="theme-version">v{{ $theme->version }}</span>
                            @endif
                        </div>

                        <div class="theme-meta">
                            <div class="theme-meta-row">
                                <span class="theme-code">主题代码：{{ $theme->code }}</span>
                                @if ($isActive)
                                    <span class="badge theme-enabled-badge">已启用</span>
                                @endif
                                <span class="theme-stat-badge{{ empty($theme->has_templates) ? ' is-muted' : '' }}">
                                    {{ empty($theme->has_templates) ? '暂无模板文件' : '模板 '.(int) $theme->template_count.' 个' }}
                                </span>
                            </div>
                        </div>

                        <div class="theme-description">{{ $description }}</div>
                        <div class="theme-actions">
                            @if ($isActive)
                                <a class="button theme-action-link theme-editor-button" href="{{ route('admin.themes.editor') }}">编辑模板源码</a>
                                <a class="button secondary theme-action-link" href="{{ route('admin.themes.editor.template-create-form') }}">新增模板</a>
                            @else
                                <form method="POST" action="{{ route('admin.themes.update') }}">
                                    @csrf
                                    <input type="hidden" name="theme_code" value="{{ $theme->code }}">
                                    <button class="button secondary theme-action-link theme-enable-button" type="submit">启用主题</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
        @php
                return ob_get_clean();
            };
        @endphp

        @if ($activeThemeItem)
            <section class="theme-section">
                <div class="theme-section-head">
                    <div>
                        <h3 class="theme-section-title">已启用主题</h3>
                    </div>
                </div>
                <div class="theme-gallery is-active">
                    {!! $renderThemeCard($activeThemeItem, true) !!}
                </div>
            </section>
        @endif

        <section class="theme-section">
            <div class="theme-section-head">
                <div>
                    <h3 class="theme-section-title">主题库</h3>
                </div>
            </div>

            @if ($libraryThemes->isEmpty())
                <section class="empty-state">
                    <h3 class="empty-state-title">暂无其他可切换主题</h3>
                    <div class="empty-state-desc">当前站点目前只绑定了正在使用的主题。</div>
                </section>
            @else
                <div class="theme-gallery">
                    @foreach ($libraryThemes as $theme)
                        {!! $renderThemeCard($theme, false) !!}
                    @endforeach
                </div>
            @endif
        </section>
    @endif
@endsection
