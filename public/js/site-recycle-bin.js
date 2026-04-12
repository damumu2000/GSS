document.addEventListener('DOMContentLoaded', () => {
    const toggleAllButton = document.getElementById('recycle-toggle-all');
    const checkboxes = Array.from(document.querySelectorAll('.js-recycle-checkbox'));
    const bulkSubmit = document.getElementById('recycle-bulk-submit');
    const bulkActionInput = document.getElementById('recycle-bulk-action');
    const bulkActionSelect = document.getElementById('recycle_bulk_action_select');
    const bulkForm = document.getElementById('recycle-bulk-form');
    const emptySubmit = document.getElementById('recycle-empty-submit');
    const emptyForm = document.getElementById('recycle-empty-form');

    const syncToggleLabel = () => {
        if (!toggleAllButton) {
            return;
        }

        const allChecked = checkboxes.length > 0 && checkboxes.every((item) => item.checked);
        toggleAllButton.textContent = allChecked ? '取消全选' : '全选';
    };

    toggleAllButton?.addEventListener('click', () => {
        const allChecked = checkboxes.length > 0 && checkboxes.every((item) => item.checked);

        checkboxes.forEach((checkbox) => {
            checkbox.checked = !allChecked;
        });

        syncToggleLabel();
    });

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', syncToggleLabel);
    });

    syncToggleLabel();

    bulkActionSelect?.addEventListener('change', () => {
        if (bulkActionInput) {
            bulkActionInput.value = bulkActionSelect.value;
        }
    });

    bulkSubmit?.addEventListener('click', (event) => {
        event.preventDefault();

        if (!bulkForm || !bulkActionInput) {
            return;
        }

        const checkedCount = checkboxes.filter((checkbox) => checkbox.checked).length;

        if (!checkedCount) {
            showMessage('请先勾选需要处理的内容。');
            return;
        }

        const isDelete = bulkActionInput.value === 'delete';

        window.showConfirmDialog({
            title: isDelete ? '确认批量彻底删除内容？' : '确认批量恢复内容？',
            text: isDelete
                ? `将彻底删除 ${checkedCount} 条内容，删除后无法恢复。`
                : `将恢复 ${checkedCount} 条内容到正常列表。`,
            confirmText: isDelete ? '批量彻底删除' : '批量恢复',
            onConfirm: () => bulkForm.submit(),
        });
    });

    emptySubmit?.addEventListener('click', (event) => {
        event.preventDefault();

        if (!emptyForm) {
            return;
        }

        window.showConfirmDialog({
            title: '确认清空回收站？',
            text: `将彻底删除当前站点回收站中的 ${checkboxes.length} 条内容，删除后无法恢复。`,
            confirmText: '清空回收站',
            onConfirm: () => emptyForm.submit(),
        });
    });

    document.querySelectorAll('[data-recycle-restore-trigger]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();

            const formId = button.dataset.formId;
            const contentTitle = button.dataset.contentTitle || '该内容';
            const formElement = formId ? document.getElementById(formId) : null;

            if (!formElement) {
                return;
            }

            window.showConfirmDialog({
                title: '确认恢复内容？',
                text: `恢复后将重新回到正常内容列表：${contentTitle}`,
                confirmText: '恢复内容',
                onConfirm: () => formElement.submit(),
            });
        });
    });

    document.querySelectorAll('[data-recycle-destroy-trigger]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();

            const formId = button.dataset.formId;
            const contentTitle = button.dataset.contentTitle || '该内容';
            const formElement = formId ? document.getElementById(formId) : null;

            if (!formElement) {
                return;
            }

            window.showConfirmDialog({
                title: '确认彻底删除内容？',
                text: `彻底删除后将无法恢复：${contentTitle}`,
                confirmText: '彻底删除',
                onConfirm: () => formElement.submit(),
            });
        });
    });
});
