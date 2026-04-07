@extends('layouts.admin')

@section('title', '员工管理 - 功能模块 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 员工管理')

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
        .payroll-header-title {
            margin: 0;
            color: #1d2939;
            font-size: 22px;
            line-height: 1.35;
            font-weight: 800;
        }
        .payroll-header-desc {
            margin-top: 8px;
            color: #667085;
            font-size: 14px;
            line-height: 1.75;
        }
        .payroll-shell {
            display: grid;
            gap: 18px;
            width: 100%;
        }
        .payroll-filter-card,
        .payroll-list-card {
            padding: 20px 22px;
            border: 1px solid #e8edf4;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }
        .payroll-filter-row {
            display: grid;
            grid-template-columns: minmax(320px, 420px) minmax(180px, 220px) auto;
            gap: 16px 14px;
            align-items: end;
        }
        .payroll-filter-field {
            display: grid;
            gap: 8px;
            min-width: 0;
        }
        .payroll-filter-field.is-search {
            width: 100%;
            max-width: 420px;
        }
        .payroll-filter-field.is-status {
            width: 100%;
            max-width: 220px;
        }
        .payroll-filter-field .field-label {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 600;
            margin: 0;
        }
        .payroll-filter-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: max-content;
        }
        .payroll-filter-actions .button,
        .payroll-filter-actions .button.secondary {
            min-height: 40px;
            padding: 0 16px;
            border-radius: 12px;
        }
        .payroll-table {
            width: 100%;
            border-collapse: collapse;
        }
        .payroll-table th,
        .payroll-table td {
            padding: 20px 12px;
            border-bottom: 1px solid #eef2f6;
            vertical-align: middle;
            text-align: left;
        }
        .payroll-table th {
            color: #98a2b3;
            font-size: 13px;
            font-weight: 700;
        }
        .payroll-table td {
            color: #344054;
            font-size: 14px;
            line-height: 1.7;
        }
        .payroll-table tr:last-child td {
            border-bottom: none;
        }
        .payroll-employee-name {
            color: #111827;
            font-size: 15px;
            font-weight: 800;
        }
        .payroll-employee-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 8px;
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.6;
        }
        .payroll-employee-tag {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 0 10px;
            border-radius: 999px;
            background: rgba(0, 80, 179, 0.08);
            color: #0050b3;
            font-size: 12px;
            font-weight: 700;
        }
        .payroll-wechat-name {
            color: #344054;
            font-size: 14px;
            font-weight: 700;
        }
        .payroll-wechat-id {
            display: inline-block;
            max-width: 220px;
            margin-top: 6px;
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.6;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: top;
        }
        .payroll-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            padding: 0 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }
        .payroll-status.is-approved {
            color: #059669;
            background: rgba(16, 185, 129, 0.10);
        }
        .payroll-status.is-pending {
            color: #b45309;
            background: rgba(245, 158, 11, 0.12);
        }
        .payroll-status.is-disabled {
            color: #64748b;
            background: rgba(148, 163, 184, 0.14);
        }
        .payroll-password-state {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 28px;
            padding: 0 12px;
            border-radius: 999px;
            background: #f8fafc;
            color: #667085;
            font-size: 12px;
            font-weight: 700;
        }
        .payroll-password-state.is-on {
            background: rgba(0, 80, 179, 0.08);
            color: #0050b3;
        }
        .payroll-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }
        .payroll-action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #fff;
            color: #374151;
            font-size: 12px;
            font-weight: 700;
        }
        .payroll-action-button.is-primary {
            border-color: rgba(0, 80, 179, 0.14);
            background: rgba(0, 80, 179, 0.06);
            color: #0050b3;
        }
        .payroll-empty {
            padding: 36px 18px;
            text-align: center;
            color: #98a2b3;
            font-size: 15px;
            line-height: 1.9;
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
        @media (max-width: 980px) {
            .payroll-filter-row {
                grid-template-columns: 1fr;
            }
            .payroll-filter-field.is-search,
            .payroll-filter-field.is-status {
                width: 100%;
                max-width: none;
            }
            .payroll-table,
            .payroll-table thead,
            .payroll-table tbody,
            .payroll-table tr,
            .payroll-table th,
            .payroll-table td {
                display: block;
                width: 100%;
            }
            .payroll-table thead {
                display: none;
            }
            .payroll-table tr {
                padding: 16px 0;
                border-bottom: 1px solid #eef2f6;
            }
            .payroll-table td {
                padding: 8px 0;
                border-bottom: none;
            }
            .payroll-actions {
                justify-content: flex-start;
                margin-top: 6px;
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
            <h1 class="payroll-header-title">员工管理</h1>
            <div class="payroll-header-desc">审核员工、启停账户和重置密码都在这里完成。</div>
        </div>
    </div>

    @include('payroll::admin._nav')

    <div class="payroll-shell">
        <section class="payroll-filter-card">
            <form method="GET" action="{{ route('admin.payroll.employees.index') }}" class="payroll-filter-row">
                <label class="payroll-filter-field is-search">
                    <span class="field-label">搜索员工</span>
                    <input class="input" type="text" name="keyword" value="{{ $keyword }}" placeholder="输入姓名、手机号码、微信名或微信 ID">
                </label>
                <div class="payroll-filter-field is-status">
                    <span class="field-label">员工状态</span>
                    <div class="site-select" data-site-select>
                        <select class="input site-select-native" name="status">
                            <option value="">全部状态</option>
                            <option value="pending" @selected($status === 'pending')>待审核</option>
                            <option value="approved" @selected($status === 'approved')>已通过</option>
                            <option value="disabled" @selected($status === 'disabled')>已禁用</option>
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">
                            {{ $status === 'pending' ? '待审核' : ($status === 'approved' ? '已通过' : ($status === 'disabled' ? '已禁用' : '全部状态')) }}
                        </button>
                        <div class="site-select-panel" data-select-panel role="listbox"></div>
                    </div>
                </div>
                <div class="payroll-filter-actions">
                    <button class="button" type="submit">筛选</button>
                    <a class="button secondary" href="{{ route('admin.payroll.employees.index') }}">重置</a>
                </div>
            </form>
        </section>

        <section class="payroll-list-card">
            @if ($employees->count() === 0)
                <div class="payroll-empty">当前暂无员工记录。</div>
            @else
                <table class="payroll-table">
                    <thead>
                        <tr>
                            <th>员工信息</th>
                            <th>微信信息</th>
                            <th>状态</th>
                            <th>密码状态</th>
                            <th>最近登录</th>
                            <th style="text-align: right;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $employee)
                            <tr>
                                <td>
                                    <div class="payroll-employee-name">{{ $employee->name }}</div>
                                    <div class="payroll-employee-meta">
                                        <span class="payroll-employee-tag">ID:{{ $employee->id }}</span>
                                        <span>{{ $employee->mobile ?: '未填写手机号码' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="payroll-wechat-name">{{ $employee->wechat_nickname ?: '未获取微信昵称' }}</div>
                                    <span class="payroll-wechat-id" title="{{ $employee->wechat_openid ?: '未获取微信 ID' }}">{{ $employee->wechat_openid ?: '未获取微信 ID' }}</span>
                                </td>
                                <td>
                                    <span class="payroll-status {{ $employee->status === 'approved' ? 'is-approved' : ($employee->status === 'disabled' ? 'is-disabled' : 'is-pending') }}">
                                        {{ $employee->status === 'approved' ? '已通过' : ($employee->status === 'disabled' ? '已禁用' : '待审核') }}
                                    </span>
                                </td>
                                <td>
                                    <span class="payroll-password-state {{ $employee->password_enabled ? 'is-on' : '' }}">
                                        {{ $employee->password_enabled ? '已开启密码' : '未开启密码' }}
                                    </span>
                                </td>
                                <td>{{ $employee->last_login_at ? \Illuminate\Support\Carbon::parse($employee->last_login_at)->format('Y-m-d H:i') : '暂无' }}</td>
                                <td>
                                    <div class="payroll-actions">
                                        @if ($employee->status === 'pending')
                                            <form method="POST" action="{{ route('admin.payroll.employees.approve', $employee->id) }}" onsubmit="return confirm('确认审核通过该员工并开放工资查询吗？');">
                                                @csrf
                                                <button class="payroll-action-button is-primary" type="submit">审核通过</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.payroll.employees.toggle', $employee->id) }}" onsubmit="return confirm('{{ $employee->status === 'disabled' ? '确认重新启用该员工账户吗？' : '确认禁用该员工账户吗？' }}');">
                                            @csrf
                                            <button class="payroll-action-button" type="submit">{{ $employee->status === 'disabled' ? '启用账户' : '禁用账户' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.payroll.employees.reset-password', $employee->id) }}" onsubmit="return confirm('确认重置该员工的自定义密码吗？重置后需重新开启并设置。');">
                                            @csrf
                                            <button class="payroll-action-button" type="submit">重置密码</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($employees->hasPages())
                    <div class="payroll-pagination">
                        {{ $employees->links() }}
                    </div>
                @endif
            @endif
        </section>
    </div>
@endsection
