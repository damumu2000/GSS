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

    $todayText = now()->format('Y年m月d日');
    $weekdayText = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'][now()->dayOfWeek];
@endphp

@push('styles')
    <style>
        .page-header {
            display: flex;
            justify-content: flex-start;
            gap: 20px;
            align-items: flex-start;
            padding: 24px 32px;
            margin: -28px -28px 24px;
            background: #ffffff;
            border-bottom: 1px solid #f0f0f0;
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

        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .dashboard-card {
            position: relative;
            overflow: hidden;
            padding: 18px;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
        }

        .dashboard-card::after {
            content: "";
            position: absolute;
            right: -12px;
            top: -14px;
            width: 92px;
            height: 92px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.04);
        }

        .dashboard-card-label {
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.5;
        }

        .dashboard-card-value {
            margin-top: 12px;
            color: #262626;
            font-size: 28px;
            line-height: 1.2;
            font-weight: 700;
        }

        .dashboard-card-foot {
            margin-top: 6px;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.6;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.18fr 0.92fr;
            gap: 18px;
            align-items: start;
        }

        .dashboard-panel {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
            padding: 20px;
        }

        .dashboard-top-panel {
            min-height: 476px;
            display: flex;
            flex-direction: column;
        }

        .dashboard-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f0f0f0;
        }

        .dashboard-panel-title {
            margin: 0;
            color: #262626;
            font-size: 16px;
            line-height: 1.4;
            font-weight: 700;
        }

        .dashboard-panel-subtitle {
            margin-top: 6px;
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.7;
        }

        .dashboard-panel-header.is-plain {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .panel-title {
            margin: 0;
            color: #262626;
            font-size: 16px;
            line-height: 1.4;
            font-weight: 700;
        }

        .panel-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .content-table-wrap {
            overflow-x: auto;
        }

        .recent-feed {
            display: grid;
            gap: 0;
            margin-top: 16px;
            flex: 1 1 auto;
            align-content: start;
        }

        .recent-feed-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            gap: 18px;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid #f0f0f0;
            transition: transform 0.18s ease, color 0.18s ease;
        }

        .recent-feed-item:hover {
            transform: translateX(2px);
        }

        .recent-feed-main {
            min-width: 0;
            display: grid;
            gap: 8px;
        }

        .recent-feed-title {
            min-width: 0;
            color: #1f2937;
            font-size: 15px;
            line-height: 1.75;
            font-weight: 400;
            text-decoration: none;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .recent-feed-title:hover {
            color: var(--primary, #0047AB);
            text-decoration: none;
        }

        .recent-feed-item:hover .recent-feed-title {
            color: var(--primary, #0047AB);
        }

        .recent-feed-time {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 72px;
            padding: 0 2px;
            color: #98a2b3;
            font-size: 15px;
            line-height: 1.4;
            font-weight: 600;
            white-space: nowrap;
            text-align: right;
        }

        .recent-feed-status {
            justify-self: end;
            white-space: nowrap;
        }

        .recent-feed-empty {
            margin-top: 16px;
            padding: 32px 18px;
            border-radius: 14px;
            background: #fafafa;
            color: #8c8c8c;
            font-size: 14px;
            text-align: center;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 0 10px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 12px;
            font-weight: 600;
            line-height: 24px;
        }

        .status-badge.published {
            background: #f6ffed;
            color: #389e0d;
        }

        .status-badge.pending {
            background: #fff7e6;
            color: #d48806;
        }

        .status-badge.draft,
        .status-badge.offline {
            background: #f5f5f5;
            color: #595959;
        }

        .panel-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .panel-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #4b5563;
            font-size: 13px;
            line-height: 1;
            font-weight: 600;
            text-decoration: none;
        }

        .panel-link:hover {
            color: #262626;
        }

        .notice-list {
            display: grid;
            gap: 12px;
            margin-top: 14px;
            flex: 1 1 auto;
            align-content: start;
        }

        .notice-item {
            display: grid;
            gap: 6px;
            padding: 14px 16px;
            border-radius: 12px;
            background: #fafafa;
            border: 1px solid rgba(226, 232, 240, 0.88);
            transition: transform 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease;
        }

        .notice-item:hover {
            transform: translateY(-1px);
            background: #f5f7fb;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.05);
        }

        .notice-item-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .content-status {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 0 10px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 12px;
            line-height: 24px;
            font-weight: 600;
        }

        .notice-item-title {
            color: #262626;
            font-size: 14px;
            font-weight: 400;
            min-width: 0;
        }

        .notice-item-title-text {
            min-width: 0;
            display: inline;
            word-break: break-word;
        }

        .notice-item-title-flags {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: 8px;
            vertical-align: middle;
        }

        .notice-item-title-flag {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            padding: 0 8px;
            border-radius: 999px;
            background: rgba(0, 71, 171, 0.08);
            color: var(--primary, #0047AB);
            font-size: 12px;
            line-height: 1;
            font-weight: 700;
        }

        .notice-item-title-flag.is-top {
            background: rgba(245, 158, 11, 0.12);
            color: #b45309;
        }

        .notice-item-title-flag.is-recommend {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
        }

        .notice-item-title a {
            color: inherit;
            text-decoration: none;
        }

        .notice-item-title a:hover {
            color: var(--primary, #0047AB);
            text-decoration: none;
        }

        .notice-item:hover .notice-item-title a {
            color: var(--primary, #0047AB);
        }

        .notice-item-date {
            color: #8c8c8c;
            font-size: 12px;
            white-space: nowrap;
        }

        .notice-item-summary {
            color: #8b94a7;
            font-size: 12px;
            line-height: 1.75;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .notice-modal[hidden] {
            display: none;
        }

        .notice-modal {
            position: fixed;
            inset: 0;
            z-index: 2800;
        }

        .notice-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.48);
            backdrop-filter: blur(8px) saturate(110%);
            opacity: 0;
            transition: opacity 0.24s ease;
        }

        .notice-modal.is-open .notice-modal-backdrop {
            opacity: 1;
        }

        .notice-modal-shell {
            position: relative;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .notice-modal-panel {
            position: relative;
            width: min(860px, calc(100vw - 40px));
            max-height: calc(100vh - 48px);
            overflow: hidden;
            border-radius: 30px;
            border: 1px solid rgba(220, 229, 239, 0.95);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.99) 0%, rgba(248, 250, 252, 0.98) 100%);
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.22), inset 0 1px 0 rgba(255, 255, 255, 0.75);
            transform: scale(0.92) translateY(18px);
            opacity: 0;
            transition: transform 0.28s cubic-bezier(.2,.8,.2,1), opacity 0.22s ease;
        }

        .notice-modal-scroll {
            max-height: calc(100vh - 48px);
            overflow: auto;
            padding-right: 14px;
            scrollbar-width: auto;
            scrollbar-color: rgba(148, 163, 184, 0.96) transparent;
        }

        .notice-modal-scroll::-webkit-scrollbar {
            width: 16px;
        }

        .notice-modal-scroll::-webkit-scrollbar-track {
            margin: 44px 8px 44px 0;
            border: 4px solid transparent;
            border-radius: 999px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.78) 0%, rgba(241, 245, 249, 0.92) 100%);
            background-clip: padding-box;
            box-shadow: inset 0 1px 2px rgba(255, 255, 255, 0.72), inset 0 -1px 3px rgba(148, 163, 184, 0.12), inset 0 0 0 1px rgba(191, 219, 254, 0.4);
        }

        .notice-modal-scroll::-webkit-scrollbar-thumb {
            border: 4px solid transparent;
            border-radius: 999px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.34) 0%, rgba(255, 255, 255, 0) 30%), linear-gradient(180deg, rgba(203, 213, 225, 0.98) 0%, rgba(148, 163, 184, 0.98) 100%);
            background-clip: padding-box;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.42), 0 4px 12px rgba(15, 23, 42, 0.10);
        }

        .notice-modal-scroll::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.32) 0%, rgba(255, 255, 255, 0) 24%), linear-gradient(180deg, rgba(148, 163, 184, 0.98) 0%, rgba(100, 116, 139, 1) 100%);
            background-clip: padding-box;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.45), 0 6px 14px rgba(15, 23, 42, 0.14);
        }

        .notice-modal-scroll::-webkit-scrollbar-corner {
            background: transparent;
        }

        .notice-modal.is-open .notice-modal-panel {
            transform: scale(1) translateY(0);
            opacity: 1;
        }

        .notice-modal-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 30px;
            background: linear-gradient(135deg, rgba(0, 71, 171, 0.05) 0%, rgba(0, 71, 171, 0) 42%), repeating-linear-gradient(135deg, rgba(148, 163, 184, 0.06) 0, rgba(148, 163, 184, 0.06) 1px, transparent 1px, transparent 22px);
            pointer-events: none;
        }

        .notice-modal-inner {
            position: relative;
            z-index: 1;
            padding: 30px 32px 34px;
        }

        .notice-modal-topbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 22px;
        }

        .notice-modal-kicker {
            color: rgba(0, 71, 171, 0.78);
            font-size: 11px;
            line-height: 1;
            letter-spacing: 0.16em;
            font-weight: 700;
            text-transform: uppercase;
        }

        .notice-modal-title {
            margin: 12px 0 0;
            color: #1f2937;
            font-size: clamp(26px, 4vw, 34px);
            line-height: 1.35;
            font-weight: 800;
        }

        .notice-modal-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-top: 12px;
            color: #8b94a7;
            font-size: 13px;
            line-height: 1.7;
            font-weight: 600;
        }

        .notice-modal-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 28px;
            padding: 0 12px;
            border-radius: 999px;
            background: rgba(0, 71, 171, 0.08);
            color: var(--primary, #0047AB);
            font-size: 12px;
            font-weight: 700;
        }

        .notice-modal-close {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            border: 1px solid rgba(213, 221, 232, 0.95);
            background: rgba(255, 255, 255, 0.92);
            color: #667085;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease;
        }

        .notice-modal-close:hover {
            background: #f8fafc;
            color: #1f2937;
            transform: translateY(-1px);
        }

        .notice-modal-close svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 1.8;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .notice-modal-frame {
            padding: 22px 24px;
            border-radius: 24px;
            border: 1px solid rgba(220, 229, 239, 0.95);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(250, 252, 255, 0.95) 100%);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
        }

        .notice-modal-summary {
            color: #526071;
            font-size: 15px;
            line-height: 1.9;
            margin-bottom: 18px;
        }

        .notice-modal-content {
            color: #1f2937;
            font-size: 15px;
            line-height: 1.95;
        }

        .notice-modal-content p {
            margin: 0 0 16px;
        }

        .notice-modal-content p:last-child {
            margin-bottom: 0;
        }

        .notice-modal-content :first-child {
            margin-top: 0;
        }

        .notice-modal-content :last-child {
            margin-bottom: 0;
        }

        .notice-modal-content img {
            max-width: 100%;
            height: auto;
            border-radius: 18px;
            display: block;
            margin: 24px auto;
        }

        .notice-modal-content figure {
            margin: 24px 0;
        }

        .notice-modal-content figure img {
            margin: 0 auto;
        }

        .notice-modal-content p > img,
        .notice-modal-content p > a > img {
            margin: 24px auto;
        }

        .notice-modal-content p + img,
        .notice-modal-content img + p,
        .notice-modal-content p + figure,
        .notice-modal-content figure + p,
        .notice-modal-content p > img + br,
        .notice-modal-content p > a + br {
            margin-top: 24px;
        }

        .notice-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 22px;
        }

        .notice-modal-actions .button.secondary {
            min-height: 40px;
            border-radius: 12px;
        }

        .dashboard-stack {
            display: grid;
            gap: 20px;
        }

        .empty-state {
            padding: 36px 24px;
            color: #8c8c8c;
            text-align: center;
        }

        @media (max-width: 1280px) {
            .dashboard-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .page-header {
                margin: -24px -18px 20px;
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
            }

            .dashboard-cards {
                grid-template-columns: 1fr;
            }

            .notice-item {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .dashboard-panel-header,
            .panel-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .panel-actions {
                justify-content: flex-start;
            }

            .notice-item-top,
            .recent-feed-item {
                grid-template-columns: 1fr;
            }

            .recent-feed-status {
                justify-self: start;
            }

            .recent-feed-time {
                justify-content: flex-start;
                text-align: left;
                min-width: 0;
            }
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">平台工作台</h2>
            <div class="page-header-desc">当前聚焦站点：{{ $currentSite->name ?? '未选择站点' }}。这里集中查看平台概览、站点动态与常用主控入口。</div>
        </div>
    </section>

    <section class="dashboard-cards">
        <article class="dashboard-card" data-icon="站">
            <div class="dashboard-card-label">站点总数</div>
            <div class="dashboard-card-value">{{ $stats['site_count'] }}</div>
            <div class="dashboard-card-foot">当前账号可管理的站点总量</div>
        </article>
        <article class="dashboard-card" data-icon="栏">
            <div class="dashboard-card-label">当前站点栏目数</div>
            <div class="dashboard-card-value">{{ $stats['channel_count'] }}</div>
            <div class="dashboard-card-foot">包括导航栏目与列表栏目</div>
        </article>
        <article class="dashboard-card" data-icon="文">
            <div class="dashboard-card-label">当前站点内容数</div>
            <div class="dashboard-card-value">{{ $stats['content_count'] }}</div>
            <div class="dashboard-card-foot">单页面与文章内容合计</div>
        </article>
        <article class="dashboard-card" data-icon="附">
            <div class="dashboard-card-label">当前站点附件数</div>
            <div class="dashboard-card-value">{{ $stats['attachment_count'] }}</div>
            <div class="dashboard-card-foot">资源中心已上传的文件数量</div>
        </article>
    </section>

    <section class="dashboard-grid">
        <article class="dashboard-panel dashboard-top-panel">
            <div class="panel-heading">
                <h3 class="panel-title">近期文章</h3>
            </div>

            @if ($recentContents->isEmpty())
                <div class="recent-feed-empty">当前站点暂无内容记录。</div>
            @else
                <div class="recent-feed">
                    @foreach ($recentContents as $content)
                        <article class="recent-feed-item" onclick="window.location.href='{{ $content->type === 'page' ? route('admin.pages.edit', $content->id) : route('admin.articles.edit', $content->id) }}'" style="cursor:pointer;">
                            <div class="recent-feed-main">
                                <a class="recent-feed-title" href="{{ $content->type === 'page' ? route('admin.pages.edit', $content->id) : route('admin.articles.edit', $content->id) }}" onclick="event.stopPropagation()">
                                    {{ ($currentSite->name ?? '当前站点') . ' · ' . $content->title }}
                                </a>
                            </div>
                            <span class="status-badge recent-feed-status {{ $content->status }}">{{ $statusLabels[$content->status] ?? $content->status }}</span>
                            <div class="recent-feed-time">
                                {{ $content->updated_at ? \Illuminate\Support\Carbon::parse($content->updated_at)->format('m-d') : '--' }}
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </article>

        <article class="dashboard-panel dashboard-top-panel">
            <div class="dashboard-panel-header is-plain">
                <div>
                    <h3 class="dashboard-panel-title">官闪闪公告栏</h3>
                </div>
            </div>

            <div class="notice-list">
                @forelse ($platformNotices as $notice)
                    @php
                        $noticeDate = $notice->published_at
                            ? \Illuminate\Support\Carbon::parse($notice->published_at)->format('Y-m-d')
                            : '暂无日期';
                        $noticeTitleStyle = [];

                        if (! empty($notice->title_color)) {
                            $noticeTitleStyle[] = 'color: '.$notice->title_color;
                        }

                        if (! empty($notice->title_bold)) {
                            $noticeTitleStyle[] = 'font-weight: 700';
                        }

                        if (! empty($notice->title_italic)) {
                            $noticeTitleStyle[] = 'font-style: italic';
                        }
                    @endphp
                    <article
                        class="notice-item"
                        style="cursor:pointer;"
                        data-notice-trigger
                        data-notice-title="{{ $notice->title }}"
                        data-notice-date="{{ $noticeDate }}"
                        data-notice-link="{{ route('site.article', ['id' => $notice->id, 'site' => $platformNoticeSiteKey]) }}"
                        data-notice-summary="{{ trim((string) ($notice->summary ?? '')) }}"
                        data-notice-content-id="platform-dashboard-notice-content-{{ $notice->id }}"
                    >
                        <div class="notice-item-top">
                            <div class="notice-item-title">
                                <span class="notice-item-title-text" style="{{ implode('; ', $noticeTitleStyle) }}">{{ $notice->title }}</span>
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
                            <div class="notice-item-date">{{ $noticeDate }}</div>
                        </div>
                        @if (trim((string) ($notice->summary ?? '')) !== '')
                            <div class="notice-item-summary">{{ $notice->summary }}</div>
                        @endif
                        <template id="platform-dashboard-notice-content-{{ $notice->id }}">
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
    </section>

    <div class="notice-modal" id="platform-dashboard-notice-modal" hidden>
        <div class="notice-modal-backdrop" data-notice-close></div>
        <div class="notice-modal-shell" data-notice-shell>
            <div class="notice-modal-panel" role="dialog" aria-modal="true" aria-labelledby="platform-dashboard-notice-modal-title">
                <div class="notice-modal-scroll">
                    <div class="notice-modal-inner">
                        <div class="notice-modal-topbar">
                            <div>
                                <div class="notice-modal-kicker">Guanshanshan Notice</div>
                                <h3 class="notice-modal-title" id="platform-dashboard-notice-modal-title">官闪闪公告栏</h3>
                                <div class="notice-modal-meta">
                                    <span class="notice-modal-chip">官闪闪公告栏</span>
                                    <span id="platform-dashboard-notice-modal-date">--</span>
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
                            <div class="notice-modal-summary" id="platform-dashboard-notice-modal-summary" hidden></div>
                            <div class="notice-modal-content" id="platform-dashboard-notice-modal-content">暂无公告内容。</div>
                        </div>
                        <div class="notice-modal-actions">
                            <a class="button secondary" id="platform-dashboard-notice-modal-link" href="#" target="_blank" rel="noopener">前台查看全文</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const noticeModal = document.getElementById('platform-dashboard-notice-modal');
            const noticeModalTitle = document.getElementById('platform-dashboard-notice-modal-title');
            const noticeModalDate = document.getElementById('platform-dashboard-notice-modal-date');
            const noticeModalSummary = document.getElementById('platform-dashboard-notice-modal-summary');
            const noticeModalContent = document.getElementById('platform-dashboard-notice-modal-content');
            const noticeModalLink = document.getElementById('platform-dashboard-notice-modal-link');
            let previousBodyOverflow = '';

            const closeNoticeModal = () => {
                if (!noticeModal || noticeModal.hidden) {
                    return;
                }

                noticeModal.classList.remove('is-open');
                window.setTimeout(() => {
                    noticeModal.hidden = true;
                    document.body.style.overflow = previousBodyOverflow;
                }, 220);
            };

            const openNoticeModal = (payload) => {
                if (!noticeModal || !noticeModalTitle || !noticeModalDate || !noticeModalSummary || !noticeModalContent || !noticeModalLink) {
                    return;
                }

                noticeModalTitle.textContent = payload.title || '官闪闪公告栏';
                noticeModalDate.textContent = payload.date || '--';

                if (payload.summary) {
                    noticeModalSummary.hidden = false;
                    noticeModalSummary.textContent = payload.summary;
                } else {
                    noticeModalSummary.hidden = true;
                    noticeModalSummary.textContent = '';
                }

                noticeModalContent.innerHTML = payload.contentHtml && payload.contentHtml.trim() !== ''
                    ? payload.contentHtml
                    : '<p>暂无公告内容。</p>';
                noticeModalLink.href = payload.link || '#';
                noticeModal.hidden = false;
                previousBodyOverflow = document.body.style.overflow || '';
                document.body.style.overflow = 'hidden';
                window.requestAnimationFrame(() => {
                    noticeModal.classList.add('is-open');
                });
            };

            document.querySelectorAll('[data-notice-trigger]').forEach((item) => {
                item.addEventListener('click', () => {
                    const templateId = item.getAttribute('data-notice-content-id');
                    const contentTemplate = templateId ? document.getElementById(templateId) : null;

                    openNoticeModal({
                        title: item.getAttribute('data-notice-title') || '官闪闪公告栏',
                        date: item.getAttribute('data-notice-date') || '--',
                        link: item.getAttribute('data-notice-link') || '#',
                        summary: item.getAttribute('data-notice-summary') || '',
                        contentHtml: contentTemplate ? contentTemplate.innerHTML.trim() : '',
                    });
                });
            });

            noticeModal?.querySelectorAll('[data-notice-close]').forEach((element) => {
                element.addEventListener('click', closeNoticeModal);
            });

            noticeModal?.querySelector('[data-notice-shell]')?.addEventListener('click', (event) => {
                if (event.target === event.currentTarget) {
                    closeNoticeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeNoticeModal();
                }
            });
        })();
    </script>
@endpush
