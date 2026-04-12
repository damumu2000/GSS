(() => {
    const textareas = Array.from(document.querySelectorAll('textarea[data-textarea-limit]'));
    if (textareas.length === 0) {
        return;
    }

    const syncCounter = (textarea) => {
        const counter = textarea.parentElement?.querySelector('[data-textarea-counter]');
        if (!counter) {
            return;
        }

        const limit = Number.parseInt(textarea.getAttribute('data-textarea-limit') || '1000', 10);
        const length = Array.from(textarea.value || '').length;
        counter.textContent = `${length} / ${limit}`;
        counter.classList.toggle('is-near-limit', length >= Math.max(0, limit - 120) && length <= limit);
        counter.classList.toggle('is-over-limit', length > limit);
    };

    textareas.forEach((textarea) => {
        textarea.addEventListener('input', () => syncCounter(textarea));
        syncCounter(textarea);
    });

    document.querySelectorAll('[data-toggle-editor]').forEach((button) => {
        const editLabel = button.getAttribute('data-label-edit') || '编辑';
        const cancelLabel = button.getAttribute('data-label-cancel') || '取消编辑';
        const field = button.getAttribute('data-toggle-editor');
        const display = document.querySelector(`[data-editor-display="${field}"]`);
        const editor = document.querySelector(`[data-editor-field="${field}"]`);

        const syncButtonState = () => {
            if (!editor) {
                return;
            }

            const editing = !editor.hidden;
            button.textContent = editing ? cancelLabel : editLabel;
        };

        syncButtonState();

        button.addEventListener('click', () => {
            if (!editor) {
                return;
            }

            const editing = !editor.hidden;

            if (editing) {
                editor.hidden = true;
                if (display) {
                    display.hidden = false;
                }
                syncButtonState();
                return;
            }

            if (display) {
                display.hidden = true;
            }

            editor.hidden = false;
            const textarea = editor.querySelector('textarea');
            if (textarea) {
                textarea.focus();
                textarea.setSelectionRange(textarea.value.length, textarea.value.length);
            }
            syncButtonState();
        });
    });
})();
