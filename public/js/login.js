(() => {
    const toastConfig = window.CMS_TOAST_CONFIG || {};
    const toastVisibleDuration = Number.isFinite(toastConfig.visibleDuration) ? toastConfig.visibleDuration : 5000;
    const toastExitDuration = Number.isFinite(toastConfig.exitDuration) ? toastConfig.exitDuration : 240;

    function showToast(message, type = 'success') {
        document.querySelectorAll('.toast').forEach((item) => item.remove());

        const toast = document.createElement('div');
        const normalizedType = type === 'error' ? 'error' : 'success';
        toast.className = `toast${normalizedType === 'error' ? ' is-error' : ''}`;
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.innerHTML = `
            <span class="toast-icon">
                ${normalizedType === 'error'
                    ? '<svg viewBox="0 0 24 24"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>'
                    : '<svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>'}
            </span>
            <span class="toast-text"></span>
        `;
        toast.querySelector('.toast-text').textContent = message;
        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.add('is-visible');
        });

        window.setTimeout(() => {
            toast.classList.remove('is-visible');
            window.setTimeout(() => {
                toast.remove();
            }, toastExitDuration);
        }, toastVisibleDuration);
    }

    const body = document.body;
    const rawMessages = body.dataset.loginMessages || '[]';
    let loginMessages = [];

    try {
        loginMessages = JSON.parse(rawMessages);
    } catch (error) {
        loginMessages = [];
    }

    if (Array.isArray(loginMessages) && loginMessages.length > 0) {
        loginMessages.forEach((message, index) => {
            window.setTimeout(() => showToast(message, 'error'), index * 220);
        });
    }

    const passwordHelpModal = document.getElementById('password-help-modal');
    const openPasswordHelp = document.querySelector('[data-open-password-help]');
    const captchaImage = document.getElementById('login-captcha-image');
    const captchaTrigger = document.getElementById('login-captcha-trigger');
    const captchaBase = body.dataset.loginCaptchaBase || '';
    const captchaCheckUrl = body.dataset.loginCaptchaCheck || '';
    const captchaRequired = body.dataset.loginCaptchaRequired === '1';
    const captchaFieldWrap = document.querySelector('.input-wrap.captcha');
    const csrfTokenInput = document.querySelector('input[name="_token"]');
    const csrfToken = csrfTokenInput instanceof HTMLInputElement ? csrfTokenInput.value : '';
    let captchaValidationTimer = null;
    let captchaValidationRequestId = 0;

    function togglePasswordHelpModal(visible) {
        if (!passwordHelpModal) {
            return;
        }

        passwordHelpModal.classList.toggle('is-visible', visible);
        passwordHelpModal.setAttribute('aria-hidden', visible ? 'false' : 'true');
    }

    openPasswordHelp?.addEventListener('click', (event) => {
        event.preventDefault();
        togglePasswordHelpModal(true);
    });

    passwordHelpModal?.querySelectorAll('[data-close-password-help]').forEach((trigger) => {
        trigger.addEventListener('click', () => togglePasswordHelpModal(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            togglePasswordHelpModal(false);
        }
    });

    document.querySelector('[data-login-form]')?.addEventListener('submit', (event) => {
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const captchaInput = document.getElementById('captcha');
        const username = usernameInput instanceof HTMLInputElement ? usernameInput.value.trim() : '';
        const password = passwordInput instanceof HTMLInputElement ? passwordInput.value : '';
        const captcha = captchaInput instanceof HTMLInputElement ? captchaInput.value.trim() : '';
        const captchaIsVisible = captchaInput instanceof HTMLInputElement;

        if (usernameInput instanceof HTMLInputElement) {
            usernameInput.value = username;
        }

        if (username === '') {
            event.preventDefault();
            showToast('请输入账号后再登录。', 'error');
            usernameInput?.focus();
            return;
        }

        if (password.trim() === '') {
            event.preventDefault();
            showToast('请输入密码后再登录。', 'error');
            passwordInput?.focus();
            return;
        }

        if (captchaIsVisible && captcha === '') {
            event.preventDefault();
            showToast('请输入验证码后再登录。', 'error');
            captchaInput?.focus();
        }
    });

    function setCaptchaValidationState(state) {
        if (!(captchaFieldWrap instanceof HTMLElement)) {
            return;
        }

        captchaFieldWrap.classList.toggle('is-valid', state === 'valid');
        captchaFieldWrap.classList.toggle('is-invalid', state === 'invalid');
    }

    function refreshCaptcha() {
        if (!(captchaImage instanceof HTMLImageElement) || captchaBase === '') {
            return;
        }

        captchaImage.src = `${captchaBase}${captchaBase.includes('?') ? '&' : '?'}t=${Date.now()}`;
        setCaptchaValidationState('neutral');
    }

    async function validateCaptcha(value) {
        const normalized = value.trim().toUpperCase();

        if (normalized === '') {
            setCaptchaValidationState('neutral');
            return;
        }

        if (normalized.length !== 4 || captchaCheckUrl === '') {
            setCaptchaValidationState('invalid');
            return;
        }

        const currentRequestId = ++captchaValidationRequestId;

        try {
            const response = await fetch(captchaCheckUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken !== '' ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                body: new URLSearchParams({
                    captcha: normalized,
                }).toString(),
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();

            if (currentRequestId !== captchaValidationRequestId) {
                return;
            }

            setCaptchaValidationState(payload.valid ? 'valid' : 'invalid');
        } catch (error) {
            if (currentRequestId === captchaValidationRequestId) {
                setCaptchaValidationState('neutral');
            }
        }
    }

    const captchaInput = document.getElementById('captcha');
    if (captchaInput instanceof HTMLInputElement) {
        captchaInput.addEventListener('input', () => {
            captchaInput.value = captchaInput.value.toUpperCase().slice(0, 4);
            window.clearTimeout(captchaValidationTimer);
            captchaValidationTimer = window.setTimeout(() => {
                validateCaptcha(captchaInput.value);
            }, 220);
        });

        captchaInput.addEventListener('blur', () => {
            validateCaptcha(captchaInput.value);
        });
    }

    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const wrapper = button.closest('.input-wrap');
            const input = wrapper?.querySelector('input');
            const openIcon = button.querySelector('[data-eye-open]');
            const closedIcon = button.querySelector('[data-eye-closed]');

            if (!(input instanceof HTMLInputElement) || !openIcon || !closedIcon) {
                return;
            }

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            openIcon.hidden = isPassword;
            closedIcon.hidden = !isPassword;
        });
    });

    captchaImage?.addEventListener('click', refreshCaptcha);
    captchaTrigger?.addEventListener('click', refreshCaptcha);
    captchaTrigger?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            refreshCaptcha();
        }
    });
})();
