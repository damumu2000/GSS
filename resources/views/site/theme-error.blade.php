<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $site->name }} - 页面暂时无法显示</title>
    <link rel="stylesheet" href="{{ asset('css/site-theme-status-pages.css') }}">
</head>
<body>
    <div class="theme-status-shell">
        <main class="theme-status-card">
            <span class="theme-status-badge is-warning">模板解析异常</span>
            <h1 class="theme-status-title">页面暂时无法显示</h1>
            <div class="theme-status-desc">
                当前主题模板存在解析问题，请稍后再试或联系管理员处理。
            </div>

            <section class="theme-status-meta">
                <div class="theme-status-meta-item">
                    <span class="theme-status-meta-label">站点名称</span>
                    <span class="theme-status-meta-value">{{ $site->name }}</span>
                </div>
                <div class="theme-status-meta-item">
                    <span class="theme-status-meta-label">站点标识</span>
                    <span class="theme-status-meta-value">{{ $site->site_key }}</span>
                </div>
                <div class="theme-status-meta-item">
                    <span class="theme-status-meta-label">异常模板</span>
                    <span class="theme-status-meta-value">{{ $template }}.tpl</span>
                </div>
                <div class="theme-status-meta-item">
                    <span class="theme-status-meta-label">错误说明</span>
                    <span class="theme-status-meta-value">{{ $message }}</span>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
