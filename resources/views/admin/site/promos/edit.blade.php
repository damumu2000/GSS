@extends('layouts.admin')

@section('title', ($isCreate ? '新建图宣位' : '编辑图宣位') . ' - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 图宣管理 / ' . ($isCreate ? '新建图宣位' : '编辑图宣位'))

@push('styles')
    @include('admin.site._custom_select_styles')
    @include('admin.site.promos._shared_styles')
    <link rel="stylesheet" href="{{ asset('css/site-promos-edit.css') }}">
@endpush

@section('content')
    <section class="stack">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">{{ $isCreate ? '新建图宣位' : '编辑图宣位' }}</h1>
                <div class="page-header-desc">先定义模板中的图宣调用位点，后续图宣内容、资源库选图和漂浮图参数会继续叠加到这里。</div>
            </div>
            <div class="promo-header-actions">
                @if (!$isCreate && $position->id)
                    <a class="button secondary neutral-action" href="{{ route('admin.promos.items.index', ['position' => $position->id] + ($promoIndexQuery ?? [])) }}">管理图宣内容</a>
                @endif
                <a class="button secondary neutral-action" href="{{ route('admin.promos.index', $promoIndexQuery ?? []) }}">返回列表</a>
            </div>
        </div>

        <div class="promo-layout">
            <div class="panel">
                <form method="POST" action="{{ $isCreate ? route('admin.promos.store', $promoIndexQuery ?? []) : route('admin.promos.update', ['position' => $position->id] + ($promoIndexQuery ?? [])) }}" class="stack">
                @csrf
                <div class="promo-form-grid">
                    <div>
                        <label for="name">图宣位名称</label>
                        <input id="name" class="field @error('name') is-error @enderror" type="text" name="name" value="{{ old('name', $position->name) }}" placeholder="例如：首页主视觉">
                    </div>
                    <div>
                        <label for="page_scope">页面范围</label>
                        <div class="site-select" data-site-select>
                            <select id="page_scope" class="field site-select-native" name="page_scope" @error('page_scope') aria-invalid="true" @enderror>
                                @foreach ($pageScopes as $scopeCode => $scopeLabel)
                                    <option value="{{ $scopeCode }}" @selected(old('page_scope', $position->page_scope) === $scopeCode)>{{ $scopeLabel }}</option>
                                @endforeach
                            </select>
                            <button class="site-select-trigger @error('page_scope') is-error @enderror" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ $pageScopes[old('page_scope', $position->page_scope)] ?? old('page_scope', $position->page_scope) }}</button>
                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                        </div>
                    </div>
                    <div>
                        <label for="display_mode">展示模式</label>
                        <div class="site-select" data-site-select>
                            <select id="display_mode" class="field site-select-native" name="display_mode" @error('display_mode') aria-invalid="true" @enderror>
                                @foreach ($displayModes as $modeCode => $modeLabel)
                                    <option value="{{ $modeCode }}" @selected(old('display_mode', $position->display_mode) === $modeCode)>{{ $modeLabel }}</option>
                                @endforeach
                            </select>
                            <button class="site-select-trigger @error('display_mode') is-error @enderror" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ $displayModes[old('display_mode', $position->display_mode)] ?? old('display_mode', $position->display_mode) }}</button>
                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                        </div>
                    </div>
                    <div>
                        <label for="channel_id">所属栏目</label>
                        <div class="site-select channel-parent-select" data-site-select>
                            <select id="channel_id" class="field site-select-native" name="channel_id" @error('channel_id') aria-invalid="true" @enderror>
                                <option value="" data-depth="0">站点默认</option>
                                @foreach ($channels as $channel)
                                    <option
                                        value="{{ $channel->id }}"
                                        data-depth="{{ (int) ($channel->tree_depth ?? 0) }}"
                                        data-has-children="{{ !empty($channel->tree_has_children) ? '1' : '0' }}"
                                        @selected((string) old('channel_id', $position->channel_id) === (string) $channel->id)
                                    >{{ $channel->name }}</option>
                                @endforeach
                            </select>
                            <button class="site-select-trigger @error('channel_id') is-error @enderror" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ collect($channels)->firstWhere('id', (int) old('channel_id', $position->channel_id))?->name ?? '站点默认' }}</button>
                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                        </div>
                    </div>
                    <div>
                        <label for="template_name">模板名称</label>
                        <input id="template_name" class="field @error('template_name') is-error @enderror" type="text" name="template_name" value="{{ old('template_name', $position->template_name) }}" placeholder="填入模板文件名即可，例如：home">
                        <div class="promo-mode-note">留空时使用站点默认位，填写后在对应模板页面生效。</div>
                    </div>
                    <div>
                        <label for="max_items">最大图宣数</label>
                        <input id="max_items" class="field @error('max_items') is-error @enderror" type="number" min="1" max="20" name="max_items" value="{{ old('max_items', $position->max_items ?: 1) }}">
                        <div class="promo-mode-note" data-promo-max-items-note>单图模式固定为 1，轮播和漂浮图可按需要调整。</div>
                    </div>
                    <div>
                        <label for="status">状态</label>
                        <div class="site-select" data-site-select>
                            <select id="status" class="field site-select-native" name="status" @error('status') aria-invalid="true" @enderror>
                                <option value="1" @selected((string) old('status', $position->status) === '1')>启用</option>
                                <option value="0" @selected((string) old('status', $position->status) === '0')>停用</option>
                            </select>
                            <button class="site-select-trigger @error('status') is-error @enderror" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ (string) old('status', $position->status) === '0' ? '停用' : '启用' }}</button>
                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                        </div>
                    </div>
                    <div class="field-span-2">
                        <label for="remark">备注</label>
                        <textarea id="remark" class="field textarea @error('remark') is-error @enderror" name="remark" placeholder="记录位点用途、推荐尺寸、适用页面说明等。">{{ old('remark', $position->remark) }}</textarea>
                    </div>
                </div>

                <div class="action-row">
                    <button class="button" type="submit">{{ $isCreate ? '创建图宣位' : '保存图宣位' }}</button>
                    <a class="button secondary" href="{{ route('admin.promos.index', $promoIndexQuery ?? []) }}">取消</a>
                </div>
                </form>
            </div>

            <aside class="promo-preview-card">
                <div>
                    <h3 class="promo-preview-title">位点预览</h3>
                    <div class="promo-preview-subtitle">根据页面范围和展示模式，实时给出位点建议和视觉示意。</div>
                </div>

                <div class="promo-preview-shell" data-promo-preview-shell data-mode="{{ old('display_mode', $position->display_mode) }}">
                    <span class="promo-preview-chip" data-promo-preview-chip>{{ $displayModes[old('display_mode', $position->display_mode)] ?? old('display_mode', $position->display_mode) }}</span>
                    <div class="promo-preview-single">
                        <div class="promo-preview-single-card">
                            <div class="promo-preview-single-copy">首页主视觉 / 栏目头图</div>
                        </div>
                    </div>
                    <div class="promo-preview-carousel">
                        <div class="promo-preview-carousel-track">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                    <div class="promo-preview-floating">
                        <div class="promo-preview-floating-stage">
                            <span class="promo-preview-floating-bubble"></span>
                        </div>
                    </div>
                </div>

                <div class="promo-preview-facts">
                    <div class="promo-preview-fact">
                        <div class="promo-preview-fact-label">适用场景</div>
                        <div class="promo-preview-fact-value" data-promo-preview-scene>首页首屏、栏目头图、活动浮窗等模板图宣区域。</div>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <div
        id="site-promo-edit-config"
        hidden
        data-mode-labels='@json($displayModes)'
        data-error-messages='@json($errors->all())'
    ></div>
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script src="{{ asset('js/site-promos-edit.js') }}"></script>
@endpush
