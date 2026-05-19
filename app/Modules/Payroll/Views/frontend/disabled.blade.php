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
        <section class="panel">
            @php($isAccountDisabled = ($disabledTitle ?? '') === '账户已禁用')
            <div class="icon" aria-hidden="true">
                <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M24 44C35.0457 44 44 35.0457 44 24C44 12.9543 35.0457 4 24 4C12.9543 4 4 12.9543 4 24C4 35.0457 12.9543 44 24 44Z" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M15 15L33 33" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h1 class="title">{{ $disabledTitle ?? '工资查询暂未开放' }}</h1>
            <div class="desc">{{ $disabledMessage ?? $settings['registration_disabled_message'] }}</div>
        </section>
    </div>
</body>
</html>
