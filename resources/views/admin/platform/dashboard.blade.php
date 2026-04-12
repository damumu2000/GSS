@extends('layouts.admin')

@section('title', '平台工作台 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 平台工作台')

@php
    $statusLabels = [
        'draft' => '草稿',
        'published' => '已发布',
        'pending' => '待审核',
        'offline' => '已下线',
        'approved' => '已通过',
        'rejected' => '已驳回',
    ];
@endphp

@push('styles')
    <link rel="stylesheet" href="/css/dashboard-insights.css">
@endpush

@section('content')
    @php
        $today = \Illuminate\Support\Carbon::now('Asia/Shanghai');
        $solarDateLabel = $today->translatedFormat('Y年n月j日');
        $lunarMonthNumber = null;
        $lunarDayNumber = null;
        $lunarLabel = null;

        if (class_exists(\IntlDateFormatter::class)) {
            try {
                $lunarMonthFormatter = new \IntlDateFormatter('zh_CN@calendar=chinese', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'Asia/Shanghai', \IntlDateFormatter::TRADITIONAL, 'M');
                $lunarDayFormatter = new \IntlDateFormatter('zh_CN@calendar=chinese', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'Asia/Shanghai', \IntlDateFormatter::TRADITIONAL, 'd');
                $lunarMonthNumber = (int) $lunarMonthFormatter->format($today);
                $lunarDayNumber = (int) $lunarDayFormatter->format($today);
            } catch (\Throwable $exception) {
                $lunarMonthNumber = null;
                $lunarDayNumber = null;
            }
        }

        if ($lunarMonthNumber && $lunarDayNumber) {
            $lunarMonths = [1 => '正月', 2 => '二月', 3 => '三月', 4 => '四月', 5 => '五月', 6 => '六月', 7 => '七月', 8 => '八月', 9 => '九月', 10 => '十月', 11 => '冬月', 12 => '腊月'];
            $lunarDays = [
                1 => '初一', 2 => '初二', 3 => '初三', 4 => '初四', 5 => '初五', 6 => '初六', 7 => '初七', 8 => '初八', 9 => '初九', 10 => '初十',
                11 => '十一', 12 => '十二', 13 => '十三', 14 => '十四', 15 => '十五', 16 => '十六', 17 => '十七', 18 => '十八', 19 => '十九', 20 => '二十',
                21 => '廿一', 22 => '廿二', 23 => '廿三', 24 => '廿四', 25 => '廿五', 26 => '廿六', 27 => '廿七', 28 => '廿八', 29 => '廿九', 30 => '三十',
            ];
            $lunarLabel = '农历' . ($lunarMonths[$lunarMonthNumber] ?? ($lunarMonthNumber . '月')) . ($lunarDays[$lunarDayNumber] ?? ($lunarDayNumber . '日'));
        }

        $hour = (int) $today->format('G');
        $timeGreeting = match (true) {
            $hour < 6 => '凌晨好',
            $hour < 9 => '早上好',
            $hour < 12 => '上午好',
            $hour < 14 => '中午好',
            $hour < 19 => '下午好',
            default => '晚上好',
        };

        $greetings = [
            '记得照顾好自己，别让今天过得太匆忙。',
            '慢一点也没关系，先让自己舒服一点。',
            '天气和心情都值得被认真对待，愿你今天顺顺的。',
            '别太赶，按自己的节奏来就很好。',
            '忙的时候也记得喝口水，歇一歇。',
            '今天也希望你心里松一点，事情顺一点。',
            '先照顾好自己的状态，其他事情都会慢慢跟上。',
            '愿你今天遇到的人和事，都温和一点。',
            '累了就停一下，不必一直绷着。',
            '希望今天的你，做事顺手，心里也轻松。',
            '别给自己太多压力，慢慢来一样很好。',
            '愿你今天有一点好消息，也有一点小轻松。',
        ];
        $operatorName = auth()->user()?->real_name ?? auth()->user()?->name ?? auth()->user()?->username ?? '管理员';
        $operatorId = (int) (auth()->user()?->id ?? 0);
        $greetingIndex = (($today->dayOfYear ?? 1) + $operatorId) % count($greetings);
        $headerGreeting = $greetings[$greetingIndex];
        $headerDateLine = '今天是：' . $solarDateLabel . ($lunarLabel ? '，' . $lunarLabel . '。' : '。');
        $headerGreetingLine = $timeGreeting . '，' . $operatorName . '，' . $headerGreeting;
    @endphp

    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">平台工作台</h2>
            <div class="page-header-desc">{{ $headerDateLine }} {{ $headerGreetingLine }}</div>
        </div>
    </section>

    <section class="insights-content">
        <div class="insights-hero-grid">
            @foreach ($insights['hero'] as $metric)
                <article class="insight-hero-card is-{{ $metric['accent'] }}">
                    <div class="insight-hero-top">
                        <div class="insight-hero-label">{{ $metric['label'] }}</div>
                        <div class="insight-hero-top-right">
                            <div class="insight-hero-icon" aria-hidden="true">
                                @if ($metric['accent'] === 'visits')
                                    <svg viewBox="0 0 24 24">
                                        <circle cx="12" cy="8" r="3.2"></circle>
                                        <path d="M5.5 19.2c0-3.3 2.9-5.5 6.5-5.5s6.5 2.2 6.5 5.5"></path>
                                    </svg>
                                @elseif ($metric['accent'] === 'trend')
                                    <svg viewBox="0 0 24 24">
                                        <path d="M4 18V6"></path>
                                        <path d="M4 18h16"></path>
                                        <path d="m7 14 3-3 3 2 4-5"></path>
                                    </svg>
                                @elseif ($metric['accent'] === 'security')
                                    <svg viewBox="0 0 24 24">
                                        <path d="M12 3 5.5 6v5.2c0 4.1 2.5 7.7 6.5 9.8 4-2.1 6.5-5.7 6.5-9.8V6L12 3Z"></path>
                                        <path d="m9.5 12 1.8 1.8 3.4-3.6"></path>
                                    </svg>
                                @else
                                    <svg viewBox="0 0 24 24">
                                        <path d="M12 3 5.5 6v5.2c0 4.1 2.5 7.7 6.5 9.8 4-2.1 6.5-5.7 6.5-9.8V6L12 3Z"></path>
                                        <path d="M12 9v3.8"></path>
                                        <path d="M12 16h.01"></path>
                                    </svg>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="insight-hero-value">{{ $metric['value'] }}</div>
                    <div class="insight-hero-note">{{ $metric['note'] }}</div>
                </article>
            @endforeach
        </div>

        <div class="insights-board">
            <article class="insight-board-card is-trend-card">
                <div>
                    <h3 class="insight-board-card-title">近 7 天访问趋势</h3>
                </div>
                <div class="insight-trend">
                    @foreach ($insights['trend'] as $trendItem)
                        <div class="insight-trend-item" data-tooltip="{{ $trendItem['label'] }} · {{ number_format($trendItem['value']) }} PV">
                            <div class="insight-trend-bar insight-trend-bar--h-{{ max(16, min(100, (int) $trendItem['height'])) }}"></div>
                            <div class="insight-trend-label">{{ $trendItem['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="insight-board-card is-article-rank-card">
                <div class="insight-board-card-head">
                    <h3 class="insight-board-card-title">文章访问排行</h3>
                    <span class="insight-board-card-tag">30天内</span>
                </div>
                <div class="insight-rank-list">
                    @forelse ($insights['top_articles'] as $index => $article)
                        <div class="insight-rank-item">
                            <div class="insight-rank-no">{{ $index + 1 }}</div>
                            <div class="insight-rank-main">
                                <div class="insight-rank-title">{{ $article['title'] }}</div>
                                <div class="insight-rank-subtitle">{{ $article['channel_name'] }}</div>
                                <div class="insight-rank-bar-track">
                                    <div class="insight-rank-bar insight-rank-bar--w-{{ max(10, min(100, (int) round($article['bar_width']))) }}"></div>
                                </div>
                            </div>
                            <div class="insight-rank-value">{{ number_format($article['view_count']) }}</div>
                        </div>
                    @empty
                        <div class="recent-feed-empty">全系统还没有文章访问数据。</div>
                    @endforelse
                </div>
            </article>

            <article class="insight-board-card is-author-card">
                <div class="insight-board-card-head">
                    <h3 class="insight-board-card-title">作者发布排行</h3>
                    <span class="insight-board-card-tag">本年度</span>
                </div>
                <div class="insight-rank-list">
                    @forelse ($insights['top_authors'] as $index => $author)
                        <div class="insight-rank-item">
                            <div class="insight-rank-no">{{ $index + 1 }}</div>
                            <div class="insight-rank-main">
                                <div class="insight-rank-title">{{ $author['name'] }}</div>
                                <div class="insight-rank-subtitle">已发布 {{ $author['published_count'] }} 篇</div>
                                <div class="insight-rank-bar-track">
                                    <div class="insight-rank-bar insight-rank-bar--w-{{ max(10, min(100, (int) round($author['bar_width']))) }}"></div>
                                </div>
                            </div>
                            <div class="insight-rank-value">{{ $author['total_count'] }} 篇</div>
                        </div>
                    @empty
                        <div class="recent-feed-empty">近 30 天还没有新的发布记录。</div>
                    @endforelse
                </div>
            </article>

            <article class="insight-board-card is-recent-card">
                <div class="panel-heading">
                    <h3 class="insight-board-card-title">近期文章</h3>
                    <div class="panel-heading-actions">
                        <a class="button dashboard-create-button dashboard-action-button" href="{{ route('admin.articles.create') }}">新建文章</a>
                        <a class="button dashboard-create-button dashboard-action-button" href="{{ route('admin.articles.index', ['status' => 'draft']) }}">草稿箱</a>
                    </div>
                </div>
                @if ($recentContents->isNotEmpty())
                    <div class="recent-feed">
                        @foreach ($recentContents as $content)
                            @php
                                $recentContentTitle = trim(((string) ($content->site_name ?? '未分站点')) . ' · ' . (string) $content->title);
                                $recentContentLink = route('site.article', ['id' => $content->id, 'site' => $content->site_key]);
                            @endphp
                            <article class="recent-feed-item is-clickable" data-recent-feed-url="{{ $recentContentLink }}" data-recent-feed-target="_blank">
                                <div class="recent-feed-main" data-tooltip="{{ $recentContentTitle }}">
                                    <a class="recent-feed-title" href="{{ $recentContentLink }}" target="_blank" rel="noopener">
                                        {{ $recentContentTitle }}
                                    </a>
                                </div>
                                <span class="status-badge recent-feed-status {{ $content->status }}">{{ $statusLabels[$content->status] ?? $content->status }}</span>
                                <div class="recent-feed-time">
                                    {{ $content->updated_at ? \Illuminate\Support\Carbon::parse($content->updated_at)->format('m-d') : '--' }}
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="recent-feed-empty">全系统暂无内容记录。</div>
                @endif
            </article>

            <article class="insight-board-card is-assets-card">
                <div class="insight-resource-head">
                    <h3 class="insight-board-card-title">资源使用</h3>
                    <div class="insight-resource-capacity">已用 {{ $insights['assets']['used_size_label'] }} / {{ $insights['assets']['storage_limit_label'] }}</div>
                </div>
                <div class="insight-resource-layout">
                    <div
                        class="insight-ring-shell"
                        data-insight-ring
                        data-default-value="{{ number_format($insights['assets']['chart_total']) }}"
                        data-default-label="全部资源"
                        data-default-detail="{{ $insights['assets']['chart_total_size_label'] }}"
                    >
                        <div class="insight-ring-stage">
                            <svg class="insight-ring-chart" viewBox="0 0 180 180" aria-hidden="true">
                                <circle class="insight-ring-track" cx="90" cy="90" r="78"></circle>
                                @foreach ($insights['assets']['segments'] as $segment)
                                    <circle
                                        class="insight-ring-segment {{ $segment['color_class'] }}"
                                        data-insight-segment
                                        data-segment="{{ $segment['key'] }}"
                                        data-value="{{ number_format($segment['value']) }}"
                                        data-label="{{ $segment['label'] }}"
                                        data-detail="{{ $segment['size_label'] }}"
                                        cx="90"
                                        cy="90"
                                        r="78"
                                        stroke-dasharray="{{ $segment['dasharray'] }}"
                                        stroke-dashoffset="{{ $segment['dashoffset'] }}"
                                    ></circle>
                                @endforeach
                            </svg>
                            <div class="insight-ring-center">
                                <div>
                                    <div class="insight-ring-value" data-insight-ring-value>{{ number_format($insights['assets']['chart_total']) }}</div>
                                    <div class="insight-ring-label" data-insight-ring-label>全部资源</div>
                                    <div class="insight-ring-detail" data-insight-ring-detail>{{ $insights['assets']['chart_total_size_label'] }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="insight-ring-legend">
                            @foreach ($insights['assets']['segments'] as $segment)
                                <div
                                    class="insight-ring-legend-card {{ $segment['color_class'] }}"
                                    tabindex="0"
                                    data-insight-segment
                                    data-segment="{{ $segment['key'] }}"
                                    data-value="{{ number_format($segment['value']) }}"
                                    data-label="{{ $segment['label'] }}"
                                    data-detail="{{ $segment['size_label'] }}"
                                >
                                    <div class="insight-ring-legend-topic">
                                        <div class="insight-ring-legend-top">
                                            <span>{{ $segment['label'] }}</span>
                                        </div>
                                    </div>
                                    <div class="insight-ring-legend-values">
                                        <span class="insight-ring-legend-size is-ratio">占比 {{ $segment['ratio'] }}%</span>
                                        <span class="insight-ring-legend-size is-volume">{{ $segment['size_label'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </article>

            <article class="insight-board-card is-notice-card">
                <div class="insight-board-card-head">
                    <h3 class="insight-board-card-title">官闪闪公告栏</h3>
                    @if ($showPlatformNoticeLink)
                        <a class="insight-board-card-tag" href="{{ route('site.channel', ['slug' => 'platform-notices', 'site' => $platformNoticeSiteKey]) }}" target="_blank">更多</a>
                    @endif
                </div>
                <div class="notice-list">
                    @forelse ($platformNotices as $notice)
                        @php
                            $noticeTitleColorClass = match (strtolower((string) ($notice->title_color ?? ''))) {
                                '#0047ab' => 'is-color-royal-blue',
                                '#2563eb' => 'is-color-bright-blue',
                                '#7c3aed' => 'is-color-violet',
                                '#db2777' => 'is-color-rose',
                                '#059669' => 'is-color-green',
                                '#d97706' => 'is-color-amber',
                                '#dc2626' => 'is-color-red',
                                default => '',
                            };
                        @endphp
                        <article
                            class="notice-item is-clickable"
                            data-notice-trigger
                            data-notice-title="{{ $notice->title }}"
                            data-notice-date="{{ $notice->published_at ? \Illuminate\Support\Carbon::parse($notice->published_at)->format('Y-m-d') : '待发布' }}"
                            data-notice-link="{{ route('site.article', ['id' => $notice->id, 'site' => $platformNoticeSiteKey]) }}"
                            data-notice-summary="{{ trim((string) ($notice->summary ?? '')) }}"
                            data-notice-content-id="platform-notice-content-{{ $notice->id }}"
                        >
                            <div class="notice-item-top">
                                <div class="notice-item-title">
                                    @php
                                        $noticeTitleText = (string) $notice->title;
                                    @endphp
                                    <span
                                        class="notice-item-title-text {{ $noticeTitleColorClass }} @if (! empty($notice->title_bold)) is-bold @endif @if (! empty($notice->title_italic)) is-italic @endif"
                                        data-tooltip="{{ $noticeTitleText }}"
                                    >{{ $noticeTitleText }}</span>
                                    @if (! empty($notice->is_top) || ! empty($notice->is_recommend))
                                        <span class="notice-item-title-flags">
                                            @if (! empty($notice->is_top))
                                                <span class="notice-item-title-flag is-top">顶</span>
                                            @endif
                                            @if (! empty($notice->is_recommend))
                                                <span class="notice-item-title-flag is-recommend">精</span>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                                <div class="notice-item-date">{{ $notice->published_at ? \Illuminate\Support\Carbon::parse($notice->published_at)->format('Y-m-d') : '待发布' }}</div>
                            </div>
                            <template id="platform-notice-content-{{ $notice->id }}">
                                {!! (string) ($notice->content ?? '') !!}
                            </template>
                        </article>
                    @empty
                        <article class="notice-item">
                            <div class="notice-item-title">当前暂无官闪闪公告。</div>
                        </article>
                    @endforelse
                </div>
            </article>
        </div>
    </section>

    <div class="notice-modal" id="platform-notice-modal" hidden>
        <div class="notice-modal-backdrop" data-notice-close></div>
        <div class="notice-modal-shell" data-notice-shell>
            <div class="notice-modal-panel" role="dialog" aria-modal="true" aria-labelledby="platform-notice-modal-title">
                <div class="notice-modal-scroll">
                    <div class="notice-modal-inner">
                        <div class="notice-modal-topbar">
                        <div class="notice-modal-heading">
                            <h3 class="notice-modal-title" id="platform-notice-modal-title">官闪闪公告栏</h3>
                            <div class="notice-modal-meta">
                                <span class="notice-modal-chip">官闪闪公告栏</span>
                                <span id="platform-notice-modal-date">--</span>
                                </div>
                            </div>
                            <button class="notice-modal-close" type="button" data-notice-close aria-label="关闭公告弹窗">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M6 6l12 12"></path>
                                    <path d="M18 6 6 18"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="notice-modal-frame">
                            <div class="notice-modal-summary" id="platform-notice-modal-summary" hidden></div>
                            <div class="notice-modal-content" id="platform-notice-modal-content">暂无公告内容。</div>
                        </div>
                        <div class="notice-modal-actions">
                            <a class="button secondary" id="platform-notice-modal-link" href="#" target="_blank" rel="noopener">前台查看全文</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="/js/dashboard-insights.js"></script>
@endpush
