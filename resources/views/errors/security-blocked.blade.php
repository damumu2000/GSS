<!DOCTYPE html>
<html lang="zh-CN">
<head>
    @php($systemName = \App\Support\ErrorPageBrand::systemName())
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $systemName }} - 安护盾拦截</title>
    <link rel="stylesheet" href="{{ asset('css/site-theme-status-pages.css') }}">
</head>
<body>
    <div class="theme-status-shell">
        <main class="theme-status-card">
            <span class="theme-status-badge is-warning">安护盾已拦截</span>
            <h1 class="theme-status-title">当前请求已被安全防护拦截</h1>
            <div class="theme-status-desc">
                安护盾检测到当前请求命中了安全规则，为保护系统与数据安全，已阻止本次访问。
                如果这是正常操作，请稍后重试，或联系管理员检查对应防护策略。
            </div>

            <section class="theme-status-meta">
                <div class="theme-status-meta-item">
                    <span class="theme-status-meta-label">状态代码</span>
                    <span class="theme-status-meta-value">403</span>
                </div>
                <div class="theme-status-meta-item">
                    <span class="theme-status-meta-label">拦截规则</span>
                    <span class="theme-status-meta-value">{{ $blockedRule['name'] ?? '安护盾拦截' }}</span>
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
