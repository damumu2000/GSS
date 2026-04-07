@extends('layouts.admin')

@section('title', '栏目管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 栏目管理')

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
        }

        .channel-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .panel.channel-panel {
            border-radius: 0;
            overflow: visible;
            background: transparent;
            box-shadow: none;
            padding: 0;
        }

        .channel-panel .panel-header {
            margin-bottom: 18px;
        }

        .filters.channel-filters {
            display: block;
            padding: 20px 22px;
            border: 1px solid #eef1f5;
            border-radius: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
            margin-bottom: 0;
        }

        .channel-filter-grid {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
            flex-wrap: nowrap;
        }

        .channel-filter-item {
            display: flex;
            gap: 12px;
            align-items: center;
            min-width: 0;
        }

        .channel-filter-item:first-child {
            flex: 0 0 320px;
            min-width: 0;
        }

        .channel-filter-item label {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 600;
            white-space: nowrap;
        }

        .channel-filter-item #keyword.field {
            flex: 1 1 auto;
        }

        .channel-filter-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: flex-end;
            padding-bottom: 2px;
            white-space: nowrap;
            flex: 0 0 auto;
        }

        .channel-filter-actions .button {
            min-width: 0;
        }

        .channel-table {
            overflow: visible;
            background: #fff;
            box-shadow: none;
            border: 1px solid #e8edf5;
            border-radius: 20px;
            padding: 10px 12px 6px;
            margin-top: 0;
        }

        .channel-list-panel {
            margin-top: 18px;
            padding: 20px 22px;
            border: 1px solid #eef1f5;
            border-radius: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }

        .channel-table .table-admin {
            border-collapse: separate;
            border-spacing: 0;
            background: transparent;
            table-layout: fixed;
            width: 100%;
        }

        .channel-table thead th {
            color: #374151;
            font-weight: 700;
            border-bottom: none;
            background: transparent;
            padding-bottom: 8px;
        }

        .channel-table tbody td {
            padding-top: 8px;
            padding-bottom: 8px;
            border-bottom: none;
            background: transparent;
        }

        .channel-table tbody tr:hover td {
            background: rgba(0, 71, 171, 0.04);
        }

        .channel-table tbody tr:hover .channel-checkbox {
            border-color: #cbd5e1;
        }

        .channel-name-cell {
            display: flex;
            align-items: center;
            min-width: 0;
        }

        .channel-tree-row {
            display: flex;
            align-items: center;
            min-width: 0;
            width: 100%;
            min-height: 30px;
        }

        .channel-tree-guides {
            display: inline-flex;
            align-items: stretch;
            align-self: stretch;
            flex: 0 0 auto;
        }

        .channel-tree-guide,
        .channel-tree-branch {
            position: relative;
            width: 24px;
            flex: 0 0 24px;
        }

        .channel-tree-guide::before,
        .channel-tree-branch::before {
            content: '';
            position: absolute;
            left: 11px;
            top: -9px;
            bottom: -9px;
            width: 1px;
            background: transparent;
        }

        .channel-tree-guide.is-active::before {
            background: rgba(0, 71, 171, 0.08);
        }

        .channel-tree-branch::before {
            background: rgba(0, 71, 171, 0.08);
        }

        .channel-tree-branch::after {
            content: '';
            position: absolute;
            left: 11px;
            top: 50%;
            width: 12px;
            height: 1px;
            background: rgba(0, 71, 171, 0.08);
            transform: translateY(-50%);
        }

        .channel-tree-branch.is-last::before {
            bottom: 50%;
        }

        .channel-tree-content {
            display: inline-flex;
            align-items: center;
            min-width: 0;
            gap: 10px;
        }

        .channel-drag-handle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 8px;
            color: #94a3b8;
            cursor: grab;
            flex: 0 0 auto;
            transition: background 0.18s ease, color 0.18s ease;
        }

        .channel-drag-handle:hover {
            background: var(--primary-soft);
            color: var(--primary);
        }

        .channel-drag-handle:active {
            cursor: grabbing;
        }

        .channel-drag-handle svg {
            width: 14px;
            height: 14px;
            fill: currentColor;
        }

        .channel-tree-content.is-toggleable {
            cursor: pointer;
            user-select: none;
        }

        .channel-tree-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            color: #94a3b8;
            flex: 0 0 auto;
        }

        .channel-tree-toggle svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            transition: transform 0.16s ease;
        }

        .channel-tree-toggle.is-expanded svg {
            transform: rotate(90deg);
        }

        .channel-tree-toggle.is-placeholder {
            opacity: 0;
        }

        .channel-tree-toggle[data-toggle-children] {
            cursor: pointer;
        }

        .channel-row-ghost td {
            background: color-mix(in srgb, var(--primary, #0047AB) 8%, #ffffff) !important;
        }

        .channel-row-chosen td {
            background: color-mix(in srgb, var(--primary, #0047AB) 12%, #ffffff) !important;
        }

        .channel-row-drag td {
            opacity: 0.96;
        }

        tr[data-channel-row].is-sorting td {
            opacity: 0.92;
        }

        tr[data-channel-row].is-saving td {
            background: color-mix(in srgb, var(--primary, #0047AB) 5%, #ffffff) !important;
        }

        .channel-name-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            color: #94a3b8;
        }

        .channel-name-icon.is-folder {
            width: 20px;
            height: 20px;
        }

        .channel-name-icon.is-folder svg,
        .channel-name-icon.is-file svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 1.85;
            fill: none;
        }

        .channel-name-icon.is-root {
            color: #3b82f6;
        }

        .channel-name-icon.is-file {
            width: 18px;
            height: 18px;
            color: #cbd5e1;
        }

        .channel-title-text {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 15px;
            font-weight: 500;
            color: #334155;
        }

        .channel-name-cell.is-root .channel-title-text {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
        }

        .channel-parent-cell {
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .channel-type-cell,
        .channel-slug-cell {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .channel-status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 68px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #f8fafc;
            color: #475569;
            font-size: 13px;
            line-height: 1;
        }

        .channel-status-pill.is-on {
            background: rgba(16, 185, 129, 0.10);
            color: #059669;
        }

        .channel-action-row {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .channel-action-link,
        .channel-action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            padding: 0;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            text-decoration: none;
            transition: color 0.18s ease, border-color 0.18s ease, background 0.18s ease, transform 0.18s ease;
            cursor: pointer;
        }

        .channel-action-link:hover,
        .channel-action-button:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
            color: #1f2937;
        }

        .channel-action-link svg,
        .channel-action-button svg {
            width: 17px;
            height: 17px;
            stroke: currentColor;
            stroke-width: 1.9;
            fill: none;
        }

        @media (max-width: 720px) {
            .page-header {
                margin: -24px -18px 20px;
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
            }

            .channel-header-actions {
                align-items: flex-start;
            }

            .channel-filter-grid {
                flex-direction: column;
                align-items: stretch;
            }

            .channel-filter-item:first-child {
                flex: 1 1 auto;
            }

        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">栏目管理</h2>
            <div class="page-header-desc">用于维护导航、栏目结构与模板绑定关系。</div>
        </div>
        <div class="channel-header-actions">
            <a class="button" href="{{ route('admin.channels.create') }}">新建栏目</a>
        </div>
    </section>

    <section class="panel channel-panel">
        <form method="GET" action="{{ route('admin.channels.index') }}" class="filters filters-card channel-filters">
            <div class="channel-filter-grid">
                <div class="channel-filter-item">
                    <label for="keyword">搜索栏目</label>
                    <input class="field" id="keyword" type="text" name="keyword" value="{{ $keyword }}" placeholder="名称或别名">
                </div>
                <div class="channel-filter-actions">
                    <button class="button neutral-action" type="submit">筛选</button>
                    <a class="button neutral-action" href="{{ route('admin.channels.index') }}">重置</a>
                </div>
            </div>
        </form>

        <div class="channel-list-panel">
            @if ($channels->isEmpty())
                <div class="empty">当前站点还没有栏目，点击右上角“新建栏目”开始搭建信息结构。</div>
            @else
                <div class="table-wrap channel-table">
                <table class="table table-admin">
                    <colgroup>
                        <col style="width: 34%;">
                        <col style="width: 14%;">
                        <col style="width: 14%;">
                        <col style="width: 18%;">
                        <col style="width: 10%;">
                        <col style="width: 10%;">
                    </colgroup>
                    <thead>
                    <tr>
                        <th>栏目名称</th>
                        <th>栏目属性</th>
                        <th>类型</th>
                        <th>别名</th>
                        <th>导航</th>
                        <th class="table-actions">操作</th>
                    </tr>
                    </thead>
                    <tbody data-channel-reorder-url="{{ route('admin.channels.reorder') }}">
                    @foreach ($channels as $channel)
                        @php
                            $channelLevelLabel = match ((int) $channel->tree_depth) {
                                0 => '一级栏目',
                                1 => '二级栏目',
                                default => '三级栏目',
                            };
                        @endphp
                        <tr
                            data-channel-row
                            data-channel-id="{{ $channel->id }}"
                            data-parent-id="{{ $channel->parent_id }}"
                            data-depth="{{ (int) $channel->tree_depth }}"
                        >
                            <td>
                                <div class="channel-name-cell {{ (int) $channel->tree_depth > 0 ? 'is-child' : 'is-root' }}">
                                    <div class="channel-tree-row">
                                        <span class="channel-tree-guides" aria-hidden="true">
                                            @foreach ($channel->tree_ancestors as $hasLine)
                                                <span class="channel-tree-guide {{ $hasLine ? 'is-active' : '' }}"></span>
                                            @endforeach
                                            @if ((int) $channel->tree_depth > 0)
                                                <span class="channel-tree-branch {{ $channel->tree_is_last ? 'is-last' : '' }}"></span>
                                            @endif
                                        </span>
                                        <span
                                            class="channel-tree-content {{ $channel->tree_has_children ? 'is-toggleable' : '' }}"
                                            @if ($channel->tree_has_children)
                                                data-toggle-children
                                                data-channel-toggle="{{ $channel->id }}"
                                                aria-expanded="false"
                                                role="button"
                                                tabindex="0"
                                            @endif
                                        >
                                            <span class="channel-drag-handle" aria-label="拖拽排序" data-tooltip="拖拽排序">
                                                <svg viewBox="0 0 20 20" aria-hidden="true">
                                                    <circle cx="6" cy="5" r="1.4"></circle>
                                                    <circle cx="6" cy="10" r="1.4"></circle>
                                                    <circle cx="6" cy="15" r="1.4"></circle>
                                                    <circle cx="14" cy="5" r="1.4"></circle>
                                                    <circle cx="14" cy="10" r="1.4"></circle>
                                                    <circle cx="14" cy="15" r="1.4"></circle>
                                                </svg>
                                            </span>
                                            <span
                                                class="channel-tree-toggle {{ $channel->tree_has_children ? '' : 'is-placeholder' }}"
                                                aria-hidden="true"
                                            >
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="m9 18 6-6-6-6"/>
                                                </svg>
                                            </span>
                                            @if ($channel->tree_has_children || (int) $channel->tree_depth === 0)
                                                <span class="channel-name-icon is-folder {{ (int) $channel->tree_depth === 0 ? 'is-root' : '' }}" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M3 7.5A1.5 1.5 0 0 1 4.5 6h4.086a1.5 1.5 0 0 1 1.06.44l1.414 1.414A1.5 1.5 0 0 0 12.121 8.5H19.5A1.5 1.5 0 0 1 21 10v7.5A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5z"/>
                                                    </svg>
                                                </span>
                                            @else
                                                <span class="channel-name-icon is-file" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z"/>
                                                        <path d="M14 2v5h5"/>
                                                        <path d="M9 13h6"/>
                                                        <path d="M9 17h4"/>
                                                    </svg>
                                                </span>
                                            @endif
                                            <span class="channel-title-text">{{ $channel->name }}</span>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="channel-parent-cell">{{ $channelLevelLabel }}</span></td>
                            <td class="channel-type-cell"><span class="pill">{{ $channelTypes[$channel->type] ?? $channel->type }}</span></td>
                            <td class="channel-slug-cell">{{ $channel->slug }}</td>
                            <td><span class="channel-status-pill {{ $channel->is_nav ? 'is-on' : '' }}">{{ $channel->is_nav ? '显示' : '隐藏' }}</span></td>
                            <td class="table-actions">
                                <div class="channel-action-row">
                                    <a class="channel-action-link" href="{{ route('admin.channels.edit', $channel->id) }}" aria-label="编辑" title="编辑">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </a>
                                    <form id="channel-delete-form-{{ $channel->id }}" method="POST" action="{{ route('admin.channels.destroy', $channel->id) }}">
                                        @csrf
                                        <button class="channel-action-button is-danger js-channel-delete" type="button" data-form-id="channel-delete-form-{{ $channel->id }}" aria-label="删除" title="删除">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
    <script>
        (() => {
            document.querySelectorAll('.js-channel-delete').forEach((button) => {
                button.addEventListener('click', () => {
                    const formId = button.dataset.formId;
                    const form = formId ? document.getElementById(formId) : null;

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
            });

            const rows = Array.from(document.querySelectorAll('[data-channel-row]'));
            const rowMap = new Map(rows.map((row) => [row.dataset.channelId, row]));
            const expanded = new Set(
                rows
                    .filter((row) => Number(row.dataset.depth || 0) === 0)
                    .map((row) => row.dataset.channelId)
                    .filter(Boolean)
            );

            const hasExpandedAncestor = (row) => {
                let parentId = row.dataset.parentId;

                while (parentId && rowMap.has(parentId)) {
                    if (!expanded.has(parentId)) {
                        return false;
                    }
                    parentId = rowMap.get(parentId).dataset.parentId;
                }

                return true;
            };

            const syncTree = () => {
                rows.forEach((row) => {
                    const depth = Number(row.dataset.depth || 0);
                    row.style.display = depth === 0 || hasExpandedAncestor(row) ? '' : 'none';
                });

                document.querySelectorAll('[data-channel-toggle]').forEach((toggle) => {
                    const isOpen = expanded.has(toggle.dataset.channelToggle);
                    toggle.classList.toggle('is-expanded', isOpen);
                    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
            };

            document.querySelectorAll('.channel-tree-content[data-toggle-children]').forEach((toggle) => {
                const handler = () => {
                    const id = toggle.dataset.channelToggle;
                    if (!id) {
                        return;
                    }

                    if (expanded.has(id)) {
                        expanded.delete(id);
                    } else {
                        expanded.add(id);
                    }

                    syncTree();
                };

                toggle.addEventListener('click', handler);
                toggle.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        handler();
                    }
                });
            });

            syncTree();

            const tbody = document.querySelector('tbody[data-channel-reorder-url]');
            const reorderUrl = tbody?.dataset.channelReorderUrl;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            let dragState = null;

            const getRowDepth = (row) => Number(row?.dataset.depth || 0);
            const getRowParentId = (row) => row?.dataset.parentId || '';
            const getSubtreeRows = (row) => {
                const depth = getRowDepth(row);
                const subtreeRows = [row];
                let nextRow = row.nextElementSibling;

                while (nextRow && getRowDepth(nextRow) > depth) {
                    subtreeRows.push(nextRow);
                    nextRow = nextRow.nextElementSibling;
                }

                return subtreeRows;
            };

            const appendSubtreeAfterRow = (row, subtreeRows) => {
                let anchor = row.nextElementSibling;
                subtreeRows.forEach((childRow) => {
                    tbody?.insertBefore(childRow, anchor);
                });
            };

            const siblingIdsForParent = (parentId) => Array.from(tbody?.querySelectorAll('tr[data-channel-row]') || [])
                .filter((row) => getRowParentId(row) === parentId)
                .map((row) => Number(row.dataset.channelId));

            const saveReorder = async (parentId, orderedIds) => {
                const response = await fetch(reorderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        parent_id: parentId === '' ? null : Number(parentId),
                        ordered_ids: orderedIds,
                    }),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message || '栏目排序保存失败，请稍后重试。');
                }

                return payload;
            };

            if (tbody && reorderUrl && window.Sortable) {
                Sortable.create(tbody, {
                    animation: 180,
                    handle: '.channel-drag-handle',
                    draggable: 'tr[data-channel-row]',
                    ghostClass: 'channel-row-ghost',
                    chosenClass: 'channel-row-chosen',
                    dragClass: 'channel-row-drag',
                    onStart(event) {
                        const row = event.item;
                        const subtreeRows = getSubtreeRows(row);
                        const descendants = subtreeRows.slice(1);

                        dragState = {
                            rowId: row.dataset.channelId || '',
                            parentId: getRowParentId(row),
                            descendants,
                            originalSiblingIds: siblingIdsForParent(getRowParentId(row)),
                        };

                        descendants.forEach((childRow) => childRow.remove());
                        row.classList.add('is-sorting');
                    },
                    onMove(event) {
                        if (!dragState) {
                            return true;
                        }

                        const related = event.related;

                        if (!related) {
                            return false;
                        }

                        return getRowParentId(related) === dragState.parentId;
                    },
                    async onEnd(event) {
                        const row = event.item;
                        const currentState = dragState;
                        row.classList.remove('is-sorting');

                        if (!currentState) {
                            syncTree();
                            return;
                        }

                        appendSubtreeAfterRow(row, currentState.descendants);
                        syncTree();

                        const nextSiblingIds = siblingIdsForParent(currentState.parentId);
                        dragState = null;

                        if (JSON.stringify(nextSiblingIds) === JSON.stringify(currentState.originalSiblingIds)) {
                            return;
                        }

                        row.classList.add('is-saving');

                        try {
                            const payload = await saveReorder(currentState.parentId, nextSiblingIds);
                            if (typeof window.showMessage === 'function') {
                                window.showMessage(payload.message || '栏目排序已保存。');
                            }
                        } catch (error) {
                            if (typeof window.showMessage === 'function') {
                                window.showMessage(error.message || '栏目排序保存失败，页面将刷新恢复。', 'error');
                            }

                            window.setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } finally {
                            row.classList.remove('is-saving');
                        }
                    },
                });
            }
        })();
    </script>
@endpush
