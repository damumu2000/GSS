@extends('layouts.admin')

@section('title', 'IP 详情 - 安护盾 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 安护盾 / IP 详情')

@push('styles')
    <link rel="stylesheet" href="/css/site-security.css">
@endpush

@section('content')
    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">IP 详情</h2>
            <div class="page-header-desc">查看当前站点下该来源 IP 的命中画像和最近拦截记录。</div>
        </div>
    </section>

    <div class="security-shell" data-security-ip-detail-content>
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
                <div class="security-card-note">当前站点累计画像次数。</div>
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
                <div class="security-card-note">{{ $detail['site_policy_label'] !== '' ? $detail['site_policy_label'] : ($detail['status_time_label'] ?? ('最近 ' . $detail['last_seen_label'])) }}</div>
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
                    <div class="security-detail-label">封禁状态</div>
                    <div class="security-detail-value">{{ $detail['status_time_label'] ?? ($detail['blocked_until_label'] !== '' ? $detail['blocked_until_label'] : '无') }}</div>
                </div>
            </div>
        </section>

        <section class="security-panel security-detail-panel">
            <h3 class="security-panel-title">封禁原因聚合</h3>
            <div class="security-panel-desc">按最近 24 小时命中类型聚合，便于快速判断该 IP 的主要风险来源。</div>
            <div class="security-detail-events">
                @forelse (($detail['reason_summary'] ?? []) as $reason)
                    <article class="security-detail-event">
                        <div class="security-detail-event-top">
                            <div class="security-detail-event-title">{{ $reason['rule_label'] }}</div>
                            <div class="security-detail-event-time">最近 {{ $reason['last_seen_label'] }}</div>
                        </div>
                        <div class="security-detail-event-meta">
                            <span class="security-event-chip {{ in_array($reason['risk_label'], ['高危', '严重'], true) ? 'is-risk-high' : 'is-risk-medium' }}">{{ $reason['risk_label'] }}</span>
                            <span class="security-event-chip">命中 {{ number_format($reason['total']) }} 次</span>
                        </div>
                    </article>
                @empty
                    <div class="security-empty">最近 24 小时没有可聚合的命中原因。</div>
                @endforelse
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
