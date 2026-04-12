(() => {
    const form = document.querySelector('[data-payroll-batch-create-form]');
    if (!form) {
        return;
    }

    const monthInput = document.getElementById('payroll-month-key');
    const monthPicker = document.getElementById('payroll-month-picker');
    const yearSelect = document.getElementById('payroll-month-year');
    const monthSelect = document.getElementById('payroll-month-month');
    const existingHint = document.getElementById('payroll-month-existing-hint');
    const existingHintLink = document.getElementById('payroll-month-existing-link');
    const existingBatchLinks = JSON.parse(form.dataset.existingBatchLinks || '{}');
    const existingBatchMap = JSON.parse(form.dataset.existingBatchMap || '{}');
    const hasOldMonth = form.dataset.hasOldMonth === '1';
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
        const monthOptions = monthSelect.closest('.payroll-month-select')?.querySelectorAll('[data-payroll-select-option]') || [];

        monthOptions.forEach((optionButton) => {
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
