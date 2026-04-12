(() => {
    document.querySelectorAll('.js-platform-role-delete').forEach((button) => {
        button.addEventListener('click', () => {
            const formId = button.dataset.formId;
            const form = formId ? document.getElementById(formId) : null;

            if (!form || typeof window.showConfirmDialog !== 'function') {
                return;
            }

            window.showConfirmDialog({
                title: '确认删除平台角色？',
                text: '删除后该平台角色及其权限绑定将被清除，操作不可恢复。',
                confirmText: '确定删除',
                onConfirm: () => form.submit(),
            });
        });
    });
})();
