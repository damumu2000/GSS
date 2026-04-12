(() => {
    const form = document.getElementById('payroll-batch-edit-form');
    if (!form) {
        return;
    }

    const submitButton = document.getElementById('payroll-batch-submit');
    const submitStatus = document.getElementById('payroll-batch-submit-status');
    const uploadInputs = Array.from(form.querySelectorAll('[data-upload-input]'));
    const exportButtons = Array.from(document.querySelectorAll('.payroll-export-button'));

    form.querySelectorAll('[data-upload-card]').forEach((card) => {
        const input = card.querySelector('[data-upload-input]');
        const placeholder = card.querySelector('[data-upload-placeholder]');
        const selected = card.querySelector('[data-upload-selected]');

        if (!input || !placeholder || !selected) {
            return;
        }

        input.addEventListener('change', () => {
            const file = input.files && input.files[0] ? input.files[0] : null;
            const hasFile = Boolean(file);
            card.classList.toggle('is-selected', hasFile);
            selected.classList.toggle('is-empty', !hasFile);

            if (!hasFile) {
                placeholder.textContent = input.name === 'salary_file'
                    ? '选择新的工资表文件（xls / xlsx）'
                    : '选择新的绩效表文件（xls / xlsx）';
                selected.textContent = '尚未选择新文件';
                return;
            }

            placeholder.textContent = '已选择新文件，保存后将重新解析';
            selected.textContent = `已选择：${file.name}`;
        });
    });

    form.addEventListener('submit', (event) => {
        const hasNewFile = uploadInputs.some((input) => input.files && input.files.length > 0);

        if (!hasNewFile) {
            event.preventDefault();
            submitStatus?.classList.add('is-active', 'is-warning');
            submitStatus?.classList.remove('is-success');
            const messageNode = submitStatus?.querySelector('span:last-child');
            if (messageNode) {
                messageNode.textContent = '请先选择新的工资表或绩效表，再进行解析。';
            }
            return;
        }

        submitStatus?.classList.remove('is-warning');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = '解析中…';
        }
        const messageNode = submitStatus?.querySelector('span:last-child');
        if (messageNode) {
            messageNode.textContent = '正在解析表格，请稍候…';
        }
        submitStatus?.classList.add('is-active');
    });

    exportButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (button.getAttribute('aria-busy') === 'true') {
                return;
            }

            button.dataset.originalText = button.textContent?.trim() || '';
            button.textContent = button.dataset.loadingText || '生成中…';
            button.setAttribute('aria-busy', 'true');

            window.setTimeout(() => {
                button.textContent = button.dataset.originalText || '导出';
                button.setAttribute('aria-busy', 'false');
            }, 1800);
        });
    });
})();
