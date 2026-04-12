<!DOCTYPE html>
<html lang="zh-CN">
<head>
    @php
        $theme = $settings['theme_profile'] ?? [];
        $themeCode = $settings['theme'] ?? 'default';
        $previousUrl = url()->previous();
        $fallbackBackUrl = $previousUrl && $previousUrl !== request()->fullUrl()
            ? $previousUrl
            : route('site.home', $siteQuery);
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $settings['name'] }} - {{ $site->name }}</title>
        <link rel="stylesheet" href="{{ asset('css/guestbook-frontend-disabled.css') }}">

</head>
<body class="theme-{{ $themeCode }}">
    <div class="shell">
        <section class="card">
            <div class="eyebrow">{{ $site->name }}</div>
            <h1 class="title">{{ $site->name }} · {{ $settings['name'] }}暂未开放</h1>
            <div class="desc">当前留言板功能已关闭，暂时无法查看或提交留言。请稍后再试，或通过网站其他公开联系方式与学校取得联系。</div>
            @if (session('status'))
                <div class="flash">{{ session('status') }}</div>
            @endif
            <div class="actions">
                <a class="button" href="{{ route('site.home', $siteQuery) }}">返回首页</a>
                <a class="button secondary" href="{{ $fallbackBackUrl }}">返回上一页</a>
            </div>
        </section>
    </div>
</body>
</html>
