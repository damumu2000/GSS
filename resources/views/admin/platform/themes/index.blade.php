@extends('layouts.admin')

@section('title', '主题市场 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 主题市场')

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

        .theme-list {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 24px;
        }

        .theme-card {
            overflow: hidden;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .theme-card:hover {
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
            transform: translateY(-5px);
        }

        .theme-cover {
            position: relative;
            height: 180px;
            background:
                linear-gradient(135deg, rgba(0, 80, 179, 0.14), rgba(0, 80, 179, 0.03)),
                linear-gradient(180deg, #f8fffe, #eef7f7);
            overflow: hidden;
        }

        .theme-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .theme-cover-placeholder {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #bfbfbf;
        }

        .theme-status-dot {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #52c41a;
            border: 2px solid rgba(255, 255, 255, 0.92);
            box-shadow: 0 0 0 0 rgba(82, 196, 26, 0.24);
            animation: theme-status-pulse 1.8s ease-out infinite;
        }

        .theme-status-dot.is-offline {
            background: #bfbfbf;
            box-shadow: none;
            animation: none;
        }

        .theme-body {
            padding: 16px;
            display: grid;
            gap: 12px;
        }

        .theme-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .theme-name {
            margin: 0;
            color: #262626;
            font-size: 15px;
            line-height: 1.4;
            font-weight: 700;
        }

        .theme-version {
            display: inline-flex;
            align-items: center;
            min-height: 22px;
            padding: 0 10px;
            border-radius: 999px;
            background: #f5f7fa;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 22px;
            font-weight: 600;
        }

        .theme-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.6;
        }

        @keyframes theme-status-pulse {
            0% { box-shadow: 0 0 0 0 rgba(82, 196, 26, 0.28); }
            70% { box-shadow: 0 0 0 8px rgba(82, 196, 26, 0); }
            100% { box-shadow: 0 0 0 0 rgba(82, 196, 26, 0); }
        }

        .theme-description {
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.7;
            min-height: 44px;
        }

        .theme-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .theme-action-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #4b5563;
            font-size: 13px;
            line-height: 1;
            font-weight: 600;
            text-decoration: none;
        }

        .theme-action-link:hover {
            color: #262626;
        }

        .theme-action-link .button-icon {
            width: 16px;
            height: 16px;
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

        @media (max-width: 1440px) {
            .theme-list {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 1080px) {
            .theme-list {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 720px) {
            .theme-list {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">主题市场</h2>
            <div class="page-header-desc">统一维护平台主题库、当前版本和主题说明，方便各站点绑定和使用主题。</div>
        </div>
        <div class="topbar-right">
            <a class="button" href="{{ route('admin.platform.themes.create') }}">新增主题</a>
        </div>
    </section>

    <section class="theme-list">
        @forelse ($themes as $theme)
            <article class="theme-card">
                <div class="theme-cover">
                    @if ($theme->cover_image)
                        <img src="{{ $theme->cover_image }}" alt="{{ $theme->name }} 封面图">
                    @else
                        <div class="theme-cover-placeholder" aria-hidden="true">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
                                <path d="M6.5 7.75h11M6.5 12h11M6.5 16.25h7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                <rect x="4" y="4" width="16" height="16" rx="4" stroke="currentColor" stroke-width="1.7"/>
                            </svg>
                        </div>
                    @endif
                    <span class="theme-status-dot" aria-label="平台主题"></span>
                </div>
                <div class="theme-body">
                    <div class="theme-row">
                        <h3 class="theme-name">{{ $theme->name }}</h3>
                        <span class="theme-version">v{{ $theme->version ?: '1.0.0' }}</span>
                    </div>
                    <div class="theme-meta">
                        <span>主题代码：{{ $theme->code }}</span>
                    </div>
                    <div class="theme-description">{{ $theme->description ?: '当前主题暂未填写描述，可进入详情页补充主题说明和版本信息。' }}</div>
                    <div class="theme-actions">
                        <a class="theme-action-link" href="{{ route('admin.platform.themes.edit', $theme->id) }}">
                            <span class="button-icon" aria-hidden="true">
                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                                    <path d="M6 3.5H3.75A1.25 1.25 0 0 0 2.5 4.75v7.5A1.25 1.25 0 0 0 3.75 13.5h8.5a1.25 1.25 0 0 0 1.25-1.25V10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="m9.5 4 2.5 2.5M8.5 12l4.75-4.75a1.06 1.06 0 0 0 0-1.5l-1-1a1.06 1.06 0 0 0-1.5 0L6 9.5 5.5 12z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <span>编辑主题</span>
                        </a>
                    </div>
                </div>
            </article>
        @empty
            <div class="empty-state">
                <h3 class="empty-state-title">还没有主题记录</h3>
                <div class="empty-state-desc">先创建第一个主题，后续就可以在这里集中维护版本、说明和启用状态。</div>
            </div>
        @endforelse
    </section>
@endsection
