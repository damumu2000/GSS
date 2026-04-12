@extends('layouts.admin')

@section('title', '工资信息 - 功能模块 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 工资信息')

@push('styles')
    @include('admin.site._custom_select_styles')
    <link rel="stylesheet" href="{{ asset('css/payroll-admin-header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/payroll-admin-batches-index.css') }}">
@endpush

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script src="{{ asset('js/payroll-admin-batches-index.js') }}"></script>
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
        <div class="payroll-filter-card payroll-filter-card--spaced">
            <div class="payroll-summary">
                <strong class="payroll-summary-heading">最近一次解析结果</strong>
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

    <div class="payroll-list-card payroll-list-card--spaced">
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
                        <th class="u-text-right">操作</th>
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
                            <td class="u-text-right">
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
                                    <form id="delete-payroll-batch-{{ $batch->id }}" method="POST" action="{{ route('admin.payroll.batches.destroy', $batch->id) }}" class="u-hidden-form">
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
