<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>已安全退出 - {{ $site->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/payroll-frontend-logout.css') }}">
</head>
<body>
    <div class="shell">
        <section class="panel">
            <div class="icon" aria-hidden="true">
                <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M24 44C29.5228 44 34.5228 41.7614 38.1421 38.1421C41.7614 34.5228 44 29.5228 44 24C44 18.4772 41.7614 13.4772 38.1421 9.85786C34.5228 6.23858 29.5228 4 24 4C18.4772 4 13.4772 6.23858 9.85786 9.85786C6.23858 13.4772 4 18.4772 4 24C4 29.5228 6.23858 34.5228 9.85786 38.1421C13.4772 41.7614 18.4772 44 24 44Z" fill="none" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/>
                    <path d="M16 24L22 30L34 18" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h1 class="title">你已经安全退出</h1>
            <div class="desc">当前工资查询登录状态已清除，可直接关闭此页面。</div>
            <div class="note">如需再次进入，请从微信入口重新打开工资查询页面。</div>
        </section>
    </div>
</body>
</html>
