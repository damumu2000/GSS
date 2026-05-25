@extends('layouts.admin')

@section('title', '平台安护盾 IP 详情 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 平台 / 安护盾总览 / IP 详情')

@push('styles')
    <link rel="stylesheet" href="/css/site-security.css">
    <link rel="stylesheet" href="/css/platform-security-overview.css">
@endpush

@section('content')
    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">平台安护盾 IP 详情</h2>
            <div class="page-header-desc">当前站点：{{ $targetSite->name }}（{{ $targetSite->site_key }}）</div>
        </div>
    </section>

    <div class="security-shell">
        <section class="security-metrics">
            <article class="security-card">
                <div class="security-card-top">
                    <div class="security-card-label">来源 IP</div>
                </div>
                <div class="security-card-value security-card-value--ip">{{ $detail['client_ip'] }}</div>
                <div class="security-card-note">{{ $detail['region_name'] ?? '未知来源' }}</div>
            </article>
            <article class="security-card">
                <div class="security-card-top">
                    <div class="security-card-label">命中次数</div>
                </div>
                <div class="security-card-value">{{ number_format($detail['hit_count']) }}</div>
                <div class="security-card-note">当前站点画像累计次数。</div>
            </article>
            <article class="security-card">
                <div class="security-card-top">
                    <div class="security-card-label">高危次数</div>
                </div>
                <div class="security-card-value">{{ number_format($detail['high_risk_count']) }}</div>
                <div class="security-card-note">高危和严重规则命中总数。</div>
            </article>
            <article class="security-card">
                <div class="security-card-top">
                    <div class="security-card-label">当前状态</div>
                </div>
                <div class="security-card-value">{{ $detail['status_label'] }}</div>
                <div class="security-card-note">{{ $detail['site_policy_label'] !== '' ? $detail['site_policy_label'] : ('最近 ' . $detail['last_seen_label']) }}</div>
            </article>
        </section>

        <section class="security-panel security-detail-panel">
            <div class="security-detail-summary">
                <div class="security-detail-summary-item">
                    <div class="security-detail-label">IP 位置</div>
                    <div class="security-detail-value">{{ $detail['region_name'] ?? '未知来源' }}</div>
                </div>
                <div class="security-detail-summary-item">
                    <div class="security-detail-label">最近命中规则</div>
                    <div class="security-detail-value">{{ $detail['last_rule_label'] }}</div>
                </div>
                <div class="security-detail-summary-item">
                    <div class="security-detail-label">最近命中路径</div>
                    <div class="security-detail-value">{{ $detail['last_request_path'] ?: '暂无路径记录' }}</div>
                </div>
                <div class="security-detail-summary-item">
                    <div class="security-detail-label">封禁到期</div>
                    <div class="security-detail-value">{{ $detail['blocked_until_label'] !== '' ? $detail['blocked_until_label'] : '无' }}</div>
                </div>
            </div>

            <div class="platform-security-policy-actions">
                @if (empty($detail['is_global_allowlisted']) && empty($detail['is_global_blocklisted']))
                    <form method="POST" action="{{ route('admin.platform.security.ip-policy.store') }}">
                        @csrf
                        <input type="hidden" name="site_id" value="{{ $targetSite->id }}">
                        <input type="hidden" name="client_ip" value="{{ $detail['client_ip'] }}">
                        <input type="hidden" name="action" value="{{ !empty($detail['is_site_allowlisted']) ? 'remove_allow' : 'allow' }}">
                        <button class="security-ip-action {{ !empty($detail['is_site_allowlisted']) ? 'is-remove' : 'is-allow' }}" type="submit">{{ !empty($detail['is_site_allowlisted']) ? '移白' : '加白' }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.platform.security.ip-policy.store') }}">
                        @csrf
                        <input type="hidden" name="site_id" value="{{ $targetSite->id }}">
                        <input type="hidden" name="client_ip" value="{{ $detail['client_ip'] }}">
                        <input type="hidden" name="action" value="{{ !empty($detail['is_site_blocklisted']) ? 'remove_block' : 'block' }}">
                        <button class="security-ip-action {{ !empty($detail['is_site_blocklisted']) ? 'is-remove' : 'is-block' }}" type="submit">{{ !empty($detail['is_site_blocklisted']) ? '移黑' : '拉黑' }}</button>
                    </form>
                @endif
                @if (($detail['status'] ?? '') === 'blocked'
                    && !empty($detail['blocked_until_label'])
                    && empty($detail['is_global_allowlisted'])
                    && empty($detail['is_global_blocklisted'])
                    && empty($detail['is_site_blocklisted'])
                    && empty($detail['is_site_allowlisted']))
                    <form method="POST" action="{{ route('admin.platform.security.ip-policy.store') }}">
                        @csrf
                        <input type="hidden" name="site_id" value="{{ $targetSite->id }}">
                        <input type="hidden" name="client_ip" value="{{ $detail['client_ip'] }}">
                        <input type="hidden" name="action" value="release_block">
                        <button class="security-ip-action is-release" type="submit">解封</button>
                    </form>
                @endif
            </div>
        </section>

        <section class="security-panel security-detail-panel">
            <h3 class="security-panel-title">最近命中记录</h3>
            <div class="security-panel-desc">按风险等级和时间排序，仅展示当前站点下该 IP 的最近 20 条记录。</div>
            <div class="security-detail-events">
                @forelse (($detail['recent_events'] ?? []) as $event)
                    <article class="security-detail-event">
                        <div class="security-detail-event-top">
                            <div class="security-detail-event-title">{{ $event['rule_label'] }}</div>
                            <div class="security-detail-event-time">{{ $event['time_label'] }}</div>
                        </div>
                        <div class="security-detail-event-meta">
                            <span class="security-event-chip {{ in_array($event['risk_level_label'], ['高危', '严重'], true) ? 'is-risk-high' : 'is-risk-medium' }}">{{ $event['risk_level_label'] }}</span>
                            <span class="security-event-chip">{{ $event['action_label'] }}</span>
                            <span class="security-event-chip">{{ $event['region_name'] ?? '未知来源' }}</span>
                            <span>{{ $event['request_method'] ?: 'GET' }}</span>
                            <span>{{ $event['request_path'] ?: '/' }}</span>
                        </div>
                        @if ($event['request_query'] !== '')
                            <div class="security-detail-event-extra">参数：{{ $event['request_query'] }}</div>
                        @endif
                        @if ($event['referer'] !== '')
                            <div class="security-detail-event-extra">来源：{{ $event['referer'] }}</div>
                        @endif
                        @if ($event['user_agent'] !== '')
                            <div class="security-detail-event-extra">UA：{{ $event['user_agent'] }}</div>
                        @endif
                    </article>
                @empty
                    <div class="security-empty">当前 IP 还没有可展示的命中记录。</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
