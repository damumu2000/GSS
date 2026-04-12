(() => {
    const form = document.querySelector('form');
    if (!form) return;

    const nameInput = form.querySelector('[data-payroll-name]');
    const mobileInput = form.querySelector('[data-payroll-mobile]');
    const nameError = form.querySelector('[data-name-error]');
    const mobileError = form.querySelector('[data-mobile-error]');
    const submitButton = form.querySelector('[data-submit-button]');
    const namePattern = /^[\u4e00-\u9fa5A-Za-z·]{2,20}$/;
    const mobilePattern = /^1[3-9]\d{9}$/;

    const toggleError = (node, message) => {
        if (!node) return;
        node.textContent = message || '';
        node.classList.toggle('is-hidden', !message);
    };

    const validateName = () => {
        const value = (nameInput?.value || '').trim();
        if (value === '') {
            toggleError(nameError, '请填写姓名。');
            return false;
        }
        if (!namePattern.test(value)) {
            toggleError(nameError, '姓名仅支持中文、英文和间隔号，长度 2-20 个字符。');
            return false;
        }
        toggleError(nameError, '');
        return true;
    };

    const validateMobile = () => {
        const value = (mobileInput?.value || '').replace(/\D+/g, '');
        if (mobileInput) mobileInput.value = value;
        if (value === '') {
            toggleError(mobileError, '请填写手机号码。');
            return false;
        }
        if (!mobilePattern.test(value)) {
            toggleError(mobileError, '请填写 11 位大陆手机号。');
            return false;
        }
        toggleError(mobileError, '');
        return true;
    };

    nameInput?.addEventListener('blur', validateName);
    mobileInput?.addEventListener('input', () => {
        mobileInput.value = mobileInput.value.replace(/\D+/g, '').slice(0, 11);
    });
    mobileInput?.addEventListener('blur', validateMobile);

    form.addEventListener('submit', (event) => {
        const valid = validateName() & validateMobile();
        if (!valid) {
            event.preventDefault();
            submitButton?.removeAttribute('disabled');
            return;
        }

        submitButton?.setAttribute('disabled', 'disabled');
    });
})();
