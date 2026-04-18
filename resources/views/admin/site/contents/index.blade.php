@extends('layouts.admin')

@section('title', $typeLabel . '管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / ' . $typeLabel . '管理')

@push('styles')
    @include('admin.site._custom_select_styles')
    <link rel="stylesheet" href="/css/site-contents-index.css">
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">{{ $typeLabel }}管理</h2>
            <div class="page-header-desc">用于统一管理{{ $typeLabel }}的查询、筛选与批量操作。</div>
        </div>
        <div class="topbar-right">
            <a class="button" href="{{ $type === 'page' ? route('admin.pages.create') : route('admin.articles.create') }}">新建{{ $typeLabel }}</a>
        </div>
    </section>

    <section class="panel content-page-panel">
        <form method="GET" action="{{ $type === 'page' ? route('admin.pages.index') : route('admin.articles.index') }}" class="content-filter-card">
            <div class="content-filter-grid">
                <div>
                    <label for="keyword">搜索{{ $typeLabel }}</label>
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
                    <label for="status_filter">{{ $statusFilterLabel ?? '发布状态' }}</label>
                    <div class="site-select" data-site-select>
                        <select id="status_filter" name="status" class="field site-select-native">
                            <option value="">全部状态</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ $statuses[$selectedStatus] ?? '全部状态' }}</button>
                        <div class="site-select-panel" data-select-panel role="listbox"></div>
                    </div>
                </div>
                <div class="content-filter-actions">
                    <button class="button neutral-action" type="submit">筛选</button>
                    <a class="button neutral-action" href="{{ $type === 'page' ? route('admin.pages.index') : route('admin.articles.index') }}">重置</a>
                </div>
            </div>
        </form>

        <div class="content-list-panel">
            @if ($contents->isEmpty())
                <div class="empty">当前条件下没有{{ $typeLabel }}记录，点击右上角“新建{{ $typeLabel }}”开始录入。</div>
            @else
                <div class="content-table-wrap">
                <table class="content-table">
                    <thead>
                    <tr>
                        <th></th>
                        <th>标题</th>
                        <th>栏目</th>
                        <th>状态</th>
                        <th>发布时间</th>
                        <th>操作</th>
                    </tr>
                    </thead>
                    <tbody
                        @if ($type === 'article')
                            data-content-reorder-url="{{ route('admin.articles.reorder') }}"
                        @endif
                    >
                    @foreach ($contents as $content)
                        @php
                            $channelNames = collect($content->channel_names ?? [])->filter()->values();
                            $primaryChannelName = $channelNames->first() ?: '未归类';
                            $extraChannelCount = max($channelNames->count() - 1, 0);
                            $channelTooltip = $channelNames->isNotEmpty()
                                ? $channelNames->implode('、')
                                : '未归类';
                            $titleClasses = [];
                            $titleColorClass = match (strtolower((string) ($content->title_color ?? ''))) {
                                '#0047ab' => 'is-color-royal-blue',
                                '#2563eb' => 'is-color-bright-blue',
                                '#7c3aed' => 'is-color-violet',
                                '#db2777' => 'is-color-rose',
                                '#059669' => 'is-color-green',
                                '#d97706' => 'is-color-amber',
                                '#dc2626' => 'is-color-red',
                                default => '',
                            };

                            if ($titleColorClass !== '') {
                                $titleClasses[] = $titleColorClass;
                            }

                            if (! empty($content->title_bold)) {
                                $titleClasses[] = 'is-bold';
                            }

                            if (! empty($content->title_italic)) {
                                $titleClasses[] = 'is-italic';
                            }
                        @endphp
                        <tr
                            @if ($type === 'article')
                                data-content-row
                                data-content-id="{{ $content->id }}"
                            @endif
                        >
                            <td>
                                <input class="content-checkbox" type="checkbox" name="ids[]" value="{{ $content->id }}" form="content-bulk-form">
                            </td>
                            <td>
                                <div class="content-title-wrap">
                                    <div class="content-title-row">
                                        @if ($type === 'article')
                                            <span class="content-drag-handle" aria-label="拖拽排序" data-tooltip="拖拽排序">
                                                <svg viewBox="0 0 20 20" aria-hidden="true">
                                                    <circle cx="6" cy="5" r="1.4"></circle>
                                                    <circle cx="6" cy="10" r="1.4"></circle>
                                                    <circle cx="6" cy="15" r="1.4"></circle>
                                                    <circle cx="14" cy="5" r="1.4"></circle>
                                                    <circle cx="14" cy="10" r="1.4"></circle>
                                                    <circle cx="14" cy="15" r="1.4"></circle>
                                                </svg>
                                            </span>
                                        @endif
                                        <div class="content-title" data-tooltip="{{ $content->title }}">
                                            <span class="content-title-text {{ implode(' ', $titleClasses) }}">{{ $content->title }}</span>
                                            @if ($type === 'article' && ($content->is_top || $content->is_recommend))
                                                <span class="content-title-flags">
                                                    @if ($content->is_top)
                                                        <span class="content-title-flag is-top">顶</span>
                                                    @endif
                                                    @if ($content->is_recommend)
                                                        <span class="content-title-flag is-recommend">精</span>
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    @if (!empty($content->summary) || ($content->status === 'rejected' && !empty($content->latest_reject_reason)) || $content->status === 'pending')
                                        <div class="content-title-meta">
                                            @if (!empty($content->summary))
                                                <div class="content-summary">{{ $content->summary }}</div>
                                            @endif
                                            @if ($content->status === 'rejected' && !empty($content->latest_reject_reason))
                                                <div class="content-review-note">最近驳回：{{ $content->latest_reject_reason }}@if((int) ($content->reject_count ?? 0) > 1) · 共 {{ (int) $content->reject_count }} 次 @endif</div>
                                            @elseif ($content->status === 'pending')
                                                <div class="content-pending-note">已提交审核，待审核人处理后才会正式上线。</div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="content-channel-tag" data-tooltip="{{ $channelTooltip }}">
                                    <span class="content-channel-tag-label">{{ $primaryChannelName }}</span>
                                    @if ($extraChannelCount > 0)
                                        <span class="content-channel-tag-more">+{{ $extraChannelCount }}</span>
                                    @endif
                                </span>
                            </td>
                            <td>
                                @php
                                    $statusClass = match ($content->status) {
                                        'published' => 'is-published',
                                        'pending' => 'is-pending',
                                        'rejected' => 'is-rejected',
                                        default => 'is-draft',
                                    };
                                    $statusLabel = $statuses[$content->status]
                                        ?? match ($content->status) {
                                            'draft' => '草稿',
                                            'pending' => '待审核',
                                            'published' => '已发布',
                                            'offline' => '已下线',
                                            'rejected' => '已驳回',
                                            default => $content->status,
                                        };
                                @endphp
                                <span class="content-status-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td>
                                @if (!empty($content->published_at))
                                    <span class="content-date">
                                        <span class="content-date-day">{{ \Illuminate\Support\Carbon::parse($content->published_at)->format('Y-m-d') }}</span>
                                        <span class="content-date-time">{{ \Illuminate\Support\Carbon::parse($content->published_at)->format('H:i:s') }}</span>
                                    </span>
                                @else
                                    <span class="content-date is-muted">未发布</span>
                                @endif
                            </td>
                            <td>
                                <div class="content-actions">
                                    <a class="content-action-link" href="{{ ($type === 'page' ? route('admin.pages.edit', $content->id) : route('admin.articles.edit', $content->id)) . '?return_to=' . urlencode(request()->fullUrl()) }}" aria-label="编辑" data-tooltip="编辑">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </a>
                                    <form id="delete-content-{{ $content->id }}" method="POST" action="{{ $type === 'page' ? route('admin.pages.destroy', $content->id) : route('admin.articles.destroy', $content->id) }}">
                                        @csrf
                                        <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                        <button class="content-action-danger js-content-delete" type="button" data-form-id="delete-content-{{ $content->id }}" aria-label="删除" data-tooltip="删除">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>

                <div class="content-bulk-row">
                    <div class="content-bulk-left">
                        @php
                            $primaryBulkLabel = ($type === 'article' && $articleRequiresReview)
                                ? '批量提交审核'
                                : '批量发布';
                        @endphp
                        <button class="button neutral-action" type="button" id="content-bulk-toggle-all">全选</button>
                        <div>
                            <div class="site-select field-sm is-dropup content-bulk-select" data-site-select>
                                <select id="content_bulk_action" name="action" class="field field-sm site-select-native" form="content-bulk-form">
                                    @if ($canPublish)
                                        <option value="publish">{{ $primaryBulkLabel }}</option>
                                    @endif
                                    <option value="offline">批量下线</option>
                                    <option value="delete">批量删除</option>
                                </select>
                                <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ $canPublish ? $primaryBulkLabel : '批量下线' }}</button>
                                <div class="site-select-panel" data-select-panel role="listbox"></div>
                            </div>
                        </div>
                        <form id="content-bulk-form" method="POST" action="{{ $type === 'page' ? route('admin.pages.bulk') : route('admin.articles.bulk') }}" class="content-bulk-form">
                            @csrf
                            <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                            <button class="button neutral-action js-bulk-submit" type="button">批量操作</button>
                        </form>
                    </div>
                    <span class="content-record-badge">{{ $contents->total() }} 条记录</span>
                </div>

                <div class="content-pagination">{{ $contents->links() }}</div>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
    @if ($type === 'article')
        <script src="/vendor/sortablejs/Sortable.min.js?v=1.15.3"></script>
    @endif
    <script src="/js/site-contents-index.js"></script>
@endpush
