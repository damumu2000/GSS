@extends('layouts.admin')

@section('title', '主题市场 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 主题市场')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/platform-themes-index.css') }}">
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
