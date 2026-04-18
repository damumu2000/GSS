@extends('layouts.admin')

@section('title', '站点管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 站点管理')

@push('styles')
    <link rel="stylesheet" href="/css/platform-sites.css">
@endpush

@include('admin.site._custom_select_styles')

@section('content')
    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">站点管理</h2>
            <div class="page-header-desc">统一维护站点状态、绑定域名、图片参数和站点管理员，为多站点交付建立清晰的管理入口。</div>
        </div>
        <div class="page-header-actions">
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
                        <div class="site-domain-text site-muted">模板数量：{{ (int) ($managedSite->template_count ?? 0) }} / {{ (int) ($managedSite->template_limit ?? 1) }}</div>
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
