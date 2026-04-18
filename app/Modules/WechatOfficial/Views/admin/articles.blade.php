@extends('layouts.admin')

@section('title', '文章推送 - 微信公众号 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 微信公众号文章推送')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/wechat-official-admin.css') }}">
@endpush

@section('content')
    @php
        $wechatArticlePageItems = $wechatArticles->getCollection();
        $wechatArticleStatusCards = [
            ['label' => '当前页文章', 'value' => $wechatArticlePageItems->count()],
            ['label' => '草稿已生成', 'value' => $wechatArticlePageItems->where('push_status', 'draft_ready')->count()],
            ['label' => '发布中', 'value' => $wechatArticlePageItems->where('push_status', 'publishing')->count()],
            ['label' => '已发布', 'value' => $wechatArticlePageItems->where('push_status', 'published')->count()],
        ];
    @endphp
    <section class="wechat-official-header">
        <div>
            <h1 class="wechat-official-title">文章推送</h1>
            <div class="wechat-official-desc">将站内已发布文章转为公众号草稿并继续发布。封面素材已支持优先带入站内封面图对应的公众号 MediaID。</div>
        </div>
        <div class="wechat-official-header-meta">
            <span class="wechat-official-meta-tag @if (! $wechatArticleSyncReady) is-light @endif">{{ $wechatArticleSyncReady ? '草稿同步已就绪' : '请先完善公众号配置' }}</span>
        </div>
    </section>

    @include('wechat_official::admin._nav')

    <section class="wechat-official-grid wechat-official-grid--compact">
        @foreach ($wechatArticleStatusCards as $stat)
            <article class="wechat-official-stat-card">
                <span class="wechat-official-stat-label">{{ $stat['label'] }}</span>
                <strong class="wechat-official-stat-value">{{ $stat['value'] }}</strong>
            </article>
        @endforeach
    </section>

    <section class="wechat-official-panel">
        <div class="wechat-official-panel-head">
            <div>
                <h2 class="wechat-official-panel-title">已发布文章</h2>
                <div class="wechat-official-panel-desc">只展示当前站点已发布文章。同步成功后会回写草稿状态和 MediaID，失败原因也会保留。</div>
            </div>
        </div>

        <form class="wechat-official-article-filters" method="get" action="{{ route('admin.wechat-official.articles') }}">
            <div class="wechat-official-field">
                <label class="wechat-official-label" for="wechat-article-keyword">关键词</label>
                <input id="wechat-article-keyword" class="field" type="text" name="keyword" value="{{ $wechatArticleKeyword }}">
            </div>
            <div class="wechat-official-field">
                <label class="wechat-official-label" for="wechat-article-push-status">推送状态</label>
                <select id="wechat-article-push-status" class="field" name="push_status">
                    <option value="">全部状态</option>
                    <option value="not_pushed" @selected($wechatArticlePushStatus === 'not_pushed')>未推送</option>
                    <option value="draft_ready" @selected($wechatArticlePushStatus === 'draft_ready')>草稿已生成</option>
                    <option value="publishing" @selected($wechatArticlePushStatus === 'publishing')>发布中</option>
                    <option value="published" @selected($wechatArticlePushStatus === 'published')>已发布</option>
                    <option value="publish_failed" @selected($wechatArticlePushStatus === 'publish_failed')>发布失败</option>
                    <option value="failed" @selected($wechatArticlePushStatus === 'failed')>推送失败</option>
                </select>
            </div>
            <div class="wechat-official-article-filter-actions">
                <button class="btn btn-primary" type="submit">筛选文章</button>
            </div>
        </form>

        <div class="wechat-official-article-list">
            @forelse ($wechatArticles as $article)
                @php
                    $pushStatus = $article->push_status ?: 'not_pushed';
                    $pushLabel = match ($pushStatus) {
                        'draft_ready' => '草稿已生成',
                        'publishing' => '发布中',
                        'published' => '已发布',
                        'publish_failed' => '发布失败',
                        'failed' => '推送失败',
                        default => '未推送',
                    };
                    $pushNextStep = match ($pushStatus) {
                        'draft_ready' => '下一步：发布到公众号',
                        'publishing' => '下一步：查询发布结果',
                        'published' => '当前文章已完成公众号发布',
                        'publish_failed' => '下一步：重新提交发布',
                        'failed' => '下一步：重新同步公众号草稿',
                        default => '下一步：先同步公众号草稿',
                    };
                @endphp
                <article class="wechat-official-article-card">
                    <div class="wechat-official-article-head">
                        <div class="wechat-official-article-title-wrap">
                            <h3 class="wechat-official-article-title">{{ $article->title }}</h3>
                            <div class="wechat-official-inline-tags">
                                <span class="wechat-official-chip">{{ $article->channel_name ?: '未绑定栏目' }}</span>
                                <span class="wechat-official-chip @if (in_array($pushStatus, ['draft_ready', 'published'], true)) is-success @elseif ($pushStatus === 'publishing') is-warning @elseif (in_array($pushStatus, ['failed', 'publish_failed'], true)) is-danger @endif">{{ $pushLabel }}</span>
                            </div>
                        </div>
                        <div class="wechat-official-article-time">
                            发布于 {{ $article->published_at ? \Illuminate\Support\Carbon::parse($article->published_at)->format('Y-m-d H:i') : '未记录' }}
                        </div>
                    </div>

                    @if (trim((string) ($article->summary ?? '')) !== '')
                        <p class="wechat-official-article-summary">{{ \Illuminate\Support\Str::limit((string) $article->summary, 140, '...') }}</p>
                    @endif

                    <div class="wechat-official-article-meta">
                        <span>作者：{{ trim((string) ($article->author ?? '')) !== '' ? $article->author : '未填写' }}</span>
                        @if (trim((string) ($article->draft_media_id ?? '')) !== '')
                            <span>草稿 MediaID：{{ $article->draft_media_id }}</span>
                        @endif
                        @if (trim((string) ($article->publish_id ?? '')) !== '')
                            <span>发布任务 ID：{{ $article->publish_id }}</span>
                        @endif
                        @if ($article->push_updated_at)
                            <span>最近同步：{{ \Illuminate\Support\Carbon::parse($article->push_updated_at)->format('Y-m-d H:i') }}</span>
                        @endif
                    </div>

                    @if (trim((string) ($article->error_message ?? '')) !== '')
                        <div class="wechat-official-article-error">{{ $article->error_message }}</div>
                    @endif

                    <div class="wechat-official-action-panel">
                        <div class="wechat-official-article-action-head">
                            <div class="wechat-official-article-action-title-wrap">
                                <strong class="wechat-official-article-action-title">{{ $pushLabel }}</strong>
                                <span class="wechat-official-article-action-desc">{{ $pushNextStep }}</span>
                            </div>
                        </div>

                        <div class="wechat-official-article-action-block">
                            <div class="wechat-official-article-action-block-head">
                                <strong>草稿准备</strong>
                                <span>先确认封面素材和原文链接，再同步公众号草稿。</span>
                            </div>

                        <form class="wechat-official-article-sync-form" method="post" action="{{ route('admin.wechat-official.articles.draft', ['content' => $article->id]) }}">
                            @csrf
                            <div class="wechat-official-field">
                                <label class="wechat-official-label" for="thumb-media-{{ $article->id }}">封面素材 MediaID</label>
                                <input id="thumb-media-{{ $article->id }}" class="field" type="text" name="thumb_media_id" value="{{ $article->auto_thumb_media_id ?? '' }}">
                                @if (trim((string) ($article->auto_thumb_media_id ?? '')) !== '')
                                    <div class="wechat-official-field-hint">已自动带入站内封面图对应的公众号素材 MediaID。</div>
                                @else
                                    <div class="wechat-official-field-hint">如果站内封面图已在“素材管理”同步过，这里会自动带出。</div>
                                @endif
                            </div>
                            <div class="wechat-official-field">
                                <label class="wechat-official-label" for="source-url-{{ $article->id }}">原文链接</label>
                                <input id="source-url-{{ $article->id }}" class="field" type="url" name="content_source_url" value="">
                            </div>
                            <div class="wechat-official-article-sync-actions">
                                <button class="btn btn-primary" type="submit" @disabled(! $wechatArticleSyncReady)>同步公众号草稿</button>
                            </div>
                        </form>
                        </div>

                        <div class="wechat-official-article-action-block">
                            <div class="wechat-official-article-action-block-head">
                                <strong>发布操作</strong>
                                <span>草稿生成后再发布；发布中可继续查询最终结果。</span>
                            </div>

                        <div class="wechat-official-card-actions">
                            @if (in_array($pushStatus, ['draft_ready', 'publish_failed'], true) && trim((string) ($article->draft_media_id ?? '')) !== '')
                                <form class="wechat-official-article-publish-form" method="post" action="{{ route('admin.wechat-official.articles.publish', ['content' => $article->id]) }}">
                                    @csrf
                                    <button class="btn btn-default" type="submit" @disabled(! $wechatArticleSyncReady)>{{ $pushStatus === 'publish_failed' ? '重新提交发布' : '发布到公众号' }}</button>
                                </form>
                            @endif

                            @if ($pushStatus === 'publishing' && trim((string) ($article->publish_id ?? '')) !== '')
                                <form class="wechat-official-article-publish-form" method="post" action="{{ route('admin.wechat-official.articles.publish-status', ['content' => $article->id]) }}">
                                    @csrf
                                    <button class="btn btn-default" type="submit" @disabled(! $wechatArticleSyncReady)>查询发布结果</button>
                                </form>
                            @endif

                            @if (! in_array($pushStatus, ['draft_ready', 'publish_failed', 'publishing'], true))
                                <span class="wechat-official-article-action-empty">当前没有额外发布操作。</span>
                            @endif
                        </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="wechat-official-placeholder">
                    <h2>暂无可推送文章</h2>
                    <p>当前筛选条件下没有已发布文章，可以先发布站内文章后再到这里同步草稿。</p>
                </div>
            @endforelse
        </div>

        {{ $wechatArticles->links() }}
    </section>

    <section class="wechat-official-panel">
        <div class="wechat-official-panel-head">
            <div>
                <h2 class="wechat-official-panel-title">最近推送记录</h2>
                <div class="wechat-official-panel-desc">这里只展示最近的草稿同步结果，完整接口日志可到“接口日志”查看。</div>
            </div>
        </div>

        <div class="wechat-official-article-log-list">
            @forelse ($wechatRecentPushes as $push)
                <div class="wechat-official-article-log-item">
                    <div>
                        <strong>{{ $push->title ?: '未命名文章' }}</strong>
                        <div class="wechat-official-article-log-meta">
                            <span>{{ $push->status === 'draft_ready' ? '草稿已生成' : ($push->status === 'publishing' ? '发布中' : ($push->status === 'published' ? '已发布' : ($push->status === 'publish_failed' ? '发布失败' : ($push->status === 'failed' ? '推送失败' : '待处理')))) }}</span>
                            @if (trim((string) ($push->draft_media_id ?? '')) !== '')
                                <span>{{ $push->draft_media_id }}</span>
                            @endif
                            @if (trim((string) ($push->publish_id ?? '')) !== '')
                                <span>{{ $push->publish_id }}</span>
                            @endif
                            <span>{{ \Illuminate\Support\Carbon::parse($push->updated_at)->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                    @if (trim((string) ($push->error_message ?? '')) !== '')
                        <div class="wechat-official-article-log-error">{{ $push->error_message }}</div>
                    @endif
                </div>
            @empty
                <div class="wechat-official-placeholder">
                    <h2>还没有推送记录</h2>
                    <p>文章第一次同步成功或失败后，这里会自动记录结果。</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection
