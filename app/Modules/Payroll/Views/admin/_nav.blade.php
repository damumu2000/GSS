@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/payroll-admin-nav.css') }}">
    @endpush
@endonce

<div class="settings-nav" role="tablist" aria-label="工资查询分组">
    <a class="settings-nav-button @if(request()->routeIs('admin.payroll.settings')) is-active @endif"
       href="{{ route('admin.payroll.settings') }}"
       role="tab"
       aria-selected="{{ request()->routeIs('admin.payroll.settings') ? 'true' : 'false' }}">
        模块配置
    </a>
    <a class="settings-nav-button @if(request()->routeIs('admin.payroll.batches.*')) is-active @endif"
       href="{{ route('admin.payroll.batches.index') }}"
       role="tab"
       aria-selected="{{ request()->routeIs('admin.payroll.batches.*') ? 'true' : 'false' }}">
        工资信息
    </a>
    <a class="settings-nav-button @if(request()->routeIs('admin.payroll.employees.*')) is-active @endif"
       href="{{ route('admin.payroll.employees.index') }}"
       role="tab"
       aria-selected="{{ request()->routeIs('admin.payroll.employees.*') ? 'true' : 'false' }}">
        员工管理
    </a>
    <a class="settings-nav-button @if(request()->routeIs('admin.payroll.help')) is-active @endif"
       href="{{ route('admin.payroll.help') }}"
       role="tab"
       aria-selected="{{ request()->routeIs('admin.payroll.help') ? 'true' : 'false' }}">
        使用帮助
    </a>
</div>
