@extends('layouts.admin')

@section('title', '安护盾总览 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 平台 / 安护盾总览')

@push('styles')
    <link rel="stylesheet" href="/css/platform-security-overview.css">
@endpush

@section('content')
    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">安护盾总览</h2>
            <div class="page-header-desc">统一查看各站点近 7 天的拦截、可疑 IP 和当前防护模式。</div>
        </div>
    </section>

    <section class="platform-security-metrics">
        <article class="platform-security-card">
            <div class="platform-security-label">今日拦截</div>
            <div class="platform-security-value">{{ number_format($overview['today_blocked']) }}</div>
            <div class="platform-security-note">全平台今天命中的总拦截次数</div>
        </article>
        <article class="platform-security-card">
            <div class="platform-security-label">近 7 天拦截</div>
            <div class="platform-security-value">{{ number_format($overview['seven_day_blocked']) }}</div>
            <div class="platform-security-note">最近 7 天全平台累计拦截</div>
        </article>
        <article class="platform-security-card">
            <div class="platform-security-label">近 7 天高危</div>
            <div class="platform-security-value">{{ number_format($overview['seven_day_high_risk']) }}</div>
            <div class="platform-security-note">高危和严重规则命中总数</div>
        </article>
        <article class="platform-security-card">
            <div class="platform-security-label">当前封禁 IP</div>
            <div class="platform-security-value">{{ number_format($overview['active_blocked_ips']) }}</div>
            <div class="platform-security-note">当前仍在封禁中的异常来源</div>
        </article>
    </section>

    <section class="platform-security-panel">
        <div class="platform-security-panel-head">
            <h3 class="platform-security-panel-title">站点态势</h3>
            <div class="platform-security-panel-meta">共 {{ number_format($overview['active_sites']) }} 个站点近 7 天有安护盾数据</div>
        </div>

        <div class="platform-security-table-wrap">
            <table class="platform-security-table">
                <thead>
                <tr>
                    <th>站点</th>
                    <th>今日拦截</th>
                    <th>近 7 天</th>
                    <th>可疑 IP</th>
                    <th>当前封禁</th>
                    <th>主要类型</th>
                    <th>模式</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($overview['site_rows'] as $row)
                    <tr>
                        <td>
                            <div class="platform-security-site-name">{{ $row['site_name'] }}</div>
                            <div class="platform-security-site-key">{{ $row['site_key'] }}</div>
                        </td>
                        <td>{{ number_format($row['today_blocked']) }}</td>
                        <td>{{ number_format($row['seven_day_blocked']) }}</td>
                        <td>{{ number_format($row['suspicious_ip_count']) }}</td>
                        <td>{{ number_format($row['blocked_ip_count']) }}</td>
                        <td>{{ $row['top_rule_label'] }}</td>
                        <td>{{ $row['security_mode_label'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="platform-security-empty">当前还没有站点级安护盾统计。</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="platform-security-panel">
        <div class="platform-security-panel-head">
            <h3 class="platform-security-panel-title">最近高危记录</h3>
            <div class="platform-security-panel-meta">仅展示近 7 天高危和严重命中</div>
        </div>

        <div class="platform-security-events">
            @forelse ($overview['recent_high_risk_events'] as $event)
                <article class="platform-security-event">
                    <div class="platform-security-event-top">
                        <div class="platform-security-event-title">{{ $event['rule_label'] }}</div>
                        <div class="platform-security-event-time">{{ $event['created_at_label'] }}</div>
                    </div>
                    <div class="platform-security-event-meta">
                        <span>{{ $event['site_name'] }}</span>
                        <a class="platform-security-event-link" href="{{ route('admin.platform.security.ip-detail', ['site_id' => $event['site_id'], 'client_ip' => $event['client_ip']]) }}">{{ $event['client_ip'] }}</a>
                        <span>{{ $event['region_name'] ?? '未知来源' }}</span>
                        <span>{{ $event['request_path'] }}</span>
                    </div>
                </article>
            @empty
                <div class="platform-security-empty">当前还没有跨站点高危记录。</div>
            @endforelse
        </div>
    </section>
@endsection
