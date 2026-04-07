@extends('layouts.admin')

@section('title', '图宣管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 图宣管理')

@push('styles')
    @include('admin.site._custom_select_styles')
    @include('admin.site.promos._shared_styles')
    <style>
        .promo-index-stack {
            gap: 14px;
        }

        .promo-index-stack .page-header {
            margin-bottom: 8px;
        }

        .promo-filter-card {
            display: grid;
            gap: 16px;
            padding: 18px;
            border: 1px solid #eef1f5;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }

        .promo-toolbar {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) repeat(3, minmax(160px, 0.6fr)) auto;
            gap: 12px;
            align-items: end;
        }

        .promo-toolbar label {
            display: block;
            margin-bottom: 8px;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 600;
        }

        .promo-empty {
            padding: 36px 20px;
            text-align: center;
            color: var(--muted);
            border-radius: 12px;
            background: #fff;
            box-shadow: var(--shadow);
        }

        .promo-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .promo-card {
            display: grid;
            gap: 16px;
            padding: 18px;
            border: 1px solid #e8edf3;
            border-radius: 24px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.05);
        }

        .promo-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .promo-card-title {
            color: #111827;
            font-size: 16px;
            font-weight: 700;
            line-height: 1.5;
        }

        .promo-card-subtitle {
            margin-top: 4px;
            color: #8b94a7;
            font-size: 12px;
            line-height: 1.6;
        }

        .promo-card-preview {
            position: relative;
            height: 190px;
            border-radius: 20px;
            overflow: hidden;
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 28%),
                linear-gradient(135deg, #eef5ff 0%, #dfeafa 100%);
            border: 1px solid #e5edf8;
        }

        .promo-card-preview-track {
            display: flex;
            height: 100%;
            transition: transform 0.28s ease;
            will-change: transform;
        }

        .promo-card-preview-slide {
            flex: 0 0 100%;
            width: 100%;
            height: 100%;
        }

        .promo-card-preview-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .promo-card-preview-empty {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: flex-end;
            padding: 18px;
            color: #445066;
            font-size: 18px;
            font-weight: 700;
            line-height: 1.5;
        }

        .promo-card-preview-nav {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 10px;
            pointer-events: none;
        }

        .promo-card-preview-nav button {
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.48);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            pointer-events: auto;
            backdrop-filter: blur(8px);
        }

        .promo-card-preview-nav button:hover {
            background: rgba(15, 23, 42, 0.62);
        }

        .promo-card-preview-nav svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        .promo-card-preview-dots {
            position: absolute;
            left: 50%;
            bottom: 12px;
            transform: translateX(-50%);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.38);
            backdrop-filter: blur(8px);
        }

        .promo-card-preview-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.42);
            transition: transform 0.18s ease, background 0.18s ease;
        }

        .promo-card-preview-dot.is-active {
            background: rgba(255, 255, 255, 0.98);
            transform: scale(1.18);
        }

        .promo-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .promo-card-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .promo-card-stat {
            display: grid;
            gap: 4px;
            padding: 12px 14px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #edf2f7;
        }

        .promo-card-stat-label {
            color: #8b94a7;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.4;
        }

        .promo-card-stat-value {
            color: #344054;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.5;
        }

        .promo-card-meta {
            display: grid;
            gap: 10px;
        }

        .promo-card-meta-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            color: #667085;
            font-size: 13px;
            line-height: 1.6;
        }

        .promo-card-meta-row strong {
            color: #8b94a7;
            font-size: 12px;
            font-weight: 600;
            flex-shrink: 0;
        }

        .promo-card-meta-row span {
            text-align: right;
            min-width: 0;
        }

        .promo-card-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .promo-status-toggle {
            min-width: 72px;
        }

        .button.promo-status-toggle.is-active,
        .button.promo-status-toggle.is-active:visited {
            color: #15803d;
            border-color: rgba(220, 239, 229, 0.96);
            background: #edf8f1;
            box-shadow: none;
        }

        .button.promo-status-toggle.is-active:hover {
            color: #15803d;
            border-color: rgba(220, 239, 229, 0.96);
            background: #e3f4ea;
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(34, 197, 94, 0.10);
        }

        .button.promo-status-toggle.is-active:active {
            color: #15803d;
            border-color: rgba(220, 239, 229, 0.96);
            background: #dbf0e3;
            transform: translateY(0);
            box-shadow: none;
        }

        .promo-status-toggle.is-muted {
            color: #8b94a7;
        }

        .promo-call-modal[hidden] {
            display: none;
        }

        .promo-call-modal {
            position: fixed;
            inset: 0;
            z-index: 2400;
        }

        .promo-call-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.46);
            backdrop-filter: blur(4px);
        }

        .promo-call-panel {
            position: relative;
            width: min(760px, calc(100% - 32px));
            margin: 48px auto;
            padding: 24px;
            max-height: calc(100vh - 96px);
            overflow-y: auto;
            border-radius: 24px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            border: 1px solid #e5e7eb;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16);
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.72) transparent;
        }

        .promo-call-panel::-webkit-scrollbar {
            width: 8px;
        }

        .promo-call-panel::-webkit-scrollbar-track {
            background: transparent;
        }

        .promo-call-panel::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.72);
            border: 2px solid transparent;
            background-clip: padding-box;
        }

        .promo-call-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .promo-call-title {
            margin: 0;
            color: #111827;
            font-size: 22px;
            line-height: 1.4;
            font-weight: 700;
        }

        .promo-call-desc {
            margin-top: 8px;
            color: #8b94a7;
            font-size: 13px;
            line-height: 1.7;
        }

        .promo-call-close {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            border: 1px solid #e7ebf1;
            background: #fff;
            color: #667085;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .promo-call-close:hover {
            background: #f8fafc;
            color: #344054;
        }

        .promo-call-close svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 1.9;
            fill: none;
        }

        .promo-call-stack {
            display: grid;
            gap: 14px;
        }

        .promo-call-card {
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid #eef2f7;
            background: #ffffff;
        }

        .promo-call-card-label {
            color: #8b94a7;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.4;
            margin-bottom: 8px;
        }

        .promo-call-card-value {
            color: #344054;
            font-size: 14px;
            line-height: 1.8;
        }

        .promo-call-param-list {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }

        .promo-call-param-group {
            margin-top: 18px;
        }

        .promo-call-param-group-title {
            color: #8b94a7;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .promo-call-param-item {
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid #e8edf3;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        }

        .promo-call-param-name {
            color: #1f2937;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 700;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }

        .promo-call-param-desc {
            margin-top: 4px;
            color: #667085;
            font-size: 12px;
            line-height: 1.7;
        }

        .promo-call-code {
            margin: 0;
            padding: 14px 16px;
            border-radius: 16px;
            background: #0f172a;
            color: #f8fafc;
            font-size: 13px;
            line-height: 1.7;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .promo-pagination {
            padding: 18px 6px 4px;
        }

        .promo-pagination nav {
            width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            padding-top: 2px;
        }

        .promo-pagination .pagination-shell {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: nowrap;
            min-width: max-content;
        }

        .promo-pagination .pagination-pages {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .promo-pagination .pagination-button,
        .promo-pagination .pagination-page,
        .promo-pagination .pagination-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            height: 32px;
            min-width: 32px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            color: #4b5563;
            font-size: 13px;
            line-height: 1;
            text-decoration: none;
            transition: all 0.2s;
        }

        .promo-pagination .pagination-page {
            width: 32px;
            padding: 0;
        }

        .promo-pagination .pagination-button {
            border: 0;
            background: transparent;
            min-width: auto;
            padding: 0 4px;
            color: #4b5563;
        }

        .promo-pagination .pagination-button:hover,
        .promo-pagination .pagination-page:hover {
            transform: translateY(-1px);
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .promo-pagination .pagination-button:hover {
            background: transparent;
            border-color: transparent;
            color: #262626;
        }

        .promo-pagination .pagination-page.is-active,
        .promo-pagination .pagination-page.is-active:visited {
            border-color: #374151 !important;
            background: #374151 !important;
            color: #ffffff !important;
            font-weight: 600;
            transform: none;
        }

        .promo-pagination .pagination-button.is-disabled,
        .promo-pagination .pagination-page.is-disabled,
        .promo-pagination .pagination-ellipsis {
            color: #c0c4cc;
            cursor: not-allowed;
        }

        .promo-pagination .pagination-button.is-disabled:hover,
        .promo-pagination .pagination-page.is-disabled:hover {
            transform: none;
            background: #ffffff;
            border-color: #e5e7eb;
        }

        .promo-pagination .pagination-button.is-disabled,
        .promo-pagination .pagination-button.is-disabled:hover {
            background: transparent;
            border-color: transparent;
        }

        .promo-pagination .pagination-icon {
            width: 14px;
            height: 14px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
        }

        @media (max-width: 1200px) {
            .promo-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 1080px) {
            .promo-toolbar {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .promo-grid,
            .promo-card-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <section class="stack promo-index-stack">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">图宣管理</h1>
                <div class="page-header-desc">统一管理模板中的横幅图、轮播图和漂浮图位点，并通过统一的资源库与引用关系维护前台调用。</div>
            </div>
            <div class="promo-header-actions">
                <a class="button" href="{{ route('admin.promos.create', $promoIndexQuery ?? []) }}">新建图宣位</a>
            </div>
        </div>

        <div class="promo-filter-card">
            <form method="GET" action="{{ route('admin.promos.index') }}" class="promo-toolbar">
                <div>
                    <label for="keyword">关键词</label>
                    <input id="keyword" class="field" type="text" name="keyword" value="{{ $keyword }}" placeholder="搜索图宣位名称">
                </div>
                <div>
                    <label for="page_scope">页面范围</label>
                    <div class="site-select" data-site-select>
                        <select id="page_scope" class="field site-select-native" name="page_scope">
                            <option value="">全部范围</option>
                            @foreach ($pageScopes as $scopeCode => $scopeLabel)
                                <option value="{{ $scopeCode }}" @selected($selectedPageScope === $scopeCode)>{{ $scopeLabel }}</option>
                            @endforeach
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ $pageScopes[$selectedPageScope] ?? '全部范围' }}</button>
                        <div class="site-select-panel" data-select-panel role="listbox"></div>
                    </div>
                </div>
                <div>
                    <label for="display_mode">展示模式</label>
                    <div class="site-select" data-site-select>
                        <select id="display_mode" class="field site-select-native" name="display_mode">
                            <option value="">全部模式</option>
                            @foreach ($displayModes as $modeCode => $modeLabel)
                                <option value="{{ $modeCode }}" @selected($selectedDisplayMode === $modeCode)>{{ $modeLabel }}</option>
                            @endforeach
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ $displayModes[$selectedDisplayMode] ?? '全部模式' }}</button>
                        <div class="site-select-panel" data-select-panel role="listbox"></div>
                    </div>
                </div>
                <div>
                    <label for="status">状态</label>
                    <div class="site-select" data-site-select>
                        <select id="status" class="field site-select-native" name="status">
                            <option value="">全部状态</option>
                            <option value="1" @selected($selectedStatus === '1')>启用</option>
                            <option value="0" @selected($selectedStatus === '0')>停用</option>
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ $selectedStatus === '1' ? '启用' : ($selectedStatus === '0' ? '停用' : '全部状态') }}</button>
                        <div class="site-select-panel" data-select-panel role="listbox"></div>
                    </div>
                </div>
                <div class="filter-actions">
                    <button class="button secondary neutral-action" type="submit">筛选</button>
                    <a class="button secondary neutral-action" href="{{ route('admin.promos.index') }}">重置</a>
                </div>
            </form>
        </div>

        <div class="panel">
            @if ($positions->count() === 0)
                <div class="promo-empty">当前还没有图宣位，可以先从“新建图宣位”开始。</div>
            @else
                <div class="promo-grid">
                    @foreach ($positions as $position)
                        <article class="promo-card">
                            <div class="promo-card-top">
                                <div>
                                    <div class="promo-card-title">{{ $position->name }}</div>
                                    <div class="promo-card-subtitle">{{ $position->channel_name ?: '站点默认' }} · {{ $pageScopes[$position->page_scope] ?? $position->page_scope }}</div>
                                </div>
                                <form method="POST" action="{{ route('admin.promos.toggle', ['position' => $position->id] + ($promoIndexQuery ?? [])) }}">
                                    @csrf
                                    <button class="button secondary neutral-action promo-status-toggle {{ (int) $position->status === 1 ? 'is-active' : 'is-muted' }}" type="submit">
                                        {{ (int) $position->status === 1 ? '已启用' : '已停用' }}
                                    </button>
                                </form>
                            </div>

                            <div class="promo-card-preview">
                                @if (!empty($position->preview_items) && count($position->preview_items) > 0)
                                    <div class="promo-card-preview-track" data-promo-preview-track>
                                        @foreach ($position->preview_items as $previewItem)
                                            <div class="promo-card-preview-slide">
                                                <img src="{{ $previewItem['image_url'] }}" alt="{{ $previewItem['title'] ?: $position->name }}">
                                            </div>
                                        @endforeach
                                    </div>
                                    @if (count($position->preview_items) > 1)
                                        <div class="promo-card-preview-nav">
                                            <button type="button" data-promo-preview-prev aria-label="上一张">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"></path></svg>
                                            </button>
                                            <button type="button" data-promo-preview-next aria-label="下一张">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"></path></svg>
                                            </button>
                                        </div>
                                        <div class="promo-card-preview-dots">
                                            @foreach ($position->preview_items as $previewIndex => $previewItem)
                                                <span class="promo-card-preview-dot{{ $previewIndex === 0 ? ' is-active' : '' }}" data-promo-preview-dot></span>
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    <div class="promo-card-preview-empty">待配置图宣内容</div>
                                @endif
                            </div>

                            <div class="promo-meta">
                                <span class="badge-soft">{{ $displayModes[$position->display_mode] ?? $position->display_mode }}</span>
                                <span class="badge-soft muted">最多 {{ (int) $position->max_items }} 项</span>
                                @if (!empty($position->template_name))
                                    <span class="badge-soft muted">模板：{{ $position->template_name }}</span>
                                @endif
                            </div>

                            <div class="promo-card-stats">
                                <div class="promo-card-stat">
                                    <div class="promo-card-stat-label">图宣内容</div>
                                    <div class="promo-card-stat-value">{{ (int) $position->item_count }} 项</div>
                                </div>
                                <div class="promo-card-stat">
                                    <div class="promo-card-stat-label">已启用</div>
                                    <div class="promo-card-stat-value">{{ (int) $position->enabled_item_count }} 项</div>
                                </div>
                                <div class="promo-card-stat">
                                    <div class="promo-card-stat-label">内容状态</div>
                                    <div class="promo-card-stat-value">{{ $position->content_state_label }}</div>
                                </div>
                            </div>

                            <div class="promo-card-meta">
                                @if (!empty($position->preview_link_url))
                                    <div class="promo-card-meta-row">
                                        <strong>跳转地址</strong>
                                        <span>{{ $position->preview_link_url }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="promo-card-actions">
                                <button
                                    class="button secondary neutral-action"
                                    type="button"
                                    data-promo-call-trigger
                                    data-promo-call-name="{{ $position->name }}"
                                    data-promo-call-code="{{ $position->code }}"
                                    data-promo-call-mode="{{ $position->display_mode }}"
                                    data-promo-call-mode-label="{{ $displayModes[$position->display_mode] ?? $position->display_mode }}"
                                    data-promo-call-scope-label="{{ $pageScopes[$position->page_scope] ?? $position->page_scope }}"
                                    data-promo-call-limit="{{ (int) $position->max_items }}"
                                >
                                    调用
                                </button>
                                <a class="button secondary neutral-action" href="{{ route('admin.promos.items.index', ['position' => $position->id] + ($promoIndexQuery ?? [])) }}">内容管理</a>
                                <a class="button secondary neutral-action" href="{{ route('admin.promos.edit', ['position' => $position->id] + ($promoIndexQuery ?? [])) }}">编辑</a>
                                <form method="POST" action="{{ route('admin.promos.destroy', ['position' => $position->id] + ($promoIndexQuery ?? [])) }}" data-promo-delete-form data-promo-delete-name="{{ $position->name }}">
                                    @csrf
                                    <button class="button secondary neutral-action" type="submit">删除</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="promo-pagination">{{ $positions->links() }}</div>
            @endif
        </div>

        <div id="promo-call-modal" class="promo-call-modal" hidden>
            <div class="promo-call-backdrop" data-promo-call-close></div>
            <div class="promo-call-panel" role="dialog" aria-modal="true" aria-labelledby="promo-call-title">
                <div class="promo-call-header">
                    <div>
                        <h3 id="promo-call-title" class="promo-call-title" data-promo-call-title>图宣位调用方法</h3>
                        <div class="promo-call-desc" data-promo-call-desc>按下面的模板写法调用即可。</div>
                    </div>
                    <button class="promo-call-close" type="button" data-promo-call-close aria-label="关闭">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="promo-call-stack">
                    <div class="promo-call-card">
                        <div class="promo-call-card-label" data-promo-call-code-label>引入数据</div>
                        <pre class="promo-call-code" data-promo-call-code-block></pre>
                    </div>
                    <div class="promo-call-card">
                        <div class="promo-call-card-label" data-promo-call-example-label>代入模板示例</div>
                        <pre class="promo-call-code" data-promo-call-example-block></pre>
                    </div>
                    <div class="promo-call-card">
                        <div class="promo-call-card-label">使用说明</div>
                        <div class="promo-call-card-value" data-promo-call-note></div>
                        <div data-promo-call-params></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script>
        (() => {
            document.querySelectorAll('[data-promo-delete-form]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    if (typeof window.showConfirmDialog !== 'function') {
                        return;
                    }

                    event.preventDefault();
                    const name = form.getAttribute('data-promo-delete-name') || '该图宣位';

                    window.showConfirmDialog({
                        title: '确认删除图宣位？',
                        text: `删除后将移除位点配置：${name}`,
                        confirmText: '删除图宣位',
                        onConfirm: () => form.submit(),
                    });
                });
            });

            const callModal = document.getElementById('promo-call-modal');
            const callTitle = callModal?.querySelector('[data-promo-call-title]');
            const callDesc = callModal?.querySelector('[data-promo-call-desc]');
            const callCodeLabel = callModal?.querySelector('[data-promo-call-code-label]');
            const callCodeBlock = callModal?.querySelector('[data-promo-call-code-block]');
            const callExampleLabel = callModal?.querySelector('[data-promo-call-example-label]');
            const callExampleBlock = callModal?.querySelector('[data-promo-call-example-block]');
            const callNote = callModal?.querySelector('[data-promo-call-note]');
            const callParams = callModal?.querySelector('[data-promo-call-params]');

            const openCallModal = (button) => {
                if (!callModal || !callTitle || !callDesc || !callCodeLabel || !callCodeBlock || !callExampleLabel || !callExampleBlock || !callNote || !callParams) {
                    return;
                }

                const name = button.getAttribute('data-promo-call-name') || '图宣位';
                const code = button.getAttribute('data-promo-call-code') || '';
                const mode = button.getAttribute('data-promo-call-mode') || 'single';
                const modeLabel = button.getAttribute('data-promo-call-mode-label') || mode;
                const scopeLabel = button.getAttribute('data-promo-call-scope-label') || '当前页面';
                const limit = Number(button.getAttribute('data-promo-call-limit') || '1') || 1;
                const assignName = mode === 'single' ? 'promoItem' : 'promoItems';
                const callSnippet = mode === 'single'
                    ? `{% set ${assignName} = promo code="${code}" %}`
                    : `{% set ${assignName} = promos code="${code}" display_mode="${mode}" limit="${limit}" %}`;
                const floatingSnippet = `{% for item in ${assignName} %}\n  <div\n    class="promo-floating promo-floating--@{{ item.display.position }} promo-floating--@{{ item.display.animation }}"\n    style="@{{ item.display.style }}"\n    data-floating-key="@{{ item.display.close_storage_key }}"\n    data-floating-expire="@{{ item.display.close_expire_hours }}"\n  >\n    <a class="promo-floating-link" href="@{{ item.link_url ?: '#' }}" target="@{{ item.link_target ?: '_self' }}">\n      <img src="@{{ item.image_url }}" alt="@{{ item.image_alt ?: item.title }}">\n    </a>\n\n    {% if item.display.closable %}\n      <button\n        class="promo-floating-close"\n        type="button"\n        aria-label="关闭漂浮图"\n        data-floating-close\n      >×</button>\n    {% endif %}\n  </div>\n{% endfor %}\n\n<style>\n  .promo-floating {\n    position: fixed;\n  }\n\n  .promo-floating-link,\n  .promo-floating-link img {\n    display: block;\n    width: 100%;\n  }\n\n  .promo-floating-link img {\n    height: auto;\n    border-radius: 16px;\n    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);\n  }\n\n  .promo-floating-close {\n    position: absolute;\n    top: 8px;\n    right: 8px;\n    width: 28px;\n    height: 28px;\n    border: 0;\n    border-radius: 999px;\n    background: rgba(15, 23, 42, 0.66);\n    color: #fff;\n    cursor: pointer;\n  }\n\n  .promo-floating--float {\n    animation: promo-floating-float 3s ease-in-out infinite;\n  }\n\n  .promo-floating--pulse {\n    animation: promo-floating-pulse 2.2s ease-in-out infinite;\n  }\n\n  @keyframes promo-floating-float {\n    0%, 100% { transform: translateY(0); }\n    50% { transform: translateY(-8px); }\n  }\n\n  @keyframes promo-floating-pulse {\n    0%, 100% { transform: scale(1); }\n    50% { transform: scale(1.03); }\n  }\n</style>\n\n<scr` + `ipt>\n  document.querySelectorAll('[data-floating-close]').forEach((button) => {\n    button.addEventListener('click', () => {\n      const floating = button.closest('.promo-floating');\n      if (!floating) {\n        return;\n      }\n\n      const storageKey = floating.dataset.floatingKey || '';\n      const expireHours = Number(floating.dataset.floatingExpire || '24') || 24;\n\n      if (storageKey) {\n        const expireAt = Date.now() + expireHours * 60 * 60 * 1000;\n        window.localStorage.setItem(storageKey, String(expireAt));\n      }\n\n      floating.remove();\n    });\n  });\n\n  document.querySelectorAll('.promo-floating').forEach((floating) => {\n    const storageKey = floating.dataset.floatingKey || '';\n    const rawExpireAt = storageKey ? window.localStorage.getItem(storageKey) : '';\n    const expireAt = Number(rawExpireAt || '0');\n\n    if (expireAt > Date.now()) {\n      floating.remove();\n      return;\n    }\n\n    if (storageKey && rawExpireAt && expireAt <= Date.now()) {\n      window.localStorage.removeItem(storageKey);\n    }\n  });\n</scr` + `ipt>`;
                const carouselSnippet = `<div class="promo-carousel" data-promo-carousel>\n  <div class="promo-carousel-track" data-promo-carousel-track>\n    {% for item in ${assignName} %}\n      <a class="promo-carousel-slide" href="@{{ item.link_url ?: '#' }}" target="@{{ item.link_target ?: '_self' }}">\n        <img src="@{{ item.image_url }}" alt="@{{ item.image_alt ?: item.title }}">\n        {% if item.title or item.subtitle %}\n          <span class="promo-carousel-copy">\n            {% if item.title %}<strong>@{{ item.title }}</strong>{% endif %}\n            {% if item.subtitle %}<em>@{{ item.subtitle }}</em>{% endif %}\n          </span>\n        {% endif %}\n      </a>\n    {% endfor %}\n  </div>\n\n  {% if ${assignName}|length > 1 %}\n    <button class="promo-carousel-arrow is-prev" type="button" data-promo-carousel-prev aria-label="上一张">‹</button>\n    <button class="promo-carousel-arrow is-next" type="button" data-promo-carousel-next aria-label="下一张">›</button>\n\n    <div class="promo-carousel-dots">\n      {% for item in ${assignName} %}\n        <button class="promo-carousel-dot{% if loop.first %} is-active{% endif %}" type="button" data-promo-carousel-dot="@{{ loop.index0 }}" aria-label="切换到第 @{{ loop.index }} 张"></button>\n      {% endfor %}\n    </div>\n  {% endif %}\n</div>\n\n<style>\n  .promo-carousel {\n    position: relative;\n    overflow: hidden;\n    border-radius: 24px;\n  }\n\n  .promo-carousel-track {\n    display: flex;\n    transition: transform .32s ease;\n    will-change: transform;\n  }\n\n  .promo-carousel-slide {\n    position: relative;\n    flex: 0 0 100%;\n    display: block;\n    min-width: 100%;\n  }\n\n  .promo-carousel-slide img {\n    display: block;\n    width: 100%;\n    aspect-ratio: 16 / 6;\n    object-fit: cover;\n  }\n\n  .promo-carousel-copy {\n    position: absolute;\n    left: 24px;\n    right: 24px;\n    bottom: 24px;\n    display: grid;\n    gap: 6px;\n    color: #fff;\n    text-shadow: 0 4px 18px rgba(15, 23, 42, 0.45);\n  }\n\n  .promo-carousel-copy strong {\n    font-size: 28px;\n    line-height: 1.2;\n  }\n\n  .promo-carousel-copy em {\n    font-style: normal;\n    font-size: 14px;\n    line-height: 1.6;\n    opacity: .92;\n  }\n\n  .promo-carousel-arrow {\n    position: absolute;\n    top: 50%;\n    z-index: 2;\n    width: 42px;\n    height: 42px;\n    border: 0;\n    border-radius: 999px;\n    background: rgba(15, 23, 42, 0.45);\n    color: #fff;\n    cursor: pointer;\n    transform: translateY(-50%);\n  }\n\n  .promo-carousel-arrow.is-prev {\n    left: 16px;\n  }\n\n  .promo-carousel-arrow.is-next {\n    right: 16px;\n  }\n\n  .promo-carousel-dots {\n    position: absolute;\n    left: 50%;\n    bottom: 18px;\n    z-index: 2;\n    display: flex;\n    gap: 8px;\n    transform: translateX(-50%);\n  }\n\n  .promo-carousel-dot {\n    width: 10px;\n    height: 10px;\n    padding: 0;\n    border: 0;\n    border-radius: 999px;\n    background: rgba(255, 255, 255, 0.45);\n    cursor: pointer;\n  }\n\n  .promo-carousel-dot.is-active {\n    background: #fff;\n    transform: scale(1.15);\n  }\n</style>\n\n<scr` + `ipt>\n  document.querySelectorAll('[data-promo-carousel]').forEach((carousel) => {\n    const track = carousel.querySelector('[data-promo-carousel-track]');\n    const dots = Array.from(carousel.querySelectorAll('[data-promo-carousel-dot]'));\n    const prev = carousel.querySelector('[data-promo-carousel-prev]');\n    const next = carousel.querySelector('[data-promo-carousel-next]');\n\n    if (!track || dots.length <= 1) {\n      return;\n    }\n\n    let activeIndex = 0;\n    let timerId = 0;\n\n    const sync = () => {\n      track.style.transform = 'translateX(-' + (activeIndex * 100) + '%)';\n      dots.forEach((dot, index) => {\n        dot.classList.toggle('is-active', index === activeIndex);\n      });\n    };\n\n    const goTo = (index) => {\n      activeIndex = (index + dots.length) % dots.length;\n      sync();\n    };\n\n    const restart = () => {\n      if (timerId) {\n        window.clearInterval(timerId);\n      }\n\n      timerId = window.setInterval(() => {\n        goTo(activeIndex + 1);\n      }, 5000);\n    };\n\n    prev?.addEventListener('click', () => {\n      goTo(activeIndex - 1);\n      restart();\n    });\n\n    next?.addEventListener('click', () => {\n      goTo(activeIndex + 1);\n      restart();\n    });\n\n    dots.forEach((dot, index) => {\n      dot.addEventListener('click', () => {\n        goTo(index);\n        restart();\n      });\n    });\n\n    sync();\n    restart();\n  });\n</scr` + `ipt>`;
                const exampleSnippet = mode === 'single'
                    ? `{% if ${assignName} %}\n  <a href="@{{ ${assignName}.link_url ?: '#' }}" target="@{{ ${assignName}.link_target ?: '_self' }}">\n    <img src="@{{ ${assignName}.image_url }}" alt="@{{ ${assignName}.image_alt ?: ${assignName}.title }}">\n  </a>\n{% endif %}`
                    : mode === 'carousel'
                        ? carouselSnippet
                        : mode === 'floating'
                            ? floatingSnippet
                            : `{% for item in ${assignName} %}\n  <a href="@{{ item.link_url ?: '#' }}" target="@{{ item.link_target ?: '_self' }}">\n    <img src="@{{ item.image_url }}" alt="@{{ item.image_alt ?: item.title }}">\n  </a>\n{% endfor %}`;

                callTitle.textContent = mode === 'single'
                    ? '单图调用方法'
                    : mode === 'carousel'
                        ? '轮播图调用方法'
                        : mode === 'floating'
                            ? '漂浮图调用方法'
                            : `${modeLabel}调用方法`;
                callDesc.textContent = `图宣位：${name} · ${scopeLabel} · ${modeLabel}`;
                callCodeLabel.textContent = '引入数据';
                callCodeBlock.textContent = callSnippet;
                callExampleLabel.textContent = '代入模板示例';
                callExampleBlock.textContent = exampleSnippet;
                callNote.textContent = mode === 'single'
                    ? '单图位推荐用 promo 调用，返回单条图宣数据。所属栏目和模板名称如果已配置，前台会自动按当前页面上下文优先匹配。'
                    : mode === 'carousel'
                        ? '轮播图建议直接使用完整容器、轨道、翻页按钮和圆点导航。上面的示例已经包含基础切换和自动轮播逻辑，拿过去就能直接改样式落地。'
                        : mode === 'floating'
                            ? '漂浮图建议直接读取 item.display 下的样式与行为参数，例如 style、animation、closable、close_storage_key。上面的示例已经包含了定位、动效和关闭记忆逻辑。'
                            : `当前位点适合用 promos 调用，返回图宣列表。建议 limit 不超过 ${limit}，模板里按 for 循环渲染即可。`;
                const tagParams = mode === 'single'
                    ? [
                        ['code', '图宣位代码，必填。写这个值，系统才能知道你要取哪一个图宣位。'],
                        ['page_scope', '页面范围，可选。不写时一般会按当前页面自动判断。'],
                        ['template_name', '模板名，可选。如果这个图宣位区分模板，可以用它指定要取哪套模板下的数据。'],
                        ['channel_id', '栏目 id，可选。如果这个图宣位跟栏目有关，可以用它指定栏目。'],
                    ]
                    : mode === 'carousel'
                        ? [
                            ['code', '图宣位代码，必填。写这个值，系统才能找到对应的轮播图位。'],
                            ['page_scope', '页面范围，可选。不写时一般会按当前页面自动判断。'],
                            ['display_mode', '展示模式，建议写成 carousel。这样能明确按轮播图方式取数据。'],
                            ['template_name', '模板名，可选。如果轮播位区分模板，可以用它指定模板。'],
                            ['channel_id', '栏目 id，可选。如果轮播位跟栏目绑定，可以用它指定栏目。'],
                            ['limit', '取几条数据，可选。一般写成这个图宣位实际要显示的张数。'],
                        ]
                        : mode === 'floating'
                            ? [
                                ['code', '图宣位代码，必填。写这个值，系统才能找到对应的漂浮图位。'],
                                ['page_scope', '页面范围，可选。不写时一般会按当前页面自动判断。'],
                                ['display_mode', '展示模式，建议写成 floating。这样能明确按漂浮图方式取数据。'],
                                ['template_name', '模板名，可选。如果漂浮图位区分模板，可以用它指定模板。'],
                                ['channel_id', '栏目 id，可选。如果漂浮图位跟栏目绑定，可以用它指定栏目。'],
                                ['limit', '取几条数据，可选。漂浮图通常建议写 1，避免同时出来多个漂浮挂件。'],
                            ]
                            : [
                                ['code', '图宣位代码，必填。写这个值，系统才能找到对应的图宣位。'],
                                ['page_scope', '页面范围，可选。'],
                                ['display_mode', '展示模式，用来告诉系统你想按哪种方式取数据。'],
                                ['template_name', '模板名，可选。'],
                                ['channel_id', '栏目 id，可选。'],
                                ['limit', '取几条数据，可选。'],
                            ];
                const floatingParams = mode === 'floating'
                    ? [
                        ['item.display.position', '漂浮位置。比如右下、左中、右上，前台用它决定挂件贴在哪个角或哪一侧。'],
                        ['item.display.animation', '动画效果。比如轻浮动、呼吸、摇摆，前台可以直接按这个值切换动画样式。'],
                        ['item.display.offset_x', '横向偏移。控制离左边或右边多远。'],
                        ['item.display.offset_y', '纵向偏移。控制离上边或下边多远。'],
                        ['item.display.width', '漂浮图宽度。前台通常直接拿来控制挂件宽度。'],
                        ['item.display.height', '漂浮图高度。没填时一般按图片原比例显示。'],
                        ['item.display.z_index', '层级。数字越大越靠上，避免被页面别的元素挡住。'],
                        ['item.display.show_on', '显示端。可区分全端、仅电脑端、仅手机端。'],
                        ['item.display.closable', '是否允许关闭。前台可根据它决定要不要显示关闭按钮。'],
                        ['item.display.remember_close', '是否记住关闭状态。打开后，用户关掉一次，下次访问可以继续隐藏。'],
                        ['item.display.close_expire_hours', '关闭记忆时长，单位是小时。过了这个时间可以重新显示。'],
                        ['item.display.style', '系统已经拼好的定位样式。里面通常带有位置、偏移、宽高、层级这些值，模板里可以直接用。'],
                        ['item.display.close_storage_key', '关闭记忆用的本地缓存 key。前台如果要做“关闭后暂时不再显示”，就会用到它。'],
                    ]
                    : [];
                const renderParamItems = (items) => items.map(([nameText, descText]) => `
                    <div class="promo-call-param-item">
                        <div class="promo-call-param-name">${nameText}</div>
                        <div class="promo-call-param-desc">${descText}</div>
                    </div>
                `).join('');
                callParams.innerHTML = `
                    <div class="promo-call-param-group">
                        <div class="promo-call-param-group-title">调用标签参数</div>
                        <div class="promo-call-param-list">
                            ${renderParamItems(tagParams)}
                        </div>
                    </div>
                    ${floatingParams.length ? `
                    <div class="promo-call-param-group">
                        <div class="promo-call-param-group-title">漂浮图专属参数</div>
                        <div class="promo-call-param-list">
                            ${renderParamItems(floatingParams)}
                        </div>
                    </div>
                    ` : ''}
                `;

                callModal.hidden = false;
                document.body.style.overflow = 'hidden';
            };

            const closeCallModal = () => {
                if (!callModal) {
                    return;
                }

                callModal.hidden = true;
                document.body.style.overflow = '';
            };

            document.querySelectorAll('[data-promo-call-trigger]').forEach((button) => {
                button.addEventListener('click', () => openCallModal(button));
            });

            callModal?.querySelectorAll('[data-promo-call-close]').forEach((element) => {
                element.addEventListener('click', closeCallModal);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && callModal && !callModal.hidden) {
                    closeCallModal();
                }
            });

            document.querySelectorAll('.promo-card-preview').forEach((preview) => {
                const track = preview.querySelector('[data-promo-preview-track]');
                const dots = Array.from(preview.querySelectorAll('[data-promo-preview-dot]'));
                const prev = preview.querySelector('[data-promo-preview-prev]');
                const next = preview.querySelector('[data-promo-preview-next]');

                if (!track || dots.length <= 1) {
                    return;
                }

                let activeIndex = 0;

                const sync = () => {
                    track.style.transform = `translateX(-${activeIndex * 100}%)`;
                    dots.forEach((dot, index) => {
                        dot.classList.toggle('is-active', index === activeIndex);
                    });
                };

                prev?.addEventListener('click', () => {
                    activeIndex = activeIndex === 0 ? dots.length - 1 : activeIndex - 1;
                    sync();
                });

                next?.addEventListener('click', () => {
                    activeIndex = activeIndex === dots.length - 1 ? 0 : activeIndex + 1;
                    sync();
                });

                sync();
            });
        })();
    </script>
    @if ($errors->has('promo'))
        <script>
            (() => {
                const message = @json($errors->first('promo'));

                if (message && typeof window.showMessage === 'function') {
                    window.showMessage(message, 'error');
                }
            })();
        </script>
    @endif
@endpush
