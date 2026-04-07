@extends('layouts.admin')

@section('title', '新增工资批次 - 功能模块 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 工资信息 / 新增工资批次')

@push('styles')
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
        .payroll-create-shell {
            display: grid;
            gap: 18px;
            width: 100%;
        }
        .payroll-create-errors {
            padding: 16px 18px;
            border: 1px solid rgba(220, 38, 38, 0.14);
            border-radius: 18px;
            background: #fff6f5;
            color: #b42318;
        }
        .payroll-create-errors-title {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
        }
        .payroll-create-errors-list {
            margin: 10px 0 0;
            padding-left: 18px;
            font-size: 13px;
            line-height: 1.8;
        }
        .payroll-create-card {
            padding: 34px 36px 36px;
            border: 1px solid #e8edf4;
            border-radius: 28px;
            background:
                radial-gradient(circle at top left, var(--primary-soft-strong), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.05);
        }
        .payroll-create-card-title {
            margin: 0;
            color: #1d2939;
            font-size: 24px;
            line-height: 1.3;
            font-weight: 800;
        }
        .payroll-create-card-desc {
            margin-top: 10px;
            color: #667085;
            font-size: 14px;
            line-height: 1.8;
        }
        .payroll-create-form {
            display: grid;
            grid-template-columns: minmax(320px, 420px);
            gap: 18px;
            margin-top: 36px;
            align-items: start;
        }
        .payroll-create-field {
            display: grid;
            gap: 12px;
        }
        .payroll-create-field-title {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #344054;
            font-size: 15px;
            font-weight: 700;
        }
        .payroll-create-pill {
            display: inline-flex;
            align-items: center;
            height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: #667085;
            font-size: 12px;
            font-weight: 700;
        }
        .payroll-create-month-picker {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 12px;
            align-items: center;
        }
        .payroll-create-month-picker.is-invalid .payroll-month-select-trigger {
            border-color: #f97066;
            box-shadow: 0 0 0 4px rgba(240, 68, 56, 0.10);
            background: #fff8f7;
        }
        .payroll-create-month-separator {
            color: #98a2b3;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.08em;
        }
        .payroll-month-select {
            position: relative;
        }
        .payroll-month-select-native {
            position: absolute;
            inset: 0;
            opacity: 0;
            pointer-events: none;
        }
        .payroll-month-select-trigger {
            width: 100%;
            height: 58px;
            padding: 0 48px 0 18px;
            border: 1px solid #d9e2ec;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            color: #1d2939;
            font: inherit;
            font-size: 17px;
            font-weight: 600;
            line-height: 58px;
            text-align: left;
            cursor: pointer;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease, background-color 0.18s ease;
            position: relative;
        }
        .payroll-month-select-trigger:hover {
            border-color: #c8d3e1;
            transform: translateY(-1px);
        }
        .payroll-month-select.is-open .payroll-month-select-trigger,
        .payroll-month-select-trigger:focus-visible {
            outline: none;
            border-color: var(--primary-border-soft);
            box-shadow: 0 0 0 4px var(--primary-soft-strong);
            background: #fff;
        }
        .payroll-month-select-trigger::after {
            content: "";
            position: absolute;
            right: 18px;
            top: 50%;
            width: 12px;
            height: 12px;
            border-right: 2px solid var(--primary);
            border-bottom: 2px solid var(--primary);
            transform: translateY(-55%) rotate(45deg);
            transition: transform 0.18s ease;
            opacity: 0.82;
        }
        .payroll-month-select.is-open .payroll-month-select-trigger::after {
            transform: translateY(-40%) rotate(225deg);
        }
        .payroll-month-select-panel {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            padding: 6px;
            border: 1px solid #e6edf5;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 18px 32px rgba(15, 23, 42, 0.12);
            opacity: 0;
            pointer-events: none;
            transform: translateY(-4px) scale(0.97);
            transform-origin: top center;
            transition: opacity 0.16s ease, transform 0.16s ease;
            z-index: 40;
            max-height: 260px;
            overflow: auto;
        }
        .payroll-month-select.is-open .payroll-month-select-panel {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0) scale(1);
        }
        .payroll-month-select-option {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 12px 14px;
            border: 0;
            border-radius: 12px;
            background: transparent;
            color: #475467;
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.4;
            text-align: left;
            cursor: pointer;
        }
        .payroll-month-select-option:hover,
        .payroll-month-select-option.is-active {
            background: var(--primary-soft);
            color: var(--primary-dark);
        }
        .payroll-month-select-option-status {
            display: none;
            align-items: center;
            justify-content: center;
            height: 24px;
            padding: 0 10px;
            border-radius: 999px;
            background: #fff;
            color: #98a2b3;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            flex-shrink: 0;
        }
        .payroll-month-select-option.is-disabled {
            background: #f8fafc;
            color: #98a2b3;
            cursor: not-allowed;
            opacity: 0.9;
        }
        .payroll-month-select-option.is-disabled:hover {
            background: #f8fafc;
            color: #98a2b3;
        }
        .payroll-month-select-option.is-disabled .payroll-month-select-option-status {
            display: inline-flex;
        }
        .payroll-month-select-check {
            width: 14px;
            height: 14px;
            stroke: var(--primary);
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            opacity: 0;
            flex-shrink: 0;
        }
        .payroll-month-select-option.is-active .payroll-month-select-check {
            opacity: 1;
        }
        .payroll-month-select-option.is-disabled .payroll-month-select-check {
            display: none;
        }
        .payroll-create-error {
            min-height: 18px;
            color: #d92d20;
            font-size: 12px;
            line-height: 1.5;
        }
        .payroll-create-hint {
            display: none;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid #fde7c7;
            border-radius: 16px;
            background: #fffbf5;
            color: #b54708;
            font-size: 13px;
            line-height: 1.7;
        }
        .payroll-create-hint.is-visible {
            display: flex;
        }
        .payroll-create-hint-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 34px;
            padding: 0 14px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid #fed7aa;
            color: #b54708;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            flex-shrink: 0;
        }
        .payroll-create-actions {
            display: flex;
            justify-content: flex-start;
            margin-top: 22px;
        }
        .payroll-create-actions .button {
            min-width: 124px;
            height: 48px;
            border-radius: 999px;
            font-size: 15px;
            font-weight: 700;
            box-shadow: 0 10px 24px rgba(0, 80, 179, 0.16);
        }
        @media (max-width: 960px) {
            .payroll-create-card {
                padding: 24px 20px 22px;
            }
            .payroll-create-form {
                grid-template-columns: 1fr;
            }
            .payroll-create-month-picker {
                grid-template-columns: 1fr;
            }
            .payroll-create-month-separator {
                display: none;
            }
        }
    </style>
