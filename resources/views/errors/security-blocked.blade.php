<!DOCTYPE html>
<html lang="zh-CN">
<head>
    @php($systemName = \App\Support\ErrorPageBrand::systemName())
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $systemName }} - 安护盾拦截</title>
    <link rel="stylesheet" href="{{ asset('css/site-theme-status-pages.css') }}">
</head>
<body class="theme-status-closed-page">
    <div class="theme-status-shell">
        <main class="theme-status-card is-closed">
            <div class="theme-status-icon" aria-hidden="true">
                <svg viewBox="0 0 48 48" fill="none" stroke="currentColor">
                    <path d="M24 5L8 11V22C8 32.2 14.6 41.7 24 45C33.4 41.7 40 32.2 40 22V11L24 5Z" stroke-width="4" stroke-linejoin="round"/>
                    <path d="M18 24L22 28L31 19" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h1 class="theme-status-title">当前请求已被安全防护拦截</h1>
            <div class="theme-status-desc">
                安护盾检测到当前请求命中了安全规则，为保护系统与数据安全，已阻止本次访问。
            </div>

            <section class="theme-status-meta">
                <div class="theme-status-meta-item">
                    <span>
                        <span class="theme-status-meta-label">状态代码</span>
                        <span class="theme-status-meta-value">403</span>
                    </span>
                    <span>
                        <span class="theme-status-meta-label">拦截规则</span>
                        <span class="theme-status-meta-value">{{ $blockedRule['name'] ?? '安护盾拦截' }}</span>
                    </span>
                </div>
                <div class="theme-status-meta-item">
                    <span class="theme-status-meta-label">请求路径</span>
                    <span class="theme-status-meta-value">{{ $blockedPath ?? '/' }}</span>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
