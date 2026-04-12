@extends('layouts.admin')

@section('title', '员工管理 - 功能模块 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 员工管理')

@push('styles')
    @include('admin.site._custom_select_styles')
    <link rel="stylesheet" href="{{ asset('css/payroll-admin-header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/payroll-admin-employees-index.css') }}">
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
                            <th class="u-text-right">操作</th>
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
                                            <form method="POST" action="{{ route('admin.payroll.employees.approve', $employee->id) }}" data-confirm-submit data-confirm-text="确认审核通过该员工并开放工资查询吗？">
                                                @csrf
                                                <button class="payroll-action-button is-primary" type="submit">审核通过</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.payroll.employees.toggle', $employee->id) }}" data-confirm-submit data-confirm-text="{{ $employee->status === 'disabled' ? '确认重新启用该员工账户吗？' : '确认禁用该员工账户吗？' }}">
                                            @csrf
                                            <button class="payroll-action-button" type="submit">{{ $employee->status === 'disabled' ? '启用账户' : '禁用账户' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.payroll.employees.reset-password', $employee->id) }}" data-confirm-submit data-confirm-text="确认重置该员工的自定义密码吗？重置后需重新开启并设置。">
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

@push('scripts')
    <script src="{{ asset('js/payroll-admin-employees-index.js') }}"></script>
@endpush
