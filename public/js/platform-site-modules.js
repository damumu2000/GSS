document.addEventListener('DOMContentLoaded', () => {
    const createModal = document.querySelector('[data-site-module-create-modal]');
    const openCreateButtons = document.querySelectorAll('[data-open-module-create-modal]');
    const closeCreateButtons = document.querySelectorAll('[data-close-module-create-modal]');

    const openCreateModal = () => {
        if (!(createModal instanceof HTMLElement)) {
            return;
        }
        createModal.hidden = false;
        createModal.classList.add('is-open');
        document.body.classList.add('has-modal-open');
        createModal.querySelector('select[name="module_id"]')?.focus();
    };

    const closeCreateModal = () => {
        if (!(createModal instanceof HTMLElement)) {
            return;
        }
        createModal.classList.remove('is-open');
        createModal.hidden = true;
        document.body.classList.remove('has-modal-open');
    };

    openCreateButtons.forEach((button) => {
        button.addEventListener('click', openCreateModal);
    });

    closeCreateButtons.forEach((button) => {
        button.addEventListener('click', closeCreateModal);
    });

    if (createModal instanceof HTMLElement && createModal.classList.contains('is-open')) {
        document.body.classList.add('has-modal-open');
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && createModal instanceof HTMLElement && !createModal.hidden) {
            closeCreateModal();
        }
    });

    document.querySelectorAll('[data-remove-site-module-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const moduleName = form.getAttribute('data-module-name') || '该模块';
            const text = `确认移除“${moduleName}”吗？移除后会删除该模块在当前站点的业务数据，此操作不可恢复。`;

            if (typeof window.showConfirmDialog === 'function') {
                event.preventDefault();
                window.showConfirmDialog({
                    title: '确认移除模块？',
                    text,
                    confirmText: '确认移除',
                    onConfirm: () => form.submit(),
                });
                return;
            }

            if (!window.confirm(text)) {
                event.preventDefault();
            }
        });
    });
});
