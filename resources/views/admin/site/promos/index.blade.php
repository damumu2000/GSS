@extends('layouts.admin')

@section('title', '图宣管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 图宣管理')

@push('styles')
    @include('admin.site._custom_select_styles')
    @include('admin.site.promos._shared_styles')
    <link rel="stylesheet" href="{{ asset('css/site-promos-index.css') }}">
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
                        <div class="promo-call-card-label">样式文件：/css/promo-snippets.css</div>
                        <pre class="promo-call-code" data-promo-call-css-block></pre>
                    </div>
                    <div class="promo-call-card">
                        <div class="promo-call-card-label">脚本文件：/js/promo-snippets.js</div>
                        <pre class="promo-call-code" data-promo-call-js-block></pre>
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

    <div
        id="site-promos-index-config"
        hidden
        data-promo-error-message="{{ $errors->first('promo') }}"
    ></div>
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script src="{{ asset('js/site-promos-index.js') }}"></script>
@endpush
