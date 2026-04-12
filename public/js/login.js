(() => {
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
            }, 240);
        }, 3000);
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
        const username = usernameInput instanceof HTMLInputElement ? usernameInput.value.trim() : '';
        const password = passwordInput instanceof HTMLInputElement ? passwordInput.value : '';

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
        }
    });

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
})();
