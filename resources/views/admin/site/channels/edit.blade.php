@extends('layouts.admin')

@section('title', ($isCreate ? '新建' : '编辑') . '栏目 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 栏目管理 / ' . ($isCreate ? '新建栏目' : '编辑栏目'))

@push('styles')
    <link rel="stylesheet" href="/css/site-channels.css">
@endpush

@include('admin.site._custom_select_styles')

@section('content')
    @php
        $currentType = old('type', $channel->type);
    @endphp
    <section class="page-header">
        <div class="page-header-copy">
            <h2 class="page-header-title">{{ $isCreate ? '新建' : '编辑' }}栏目</h2>
            <div class="page-header-desc">{{ $isCreate ? '设置栏目名称、类型与归属。' : '调整栏目基础信息、归属关系与展示方式。' }}</div>
        </div>
        <div class="channel-header-actions">
            <a class="button secondary" href="{{ route('admin.channels.index') }}">返回列表</a>
            <button class="button" type="submit" form="channel-form">{{ $isCreate ? '创建栏目' : '保存修改' }}</button>
        </div>
    </section>

    <section class="panel channel-editor-panel">
        <div class="panel-header">
            <div></div>
            <span class="badge">{{ $isCreate ? '新建模式' : '编辑模式' }}</span>
        </div>

        <form id="channel-form" method="POST" action="{{ $isCreate ? route('admin.channels.store') : route('admin.channels.update', $channel->id) }}" class="stack" novalidate data-slugify-endpoint="{{ route('admin.channels.slugify') }}" data-current-type="{{ $currentType }}" data-validation-errors='@json($errors->all())'>
            @csrf

            <div class="channel-form-layout">
                <div class="channel-form-stack">
                    <div class="form-section channel-section">
                        <div class="stack">
                            @if ($isCreate)
                                <div class="channel-type-grid">
                                    @foreach ($channelTypes as $value => $label)
                                        @php $isActiveType = old('type', $channel->type) === $value; @endphp
                                        <label class="channel-type-card {{ $isActiveType ? 'is-active' : '' }}" data-channel-type-card>
                                            <input class="channel-radio" type="radio" name="type" value="{{ $value }}" @checked($isActiveType)>
                                            <div class="channel-type-card-title">{{ $label }}</div>
                                            <div class="channel-type-card-desc">
                                                @if ($value === 'list')
                                                    适合新闻、公告、活动等栏目列表展示。
                                                @elseif ($value === 'page')
                                                    适合简介、联系我们等固定内容页面。
                                                @else
                                                    适合跳转到外部网址或第三方平台页面。
                                                @endif
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <input type="hidden" name="type" value="{{ $channel->type }}">
                                <div class="value-display">栏目类型：{{ $channelTypes[$channel->type] ?? '栏目类型' }}</div>
                            @endif
                            <span class="field-meta">
                                @error('type')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </span>

                            <div class="split split-form">
                                <div>
                                    <label for="name">栏目名称</label>
                                    <input class="field @error('name') is-error @enderror" id="name" type="text" name="name" value="{{ old('name', $channel->name) }}" maxlength="100" @error('name') aria-invalid="true" @enderror>
                                    <span class="field-meta">
                                        @error('name')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </span>
                                </div>
                                <div>
                                    <label for="slug">栏目别名</label>
                                    @if ($isCreate)
                                        <input class="field @error('slug') is-error @enderror" id="slug" type="text" name="slug" value="{{ old('slug', $channel->slug) }}" inputmode="latin" maxlength="20" @error('slug') aria-invalid="true" @enderror>
                                    @else
                                        <input type="hidden" name="slug" value="{{ $channel->slug }}">
                                        <div class="channel-slug-display">{{ $channel->slug }}</div>
                                    @endif
                                    <span class="field-meta">
                                        @error('slug')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </span>
                                </div>
                            </div>

                            <div class="split split-form">
                                <div>
                                    <label for="parent_id">上级栏目</label>
                                    <div class="site-select channel-parent-select" data-site-select>
                                        <select id="parent_id" name="parent_id" class="field site-select-native" @error('parent_id') aria-invalid="true" @enderror>
                                            <option value="" data-depth="0">顶级栏目</option>
                                            @foreach ($parentChannels as $parentChannel)
                                                <option value="{{ $parentChannel->id }}" data-depth="{{ (int) ($parentChannel->tree_depth ?? 0) }}" data-has-children="{{ !empty($parentChannel->tree_has_children) ? '1' : '0' }}" @selected((string) old('parent_id', $channel->parent_id) === (string) $parentChannel->id)>
                                                    {{ $parentChannel->option_label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button class="site-select-trigger @error('parent_id') is-error @enderror" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ collect($parentChannels)->firstWhere('id', (int) old('parent_id', $channel->parent_id))?->option_label ?? '顶级栏目' }}</button>
                                        <div class="site-select-panel" data-select-panel role="listbox"></div>
                                    </div>
                                    <span class="field-meta">
                                        @error('parent_id')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section channel-section">
                        <div class="form-section-title channel-section-title">栏目设置</div>
                        <div class="stack" id="channel-type-sections">
                            <div class="form-block channel-form-block" data-channel-type-section="list" @if($currentType !== 'list') hidden @endif>
                                <div class="split split-form">
                                    <div>
                                        <label for="list_template">列表模板</label>
                                        <div class="site-select" data-site-select>
                                            <select id="list_template" name="list_template" class="field site-select-native" @disabled($currentType !== 'list') @error('list_template') aria-invalid="true" @enderror>
                                                <option value="">默认列表模板</option>
                                                @foreach ($templateOptions['list'] as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('list_template', $channel->list_template) === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button class="site-select-trigger @error('list_template') is-error @enderror" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ $templateOptions['list'][old('list_template', $channel->list_template)] ?? '默认列表模板' }}</button>
                                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                                        </div>
                                        <span class="field-meta">
                                            @error('list_template')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
                                    </div>
                                    <div>
                                        <label for="detail_template">详情模板</label>
                                        <div class="site-select" data-site-select>
                                            <select id="detail_template" name="detail_template" class="field site-select-native" @disabled($currentType !== 'list') @error('detail_template') aria-invalid="true" @enderror>
                                                <option value="">默认详情模板</option>
                                                @foreach ($templateOptions['detail'] as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('detail_template', $channel->detail_template) === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button class="site-select-trigger @error('detail_template') is-error @enderror" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ $templateOptions['detail'][old('detail_template', $channel->detail_template)] ?? '默认详情模板' }}</button>
                                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                                        </div>
                                        <span class="field-meta">
                                            @error('detail_template')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-block channel-form-block" data-channel-type-section="page" @if($currentType !== 'page') hidden @endif>
                                <label for="page_detail_template">单页模板</label>
                                <div class="site-select" data-site-select>
                                    <select id="page_detail_template" name="detail_template" class="field site-select-native" @disabled($currentType !== 'page') @error('detail_template') aria-invalid="true" @enderror>
                                        <option value="">默认单页模板</option>
                                        @foreach ($templateOptions['page'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('detail_template', $channel->detail_template) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="site-select-trigger @error('detail_template') is-error @enderror" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ $templateOptions['page'][old('detail_template', $channel->detail_template)] ?? '默认单页模板' }}</button>
                                    <div class="site-select-panel" data-select-panel role="listbox"></div>
                                </div>
                                <span class="field-meta">
                                    @error('detail_template')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </span>
                            </div>

                            <div class="form-block channel-form-block" data-channel-type-section="link" @if($currentType !== 'link') hidden @endif>
                                <div class="split split-form">
                                    <div>
                                        <label for="link_url">外链地址</label>
                                        <input class="field @error('link_url') is-error @enderror" id="link_url" type="text" name="link_url" value="{{ old('link_url', $channel->link_url ?? '') }}" inputmode="url" placeholder="https://example.com/page" @disabled($currentType !== 'link') @error('link_url') aria-invalid="true" @enderror>
                                        <span class="field-meta">
                                            @error('link_url')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
                                    </div>
                                    <div>
                                        <label for="link_target">打开方式</label>
                                        <div class="site-select" data-site-select>
                                            <select id="link_target" name="link_target" class="field site-select-native" @disabled($currentType !== 'link') @error('link_target') aria-invalid="true" @enderror>
                                                <option value="_self" @selected(old('link_target', $channel->link_target ?? '_self') === '_self')>当前窗口打开</option>
                                                <option value="_blank" @selected(old('link_target', $channel->link_target ?? '_self') === '_blank')>新窗口打开</option>
                                            </select>
                                            <button class="site-select-trigger @error('link_target') is-error @enderror" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ old('link_target', $channel->link_target ?? '_self') === '_blank' ? '新窗口打开' : '当前窗口打开' }}</button>
                                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                                        </div>
                                        <span class="field-meta">
                                            @error('link_target')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="channel-side-stack">
                    <div class="channel-toggle-card">
                        <div>
                            <div class="channel-toggle-title">导航显示</div>
                            <div class="channel-toggle-desc">开启后，该栏目会在前台导航中显示。</div>
                        </div>
                        <label class="channel-switch">
                            <input type="checkbox" name="is_nav" value="1" @checked(old('is_nav', $channel->is_nav))>
                        </label>
                    </div>
                </aside>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script src="/js/site-channels-edit.js"></script>
@endpush
