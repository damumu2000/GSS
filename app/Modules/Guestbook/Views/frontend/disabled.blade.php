<!DOCTYPE html>
<html lang="zh-CN">
<head>
    @php
        $theme = $settings['theme_profile'] ?? [];
        $themeCode = $settings['theme'] ?? 'default';
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $settings['name'] }} - {{ $site->name }}</title>
    <style>
        :root {
            --primary: {{ $theme['primary'] ?? '#0050b3' }};
            --primary-deep: {{ $theme['primary_deep'] ?? '#003f8f' }};
            --primary-border: {{ $theme['primary_border'] ?? 'rgba(0,80,179,0.18)' }};
            --bg: {{ $theme['bg'] ?? '#f5f7fa' }};
            --panel: {{ $theme['panel'] ?? '#ffffff' }};
            --text: {{ $theme['text'] ?? '#1f2937' }};
            --muted: {{ $theme['muted'] ?? '#8c8c8c' }};
            --line: {{ $theme['line'] ?? '#e5e7eb' }};
            --warning: {{ $theme['warning'] ?? '#b45309' }};
            --flash-bg: {{ $theme['flash_bg'] ?? 'rgba(245,158,11,0.10)' }};
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "PingFang SC","Microsoft YaHei",sans-serif; background: var(--bg); color: var(--text); }
        .shell { width:min(760px, calc(100% - 24px)); margin: 36px auto; }
        .card {
            position: relative;
            padding: 36px 34px;
            border-radius: 24px;
            border: 1px solid var(--line);
            background: var(--panel);
            box-shadow: 0 10px 24px rgba(15,23,42,0.05);
            text-align: center;
        }
        .eyebrow { color: var(--warning); font-size: 13px; line-height: 1.6; font-weight: 700; letter-spacing: 0.02em; }
        .title { margin: 10px 0 0; font-size: 22px; line-height: 1.35; font-weight: 700; }
        .desc { margin: 14px auto 0; max-width: 560px; color: #667085; font-size: 15px; line-height: 1.9; }
        .flash { margin: 18px auto 0; max-width: 560px; padding: 12px 14px; border-radius: 14px; background: var(--flash-bg); color: var(--warning); font-size: 14px; line-height: 1.8; font-weight: 700; }
        .actions { margin-top: 26px; display:flex; gap:12px; flex-wrap:wrap; justify-content:center; }
        .button {
            display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:0 18px;
            border-radius:999px; border:1px solid transparent; background:var(--primary); color:#fff; font-size:14px; font-weight:700; text-decoration:none;
        }
        .button:hover { background: var(--primary-deep); }
        .button.secondary { background:#fff; color:var(--primary); border-color: var(--primary-border); }
        .button.secondary:hover { background:#fff; color:var(--primary-deep); border-color: var(--primary-border); }
        .theme-china-red .card {
            border-top: 3px solid rgba(178, 34, 34, 0.56);
        }
        .theme-china-red .button {
            border-radius: 12px;
            letter-spacing: 0.03em;
        }
        .theme-education-green .card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 30px;
            bottom: 30px;
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
        .theme-vibrant-orange .card {
            border-radius: 28px;
            box-shadow: 0 12px 26px rgba(201, 106, 16, 0.06);
        }
        .theme-vibrant-orange .button {
            border-radius: 14px;
            box-shadow: 0 10px 22px rgba(242, 140, 40, 0.18);
        }
        .theme-vibrant-orange .button.secondary {
            box-shadow: none;
        }
    </style>
</head>
<body class="theme-{{ $themeCode }}">
    <div class="shell">
        <section class="card">
            <div class="eyebrow">{{ $site->name }}</div>
            <h1 class="title">{{ $site->name }} · {{ $settings['name'] }}暂未开放</h1>
            <div class="desc">当前留言板功能已关闭，暂时无法查看或提交留言。请稍后再试，或通过网站其他公开联系方式与学校取得联系。</div>
            @if (session('status'))
                <div class="flash">{{ session('status') }}</div>
            @endif
            <div class="actions">
                <a class="button" href="{{ route('site.home', $siteQuery) }}">返回首页</a>
                <a class="button secondary" href="javascript:history.back()">返回上一页</a>
            </div>
        </section>
    </div>
</body>
</html>
