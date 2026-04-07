@extends('layouts.admin')

@section('title', '图宣内容管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 图宣管理 / 图宣内容')

@push('styles')
    @include('admin.site._custom_select_styles')
    @include('admin.site.promos._shared_styles')
    <style>
        @include('admin.site.attachments._attachment_library_styles')

        .attachment-library-modal {
            z-index: 2800;
        }

        .attachment-usage-modal {
            z-index: 2850;
        }

        .page-header {
            margin-bottom: 12px;
        }

        .promo-item-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .promo-item-card {
            position: relative;
            padding: 18px;
            border-radius: 22px;
            border: 1px solid #e8edf3;
            background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
            display: grid;
            gap: 14px;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }

        .promo-item-card:hover {
            border-color: rgba(0, 71, 171, 0.14);
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.07);
            transform: translateY(-1px);
        }

        .promo-item-card.is-saving {
            background: color-mix(in srgb, var(--primary, #0047AB) 5%, #ffffff);
        }

        .promo-item-card.is-dragging {
            opacity: 0.94;
        }

        .promo-item-card.is-ghost {
            background: color-mix(in srgb, var(--primary, #0047AB) 10%, #ffffff);
        }

        .promo-item-card.is-chosen {
            background: color-mix(in srgb, var(--primary, #0047AB) 12%, #ffffff);
        }

        .promo-item-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .promo-item-status-badge {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 12px;
            border-radius: 999px;
            background: rgba(22, 163, 74, 0.10);
            color: #15803d;
            font-size: 12px;
            font-weight: 700;
        }

        .promo-item-status-badge.is-muted {
            background: #f5f7fa;
            color: #8c8c8c;
        }

        .promo-item-status-badge.is-warning {
            background: rgba(245, 158, 11, 0.12);
            color: #b45309;
        }

        .promo-item-status-badge.is-danger {
            background: rgba(239, 68, 68, 0.10);
            color: #dc2626;
        }

        .promo-item-drag-handle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 10px;
            color: #98a2b3;
            cursor: grab;
            transition: background 0.18s ease, color 0.18s ease;
        }

        .promo-item-drag-handle:hover {
            background: rgba(0, 80, 179, 0.08);
            color: #0050b3;
        }

        .promo-item-drag-handle svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }

        .promo-item-preview-button {
            position: relative;
            padding: 0;
            border: 0;
            border-radius: 18px;
            overflow: hidden;
            background: transparent;
            cursor: pointer;
            text-align: left;
        }

        .promo-item-preview {
            position: relative;
            aspect-ratio: 4 / 3;
            border-radius: 18px;
            overflow: hidden;
            background:
                radial-gradient(circle at top right, rgba(0, 71, 171, 0.08), transparent 28%),
                linear-gradient(180deg, #f8fbff 0%, #f2f6fb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8b94a7;
            font-size: 13px;
            line-height: 1.7;
        }

        .promo-item-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .promo-item-preview-button::after {
            content: '点击更换图片';
            position: absolute;
            right: 12px;
            bottom: 12px;
            min-height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.76);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            line-height: 28px;
            opacity: 0;
            transform: translateY(6px);
            transition: opacity 0.18s ease, transform 0.18s ease;
            pointer-events: none;
        }

        .promo-item-preview-button:hover::after {
            opacity: 1;
            transform: translateY(0);
        }

        .promo-item-title {
            color: #1f2937;
            font-size: 16px;
            font-weight: 700;
            line-height: 1.5;
            word-break: break-word;
        }

        .promo-item-subtitle {
            color: #8b94a7;
            font-size: 13px;
            line-height: 1.7;
            margin-top: 4px;
        }

        .promo-item-meta {
            display: grid;
            gap: 6px;
            color: #667085;
            font-size: 12px;
            line-height: 1.7;
        }

        .promo-item-actions {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 6px;
            align-items: start;
        }

        .promo-item-actions form {
            margin: 0;
            display: block;
            height: 32px;
        }

        .promo-item-actions .button,
        .promo-item-actions .button.secondary {
            height: 32px;
            min-height: 32px;
            padding: 0 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            font-size: 12px;
            border-radius: 10px;
            width: 100%;
            min-width: 0;
        }

        .promo-item-panel-shell {
            display: grid;
            gap: 16px;
        }

        .promo-item-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .promo-item-toolbar-copy {
            color: #8b94a7;
            font-size: 13px;
            line-height: 1.7;
        }

        .promo-item-empty {
            padding: 36px 24px;
            border-radius: 20px;
            border: 1px dashed #d9e1eb;
            background: #fbfdff;
            color: #8b94a7;
            text-align: center;
            font-size: 14px;
            line-height: 1.8;
        }

        .promo-item-empty[hidden] {
            display: none;
        }

        .promo-item-grid[hidden] {
            display: none;
        }

        .promo-item-editor[hidden] {
            display: none;
        }

        .promo-item-editor {
            position: fixed;
            inset: 0;
            z-index: 2600;
        }

        .promo-item-editor-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.42);
            backdrop-filter: blur(4px);
        }

        .promo-item-editor-panel {
            position: absolute;
            top: 0;
            right: 0;
            width: min(520px, 100vw);
            height: 100%;
            min-height: 0;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
            border-left: 1px solid #e8edf3;
            box-shadow: -24px 0 48px rgba(15, 23, 42, 0.12);
        }

        .promo-item-editor-form {
            min-height: 0;
            height: 100%;
            display: grid;
            grid-template-rows: minmax(0, 1fr) auto;
            overflow: hidden;
        }

        .promo-item-editor-header,
        .promo-item-editor-footer {
            padding: 20px 22px;
            border-bottom: 1px solid #eef2f7;
            background: rgba(255, 255, 255, 0.9);
        }

        .promo-item-editor-footer {
            border-top: 1px solid #eef2f7;
            border-bottom: 0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .promo-item-editor-body {
            overflow-y: auto;
            min-height: 0;
            padding: 22px;
            display: grid;
            gap: 18px;
            scrollbar-gutter: stable;
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.76) transparent;
        }

        .promo-item-editor-body::-webkit-scrollbar {
            width: 8px;
        }

        .promo-item-editor-body::-webkit-scrollbar-track {
            background: transparent;
        }

        .promo-item-editor-body::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.76);
            border: 2px solid transparent;
            background-clip: padding-box;
        }

        .promo-item-editor-headline {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .promo-item-editor-title {
            margin: 0;
            color: #1f2937;
            font-size: 18px;
            line-height: 1.5;
            font-weight: 700;
        }

        .promo-item-editor-desc {
            margin-top: 6px;
            color: #8b94a7;
            font-size: 13px;
            line-height: 1.7;
        }

        .promo-item-editor-close {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            border: 1px solid #e7ebf1;
            background: #fff;
            color: #667085;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .promo-item-editor-close svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 1.9;
            fill: none;
        }

        .promo-item-editor-preview {
            display: grid;
            gap: 12px;
        }

        .promo-item-editor-preview-trigger {
            display: grid;
            gap: 12px;
            cursor: pointer;
        }

        .promo-item-editor-preview-box {
            aspect-ratio: 4 / 3;
            border-radius: 22px;
            overflow: hidden;
            border: 1px solid #e6edf5;
            background:
                radial-gradient(circle at top right, rgba(0, 71, 171, 0.08), transparent 28%),
                linear-gradient(180deg, #f8fbff 0%, #f2f6fb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8b94a7;
            font-size: 14px;
            line-height: 1.7;
            text-align: center;
        }

        .promo-item-editor-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .promo-item-editor-note,
        .promo-item-editor-errors {
            color: #8b94a7;
            font-size: 13px;
            line-height: 1.7;
        }

        .promo-item-editor-errors {
            color: #d14343;
        }

        .promo-item-editor-errors[hidden] {
            display: none;
        }

        .promo-item-form-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .promo-item-form-fields .field-span-2 {
            grid-column: 1 / -1;
        }

        .promo-item-floating-fields[hidden] {
            display: none;
        }

        .promo-item-floating-fields {
            display: grid;
            gap: 14px;
            padding: 18px;
            border-radius: 22px;
            border: 1px solid rgba(0, 71, 171, 0.08);
            background:
                radial-gradient(circle at top right, rgba(0, 71, 171, 0.08), transparent 32%),
                linear-gradient(180deg, #f8fbff 0%, #f4f8ff 100%);
        }

        .promo-item-floating-head {
            display: grid;
            gap: 6px;
        }

        .promo-item-floating-title {
            margin: 0;
            color: #1f2937;
            font-size: 15px;
            line-height: 1.5;
            font-weight: 700;
        }

        .promo-item-floating-copy {
            color: #667085;
            font-size: 13px;
            line-height: 1.7;
        }

        .promo-item-floating-fields .promo-item-form-fields {
            padding: 16px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.5);
        }

        .promo-item-floating-hint {
            margin-top: 6px;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.6;
        }

        @media (max-width: 1080px) {
            .promo-item-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 860px) {
            .promo-item-grid,
            .promo-item-form-fields {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .promo-item-grid {
                grid-template-columns: 1fr;
            }

            .promo-item-editor-panel {
                width: 100vw;
            }
        }
    </style>
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

        @include('admin.site.attachments._attachment_library_modal')
    </section>
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script src="/vendor/sortablejs/Sortable.min.js?v=1.15.3"></script>
    <script>
        let cmsAttachments = [];
        const attachmentLibraryWorkspaceAccess = @json($attachmentLibraryWorkspaceAccess);
        const attachmentDeleteUrlTemplate = @json(route('admin.attachments.destroy', ['attachment' => '__ATTACHMENT__']));
        const attachmentUsageUrlTemplate = @json(route('admin.attachments.usages', ['attachment' => '__ATTACHMENT__']));

        @include('admin.site.attachments._attachment_library_script')

        (() => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const grid = document.querySelector('[data-promo-item-grid]');
            const emptyState = document.querySelector('[data-promo-item-empty]');
            const badge = document.querySelector('[data-item-count-badge]');
            const editor = document.querySelector('[data-promo-item-editor]');
            const editorForm = document.querySelector('[data-promo-item-editor-form]');
            const editorTitle = document.getElementById('promo-item-editor-title');
            const drawerErrors = document.querySelector('[data-drawer-errors]');
            const preview = document.querySelector('[data-drawer-image-preview]');
            const previewNote = document.querySelector('[data-drawer-image-note]');
            const drawerSubmit = document.querySelector('[data-drawer-submit]');
            const attachmentInput = document.getElementById('drawer_attachment_id');

            if (!grid || !editor || !editorForm || !attachmentInput || !preview) {
                return;
            }

            const rawItems = @json($itemSheets);
            const items = new Map(Object.entries(rawItems).map(([id, item]) => [Number(id), item]));
            const maxItems = Number(grid.dataset.maxItems || 0);
            const displayMode = @json($position->display_mode);
            let editingItemId = null;

            const storeUrl = grid.dataset.storeUrl || '';
            const updateUrlTemplate = grid.dataset.updateUrlTemplate || '';
            const replaceImageUrlTemplate = grid.dataset.replaceImageUrlTemplate || '';
            const duplicateUrlTemplate = grid.dataset.duplicateUrlTemplate || '';
            const toggleUrlTemplate = grid.dataset.toggleUrlTemplate || '';
            const destroyUrlTemplate = grid.dataset.destroyUrlTemplate || '';

            const defaults = {
                id: null,
                attachment_id: 0,
                attachment_name: '',
                attachment_url: '',
                title: '',
                subtitle: '',
                link_url: '',
                link_target: '_self',
                status: 1,
                start_at: '',
                end_at: '',
                display_payload: {
                    position: 'right-bottom',
                    animation: 'float',
                    offset_x: 24,
                    offset_y: 24,
                    width: 180,
                    height: '',
                    z_index: 120,
                    show_on: 'all',
                    closable: true,
                    remember_close: true,
                    close_expire_hours: 24,
                },
            };

            const upsertAttachmentCache = (attachment) => {
                const resolvedId = Number(attachment?.id || attachment?.attachment_id || 0);

                if (resolvedId < 1) {
                    return null;
                }

                const normalized = {
                    id: resolvedId,
                    name: attachment?.name || attachment?.attachment_name || '',
                    url: attachment?.url || attachment?.attachment_url || '',
                    extension: String(attachment?.extension || '').toLowerCase(),
                };
                const existingIndex = cmsAttachments.findIndex((item) => Number(item.id) === resolvedId);

                if (existingIndex >= 0) {
                    cmsAttachments[existingIndex] = { ...cmsAttachments[existingIndex], ...normalized };
                } else {
                    cmsAttachments.push(normalized);
                }

                return cmsAttachments.find((item) => Number(item.id) === resolvedId) || null;
            };

            const cloneItem = (item) => JSON.parse(JSON.stringify(item));

            const toDateTimeLocal = (value) => {
                if (!value) {
                    return '';
                }

                const normalized = String(value).replace(' ', 'T');
                return normalized.slice(0, 16);
            };

            const formatDateRange = (item) => `${item.start_at || '立即生效'} ~ ${item.end_at || '长期有效'}`;

            const replaceTemplate = (template, itemId) => template.replace('__ITEM__', String(itemId));

            const updateCountBadge = () => {
                if (badge) {
                    badge.textContent = `${items.size} / ${maxItems}`;
                }
            };

            const syncSelectValue = (selectElement, value) => {
                if (!selectElement) {
                    return;
                }

                selectElement.value = value;
                Array.from(selectElement.options).forEach((option) => {
                    option.selected = option.value === value;
                });

                const root = selectElement.closest('[data-site-select]');
                const trigger = root?.querySelector('[data-select-trigger]');
                const panel = root?.querySelector('[data-select-panel]');
                const selectedOption = Array.from(selectElement.options).find((option) => option.value === value);

                if (trigger) {
                    trigger.textContent = selectedOption?.textContent || '';
                }

                if (panel) {
                    panel.querySelectorAll('.site-select-option').forEach((button) => {
                        button.classList.toggle('is-active', button.dataset.value === value);
                    });
                }
            };

            const renderDrawerImage = (attachmentId, fallbackAttachment = null) => {
                const attachment = cmsAttachments.find((item) => Number(item.id) === Number(attachmentId))
                    || upsertAttachmentCache(fallbackAttachment);
                preview.innerHTML = '';

                if (!attachment) {
                    const placeholder = document.createElement('span');
                    placeholder.textContent = '点击选择图宣图片';
                    placeholder.setAttribute('data-drawer-image-placeholder', '');
                    preview.appendChild(placeholder);
                    previewNote.textContent = '未选择图片，点击上方区域即可从资源库选图。';
                    return null;
                }

                const img = document.createElement('img');
                img.src = attachment.url || '';
                img.alt = attachment.name || '图宣图片';
                preview.appendChild(img);
                previewNote.textContent = '已选择图片，可点击上方图片重新更换。';
                return attachment;
            };

            const fillForm = (item) => {
                attachmentInput.value = item.attachment_id ? String(item.attachment_id) : '';
                document.getElementById('drawer_title').value = item.title || '';
                document.getElementById('drawer_subtitle').value = item.subtitle || '';
                document.getElementById('drawer_link_url').value = item.link_url || '';
                document.getElementById('drawer_start_at').value = toDateTimeLocal(item.start_at);
                document.getElementById('drawer_end_at').value = toDateTimeLocal(item.end_at);
                syncSelectValue(document.getElementById('drawer_link_target'), item.link_target || '_self');
                syncSelectValue(document.getElementById('drawer_status'), String(item.status ?? 1));

                if (displayMode === 'floating') {
                    const payload = item.display_payload || {};
                    syncSelectValue(document.getElementById('drawer_floating_position'), payload.position || 'right-bottom');
                    syncSelectValue(document.getElementById('drawer_floating_animation'), payload.animation || 'float');
                    document.getElementById('drawer_floating_offset_x').value = payload.offset_x ?? 24;
                    document.getElementById('drawer_floating_offset_y').value = payload.offset_y ?? 24;
                    document.getElementById('drawer_floating_width').value = payload.width ?? 180;
                    document.getElementById('drawer_floating_height').value = payload.height ?? '';
                    document.getElementById('drawer_floating_z_index').value = payload.z_index ?? 120;
                    syncSelectValue(document.getElementById('drawer_floating_show_on'), payload.show_on || 'all');
                    syncSelectValue(document.getElementById('drawer_floating_closable'), String(payload.closable === false ? 0 : 1));
                    syncSelectValue(document.getElementById('drawer_floating_remember_close'), String(payload.remember_close === false ? 0 : 1));
                    document.getElementById('drawer_floating_close_expire_hours').value = payload.close_expire_hours ?? 24;
                }

                renderDrawerImage(item.attachment_id, {
                    attachment_id: item.attachment_id,
                    attachment_name: item.attachment_name,
                    attachment_url: item.attachment_url,
                });
            };

            const closeEditor = () => {
                editingItemId = null;
                drawerErrors.hidden = true;
                drawerErrors.innerHTML = '';
                editor.hidden = true;
                document.body.style.overflow = '';
            };

            const openEditor = (itemId = null) => {
                if (itemId === null && items.size >= maxItems) {
                    window.showMessage?.('当前图宣位已达到最大图宣数量限制，请先删除或停用其他图宣内容。', 'error');
                    return;
                }

                editingItemId = itemId;
                const item = itemId === null ? cloneItem(defaults) : cloneItem(items.get(Number(itemId)) || defaults);
                editorTitle.textContent = itemId === null ? '新增图宣内容' : '编辑图宣内容';
                drawerSubmit.textContent = itemId === null ? '创建图宣内容' : '保存图宣内容';
                drawerErrors.hidden = true;
                drawerErrors.innerHTML = '';
                fillForm(item);
                editor.hidden = false;
                document.body.style.overflow = 'hidden';
            };

            const formDataFromDrawer = () => {
                const formData = new FormData(editorForm);
                return formData;
            };

            const renderCardHtml = (item) => {
                const title = item.title || item.attachment_name || '未命名图宣';
                const subtitle = item.subtitle ? `<div class="promo-item-subtitle">${escapeHtml(item.subtitle)}</div>` : '';
                const linkLine = item.link_url ? `<div>链接：${escapeHtml(item.link_url)}</div>` : '';
                const badgeClassMap = {
                    active: '',
                    disabled: ' is-muted',
                    scheduled: ' is-warning',
                    expired: ' is-danger',
                };
                const badgeClass = badgeClassMap[item.effective_status] || ' is-muted';
                const badgeText = item.effective_status_label || '已停用';
                const toggleText = Number(item.status) === 1 ? '停用' : '启用';
                const imageMarkup = item.attachment_url
                    ? `<img src="${escapeHtml(item.attachment_url)}" alt="${escapeHtml(title)}">`
                    : '<span>点击选择图宣图片</span>';

                return `
                    <div class="promo-item-card-head">
                        <span class="promo-item-status-badge${badgeClass}">${badgeText}</span>
                        <span class="promo-item-drag-handle" aria-label="拖拽排序" data-tooltip="拖拽排序">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h8v2H8zM8 11h8v2H8zM8 16h8v2H8z"/></svg>
                        </span>
                    </div>
                    <button class="promo-item-preview-button" type="button" data-replace-item-image="${item.id}">
                        <div class="promo-item-preview">${imageMarkup}</div>
                    </button>
                    <div>
                        <div class="promo-item-title">${escapeHtml(title)}</div>
                        ${subtitle}
                    </div>
                    <div class="promo-item-meta">
                        <div>文件：${escapeHtml(item.attachment_name || '未选择图片')}</div>
                        <div>时间：${escapeHtml(formatDateRange(item))}</div>
                        ${linkLine}
                    </div>
                    <div class="promo-item-actions">
                        <form method="POST" action="${replaceTemplate(duplicateUrlTemplate, item.id)}">
                            <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                            <button class="button secondary neutral-action" type="submit">复制</button>
                        </form>
                        <form method="POST" action="${replaceTemplate(toggleUrlTemplate, item.id)}">
                            <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                            <button class="button secondary neutral-action" type="submit">${toggleText}</button>
                        </form>
                        <button class="button secondary neutral-action" type="button" data-open-item-editor="${item.id}">编辑</button>
                        <form method="POST" action="${replaceTemplate(destroyUrlTemplate, item.id)}" data-promo-item-delete-form data-promo-item-delete-name="${escapeHtml(title)}">
                            <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                            <button class="button secondary neutral-action" type="submit">删除</button>
                        </form>
                    </div>
                `;
            };

            const escapeHtml = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const formatErrorMessages = (messages) => {
                return [...new Set((messages || [])
                    .map((message) => String(message || '').trim())
                    .filter((message) => message !== '')
                    .map((message) => message.replace(/[，。；、]+$/u, '')))]
                    .join('，') + '。';
            };

            const upsertCard = (item, insertAtTop = false) => {
                items.set(Number(item.id), item);

                let row = grid.querySelector(`[data-promo-item-id="${item.id}"]`);
                const isNew = !row;

                if (!row) {
                    row = document.createElement('article');
                    row.className = 'promo-item-card';
                    row.id = `promo-item-${item.id}`;
                    row.dataset.promoItemRow = '';
                    row.dataset.promoItemId = String(item.id);
                }

                row.innerHTML = renderCardHtml(item);

                if (isNew) {
                    if (insertAtTop && grid.firstChild) {
                        grid.prepend(row);
                    } else {
                        grid.appendChild(row);
                    }
                }

                bindDeleteConfirmation(row.querySelector('[data-promo-item-delete-form]'));

                emptyState.hidden = items.size > 0;
                grid.hidden = items.size === 0;
                updateCountBadge();
            };

            const submitDrawer = async (event) => {
                event.preventDefault();
                const formData = formDataFromDrawer();
                const targetUrl = editingItemId === null ? storeUrl : replaceTemplate(updateUrlTemplate, editingItemId);

                drawerErrors.hidden = true;
                drawerErrors.innerHTML = '';
                drawerSubmit.disabled = true;

                try {
                    const response = await fetch(targetUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: formData,
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const errors = payload.errors ? Object.values(payload.errors).flat() : [payload.message || '保存失败，请稍后重试。'];
                        window.showMessage?.(formatErrorMessages(errors), 'error');
                        return;
                    }

                    upsertCard(payload.item, editingItemId === null);
                    closeEditor();
                    window.showMessage?.(payload.message || '图宣内容已保存。');
                } finally {
                    drawerSubmit.disabled = false;
                }
            };

            const replaceImage = async (itemId, attachment) => {
                const response = await fetch(replaceTemplate(replaceImageUrlTemplate, itemId), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ attachment_id: attachment.id }),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message || '图宣图片更新失败，请稍后重试。');
                }

                upsertCard(payload.item);

                if (editingItemId === itemId) {
                    fillForm(payload.item);
                }

                return payload;
            };

            const bindDeleteConfirmation = (form) => {
                form?.addEventListener('submit', (event) => {
                    if (typeof window.showConfirmDialog !== 'function') {
                        return;
                    }

                    event.preventDefault();
                    const name = form.getAttribute('data-promo-item-delete-name') || '该图宣内容';

                    window.showConfirmDialog({
                        title: '确认删除图宣内容？',
                        text: `删除后将解除图片引用并移除配置：${name}`,
                        confirmText: '删除图宣内容',
                        onConfirm: () => form.submit(),
                    });
                });
            };

            document.querySelectorAll('[data-promo-item-delete-form]').forEach(bindDeleteConfirmation);

            document.addEventListener('click', (event) => {
                const createTrigger = event.target.closest('[data-open-create-drawer]');
                if (createTrigger) {
                    openEditor(null);
                    return;
                }

                const editTrigger = event.target.closest('[data-open-item-editor]');
                if (editTrigger) {
                    openEditor(Number(editTrigger.getAttribute('data-open-item-editor')));
                    return;
                }

                const replaceTrigger = event.target.closest('[data-replace-item-image]');
                if (replaceTrigger) {
                    const itemId = Number(replaceTrigger.getAttribute('data-replace-item-image'));
                    window.openSiteAttachmentLibrary?.({
                        mode: 'picker',
                        context: 'promo',
                        imageOnly: true,
                        onSelect: async (attachment) => {
                            try {
                                const payload = await replaceImage(itemId, attachment);
                                window.showMessage?.(payload.message || '图宣图片已更新。');
                            } catch (error) {
                                window.showMessage?.(error.message || '图宣图片更新失败。', 'error');
                            }
                        },
                    });
                    return;
                }

                if (event.target.closest('[data-open-drawer-image-library]')) {
                    window.openSiteAttachmentLibrary?.({
                        mode: 'picker',
                        context: 'promo',
                        imageOnly: true,
                        onSelect: (attachment) => {
                            attachmentInput.value = String(attachment.id || '');
                            renderDrawerImage(attachment.id, attachment);
                        },
                    });
                    return;
                }

                if (event.target.closest('[data-close-item-editor]')) {
                    closeEditor();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !editor.hidden) {
                    closeEditor();
                }
            });

            editorForm.addEventListener('submit', submitDrawer);

            if (window.Sortable) {
                const getOrderedIds = () => Array.from(grid.querySelectorAll('[data-promo-item-row]'))
                    .map((row) => Number(row.dataset.promoItemId));

                const saveReorder = async (orderedIds) => {
                    const response = await fetch(grid.dataset.reorderUrl || '', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ ordered_ids: orderedIds }),
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(payload.message || '图宣内容排序保存失败，请稍后重试。');
                    }

                    orderedIds.forEach((itemId, index) => {
                        const currentItem = items.get(itemId);
                        if (currentItem) {
                            currentItem.sort = (index + 1) * 10;
                        }
                    });

                    return payload;
                };

                Sortable.create(grid, {
                    animation: 180,
                    handle: '.promo-item-drag-handle',
                    draggable: '[data-promo-item-row]',
                    ghostClass: 'is-ghost',
                    chosenClass: 'is-chosen',
                    dragClass: 'is-dragging',
                    async onEnd(event) {
                        const row = event.item;

                        if (event.oldIndex === event.newIndex) {
                            return;
                        }

                        row.classList.add('is-saving');

                        try {
                            const payload = await saveReorder(getOrderedIds());
                            window.showMessage?.(payload.message || '图宣内容排序已保存。');
                        } catch (error) {
                            window.showMessage?.(error.message || '图宣内容排序保存失败，页面将刷新恢复。', 'error');
                            window.setTimeout(() => window.location.reload(), 500);
                        } finally {
                            row.classList.remove('is-saving');
                        }
                    },
                });
            }
        })();
    </script>
    @if ($errors->has('promo_item'))
        <script>
            (() => {
                const message = @json($errors->first('promo_item'));

                if (message && typeof window.showMessage === 'function') {
                    window.showMessage(message, 'error');
                }
            })();
        </script>
    @endif
@endpush
