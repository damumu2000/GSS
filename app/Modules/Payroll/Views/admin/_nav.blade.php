@once
    @push('styles')
        <style>
            .settings-nav {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
                padding: 8px;
                margin-bottom: 18px;
                border: 1px solid #eef2f6;
                border-radius: 16px;
                background: #ffffff;
                box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
                width: fit-content;
                max-width: 100%;
            }

            .settings-nav-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 40px;
                padding: 0 18px;
                border: 1px solid transparent;
                border-radius: 12px;
                background: transparent;
                color: #6b7280;
                font-size: 14px;
                font-weight: 700;
                text-decoration: none;
                transition: background 0.18s ease, color 0.18s ease, border-color 0.18s ease;
            }

            .settings-nav-button:hover {
                background: #f8fafc;
                color: #374151;
            }

            .settings-nav-button.is-active {
                background: rgba(0, 80, 179, 0.08);
                border-color: rgba(0, 80, 179, 0.12);
                color: var(--primary, #0050b3);
            }
        </style>
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
