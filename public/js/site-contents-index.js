(() => {
    const toggleAllButton = document.getElementById('content-bulk-toggle-all');
    const checkboxes = Array.from(document.querySelectorAll('.content-checkbox'));
    const bulkButton = document.querySelector('.js-bulk-submit');
    const bulkForm = document.getElementById('content-bulk-form');
    const tbody = document.querySelector('tbody[data-content-reorder-url]');
    const reorderUrl = tbody?.dataset.contentReorderUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    if (toggleAllButton && checkboxes.length > 0) {
        const syncToggleLabel = () => {
            const allChecked = checkboxes.every((checkbox) => checkbox.checked);
            toggleAllButton.textContent = allChecked ? '取消全选' : '全选';
        };

        toggleAllButton.addEventListener('click', () => {
            const allChecked = checkboxes.every((checkbox) => checkbox.checked);
            checkboxes.forEach((checkbox) => {
                checkbox.checked = !allChecked;
            });
            syncToggleLabel();
        });

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', syncToggleLabel);
        });

        syncToggleLabel();
    }

    if (bulkButton && bulkForm) {
        bulkButton.addEventListener('click', () => {
            if (typeof window.showConfirmDialog !== 'function') {
                bulkForm.submit();
                return;
            }

            window.showConfirmDialog({
                title: '确认执行批量操作？',
                text: '批量操作将立即对已勾选内容生效，请确认后继续。',
                confirmText: '确认执行',
                onConfirm: () => bulkForm.submit(),
            });
        });
    }

    document.querySelectorAll('.js-content-delete').forEach((button) => {
        button.addEventListener('click', () => {
            const formId = button.dataset.formId;
            const form = formId ? document.getElementById(formId) : null;

            if (!form || typeof window.showConfirmDialog !== 'function') {
                return;
            }

            window.showConfirmDialog({
                title: '确认删除这条记录？',
                text: '删除后内容会进入回收站，可在回收站中继续处理。',
                confirmText: '确认删除',
                onConfirm: () => form.submit(),
            });
        });
    });

    if (tbody && reorderUrl && window.Sortable) {
        const getVisibleOrderedIds = () => Array.from(tbody.querySelectorAll('tr[data-content-row]'))
            .map((row) => Number(row.dataset.contentId));

        const saveReorder = async (orderedIds) => {
            const response = await fetch(reorderUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ ordered_ids: orderedIds }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload.message || '文章排序保存失败，请稍后重试。');
            }

            return payload;
        };

        Sortable.create(tbody, {
            animation: 180,
            handle: '.content-drag-handle',
            draggable: 'tr[data-content-row]',
            ghostClass: 'content-row-ghost',
            chosenClass: 'content-row-chosen',
            dragClass: 'content-row-drag',
            async onEnd(event) {
                const row = event.item;
                const beforeIds = event.oldIndex === event.newIndex ? null : true;

                if (!beforeIds) {
                    return;
                }

                const orderedIds = getVisibleOrderedIds();
                row.classList.add('is-saving');

                try {
                    const payload = await saveReorder(orderedIds);
                    window.showMessage?.(payload.message || '文章排序已保存。');
                } catch (error) {
                    window.showMessage?.(error.message || '文章排序保存失败，页面将刷新恢复。', 'error');
                    window.setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } finally {
                    row.classList.remove('is-saving');
                }
            },
        });
    }
})();
