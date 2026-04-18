@extends('layouts.admin')

@section('title', '资源库管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '资源库管理')

@php
    $attachmentSystemSettings = app(\App\Support\SystemSettings::class);
    $attachmentAutoCompressLabel = $attachmentSystemSettings->attachmentImageAutoCompressEnabled() ? '自动压缩已开启' : '自动压缩未开启';
    $attachmentRuleTooltipLines = [
        '支持 ' . strtoupper(implode(' / ', $attachmentSystemSettings->attachmentAllowedExtensions())),
        sprintf(
            '单文件不超过 %dMB；图片不超过 %dMB；最大 %d×%d 像素。',
            $attachmentSystemSettings->attachmentMaxSizeMb(),
            $attachmentSystemSettings->attachmentImageMaxSizeMb(),
            $attachmentSystemSettings->attachmentImageMaxWidth(),
            $attachmentSystemSettings->attachmentImageMaxHeight()
        ),
    ];
@endphp

@push('styles')
    @include('admin.site._custom_select_styles')
    <link rel="stylesheet" href="/css/site-attachments-index.css">
@endpush

@section('content')
    <div id="attachment-index-config"
         hidden
         data-upload-url="{{ route('admin.attachments.library-upload') }}"
         data-replace-url-template="{{ route('admin.attachments.replace', ['attachment' => '__ATTACHMENT__']) }}"
         data-usage-url-template="{{ route('admin.attachments.usages', ['attachment' => '__ATTACHMENT__']) }}"></div>
    <section class="page-header">
        <div>
            <h2 class="page-header-title">资源库管理</h2>
            <div class="page-header-desc">统一管理站点文件，支持筛选、预览、批量处理与上传。</div>
        </div>
        <div class="page-header-actions">
            <form id="attachment-upload-form" method="POST" action="{{ route('admin.attachments.store') }}" enctype="multipart/form-data">
                @csrf
                <input id="attachment-upload-file" type="file" name="files[]" hidden multiple required>
            </form>
            <input id="attachment-replace-file" type="file" hidden>
            <span id="attachment-upload-status" class="attachment-upload-status" aria-live="polite"></span>
            <button id="attachment-upload-trigger" class="button" type="button">上传新资源</button>
        </div>
    </section>

    <section class="attachment-panel attachment-main">
            <div class="attachment-toolbar">
                <form method="GET" action="{{ route('admin.attachments.index') }}" class="attachment-toolbar-grid">
                    <div class="attachment-filter-item is-search">
                        <label for="keyword">搜索</label>
                        <input class="field" id="keyword" type="text" name="keyword" value="{{ $keyword }}" placeholder="搜索文件名">
                    </div>
                    <div class="attachment-filter-item">
                        <label for="filter">类型</label>
                        <div class="site-select" data-site-select>
                            <select id="filter" name="filter" class="field site-select-native">
                                <option value="all" @selected($selectedFilter === 'all')>全部类型</option>
                                <option value="image" @selected($selectedFilter === 'image')>仅图片</option>
                                <option value="file" @selected($selectedFilter === 'file')>仅文件</option>
                            </select>
                            <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">
                                {{ $selectedFilter === 'image' ? '仅图片' : ($selectedFilter === 'file' ? '仅文件' : '全部类型') }}
                            </button>
                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                        </div>
                    </div>
                    <div class="attachment-filter-item">
                        <label for="usage">引用</label>
                        <div class="site-select" data-site-select>
                            <select id="usage" name="usage" class="field site-select-native">
                                <option value="all" @selected($selectedUsage === 'all')>全部引用状态</option>
                                <option value="used" @selected($selectedUsage === 'used')>仅已引用</option>
                                <option value="unused" @selected($selectedUsage === 'unused')>仅未引用</option>
                            </select>
                            <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">
                                {{ $selectedUsage === 'used' ? '仅已引用' : ($selectedUsage === 'unused' ? '仅未引用' : '全部引用状态') }}
                            </button>
                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                        </div>
                    </div>
                    <div class="attachment-filter-item">
                        <label for="sort">排序</label>
                        <div class="site-select" data-site-select>
                            <select id="sort" name="sort" class="field site-select-native">
                                <option value="latest" @selected($selectedSort === 'latest')>最新上传</option>
                                <option value="oldest" @selected($selectedSort === 'oldest')>最早上传</option>
                            </select>
                            <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">
                                {{ $selectedSort === 'oldest' ? '最早上传' : '最新上传' }}
                            </button>
                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                        </div>
                    </div>
                    <div class="attachment-bulk-actions">
                        <button class="button neutral-action" type="submit">筛选</button>
                        <a class="button secondary neutral-action" href="{{ route('admin.attachments.index') }}">重置</a>
                    </div>
                </form>
                @if ($unusedDays === 30)
                    <div class="attachment-filter-note">
                        当前仅显示上传满 30 天且未被引用的资源
                        <a href="{{ route('admin.attachments.index') }}">清除筛选</a>
                    </div>
                @endif
                @error('file')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="attachment-list-panel">
                <div class="panel-header attachment-panel-header-reset">
                    <div></div>
                    <span class="badge attachment-summary-badge">
                        <span>
                            {{ $attachments->total() }} 个文件 · 已用 {{ $attachmentTotalSizeLabel }} / {{ $attachmentStorageLimitLabel }}
                            <span class="attachment-summary-status"> · {{ $attachmentAutoCompressLabel }}</span>
                        </span>
                        <button class="attachment-rule-hint" type="button" aria-label="查看上传限制说明">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M12 10v6"></path>
                                <path d="M12 7.5h.01"></path>
                            </svg>
                            <span class="attachment-rule-tooltip" role="tooltip">
                                @foreach ($attachmentRuleTooltipLines as $line)
                                    <span class="attachment-rule-tooltip-line">{{ $line }}</span>
                                @endforeach
                            </span>
                        </button>
                    </span>
                </div>

                @if ($attachments->isEmpty())
                    <div class="attachment-empty">当前站点还没有上传附件，可以直接使用上方按钮上传新的站点资源。</div>
                @else
                    <form id="attachment-bulk-form" method="POST" action="{{ route('admin.attachments.bulk') }}">
                        @csrf
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                    </form>
                    <div class="attachment-library-grid">
                    @foreach ($attachments as $attachment)
                        @php
                            $isImage = in_array(strtolower((string) $attachment->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                            $previewText = strtoupper((string) ($attachment->extension ?: 'FILE'));
                            $dimensionLabel = $isImage && $attachment->width && $attachment->height
                                ? sprintf('%d×%d', (int) $attachment->width, (int) $attachment->height)
                                : '';
                        @endphp
                        <article class="attachment-card {{ $attachment->usage_count > 0 ? 'is-used' : '' }}">
                            <label class="attachment-select">
                                <input class="attachment-checkbox" type="checkbox" name="ids[]" value="{{ $attachment->id }}" form="attachment-bulk-form">
                            </label>
                            <div class="attachment-preview">
                                @if ($isImage && $attachment->url)
                                    <img src="{{ $attachment->url }}" alt="{{ $attachment->origin_name }}">
                                @else
                                    {{ $previewText }}
                                @endif
                            </div>
                            <div class="attachment-name">{{ $attachment->origin_name }}</div>
                            <div class="attachment-meta">
                                <div class="attachment-meta-line">
                                    <div class="attachment-meta-line-main">
                                        <span>
                                            {{ strtoupper($attachment->extension ?: '-') }}{{ $isImage ? ' · 图片资源' : ' · 附件文件' }}
                                            @if ($dimensionLabel !== '')
                                                <span class="attachment-dimension"> · {{ $dimensionLabel }}</span>
                                            @endif
                                        </span>
                                    </div>
                                    <strong>{{ number_format($attachment->size / 1024, 1) }} KB</strong>
                                </div>
                                <div class="attachment-meta-line is-usage-line">
                                    <span class="attachment-meta-primary">
                                        @if ($attachment->usage_count > 0)
                                            <span class="attachment-used-indicator" aria-label="该附件已被引用"></span>
                                        @endif
                                        引用 {{ $attachment->usage_count }} 次
                                        @if ($attachment->usage_count > 0)
                                            <button class="attachment-usage-link"
                                                    type="button"
                                                    data-attachment-usage-trigger
                                                    data-attachment-id="{{ $attachment->id }}"
                                                    data-attachment-name="{{ $attachment->origin_name }}">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12Z"/>
                                                    <circle cx="12" cy="12" r="3"/>
                                                </svg>
                                                查看
                                            </button>
                                        @endif
                                    </span>
                                    <span class="attachment-meta-secondary">
                                        <time>{{ \Illuminate\Support\Carbon::parse($attachment->created_at)->format('m-d H:i') }}</time>
                                        <span>{{ $attachment->uploaded_by_name ?? '未记录' }}</span>
                                    </span>
                                </div>
                            </div>
                            <div class="attachment-actions">
                                <div class="attachment-actions-left">
                                    @if ($attachment->url)
                                        <a class="button secondary" href="{{ $attachment->url }}" target="_blank">预览</a>
                                    @endif
                                </div>
                                <div class="attachment-actions-right">
                                    <button class="button secondary attachment-replace-button"
                                            type="button"
                                            data-attachment-replace-trigger
                                            data-attachment-id="{{ $attachment->id }}"
                                            data-attachment-name="{{ $attachment->origin_name }}"
                                            data-attachment-extension="{{ strtolower((string) ($attachment->extension ?: '')) }}">替换</button>
                                    @if ($attachment->usage_count <= 0)
                                        <form id="attachment-delete-form-{{ $attachment->id }}" method="POST" action="{{ route('admin.attachments.destroy', $attachment->id) }}">
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                            <button class="button secondary attachment-danger"
                                                    type="button"
                                                    data-attachment-delete-trigger
                                                    data-form-id="attachment-delete-form-{{ $attachment->id }}"
                                                    data-attachment-name="{{ $attachment->origin_name }}">删除</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                    </div>
                    <div class="attachment-bulk-row">
                        <button id="attachment-select-all" class="button neutral-action" type="button">全选</button>
                        <button id="attachment-bulk-submit" class="button neutral-action" type="submit" form="attachment-bulk-form">批量删除</button>
                        <a class="attachment-unused-filter {{ $unusedDays === 30 ? 'is-active' : '' }}"
                           href="{{ route('admin.attachments.index', ['unused_days' => 30]) }}">
                            30天未引用资源
                        </a>
                    </div>
                    <div class="attachment-pagination">{{ $attachments->links() }}</div>
                @endif
            </div>
    </section>

    <div id="attachment-usage-modal" class="attachment-usage-modal" hidden>
        <div class="attachment-usage-backdrop" data-close-attachment-usage></div>
        <div class="attachment-usage-panel" role="dialog" aria-modal="true" aria-labelledby="attachment-usage-title">
            <div class="attachment-usage-header">
                <div>
                    <h3 class="attachment-usage-title" id="attachment-usage-title">引用详情</h3>
                    <div class="attachment-usage-desc" id="attachment-usage-desc">正在加载附件引用信息...</div>
                </div>
                <button class="attachment-usage-close" type="button" data-close-attachment-usage aria-label="关闭">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>
            </div>
            <div class="attachment-usage-loading" id="attachment-usage-loading">正在整理该附件的引用内容...</div>
            <div class="attachment-usage-list" id="attachment-usage-list" hidden></div>
            <div class="attachment-usage-empty" id="attachment-usage-empty" hidden>当前没有找到可见的引用内容。</div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script src="/js/site-attachments-index.js"></script>
@endpush
