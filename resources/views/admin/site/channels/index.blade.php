@extends('layouts.admin')

@section('title', '栏目管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 栏目管理')

@push('styles')
    <link rel="stylesheet" href="/css/site-channels.css">
@endpush

@include('admin.site._custom_select_styles')

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">栏目管理</h2>
            <div class="page-header-desc">用于维护导航、栏目结构与模板绑定关系。</div>
        </div>
        <div class="channel-header-actions">
            <a class="button" href="{{ route('admin.channels.create') }}">新建栏目</a>
        </div>
    </section>

    <section class="panel channel-panel">
        <form method="GET" action="{{ route('admin.channels.index') }}" class="filters filters-card channel-filters">
            <div class="channel-filter-grid">
                <div class="channel-filter-item">
                    <label for="keyword">搜索栏目</label>
                    <input class="field" id="keyword" type="text" name="keyword" value="{{ $keyword }}" placeholder="名称或别名">
                </div>
                <div class="channel-filter-actions">
                    <button class="button neutral-action" type="submit">筛选</button>
                    <a class="button neutral-action" href="{{ route('admin.channels.index') }}">重置</a>
                </div>
            </div>
        </form>

        <div class="channel-list-panel">
            @if ($channels->isEmpty())
                <div class="empty">当前站点还没有栏目，点击右上角“新建栏目”开始搭建信息结构。</div>
            @else
                <div class="table-wrap channel-table">
                <table class="table table-admin">
                    <colgroup>
                        <col class="channel-col-name">
                        <col class="channel-col-attribute">
                        <col class="channel-col-type">
                        <col class="channel-col-slug">
                        <col class="channel-col-nav">
                        <col class="channel-col-actions">
                    </colgroup>
                    <thead>
                    <tr>
                        <th>栏目名称</th>
                        <th>栏目属性</th>
                        <th>类型</th>
                        <th>别名</th>
                        <th>导航</th>
                        <th class="table-actions">操作</th>
                    </tr>
                    </thead>
                    <tbody data-channel-reorder-url="{{ route('admin.channels.reorder') }}">
                    @foreach ($channels as $channel)
                        @php
                            $channelLevelLabel = match ((int) $channel->tree_depth) {
                                0 => '一级栏目',
                                1 => '二级栏目',
                                default => '三级栏目',
                            };
                        @endphp
                        <tr
                            data-channel-row
                            data-channel-id="{{ $channel->id }}"
                            data-parent-id="{{ $channel->parent_id }}"
                            data-depth="{{ (int) $channel->tree_depth }}"
                        >
                            <td>
                                <div class="channel-name-cell {{ (int) $channel->tree_depth > 0 ? 'is-child' : 'is-root' }}">
                                    <div class="channel-tree-row">
                                        <span class="channel-tree-guides" aria-hidden="true">
                                            @foreach ($channel->tree_ancestors as $hasLine)
                                                <span class="channel-tree-guide {{ $hasLine ? 'is-active' : '' }}"></span>
                                            @endforeach
                                            @if ((int) $channel->tree_depth > 0)
                                                <span class="channel-tree-branch {{ $channel->tree_is_last ? 'is-last' : '' }}"></span>
                                            @endif
                                        </span>
                                        <span
                                            class="channel-tree-content {{ $channel->tree_has_children ? 'is-toggleable' : '' }}"
                                            @if ($channel->tree_has_children)
                                                data-toggle-children
                                                data-channel-toggle="{{ $channel->id }}"
                                                aria-expanded="false"
                                                role="button"
                                                tabindex="0"
                                            @endif
                                        >
                                            <span class="channel-drag-handle" aria-label="拖拽排序" data-tooltip="拖拽排序">
                                                <svg viewBox="0 0 20 20" aria-hidden="true">
                                                    <circle cx="6" cy="5" r="1.4"></circle>
                                                    <circle cx="6" cy="10" r="1.4"></circle>
                                                    <circle cx="6" cy="15" r="1.4"></circle>
                                                    <circle cx="14" cy="5" r="1.4"></circle>
                                                    <circle cx="14" cy="10" r="1.4"></circle>
                                                    <circle cx="14" cy="15" r="1.4"></circle>
                                                </svg>
                                            </span>
                                            <span
                                                class="channel-tree-toggle {{ $channel->tree_has_children ? '' : 'is-placeholder' }}"
                                                aria-hidden="true"
                                            >
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="m9 18 6-6-6-6"/>
                                                </svg>
                                            </span>
                                            @if ($channel->tree_has_children || (int) $channel->tree_depth === 0)
                                                <span class="channel-name-icon is-folder {{ (int) $channel->tree_depth === 0 ? 'is-root' : '' }}" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M3 7.5A1.5 1.5 0 0 1 4.5 6h4.086a1.5 1.5 0 0 1 1.06.44l1.414 1.414A1.5 1.5 0 0 0 12.121 8.5H19.5A1.5 1.5 0 0 1 21 10v7.5A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5z"/>
                                                    </svg>
                                                </span>
                                            @else
                                                <span class="channel-name-icon is-file" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z"/>
                                                        <path d="M14 2v5h5"/>
                                                        <path d="M9 13h6"/>
                                                        <path d="M9 17h4"/>
                                                    </svg>
                                                </span>
                                            @endif
                                            <span class="channel-title-text">{{ $channel->name }}</span>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="channel-parent-cell">{{ $channelLevelLabel }}</span></td>
                            <td class="channel-type-cell"><span class="pill">{{ $channelTypes[$channel->type] ?? $channel->type }}</span></td>
                            <td class="channel-slug-cell">{{ $channel->slug }}</td>
                            <td><span class="channel-status-pill {{ $channel->is_nav ? 'is-on' : '' }}">{{ $channel->is_nav ? '显示' : '隐藏' }}</span></td>
                            <td class="table-actions">
                                <div class="channel-action-row">
                                    <a class="channel-action-link" href="{{ route('admin.channels.edit', $channel->id) }}" aria-label="编辑" title="编辑">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </a>
                                    <form id="channel-delete-form-{{ $channel->id }}" method="POST" action="{{ route('admin.channels.destroy', $channel->id) }}">
                                        @csrf
                                        <button class="channel-action-button is-danger js-channel-delete" type="button" data-form-id="channel-delete-form-{{ $channel->id }}" aria-label="删除" title="删除">
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
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script src="/vendor/sortablejs/Sortable.min.js?v=1.15.3"></script>
    <script src="/js/site-channels-index.js"></script>
@endpush
