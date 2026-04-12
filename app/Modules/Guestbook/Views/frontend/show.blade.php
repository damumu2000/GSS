<!DOCTYPE html>
<html lang="zh-CN">
<head>
    @php
        $theme = $settings['theme_profile'] ?? [];
        $themeCode = $settings['theme'] ?? 'default';
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>#{{ $message['display_no'] }} 留言详情 - {{ $settings['name'] }} - {{ $site->name }}</title>
        <link rel="stylesheet" href="{{ asset('css/guestbook-frontend-show.css') }}">

</head>
<body class="theme-{{ $themeCode }}">
    <div class="shell">
        <section class="card">
            <h1 class="title">#{{ $message['display_no'] }} 留言详情</h1>
            <div class="subline">这里展示该条公开留言的完整内容和最新办理结果，便于访客持续查看处理进度。</div>
            <div class="meta">
                @if ($message['name'] !== '')
                    <span class="meta-text">{{ $message['name'] }}</span>
                @endif
                <span class="meta-text">ID:{{ $message['display_no'] }}</span>
                <span class="meta-text">{{ $message['created_at_label'] }}</span>
                <span class="badge {{ $message['status_label'] === '已办理' ? 'success' : 'warning' }}">{{ $message['status_label'] }}</span>
            </div>
            <div class="content">{{ $message['content'] }}</div>
        </section>

        @if ($message['reply_content'] !== '')
            <section class="card">
                <h2 class="section-title">回复内容</h2>
                <div class="content">{{ $message['reply_content'] }}</div>
                @if ($message['replied_at_label'] !== '')
                    <div class="reply-time">回复时间：{{ $message['replied_at_label'] }}</div>
                @endif
            </section>
        @endif

        <div class="actions">
            <a class="button secondary" href="{{ route('site.guestbook.index', $siteQuery) }}">返回列表</a>
            <a class="button" href="{{ route('site.guestbook.create', $siteQuery) }}">继续留言</a>
        </div>
    </div>
</body>
</html>
