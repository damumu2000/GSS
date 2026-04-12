(() => {
    const image = document.getElementById('guestbook-captcha-image');
    const refresh = document.getElementById('guestbook-refresh-captcha');
    const textarea = document.querySelector('textarea[name="content"][data-textarea-limit]');
    const counter = document.querySelector('[data-textarea-counter]');
    const contentLiveError = document.querySelector('[data-content-live-error]');
    const nameInput = document.querySelector('input[name="name"]');
    const phoneInput = document.querySelector('input[name="phone"]');
    const captchaInput = document.querySelector('input[name="captcha"]');
    const nameLiveError = document.querySelector('[data-name-live-error]');
    const phoneLiveError = document.querySelector('[data-phone-live-error]');
    const captchaLiveError = document.querySelector('[data-captcha-live-error]');
    const form = document.querySelector('form');
    const captchaBase = form?.dataset.captchaBase || '';

    const syncNameValidation = () => {
        if (!nameInput || !nameLiveError) {
            return true;
        }
        const raw = nameInput.value || '';
        const trimmed = raw.trim();
        let message = '';
        if (raw.length > 20) {
            message = '称呼不能超过 20 个字符。';
        } else if (trimmed !== '' && Array.from(trimmed).length < 2) {
            message = '称呼至少需要 2 个字符。';
        } else if (raw.length > 0 && trimmed === '') {
            message = '称呼不能为空白字符，请重新填写。';
        } else if (trimmed !== '' && !/^[A-Za-z\u4E00-\u9FFF]+(?:[·•\s][A-Za-z\u4E00-\u9FFF]+)*$/.test(trimmed)) {
            message = '称呼请填写真实姓名，仅支持中文、英文和间隔号。';
        }
        nameLiveError.textContent = message;
        nameLiveError.hidden = message === '';
        return message === '';
    };

    const syncPhoneValidation = () => {
        if (!phoneInput || !phoneLiveError) {
            return true;
        }
        const raw = phoneInput.value || '';
        const trimmed = raw.replace(/\D+/g, '');
        if (raw !== trimmed) {
            phoneInput.value = trimmed;
        }
        let message = '';
        if (trimmed.length > 11) {
            message = '手机号码应为 11 位数字。';
        } else if (trimmed !== '' && !/^1[3-9]\d{9}$/.test(trimmed)) {
            message = '手机号码格式不正确，请填写 11 位大陆手机号。';
        }
        phoneLiveError.textContent = message;
        phoneLiveError.hidden = message === '';
        return message === '';
    };

    const syncContentValidation = () => {
        if (!textarea || !contentLiveError) {
            return true;
        }

        const raw = textarea.value || '';
        const trimmed = raw.replace(/\s+/g, '');
        let message = '';

        if (raw.length > 1000) {
            message = '留言内容不能超过 1000 字。';
        } else if (raw.length > 0 && trimmed.length === 0) {
            message = '留言内容不能为空白字符，请重新填写。';
        }

        contentLiveError.textContent = message;
        contentLiveError.hidden = message === '';

        return message === '';
    };

    const syncCaptchaValidation = () => {
        if (!captchaInput || !captchaLiveError) {
            return true;
        }
        const raw = captchaInput.value || '';
        const trimmed = raw.trim();
        let message = '';
        if (raw.length > 4) {
            message = '验证码应为 4 位字符，请重新输入。';
        } else if (trimmed !== '' && trimmed.length !== 4) {
            message = '验证码应为 4 位字符，请重新输入。';
        }
        captchaLiveError.textContent = message;
        captchaLiveError.hidden = message === '';
        return message === '';
    };

    const syncCounter = () => {
        if (!textarea || !counter) {
            return;
        }

        const limit = Number.parseInt(textarea.getAttribute('data-textarea-limit') || '1000', 10);
        const length = Array.from(textarea.value || '').length;
        counter.textContent = `${length} / ${limit}`;
        counter.classList.toggle('is-near-limit', length >= Math.max(0, limit - 120) && length <= limit);
        counter.classList.toggle('is-over-limit', length > limit);
    };

    if (nameInput) {
        nameInput.addEventListener('input', () => {
            nameLiveError.hidden = true;
        });
        nameInput.addEventListener('blur', syncNameValidation);
    }

    if (phoneInput) {
        phoneInput.addEventListener('input', () => {
            const raw = phoneInput.value || '';
            const trimmed = raw.replace(/\D+/g, '');
            if (raw !== trimmed) {
                phoneInput.value = trimmed;
            }
            phoneLiveError.hidden = true;
        });
        phoneInput.addEventListener('blur', syncPhoneValidation);
    }

    if (textarea && counter) {
        textarea.addEventListener('input', () => {
            syncCounter();
            contentLiveError.hidden = true;
        });
        textarea.addEventListener('blur', syncContentValidation);
        syncCounter();
    }

    if (captchaInput) {
        captchaInput.addEventListener('input', () => {
            captchaInput.value = captchaInput.value.toUpperCase();
            captchaLiveError.hidden = true;
        });
        captchaInput.addEventListener('blur', syncCaptchaValidation);
    }

    if (form) {
        form.addEventListener('submit', (event) => {
            const valid = [
                syncNameValidation(),
                syncPhoneValidation(),
                syncContentValidation(),
                syncCaptchaValidation(),
            ].every(Boolean);

            if (!valid) {
                event.preventDefault();
                if (!syncNameValidation()) {
                    nameInput?.focus();
                } else if (!syncPhoneValidation()) {
                    phoneInput?.focus();
                } else if (!syncContentValidation()) {
                    textarea?.focus();
                } else if (!syncCaptchaValidation()) {
                    captchaInput?.focus();
                }
            }
        });
    }

    if (image && refresh && captchaBase) {
        refresh.addEventListener('click', () => {
            image.src = `${captchaBase}${captchaBase.includes('?') ? '&' : '?'}t=${Date.now()}`;
        });
    }
})();
