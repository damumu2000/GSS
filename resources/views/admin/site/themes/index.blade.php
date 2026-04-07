@extends('layouts.admin')

@section('title', '模板管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 模板管理')

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

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .page-header-main {
            min-width: 0;
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

        .theme-section + .theme-section {
            margin-top: 28px;
        }

        .theme-section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .theme-section-title {
            margin: 0;
            color: #1f2937;
            font-size: 16px;
            line-height: 1.5;
            font-weight: 700;
        }

        .theme-section-desc {
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.7;
        }

        .theme-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 340px));
            justify-content: flex-start;
            gap: 18px;
        }

        .theme-gallery.is-active {
            grid-template-columns: minmax(260px, 340px);
        }

        .theme-card {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid #eaf0f6;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.05);
            transition: box-shadow 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
        }

        .theme-card:hover {
            border-color: #dde7f2;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
            transform: translateY(-2px);
        }

        .theme-cover {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 128px;
            padding: 18px;
            background: linear-gradient(180deg, #f8fbff 0%, #f3f7fd 100%);
            overflow: hidden;
        }

        .theme-cover::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(0, 71, 171, 0.08), transparent 42%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.18) 0%, transparent 100%);
            pointer-events: none;
        }

        .theme-cover img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .theme-cover-placeholder {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 150px;
            max-width: 100%;
            padding: 14px 18px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.96);
            color: color-mix(in srgb, var(--primary) 88%, #163a73);
            font-size: 18px;
            line-height: 1.3;
            font-weight: 700;
            text-align: center;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        }

        .theme-status-dot {
            width: 10px;
            height: 10px;
            margin-left: auto;
            border-radius: 999px;
            background: #52c41a;
            border: 2px solid rgba(255, 255, 255, 0.92);
            box-shadow: 0 0 0 0 rgba(82, 196, 26, 0.24);
            animation: theme-status-pulse 1.8s ease-out infinite;
        }

        .theme-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border-bottom: 1px solid #f2f5f8;
        }

        .theme-card-accent {
            width: 4px;
            height: 16px;
            border-radius: 999px;
            background: #4b5563;
            flex-shrink: 0;
        }

        .theme-card-title {
            color: #344054;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 700;
        }

        .theme-body {
            flex: 1;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .theme-name-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }

        .theme-name {
            margin: 0;
            color: #1f2937;
            font-size: 16px;
            line-height: 1.45;
            font-weight: 700;
        }

        .theme-version {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            background: #f5f7fb;
            color: #6b7280;
            font-size: 11px;
            line-height: 1.4;
            font-weight: 600;
        }

        .theme-meta {
            display: grid;
            gap: 8px;
        }

        .theme-meta-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.6;
        }

        .theme-code {
            color: #667085;
        }

        .theme-stat-badge {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 11px;
            border-radius: 999px;
            background: #f7f8fb;
            color: #475467;
            font-size: 12px;
            font-weight: 700;
        }

        .theme-stat-badge.is-muted {
            background: #f8fafc;
            color: #94a3b8;
        }

        .theme-description {
            color: #667085;
            font-size: 13px;
            line-height: 1.7;
            min-height: 60px;
            padding: 10px 12px;
            border-radius: 12px;
            background: linear-gradient(180deg, #fbfcfe 0%, #f7f9fc 100%);
            border: 1px solid #f1f4f8;
        }

        .theme-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: auto;
            padding-top: 14px;
            border-top: 1px solid #f2f5f8;
        }

        .theme-action-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 13px;
            line-height: 1;
            font-weight: 600;
            text-decoration: none;
        }

        .theme-body .badge {
            background: #f3f4f6;
            color: #4b5563;
        }

        .theme-preview-button {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
        }

        .theme-preview-button:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .theme-editor-button {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
        }

        .theme-editor-button:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            color: #ffffff;
        }

        .theme-enable-button {
            min-width: 108px;
        }

        .theme-fixed-accent {
            background: #4b5563;
        }

        .theme-fixed-link {
            color: #4b5563;
        }

        .theme-fixed-link:hover {
            color: #262626;
        }

        .theme-enabled-badge {
            background: #eefaf1;
            color: #15803d;
        }

        .empty-state {
            padding: 48px 32px;
            border: 1px dashed #e8e8e8;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.92);
            text-align: center;
        }

        .empty-state-title {
            margin: 0;
            color: #262626;
            font-size: 16px;
            font-weight: 700;
        }

        .empty-state-desc {
            margin-top: 8px;
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.7;
        }

        .theme-callout {
            margin-bottom: 18px;
            padding: 16px 18px;
            border-radius: 12px;
            border: 1px solid #e8edf3;
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        }

        .theme-callout.is-warning {
            border-color: #fde68a;
            background: #fffbeb;
        }

        .theme-callout.is-error {
            border-color: #fecaca;
            background: #fef2f2;
        }

        .theme-callout-title {
            color: #262626;
            font-size: 14px;
            line-height: 1.6;
            font-weight: 700;
        }

        .theme-callout-text {
            margin-top: 6px;
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.7;
        }

        @keyframes theme-status-pulse {
            0% { box-shadow: 0 0 0 0 rgba(82, 196, 26, 0.28); }
            70% { box-shadow: 0 0 0 8px rgba(82, 196, 26, 0); }
            100% { box-shadow: 0 0 0 0 rgba(82, 196, 26, 0); }
        }

        @media (max-width: 1440px) {
            .theme-gallery { grid-template-columns: repeat(auto-fit, minmax(250px, 320px)); }
            .theme-gallery.is-active { grid-template-columns: minmax(250px, 320px); }
        }

        @media (max-width: 1080px) {
            .theme-gallery { grid-template-columns: repeat(auto-fit, minmax(240px, 300px)); }
            .theme-gallery.is-active { grid-template-columns: minmax(240px, 300px); }
        }

        @media (max-width: 720px) {
            .page-header {
                margin: -24px -18px 20px;
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
            }

            .theme-gallery {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">模板管理</h2>
            <div class="page-header-desc">这里负责主题切换和静态标签模板维护。</div>
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
