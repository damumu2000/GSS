document.addEventListener('DOMContentLoaded', () => {
    const createModal = document.querySelector('[data-template-create-modal]');
    const deleteModal = document.querySelector('[data-template-delete-modal]');

    if (!createModal && !deleteModal) {
        return;
    }

    const createOpenButtons = document.querySelectorAll('[data-open-template-create-modal]');
    const createCloseButtons = document.querySelectorAll('[data-close-template-create-modal]');
    const deleteOpenButtons = document.querySelectorAll('[data-open-template-delete-modal]');
    const deleteCloseButtons = document.querySelectorAll('[data-close-template-delete-modal]');
    const deleteName = document.querySelector('[data-delete-template-name]');
    const deleteKey = document.querySelector('[data-delete-template-key]');
    const deleteConfirmButton = document.querySelector('[data-confirm-template-delete]');

    let targetDeleteFormId = '';

    const syncBodyLock = () => {
        const hasOpenModal = Boolean(
            (createModal && createModal.classList.contains('is-open')) ||
            (deleteModal && deleteModal.classList.contains('is-open'))
        );
        document.body.classList.toggle('theme-modal-open', hasOpenModal);
    };

    const setCreateModalOpen = (isOpen) => {
        if (!createModal) {
            return;
        }
        createModal.classList.toggle('is-open', isOpen);
        syncBodyLock();
    };

    const setDeleteModalOpen = (isOpen) => {
        if (!deleteModal) {
            return;
        }
        deleteModal.classList.toggle('is-open', isOpen);
        if (!isOpen) {
            targetDeleteFormId = '';
        }
        syncBodyLock();
    };

    if (createModal) {
        setCreateModalOpen(createModal.classList.contains('is-open'));
    } else {
        syncBodyLock();
    }

    createOpenButtons.forEach((button) => {
        button.addEventListener('click', () => setCreateModalOpen(true));
    });

    createCloseButtons.forEach((button) => {
        button.addEventListener('click', () => setCreateModalOpen(false));
    });

    deleteOpenButtons.forEach((button) => {
        button.addEventListener('click', () => {
            targetDeleteFormId = button.dataset.deleteFormId || '';
            if (deleteName) {
                deleteName.textContent = button.dataset.templateName || '-';
            }
            if (deleteKey) {
                deleteKey.textContent = button.dataset.templateKey || '-';
            }
            setDeleteModalOpen(true);
        });
    });

    deleteCloseButtons.forEach((button) => {
        button.addEventListener('click', () => setDeleteModalOpen(false));
    });

    if (deleteConfirmButton) {
        deleteConfirmButton.addEventListener('click', () => {
            if (!targetDeleteFormId) {
                return;
            }
            const targetForm = document.getElementById(targetDeleteFormId);
            if (!targetForm) {
                return;
            }
            targetForm.submit();
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }
        if (deleteModal && deleteModal.classList.contains('is-open')) {
            setDeleteModalOpen(false);
            return;
        }
        if (createModal && createModal.classList.contains('is-open')) {
            setCreateModalOpen(false);
        }
    });
});
