@extends('layouts.admin')

@section('title', '文章审核 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 文章审核')

@push('styles')
    @include('admin.site._custom_select_styles')
    <link rel="stylesheet" href="/css/site-article-reviews.css">
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">文章审核</h2>
            <div class="page-header-desc">集中处理待审核与已驳回文章，审核通过后才会正式上线。</div>
        </div>
    </section>

    <section class="panel review-page-panel">
        <form method="GET" action="{{ route('admin.article-reviews.index') }}" class="review-filter-card">
            <div class="review-filter-grid">
                <div>
                    <label for="keyword">搜索文章</label>
                    <input class="field" id="keyword" type="text" name="keyword" value="{{ $keyword }}" placeholder="标题或摘要关键词">
                </div>
                <div>
                    <label for="channel_filter">所属栏目</label>
                    <div class="site-select channel-parent-select" data-site-select>
                        <select id="channel_filter" name="channel_id" class="field site-select-native">
                            <option value="">全部栏目</option>
                            @foreach ($channels as $channel)
                                <option
                                    value="{{ $channel->id }}"
                                    data-depth="{{ (int) ($channel->tree_depth ?? 0) }}"
                                    data-has-children="{{ !empty($channel->tree_has_children) ? '1' : '0' }}"
                                    @selected($selectedChannelId === (string) $channel->id)
                                >{{ $channel->name }}</option>
                            @endforeach
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ collect($channels)->firstWhere('id', (int) $selectedChannelId)?->name ?? '全部栏目' }}</button>
                        <div class="site-select-panel" data-select-panel role="listbox"></div>
                    </div>
                </div>
                <div>
                    <label for="status_filter">审核状态</label>
                    <div class="site-select" data-site-select>
                        <select id="status_filter" name="status" class="field site-select-native">
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ $statuses[$selectedStatus] ?? '待审核' }}</button>
                        <div class="site-select-panel" data-select-panel role="listbox"></div>
                    </div>
                </div>
                <div class="review-filter-actions">
                    <button class="button neutral-action" type="submit">筛选</button>
                    <a class="button neutral-action" href="{{ route('admin.article-reviews.index') }}">重置</a>
                    <div class="review-summary-pills">
                        <a
                            class="review-summary-pill is-pending"
                            href="{{ route('admin.article-reviews.index', array_filter(['keyword' => $keyword, 'channel_id' => $selectedChannelId, 'status' => 'pending'], fn ($value) => $value !== null && $value !== '')) }}"
                        >待审核 <strong>{{ $reviewSummary->pending_count }}</strong></a>
                        <a
                            class="review-summary-pill is-rejected"
                            href="{{ route('admin.article-reviews.index', array_filter(['keyword' => $keyword, 'channel_id' => $selectedChannelId, 'status' => 'rejected'], fn ($value) => $value !== null && $value !== '')) }}"
                        >已驳回 <strong>{{ $reviewSummary->rejected_count }}</strong></a>
                    </div>
                </div>
            </div>
        </form>

        <div class="review-list-panel">
            @if ($contents->isEmpty())
                <div class="empty">当前筛选条件下没有需要处理的文章。</div>
            @else
                <div class="review-table-wrap">
                <table class="review-table">
                    <thead>
                    <tr>
                        <th></th>
                        <th>标题</th>
                        <th>栏目</th>
                        <th>状态</th>
                        <th>提交人</th>
                        <th>更新时间</th>
                        <th>操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($contents as $content)
                        @php
                            $channelNames = collect($content->channel_names ?? [])->filter()->values();
                            $primaryChannelName = $channelNames->first() ?: '未归类';
                            $extraChannelCount = max($channelNames->count() - 1, 0);
                            $channelTooltip = $channelNames->isNotEmpty()
                                ? $channelNames->implode('、')
                                : '未归类';
                        @endphp
                        <tr>
                            <td>
                                @if ($content->status === 'pending')
                                    <input class="review-checkbox" type="checkbox" name="ids[]" value="{{ $content->id }}" form="review-bulk-approve-form">
                                @endif
                            </td>
                            <td>
                                <div class="review-title" data-tooltip="ID {{ $content->id }} · {{ $content->title }}">{{ $content->title }}</div>
                                @if ($content->status === 'rejected' && !empty($content->latest_reject_reason))
                                    <div class="review-reject-note">最近驳回：{{ $content->latest_reject_reason }}</div>
                                    <div class="review-reject-meta">
                                        审核人：{{ $content->latest_reviewer_name ?: '未记录' }}
                                        @if (!empty($content->latest_reviewer_phone))
                                            · {{ $content->latest_reviewer_phone }}
                                        @endif
                                        @if (!empty($content->latest_rejected_at))
                                            · {{ \Illuminate\Support\Carbon::parse($content->latest_rejected_at)->format('Y-m-d H:i') }}
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="review-channel-tag" data-tooltip="{{ $channelTooltip }}">
                                    <span class="review-channel-tag-label">{{ $primaryChannelName }}</span>
                                    @if ($extraChannelCount > 0)
                                        <span class="review-channel-tag-more">+{{ $extraChannelCount }}</span>
                                    @endif
                                </span>
                            </td>
                            <td>
                                <span class="review-status-pill {{ $content->status === 'pending' ? 'is-pending' : 'is-rejected' }}">{{ $statuses[$content->status] ?? $content->status }}</span>
                            </td>
                            <td><span class="review-meta">{{ $content->submitter_name ?: '未记录' }}</span></td>
                            <td><span class="review-meta">{{ \Illuminate\Support\Carbon::parse($content->updated_at)->format('m-d H:i') }}</span></td>
                            <td>
                                <div class="review-actions">
                                    <a class="review-link" href="{{ route('admin.articles.edit', $content->id) }}" aria-label="查看" data-tip="查看">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    @if ($content->status === 'pending')
                                        <form method="POST" action="{{ route('admin.article-reviews.approve', $content->id) }}">
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ url()->full() }}">
                                            <button class="review-link is-approve" type="button" data-approve-title="{{ $content->title }}" data-approve-form aria-label="通过" data-tip="通过">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                            </button>
                                        </form>
                                        <button class="review-danger" type="button" data-open-reject-modal data-content-id="{{ $content->id }}" data-content-title="{{ $content->title }}" data-return-url="{{ url()->full() }}" aria-label="驳回" data-tip="驳回">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 15.5 7-7"/></svg>
                                        </button>
                                    @else
                                        <span class="review-meta">驳回 {{ (int) $content->reject_count }} 次</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>

                <div class="review-bulk-row">
                    <div class="review-bulk-left">
                        <button class="button neutral-action" type="button" id="review-bulk-toggle-all">全选</button>
                        <form id="review-bulk-approve-form" method="POST" action="{{ route('admin.article-reviews.bulk-approve') }}">
                            @csrf
                            <input type="hidden" name="return_url" value="{{ url()->full() }}">
                            <button class="button neutral-action" type="button" id="review-bulk-approve-button">批量审核通过</button>
                        </form>
                    </div>
                    <span class="review-record-badge">{{ $contents->total() }} 条记录</span>
                </div>

                <div class="review-pagination">{{ $contents->links() }}</div>
            @endif
        </div>
    </section>

    <div class="review-modal" id="review-reject-modal" aria-hidden="true">
        <div class="review-modal-card">
            <h3 class="review-modal-title">驳回文章</h3>
            <p class="review-modal-desc" id="review-reject-desc">请填写驳回原因，作者将在编辑页看到这条信息。</p>
            <form id="review-reject-form" method="POST" class="review-modal-form" data-action-template="{{ rtrim(route('admin.article-reviews.reject', ['content' => '__ID__']), '/') }}" data-default-return-url="{{ url()->full() }}">
                @csrf
                <input type="hidden" name="return_url" value="{{ url()->full() }}">
                <div class="review-modal-field">
                    <textarea class="field textarea" name="reason" rows="5" placeholder="请输入驳回原因" maxlength="500" required></textarea>
                    <div class="review-modal-meta">
                        <div class="review-modal-error" id="review-reject-error">请填写驳回原因后再提交。</div>
                        <div class="review-modal-count"><span id="review-reject-count">0</span>/500</div>
                    </div>
                </div>
                <div class="review-modal-actions">
                    <button class="button neutral-action" type="button" data-close-reject-modal>关闭</button>
                    <button class="button" type="submit" id="review-reject-submit">确认驳回</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script src="/js/site-article-reviews.js"></script>
@endpush
