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
                <div class="page-header-desc">定义模板调用用的图宣位，后续在内容管理里上传图片和设置链接。</div>
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
                        <label for="code">图宣位编码</label>
                        <input id="code" class="field @error('code') is-error @enderror" type="text" name="code" value="{{ old('code', $position->code) }}" placeholder="例如：home_banner">
                        <div class="promo-mode-note">模板调用使用这个编码。只支持小写字母、数字、中横线和下划线。</div>
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
                        <textarea id="remark" class="field textarea @error('remark') is-error @enderror" name="remark" placeholder="记录用途、推荐尺寸等内部说明。">{{ old('remark', $position->remark) }}</textarea>
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
                    <div class="promo-preview-subtitle">根据展示模式，给出位点建议和视觉示意。</div>
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
