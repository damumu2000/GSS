(() => {
    const form = document.getElementById('channel-form');
    if (!form) {
        return;
    }

    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    const linkUrlInput = document.getElementById('link_url');
    const typeCards = Array.from(document.querySelectorAll('[data-channel-type-card]'));
    const typeInputs = Array.from(document.querySelectorAll('input[name="type"]'));
    const sections = Array.from(document.querySelectorAll('[data-channel-type-section]'));
    const slugifyEndpoint = form.dataset.slugifyEndpoint || '';
    const serverMessages = JSON.parse(form.dataset.validationErrors || '[]');
    const currentTypeValue = form.dataset.currentType || 'list';
    const channelNamePattern = /^[\u3400-\u9FFF\uF900-\uFAFFA-Za-z0-9_\-\s·()（）]+$/u;

    let slugManuallyEdited = !!slugInput && slugInput.value.trim() !== '';
    let slugRequestToken = 0;

    const normalizeSlug = (value) => {
        return value
            .normalize('NFKC')
            .trim()
            .replace(/[^A-Za-z0-9_-]+/g, '-')
            .replace(/-{2,}/g, '-')
            .replace(/_{2,}/g, '_')
            .replace(/^[-_]+|[-_]+$/g, '')
            .slice(0, 20);
    };

    const normalizeGeneratedSlug = (value) => {
        return value
            .normalize('NFKC')
            .trim()
            .replace(/[^A-Za-z0-9_-]+/g, '')
            .replace(/-{2,}/g, '-')
            .replace(/_{2,}/g, '_')
            .replace(/^[-_]+|[-_]+$/g, '')
            .slice(0, 20)
            .toLowerCase();
    };

    const buildSlug = async (text) => {
        const normalizedText = text.trim();

        if (normalizedText === '') {
            return '';
        }

        const requestToken = ++slugRequestToken;

        try {
            const response = await fetch(`${slugifyEndpoint}?name=${encodeURIComponent(normalizedText)}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('slugify failed');
            }

            const payload = await response.json();

            if (requestToken !== slugRequestToken) {
                return null;
            }

            return normalizeGeneratedSlug(payload.slug || '');
        } catch (error) {
            return normalizeGeneratedSlug(normalizedText);
        }
    };

    const getCurrentType = () => {
        const checkedType = typeInputs.find((input) => input.checked);
        return checkedType?.value || currentTypeValue;
    };

    const clearFieldError = (field) => {
        if (!field) {
            return;
        }

        field.classList.remove('is-error');
        field.removeAttribute('aria-invalid');
    };

    const setFieldError = (field) => {
        if (!field) {
            return;
        }

        field.classList.add('is-error');
        field.setAttribute('aria-invalid', 'true');
    };

    window.formatAdminErrorMessages = window.formatAdminErrorMessages || ((messages) => {
        return [...new Set((messages || [])
            .map((message) => String(message || '').trim())
            .filter((message) => message !== '')
            .map((message) => message.replace(/[，。；、]+$/u, '')))]
            .join('，') + '。';
    });

    const syncTypeView = () => {
        const currentType = getCurrentType();

        typeCards.forEach((card) => {
            const radio = card.querySelector('input[name="type"]');
            card.classList.toggle('is-active', !!radio?.checked);
        });

        sections.forEach((section) => {
            const isActive = section.dataset.channelTypeSection === currentType;
            section.hidden = !isActive;
            section.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = !isActive;
            });
        });
    };

    const validateName = () => {
        const value = String(nameInput?.value || '').trim();

        if (value === '') {
            return '请填写栏目名称。';
        }

        if (value.length < 2) {
            return '栏目名称不能少于2个字符。';
        }

        if (!channelNamePattern.test(value)) {
            return '栏目名称只能使用中文、英文、数字、空格、下划线、中划线、圆括号或间隔点。';
        }

        return value.length <= 100 ? '' : '栏目名称不能超过100个字符。';
    };

    const validateSlug = () => {
        if (!slugInput) {
            return '';
        }

        const value = String(slugInput.value || '').trim();

        if (value === '') {
            return '请填写栏目别名。';
        }

        if (value.length < 3) {
            return '栏目别名不能少于3个字符。';
        }

        if (value.length > 20) {
            return '栏目别名不能超过20个字符。';
        }

        return /^[A-Za-z0-9_-]+$/.test(value) ? '' : '栏目别名只能由英文、数字、下划线和短横线组成。';
    };

    const validateType = () => {
        return getCurrentType() ? '' : '请选择栏目类型。';
    };

    const validateLinkUrl = () => {
        if (!linkUrlInput || getCurrentType() !== 'link') {
            return '';
        }

        const value = String(linkUrlInput.value || '').trim();

        if (value === '') {
            return '外链栏目必须填写外链地址。';
        }

        try {
            const parsed = new URL(value);
            return ['http:', 'https:'].includes(parsed.protocol) ? '' : '外链地址格式不正确，请输入完整的 http:// 或 https:// 地址。';
        } catch (error) {
            return '外链地址格式不正确，请输入完整的 http:// 或 https:// 地址。';
        }
    };

    const regenerateSlug = async () => {
        if (!slugInput || !nameInput) {
            return;
        }

        const generatedSlug = await buildSlug(nameInput.value);

        if (generatedSlug === null) {
            return;
        }

        slugInput.value = generatedSlug;
    };

    nameInput?.addEventListener('input', async () => {
        if (!slugManuallyEdited) {
            await regenerateSlug();
        }
    });

    slugInput?.addEventListener('input', () => {
        slugManuallyEdited = slugInput.value.trim() !== '';
        slugInput.value = normalizeSlug(slugInput.value);
    });

    typeInputs.forEach((input) => {
        input.addEventListener('change', syncTypeView);
    });

    typeCards.forEach((card) => {
        card.addEventListener('click', () => {
            const radio = card.querySelector('input[name="type"]');
            if (!radio) {
                return;
            }

            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    nameInput?.setAttribute('maxlength', '100');
    nameInput?.addEventListener('blur', () => {
        const message = validateName();
        if (message === '') {
            clearFieldError(nameInput);
        }
    });

    slugInput?.addEventListener('blur', () => {
        const message = validateSlug();
        if (message === '') {
            clearFieldError(slugInput);
        }
    });

    linkUrlInput?.addEventListener('blur', () => {
        const message = validateLinkUrl();
        if (message === '') {
            clearFieldError(linkUrlInput);
        }
    });

    form.addEventListener('submit', (event) => {
        const messages = [];
        let firstInvalid = null;

        clearFieldError(nameInput);
        clearFieldError(slugInput);
        clearFieldError(linkUrlInput);
        typeCards.forEach((card) => card.classList.remove('is-error'));

        const nameMessage = validateName();
        if (nameMessage !== '') {
            setFieldError(nameInput);
            messages.push(nameMessage);
            firstInvalid = firstInvalid || nameInput;
        }

        const slugMessage = validateSlug();
        if (slugMessage !== '') {
            setFieldError(slugInput);
            messages.push(slugMessage);
            firstInvalid = firstInvalid || slugInput;
        }

        const typeMessage = validateType();
        if (typeMessage !== '') {
            typeCards.forEach((card) => card.classList.add('is-error'));
            messages.push(typeMessage);
            firstInvalid = firstInvalid || typeInputs[0];
        }

        const linkUrlMessage = validateLinkUrl();
        if (linkUrlMessage !== '') {
            setFieldError(linkUrlInput);
            messages.push(linkUrlMessage);
            firstInvalid = firstInvalid || linkUrlInput;
        }

        if (messages.length > 0) {
            event.preventDefault();
            if (typeof window.showMessage === 'function') {
                window.showMessage(window.formatAdminErrorMessages(messages), 'error');
            }
            firstInvalid?.focus();
            firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    if (Array.isArray(serverMessages) && serverMessages.length > 0 && typeof window.showMessage === 'function' && typeof window.formatAdminErrorMessages === 'function') {
        window.showMessage(window.formatAdminErrorMessages(serverMessages), 'error');
    }

    syncTypeView();
})();
