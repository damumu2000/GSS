<!DOCTYPE html>
<html lang="zh-CN">
<head>
    @php($systemName = \App\Support\ErrorPageBrand::systemName())
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $systemName }} - 页面不存在</title>
    <link rel="stylesheet" href="{{ asset('css/site-theme-status-pages.css') }}">
</head>
<body class="theme-status-closed-page">
    <div class="theme-status-shell">
        <main class="theme-status-card is-closed">
            <div class="theme-status-icon" aria-hidden="true">
                <svg viewBox="0 0 48 48" fill="none" stroke="currentColor">
                    <circle cx="24" cy="24" r="18" stroke-width="4"/>
                    <path d="M18 18L30 30" stroke-width="4" stroke-linecap="round"/>
                    <path d="M30 18L18 30" stroke-width="4" stroke-linecap="round"/>
                </svg>
            </div>
            <h1 class="theme-status-title">未找到页面</h1>
            <div class="theme-status-desc">
                你访问的地址当前无法找到，可能已被删除、移动，或链接本身不正确。
            </div>

            <section class="theme-status-meta">
                <div class="theme-status-meta-item">
                    <span class="theme-status-meta-label">状态代码</span>
                    <span class="theme-status-meta-value">404</span>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
