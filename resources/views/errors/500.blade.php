<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - 系统暂时不可用</title>
    <link rel="stylesheet" href="{{ asset('css/site-theme-status-pages.css') }}">
</head>
<body>
    <div class="theme-status-shell">
        <main class="theme-status-card">
            <span class="theme-status-badge is-warning">Internal Server Error</span>
            <h1 class="theme-status-title">系统暂时不可用</h1>
            <div class="theme-status-desc">
                当前页面处理过程中发生了异常，系统正在尝试恢复。请稍后刷新重试；
                如果问题持续存在，请联系管理员检查日志与服务状态。
            </div>

            <section class="theme-status-meta">
                <div class="theme-status-meta-item">
                    <span class="theme-status-meta-label">状态代码</span>
                    <span class="theme-status-meta-value">500</span>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
