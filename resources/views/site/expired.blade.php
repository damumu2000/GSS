<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>站点服务已到期</title>
    <link rel="stylesheet" href="{{ asset('css/site-theme-status-pages.css') }}">
</head>
<body class="theme-status-expired-page">
    <div class="theme-status-shell">
        <main class="theme-status-card is-expired">
            <div class="theme-status-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l3 2"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/>
                </svg>
            </div>
            <h1 class="theme-status-title">站点服务已到期</h1>
            <section class="theme-status-meta">
                <div class="theme-status-meta-item">
                    <span class="theme-status-meta-label">站点</span>
                    <span class="theme-status-meta-value">{{ $site->name ?? '当前站点' }}</span>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
