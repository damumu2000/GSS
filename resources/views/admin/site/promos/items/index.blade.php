@extends('layouts.admin')

@section('title', '图宣内容管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 图宣管理 / 图宣内容')

@push('styles')
    @include('admin.site._custom_select_styles')
    @include('admin.site.promos._shared_styles')
    @include('admin.site.attachments._attachment_library_styles')
    <link rel="stylesheet" href="{{ asset('css/site-promo-items-index.css') }}">
@endpush

@section('content')
    <section class="stack">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">{{ $position->name }}</h1>
                <div class="page-header-desc">模式：{{ $displayModes[$position->display_mode] ?? $position->display_mode }} · 最多 {{ (int) $position->max_items }} 项。当前页支持换图、属性编辑、资源库上传与拖拽排序。</div>
            </div>
            <div class="promo-header-actions">
                <a class="button secondary neutral-action" href="{{ route('admin.promos.index', $promoIndexQuery ?? []) }}">返回图宣位</a>
                <button class="button" type="button" data-open-create-drawer>新增图宣内容</button>
            </div>
        </div>

        <div class="panel promo-item-panel-shell">
            <div class="promo-item-toolbar">
                <div class="promo-item-toolbar-copy">点击图片可直接更换图宣图，点击编辑可在当前页完成属性修改并保存。</div>
                <span class="badge" data-item-count-badge>{{ $items->count() }} / {{ (int) $position->max_items }}</span>
            </div>

            <div class="promo-item-empty" data-promo-item-empty @if(!$items->isEmpty()) hidden @endif>
                当前位点还没有图宣内容，点击右上角“新增图宣内容”即可直接在本页完成选图和属性配置。
            </div>

            <div
                class="promo-item-grid"
                data-promo-item-grid
                data-reorder-url="{{ route('admin.promos.items.reorder', $position->id) }}"
                data-store-url="{{ route('admin.promos.items.quick-store', $position->id) }}"
                data-update-url-template="{{ route('admin.promos.items.quick-update', [$position->id, '__ITEM__']) }}"
                data-replace-image-url-template="{{ route('admin.promos.items.replace-image', [$position->id, '__ITEM__']) }}"
                data-toggle-url-template="{{ route('admin.promos.items.toggle', ['position' => $position->id, 'item' => '__ITEM__'] + ($promoIndexQuery ?? [])) }}"
                data-duplicate-url-template="{{ route('admin.promos.items.duplicate', ['position' => $position->id, 'item' => '__ITEM__'] + ($promoIndexQuery ?? [])) }}"
                data-destroy-url-template="{{ route('admin.promos.items.destroy', ['position' => $position->id, 'item' => '__ITEM__'] + ($promoIndexQuery ?? [])) }}"
                data-max-items="{{ (int) $position->max_items }}"
                @if($items->isEmpty()) hidden @endif
            >
                @foreach ($items as $item)
                    <article class="promo-item-card" id="promo-item-{{ $item->id }}" data-promo-item-row data-promo-item-id="{{ $item->id }}">
                        <div class="promo-item-card-head">
                            <span class="promo-item-status-badge{{ $item->effective_status_tone === 'muted' ? ' is-muted' : ($item->effective_status_tone === 'warning' ? ' is-warning' : ($item->effective_status_tone === 'danger' ? ' is-danger' : '')) }}">{{ $item->effective_status_label }}</span>
                            <span class="promo-item-drag-handle" aria-label="拖拽排序" data-tooltip="拖拽排序">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h8v2H8zM8 11h8v2H8zM8 16h8v2H8z"/></svg>
                            </span>
                        </div>
                        <button class="promo-item-preview-button" type="button" data-replace-item-image="{{ $item->id }}">
                            <div class="promo-item-preview">
                                <img src="{{ $item->attachment_url }}" alt="{{ $item->title ?: $item->attachment_name }}">
                            </div>
                        </button>
                        <div>
                            <div class="promo-item-title">{{ $item->title ?: $item->attachment_name }}</div>
                            @if (!empty($item->subtitle))
                                <div class="promo-item-subtitle">{{ $item->subtitle }}</div>
                            @endif
                        </div>
                        <div class="promo-item-meta">
                            <div>文件：{{ $item->attachment_name }}</div>
                            <div>时间：{{ $item->start_at ?: '立即生效' }} ~ {{ $item->end_at ?: '长期有效' }}</div>
                            @if (!empty($item->link_url))
                                <div>链接：{{ $item->link_url }}</div>
                            @endif
                        </div>
                        <div class="promo-item-actions">
                            <form method="POST" action="{{ route('admin.promos.items.duplicate', ['position' => $position->id, 'item' => $item->id] + ($promoIndexQuery ?? [])) }}">
                                @csrf
                                <button class="button secondary neutral-action" type="submit">复制</button>
                            </form>
                            <form method="POST" action="{{ route('admin.promos.items.toggle', ['position' => $position->id, 'item' => $item->id] + ($promoIndexQuery ?? [])) }}">
                                @csrf
                                <button class="button secondary neutral-action" type="submit">{{ (int) $item->status === 1 ? '停用' : '启用' }}</button>
                            </form>
                            <button class="button secondary neutral-action" type="button" data-open-item-editor="{{ $item->id }}">编辑</button>
                            <form method="POST" action="{{ route('admin.promos.items.destroy', ['position' => $position->id, 'item' => $item->id] + ($promoIndexQuery ?? [])) }}" data-promo-item-delete-form data-promo-item-delete-name="{{ $item->title ?: $item->attachment_name }}">
                                @csrf
                                <button class="button secondary neutral-action" type="submit">删除</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        <div class="promo-item-editor" data-promo-item-editor hidden>
            <div class="promo-item-editor-backdrop" data-close-item-editor></div>
            <div class="promo-item-editor-panel" role="dialog" aria-modal="true" aria-labelledby="promo-item-editor-title">
                <div class="promo-item-editor-header">
                    <div class="promo-item-editor-headline">
                        <div>
                            <h2 class="promo-item-editor-title" id="promo-item-editor-title">编辑图宣内容</h2>
                            <div class="promo-item-editor-desc">在当前页完成选图、属性修改与漂浮参数配置，保存后会直接同步到当前卡片。</div>
                        </div>
                        <button class="promo-item-editor-close" type="button" data-close-item-editor aria-label="关闭">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"></path></svg>
                        </button>
                    </div>
                </div>
                <form class="promo-item-editor-form" data-promo-item-editor-form>
                    <input type="hidden" name="attachment_id" id="drawer_attachment_id" value="">
                    <div class="promo-item-editor-body">
                        <div class="promo-item-editor-preview">
                            <div class="promo-item-editor-preview-trigger" data-open-drawer-image-library>
                                <div class="promo-item-editor-preview-box" data-drawer-image-preview>
                                    <span data-drawer-image-placeholder>点击选择图宣图片</span>
                                </div>
                                <div class="promo-item-editor-note" data-drawer-image-note>未选择图片，点击上方区域即可从资源库选图。</div>
                            </div>
                        </div>

                        <div class="promo-item-editor-errors" data-drawer-errors hidden></div>

                        <div class="promo-item-form-fields">
                            <div class="field-span-2">
                                <label for="drawer_title">标题</label>
                                <input id="drawer_title" class="field" type="text" name="title" placeholder="可选，用于图宣文案">
                            </div>
                            <div class="field-span-2">
                                <label for="drawer_subtitle">副标题</label>
                                <input id="drawer_subtitle" class="field" type="text" name="subtitle" placeholder="可选，适用于轮播文案或浮层提示">
                            </div>
                            <div class="field-span-2">
                                <label for="drawer_link_url">跳转地址</label>
                                <input id="drawer_link_url" class="field" type="text" name="link_url" placeholder="/article/123 或 https://example.com">
                            </div>
                            <div>
                                <label for="drawer_link_target">跳转方式</label>
                                <div class="site-select" data-site-select>
                                    <select id="drawer_link_target" class="field site-select-native" name="link_target">
                                        <option value="_self">当前窗口打开</option>
                                        <option value="_blank">新窗口打开</option>
                                    </select>
                                    <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">当前窗口打开</button>
                                    <div class="site-select-panel" data-select-panel role="listbox"></div>
                                </div>
                            </div>
                            <div>
                                <label for="drawer_status">状态</label>
                                <div class="site-select" data-site-select>
                                    <select id="drawer_status" class="field site-select-native" name="status">
                                        <option value="1">启用</option>
                                        <option value="0">停用</option>
                                    </select>
                                    <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">启用</button>
                                    <div class="site-select-panel" data-select-panel role="listbox"></div>
                                </div>
                            </div>
                            <div>
                                <label for="drawer_start_at">开始时间</label>
                                <input id="drawer_start_at" class="field" type="datetime-local" name="start_at">
                            </div>
                            <div>
                                <label for="drawer_end_at">结束时间</label>
                                <input id="drawer_end_at" class="field" type="datetime-local" name="end_at">
                            </div>
                        </div>

                        @if ($position->display_mode === 'floating')
                            <div class="promo-item-floating-fields">
                                <div class="promo-item-floating-head">
                                    <h3 class="promo-item-floating-title">漂浮图参数</h3>
                                    <div class="promo-item-floating-copy">这些配置会写入图宣数据，前台模板读取后可直接控制漂浮位置、尺寸、动画和关闭记忆行为。</div>
                                </div>
                                <div class="promo-item-form-fields">
                                    <div>
                                        <label for="drawer_floating_position">漂浮位置</label>
                                        <div class="site-select" data-site-select>
                                            <select id="drawer_floating_position" class="field site-select-native" name="floating_position">
                                                @foreach (['right-bottom' => '右下', 'right-center' => '右中', 'left-bottom' => '左下', 'left-center' => '左中', 'right-top' => '右上', 'left-top' => '左上'] as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">右下</button>
                                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                                        </div>
                                        <div class="promo-item-floating-hint">控制漂浮图贴边的位置，例如右下角、左中等常见挂件区域。</div>
                                    </div>
                                    <div>
                                        <label for="drawer_floating_animation">动画</label>
                                        <div class="site-select" data-site-select>
                                            <select id="drawer_floating_animation" class="field site-select-native" name="floating_animation">
                                                @foreach (['float' => '轻浮动', 'pulse' => '呼吸', 'sway' => '摇摆', 'none' => '无动画'] as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">轻浮动</button>
                                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                                        </div>
                                        <div class="promo-item-floating-hint">前台模板可按这里的动画值切换浮动、呼吸或静止样式。</div>
                                    </div>
                                    <div>
                                        <label for="drawer_floating_offset_x">横向偏移</label>
                                        <input id="drawer_floating_offset_x" class="field" type="number" name="floating_offset_x">
                                        <div class="promo-item-floating-hint">距离左右边缘的像素偏移，常用 16 到 32。</div>
                                    </div>
                                    <div>
                                        <label for="drawer_floating_offset_y">纵向偏移</label>
                                        <input id="drawer_floating_offset_y" class="field" type="number" name="floating_offset_y">
                                        <div class="promo-item-floating-hint">距离顶部或底部的像素偏移，用来微调悬浮高度。</div>
                                    </div>
                                    <div>
                                        <label for="drawer_floating_width">宽度</label>
                                        <input id="drawer_floating_width" class="field" type="number" name="floating_width">
                                        <div class="promo-item-floating-hint">控制漂浮图整体宽度，前台会按比例适配图片高度。</div>
                                    </div>
                                    <div>
                                        <label for="drawer_floating_height">高度</label>
                                        <input id="drawer_floating_height" class="field" type="number" name="floating_height">
                                        <div class="promo-item-floating-hint">可留空让图片按原比例展示，只有定高模板才建议填写。</div>
                                    </div>
                                    <div>
                                        <label for="drawer_floating_z_index">层级</label>
                                        <input id="drawer_floating_z_index" class="field" type="number" name="floating_z_index">
                                        <div class="promo-item-floating-hint">数字越大越靠上，避免被页面导航或弹层遮住。</div>
                                    </div>
                                    <div>
                                        <label for="drawer_floating_show_on">显示端</label>
                                        <div class="site-select" data-site-select>
                                            <select id="drawer_floating_show_on" class="field site-select-native" name="floating_show_on">
                                                @foreach (['all' => '全端', 'pc' => '仅桌面', 'mobile' => '仅移动端'] as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">全端</button>
                                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                                        </div>
                                        <div class="promo-item-floating-hint">可限制仅在桌面或移动端展示，避免不同端样式冲突。</div>
                                    </div>
                                    <div>
                                        <label for="drawer_floating_closable">允许关闭</label>
                                        <div class="site-select" data-site-select>
                                            <select id="drawer_floating_closable" class="field site-select-native" name="floating_closable">
                                                <option value="1">是</option>
                                                <option value="0">否</option>
                                            </select>
                                            <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">是</button>
                                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                                        </div>
                                        <div class="promo-item-floating-hint">开启后，前台可显示关闭按钮，让访客手动收起漂浮图。</div>
                                    </div>
                                    <div>
                                        <label for="drawer_floating_remember_close">记忆关闭状态</label>
                                        <div class="site-select" data-site-select>
                                            <select id="drawer_floating_remember_close" class="field site-select-native" name="floating_remember_close">
                                                <option value="1">是</option>
                                                <option value="0">否</option>
                                            </select>
                                            <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">是</button>
                                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                                        </div>
                                        <div class="promo-item-floating-hint">开启后，访客关闭一次，指定时长内再次访问可保持隐藏。</div>
                                    </div>
                                    <div>
                                        <label for="drawer_floating_close_expire_hours">关闭记忆时长（小时）</label>
                                        <input id="drawer_floating_close_expire_hours" class="field" type="number" min="1" max="720" name="floating_close_expire_hours">
                                        <div class="promo-item-floating-hint">控制关闭状态的失效时间，到期后前台可以重新展示漂浮图。</div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="promo-item-editor-footer">
                        <button class="button secondary" type="button" data-close-item-editor>取消</button>
                        <button class="button" type="submit" data-drawer-submit>保存图宣内容</button>
                    </div>
                </form>
            </div>
        </div>

        <div
            id="site-promo-items-config"
            hidden
            data-item-sheets='@json($itemSheets)'
            data-display-mode="{{ $position->display_mode }}"
            data-promo-item-error="@if($errors->has('promo_item')){{ $errors->first('promo_item') }}@endif"
        ></div>

        @include('admin.site.attachments._attachment_library_modal')
    </section>
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script src="/vendor/sortablejs/Sortable.min.js?v=1.15.3"></script>
    @include('admin.site.attachments._attachment_library_scripts')
    <script src="{{ asset('js/site-promos-items-index.js') }}"></script>
@endpush
