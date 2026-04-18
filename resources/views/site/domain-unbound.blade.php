<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Support\ErrorPageBrand::systemName() }} - 域名未绑定站点</title>
    <link rel="stylesheet" href="{{ asset('css/site-theme-status-pages.css') }}">
</head>
<body>
    <div class="theme-status-shell">
        <main class="theme-status-card">
            <span class="theme-status-badge is-warning">域名未绑定</span>
            <h1 class="theme-status-title">当前域名尚未绑定站点</h1>
            <div class="theme-status-desc">
                该域名已接入系统，但暂未分配可访问站点。请联系管理员完成绑定后再访问。
            </div>

            <section class="theme-status-meta">
                <div class="theme-status-meta-item">
                    <span class="theme-status-meta-label">访问域名</span>
                    <span class="theme-status-meta-value">{{ $host ?: '--' }}</span>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
