<!DOCTYPE html>
<html lang="zh-CN">
<head>
    @php($systemName = \App\Support\ErrorPageBrand::systemName())
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $systemName }} - 页面不存在</title>
    <link rel="stylesheet" href="{{ asset('css/site-theme-status-pages.css') }}">
</head>
<body>
    <div class="theme-status-shell">
        <main class="theme-status-card">
            <span class="theme-status-badge is-muted">404 Not Found</span>
            <h1 class="theme-status-title">页面不存在或已被移除</h1>
            <div class="theme-status-desc">
                你访问的地址当前无法找到，可能已被删除、移动，或链接本身不正确。
                请返回上一页重新进入，或联系管理员确认页面路径是否已调整。
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
