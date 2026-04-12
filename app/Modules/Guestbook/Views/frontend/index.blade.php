<!DOCTYPE html>
<html lang="zh-CN">
<head>
    @php
        $theme = $settings['theme_profile'] ?? [];
        $themeCode = $settings['theme'] ?? 'default';
        $heroNoticeImage = trim((string) ($settings['notice_image'] ?? ''));
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $settings['name'] }} - {{ $site->name }}</title>
        <link rel="stylesheet" href="{{ asset('css/guestbook-frontend-index.css') }}">

</head>
<body class="theme-{{ $themeCode }}">
    <div class="shell">
        <header class="header">
            <div class="header-top">
                <div>
                    <h1 class="board-title">
                        <span class="board-site-inline">{{ $site->name }}</span>
                        <span class="board-separator">·</span>
                        <span class="board-name">{{ $settings['name'] }}</span>
                    </h1>
                </div>
            </div>
        </header>

        <section class="hero{{ $heroNoticeImage !== '' ? ' has-media' : '' }}">
            <div class="hero-accent" aria-hidden="true"></div>
            <div class="hero-main">
                <div class="hero-kicker">主题预览</div>
                <div class="hero-notice-title{{ $themeCode !== 'default' ? ' is-offset' : '' }}">发布须知</div>
                <div class="hero-notice">{!! $settings['notice'] !!}</div>
                <div class="hero-actions">
                    <a class="button" href="{{ route('site.guestbook.create', $siteQuery) }}">我要留言</a>
                    <a class="button secondary" href="{{ route('site.home', $siteQuery) }}">返回首页</a>
                </div>
                @if (session('status'))
                    <div class="flash">{{ session('status') }}</div>
                @endif
            </div>
            @if ($heroNoticeImage !== '')
                <div class="hero-media" aria-hidden="true">
                    <img class="hero-media-image" src="{{ $heroNoticeImage }}" alt="">
                </div>
            @endif
        </section>

        <section class="list">
            @forelse ($messages as $message)
                <article class="card">
                    <div class="card-head">
                        <div>
                            <div class="card-title">
                                @if ($message['name'] !== '')
                                    <span class="card-name">{{ $message['name'] }}</span>
                                @endif
                                <span class="card-id">ID:{{ $message['display_no'] }}</span>
                                <span class="card-time">{{ $message['created_at_label'] }}</span>
                            </div>
                        </div>
                        <div class="card-badge-row">
                            <span class="badge {{ $message['status_label'] === '已办理' ? 'success' : 'warning' }}">{{ $message['status_label'] }}</span>
                        </div>
                    </div>
                    <div class="card-summary">
                        <div class="card-summary-text">{{ $message['summary'] }}</div>
                        <span class="card-actions">
                            <a class="card-link" href="{{ route('site.guestbook.show', ['displayNo' => $message['display_no']] + $siteQuery) }}">[查看全文]</a>
                        </span>
                    </div>
                    @if ($message['reply_content'] !== '')
                        <div class="card-reply">
                            <span class="card-reply-label">回复内容：</span>{{ \Illuminate\Support\Str::limit($message['reply_content'], 120, '...') }}
                            @if ($message['replied_at_label'] !== '')
                                <div class="card-reply-time">回复时间：{{ $message['replied_at_label'] }}</div>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <div class="empty">当前还没有可展示的留言内容。</div>
            @endforelse
        </section>

        @if ($messages->hasPages())
            <div class="pagination">
                <nav aria-label="留言分页">
                    <div class="pagination-shell">
                        @if ($messages->onFirstPage())
                            <span class="pagination-button is-disabled">上一页</span>
                        @else
                            <a class="pagination-button" href="{{ $messages->previousPageUrl() }}">上一页</a>
                        @endif

                        <div class="pagination-pages">
                            @php
                                $currentPage = $messages->currentPage();
                                $lastPage = $messages->lastPage();
                                $startPage = max(1, $currentPage - 1);
                                $endPage = min($lastPage, $currentPage + 1);
                            @endphp

                            @if ($startPage > 1)
                                <a class="pagination-page" href="{{ $messages->url(1) }}">1</a>
                                @if ($startPage > 2)
                                    <span class="pagination-ellipsis">…</span>
                                @endif
                            @endif

                            @for ($page = $startPage; $page <= $endPage; $page++)
                                @if ($page === $currentPage)
                                    <span class="pagination-page is-active">{{ $page }}</span>
                                @else
                                    <a class="pagination-page" href="{{ $messages->url($page) }}">{{ $page }}</a>
                                @endif
                            @endfor

                            @if ($endPage < $lastPage)
                                @if ($endPage < $lastPage - 1)
                                    <span class="pagination-ellipsis">…</span>
                                @endif
                                <a class="pagination-page" href="{{ $messages->url($lastPage) }}">{{ $lastPage }}</a>
                            @endif
                        </div>

                        @if ($messages->hasMorePages())
                            <a class="pagination-button" href="{{ $messages->nextPageUrl() }}">下一页</a>
                        @else
                            <span class="pagination-button is-disabled">下一页</span>
                        @endif
                    </div>
                </nav>
            </div>
        @endif
    </div>
</body>
</html>
