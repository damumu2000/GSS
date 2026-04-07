<!DOCTYPE html>
<html lang="zh-CN">
<head>
    @php
        $theme = $settings['theme_profile'] ?? [];
        $themeCode = $settings['theme'] ?? 'default';
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>#{{ $message['display_no'] }} 留言详情 - {{ $settings['name'] }} - {{ $site->name }}</title>
    <style>
        :root {
            --primary: {{ $theme['primary'] ?? '#0050b3' }};
            --primary-deep: {{ $theme['primary_deep'] ?? '#003f8f' }};
            --primary-border: {{ $theme['primary_border'] ?? 'rgba(0,80,179,0.08)' }};
            --bg: {{ $theme['bg'] ?? '#f5f7fa' }};
            --panel: {{ $theme['panel'] ?? '#ffffff' }};
            --text: {{ $theme['text'] ?? '#1f2937' }};
            --muted: {{ $theme['muted'] ?? '#8c8c8c' }};
            --line: {{ $theme['line'] ?? '#e5e7eb' }};
            --success: {{ $theme['success'] ?? '#059669' }};
            --warning: {{ $theme['warning'] ?? '#b45309' }};
            --reply-bg: {{ $theme['reply_bg'] ?? '#fbfdff' }};
            --reply-text: {{ $theme['reply_text'] ?? '#344054' }};
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "PingFang SC","Microsoft YaHei",sans-serif; background: var(--bg); color: var(--text); }
        a { color: inherit; text-decoration: none; }
        .shell { width: min(980px, calc(100% - 24px)); margin: 28px auto 40px; }
        .card {
            position: relative;
            padding: 24px 26px;
            border-radius: 20px;
            border: 1px solid var(--line);
            background: var(--panel);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }
        .card + .card { margin-top: 18px; }
        .title { margin: 0; font-size: 28px; line-height: 1.3; font-weight: 700; }
        .subline { margin-top: 10px; color: #667085; font-size: 14px; line-height: 1.85; }
        .meta { margin-top: 16px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .meta-text { color: #98a2b3; font-size: 12px; line-height: 1.7; display: inline-flex; align-items: center; min-height: 28px; }
        .badge { display: inline-flex; align-items: center; justify-content: center; height: 28px; padding: 0 12px; border-radius: 999px; background: #f5f7fa; color: #667085; font-size: 12px; line-height: 1; font-weight: 700; white-space: nowrap; }
        .badge.success { background: rgba(16,185,129,0.10); color: var(--success); }
        .badge.warning { background: rgba(245,158,11,0.12); color: var(--warning); }
        .content {
            margin-top: 18px;
            padding: 18px 18px 16px;
            border-radius: 16px;
            background: var(--reply-bg);
            border: 1px solid #eef2f6;
            color: var(--reply-text);
            font-size: 14px;
            line-height: 1.9;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .section-title { margin: 0; color: #1f2937; font-size: 18px; line-height: 1.5; font-weight: 700; }
        .reply-time { margin-top: 10px; color: #98a2b3; font-size: 12px; line-height: 1.7; }
        .actions { margin-top: 22px; display: flex; gap: 12px; flex-wrap: wrap; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid transparent;
            background: var(--primary);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
        }
        .button:hover { background: var(--primary-deep); }
        .button.secondary { background: #fff; color: var(--primary); border-color: var(--primary-border); }
        .button.secondary:hover { background: #fff; color: var(--primary-deep); border-color: var(--primary-border); }
        .theme-china-red .card:first-of-type {
            border-top: 3px solid rgba(178, 34, 34, 0.56);
        }
        .theme-china-red .badge {
            border-radius: 10px;
        }
        .theme-china-red .button {
            border-radius: 12px;
            letter-spacing: 0.03em;
        }
        .theme-china-red .content {
            border-left: 4px solid rgba(178, 34, 34, 0.44);
            border-radius: 12px;
            background: transparent;
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
            box-shadow: 0 8px 18px rgba(47, 143, 87, 0.12);
        }
        .theme-education-green .button.secondary {
            box-shadow: none;
        }
        .theme-education-green .content {
            border-radius: 18px;
            background: transparent;
        }
        .theme-vibrant-orange .card {
            border-radius: 22px;
            box-shadow: 0 12px 26px rgba(201, 106, 16, 0.06);
        }
        .theme-vibrant-orange .button {
            border-radius: 14px;
            box-shadow: 0 10px 22px rgba(242, 140, 40, 0.18);
        }
        .theme-vibrant-orange .button.secondary {
            box-shadow: none;
        }
        .theme-vibrant-orange .content {
            border-radius: 18px;
            border: 1px solid rgba(242, 140, 40, 0.16);
            background: transparent;
        }
        @media (max-width: 640px) {
            .shell { width: calc(100% - 20px); margin-top: 18px; }
            .card { padding: 20px 18px; }
            .title { font-size: 24px; }
        }
    </style>
</head>
<body class="theme-{{ $themeCode }}">
    <div class="shell">
        <section class="card">
            <h1 class="title">#{{ $message['display_no'] }} 留言详情</h1>
            <div class="subline">这里展示该条公开留言的完整内容和最新办理结果，便于访客持续查看处理进度。</div>
            <div class="meta">
                @if ($message['name'] !== '')
                    <span class="meta-text">{{ $message['name'] }}</span>
                @endif
                <span class="meta-text">ID:{{ $message['display_no'] }}</span>
                <span class="meta-text">{{ $message['created_at_label'] }}</span>
                <span class="badge {{ $message['status_label'] === '已办理' ? 'success' : 'warning' }}">{{ $message['status_label'] }}</span>
            </div>
            <div class="content">{{ $message['content'] }}</div>
        </section>

        @if ($message['reply_content'] !== '')
            <section class="card">
                <h2 class="section-title">回复内容</h2>
                <div class="content">{{ $message['reply_content'] }}</div>
                @if ($message['replied_at_label'] !== '')
                    <div class="reply-time">回复时间：{{ $message['replied_at_label'] }}</div>
                @endif
            </section>
        @endif

        <div class="actions">
            <a class="button secondary" href="{{ route('site.guestbook.index', $siteQuery) }}">返回列表</a>
            <a class="button" href="{{ route('site.guestbook.create', $siteQuery) }}">继续留言</a>
        </div>
    </div>
</body>
</html>
