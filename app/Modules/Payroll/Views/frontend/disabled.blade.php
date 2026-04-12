<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $disabledTitle ?? '工资查询暂未开放' }} - {{ $site->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/payroll-frontend-disabled.css') }}">
</head>
<body>
    <div class="shell">
        <div class="topbar">工资查询</div>
        <div class="subbar">{{ $site->name }}</div>

        <section class="panel">
            <div class="icon">薪</div>
            <h1 class="title">{{ $disabledTitle ?? '工资查询暂未开放' }}</h1>
            <div class="desc">{{ $disabledMessage ?? $settings['registration_disabled_message'] }}</div>
            <div class="note">如当前站点已经完成微信登录配置，建议从微信入口重新进入；如仍无法访问，请联系管理员检查模块状态、微信配置与员工审核状态。</div>
            <div class="actions">
                <a class="button secondary" href="{{ route('site.payroll.index', $siteQuery) }}">重新进入</a>
                <a class="button" href="{{ route('site.payroll.wechat.redirect', $siteQuery) }}">尝试微信登录</a>
            </div>
        </section>
    </div>
</body>
</html>
