(() => {
    const form = document.querySelector('form');
    if (!form) return;

    const passwordInput = form.querySelector('[data-payroll-password]');
    const passwordError = form.querySelector('[data-password-error]');
    const confirmInput = form.querySelector('[data-payroll-password-confirmation]');
    const confirmError = form.querySelector('[data-password-confirmation-error]');
    const submitButton = form.querySelector('[data-submit-button]');
    const toggle = form.querySelector('[data-password-toggle]');
    const passwordField = form.querySelector('[data-password-field]');
    const confirmationField = form.querySelector('[data-password-confirmation-field]');
    const manageMode = form.dataset.manageMode === '1';

    const toggleError = (node, message) => {
        if (!node) return;
        node.textContent = message || '';
        node.classList.toggle('is-hidden', !message);
    };

    const syncPasswordFieldState = () => {
        if (!manageMode || !toggle) return;
        const enabled = toggle.checked;
        passwordField?.classList.toggle('is-muted', !enabled);
        confirmationField?.classList.toggle('is-muted', !enabled);
        if (passwordInput) passwordInput.disabled = !enabled;
        if (confirmInput) confirmInput.disabled = !enabled;
        if (!enabled) {
            toggleError(passwordError, '');
            toggleError(confirmError, '');
            if (passwordInput) passwordInput.value = '';
            if (confirmInput) confirmInput.value = '';
        }
    };

    const validatePassword = () => {
        const value = passwordInput?.value || '';
        if (!manageMode) {
            if (value.trim() === '') {
                toggleError(passwordError, '请输入密码。');
                return false;
            }
            toggleError(passwordError, '');
            return true;
        }

        if (!toggle?.checked) {
            toggleError(passwordError, '');
            return true;
        }

        if (value.trim() === '') {
            toggleError(passwordError, '开启密码保护后，请输入新的密码。');
            return false;
        }

        if (value.length < 4) {
            toggleError(passwordError, '密码至少需要 4 位。');
            return false;
        }

        toggleError(passwordError, '');
        return true;
    };

    const validateConfirmation = () => {
        if (!manageMode || !confirmInput) return true;
        if (!toggle?.checked) {
            toggleError(confirmError, '');
            return true;
        }

        if ((passwordInput?.value || '') !== confirmInput.value) {
            toggleError(confirmError, '两次输入的密码不一致。');
            return false;
        }

        toggleError(confirmError, '');
        return true;
    };

    syncPasswordFieldState();
    passwordInput?.addEventListener('blur', validatePassword);
    confirmInput?.addEventListener('blur', validateConfirmation);
    toggle?.addEventListener('change', () => {
        syncPasswordFieldState();
    });

    form.addEventListener('submit', (event) => {
        const valid = validatePassword() & validateConfirmation();
        if (!valid) {
            event.preventDefault();
            submitButton?.removeAttribute('disabled');
            return;
        }

        submitButton?.setAttribute('disabled', 'disabled');
    });
})();
