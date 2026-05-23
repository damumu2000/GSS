@extends('layouts.admin')

@section('title', '安护盾 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 安护盾')

@push('styles')
    <link rel="stylesheet" href="/css/site-security.css">
@endpush

@push('scripts')
    <script src="/js/site-security.js"></script>
@endpush

@section('content')
    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">安护盾</h2>
            <div class="page-header-desc">构建于硬核防火墙之后的 WAF 纵深防御体系，作为 Web 应用的第二道核心屏障，精准拦截各类应用层威胁。</div>
        </div>
    </section>

    <div class="security-shell">
        <section class="security-metrics">
            <article class="security-card">
                <div class="security-card-top">
                    <div class="security-card-label">今日拦截攻击</div>
                    <div class="security-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 3 5.5 6v5.2c0 4.1 2.5 7.7 6.5 9.8 4-2.1 6.5-5.7 6.5-9.8V6L12 3Z"></path>
                            <path d="m9.5 12 1.8 1.8 3.4-3.6"></path>
                        </svg>
                    </div>
                </div>
                <div class="security-card-value">{{ number_format($security['today_blocked']) }}</div>
                <div class="security-card-note">今天已经拦下的异常请求。</div>
            </article>
            <article class="security-card">
                <div class="security-card-top">
                    <div class="security-card-label">近 7 天拦截总数</div>
                    <div class="security-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 3 5.5 6v5.2c0 4.1 2.5 7.7 6.5 9.8 4-2.1 6.5-5.7 6.5-9.8V6L12 3Z"></path>
                            <path d="M12 9v3.8"></path>
                            <path d="M12 16h.01"></path>
                        </svg>
                    </div>
                </div>
                <div class="security-card-value">{{ number_format($security['seven_day_blocked']) }}</div>
                <div class="security-card-note">最近 7 天命中的总拦截次数。</div>
            </article>
            <article class="security-card">
                <div class="security-card-top">
                    <div class="security-card-label">近 7 天高危次数</div>
                    <div class="security-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 3 5.5 6v5.2c0 4.1 2.5 7.7 6.5 9.8 4-2.1 6.5-5.7 6.5-9.8V6L12 3Z"></path>
                            <path d="M12 8v5"></path>
                            <path d="M12 16h.01"></path>
                        </svg>
                    </div>
                </div>
                <div class="security-card-value">{{ number_format($security['seven_day_high_risk']) }}</div>
                <div class="security-card-note">近 7 天高危规则命中的总次数。</div>
            </article>
            <article class="security-card is-status">
                <div class="security-status-showcase{{ ($security['status_tone'] ?? 'running') === 'disabled' ? ' is-disabled' : '' }}">
                    <div class="security-status-visual" aria-hidden="true">
                        <div class="security-status-core-glow"></div>
                        <div class="security-status-ring"></div>
                        <span class="security-status-particle is-one"></span>
                        <span class="security-status-particle is-two"></span>
                        <span class="security-status-particle is-three"></span>
                        <div class="security-status-shield">
                            <svg viewBox="0 0 24 24">
                                <path d="M12 3 5.5 6v5.2c0 4.1 2.5 7.7 6.5 9.8 4-2.1 6.5-5.7 6.5-9.8V6L12 3Z"></path>
                                <path d="m9.5 12 1.8 1.8 3.4-3.6"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="security-status-state">{{ $security['status_label'] }}</div>
                </div>
            </article>
        </section>

        @php
            $suspiciousIps = collect($security['suspicious_ips'] ?? []);
        @endphp

        <section class="security-grid">
            <article class="security-panel">
                <h3 class="security-panel-title">近 7 天拦截趋势</h3>
                @php
                    $trendItems = collect($security['trend'])->values();
                    $trendTotal = (int) $trendItems->sum('value');
                    $todayValue = (int) ($trendItems->last()['value'] ?? 0);
                    $yesterdayValue = (int) ($trendItems->slice(-2, 1)->first()['value'] ?? 0);
                    $delta = $todayValue - $yesterdayValue;
                    $leadType = collect($security['types'])->sortByDesc('value')->first();
                    $regionItems = collect($security['regions'] ?? []);
                    $chartWidth = 760;
                    $chartHeight = 238;
                    $chartPaddingX = 22;
                    $chartTop = 70;
                    $chartBottom = 50;
                    $chartInnerWidth = $chartWidth - ($chartPaddingX * 2);
                    $chartInnerHeight = $chartHeight - $chartTop - $chartBottom;
                    $pointCount = max(1, $trendItems->count());
                    $peakValue = max(1, (int) $trendItems->max('value'));
                    $quietDays = $trendItems->where('value', 0)->count();
                    $stepX = $pointCount > 1 ? $chartInnerWidth / ($pointCount - 1) : 0;
                    $points = [];

                    foreach ($trendItems as $index => $item) {
                        $x = $chartPaddingX + ($stepX * $index);
                        $y = $chartTop + $chartInnerHeight - (($item['value'] / $peakValue) * $chartInnerHeight);
                        $points[] = [
                            'x' => round($x, 2),
                            'y' => round($y, 2),
                            'label' => $item['label'],
                            'value' => (int) $item['value'],
                        ];
                    }

                    $linePath = '';
                    if (count($points) === 1) {
                        $linePath = 'M ' . $points[0]['x'] . ' ' . $points[0]['y'];
                    } elseif (count($points) === 2) {
                        $linePath = 'M ' . $points[0]['x'] . ' ' . $points[0]['y']
                            . ' L ' . $points[1]['x'] . ' ' . $points[1]['y'];
                    } elseif (count($points) > 2) {
                        $linePath = 'M ' . $points[0]['x'] . ' ' . $points[0]['y'];

                        for ($i = 0; $i < count($points) - 1; $i++) {
                            $p0 = $points[$i - 1] ?? $points[$i];
                            $p1 = $points[$i];
                            $p2 = $points[$i + 1];
                            $p3 = $points[$i + 2] ?? $p2;

                            $cp1x = round($p1['x'] + (($p2['x'] - $p0['x']) / 6), 2);
                            $cp1y = round($p1['y'] + (($p2['y'] - $p0['y']) / 6), 2);
                            $cp2x = round($p2['x'] - (($p3['x'] - $p1['x']) / 6), 2);
                            $cp2y = round($p2['y'] - (($p3['y'] - $p1['y']) / 6), 2);

                            $linePath .= ' C '
                                . $cp1x . ' ' . $cp1y . ' '
                                . $cp2x . ' ' . $cp2y . ' '
                                . $p2['x'] . ' ' . $p2['y'];
                        }
                    }

                    $baselineY = $chartTop + $chartInnerHeight;
                    $areaPath = $linePath !== ''
                        ? $linePath . ' L ' . $points[array_key_last($points)]['x'] . ' ' . $baselineY . ' L ' . $points[0]['x'] . ' ' . $baselineY . ' Z'
                        : '';
                @endphp
                <div class="security-trend">
                    <div class="security-trend-chart">
                        <div class="security-trend-headline">
                            <span class="security-trend-headline-dot"></span>
                            <span>{{ $quietDays > 0 ? ('近 7 天有 ' . $quietDays . ' 天处于静默拦截') : '近 7 天每天都有拦截记录' }}</span>
                        </div>
                        <div class="security-trend-focus">
                            <div class="security-trend-focus-label">当前最高点</div>
                            <div class="security-trend-focus-value">{{ number_format($security['peak_blocked']) }}</div>
                        </div>
                        <svg class="security-trend-svg" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="securityTrendAreaFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#002366" stop-opacity="0.4"></stop>
                                    <stop offset="100%" stop-color="#002366" stop-opacity="0"></stop>
                                </linearGradient>
                            </defs>
                            <line class="security-trend-gridline" x1="{{ $chartPaddingX }}" y1="{{ $chartTop + 26 }}" x2="{{ $chartWidth - $chartPaddingX }}" y2="{{ $chartTop + 26 }}"></line>
                            <line class="security-trend-gridline" x1="{{ $chartPaddingX }}" y1="{{ $chartTop + 78 }}" x2="{{ $chartWidth - $chartPaddingX }}" y2="{{ $chartTop + 78 }}"></line>
                            <line class="security-trend-baseline" x1="{{ $chartPaddingX }}" y1="{{ $baselineY }}" x2="{{ $chartWidth - $chartPaddingX }}" y2="{{ $baselineY }}"></line>
                            @if ($areaPath !== '')
                                <path class="security-trend-area" d="{{ $areaPath }}"></path>
                            @endif
                            @if ($linePath !== '')
                                <path class="security-trend-line" d="{{ $linePath }}"></path>
                            @endif
                            @foreach ($points as $index => $point)
                                @php
                                    $hotspotX = $point['x'] - (($index === 0 || $index === count($points) - 1) ? 28 : 44);
                                    $hotspotWidth = $index === 0 || $index === count($points) - 1 ? 56 : 88;
                                @endphp
                                <rect class="security-trend-hotspot" x="{{ $hotspotX }}" y="{{ $chartTop }}" width="{{ $hotspotWidth }}" height="{{ $chartInnerHeight + 16 }}"></rect>
                                @if ($point['value'] > 0)
                                    <circle class="security-trend-point" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5.4" data-tooltip="{{ $point['label'] }} · {{ number_format($point['value']) }} 次"></circle>
                                @else
                                    <circle class="security-trend-zero-dot" cx="{{ $point['x'] }}" cy="{{ $baselineY }}" r="3.1"></circle>
                                @endif
                            @endforeach
                        </svg>
                        <div class="security-trend-axis">
                            @foreach ($trendItems as $item)
                                <div class="security-trend-label">{{ $item['label'] }}</div>
                            @endforeach
                        </div>
                    </div>
                    <div class="security-trend-stats">
                        <div class="security-trend-stat">
                            <div class="security-trend-stat-label">近 7 天累计</div>
                            <div class="security-trend-stat-value">{{ number_format($trendTotal) }}</div>
                            <div class="security-trend-stat-note">最近一周命中的总拦截次数</div>
                        </div>
                        <div class="security-trend-stat">
                            <div class="security-trend-stat-label">单日峰值</div>
                            <div class="security-trend-stat-value">{{ number_format($security['peak_blocked']) }}</div>
                            <div class="security-trend-stat-note">近 7 天单日最高拦截值</div>
                        </div>
                        <div class="security-trend-stat">
                            <div class="security-trend-stat-label">今日较昨日</div>
                            <div class="security-trend-stat-value">{{ $delta > 0 ? '+' : '' }}{{ number_format($delta) }}</div>
                            <div class="security-trend-stat-note">{{ $leadType ? ('主要类型：' . $leadType['label']) : '当前还没有主要拦截类型' }}</div>
                        </div>
                    </div>
                    <div class="security-region">
                        <h4 class="security-region-heading">攻击区域</h4>
                        <div class="security-region-sub">近 7 天命中的拦截记录里，主要攻击来源区域如下。</div>
                        <div class="security-region-list-items">
                            @forelse ($regionItems as $region)
                                <div class="security-region-item">
                                    <div class="security-region-item-top">
                                        <div class="security-region-item-name">{{ $region['label'] }}</div>
                                        <div class="security-region-item-meta">{{ number_format($region['value']) }} 次 · {{ $region['ratio'] }}%</div>
                                    </div>
                                    <div class="security-region-track">
                                        <div class="security-region-bar security-region-bar--r-{{ max(0, min(100, (int) round($region['ratio']))) }}"></div>
                                    </div>
                                </div>
                            @empty
                                <div class="security-empty">当前还没有足够的来源区域数据。</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </article>

            <article class="security-panel">
                <h3 class="security-panel-title">拦截类型分布</h3>
                <div class="security-panel-desc">按近 7 天命中次数排序，优先看最主要的几类异常请求。</div>
                <div class="security-types">
                    @forelse ($security['types'] as $item)
                        <div class="security-type-item{{ ! empty($item['is_high_risk']) ? ' is-high-risk' : '' }}">
                            <div class="security-type-top">
                                <div class="security-type-name">{{ $item['label'] }}</div>
                                <div class="security-type-value">{{ number_format($item['value']) }}</div>
                            </div>
                            <div class="security-type-track">
                                <div class="security-type-bar security-type-bar--r-{{ max(0, min(100, (int) round($item['ratio']))) }}"></div>
                            </div>
                            <div class="security-type-meta">占比 {{ $item['ratio'] }}%</div>
                            <div class="security-type-note">{{ $item['note'] }}</div>
                        </div>
                    @empty
                        <div class="security-empty">当前还没有拦截记录。功能已经就绪，后续有命中时会在这里显示。</div>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="security-panel">
            <h3 class="security-panel-title">最近拦截记录</h3>
            <div class="security-panel-desc">展示近 7 天最近命中的拦截记录、访问 IP、处置方式和防护类型。</div>
            <div class="security-event-filters" data-security-event-filters>
                <button class="security-event-filter is-active" type="button" data-filter="all">全部</button>
                <button class="security-event-filter" type="button" data-filter="high-risk">只看高危</button>
                <button class="security-event-filter" type="button" data-filter="sql-injection">只看 SQL 注入</button>
                <button class="security-event-filter" type="button" data-filter="xss">只看 XSS</button>
                <button class="security-event-filter" type="button" data-filter="path-traversal">只看路径穿越</button>
                <button class="security-event-filter" type="button" data-filter="bad-upload">只看可疑上传</button>
                <button class="security-event-filter" type="button" data-filter="probe-abuse">只看扫描试探超限</button>
                <button class="security-event-filter" type="button" data-filter="rate-limit">只看频繁刷新</button>
                <button class="security-event-filter" type="button" data-filter="temporary-block">只看已封禁</button>
                <button class="security-event-filter" type="button" data-filter="ip-blocklist">只看黑名单</button>
                <button class="security-event-filter" type="button" data-filter="bad-client">只看脚本扫描器</button>
                <button class="security-event-filter" type="button" data-filter="bad-method">只看异常方法</button>
                <button class="security-event-filter" type="button" data-filter="bad-payload">只看异常参数</button>
            </div>
            <div class="security-events">
                @forelse ($security['events'] as $event)
                    <article
                        class="security-event"
                        data-rule-code="{{ $event['rule_code'] }}"
                        data-risk-level="{{ $event['risk_level'] }}"
                        data-action="{{ $event['action'] }}"
                    >
                        <div class="security-event-top">
                            <div class="security-event-rule">{{ $event['rule_name'] }}</div>
                            <div class="security-event-time">{{ $event['created_at_label'] }}</div>
                        </div>
                        <div class="security-event-meta">
                            <span class="security-event-chip">{{ $event['category_label'] }}</span>
                            <span class="security-event-chip {{ in_array($event['risk_level'], ['critical', 'high'], true) ? 'is-risk-high' : 'is-risk-medium' }}">{{ $event['risk_label'] }}</span>
                            <span class="security-event-chip">{{ $event['action_label'] }}</span>
                            <span class="security-event-chip is-ip">IP {{ $event['client_ip'] }}</span>
                        </div>
                        <div class="security-event-path">{{ $event['request_method'] }} · {{ $event['request_path'] }}</div>
                    </article>
                @empty
                    <div class="security-empty">当前还没有最近拦截记录。站点端会在命中拦截后自动更新，不需要手动操作。</div>
                @endforelse
                @if (! empty($security['events']))
                    <div class="security-empty security-empty-filtered" data-security-event-empty hidden>当前筛选条件下没有命中的拦截记录。</div>
                @endif
            </div>
        </section>

        <section class="security-panel">
            <h3 class="security-panel-title">可疑 IP 排行</h3>
            <div class="security-panel-desc">按近 7 天高危次数和命中次数排序，辅助判断异常来源。</div>
            <div class="security-ip-list">
                @forelse ($suspiciousIps as $ip)
                    @php
                        $clientIp = (string) ($ip['client_ip'] ?? '');
                        $canManageIp = filter_var($clientIp, FILTER_VALIDATE_IP) !== false && ! in_array($clientIp, ['127.0.0.1', '::1'], true);
                    @endphp
                    <article class="security-ip-item">
                        <div class="security-ip-main">
                            <div class="security-ip-address">{{ $ip['client_ip'] }}</div>
                            <div class="security-ip-path">{{ $ip['last_request_path'] ?: '暂无路径记录' }}</div>
                        </div>
                        <div class="security-ip-metrics">
                            <span>命中 {{ number_format($ip['hit_count']) }}</span>
                            <span>高危 {{ number_format($ip['high_risk_count']) }}</span>
                        </div>
                        <div class="security-ip-status">
                            <span class="security-event-chip {{ $ip['status'] === 'blocked' ? 'is-risk-high' : 'is-risk-medium' }}">{{ $ip['status_label'] }}</span>
                            @if (!empty($ip['site_policy_label']))
                                <span class="security-event-chip is-ip-policy">{{ $ip['site_policy_label'] }}</span>
                            @endif
                            <span class="security-ip-time">{{ $ip['status'] === 'blocked' && $ip['blocked_until_label'] !== '' ? ('至 ' . $ip['blocked_until_label']) : ('最近 ' . $ip['last_seen_label']) }}</span>
                        </div>
                        <div class="security-ip-detail-link-wrap">
                            <a class="security-ip-detail-link" href="{{ route('admin.security.ip-detail', ['client_ip' => $clientIp]) }}">查看详情</a>
                        </div>
                        @if ($canManageIp && !empty($canManageIpPolicy))
                            <div class="security-ip-actions">
                                @if (empty($ip['is_global_allowlisted']) && empty($ip['is_global_blocklisted']))
                                    <form method="POST" action="{{ route('admin.security.ip-policy.store') }}">
                                        @csrf
                                        <input type="hidden" name="client_ip" value="{{ $clientIp }}">
                                        <input type="hidden" name="action" value="{{ !empty($ip['is_site_allowlisted']) ? 'remove_allow' : 'allow' }}">
                                        <button class="security-ip-action {{ !empty($ip['is_site_allowlisted']) ? 'is-remove' : 'is-allow' }}" type="submit">{{ !empty($ip['is_site_allowlisted']) ? '移白' : '加白' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.security.ip-policy.store') }}">
                                        @csrf
                                        <input type="hidden" name="client_ip" value="{{ $clientIp }}">
                                        <input type="hidden" name="action" value="{{ !empty($ip['is_site_blocklisted']) ? 'remove_block' : 'block' }}">
                                        <button class="security-ip-action {{ !empty($ip['is_site_blocklisted']) ? 'is-remove' : 'is-block' }}" type="submit">{{ !empty($ip['is_site_blocklisted']) ? '移黑' : '拉黑' }}</button>
                                    </form>
                                @endif
                                @if (($ip['status'] ?? '') === 'blocked'
                                    && !empty($ip['blocked_until_label'])
                                    && empty($ip['is_global_allowlisted'])
                                    && empty($ip['is_global_blocklisted'])
                                    && empty($ip['is_site_blocklisted'])
                                    && empty($ip['is_site_allowlisted']))
                                    <form method="POST" action="{{ route('admin.security.ip-policy.store') }}">
                                        @csrf
                                        <input type="hidden" name="client_ip" value="{{ $clientIp }}">
                                        <input type="hidden" name="action" value="release_block">
                                        <button class="security-ip-action is-release" type="submit">解封</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="security-empty">当前还没有可疑 IP 画像。命中拦截后会自动沉淀到这里。</div>
                @endforelse
            </div>
        </section>
    </div>

@endsection
