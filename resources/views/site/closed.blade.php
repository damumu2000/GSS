<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统升级中</title>
    <link rel="stylesheet" href="{{ asset('css/site-theme-status-pages.css') }}">
</head>
<body class="theme-status-closed-page">
    <canvas class="theme-status-particles" data-status-particles aria-hidden="true"></canvas>
    <div class="theme-status-shell">
        <main class="theme-status-card is-closed">
            <div class="theme-status-icon" aria-hidden="true">
                <svg viewBox="0 0 48 48" fill="none" stroke="currentColor">
                    <path d="M30.4417 5C32.406 5 34.265 5.44776 35.9207 6.24607L32.7172 9.42668C30.8706 11.2601 30.8706 14.2327 32.7172 16.0661C34.5638 17.8995 37.5578 17.8995 39.4044 16.0661L42.2571 13.2337C42.7379 14.5558 43 15.9818 43 17.4685C43 24.3547 37.3775 29.937 30.4417 29.937C28.7825 29.937 27.1985 29.6176 25.7486 29.0373L13.07 41.6253C11.2238 43.4582 8.2307 43.4582 6.38459 41.6253C4.53847 39.7924 4.53847 36.8207 6.38459 34.9877L18.9523 22.5099C18.2651 20.9684 17.8834 19.2627 17.8834 17.4685C17.8834 10.5823 23.5059 5 30.4417 5Z" stroke-width="4" stroke-linejoin="round"/>
                </svg>
            </div>
            <h1 class="theme-status-title">抱歉，我们正在对系统进行技术升级，目前暂不可用。请稍后再试。</h1>
            <section class="theme-status-meta">
                <div class="theme-status-meta-item">
                    <span class="theme-status-meta-label">站点</span>
                    <span class="theme-status-meta-value">{{ $site->name ?? '当前站点' }}</span>
                </div>
            </section>
        </main>
    </div>
    <script src="{{ asset('js/site-status-particles.js') }}" defer></script>
</body>
</html>
