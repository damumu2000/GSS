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
