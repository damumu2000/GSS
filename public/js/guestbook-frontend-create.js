(() => {
    const image = document.getElementById('guestbook-captcha-image');
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
    const captchaVerifyUrl = form?.dataset.captchaVerify || '';
    const csrfToken = form?.querySelector('input[name="_token"]')?.value || '';
    let captchaVerifySerial = 0;
    let captchaVerifiedValue = '';
    const setContentFieldState = (state) => {
        if (!textarea) {
            return;
        }
        textarea.classList.remove('is-error', 'is-valid');
        if (state === 'error') {
            textarea.classList.add('is-error');
        }
        if (state === 'valid') {
            textarea.classList.add('is-valid');
        }
    };

    const setCaptchaMessage = (message, valid = false) => {
        if (!captchaLiveError) {
            return;
        }
        captchaLiveError.textContent = message;
        captchaLiveError.hidden = message === '';
        captchaLiveError.classList.toggle('is-valid', valid && message !== '');
    };

    const refreshCaptcha = () => {
        if (!image || !captchaBase) {
            return;
        }
        image.src = `${captchaBase}${captchaBase.includes('?') ? '&' : '?'}t=${Date.now()}`;
        captchaVerifiedValue = '';
        captchaVerifySerial += 1;
        setCaptchaMessage('');
    };

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
        if (message !== '') {
            setContentFieldState('error');
        } else if (raw.length > 0) {
            setContentFieldState('valid');
            contentLiveError.textContent = '输入正确';
            contentLiveError.hidden = false;
            contentLiveError.classList.add('is-valid');
            return true;
        } else {
            setContentFieldState('');
        }
        contentLiveError.classList.remove('is-valid');

        return message === '';
    };

    const syncCaptchaValidation = () => {
        if (!captchaInput || !captchaLiveError) {
            return true;
        }
        const raw = captchaInput.value || '';
        const trimmed = raw.trim();
        let message = '';
        let valid = true;
        if (raw.length > 4) {
            message = '验证码应为 4 位字符，请重新输入。';
            valid = false;
        } else if (trimmed !== '' && trimmed.length !== 4) {
            message = '验证码应为 4 位字符，请重新输入。';
            valid = false;
        }
        setCaptchaMessage(message, false);
        return valid;
    };

    const verifyCaptcha = async (captcha) => {
        if (!captchaVerifyUrl || !captchaLiveError) {
            return false;
        }
        if (!csrfToken) {
            setCaptchaMessage('验证码校验失败，请刷新页面后重试。', false);
            return false;
        }

        const serial = ++captchaVerifySerial;
        const verifyPayload = new URLSearchParams();
        verifyPayload.set('captcha', captcha);

        try {
            const response = await fetch(captchaVerifyUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: verifyPayload.toString(),
                credentials: 'same-origin',
            });

            const payload = await response.json();
            if (serial !== captchaVerifySerial) {
                return false;
            }

            if (!response.ok) {
                setCaptchaMessage(payload?.message || '验证码校验失败，请稍后重试。', false);
                return false;
            }

            if (payload?.valid) {
                captchaVerifiedValue = captcha;
                setCaptchaMessage('输入正确', true);
                return true;
            }

            captchaVerifiedValue = '';
            setCaptchaMessage(payload?.message || '验证码不正确，请重新输入。', false);
            return false;
        } catch (error) {
            if (serial === captchaVerifySerial) {
                setCaptchaMessage('验证码校验失败，请稍后重试。', false);
            }
            return false;
        }
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
            syncContentValidation();
        });
        textarea.addEventListener('blur', syncContentValidation);
        syncCounter();
    }

    if (captchaInput) {
        captchaInput.addEventListener('input', async () => {
            captchaInput.value = captchaInput.value.toUpperCase().slice(0, 4);
            const trimmed = (captchaInput.value || '').trim().toUpperCase();
            if (captchaVerifiedValue && captchaVerifiedValue !== trimmed) {
                captchaVerifiedValue = '';
            }

            const formatValid = syncCaptchaValidation();
            if (!formatValid) {
                return;
            }

            if (trimmed === '') {
                setCaptchaMessage('');
                return;
            }

            if (trimmed.length === 4 && captchaVerifiedValue !== trimmed) {
                await verifyCaptcha(trimmed);
            } else if (captchaVerifiedValue === trimmed) {
                setCaptchaMessage('输入正确', true);
            } else {
                setCaptchaMessage('');
            }
        });
        captchaInput.addEventListener('blur', async () => {
            const trimmed = (captchaInput.value || '').trim().toUpperCase();
            const formatValid = syncCaptchaValidation();
            if (formatValid && trimmed.length === 4 && captchaVerifiedValue !== trimmed) {
                await verifyCaptcha(trimmed);
            }
        });
    }

    if (form) {
        form.addEventListener('submit', async (event) => {
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
                return;
            }

            if (captchaInput) {
                const trimmed = (captchaInput.value || '').trim().toUpperCase();
                if (trimmed.length === 4 && captchaVerifiedValue !== trimmed) {
                    const verified = await verifyCaptcha(trimmed);
                    if (!verified) {
                        event.preventDefault();
                        captchaInput.focus();
                    }
                }
            }
        });
    }

    if (image && captchaBase) {
        image.addEventListener('click', refreshCaptcha);
        image.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                refreshCaptcha();
            }
        });
    }
})();
