(() => {
    const toggleAllButton = document.getElementById('review-bulk-toggle-all');
    const bulkApproveButton = document.getElementById('review-bulk-approve-button');
    const bulkApproveForm = document.getElementById('review-bulk-approve-form');
    const checkboxes = Array.from(document.querySelectorAll('.review-checkbox'));

    if (toggleAllButton && checkboxes.length > 0) {
        const syncToggleLabel = () => {
            const allChecked = checkboxes.length > 0 && checkboxes.every((checkbox) => checkbox.checked);
            toggleAllButton.textContent = allChecked ? '取消全选' : '全选';
        };

        toggleAllButton.addEventListener('click', () => {
            const allChecked = checkboxes.length > 0 && checkboxes.every((checkbox) => checkbox.checked);
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

    if (bulkApproveButton && bulkApproveForm) {
        bulkApproveButton.addEventListener('click', () => {
            const selected = checkboxes.filter((checkbox) => checkbox.checked);

            if (selected.length === 0) {
                if (typeof window.showMessage === 'function') {
                    window.showMessage('请先勾选至少一篇待审核文章。');
                }
                return;
            }

            if (typeof window.showConfirmDialog !== 'function') {
                bulkApproveForm.submit();
                return;
            }

            window.showConfirmDialog({
                title: '确认批量审核通过？',
                text: `将对已勾选的 ${selected.length} 篇待审核文章执行审核通过。`,
                confirmText: '批量通过',
                onConfirm: () => bulkApproveForm.submit(),
            });
        });
    }

    document.querySelectorAll('[data-approve-form]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = button.closest('form');
            if (!form) {
                return;
            }

            if (typeof window.showConfirmDialog !== 'function') {
                form.submit();
                return;
            }

            window.showConfirmDialog({
                title: '确认审核通过？',
                text: `通过后该文章会正式上线：${button.dataset.approveTitle || ''}`,
                confirmText: '审核通过',
                onConfirm: () => form.submit(),
            });
        });
    });

    const modal = document.getElementById('review-reject-modal');
    const form = document.getElementById('review-reject-form');
    const desc = document.getElementById('review-reject-desc');
    const reasonField = form?.querySelector('textarea[name="reason"]');
    const errorField = document.getElementById('review-reject-error');
    const countField = document.getElementById('review-reject-count');
    const submitButton = document.getElementById('review-reject-submit');
    const rejectActionTemplate = form?.dataset.actionTemplate || '';
    const defaultReturnUrl = form?.dataset.defaultReturnUrl || '';

    if (!modal || !form || !desc) {
        return;
    }

    const syncRejectMeta = () => {
        if (!reasonField || !countField) {
            return;
        }

        const currentLength = reasonField.value.trim().length;
        countField.textContent = String(currentLength);

        if (errorField) {
            errorField.classList.toggle('is-visible', currentLength === 0);
        }
    };

    const closeModal = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        form.reset();
        syncRejectMeta();
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = '确认驳回';
        }
    };

    document.querySelectorAll('[data-open-reject-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            form.action = rejectActionTemplate.replace('__ID__', button.dataset.contentId || '');
            const returnInput = form.querySelector('input[name="return_url"]');
            if (returnInput) {
                returnInput.value = button.dataset.returnUrl || defaultReturnUrl;
            }
            desc.textContent = `请填写驳回原因，作者将在编辑页看到这条信息：${button.dataset.contentTitle || ''}`;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            syncRejectMeta();
            form.querySelector('textarea')?.focus();
        });
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    modal.querySelectorAll('[data-close-reject-modal]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    reasonField?.addEventListener('input', syncRejectMeta);

    form.addEventListener('submit', (event) => {
        if (!reasonField || !submitButton) {
            return;
        }

        if (reasonField.value.trim() === '') {
            event.preventDefault();
            syncRejectMeta();
            reasonField.focus();
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = '正在驳回...';
    });

    syncRejectMeta();
})();
