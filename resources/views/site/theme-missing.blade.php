<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $site->name }} - 站点模板未绑定</title>
    <link rel="stylesheet" href="{{ asset('css/site-theme-status-pages.css') }}">
</head>
<body>
    <div class="theme-status-shell">
        <main class="theme-status-card">
            <span class="theme-status-badge is-muted">{{ $isPreview ? '预览不可用' : '站点暂不可访问' }}</span>
            <h1 class="theme-status-title">{{ $isPreview ? '当前内容暂时无法预览' : '当前站点暂未绑定可用模板' }}</h1>
            <div class="theme-status-desc">
                {{ $isPreview ? '该内容所属站点当前还没有配置可用主题，因此暂时无法按前台样式进行预览。' : '该站点当前还没有配置可用主题，因此前台页面暂时无法正常显示。' }}
                请先在后台为该站点绑定主题，并在模板管理中选择一个可用主题。
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
            </section>
        </main>
    </div>
</body>
</html>
