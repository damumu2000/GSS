(() => {
    document.querySelectorAll('[data-confirm-submit]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm-text') || '确认继续执行当前操作吗？';

            if (typeof window.showConfirmDialog !== 'function') {
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
                return;
            }

            event.preventDefault();
            window.showConfirmDialog({
                title: '确认执行操作？',
                text: message,
                confirmText: '继续',
                onConfirm: () => form.submit(),
            });
        });
    });
})();
