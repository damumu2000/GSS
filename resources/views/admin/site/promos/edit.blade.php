@extends('layouts.admin')

@section('title', ($isCreate ? '新建图宣位' : '编辑图宣位') . ' - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 图宣管理 / ' . ($isCreate ? '新建图宣位' : '编辑图宣位'))

@push('styles')
    @include('admin.site._custom_select_styles')
    @include('admin.site.promos._shared_styles')
    <style>
        .promo-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.8fr);
            gap: 18px;
            align-items: start;
        }

        .promo-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .promo-form-grid .field-span-2 {
            grid-column: 1 / -1;
        }

        .promo-mode-note {
            margin-top: 8px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .promo-preview-card {
            padding: 18px;
            border: 1px solid #e8edf3;
            border-radius: 22px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.04);
            display: grid;
            gap: 16px;
            position: sticky;
            top: 88px;
        }

        .promo-preview-title {
            margin: 0;
            color: #1f2937;
            font-size: 16px;
            font-weight: 700;
        }

        .promo-preview-subtitle {
            color: #8b94a7;
            font-size: 13px;
            line-height: 1.7;
        }

        .promo-preview-shell {
            min-height: 220px;
            border-radius: 20px;
            border: 1px solid #e8edf3;
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.08), transparent 24%),
                linear-gradient(180deg, #f8fbff 0%, #f4f8fc 100%);
            overflow: hidden;
            position: relative;
            padding: 18px;
        }

        .promo-preview-chip {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 0 10px;
            border-radius: 999px;
            background: rgba(0, 80, 179, 0.08);
            color: #0050b3;
            font-size: 12px;
            font-weight: 700;
        }

        .promo-preview-single,
        .promo-preview-carousel,
        .promo-preview-floating {
            display: none;
        }

        .promo-preview-shell[data-mode="single"] .promo-preview-single,
        .promo-preview-shell[data-mode="carousel"] .promo-preview-carousel,
        .promo-preview-shell[data-mode="floating"] .promo-preview-floating {
            display: block;
        }

        .promo-preview-single-card {
            margin-top: 18px;
            height: 138px;
            border-radius: 18px;
            background: linear-gradient(135deg, #dceaff 0%, #b6d4ff 100%);
            position: relative;
            overflow: hidden;
        }

        .promo-preview-single-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.1) 0%, rgba(15, 23, 42, 0.08) 100%);
        }

        .promo-preview-single-copy {
            position: absolute;
            left: 18px;
            bottom: 18px;
            right: 18px;
            color: #fff;
            font-size: 20px;
            font-weight: 700;
            z-index: 1;
        }

        .promo-preview-carousel-track {
            margin-top: 18px;
            display: grid;
            grid-template-columns: 1.35fr 0.85fr 0.85fr;
            gap: 10px;
            height: 138px;
        }

        .promo-preview-carousel-track span {
            display: block;
            border-radius: 16px;
            background: linear-gradient(135deg, #dceaff 0%, #b6d4ff 100%);
        }

        .promo-preview-carousel-track span:nth-child(2) {
            background: linear-gradient(135deg, #d9f4ea 0%, #b8e7d4 100%);
        }

        .promo-preview-carousel-track span:nth-child(3) {
            background: linear-gradient(135deg, #fff0cb 0%, #ffd98b 100%);
        }

        .promo-preview-floating-stage {
            margin-top: 18px;
            height: 150px;
            border-radius: 18px;
            background: linear-gradient(180deg, #f7fafc 0%, #edf3f8 100%);
            position: relative;
            overflow: hidden;
        }

        .promo-preview-floating-bubble {
            position: absolute;
            right: 16px;
            bottom: 16px;
            width: 88px;
            height: 88px;
            border-radius: 22px;
            background: linear-gradient(135deg, #ffd7d7 0%, #ffbcbc 100%);
            box-shadow: 0 16px 24px rgba(248, 113, 113, 0.18);
        }

        .promo-preview-facts {
            display: grid;
            gap: 10px;
        }

        .promo-preview-fact {
            display: grid;
            gap: 4px;
            padding: 12px 14px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #edf2f7;
        }

        .promo-preview-fact-label {
            color: #8b94a7;
            font-size: 12px;
            font-weight: 600;
        }

        .promo-preview-fact-value {
            color: #334155;
            font-size: 13px;
            line-height: 1.6;
            word-break: break-all;
        }

        @media (max-width: 960px) {
            .promo-layout,
            .promo-form-grid {
                grid-template-columns: 1fr;
            }

            .promo-preview-card {
                position: static;
            }
        }
    </style>
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
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script>
        (() => {
            const modeInput = document.getElementById('display_mode');
            const scopeInput = document.getElementById('page_scope');
            const maxItemsInput = document.getElementById('max_items');
            const shell = document.querySelector('[data-promo-preview-shell]');
            const chip = document.querySelector('[data-promo-preview-chip]');
            const sceneText = document.querySelector('[data-promo-preview-scene]');
            const maxItemsNote = document.querySelector('[data-promo-max-items-note]');

            if (!modeInput || !scopeInput || !shell || !chip || !sceneText) {
                return;
            }

            const modeLabels = @json($displayModes);
            const maxItemLimits = {
                single: 1,
                floating: 2,
                carousel: 10,
            };
            const scenes = {
                single: '适合首页主视觉、栏目头图、详情页头图等单图位点。',
                carousel: '适合首页轮播、专题推荐、多图活动位等连续展示场景。',
                floating: '适合右下角活动入口、通知提示、节日挂件等漂浮图。'
            };

            const syncPreview = () => {
                const mode = modeInput.value || 'single';
                const scope = scopeInput.value || 'global';
                const isSingle = mode === 'single';
                const currentLimit = maxItemLimits[mode] || 20;

                if (maxItemsInput) {
                    maxItemsInput.setAttribute('max', String(currentLimit));

                    if (isSingle) {
                        maxItemsInput.value = '1';
                        maxItemsInput.setAttribute('readonly', 'readonly');
                        maxItemsInput.setAttribute('aria-readonly', 'true');
                    } else {
                        maxItemsInput.removeAttribute('readonly');
                        maxItemsInput.removeAttribute('aria-readonly');

                        if ((Number(maxItemsInput.value || '0') || 0) < 1) {
                            maxItemsInput.value = '1';
                        }

                        if ((Number(maxItemsInput.value || '0') || 0) > currentLimit) {
                            maxItemsInput.value = String(currentLimit);
                        }
                    }
                }

                if (maxItemsNote) {
                    maxItemsNote.textContent = mode === 'single'
                        ? '单图模式固定为 1，无需单独设置。'
                        : mode === 'floating'
                            ? '漂浮图模式最多支持 2 项，避免页面遮挡。'
                            : '轮播图模式最多支持 10 项，按实际需要调整。';
                }
                shell.dataset.mode = mode;
                chip.textContent = modeLabels[mode] || mode;
                sceneText.textContent = scenes[mode] || '适合模板图宣展示。';
            };

            [modeInput, scopeInput, maxItemsInput].forEach((input) => {
                input?.addEventListener('input', syncPreview);
                input?.addEventListener('change', syncPreview);
            });

            syncPreview();
        })();
    </script>
    @if ($errors->any())
        <script>
            (() => {
                const messages = @json($errors->all());

                const formattedMessage = [...new Set((messages || [])
                    .map((message) => String(message || '').trim())
                    .filter((message) => message !== '')
                    .map((message) => message.replace(/[，。；、]+$/u, '')))]
                    .join('，') + '。';

                if (formattedMessage !== '。' && typeof window.showMessage === 'function') {
                    window.showMessage(formattedMessage, 'error');
                }
            })();
        </script>
    @endif
@endpush
