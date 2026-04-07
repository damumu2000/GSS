<!DOCTYPE html>
<html lang="zh-CN">
<head>
    @php
        $theme = $settings['theme_profile'] ?? [];
        $themeCode = $settings['theme'] ?? 'default';
        $heroNoticeImage = trim((string) ($settings['notice_image'] ?? ''));
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $settings['name'] }} - {{ $site->name }}</title>
    <style>
        :root {
            --primary: {{ $theme['primary'] ?? '#0050b3' }};
            --primary-deep: {{ $theme['primary_deep'] ?? '#003f8f' }};
            --primary-soft: {{ $theme['primary_soft'] ?? 'rgba(0,80,179,0.08)' }};
            --primary-border: {{ $theme['primary_border'] ?? 'rgba(0,80,179,0.08)' }};
            --bg: {{ $theme['bg'] ?? '#f5f7fa' }};
            --panel: {{ $theme['panel'] ?? '#ffffff' }};
            --text: {{ $theme['text'] ?? '#1f2937' }};
            --muted: {{ $theme['muted'] ?? '#8c8c8c' }};
            --line: {{ $theme['line'] ?? '#e5e7eb' }};
            --success: {{ $theme['success'] ?? '#059669' }};
            --warning: {{ $theme['warning'] ?? '#b45309' }};
            --hero-gradient: {{ $theme['hero_gradient'] ?? 'linear-gradient(135deg, rgba(0,80,179,0.08) 0%, rgba(255,255,255,0.96) 100%)' }};
            --hero-border: {{ $theme['hero_border'] ?? 'rgba(0,80,179,0.08)' }};
            --badge-bg: {{ $theme['badge_bg'] ?? '#f5f7fa' }};
            --badge-text: {{ $theme['badge_text'] ?? '#667085' }};
            --reply-bg: {{ $theme['reply_bg'] ?? '#f8fafc' }};
            --reply-text: {{ $theme['reply_text'] ?? '#475467' }};
            --flash-bg: {{ $theme['flash_bg'] ?? 'rgba(16,185,129,0.10)' }};
        }
        * { box-sizing: border-box; }
        body { margin: 0; padding-bottom: 48px; font-family: "PingFang SC","Microsoft YaHei",sans-serif; background: var(--bg); color: var(--text); }
        a { color: inherit; text-decoration: none; }
        .shell { width: min(1120px, calc(100% - 32px)); margin: 0 auto; }
        .header {
            padding: 24px 0 18px;
        }
        .header-top { display:flex; justify-content:space-between; gap:16px; align-items:center; }
        .site-name { font-size: 14px; color: var(--muted); line-height: 1.7; }
        .board-title { margin: 6px 0 0; display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 30px; line-height: 1.3; font-weight: 700; }
        .board-site-inline { color: inherit; font-size: inherit; font-weight: inherit; }
        .board-separator { color: inherit; font-weight: inherit; line-height: 1; }
        .board-name { color: inherit; font-size: 24px; font-weight: 700; line-height: 1.2; }
        .hero {
            position: relative;
            overflow: hidden;
            padding: 28px 32px;
            border-radius: 20px;
            background: var(--hero-gradient);
            border: 1px solid var(--hero-border);
        }
        .hero-accent { display: none; }
        .hero-main {
            position: relative;
            z-index: 1;
            min-width: 0;
            max-width: 760px;
        }
        .hero.has-media .hero-main {
            max-width: min(66%, 760px);
        }
        .hero-media {
            display: none;
            position: absolute;
            top: 18px;
            right: 18px;
            bottom: 18px;
            width: min(34%, 360px);
            border-radius: 18px;
            overflow: hidden;
            pointer-events: none;
        }
        .hero.has-media .hero-media {
            display: block;
        }
        .hero-media::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: var(--hero-media-image);
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center;
            opacity: 0.92;
            -webkit-mask-image: linear-gradient(90deg, transparent 0%, rgba(0, 0, 0, 0.26) 22%, rgba(0, 0, 0, 0.86) 58%, #000 100%);
            mask-image: linear-gradient(90deg, transparent 0%, rgba(0, 0, 0, 0.26) 22%, rgba(0, 0, 0, 0.86) 58%, #000 100%);
        }
        .hero-media::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.86) 0%, rgba(255, 255, 255, 0.28) 42%, rgba(255, 255, 255, 0.02) 100%);
        }
        .hero-kicker {
            display: none;
            width: fit-content;
            min-height: 28px;
            padding: 0 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.74);
            color: var(--primary);
            font-size: 12px;
            line-height: 28px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }
        .hero-notice { margin-top: 12px; color: #475467; font-size: 15px; line-height: 1.9; max-width: 760px; }
        .hero-notice p { margin: 0 0 10px; }
        .hero-notice p:last-child { margin-bottom: 0; }
        .hero-notice ul,
        .hero-notice ol { margin: 10px 0 10px 20px; padding: 0; }
        .hero-notice li { margin: 4px 0; }
        .hero-notice a { color: var(--primary); }
        .hero-actions { margin-top: 20px; display:flex; gap:12px; flex-wrap:wrap; }
        .button {
            display:inline-flex; align-items:center; justify-content:center; min-height: 42px; padding: 0 18px;
            border-radius: 999px; border: 1px solid transparent; background: var(--primary); color:#fff; font-size:14px; font-weight:700;
        }
        .button:hover { background: var(--primary-deep); }
        .button.secondary { background:#fff; color:var(--primary); border-color: var(--primary-border); }
        .button.secondary:hover { background: #fff; color: var(--primary-deep); border-color: var(--primary-border); }
        .flash { margin-top: 18px; padding: 14px 16px; border-radius: 14px; background: var(--flash-bg); color: var(--success); font-size: 14px; font-weight: 700; }
        .list { display:grid; gap: 16px; margin: 24px 0 40px; }
        .card {
            position: relative;
            padding: 22px 24px;
            border-radius: 18px;
            background: var(--panel);
            border: 1px solid var(--line);
            box-shadow: 0 8px 18px rgba(15,23,42,0.04);
        }
        .card-head { display:flex; justify-content:space-between; gap:14px; align-items:flex-start; }
        .card-title { display:flex; align-items:center; gap:18px; flex-wrap:wrap; color:#98a2b3; font-size:13px; line-height:1.7; }
        .card-name { color: var(--text); font-size:16px; line-height:1.6; font-weight:700; }
        .card-id { color: #111827; font-size: 15px; line-height: 1.6; font-weight: 500; }
        .card-time { color:#8c8c8c; font-size:13px; line-height:1.7; }
        .card-summary { margin-top: 18px; display:flex; gap:16px; align-items:flex-end; justify-content:space-between; color:#344054; font-size:16px; line-height:1.95; font-weight:400; }
        .card-summary-text { flex:1; min-width:0; }
        .card-reply { margin-top: 14px; padding: 14px 16px; border-radius: 14px; background: var(--reply-bg); color:var(--reply-text); font-size:13px; line-height:1.85; }
        .card-reply-label { color:#1f2937; font-weight:700; }
        .card-reply-time { margin-top: 8px; color:#98a2b3; font-size:12px; line-height:1.7; }
        .card-actions { flex-shrink:0; display:inline-flex; align-items:flex-end; }
        .card-link { color: var(--primary); font-size: 15px; line-height: 1.7; font-weight: 700; }
        .card-link:hover { color: var(--primary-deep); }
        .badge { display:inline-flex; align-items:center; min-height:26px; padding:0 12px; border-radius:999px; background:#f3f4f6; color:#6b7280; font-size:12px; font-weight:700; }
        .badge.success { background: rgba(16,185,129,0.10); color: #059669; }
        .badge.warning { background: rgba(245,158,11,0.12); color: #b45309; }
        .theme-china-red .hero {
            position: relative;
            overflow: hidden;
            border-radius: 22px;
        }
        .theme-china-red .hero::before {
            content: '';
            position: absolute;
            inset: 0 auto auto 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #b22222 0%, #d4a64f 100%);
        }
        .theme-china-red .hero-kicker {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 246, 244, 0.96);
            color: #8f2d1f;
            border: 1px solid rgba(178, 34, 34, 0.12);
        }
        .theme-china-red .card {
            border-top: 3px solid rgba(178, 34, 34, 0.56);
            box-shadow: 0 10px 22px rgba(122, 18, 18, 0.05);
        }
        .theme-china-red .badge {
            border-radius: 10px;
        }
        .theme-china-red .button {
            border-radius: 12px;
            letter-spacing: 0.03em;
        }
        .theme-china-red .card-reply {
            border-left: 4px solid rgba(178, 34, 34, 0.44);
            border-radius: 12px;
            background: transparent;
        }
        .theme-education-green .hero {
            display: grid;
            grid-template-columns: 8px minmax(0, 1fr);
            gap: 18px;
            align-items: stretch;
        }
        .theme-education-green .hero-accent {
            display: block;
            border-radius: 999px;
            background: linear-gradient(180deg, rgba(47, 143, 87, 0.95) 0%, rgba(221, 239, 227, 0.88) 100%);
        }
        .theme-education-green .card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 22px;
            bottom: 22px;
            width: 4px;
            border-radius: 999px;
            background: rgba(47, 143, 87, 0.18);
        }
        .theme-education-green .button {
            border-radius: 999px;
            box-shadow: 0 8px 18px rgba(47, 143, 87, 0.12);
        }
        .theme-education-green .button.secondary {
            box-shadow: none;
        }
        .theme-education-green .card-reply {
            border-radius: 18px;
            background: transparent;
        }
        .theme-vibrant-orange .hero {
            position: relative;
            overflow: hidden;
        }
        .theme-vibrant-orange .hero::before {
            content: '';
            position: absolute;
            width: 220px;
            height: 220px;
            right: -90px;
            top: -110px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(242, 140, 40, 0.18) 0%, rgba(242, 140, 40, 0) 72%);
        }
        .theme-vibrant-orange .hero-kicker {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 247, 239, 0.96);
            color: #c96a10;
            border: 1px solid rgba(242, 140, 40, 0.16);
        }
        .theme-vibrant-orange .card {
            border-radius: 22px;
            box-shadow: 0 12px 26px rgba(201, 106, 16, 0.06);
        }
        .theme-vibrant-orange .card-head {
            align-items: center;
        }
        .theme-vibrant-orange .button {
            border-radius: 14px;
            box-shadow: 0 10px 22px rgba(242, 140, 40, 0.18);
        }
        .theme-vibrant-orange .button.secondary {
            box-shadow: none;
        }
        .theme-vibrant-orange .card-reply {
            border-radius: 18px;
            border: 1px solid rgba(242, 140, 40, 0.16);
            background: transparent;
        }
        .pagination {
            margin-top: 8px;
            padding: 18px 0 12px;
        }
        .pagination nav {
            display: flex;
            justify-content: center;
        }
        .pagination-shell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            padding: 12px 14px;
            border-radius: 18px;
            background: rgba(255,255,255,0.72);
            border: 1px solid rgba(229, 231, 235, 0.9);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }
        .pagination-pages {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .pagination-button,
        .pagination-page,
        .pagination-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            color: #4b5563;
            font-size: 13px;
            line-height: 1;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.18s ease;
        }
        .pagination-page { padding: 0; }
        .pagination-button:hover,
        .pagination-page:hover {
            transform: translateY(-1px);
            background: #f9fafb;
            border-color: #d1d5db;
        }
        .pagination-page.is-active,
        .pagination-page.is-active:visited {
            background: #374151;
            border-color: #374151;
            color: #ffffff;
        }
        .pagination-button.is-disabled,
        .pagination-page.is-disabled,
        .pagination-ellipsis {
            color: #c0c4cc;
            cursor: default;
            pointer-events: none;
        }
        .empty { padding: 44px 24px; text-align:center; color:var(--muted); border:1px dashed var(--line); border-radius:18px; background: var(--panel); }
        @media (max-width: 768px) {
            .shell { width: calc(100% - 20px); }
            .hero { padding: 22px 18px; }
            .hero.has-media .hero-main { max-width: 100%; }
            .hero-media { display: none !important; }
            .theme-education-green .hero {
                grid-template-columns: 1fr;
                gap: 14px;
            }
            .theme-education-green .hero-accent {
                min-height: 6px;
            }
            .board-title { font-size: 24px; }
            .board-name { font-size: 20px; }
            .card { padding: 18px; }
            .card-head { flex-direction: column; }
            .card-title { gap: 10px 14px; }
            .card-summary { font-size: 15px; line-height: 1.9; align-items:flex-start; flex-direction:column; }
            .card-actions { align-self:flex-end; }
            body { padding-bottom: 36px; }
        }
    </style>
</head>
<body class="theme-{{ $themeCode }}">
    <div class="shell">
        <header class="header">
            <div class="header-top">
                <div>
                    <h1 class="board-title">
                        <span class="board-site-inline">{{ $site->name }}</span>
                        <span class="board-separator">·</span>
                        <span class="board-name">{{ $settings['name'] }}</span>
                    </h1>
                </div>
            </div>
        </header>

        <section
            class="hero{{ $heroNoticeImage !== '' ? ' has-media' : '' }}"
            @if ($heroNoticeImage !== '')
                style="--hero-media-image:url('{{ e($heroNoticeImage) }}');"
            @endif
        >
            <div class="hero-accent" aria-hidden="true"></div>
            <div class="hero-main">
                <div class="hero-kicker">主题预览</div>
                <div style="font-size:14px;color:var(--primary);font-weight:700;margin-top:{{ $themeCode === 'default' ? '0' : '10px' }};">发布须知</div>
                <div class="hero-notice">{!! $settings['notice'] !!}</div>
                <div class="hero-actions">
                    <a class="button" href="{{ route('site.guestbook.create', $siteQuery) }}">我要留言</a>
                    <a class="button secondary" href="{{ route('site.home', $siteQuery) }}">返回首页</a>
                </div>
                @if (session('status'))
                    <div class="flash">{{ session('status') }}</div>
                @endif
            </div>
            @if ($heroNoticeImage !== '')
                <div class="hero-media" aria-hidden="true"></div>
            @endif
        </section>

        <section class="list">
            @forelse ($messages as $message)
                <article class="card">
                    <div class="card-head">
                        <div>
                            <div class="card-title">
                                @if ($message['name'] !== '')
                                    <span class="card-name">{{ $message['name'] }}</span>
                                @endif
                                <span class="card-id">ID:{{ $message['display_no'] }}</span>
                                <span class="card-time">{{ $message['created_at_label'] }}</span>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <span class="badge {{ $message['status_label'] === '已办理' ? 'success' : 'warning' }}">{{ $message['status_label'] }}</span>
                        </div>
                    </div>
                    <div class="card-summary">
                        <div class="card-summary-text">{{ $message['summary'] }}</div>
                        <span class="card-actions">
                            <a class="card-link" href="{{ route('site.guestbook.show', ['displayNo' => $message['display_no']] + $siteQuery) }}">[查看全文]</a>
                        </span>
                    </div>
                    @if ($message['reply_content'] !== '')
                        <div class="card-reply">
                            <span class="card-reply-label">回复内容：</span>{{ \Illuminate\Support\Str::limit($message['reply_content'], 120, '...') }}
                            @if ($message['replied_at_label'] !== '')
                                <div class="card-reply-time">回复时间：{{ $message['replied_at_label'] }}</div>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <div class="empty">当前还没有可展示的留言内容。</div>
            @endforelse
        </section>

        @if ($messages->hasPages())
            <div class="pagination">
                <nav aria-label="留言分页">
                    <div class="pagination-shell">
                        @if ($messages->onFirstPage())
                            <span class="pagination-button is-disabled">上一页</span>
                        @else
                            <a class="pagination-button" href="{{ $messages->previousPageUrl() }}">上一页</a>
                        @endif

                        <div class="pagination-pages">
                            @php
                                $currentPage = $messages->currentPage();
                                $lastPage = $messages->lastPage();
                                $startPage = max(1, $currentPage - 1);
                                $endPage = min($lastPage, $currentPage + 1);
                            @endphp

                            @if ($startPage > 1)
                                <a class="pagination-page" href="{{ $messages->url(1) }}">1</a>
                                @if ($startPage > 2)
                                    <span class="pagination-ellipsis">…</span>
                                @endif
                            @endif

                            @for ($page = $startPage; $page <= $endPage; $page++)
                                @if ($page === $currentPage)
                                    <span class="pagination-page is-active">{{ $page }}</span>
                                @else
                                    <a class="pagination-page" href="{{ $messages->url($page) }}">{{ $page }}</a>
                                @endif
                            @endfor

                            @if ($endPage < $lastPage)
                                @if ($endPage < $lastPage - 1)
                                    <span class="pagination-ellipsis">…</span>
                                @endif
                                <a class="pagination-page" href="{{ $messages->url($lastPage) }}">{{ $lastPage }}</a>
                            @endif
                        </div>

                        @if ($messages->hasMorePages())
                            <a class="pagination-button" href="{{ $messages->nextPageUrl() }}">下一页</a>
                        @else
                            <span class="pagination-button is-disabled">下一页</span>
                        @endif
                    </div>
                </nav>
            </div>
        @endif
    </div>
</body>
</html>
