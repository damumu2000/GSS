@extends('layouts.admin')

@section('title', '站点管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 站点管理')

@push('styles')
    @include('admin.site._custom_select_styles')
    @include('admin.platform.sites._form_styles')
    <style>
        .site-gallery-toolbar {
            display: block;
            width: 100%;
        }

        .site-filter-card {
            width: 100%;
            padding: 20px 22px;
            border: 1px solid #eef1f5;
            border-radius: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }

        .site-filter-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 180px auto;
            gap: 16px;
            align-items: end;
        }

        .site-filter-grid label,
        .site-filter-field-group > label {
            display: block;
            margin-bottom: 8px;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 600;
        }

        .site-filter-field-group {
            min-width: 0;
        }

        .site-filter-field-group .field,
        .site-filter-field-group .site-select-trigger {
            width: 100%;
        }

        .site-filter-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: flex-end;
            padding-bottom: 2px;
            flex-wrap: wrap;
        }

        .site-filter-actions .button.is-active {
            background: #111827;
            border-color: #111827;
            color: #ffffff;
        }

        .site-list-shell {
            overflow: hidden;
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid #edf1f5;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
            margin-top: 18px;
        }

        .site-list-header,
        .site-item {
            display: grid;
            grid-template-columns: minmax(200px, 2.15fr) minmax(120px, 1fr) minmax(140px, 1fr) minmax(120px, 1.1fr) 88px 88px;
            gap: 16px;
            align-items: center;
            padding: 16px 22px;
        }

        .site-list-header {
            border-bottom: 1px solid #eef1f4;
            background: #fbfcfd;
        }

        .site-list-title {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.5;
            font-weight: 700;
        }

        .site-list-header .site-list-title:nth-child(5),
        .site-list-header .site-list-title:nth-child(6) {
            text-align: center;
        }

        .site-list {
            display: grid;
        }

        .site-item {
            border-bottom: 1px solid #f2f4f7;
            transition: background-color 0.18s ease;
        }

        .site-item:last-child {
            border-bottom: 0;
        }

        .site-item:hover {
            background: #fcfcfd;
        }

        .site-main {
            min-width: 0;
            display: grid;
            gap: 8px;
        }

        .site-main-row {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            flex-wrap: wrap;
        }

        .site-name {
            margin: 0;
            color: #262626;
            font-size: 15px;
            line-height: 1.5;
            font-weight: 700;
            word-break: keep-all;
        }

        .site-key-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 26px;
            padding: 0 10px;
            border-radius: 999px;
            background: #eef4ff;
            color: #1d4ed8;
            font-size: 12px;
            line-height: 1;
            font-weight: 700;
            white-space: nowrap;
        }

        .site-domain-text,
        .site-time-text,
        .site-modules-text {
            color: #4b5563;
            font-size: 13px;
            line-height: 1.7;
            min-width: 0;
        }

        .site-domain-text {
            word-break: break-all;
        }

        .site-modules-text {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-break: break-word;
        }

        .site-muted {
            color: #98a2b3;
        }

        .site-timeline {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .site-timeline-item {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            color: #4b5563;
            font-size: 13px;
            line-height: 1.6;
        }

        .site-timeline-label {
            color: #98a2b3;
            font-size: 11px;
            line-height: 1.4;
            font-weight: 600;
            white-space: nowrap;
        }

        .site-timeline-value {
            min-width: 0;
            word-break: keep-all;
        }

        .site-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
            padding: 0 12px;
            border-radius: 999px;
            background: rgba(16, 185, 129, 0.10);
            color: #059669;
            font-size: 12px;
            line-height: 1;
            font-weight: 700;
            white-space: nowrap;
        }

        .site-status-badge.is-offline {
            background: #f3f4f6;
            color: #6b7280;
        }

        .site-status-wrap {
            display: grid;
            gap: 6px;
            justify-items: center;
            text-align: center;
        }

        .site-status-hint {
            color: #d97706;
            font-size: 11px;
            line-height: 1.4;
            font-weight: 600;
            white-space: nowrap;
        }

        .site-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .site-action-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #4b5563;
            font-size: 12px;
            line-height: 1;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
        }

        .site-action-link:hover {
            color: #262626;
        }

        .site-action-link .button-icon {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
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

        @media (max-width: 1200px) {
            .site-list-header,
            .site-item {
                grid-template-columns: minmax(180px, 2fr) minmax(110px, 0.95fr) minmax(130px, 0.95fr) minmax(110px, 1fr) 80px 80px;
            }
        }

        @media (max-width: 720px) {
            .site-filter-grid {
                grid-template-columns: 1fr;
            }

            .site-filter-actions {
                justify-content: flex-start;
            }

            .site-list-shell {
                overflow: visible;
            }

            .site-list-header {
                display: none;
            }

            .site-list {
                gap: 14px;
                padding: 16px;
            }

            .site-item {
                grid-template-columns: 1fr;
                gap: 10px;
                padding: 18px;
                border: 1px solid #edf1f5;
                border-radius: 14px;
            }
        }

        .site-gallery-toolbar .button {
            background: #374151;
            border-color: #374151;
        }

        .site-gallery-toolbar .button:hover {
            background: #1f2937;
            border-color: #1f2937;
        }

        .site-gallery-toolbar .button.secondary {
            background: #ffffff;
            color: #4b5563;
            border-color: #e5e7eb;
        }

        .site-gallery-toolbar .button.secondary:hover {
            background: #f9fafb;
            color: #262626;
        }

        .site-gallery-toolbar .site-select {
            min-width: 0;
        }

        .site-gallery-toolbar .site-select-trigger {
            width: 100%;
        }

    </style>
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">站点管理</h2>
            <div class="page-header-desc">统一维护站点状态、绑定域名、图片参数和站点管理员，为多站点交付建立清晰的管理入口。</div>
        </div>
        <div class="topbar-right">
            <a class="button" href="{{ route('admin.platform.sites.create') }}">新增站点</a>
        </div>
    </section>

    <section class="site-gallery-toolbar">
        <form class="site-filter-card" method="GET" action="{{ route('admin.platform.sites.index') }}">
            <div class="site-filter-grid">
                <div class="site-filter-field-group">
                    <label for="keyword">搜索站点</label>
                    <input class="field" id="keyword" type="text" name="keyword" value="{{ $keyword }}" placeholder="站点名称、标识、域名、管理员">
                </div>
                <div class="site-filter-field-group">
                    <label for="run_status">运行状态</label>
                    <div class="site-select" data-site-select>
                        <select id="run_status" name="run_status" class="field site-select-native">
                            <option value="">全部状态</option>
                            <option value="1" @selected($runStatus === '1')>开启中</option>
                            <option value="0" @selected($runStatus === '0')>已关闭</option>
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ $runStatus === '1' ? '开启中' : ($runStatus === '0' ? '已关闭' : '全部状态') }}</button>
                        <div class="site-select-panel" data-select-panel role="listbox"></div>
                    </div>
                </div>
                <div class="site-filter-actions">
                    @if ($expiresSoon === '1')
                        <input type="hidden" name="expires_soon" value="1">
                    @endif
                    <button class="button neutral-action" type="submit">筛选</button>
                    <a class="button neutral-action" href="{{ route('admin.platform.sites.index') }}">重置</a>
                    <a class="button neutral-action {{ $expiresSoon === '1' ? 'is-active' : '' }}" href="{{ route('admin.platform.sites.index', array_filter([
                        'keyword' => $keyword !== '' ? $keyword : null,
                        'run_status' => $runStatus !== '' ? $runStatus : null,
                        'expires_soon' => '1',
                    ], fn ($value) => $value !== null)) }}">即将到期</a>
                </div>
            </div>
        </form>
    </section>

    @if ($managedSites->isEmpty())
        <div class="empty-state">
            <h3 class="empty-state-title">没有找到站点</h3>
            <div class="empty-state-desc">当前筛选条件下没有匹配的站点记录，你可以调整关键词，或直接创建新的站点。</div>
        </div>
    @else
        <section class="site-list-shell">
            <div class="site-list-header">
                <div class="site-list-title">站点信息</div>
                <div class="site-list-title">主域名</div>
                <div class="site-list-title">时效信息</div>
                <div class="site-list-title">已绑定模块</div>
                <div class="site-list-title">运行状态</div>
                <div class="site-list-title">操作</div>
            </div>
            <div class="site-list">
            @foreach ($managedSites as $managedSite)
                @php
                    $openedAt = $managedSite->opened_at ? \Illuminate\Support\Carbon::parse($managedSite->opened_at)->format('Y-m-d') : '未设置';
                    $expiresAt = $managedSite->expires_at ? \Illuminate\Support\Carbon::parse($managedSite->expires_at)->format('Y-m-d') : '长期有效';
                    $moduleNames = trim((string) ($managedSite->module_names ?? ''));
                    $expiresSoonLabel = null;

                    if ($managedSite->expires_at) {
                        $expiresDiffDays = now()->diffInDays(\Illuminate\Support\Carbon::parse($managedSite->expires_at), false);

                        if ($expiresDiffDays >= 0 && $expiresDiffDays < 30) {
                            $expiresSoonLabel = '不满30天';
                        }
                    }
                @endphp

                <article class="site-item">
                    <div class="site-main">
                        <div class="site-main-row">
                            <h3 class="site-name">{{ $managedSite->name }}</h3>
                            <span class="site-key-badge">{{ '@' . $managedSite->site_key }}</span>
                        </div>
                        <div class="site-domain-text site-muted">{{ $managedSite->theme_name ? ('默认主题：' . $managedSite->theme_name) : '未绑定默认主题' }}</div>
                    </div>
                    <div class="site-domain-text">
                        {{ $managedSite->primary_domain ?: '未绑定' }}@if(($managedSite->domain_count ?? 0) > 1) +{{ $managedSite->domain_count - 1 }}@endif
                    </div>
                    <div class="site-timeline">
                        <div class="site-timeline-item">
                            <span class="site-timeline-label">开通</span>
                            <span class="site-timeline-value">{{ $openedAt }}</span>
                        </div>
                        <div class="site-timeline-item">
                            <span class="site-timeline-label">到期</span>
                            <span class="site-timeline-value">{{ $expiresAt }}</span>
                        </div>
                    </div>
                    <div class="site-modules-text {{ $moduleNames === '' ? 'site-muted' : '' }}">{{ $moduleNames !== '' ? $moduleNames : '未绑定模块' }}</div>
                    <div class="site-status-wrap">
                        <span class="site-status-badge {{ $managedSite->status ? '' : 'is-offline' }}">{{ $managedSite->status ? '开启中' : '已关闭' }}</span>
                        @if ($expiresSoonLabel)
                            <span class="site-status-hint">{{ $expiresSoonLabel }}</span>
                        @endif
                    </div>
                    <div class="site-actions">
                        <a class="site-action-link" href="{{ route('admin.platform.sites.edit', $managedSite->id) }}">
                            <span class="button-icon" aria-hidden="true">
                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                                    <path d="M6 3.5H3.75A1.25 1.25 0 0 0 2.5 4.75v7.5A1.25 1.25 0 0 0 3.75 13.5h8.5a1.25 1.25 0 0 0 1.25-1.25V10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="m9.5 4 2.5 2.5M8.5 12l4.75-4.75a1.06 1.06 0 0 0 0-1.5l-1-1a1.06 1.06 0 0 0-1.5 0L6 9.5 5.5 12z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <span>编辑站点</span>
                        </a>
                    </div>
                </article>
            @endforeach
            </div>
        </section>
    @endif
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
@endpush
