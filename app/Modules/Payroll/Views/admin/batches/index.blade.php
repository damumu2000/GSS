@extends('layouts.admin')

@section('title', '工资信息 - 功能模块 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 工资信息')

@push('styles')
    @include('admin.site._custom_select_styles')
    <style>
        .payroll-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            padding: 24px 32px;
            margin: -28px -28px 24px;
            background: #fff;
            border-bottom: 1px solid #f0f0f0;
        }
        .payroll-header-title { margin: 0; color: #262626; font-size: 20px; line-height: 1.4; font-weight: 700; }
        .payroll-header-desc { margin-top: 8px; color: #8c8c8c; font-size: 14px; line-height: 1.75; }
        .payroll-filter-card,
        .payroll-list-card {
            padding: 20px 22px;
            border: 1px solid #eef2f6;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }
        .payroll-intro-card {
            margin-bottom: 18px;
            padding: 18px 20px;
            border: 1px solid #e6edf7;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }
        .payroll-intro-title {
            margin: 0;
            color: #262626;
            font-size: 16px;
            line-height: 1.5;
            font-weight: 700;
        }
        .payroll-intro-desc {
            margin-top: 8px;
            color: #667085;
            font-size: 13px;
            line-height: 1.8;
        }
        .payroll-intro-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.7;
        }
        .payroll-intro-meta strong {
            color: #475467;
            font-weight: 700;
        }
        .payroll-filter-form {
            display: grid;
            grid-template-columns: auto minmax(160px, 190px) minmax(160px, 190px) auto;
            gap: 14px;
            align-items: center;
            justify-content: start;
        }
        .payroll-filter-actions { display: flex; gap: 10px; }
        .payroll-filter-item {
            display: grid;
            gap: 8px;
            min-width: 0;
        }
        .payroll-filter-heading {
            color: #4b5563;
            font-size: 15px;
            line-height: 1.4;
            font-weight: 700;
            white-space: nowrap;
        }
        .payroll-filter-item .site-select {
            width: 100%;
        }
        .payroll-filter-item .site-select-trigger {
            min-height: 44px;
            border-radius: 12px;
            padding: 0 38px 0 14px;
            font-size: 14px;
            line-height: 44px;
        }
        .payroll-summary {
            display: grid;
            gap: 10px;
            margin-bottom: 18px;
            padding: 16px 18px;
            border-radius: 16px;
            background: #f8fbff;
            border: 1px solid #e6eef9;
        }
        .payroll-summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .payroll-summary-item {
            padding: 12px 14px;
            border-radius: 14px;
            background: #fff;
            border: 1px solid #edf2f7;
        }
        .payroll-summary-label { color: #667085; font-size: 12px; font-weight: 700; }
        .payroll-summary-value { display: block; margin-top: 6px; color: #262626; font-size: 14px; line-height: 1.7; }
        .payroll-table { width: 100%; border-collapse: collapse; }
        .payroll-table col.payroll-col-month { width: 180px; }
        .payroll-table col.payroll-col-files { width: auto; }
        .payroll-table col.payroll-col-imported { width: 170px; }
        .payroll-table col.payroll-col-status { width: 150px; }
        .payroll-table col.payroll-col-actions { width: 210px; }
        .payroll-table th,
        .payroll-table td {
            padding: 16px 14px;
            border-bottom: 1px solid #f0f0f0;
            text-align: left;
            vertical-align: middle;
        }
        .payroll-table th {
            color: #8c8c8c;
            font-size: 13px;
            font-weight: 700;
        }
        .payroll-table td {
            color: #374151;
            font-size: 14px;
            line-height: 1.75;
        }
        .payroll-table tr:last-child td { border-bottom: none; }
        .payroll-month {
            color: #111827;
            font-size: 16px;
            line-height: 1.5;
            font-weight: 700;
            white-space: nowrap;
        }
        .payroll-sub {
            display: block;
            margin-top: 6px;
            color: #98a2b3;
            font-size: 12px;
        }
        .payroll-file-list { display: grid; gap: 6px; max-width: 100%; }
        .payroll-file-name { color: #475467; font-weight: 600; }
        .payroll-empty-file { color: #98a2b3; }
        .payroll-file-item {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            align-items: center;
            gap: 6px;
            min-width: 0;
        }
        .payroll-file-tooltip {
            display: block;
            min-width: 0;
        }
        .payroll-file-text {
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .payroll-empty {
            padding: 34px 28px;
            border-radius: 16px;
            border: 1px dashed #dbe4ee;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            text-align: center;
        }
        .payroll-empty-title {
            color: #344054;
            font-size: 18px;
            line-height: 1.6;
            font-weight: 700;
        }
        .payroll-empty-desc {
            margin-top: 8px;
            color: #98a2b3;
            font-size: 13px;
            line-height: 1.8;
        }
        .payroll-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            min-width: 96px;
            padding: 0 18px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }
        .payroll-status.is-imported {
            color: #059669;
            background: rgba(16, 185, 129, 0.10);
        }
        .payroll-status.is-draft {
            color: #b45309;
            background: rgba(245, 158, 11, 0.12);
        }
        .payroll-ops {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: #374151;
            font-weight: 700;
            white-space: nowrap;
            min-height: 34px;
            padding: 0 12px;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #fff;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            transform: translateY(0);
            transition: border-color 0.18s ease, color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }
        .payroll-ops::after {
            content: '';
            width: 14px;
            flex: 0 0 14px;
        }
        .payroll-ops i {
            width: 14px;
            text-align: center;
            flex: 0 0 14px;
        }
        .payroll-ops:hover {
            color: #1f2937;
            border-color: #d1d5db;
            background: #f9fafb;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
            transform: translateY(-1px);
        }
        .payroll-ops:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
        }
        .payroll-ops svg {
            transition: transform 0.18s ease, opacity 0.18s ease;
        }
        .payroll-ops:hover svg {
            transform: scale(1.06);
            opacity: 0.92;
        }
        .payroll-ops.is-danger {
            color: #b42318;
            border-color: rgba(180, 83, 9, 0.18);
            background: #fff;
        }
        .payroll-ops.is-danger:hover {
            color: #991b1b;
            border-color: rgba(180, 83, 9, 0.28);
            background: #fff7ed;
        }
        .payroll-ops-group {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: flex-end;
            flex-wrap: nowrap;
            white-space: nowrap;
        }
        .payroll-pagination {
            margin-top: 18px;
        }
        .payroll-pagination .pagination-shell {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: nowrap;
            min-width: max-content;
        }
        .payroll-pagination .pagination-pages {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .payroll-pagination .pagination-button,
        .payroll-pagination .pagination-page,
        .payroll-pagination .pagination-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            height: 32px;
            min-width: 32px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            color: #4b5563;
            font-size: 13px;
            line-height: 1;
            text-decoration: none;
            transition: all 0.2s;
        }
        .payroll-pagination .pagination-page {
            width: 32px;
            padding: 0;
        }
        .payroll-pagination .pagination-button {
            border: 0;
            background: transparent;
            min-width: auto;
            padding: 0 4px;
            color: #4b5563;
        }
        .payroll-pagination .pagination-button:hover,
        .payroll-pagination .pagination-page:hover {
            transform: translateY(-1px);
            background: #f9fafb;
            border-color: #d1d5db;
        }
        .payroll-pagination .pagination-button:hover {
            background: transparent;
            border-color: transparent;
            color: #262626;
        }
        .payroll-pagination .pagination-page.is-active,
        .payroll-pagination .pagination-page.is-active:visited {
            border-color: #374151 !important;
            background: #374151 !important;
            color: #ffffff !important;
            font-weight: 600;
            transform: none;
        }
        .payroll-pagination .pagination-button.is-disabled,
        .payroll-pagination .pagination-page.is-disabled,
        .payroll-pagination .pagination-ellipsis {
            color: #c0c4cc;
            cursor: not-allowed;
        }
        .payroll-pagination .pagination-button.is-disabled:hover,
        .payroll-pagination .pagination-page.is-disabled:hover {
            transform: none;
            background: #ffffff;
            border-color: #e5e7eb;
        }
        .payroll-pagination .pagination-button.is-disabled,
        .payroll-pagination .pagination-button.is-disabled:hover {
            background: transparent;
            border-color: transparent;
        }
        @media (max-width: 960px) {
            .payroll-filter-form,
            .payroll-summary-grid { grid-template-columns: 1fr; }
            .payroll-table,
            .payroll-table thead,
            .payroll-table tbody,
            .payroll-table tr,
            .payroll-table th,
            .payroll-table td { display: block; width: 100%; }
            .payroll-table thead { display: none; }
            .payroll-table tr { padding: 16px 0; border-bottom: 1px solid #f0f0f0; }
            .payroll-table td {
                padding: 8px 0;
                border-bottom: none;
            }
        }
    </style>
@endpush

@push('scripts')
    @include('admin.site._custom_select_scripts')
@endpush

@section('content')
    <div class="payroll-header">
        <div>
            <h1 class="payroll-header-title">工资信息</h1>
            <div class="payroll-header-desc">按月份维护工资条与绩效表，上传对应表格即可，第一列必须是姓名，不能重复。</div>
        </div>
        <div class="page-header-actions">
            <a class="button" href="{{ route('admin.payroll.batches.create') }}">新增工资批次</a>
        </div>
    </div>

    @include('payroll::admin._nav')

    @if (! empty($importSummary) && is_array($importSummary))
        <div class="payroll-filter-card" style="margin-bottom: 18px;">
            <div class="payroll-summary">
                <strong style="color:#262626;font-size:15px;">最近一次解析结果</strong>
                <div class="payroll-summary-grid">
                    @foreach ($importSummary as $type => $summary)
                        <div class="payroll-summary-item">
                            <span class="payroll-summary-label">{{ $type === 'salary' ? '工资表' : '绩效表' }}</span>
                            <span class="payroll-summary-value">
                                已匹配 {{ $summary['matched'] ?? 0 }} 位员工，
                                解析 {{ is_countable($summary['sheets'] ?? null) ? count($summary['sheets']) : 0 }} 个工作表。
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="payroll-filter-card">
        <form method="GET" action="{{ route('admin.payroll.batches.index') }}" class="payroll-filter-form">
            <div class="payroll-filter-heading">搜索批次</div>
            <div class="payroll-filter-item">
                <div class="site-select" data-site-select>
                    <select class="site-select-native" name="year">
                        <option value="">全部年份</option>
                        @foreach ($yearOptions as $year)
                            <option value="{{ $year }}" @selected($selectedYear === $year)>{{ $year }} 年</option>
                        @endforeach
                    </select>
                    <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">
                        {{ $selectedYear !== '' ? $selectedYear . ' 年' : '全部年份' }}
                    </button>
                    <div class="site-select-panel" data-select-panel role="listbox"></div>
                </div>
            </div>
            <div class="payroll-filter-item">
                <div class="site-select" data-site-select>
                    <select class="site-select-native" name="month">
                        <option value="">全部月份</option>
                        @foreach (range(1, 12) as $month)
                            @php($monthValue = str_pad((string) $month, 2, '0', STR_PAD_LEFT))
                            <option value="{{ $monthValue }}" @selected($selectedMonth === $monthValue || $selectedMonth === (string) $month)>{{ $month }} 月</option>
                        @endforeach
                    </select>
                    <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">
                        {{ $selectedMonth !== '' ? ((int) $selectedMonth) . ' 月' : '全部月份' }}
                    </button>
                    <div class="site-select-panel" data-select-panel role="listbox"></div>
                </div>
            </div>
            <div class="payroll-filter-actions">
                <button class="button" type="submit">筛选</button>
                <a class="button secondary" href="{{ route('admin.payroll.batches.index') }}">重置</a>
            </div>
        </form>
    </div>

    <div class="payroll-list-card" style="margin-top: 20px;">
        @if ($batches->count() === 0)
            <div class="payroll-empty">
                <div class="payroll-empty-title">当前暂无工资批次</div>
                <div class="payroll-empty-desc">先新增一个月份批次，再上传对应的工资表和绩效表即可开始查询。</div>
            </div>
        @else
            <table class="payroll-table">
                <colgroup>
                    <col class="payroll-col-month">
                    <col class="payroll-col-files">
                    <col class="payroll-col-imported">
                    <col class="payroll-col-status">
                    <col class="payroll-col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th>月份信息</th>
                        <th>解析文件</th>
                        <th>最近解析</th>
                        <th>状态</th>
                        <th style="text-align: right;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($batches as $batch)
                        <tr>
                            <td>
                                <span class="payroll-month">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $batch->month_key)->format('Y年n月') }}</span>
                            </td>
                            <td>
                                <div class="payroll-file-list">
                                    <div class="payroll-file-item">
                                        <span class="payroll-file-name">工资表：</span>
                                        @if ($batch->salary_file_name)
                                            <span class="payroll-file-tooltip" data-tooltip="{{ $batch->salary_file_name }}">
                                                <span class="payroll-file-text">{{ $batch->salary_file_name }}</span>
                                            </span>
                                        @else
                                            <span class="payroll-empty-file">未上传</span>
                                        @endif
                                    </div>
                                    <div class="payroll-file-item">
                                        <span class="payroll-file-name">绩效表：</span>
                                        @if ($batch->performance_file_name)
                                            <span class="payroll-file-tooltip" data-tooltip="{{ $batch->performance_file_name }}">
                                                <span class="payroll-file-text">{{ $batch->performance_file_name }}</span>
                                            </span>
                                        @else
                                            <span class="payroll-empty-file">未上传</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($batch->imported_at)
                                    {{ \Illuminate\Support\Carbon::parse($batch->imported_at)->format('Y-m-d H:i') }}
                                @else
                                    <span class="payroll-empty-file">暂无解析记录</span>
                                @endif
                            </td>
                            <td>
                                <span class="payroll-status {{ $batch->status === 'imported' ? 'is-imported' : 'is-draft' }}">
                                    {{ $batch->status === 'imported' ? '已解析' : '待上传' }}
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="payroll-ops-group">
                                    <a class="payroll-ops" href="{{ route('admin.payroll.batches.edit', $batch->id) }}">
                                        <i class="fa-regular fa-pen-to-square"></i>
                                        <span>编辑</span>
                                    </a>
                                    <button
                                        class="payroll-ops is-danger js-payroll-batch-delete"
                                        type="button"
                                        data-form-id="delete-payroll-batch-{{ $batch->id }}"
                                        data-batch-label="{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $batch->month_key)->format('Y年n月') }}"
                                    >
                                        <i class="fa-regular fa-trash-can"></i>
                                        <span>删除</span>
                                    </button>
                                    <form id="delete-payroll-batch-{{ $batch->id }}" method="POST" action="{{ route('admin.payroll.batches.destroy', $batch->id) }}" style="display: none;">
                                        @csrf
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($batches->hasPages())
                <div class="payroll-pagination">
                    {{ $batches->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            document.querySelectorAll('.js-payroll-batch-delete').forEach((button) => {
                button.addEventListener('click', () => {
                    const formId = button.dataset.formId;
                    const batchLabel = button.dataset.batchLabel || '该月份';
                    const form = formId ? document.getElementById(formId) : null;

                    if (!form || typeof window.showConfirmDialog !== 'function') {
                        return;
                    }

                    window.showConfirmDialog({
                        title: '确认删除工资批次？',
                        text: `${batchLabel}工资批次及该月份已解析的工资、绩效数据都会被删除，操作不可恢复。`,
                        confirmText: '确定删除',
                        onConfirm: () => form.submit(),
                    });
                });
            });
        })();
    </script>
@endpush
