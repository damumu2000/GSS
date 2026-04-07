@extends('layouts.admin')

@section('title', ($isCreate ? '新建' : '编辑') . '栏目 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 栏目管理 / ' . ($isCreate ? '新建栏目' : '编辑栏目'))

@push('styles')
    @include('admin.site._custom_select_styles')
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-start;
            padding: 24px 32px;
            margin: -28px -28px 24px;
            background: #ffffff;
            border-bottom: 1px solid #f0f0f0;
        }

        .page-header-title {
            margin: 0;
            color: #262626;
            font-size: 20px;
            line-height: 1.4;
            font-weight: 700;
        }

        .page-header-desc {
            margin-top: 8px;
            color: #8c8c8c;
            font-size: 14px;
            line-height: 1.7;
            max-width: 760px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .channel-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            white-space: nowrap;
            min-width: 0;
        }

        .channel-editor-panel {
            border-radius: 28px;
            overflow: visible;
        }

        .channel-section {
            padding: 22px 20px;
            border-radius: 24px;
            border: 1px solid #edf2f7;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.03);
            overflow: visible;
        }

        .channel-section label {
            display: block;
            margin-bottom: 8px;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 600;
        }

        .channel-form-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 22px;
        }

        .field-meta {
            min-height: 0;
            display: grid;
            align-content: start;
            gap: 6px;
            margin-top: 6px;
        }

        .channel-editor-panel .form-error {
            display: none;
        }

        .site-select-trigger.is-error {
            border-color: #ff4d4f;
            box-shadow: 0 0 0 3px rgba(255, 77, 79, 0.12);
        }

        .channel-form-stack,
        .channel-side-stack {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .channel-type-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .channel-type-card {
            position: relative;
            padding: 14px 14px 12px;
            border-radius: 20px;
            border: 1px solid #e6edf6;
            background: #ffffff;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease, background-color 0.18s ease;
        }

        .channel-type-card:not(.is-readonly):hover {
            border-color: var(--primary-border-soft);
            background: var(--primary-soft);
            box-shadow: 0 10px 24px color-mix(in srgb, var(--primary, #0047AB) 12%, transparent);
            transform: translateY(-1px);
        }

        .channel-type-card.is-active {
            border-color: var(--primary-border-soft);
            background: var(--tag-bg);
            box-shadow: 0 10px 26px color-mix(in srgb, var(--primary, #0047AB) 16%, transparent);
            transform: translateY(-1px);
        }

        .channel-type-card.is-error {
            border-color: #ff4d4f;
            box-shadow: 0 0 0 3px rgba(255, 77, 79, 0.10);
        }

        .channel-type-card.is-readonly {
            cursor: default;
        }

        .channel-type-card-title {
            color: #1f2937;
            font-size: 16px;
            font-weight: 700;
        }

        .channel-type-card.is-active .channel-type-card-title,
        .channel-type-card:not(.is-readonly):hover .channel-type-card-title {
            color: var(--primary-dark);
        }

        .channel-type-card-desc {
            margin-top: 6px;
            color: #8c96a7;
            font-size: 13px;
            line-height: 1.6;
        }

        .channel-type-card.is-active .channel-type-card-desc,
        .channel-type-card:not(.is-readonly):hover .channel-type-card-desc {
            color: color-mix(in srgb, var(--primary, #0047AB) 56%, #64748b 44%);
        }

        .channel-radio {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .channel-form-block[hidden] {
            display: none !important;
        }

        .channel-form-block .site-select {
            z-index: 1;
        }

        .channel-form-block .site-select.is-open {
            z-index: 60;
        }

        .channel-parent-select .site-select-option.is-tree-option {
            min-height: 44px;
            align-items: stretch;
        }

        .channel-parent-select .site-select-option.is-tree-option .site-select-option-label {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-height: 22px;
            padding-left: calc(var(--option-depth, 0) * 24px);
        }

        .channel-parent-select .site-select-option.is-tree-option .site-select-option-label::before {
            content: '';
            position: absolute;
            left: calc((var(--option-depth, 0) * 24px) - 12px);
            top: 2px;
            bottom: 2px;
            width: 1px;
            background: rgba(0, 71, 171, 0.08);
            opacity: 0;
        }

        .channel-parent-select .site-select-option.is-tree-option[data-depth="1"] .site-select-option-label::before,
        .channel-parent-select .site-select-option.is-tree-option[data-depth="2"] .site-select-option-label::before,
        .channel-parent-select .site-select-option.is-tree-option[data-depth="3"] .site-select-option-label::before,
        .channel-parent-select .site-select-option.is-tree-option[data-depth="4"] .site-select-option-label::before {
            opacity: 1;
        }

        .channel-parent-select .site-select-option.is-tree-option[data-depth="0"] .site-select-option-label {
            font-weight: 600;
        }

        .channel-parent-select .site-select-option-label-text {
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .channel-parent-select .site-select-option-icon {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            color: #0047AB;
            opacity: 0.92;
        }

        .channel-parent-select .site-select-option-icon.is-leaf {
            color: #94a3b8;
        }

        .channel-helper {
            color: #64748b;
            line-height: 1.7;
        }

        .channel-toggle-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid #edf2f7;
            background: #ffffff;
        }

        .channel-slug-display {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            padding: 0 14px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #334155;
            font-size: 15px;
            line-height: 1;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .channel-section > .stack,
        .channel-form-block > .split,
        .channel-form-block > .stack {
            gap: 22px;
        }

        .channel-toggle-title {
            color: #1f2937;
            font-size: 15px;
            font-weight: 700;
        }

        .channel-toggle-desc {
            margin-top: 6px;
            color: #8c96a7;
            font-size: 13px;
            line-height: 1.7;
        }

        .channel-switch {
            position: relative;
            width: 54px;
            height: 30px;
            border-radius: 999px;
            background: #e7eef8;
            border: 1px solid #d2deef;
            flex-shrink: 0;
            transition: background 0.18s ease, border-color 0.18s ease;
        }

        .channel-switch input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            margin: 0;
        }

        .channel-switch::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 22px;
            height: 22px;
            border-radius: 999px;
            background: #ffffff;
            box-shadow: 0 5px 12px rgba(15, 23, 42, 0.14);
            transition: transform 0.18s ease;
        }

        .channel-switch:has(input:checked) {
            background: rgba(0, 71, 171, 0.16);
            border-color: rgba(0, 71, 171, 0.22);
        }

        .channel-switch:has(input:checked)::after {
            transform: translateX(24px);
            background: #0047AB;
        }

        @media (max-width: 720px) {
            .page-header {
                margin: -24px -18px 20px;
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
            }

            .channel-form-layout,
            .channel-type-grid,
            .channel-slug-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

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

        <form id="channel-form" method="POST" action="{{ $isCreate ? route('admin.channels.store') : route('admin.channels.update', $channel->id) }}" class="stack" novalidate>
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
    <script>
        (() => {
            const form = document.getElementById('channel-form');
            const nameInput = document.getElementById('name');
            const slugInput = document.getElementById('slug');
            const linkUrlInput = document.getElementById('link_url');
            const typeCards = Array.from(document.querySelectorAll('[data-channel-type-card]'));
            const typeInputs = Array.from(document.querySelectorAll('input[name="type"]'));
            const sections = Array.from(document.querySelectorAll('[data-channel-type-section]'));
            const slugifyEndpoint = @json(route('admin.channels.slugify'));
            const channelNamePattern = /^[\u3400-\u9FFF\uF900-\uFAFFA-Za-z0-9_\-\s·()（）]+$/u;

            let slugManuallyEdited = slugInput.value.trim() !== '';
            let slugRequestToken = 0;

            const normalizeSlug = (value) => {
                return value
                    .normalize('NFKC')
                    .trim()
                    .replace(/[^A-Za-z0-9_-]+/g, '-')
                    .replace(/-{2,}/g, '-')
                    .replace(/_{2,}/g, '_')
                    .replace(/^[-_]+|[-_]+$/g, '')
                    .slice(0, 20);
            };

            const normalizeGeneratedSlug = (value) => {
                return value
                    .normalize('NFKC')
                    .trim()
                    .replace(/[^A-Za-z0-9_-]+/g, '')
                    .replace(/-{2,}/g, '-')
                    .replace(/_{2,}/g, '_')
                    .replace(/^[-_]+|[-_]+$/g, '')
                    .slice(0, 20)
                    .toLowerCase();
            };

            const buildSlug = async (text) => {
                const normalizedText = text.trim();

                if (normalizedText === '') {
                    return '';
                }

                const requestToken = ++slugRequestToken;

                try {
                    const response = await fetch(`${slugifyEndpoint}?name=${encodeURIComponent(normalizedText)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('slugify failed');
                    }

                    const payload = await response.json();

                    if (requestToken !== slugRequestToken) {
                        return null;
                    }

                    return normalizeGeneratedSlug(payload.slug || '');
                } catch (error) {
                    return normalizeGeneratedSlug(normalizedText);
                }
            };

            const syncTypeView = () => {
                const currentType = (typeInputs.find((input) => input.checked)?.value) || 'list';

                typeCards.forEach((card) => {
                    const radio = card.querySelector('input[name="type"]');
                    card.classList.toggle('is-active', !!radio?.checked);
                });

                sections.forEach((section) => {
                    const isActive = section.dataset.channelTypeSection === currentType;
                    section.hidden = !isActive;
                    section.querySelectorAll('input, select, textarea').forEach((field) => {
                        field.disabled = !isActive;
                    });
                });

            };

            const getCurrentType = () => {
                const checkedType = typeInputs.find((input) => input.checked);
                return checkedType?.value || @json($currentType);
            };

            const clearFieldError = (field) => {
                if (!field) {
                    return;
                }

                field.classList.remove('is-error');
                field.removeAttribute('aria-invalid');
            };

            const setFieldError = (field) => {
                if (!field) {
                    return;
                }

                field.classList.add('is-error');
                field.setAttribute('aria-invalid', 'true');
            };

            window.formatAdminErrorMessages = window.formatAdminErrorMessages || ((messages) => {
                return [...new Set((messages || [])
                    .map((message) => String(message || '').trim())
                    .filter((message) => message !== '')
                    .map((message) => message.replace(/[，。；、]+$/u, '')))]
                    .join('，') + '。';
            });

            const validateName = () => {
                const value = String(nameInput?.value || '').trim();

                if (value === '') {
                    return '请填写栏目名称。';
                }

                if (value.length < 2) {
                    return '栏目名称不能少于2个字符。';
                }

                if (!channelNamePattern.test(value)) {
                    return '栏目名称只能使用中文、英文、数字、空格、下划线、中划线、圆括号或间隔点。';
                }

                return value.length <= 100 ? '' : '栏目名称不能超过100个字符。';
            };

            const validateSlug = () => {
                if (!slugInput) {
                    return '';
                }

                const value = String(slugInput.value || '').trim();

                if (value === '') {
                    return '请填写栏目别名。';
                }

                if (value.length < 3) {
                    return '栏目别名不能少于3个字符。';
                }

                if (value.length > 20) {
                    return '栏目别名不能超过20个字符。';
                }

                return /^[A-Za-z0-9_-]+$/.test(value) ? '' : '栏目别名只能由英文、数字、下划线和短横线组成。';
            };

            const validateType = () => {
                return getCurrentType() ? '' : '请选择栏目类型。';
            };

            const validateLinkUrl = () => {
                if (!linkUrlInput || getCurrentType() !== 'link') {
                    return '';
                }

                const value = String(linkUrlInput.value || '').trim();

                if (value === '') {
                    return '外链栏目必须填写外链地址。';
                }

                try {
                    const parsed = new URL(value);
                    return ['http:', 'https:'].includes(parsed.protocol) ? '' : '外链地址格式不正确，请输入完整的 http:// 或 https:// 地址。';
                } catch (error) {
                    return '外链地址格式不正确，请输入完整的 http:// 或 https:// 地址。';
                }
            };


            const regenerateSlug = async () => {
                const generatedSlug = await buildSlug(nameInput.value);

                if (generatedSlug === null) {
                    return;
                }

                slugInput.value = generatedSlug;
            };

            nameInput?.addEventListener('input', async () => {
                if (!slugManuallyEdited) {
                    await regenerateSlug();
                }
            });

            slugInput?.addEventListener('input', () => {
                slugManuallyEdited = slugInput.value.trim() !== '';
                slugInput.value = normalizeSlug(slugInput.value);
            });

            typeInputs.forEach((input) => {
                input.addEventListener('change', syncTypeView);
            });

            typeCards.forEach((card) => {
                card.addEventListener('click', () => {
                    const radio = card.querySelector('input[name="type"]');
                    if (!radio) {
                        return;
                    }

                    radio.checked = true;
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });

            nameInput?.setAttribute('maxlength', '100');
            nameInput?.addEventListener('blur', () => {
                const message = validateName();
                if (message === '') {
                    clearFieldError(nameInput);
                }
            });

            slugInput?.addEventListener('blur', () => {
                const message = validateSlug();
                if (message === '') {
                    clearFieldError(slugInput);
                }
            });

            linkUrlInput?.addEventListener('blur', () => {
                const message = validateLinkUrl();
                if (message === '') {
                    clearFieldError(linkUrlInput);
                }
            });

            if (form) {
                form.addEventListener('submit', (event) => {
                    const messages = [];
                    let firstInvalid = null;

                    clearFieldError(nameInput);
                    clearFieldError(slugInput);
                    clearFieldError(linkUrlInput);
                    typeCards.forEach((card) => card.classList.remove('is-error'));

                    const nameMessage = validateName();
                    if (nameMessage !== '') {
                        setFieldError(nameInput);
                        messages.push(nameMessage);
                        firstInvalid = firstInvalid || nameInput;
                    }

                    const slugMessage = validateSlug();
                    if (slugMessage !== '') {
                        setFieldError(slugInput);
                        messages.push(slugMessage);
                        firstInvalid = firstInvalid || slugInput;
                    }

                    const typeMessage = validateType();
                    if (typeMessage !== '') {
                        typeCards.forEach((card) => card.classList.add('is-error'));
                        messages.push(typeMessage);
                        firstInvalid = firstInvalid || typeInputs[0];
                    }

                    const linkUrlMessage = validateLinkUrl();
                    if (linkUrlMessage !== '') {
                        setFieldError(linkUrlInput);
                        messages.push(linkUrlMessage);
                        firstInvalid = firstInvalid || linkUrlInput;
                    }

                    if (messages.length > 0) {
                        event.preventDefault();
                        if (typeof window.showMessage === 'function') {
                            window.showMessage(window.formatAdminErrorMessages(messages), 'error');
                        }
                        firstInvalid?.focus();
                        firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            }

            syncTypeView();
        })();
    </script>
    @if ($errors->any())
        <script>
            (() => {
                const messages = @json($errors->all());

                if (Array.isArray(messages) && messages.length > 0 && typeof window.showMessage === 'function' && typeof window.formatAdminErrorMessages === 'function') {
                    window.showMessage(window.formatAdminErrorMessages(messages), 'error');
                }
            })();
        </script>
    @endif
    @unless ($isCreate)
        <script>
            (() => {
                const button = document.querySelector('.js-channel-delete');
                if (!button) {
                    return;
                }

                button.addEventListener('click', () => {
                    const form = document.getElementById(button.dataset.formId || '');
                    if (!form) {
                        return;
                    }

                    if (typeof window.showConfirmDialog === 'function') {
                        window.showConfirmDialog({
                            title: '确认删除这个栏目？',
                            text: '删除后如果该栏目仍有子栏目或内容占用，系统会阻止删除。请确认已经清理相关依赖后再继续。',
                            confirmText: '确认删除',
                            onConfirm: () => form.submit(),
                        });
                        return;
                    }

                    if (window.confirm('确认删除这个栏目？')) {
                        form.submit();
                    }
                });
            })();
        </script>
    @endunless
@endpush
