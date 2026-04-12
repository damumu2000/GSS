(() => {
    document.querySelectorAll('.js-role-delete-trigger').forEach((button) => {
        button.addEventListener('click', () => {
            const formId = button.dataset.formId;
            const form = formId ? document.getElementById(formId) : null;

            if (!form || typeof window.showConfirmDialog !== 'function') {
                return;
            }

            window.showConfirmDialog({
                title: '确认删除角色？',
                text: '删除后该角色下的用户权限将失效，且操作不可恢复。',
                confirmText: '确定删除',
                onConfirm: () => form.submit(),
            });
        });
    });
})();
