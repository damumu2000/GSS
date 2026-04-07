@extends('layouts.admin')

@section('title', '回收站 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 回收站')

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
        }

        .panel.recycle-page-panel {
            overflow: visible;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
            padding: 0;
        }

        .recycle-record-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 34px;
            padding: 0 14px;
            border-radius: 999px;
            background: #f5f7fb;
            color: #4b5563;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .recycle-filter-card {
            padding: 20px 22px;
            border: 1px solid #eef1f5;
            border-radius: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }

        .recycle-filter-grid {
            display: flex;
            gap: 14px;
            align-items: center;
            flex-wrap: nowrap;
        }

        .recycle-filter-item {
            display: flex;
            gap: 12px;
            align-items: center;
            min-width: 0;
        }

        .recycle-filter-item label {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 600;
            white-space: nowrap;
        }

        .recycle-filter-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: flex-end;
            padding-bottom: 2px;
        }

        .recycle-list-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .recycle-bulk-row {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }

        .recycle-table-wrap {
            border: 1px solid #eef1f5;
            border-radius: 20px;
            overflow: hidden;
            background: #ffffff;
            margin-top: 0;
        }

        .recycle-list-panel {
            margin-top: 18px;
            padding: 20px 22px;
            border: 1px solid #eef1f5;
            border-radius: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }

        .recycle-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .recycle-table thead th {
            padding: 18px 16px;
            background: #fafbfc;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 700;
            letter-spacing: 0.02em;
            border-bottom: 1px solid #eef1f5;
            text-align: left;
        }

        .recycle-table tbody td {
            padding: 18px 16px;
            border-bottom: 1px solid #f3f4f6;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.6;
            vertical-align: middle;
        }

        .recycle-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .recycle-table tbody tr:hover,
        .recycle-table tbody tr:hover td {
            background: transparent !important;
        }

        .recycle-table th:nth-child(1),
        .recycle-table td:nth-child(1) {
            width: 52px;
            text-align: center;
        }

        .recycle-table th:nth-child(2),
        .recycle-table td:nth-child(2) {
            width: 38%;
        }

        .recycle-table th:nth-child(3),
        .recycle-table td:nth-child(3) {
            width: 12%;
        }

        .recycle-table th:nth-child(4),
        .recycle-table td:nth-child(4) {
            width: 16%;
        }

        .recycle-table th:nth-child(5),
        .recycle-table td:nth-child(5) {
            width: 16%;
        }

        .recycle-table th:nth-child(6),
        .recycle-table td:nth-child(6) {
            width: 18%;
        }

        .recycle-checkbox {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }

        .recycle-title-wrap {
            min-width: 0;
            position: relative;
            background: transparent !important;
            box-shadow: none !important;
            border: 0 !important;
        }

        .recycle-title {
            color: #1f2937;
            font-size: 15px;
            line-height: 1.6;
            font-weight: 400;
            display: block;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            cursor: default;
            background: transparent !important;
            box-shadow: none !important;
            border: 0 !important;
        }

        .recycle-title-wrap:hover,
        .recycle-title:hover {
            background: transparent !important;
            box-shadow: none !important;
            border: 0 !important;
        }

        .recycle-type-tag,
        .recycle-channel-tag {
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            color: #4b5563;
            font-size: 13px;
            font-weight: 600;
            min-height: auto;
            padding: 0;
            border-radius: 0;
            background: transparent;
        }

        .recycle-date {
            color: #6b7280;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
        }

        .recycle-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: flex-start;
            white-space: nowrap;
        }

        .recycle-action-button {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            border: 1px solid #e7ebf1;
            background: #fff;
            color: #667085;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: border-color 0.2s ease, background 0.2s ease, color 0.2s ease, transform 0.2s ease;
            position: relative;
        }

        .recycle-action-button svg {
            width: 17px;
            height: 17px;
            stroke: currentColor;
            stroke-width: 1.9;
            fill: none;
        }

        .recycle-action-button:hover {
            transform: translateY(-1px);
        }

        .recycle-action-button.is-restore:hover {
            border-color: rgba(34, 197, 94, 0.18);
            background: rgba(34, 197, 94, 0.06);
            color: #16a34a;
        }

        .recycle-action-button.is-destroy:hover {
            border-color: rgba(239, 68, 68, 0.18);
            background: rgba(239, 68, 68, 0.06);
            color: #ef4444;
        }

        .recycle-tooltip {
            position: absolute;
            left: 50%;
            bottom: calc(100% + 10px);
            transform: translateX(-50%) translateY(4px);
            padding: 8px 12px;
            border-radius: 14px;
            background: rgba(15, 23, 42, 0.92);
            color: #ffffff;
            font-size: 12px;
            line-height: 1;
            font-weight: 700;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.18s ease, transform 0.18s ease;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.18);
            z-index: 8;
        }

        .recycle-action-button:hover .recycle-tooltip {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        .recycle-pagination {
            padding: 18px 6px 4px;
        }

        .recycle-pagination nav {
            width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            padding-top: 2px;
        }

        .recycle-pagination .pagination-shell {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: nowrap;
            min-width: max-content;
        }

        .recycle-pagination .pagination-pages {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .recycle-pagination .pagination-button,
        .recycle-pagination .pagination-page,
        .recycle-pagination .pagination-ellipsis {
            min-width: 40px;
            height: 40px;
            padding: 0 14px;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            color: #4b5563;
            font-size: 13px;
            line-height: 1;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            transition: all 0.2s ease;
        }

        .recycle-pagination .pagination-page {
            min-width: 40px;
            padding: 0;
        }

        .recycle-pagination .pagination-button {
            gap: 8px;
        }

        .recycle-pagination .pagination-button:hover,
        .recycle-pagination .pagination-page:hover {
            border-color: #d8dee8;
            background: #f8fafc;
            color: #344054;
        }

        .recycle-pagination .pagination-page.is-active,
        .recycle-pagination .pagination-page.is-active:visited {
            border-color: transparent;
            background: #374151;
            color: #ffffff;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12);
        }

        .recycle-pagination .pagination-button.is-disabled,
        .recycle-pagination .pagination-page.is-disabled,
        .recycle-pagination .pagination-ellipsis {
            color: #c0c6d1;
            background: transparent;
            border-color: transparent;
            box-shadow: none;
            pointer-events: none;
        }

        .recycle-empty {
            padding: 42px 18px;
            border: 1px dashed #dce4ef;
            border-radius: 18px;
            background: #fbfdff;
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.7;
            text-align: center;
        }

        @media (max-width: 1024px) {
            .recycle-filter-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .page-header {
                margin: -24px -18px 20px;
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
            }

            .recycle-table-wrap {
                overflow-x: auto;
            }

            .recycle-table {
                min-width: 760px;
            }

            .recycle-filter-grid {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">回收站</h2>
            <div class="page-header-desc">统一查看已删除内容，支持筛选、恢复与彻底删除。</div>
        </div>
        <span class="recycle-record-badge">{{ $deletedContents->total() }} 条记录</span>
    </section>

    <section class="panel recycle-page-panel">
        <div class="recycle-filter-card">
            <form method="GET" action="{{ route('admin.recycle-bin.index') }}" class="recycle-filter-grid">
                <div class="recycle-filter-item" style="flex: 0 0 320px;">
                    <label for="keyword">搜索标题</label>
                    <input class="field" id="keyword" type="text" name="keyword" value="{{ $keyword }}" placeholder="标题关键词">
                </div>
                <div class="recycle-filter-item" style="flex: 0 0 340px;">
                    <label for="type">类型筛选</label>
                    <div class="site-select" data-site-select style="flex: 1 1 auto;">
                        <select id="type" name="type" class="field site-select-native">
                            <option value="">全部类型</option>
                            <option value="page" @selected($selectedType === 'page')>单页面</option>
                            <option value="article" @selected($selectedType === 'article')>文章</option>
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">{{ $selectedType === 'page' ? '单页面' : ($selectedType === 'article' ? '文章' : '全部类型') }}</button>
                        <div class="site-select-panel" data-select-panel role="listbox"></div>
                    </div>
                </div>
                <div class="recycle-filter-actions" style="justify-content:flex-end; flex: 0 0 auto;">
                    <button class="button neutral-action" type="submit">筛选</button>
                    <a class="button neutral-action" href="{{ route('admin.recycle-bin.index') }}">重置</a>
                </div>
            </form>
        </div>

        <div class="recycle-list-panel">
            @if ($deletedContents->isEmpty())
                <div class="recycle-empty">当前回收站为空。</div>
            @else
                <form id="recycle-bulk-form" method="POST" action="{{ route('admin.recycle-bin.bulk') }}">
                    @csrf
                    <input type="hidden" name="action" value="restore" id="recycle-bulk-action">
                </form>
                <form id="recycle-empty-form" method="POST" action="{{ route('admin.recycle-bin.empty') }}">
                    @csrf
                </form>

                <div class="recycle-list-toolbar">
                    <div class="recycle-bulk-row">
                        <button id="recycle-toggle-all" class="button neutral-action" type="button">全选</button>
                        <div class="site-select" data-site-select style="min-width: 170px;">
                            <select id="recycle_bulk_action_select" class="field site-select-native">
                                <option value="restore">批量恢复</option>
                                <option value="delete">批量彻底删除</option>
                            </select>
                            <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">批量恢复</button>
                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                        </div>
                        <button id="recycle-bulk-submit" class="button neutral-action" type="submit" form="recycle-bulk-form">批量操作</button>
                        <button id="recycle-empty-submit" class="button neutral-action" type="submit" form="recycle-empty-form">清空回收站</button>
                    </div>
                    <span class="recycle-record-badge">{{ $deletedContents->total() }} 条记录</span>
                </div>

                <div class="recycle-table-wrap">
                <table class="recycle-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>标题</th>
                            <th>类型</th>
                            <th>栏目</th>
                            <th>删除时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deletedContents as $item)
                            <tr>
                                <td>
                                    <input class="recycle-checkbox js-recycle-checkbox" type="checkbox" name="ids[]" value="{{ $item->id }}" form="recycle-bulk-form">
                                </td>
                                <td>
                                    <div class="recycle-title-wrap" data-tooltip="ID {{ $item->id }} · {{ $item->title }}">
                                        <span class="recycle-title">{{ $item->title }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="recycle-type-tag">{{ $item->type === 'page' ? '单页面' : '文章' }}</span>
                                </td>
                                <td>
                                    <span class="recycle-channel-tag">{{ $item->channel_name ?: '未归类' }}</span>
                                </td>
                                <td>
                                    <span class="recycle-date">{{ \Illuminate\Support\Carbon::parse($item->deleted_at)->format('m-d H:i') }}</span>
                                </td>
                                <td>
                                    <div class="recycle-actions">
                                        <form id="recycle-restore-form-{{ $item->id }}" method="POST" action="{{ route('admin.recycle-bin.restore', $item->id) }}">
                                            @csrf
                                        </form>
                                        <button class="recycle-action-button is-restore"
                                                type="button"
                                                data-recycle-restore-trigger
                                                data-form-id="recycle-restore-form-{{ $item->id }}"
                                                data-content-title="{{ $item->title }}">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M9 8H5v4"/>
                                                <path d="M5.5 11.5A7 7 0 1 0 8.2 6.2"/>
                                            </svg>
                                            <span class="recycle-tooltip">恢复内容</span>
                                        </button>

                                        <form id="recycle-destroy-form-{{ $item->id }}" method="POST" action="{{ route('admin.recycle-bin.destroy', $item->id) }}">
                                            @csrf
                                        </form>
                                        <button class="recycle-action-button is-destroy"
                                                type="button"
                                                data-recycle-destroy-trigger
                                                data-form-id="recycle-destroy-form-{{ $item->id }}"
                                                data-content-title="{{ $item->title }}">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M3 6h18"/>
                                                <path d="M8 6V4h8v2"/>
                                                <path d="M19 6l-1 14H6L5 6"/>
                                                <path d="M10 11v6M14 11v6"/>
                                            </svg>
                                            <span class="recycle-tooltip">彻底删除</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>

                <div class="recycle-pagination">{{ $deletedContents->links() }}</div>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggleAllButton = document.getElementById('recycle-toggle-all');
            const checkboxes = Array.from(document.querySelectorAll('.js-recycle-checkbox'));
            const bulkSubmit = document.getElementById('recycle-bulk-submit');
            const bulkActionInput = document.getElementById('recycle-bulk-action');
            const bulkActionSelect = document.getElementById('recycle_bulk_action_select');
            const bulkForm = document.getElementById('recycle-bulk-form');
            const emptySubmit = document.getElementById('recycle-empty-submit');
            const emptyForm = document.getElementById('recycle-empty-form');

            const syncToggleLabel = () => {
                if (!toggleAllButton) {
                    return;
                }

                const allChecked = checkboxes.length > 0 && checkboxes.every((item) => item.checked);
                toggleAllButton.textContent = allChecked ? '取消全选' : '全选';
            };

            toggleAllButton?.addEventListener('click', () => {
                const allChecked = checkboxes.length > 0 && checkboxes.every((item) => item.checked);

                checkboxes.forEach((checkbox) => {
                    checkbox.checked = !allChecked;
                });

                syncToggleLabel();
            });

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    syncToggleLabel();
                });
            });

            syncToggleLabel();

            bulkActionSelect?.addEventListener('change', () => {
                if (bulkActionInput) {
                    bulkActionInput.value = bulkActionSelect.value;
                }
            });

            bulkSubmit?.addEventListener('click', (event) => {
                event.preventDefault();

                if (!bulkForm || !bulkActionInput) {
                    return;
                }

                const checkedCount = checkboxes.filter((checkbox) => checkbox.checked).length;

                if (!checkedCount) {
                    showMessage('请先勾选需要处理的内容。');
                    return;
                }

                const isDelete = bulkActionInput.value === 'delete';

                window.showConfirmDialog({
                    title: isDelete ? '确认批量彻底删除内容？' : '确认批量恢复内容？',
                    text: isDelete
                        ? `将彻底删除 ${checkedCount} 条内容，删除后无法恢复。`
                        : `将恢复 ${checkedCount} 条内容到正常列表。`,
                    confirmText: isDelete ? '批量彻底删除' : '批量恢复',
                    onConfirm: () => bulkForm.submit(),
                });
            });

            emptySubmit?.addEventListener('click', (event) => {
                event.preventDefault();

                if (!emptyForm) {
                    return;
                }

                window.showConfirmDialog({
                    title: '确认清空回收站？',
                    text: `将彻底删除当前站点回收站中的 ${checkboxes.length} 条内容，删除后无法恢复。`,
                    confirmText: '清空回收站',
                    onConfirm: () => emptyForm.submit(),
                });
            });

            document.querySelectorAll('[data-recycle-restore-trigger]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();

                    const formId = button.dataset.formId;
                    const contentTitle = button.dataset.contentTitle || '该内容';
                    const formElement = formId ? document.getElementById(formId) : null;

                    if (!formElement) {
                        return;
                    }

                    window.showConfirmDialog({
                        title: '确认恢复内容？',
                        text: `恢复后将重新回到正常内容列表：${contentTitle}`,
                        confirmText: '恢复内容',
                        onConfirm: () => formElement.submit(),
                    });
                });
            });

            document.querySelectorAll('[data-recycle-destroy-trigger]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();

                    const formId = button.dataset.formId;
                    const contentTitle = button.dataset.contentTitle || '该内容';
                    const formElement = formId ? document.getElementById(formId) : null;

                    if (!formElement) {
                        return;
                    }

                    window.showConfirmDialog({
                        title: '确认彻底删除内容？',
                        text: `彻底删除后将无法恢复：${contentTitle}`,
                        confirmText: '彻底删除',
                        onConfirm: () => formElement.submit(),
                    });
                });
            });
        });
    </script>
@endpush
