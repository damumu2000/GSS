@extends('layouts.admin')

@section('title', '回收站 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 回收站')

@push('styles')
    @include('admin.site._custom_select_styles')
    <link rel="stylesheet" href="/css/site-recycle-bin.css">
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">回收站</h2>
            <div class="page-header-desc">统一查看已删除内容，支持筛选、恢复与彻底删除。</div>
        </div>
        <span class="recycle-record-badge">{{ $deletedContents->total() }} 条记录</span>
    </section>

    <section class="panel recycle-page-panel">
        <div class="recycle-filter-card">
            <form method="GET" action="{{ route('admin.recycle-bin.index') }}" class="recycle-filter-grid">
                <div class="recycle-filter-item recycle-filter-item--keyword">
                    <label for="keyword">搜索标题</label>
                    <input class="field" id="keyword" type="text" name="keyword" value="{{ $keyword }}" placeholder="标题关键词">
                </div>
                <div class="recycle-filter-item recycle-filter-item--type">
                    <label for="type">类型筛选</label>
                    <div class="site-select recycle-select-flex" data-site-select>
                        <select id="type" name="type" class="field site-select-native">
                            <option value="">全部类型</option>
                            <option value="page" @selected($selectedType === 'page')>单页面</option>
                            <option value="article" @selected($selectedType === 'article')>文章</option>
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ $selectedType === 'page' ? '单页面' : ($selectedType === 'article' ? '文章' : '全部类型') }}</button>
                        <div class="site-select-panel" data-select-panel role="listbox"></div>
                    </div>
                </div>
                <div class="recycle-filter-actions recycle-filter-actions--fixed">
                    <button class="button neutral-action" type="submit">筛选</button>
                    <a class="button neutral-action" href="{{ route('admin.recycle-bin.index') }}">重置</a>
                </div>
            </form>
        </div>

        <div class="recycle-list-panel">
            @if ($deletedContents->isEmpty())
                <div class="recycle-empty">当前回收站为空。</div>
            @else
                <form id="recycle-bulk-form" method="POST" action="{{ route('admin.recycle-bin.bulk') }}">
                    @csrf
                    <input type="hidden" name="action" value="restore" id="recycle-bulk-action">
                </form>
                <form id="recycle-empty-form" method="POST" action="{{ route('admin.recycle-bin.empty') }}">
                    @csrf
                </form>

                <div class="recycle-list-toolbar">
                    <div class="recycle-bulk-row">
                        <button id="recycle-toggle-all" class="button neutral-action" type="button">全选</button>
                        <div class="site-select recycle-bulk-select" data-site-select>
                            <select id="recycle_bulk_action_select" class="field site-select-native">
                                <option value="restore">批量恢复</option>
                                <option value="delete">批量彻底删除</option>
                            </select>
                            <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">批量恢复</button>
                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                        </div>
                        <button id="recycle-bulk-submit" class="button neutral-action" type="submit" form="recycle-bulk-form">批量操作</button>
                        <button id="recycle-empty-submit" class="button neutral-action" type="submit" form="recycle-empty-form">清空回收站</button>
                    </div>
                    <span class="recycle-record-badge">{{ $deletedContents->total() }} 条记录</span>
                </div>

                <div class="recycle-table-wrap">
                <table class="recycle-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>标题</th>
                            <th>类型</th>
                            <th>栏目</th>
                            <th>删除时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deletedContents as $item)
                            <tr>
                                <td>
                                    <input class="recycle-checkbox js-recycle-checkbox" type="checkbox" name="ids[]" value="{{ $item->id }}" form="recycle-bulk-form">
                                </td>
                                <td>
                                    <div class="recycle-title-wrap" data-tooltip="ID {{ $item->id }} · {{ $item->title }}">
                                        <span class="recycle-title">{{ $item->title }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="recycle-type-tag">{{ $item->type === 'page' ? '单页面' : '文章' }}</span>
                                </td>
                                <td>
                                    <span class="recycle-channel-tag">{{ $item->channel_name ?: '未归类' }}</span>
                                </td>
                                <td>
                                    <span class="recycle-date">{{ \Illuminate\Support\Carbon::parse($item->deleted_at)->format('m-d H:i') }}</span>
                                </td>
                                <td>
                                    <div class="recycle-actions">
                                        <form id="recycle-restore-form-{{ $item->id }}" method="POST" action="{{ route('admin.recycle-bin.restore', $item->id) }}">
                                            @csrf
                                        </form>
                                        <button class="recycle-action-button is-restore"
                                                type="button"
                                                data-recycle-restore-trigger
                                                data-form-id="recycle-restore-form-{{ $item->id }}"
                                                data-content-title="{{ $item->title }}">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M9 8H5v4"/>
                                                <path d="M5.5 11.5A7 7 0 1 0 8.2 6.2"/>
                                            </svg>
                                            <span class="recycle-tooltip">恢复内容</span>
                                        </button>

                                        <form id="recycle-destroy-form-{{ $item->id }}" method="POST" action="{{ route('admin.recycle-bin.destroy', $item->id) }}">
                                            @csrf
                                        </form>
                                        <button class="recycle-action-button is-destroy"
                                                type="button"
                                                data-recycle-destroy-trigger
                                                data-form-id="recycle-destroy-form-{{ $item->id }}"
                                                data-content-title="{{ $item->title }}">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M3 6h18"/>
                                                <path d="M8 6V4h8v2"/>
                                                <path d="M19 6l-1 14H6L5 6"/>
                                                <path d="M10 11v6M14 11v6"/>
                                            </svg>
                                            <span class="recycle-tooltip">彻底删除</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>

                <div class="recycle-pagination">{{ $deletedContents->links() }}</div>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script src="/js/site-recycle-bin.js"></script>
@endpush
