<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - 无权访问</title>
    <link rel="stylesheet" href="{{ asset('css/site-theme-status-pages.css') }}">
</head>
<body>
    <div class="theme-status-shell">
        <main class="theme-status-card">
            <span class="theme-status-badge is-warning">403 Forbidden</span>
            <h1 class="theme-status-title">无权访问当前页面</h1>
            <div class="theme-status-desc">
                当前账号没有访问该页面或执行该操作的权限。
                如果你认为这是异常情况，请联系管理员检查账号角色与授权配置。
            </div>

            <section class="theme-status-meta">
                <div class="theme-status-meta-item">
                    <span class="theme-status-meta-label">状态代码</span>
                    <span class="theme-status-meta-value">403</span>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
