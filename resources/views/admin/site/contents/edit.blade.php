@extends('layouts.admin')

@section('title', ($isCreate ? '新建' : '编辑') . $typeLabel . ' - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / ' . $typeLabel . '管理 / ' . ($isCreate ? '新建' . $typeLabel : '编辑' . $typeLabel))

@push('styles')
    @include('admin.site._custom_select_styles')
    <link rel="stylesheet" href="{{ asset('css/site-content-edit.css') }}">
@endpush

@section('content')
    @php
        $initialEditorStatus = in_array(old('status', $content->status), ['published', 'pending', 'rejected'], true) ? 'published' : 'draft';
        $initialPublishActionLabel = ($type === 'article' && $articleRequiresReview)
            ? '提交审核'
            : ($isCreate ? '正式发布' : '保存修改');
        $initialSaveActionLabel = ($type === 'article')
            ? ($initialEditorStatus === 'draft' ? '保存草稿' : $initialPublishActionLabel)
            : ($isCreate ? '创建' . $typeLabel : '保存修改');
        $returnTo = (string) request()->query('return_to', old('return_to', ''));
        $backToListUrl = ($returnTo !== '' && str_starts_with($returnTo, url('/')))
            ? $returnTo
            : ($type === 'page' ? route('admin.pages.index') : route('admin.articles.index'));
        $previewUrl = ! $isCreate
            ? ($type === 'page'
                ? route('admin.content-preview.page', ['content' => $content->id])
                : route('admin.content-preview.article', ['content' => $content->id]))
            : '';
    @endphp

    <section class="page-header">
        <div>
            <h2 class="page-header-title">{{ $isCreate ? '新建' : '编辑' }}{{ $typeLabel }}</h2>
            <div class="page-header-desc">当前站点：{{ $currentSite->name }}。{{ $isCreate ? '建议先完成基础信息，再补正文与附件。' : '建议优先修改正文与状态，再处理附件和删除操作。' }}</div>
        </div>
        <div class="topbar-right">
            <a class="button secondary" href="{{ $backToListUrl }}">返回列表</a>
            <button
                class="button"
                type="submit"
                form="content-editor-form"
                data-page-save-button
                data-label-draft="{{ $type === 'article' ? '保存草稿' : ($isCreate ? '创建' . $typeLabel : '保存修改') }}"
                data-label-published="{{ $type === 'article' ? $initialPublishActionLabel : ($isCreate ? '创建' . $typeLabel : '保存修改') }}"
            >
                <span data-page-save-label>{{ $initialSaveActionLabel }}</span>
            </button>
        </div>
    </section>

    <div class="content-editor-floating-actions">
        <button
            class="button"
            type="submit"
            form="content-editor-form"
            data-tip="{{ $initialSaveActionLabel }}"
            aria-label="{{ $initialSaveActionLabel }}"
            data-floating-save-button
        ><span>@if($isCreate)创<br>建@else保<br>存@endif</span></button>
    </div>

    <section class="content-editor-shell">
        <form id="content-editor-form" method="POST" action="{{ $isCreate ? ($type === 'page' ? route('admin.pages.store') : route('admin.articles.store')) : ($type === 'page' ? route('admin.pages.update', $content->id) : route('admin.articles.update', $content->id)) }}" class="stack" novalidate>
            @csrf
            <input type="hidden" name="return_to" value="{{ $returnTo }}">

            <div class="content-editor-main">
                <div class="content-editor-panel primary">
                    <div class="content-editor-heading">
                        <div>
                            <h3 class="content-editor-title">{{ $isCreate ? '开始撰写' : '编辑内容' }}</h3>
                        </div>
                        <div class="content-side-switcher" data-editor-switcher>
                            <button class="content-side-button is-active" type="button" data-pane-target="main">正文内容</button>
                            <button class="content-side-button" type="button" data-pane-target="basic">基础参数</button>
                            @if (! $isCreate && $type === 'article' && $articleRequiresReview && $reviewHistory->isNotEmpty())
                                <button class="content-side-button" type="button" data-pane-target="reviews">审核记录</button>
                            @endif
                        </div>
                    </div>

                    @if (! $isCreate && $type === 'article' && $content->status === 'rejected' && $latestRejectedReview)
                        <section class="content-review-alert">
                            <div class="content-review-alert-title">
                                <span class="content-review-alert-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                                </span>
                                最近一次审核已驳回
                            </div>
                            <div class="content-review-alert-grid">
                                <div class="content-review-alert-item is-full-span">
                                    <span class="content-review-alert-label">驳回原因</span>
                                    <div class="content-review-alert-value">{{ $latestRejectedReview->reason ?: '未填写驳回原因。' }}</div>
                                </div>
                                <div class="content-review-alert-meta">
                                    <div class="content-review-alert-meta-item">
                                        <span class="content-review-alert-label">审核人</span>
                                        <div class="content-review-alert-value">{{ $latestRejectedReview->reviewer_name ?: '未记录' }}</div>
                                    </div>
                                    <div class="content-review-alert-meta-item">
                                        <span class="content-review-alert-label">联系电话</span>
                                        <div class="content-review-alert-value">{{ $latestRejectedReview->reviewer_phone ?: '未记录' }}</div>
                                    </div>
                                    <div class="content-review-alert-meta-item">
                                        <span class="content-review-alert-label">驳回时间</span>
                                        <div class="content-review-alert-value">{{ \Illuminate\Support\Carbon::parse($latestRejectedReview->created_at)->format('Y-m-d H:i') }}</div>
                                    </div>
                                    <div class="content-review-alert-meta-item">
                                        <span class="content-review-alert-label">驳回次数</span>
                                        <div class="content-review-alert-value">{{ $rejectCount }} 次</div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    @endif

                        <div class="content-editor-panels" data-editor-panels>
                            @php
                                $publishedAtValue = old('published_at');
                                if ($publishedAtValue === null) {
                                    $publishedAtSource = $content->published_at
                                        ?: (($content->status ?? null) === 'rejected' ? ($latestRejectedReview->created_at ?? $latestReviewRecord->created_at ?? null) : ($latestReviewRecord->created_at ?? null));

                                    if (! empty($publishedAtSource)) {
                                        $publishedAtValue = \Illuminate\Support\Carbon::parse($publishedAtSource)->format('Y-m-d\TH:i');
                                    }
                                }
                            @endphp

                            <section class="content-editor-pane" data-editor-pane="basic">
                                <div class="content-pane-grid">
                                    @if (in_array($type, ['article', 'page'], true))
                                        <div class="content-pane-card is-plain">
                                            <h4 class="content-pane-card-title">{{ $type === 'page' ? '单页模板' : '详情模板' }}</h4>
                                            <div class="site-select" data-site-select>
                                                <select id="template_name" name="template_name" class="field site-select-native" @error('template_name') aria-invalid="true" @enderror>
                                                    <option value="">{{ $type === 'page' ? '默认单页模板' : '默认详情模板' }}</option>
                                                    @foreach (($templateOptions ?? []) as $value => $label)
                                                        <option value="{{ $value }}" @selected(old('template_name', $content->template_name ?? '') === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <button class="site-select-trigger @error('template_name') is-error @enderror" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ ($templateOptions ?? [])[old('template_name', $content->template_name ?? '')] ?? ($type === 'page' ? '默认单页模板' : '默认详情模板') }}</button>
                                                <div class="site-select-panel" data-select-panel role="listbox"></div>
                                            </div>
                                            @error('template_name')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    @endif

                                </div>
                                <div class="content-pane-grid">
                                    <div class="content-pane-card is-plain">
                                        <h4 class="content-pane-card-title">摘要</h4>
                                        <textarea class="field textarea" id="summary" name="summary">{{ old('summary', $content->summary) }}</textarea>
                                    </div>
                                </div>
                                <div class="content-pane-grid">
                                    <div class="content-pane-card is-plain">
                                        <h4 class="content-pane-card-title">来源</h4>
                                        <input class="field" id="source" type="text" name="source" value="{{ old('source', $content->source ?: $currentSite->name) }}">
                                    </div>
                                </div>
                                <div class="content-pane-grid">
                                    <div class="content-pane-card is-plain">
                                        <h4 class="content-pane-card-title">发布人</h4>
                                        <div class="content-static-display">{{ $publisherName }}</div>
                                    </div>
                                </div>
                            </section>

                            @if (! $isCreate && $type === 'article' && $articleRequiresReview && $reviewHistory->isNotEmpty())
                                <section class="content-editor-pane" data-editor-pane="reviews">
                                    <section class="content-review-history is-flat">
                                        <div class="content-review-history-title">最近审核记录</div>
                                        <div class="content-review-history-list">
                                            @foreach ($reviewHistory as $reviewItem)
                                                @php
                                                    $reviewActionMap = [
                                                        'submitted' => '提交审核',
                                                        'approved' => '审核通过',
                                                        'rejected' => '驳回内容',
                                                    ];
                                                @endphp
                                                <article class="content-review-history-item">
                                                    <div class="content-review-history-top">
                                                        <span class="content-review-history-action is-{{ $reviewItem->action }}">
                                                            {{ $reviewActionMap[$reviewItem->action] ?? '审核记录' }}
                                                        </span>
                                                        <span class="content-review-history-meta">
                                                            {{ $reviewItem->reviewer_name ?: '未记录审核人' }}
                                                            @if ($reviewItem->reviewer_phone)
                                                                · {{ $reviewItem->reviewer_phone }}
                                                            @endif
                                                            · {{ \Illuminate\Support\Carbon::parse($reviewItem->created_at)->format('Y-m-d H:i') }}
                                                        </span>
                                                    </div>
                                                    @if ($reviewItem->action === 'rejected')
                                                        <div class="content-review-history-reason">驳回原因：{{ $reviewItem->reason ?: '未填写驳回原因。' }}</div>
                                                    @elseif ($reviewItem->action === 'submitted')
                                                        <div class="content-review-history-reason">已提交审核，等待审核人处理后才会正式上线。</div>
                                                    @else
                                                        <div class="content-review-history-reason">审核通过后文章已进入正式发布状态。</div>
                                                    @endif
                                                </article>
                                            @endforeach
                                        </div>
                                    </section>
                                </section>
                            @endif

                        </div>

                    <div class="content-main-stack" data-editor-main>
                        <div class="content-field-group">
                            <div class="content-main-top">
                                <div class="content-meta-stack">
                                    <div class="content-main-row">
                                        <label class="content-main-label" for="channel_id_main">栏目</label>
                                        @if ($type === 'article')
                                            <div class="content-channel-select @error('channel_ids') is-error @enderror @error('channel_ids.*') is-error @enderror" data-content-channel-select>
                                                <button class="content-channel-trigger" type="button" data-content-channel-trigger aria-haspopup="dialog" aria-expanded="false">请选择栏目</button>
                                                <div class="content-channel-panel" data-content-channel-panel>
                                                    <div class="content-channel-search" data-channel-search>
                                                        <input class="content-channel-search-input" type="text" placeholder="搜索栏目名称" data-channel-search-input>
                                                        <button class="content-channel-search-clear" type="button" data-channel-search-clear aria-label="清空搜索">
                                                            <svg viewBox="0 0 16 16" aria-hidden="true">
                                                                <path d="M4 4l8 8"></path>
                                                                <path d="M12 4 4 12"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <div class="content-channel-list">
                                                        @foreach ($channels as $channel)
                                                            <label
                                                                class="content-channel-option {{ !empty($channel->is_selectable) ? '' : 'is-disabled' }}"
                                                                data-depth="{{ (int) ($channel->tree_depth ?? 0) }}"
                                                                data-channel-option
                                                                data-channel-keyword="{{ \Illuminate\Support\Str::lower($channel->name) }}"
                                                            >
                                                                @if (!empty($channel->is_selectable))
                                                                    <input
                                                                        class="content-channel-checkbox"
                                                                        type="checkbox"
                                                                        name="channel_ids[]"
                                                                        value="{{ $channel->id }}"
                                                                        data-channel-checkbox
                                                                        data-channel-name="{{ $channel->name }}"
                                                                        @checked(in_array((int) $channel->id, $selectedChannelIds ?? [], true))
                                                                    >
                                                                @else
                                                                    <span class="content-channel-checkbox-spacer" aria-hidden="true"></span>
                                                                @endif
                                                                <span class="content-channel-guides" aria-hidden="true">
                                                                    @foreach (($channel->tree_ancestors ?? []) as $hasLine)
                                                                        <span class="content-channel-guide {{ $hasLine ? 'is-active' : '' }}"></span>
                                                                    @endforeach
                                                                    @if ((int) ($channel->tree_depth ?? 0) > 0)
                                                                        <span class="content-channel-branch {{ !empty($channel->tree_is_last) ? 'is-last' : '' }}"></span>
                                                                    @endif
                                                                </span>
                                                                <span class="content-channel-icon {{ !empty($channel->tree_has_children) || (int) ($channel->tree_depth ?? 0) === 0 ? 'is-folder' : '' }}" aria-hidden="true">
                                                                    @if (!empty($channel->tree_has_children) || (int) ($channel->tree_depth ?? 0) === 0)
                                                                        <svg viewBox="0 0 24 24"><path d="M3 7.5A1.5 1.5 0 0 1 4.5 6h4.086a1.5 1.5 0 0 1 1.06.44l1.414 1.414A1.5 1.5 0 0 0 12.121 8.5H19.5A1.5 1.5 0 0 1 21 10v7.5A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5z"/></svg>
                                                                    @else
                                                                        <svg viewBox="0 0 24 24"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z"/><path d="M14 2v5h5"/><path d="M9 13h6"/><path d="M9 17h4"/></svg>
                                                                    @endif
                                                                </span>
                                                                <span class="content-channel-text">{{ $channel->name }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="field-meta">
                                                @error('channel_ids')
                                                    <span class="form-error">{{ $message }}</span>
                                                @enderror
                                                @error('channel_ids.*')
                                                    <span class="form-error">{{ $message }}</span>
                                                @enderror
                                            </span>
                                            @if(($lockedSelectedChannels ?? collect())->isNotEmpty())
                                                <div class="content-channel-locked-note">
                                                    <div class="content-channel-locked-title">以下栏目已关联，但当前账号无权调整，本次保存会自动保留：</div>
                                                    <div class="content-channel-locked-tags">
                                                        @foreach($lockedSelectedChannels as $lockedChannel)
                                                            <span class="content-channel-locked-tag">{{ $lockedChannel->name }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        @else
                                            <div class="content-channel-select @error('channel_ids') is-error @enderror @error('channel_ids.*') is-error @enderror" data-content-channel-select>
                                                <button class="content-channel-trigger" type="button" data-content-channel-trigger aria-haspopup="dialog" aria-expanded="false">请选择栏目</button>
                                                <div class="content-channel-panel" data-content-channel-panel>
                                                    <div class="content-channel-search" data-channel-search>
                                                        <input class="content-channel-search-input" type="text" placeholder="搜索栏目名称" data-channel-search-input>
                                                        <button class="content-channel-search-clear" type="button" data-channel-search-clear aria-label="清空搜索">
                                                            <svg viewBox="0 0 16 16" aria-hidden="true">
                                                                <path d="M4 4l8 8"></path>
                                                                <path d="M12 4 4 12"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <div class="content-channel-list">
                                                        @foreach ($channels as $channel)
                                                            <label
                                                                class="content-channel-option {{ !empty($channel->is_selectable) ? '' : 'is-disabled' }}"
                                                                data-depth="{{ (int) ($channel->tree_depth ?? 0) }}"
                                                                data-channel-option
                                                                data-channel-keyword="{{ \Illuminate\Support\Str::lower($channel->name) }}"
                                                            >
                                                                @if (!empty($channel->is_selectable))
                                                                    <input
                                                                        class="content-channel-checkbox"
                                                                        type="checkbox"
                                                                        name="channel_ids[]"
                                                                        value="{{ $channel->id }}"
                                                                        data-channel-checkbox
                                                                        data-channel-name="{{ $channel->name }}"
                                                                        @checked(in_array((int) $channel->id, $selectedChannelIds ?? [], true))
                                                                    >
                                                                @else
                                                                    <span class="content-channel-checkbox-spacer" aria-hidden="true"></span>
                                                                @endif
                                                                <span class="content-channel-guides" aria-hidden="true">
                                                                    @foreach (($channel->tree_ancestors ?? []) as $hasLine)
                                                                        <span class="content-channel-guide {{ $hasLine ? 'is-active' : '' }}"></span>
                                                                    @endforeach
                                                                    @if ((int) ($channel->tree_depth ?? 0) > 0)
                                                                        <span class="content-channel-branch {{ !empty($channel->tree_is_last) ? 'is-last' : '' }}"></span>
                                                                    @endif
                                                                </span>
                                                                <span class="content-channel-icon {{ !empty($channel->tree_has_children) || (int) ($channel->tree_depth ?? 0) === 0 ? 'is-folder' : '' }}" aria-hidden="true">
                                                                    @if (!empty($channel->tree_has_children) || (int) ($channel->tree_depth ?? 0) === 0)
                                                                        <svg viewBox="0 0 24 24"><path d="M3 7.5A1.5 1.5 0 0 1 4.5 6h4.086a1.5 1.5 0 0 1 1.06.44l1.414 1.414A1.5 1.5 0 0 0 12.121 8.5H19.5A1.5 1.5 0 0 1 21 10v7.5A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5z"/></svg>
                                                                    @else
                                                                        <svg viewBox="0 0 24 24"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z"/><path d="M14 2v5h5"/><path d="M9 13h6"/><path d="M9 17h4"/></svg>
                                                                    @endif
                                                                </span>
                                                                <span class="content-channel-text">{{ $channel->name }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="field-meta">
                                                @error('channel_ids')
                                                    <span class="form-error">{{ $message }}</span>
                                                @enderror
                                                @error('channel_ids.*')
                                                    <span class="form-error">{{ $message }}</span>
                                                @enderror
                                            </span>
                                            @if(($lockedSelectedChannels ?? collect())->isNotEmpty())
                                                <div class="content-channel-locked-note">
                                                    <div class="content-channel-locked-title">以下栏目已关联，但当前账号无权调整，本次保存会自动保留：</div>
                                                    <div class="content-channel-locked-tags">
                                                        @foreach($lockedSelectedChannels as $lockedChannel)
                                                            <span class="content-channel-locked-tag">{{ $lockedChannel->name }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        @endif
                                    </div>

                                    <div class="content-main-row @if($type === 'article') has-article-flags @endif">
                                        <label class="content-main-label" for="title">标题</label>
                                        <div class="content-title-stack">
                                            @php
                                                $currentEditorStatus = old('status', $content->status);
                                                $editorStatus = in_array($currentEditorStatus, ['published', 'pending', 'rejected'], true) ? 'published' : 'draft';
                                                $publishOptionLabel = ($type === 'article' && $articleRequiresReview)
                                                    ? '提交审核'
                                                    : '正式发布';
                                            @endphp
                                            <div class="content-title-input-row">
                                                <input class="field content-title-input content-main-field @error('title') is-error @enderror" id="title" type="text" name="title" value="{{ old('title', $content->title) }}" required>
                                                <div class="content-title-toolbar" data-title-toolbar>
                                                    <input id="title_color" class="content-title-color-input" type="hidden" name="title_color" value="{{ old('title_color', $content->title_color ?: '') }}">
                                                    <div class="content-color-control">
                                                        <button class="content-style-button content-color-button @if(old('title_color', $content->title_color ?: '')) has-selection @endif" type="button" data-color-trigger data-tip="标题颜色">
                                                            <span data-color-preview></span>
                                                        </button>
                                                        <div class="content-color-picker" data-color-picker>
                                                            @foreach ([
                                                                ['value' => '#0047AB', 'label' => '宝蓝'],
                                                                ['value' => '#2563EB', 'label' => '亮蓝'],
                                                                ['value' => '#7C3AED', 'label' => '紫罗兰'],
                                                                ['value' => '#DB2777', 'label' => '玫红'],
                                                                ['value' => '#059669', 'label' => '松绿'],
                                                                ['value' => '#D97706', 'label' => '琥珀'],
                                                                ['value' => '#DC2626', 'label' => '朱红'],
                                                            ] as $colorOption)
                                                                @php
                                                                    $swatchClass = match (strtolower($colorOption['value'])) {
                                                                        '#0047ab' => 'content-color-swatch--royal-blue',
                                                                        '#2563eb' => 'content-color-swatch--bright-blue',
                                                                        '#7c3aed' => 'content-color-swatch--violet',
                                                                        '#db2777' => 'content-color-swatch--rose',
                                                                        '#059669' => 'content-color-swatch--green',
                                                                        '#d97706' => 'content-color-swatch--amber',
                                                                        '#dc2626' => 'content-color-swatch--red',
                                                                        default => '',
                                                                    };
                                                                @endphp
                                                                <button
                                                                    class="content-color-swatch {{ $swatchClass }} @if(strtolower((string) old('title_color', $content->title_color ?: '')) === strtolower($colorOption['value'])) is-active @endif"
                                                                    type="button"
                                                                    data-color-swatch
                                                                    data-color="{{ $colorOption['value'] }}"
                                                                    data-tip="{{ $colorOption['label'] }}"
                                                                ></button>
                                                            @endforeach
                                                            <button class="content-color-swatch reset @if(old('title_color', $content->title_color ?: '') === '') is-active @endif" type="button" data-color-reset data-tip="默认色"><i></i></button>
                                                        </div>
                                                    </div>
                                                    <input type="hidden" name="title_bold" value="0">
                                                    <label class="content-style-button @if(old('title_bold', $content->title_bold)) is-active @endif" data-style-toggle data-style-label="加粗" data-tip="标题加粗">
                                                        <input id="title_bold" type="checkbox" name="title_bold" value="1" @checked(old('title_bold', $content->title_bold)) hidden>
                                                        B
                                                    </label>
                                                    <input type="hidden" name="title_italic" value="0">
                                                    <label class="content-style-button italic @if(old('title_italic', $content->title_italic)) is-active @endif" data-style-toggle data-style-label="斜体" data-tip="标题斜体">
                                                        <input id="title_italic" type="checkbox" name="title_italic" value="1" @checked(old('title_italic', $content->title_italic)) hidden>
                                                        I
                                                    </label>
                                                    @if ($type === 'article')
                                                        <input type="hidden" name="is_top" value="0">
                                                        <label class="content-style-button @if(old('is_top', $content->is_top)) is-active @endif" data-style-toggle data-style-label="置顶" data-tip="栏目置顶">
                                                            <input id="is_top" type="checkbox" name="is_top" value="1" @checked(old('is_top', $content->is_top)) hidden>
                                                            顶
                                                        </label>
                                                        <input type="hidden" name="is_recommend" value="0">
                                                        <label class="content-style-button @if(old('is_recommend', $content->is_recommend)) is-active @endif" data-style-toggle data-style-label="精华" data-tip="标题精华标识">
                                                            <input id="is_recommend" type="checkbox" name="is_recommend" value="1" @checked(old('is_recommend', $content->is_recommend)) hidden>
                                                            精
                                                        </label>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="content-main-row">
                                        <label class="content-main-label" for="published_at">时间</label>
                                        <div class="content-title-meta-field time-field">
                                            <div class="content-datetime-field">
                                                <input class="field content-datetime-input" id="published_at" type="datetime-local" name="published_at" value="{{ $publishedAtValue }}">
                                                <button class="content-datetime-trigger" type="button" data-datetime-trigger aria-label="选择时间">选择</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="content-main-row">
                                        <div class="content-main-label">状态</div>
                                        <div class="content-title-meta-field">
                                            <div class="content-status-options">
                                                <label class="content-status-option @if($editorStatus === 'draft') is-active @endif">
                                                    <input type="radio" name="status" value="draft" @checked($editorStatus === 'draft')>
                                                    草稿
                                                </label>
                                                @php
                                                    $canRequestPublish = $canPublish || ($type === 'article' && $articleRequiresReview);
                                                @endphp
                                                <label class="content-status-option @if($editorStatus === 'published') is-active @endif @if(! $canRequestPublish) is-disabled @endif">
                                                    <input type="radio" name="status" value="published" @checked($editorStatus === 'published') @disabled(! $canRequestPublish)>
                                                    {{ $publishOptionLabel }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="content-cover-card">
                                    <input class="field content-cover-input" id="cover_image" type="text" name="cover_image" value="{{ old('cover_image', $content->cover_image) }}" data-cover-input>
                                    <div class="content-cover-preview" data-cover-preview data-open-cover-library>
                                        @if (! empty(old('cover_image', $content->cover_image)))
                                            <img src="{{ old('cover_image', $content->cover_image) }}" alt="封面图预览" data-cover-image>
                                        @else
                                            <div class="content-cover-placeholder" data-cover-placeholder>{{ $typeLabel }}封面</div>
                                        @endif
                                        <div class="content-cover-actions">
                                            <button class="content-cover-remove" type="button" data-cover-remove>
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M3 6h18"></path>
                                                    <path d="M8 6V4h8v2"></path>
                                                    <path d="M19 6l-1 14H6L5 6"></path>
                                                    <path d="M10 11v6"></path>
                                                    <path d="M14 11v6"></path>
                                                </svg>
                                                删除封面
                                            </button>
                                        </div>
                                    </div>
                                    <div class="content-cover-meta" data-open-cover-library>
                                        <div class="content-cover-tip">幻灯片或文章图片展示需要上传封面图</div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="content-body-header">
                                    <label class="content-main-label" for="content">正文</label>
                                    @if (! $isCreate)
                                        <a class="button secondary neutral-action content-body-preview-button" href="{{ $previewUrl }}" target="_blank" rel="noreferrer">前台预览</a>
                                    @endif
                                </div>
                                <div class="content-editor-body @error('content') is-error @enderror">
                                    <textarea class="field textarea textarea-lg rich-editor" id="content" name="content">{{ old('content', $content->content) }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </form>
    </section>

    @include('admin.site.attachments._attachment_library_modal')

    <div id="emoji-picker-modal" class="emoji-picker-modal" hidden>
        <div class="emoji-picker-backdrop" data-close-emoji-picker></div>
        <div class="emoji-picker-panel" role="dialog" aria-modal="true" aria-labelledby="emoji-picker-title">
            <div class="emoji-picker-header">
                <div>
                    <h3 id="emoji-picker-title">表情面板</h3>
                    <div class="muted">选择表情后会直接插入正文，最近使用会自动保留。</div>
                </div>
                <button class="button secondary" type="button" data-close-emoji-picker>关闭</button>
            </div>
            <div class="emoji-picker-toolbar">
                <input id="emoji-picker-search" class="field" type="text" placeholder="搜索表情名称">
                <div id="emoji-picker-categories" class="emoji-picker-categories"></div>
            </div>
            <div id="emoji-picker-grid" class="emoji-picker-grid"></div>
        </div>
    </div>

    <div id="video-embed-modal" class="video-embed-modal" hidden>
        <div class="video-embed-backdrop" data-close-video-embed></div>
        <div class="video-embed-panel" role="dialog" aria-modal="true" aria-labelledby="video-embed-title">
            <div class="video-embed-header">
                <div>
                    <h3 id="video-embed-title">插入视频</h3>
                    <div class="muted">粘贴哔哩哔哩视频网页地址，系统会自动解析为可播放视频。</div>
                </div>
                <button class="button secondary" type="button" data-close-video-embed>关闭</button>
            </div>
            <div class="video-embed-grid">
                <div class="video-embed-field video-embed-field-wide">
                    <label for="video-embed-url">哔哩哔哩地址</label>
                    <input id="video-embed-url" class="field" type="text" placeholder="例如：https://www.bilibili.com/video/BV1xx411c7mD/">
                </div>
                <div class="video-embed-field">
                    <label for="video-embed-width">宽度</label>
                    <input id="video-embed-width" class="field" type="text" value="90%" placeholder="90%">
                </div>
                <div class="video-embed-field">
                    <label for="video-embed-height">高度</label>
                    <input id="video-embed-height" class="field" type="text" value="450" placeholder="450">
                </div>
                <div class="video-embed-field">
                    <label for="video-embed-align">对齐方式</label>
                    <div class="site-select" data-site-select>
                        <select id="video-embed-align" class="field site-select-native">
                            <option value="center" selected>居中</option>
                            <option value="left">居左</option>
                            <option value="right">居右</option>
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-expanded="false">居中</button>
                        <div class="site-select-panel" data-select-panel></div>
                    </div>
                </div>
            </div>
            <div id="video-embed-error" class="video-embed-error" hidden></div>
            <div class="action-row video-embed-actions">
                <button id="video-embed-confirm" class="button" type="button">插入视频</button>
            </div>
        </div>
    </div>

    <div
        id="site-content-edit-config"
        hidden
        data-type-label="{{ $typeLabel }}"
        data-bilibili-resolve-url="{{ route('admin.articles.resolve-bilibili') }}"
        data-image-upload-url="{{ route('admin.attachments.image-upload') }}"
        data-editor-errors='@json(array_values(array_filter([$errors->first("title"), $errors->first("content")])))'
    ></div>

@endsection

@push('styles')
    @include('admin.site.attachments._attachment_library_styles')
    <link rel="stylesheet" href="{{ asset('css/site-content-edit-emoji.css') }}">
@endpush

@push('scripts')
    @include('admin.site._custom_select_scripts')
    @include('admin.site.attachments._attachment_library_scripts')
@endpush

@push('scripts')
    <script src="/vendor/tinymce/tinymce.min.js"></script>
    <script src="{{ asset('js/site-content-edit.js') }}"></script>
@endpush
