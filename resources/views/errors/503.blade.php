<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - 服务暂不可用</title>
    <link rel="stylesheet" href="{{ asset('css/site-theme-status-pages.css') }}">
</head>
<body>
    <div class="theme-status-shell">
        <main class="theme-status-card">
            <span class="theme-status-badge is-warning">503 Service Unavailable</span>
            <h1 class="theme-status-title">服务暂时不可用</h1>
            <div class="theme-status-desc">
                当前服务可能正在维护、重启或短时过载，请稍后刷新重试。
                如果长时间无法恢复，请联系管理员检查运行状态与服务日志。
            </div>

            <section class="theme-status-meta">
                <div class="theme-status-meta-item">
                    <span class="theme-status-meta-label">状态代码</span>
                    <span class="theme-status-meta-value">503</span>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