@endpush

@section('content')
    <form method="POST" action="{{ route('admin.payroll.batches.store') }}">
        @csrf

        <div class="payroll-header">
            <div>
                <h1 class="payroll-header-title">新增工资批次</h1>
                <div class="payroll-header-desc">创建月份批次后，再进入下一步上传工资表和绩效表。</div>
            </div>
            <div class="page-header-actions">
                <a class="button secondary" href="{{ route('admin.payroll.batches.index') }}">返回列表</a>
            </div>
        </div>

        @include('payroll::admin._nav')

        <div class="payroll-create-shell">
            @if ($errors->any())
                <section class="payroll-create-errors">
                    <h2 class="payroll-create-errors-title">请先修正以下内容</h2>
                    <ul class="payroll-create-errors-list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="payroll-create-card">
                <h2 class="payroll-create-card-title">基础信息</h2>
                <div class="payroll-create-card-desc">这里只需要选择工资月份，创建后即可继续上传工资表和绩效表。</div>

                <div class="payroll-create-form">
                    <label class="payroll-create-field">
                        <span class="payroll-create-field-title">
                            工资月份
                            <span class="payroll-create-pill">必填</span>
                        </span>
                        <input type="hidden" name="month_key" id="payroll-month-key" value="{{ old('month_key') }}">
                        <span class="payroll-create-month-picker @error('month_key') is-invalid @enderror" id="payroll-month-picker">
                            <span class="payroll-month-select" data-payroll-select>
                                <select class="payroll-month-select-native" id="payroll-month-year" aria-label="选择年份">
                                    @php
                                        $oldMonthKey = old('month_key');
                                        $selectedYear = now()->year;
                                        $selectedMonth = now()->month;
                                        if (is_string($oldMonthKey) && preg_match('/^(\d{4})-(\d{2})$/', $oldMonthKey, $monthMatches)) {
                                            $selectedYear = (int) $monthMatches[1];
                                            $selectedMonth = (int) $monthMatches[2];
                                        }
                                    @endphp
                                    @for ($year = now()->year; $year <= now()->year + 1; $year++)
                                        <option value="{{ $year }}" @selected($selectedYear === $year)>{{ $year }} 年</option>
                                    @endfor
                                </select>
                                <button class="payroll-month-select-trigger" type="button" data-payroll-select-trigger aria-haspopup="listbox" aria-expanded="false">
                                    <span data-payroll-select-label>{{ $selectedYear }} 年</span>
                                </button>
                                <div class="payroll-month-select-panel" role="listbox" data-payroll-select-panel>
                                    @for ($year = now()->year; $year <= now()->year + 1; $year++)
                                        <button class="payroll-month-select-option @if($selectedYear === $year) is-active @endif" type="button" data-payroll-select-option data-value="{{ $year }}">
                                            <span>{{ $year }} 年</span>
                                            <span class="payroll-month-select-option-status">已添加</span>
                                            <svg class="payroll-month-select-check" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5 6.5 11.5 12.5 4.5"/></svg>
                                        </button>
                                    @endfor
                                </div>
                            </span>
                            <span class="payroll-create-month-separator">·</span>
                            <span class="payroll-month-select" data-payroll-select>
                                <select class="payroll-month-select-native" id="payroll-month-month" aria-label="选择月份">
                                    @for ($month = 1; $month <= 12; $month++)
                                        <option value="{{ str_pad((string) $month, 2, '0', STR_PAD_LEFT) }}" @selected($selectedMonth === $month)>{{ $month }} 月</option>
                                    @endfor
                                </select>
                                <button class="payroll-month-select-trigger" type="button" data-payroll-select-trigger aria-haspopup="listbox" aria-expanded="false">
                                    <span data-payroll-select-label>{{ $selectedMonth }} 月</span>
                                </button>
                                <div class="payroll-month-select-panel" role="listbox" data-payroll-select-panel>
                                    @for ($month = 1; $month <= 12; $month++)
                                        <button class="payroll-month-select-option @if($selectedMonth === $month) is-active @endif" type="button" data-payroll-select-option data-value="{{ str_pad((string) $month, 2, '0', STR_PAD_LEFT) }}">
                                            <span>{{ $month }} 月</span>
                                            <span class="payroll-month-select-option-status">已添加</span>
                                            <svg class="payroll-month-select-check" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5 6.5 11.5 12.5 4.5"/></svg>
                                        </button>
                                    @endfor
                                </div>
                            </span>
                        </span>
                        <div class="payroll-create-error" data-error-for="month_key">@error('month_key'){{ $message }}@enderror</div>
                        <div class="payroll-create-hint" id="payroll-month-existing-hint">
                            <span id="payroll-month-existing-text">该月份批次已存在，请前往编辑页重新上传工资表或绩效表。</span>
                            <a class="payroll-create-hint-link" id="payroll-month-existing-link" href="#">前往编辑该月份</a>
                        </div>
                    </label>

                </div>

                <div class="payroll-create-actions">
                    <button class="button" type="submit">创建批次</button>
                </div>
            </section>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('form[action="{{ route('admin.payroll.batches.store') }}"]');
            if (!form) {
                return;
            }

            const monthInput = document.getElementById('payroll-month-key');
            const monthPicker = document.getElementById('payroll-month-picker');
            const yearSelect = document.getElementById('payroll-month-year');
            const monthSelect = document.getElementById('payroll-month-month');
            const existingHint = document.getElementById('payroll-month-existing-hint');
            const existingHintLink = document.getElementById('payroll-month-existing-link');
            const existingBatchLinks = @json($existingBatchLinks ?? []);
            const existingBatchMap = @json($existingBatchMap ?? []);
            const hasOldMonth = @json((bool) old('month_key'));
            const messages = {
                month_key: {
                    required: '请选择工资月份。',
                    invalid: '工资月份格式不正确。',
                    existing: '该月份批次已存在，请前往编辑页重新上传工资表或绩效表。',
                },
            };

            const setFieldError = (name, message = '') => {
                const field = form.querySelector(`[name="${name}"]`);
                const errorNode = form.querySelector(`[data-error-for="${name}"]`);
                if (!field || !errorNode) {
                    return;
                }

                monthPicker?.classList.toggle('is-invalid', message !== '');
                errorNode.textContent = message;
            };
            const setExistingHint = (monthKey = '') => {
                if (!existingHint || !existingHintLink) {
                    return;
                }

                const href = existingBatchLinks[monthKey] ?? '';
                const visible = href !== '';
                existingHint.classList.toggle('is-visible', visible);
                existingHintLink.href = href || '#';
                existingHintLink.tabIndex = visible ? 0 : -1;
            };

            const syncMonthValue = () => {
                if (!yearSelect || !monthSelect || !monthInput) {
                    return '';
                }
                const value = `${yearSelect.value}-${monthSelect.value}`;
                monthInput.value = value;
                return value;
            };

            const validateMonth = () => {
                const value = syncMonthValue().trim();
                if (value === '') {
                    setExistingHint();
                    setFieldError('month_key', messages.month_key.required);
                    return false;
                }
                if (!/^\d{4}-\d{2}$/.test(value)) {
                    setExistingHint();
                    setFieldError('month_key', messages.month_key.invalid);
                    return false;
                }
                if (existingBatchMap[value]) {
                    setExistingHint(value);
                    setFieldError('month_key', messages.month_key.existing);
                    return false;
                }

                setExistingHint();
                setFieldError('month_key');
                return true;
            };
            const updateMonthAvailability = () => {
                if (!yearSelect || !monthSelect) {
                    return;
                }

                const selectedYear = yearSelect.value;
                const monthOptions = document.querySelectorAll('#payroll-month-month')
                    ? monthSelect.closest('.payroll-month-select')?.querySelectorAll('[data-payroll-select-option]')
                    : [];

                monthOptions?.forEach((optionButton) => {
                    const monthValue = optionButton.dataset.value ?? '';
                    const monthKey = `${selectedYear}-${monthValue}`;
                    const disabled = Boolean(existingBatchMap[monthKey]);
                    optionButton.classList.toggle('is-disabled', disabled);
                    optionButton.setAttribute('aria-disabled', disabled ? 'true' : 'false');
                });
            };
            const selectFirstAvailableMonth = () => {
                if (!yearSelect || !monthSelect) {
                    return;
                }

                const currentValue = `${yearSelect.value}-${monthSelect.value}`;
                if (!existingBatchMap[currentValue]) {
                    return;
                }

                const yearOptions = Array.from(yearSelect.options).map((option) => option.value);
                const monthOptions = Array.from(monthSelect.options).map((option) => option.value);
                const currentYear = yearSelect.value;
                const currentMonth = monthSelect.value;

                const currentYearIndex = yearOptions.indexOf(currentYear);
                const currentMonthIndex = monthOptions.indexOf(currentMonth);

                const searchPlans = [];

                if (currentYearIndex !== -1) {
                    searchPlans.push({
                        year: currentYear,
                        months: monthOptions.slice(Math.max(currentMonthIndex + 1, 0)),
                    });
                }

                yearOptions.forEach((yearValue, index) => {
                    if (index === currentYearIndex) {
                        return;
                    }

                    searchPlans.push({
                        year: yearValue,
                        months: monthOptions,
                    });
                });

                if (currentYearIndex !== -1) {
                    searchPlans.push({
                        year: currentYear,
                        months: monthOptions.slice(0, Math.max(currentMonthIndex, 0)),
                    });
                }

                for (const plan of searchPlans) {
                    for (const monthValue of plan.months) {
                        const monthKey = `${plan.year}-${monthValue}`;
                        if (!existingBatchMap[monthKey]) {
                            yearSelect.value = plan.year;
                            monthSelect.value = monthValue;
                            return;
                        }
                    }
                }
            };
            const refreshSelectUI = (selectRoot) => {
                const nativeSelect = selectRoot.querySelector('.payroll-month-select-native');
                const label = selectRoot.querySelector('[data-payroll-select-label]');
                const options = selectRoot.querySelectorAll('[data-payroll-select-option]');

                if (!nativeSelect || !label || options.length === 0) {
                    return;
                }

                const selected = nativeSelect.options[nativeSelect.selectedIndex];
                label.textContent = selected ? selected.textContent : '请选择';
                options.forEach((item) => {
                    item.classList.toggle('is-active', item.dataset.value === nativeSelect.value);
                });
            };

            if (!hasOldMonth) {
                selectFirstAvailableMonth();
            }
            syncMonthValue();
            updateMonthAvailability();
            yearSelect?.addEventListener('change', validateMonth);
            monthSelect?.addEventListener('change', validateMonth);
            yearSelect?.addEventListener('blur', validateMonth);
            monthSelect?.addEventListener('blur', validateMonth);

            document.querySelectorAll('[data-payroll-select]').forEach((selectRoot) => {
                const nativeSelect = selectRoot.querySelector('.payroll-month-select-native');
                const trigger = selectRoot.querySelector('[data-payroll-select-trigger]');
                const label = selectRoot.querySelector('[data-payroll-select-label]');
                const options = selectRoot.querySelectorAll('[data-payroll-select-option]');

                if (!nativeSelect || !trigger || !label || options.length === 0) {
                    return;
                }

                const syncLabel = () => {
                    refreshSelectUI(selectRoot);
                };

                const closeSelect = () => {
                    selectRoot.classList.remove('is-open');
                    trigger.setAttribute('aria-expanded', 'false');
                };

                trigger.addEventListener('click', () => {
                    const willOpen = !selectRoot.classList.contains('is-open');
                    document.querySelectorAll('[data-payroll-select].is-open').forEach((opened) => {
                        opened.classList.remove('is-open');
                        opened.querySelector('[data-payroll-select-trigger]')?.setAttribute('aria-expanded', 'false');
                    });
                    if (willOpen) {
                        selectRoot.classList.add('is-open');
                        trigger.setAttribute('aria-expanded', 'true');
                    }
                });

                options.forEach((optionButton) => {
                    optionButton.addEventListener('click', () => {
                        if (optionButton.classList.contains('is-disabled')) {
                            return;
                        }
                        const value = optionButton.dataset.value ?? '';
                        nativeSelect.value = value;
                        syncLabel();
                        closeSelect();
                        if (nativeSelect === yearSelect) {
                            updateMonthAvailability();
                        }
                        nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                });

                document.addEventListener('click', (event) => {
                    if (!selectRoot.contains(event.target)) {
                        closeSelect();
                    }
                });

                syncLabel();
            });

            validateMonth();

            form.addEventListener('submit', (event) => {
                if (validateMonth()) {
                    return;
                }

                event.preventDefault();
                yearSelect?.focus();
            });
        })();
    </script>
@endpush
