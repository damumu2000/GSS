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
        <div class="topbar">工资查询</div>

        <section class="panel">
            <div class="icon">退</div>
            <h1 class="title">你已经安全退出</h1>
            <div class="desc">当前工资查询登录状态已清除，可直接关闭此页面。</div>
            <div class="note">如需再次进入，请从微信入口重新打开工资查询页面。</div>
        </section>
    </div>
</body>
</html>
