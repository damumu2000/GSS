(() => {
    const form = document.getElementById('site-setting-form');

    if (!form) {
        return;
    }

    const rawErrors = form.dataset.validationErrors || '[]';
    let serverValidationErrors = [];

    try {
        serverValidationErrors = JSON.parse(rawErrors);
    } catch (error) {
        serverValidationErrors = [];
    }

    const fields = {
        name: document.getElementById('name'),
        filing_number: document.getElementById('filing_number'),
        contact_phone: document.getElementById('contact_phone'),
        contact_email: document.getElementById('contact_email'),
    };

    const validators = {
        name: (value) => value.trim() !== '' ? '' : '请填写站点名称。',
        contact_phone: (value) => value === '' || /^[0-9\-+\s()#]{6,50}$/.test(value) ? '' : '联系电话格式不正确，请输入有效的电话或手机号。',
        contact_email: (value) => value === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? '' : '联系邮箱格式不正确，请重新填写。',
        filing_number: (value) => value === '' || /^[A-Za-z0-9\u4E00-\u9FA5\-\(\)（）〔〕[\]【】\/\s]+$/u.test(value) ? '' : '备案号格式不正确，请仅使用中文、字母、数字、空格及常见连接符。',
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

    Object.entries(fields).forEach(([key, field]) => {
        if (!field) {
            return;
        }

        const validateCurrentField = () => {
            const validator = validators[key];

            if (!validator) {
                return;
            }

            const message = validator(field.value.trim());

            if (message === '') {
                clearFieldError(field);
            }
        };

        field.addEventListener('input', validateCurrentField);
        field.addEventListener('blur', validateCurrentField);
    });

    document.querySelectorAll('.setting-toggle-input').forEach((toggle) => {
        const state = document.querySelector(`[data-toggle-state-for="${toggle.id}"]`);

        if (!state) {
            return;
        }

        const syncState = () => {
            state.textContent = toggle.checked ? '已开启' : '未开启';
        };

        toggle.addEventListener('change', syncState);
        syncState();
    });

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const uploadUrl = form.dataset.mediaUploadUrl || '';

    const getMediaParts = (uploaderRoot) => {
        if (!(uploaderRoot instanceof HTMLElement)) {
            return null;
        }

        const hiddenInput = uploaderRoot.querySelector('[data-media-value]');
        const targetSelector = hiddenInput?.getAttribute('data-media-target') ?? '';
        const actualInput = targetSelector ? document.querySelector(targetSelector) : null;
        const fileInput = uploaderRoot.querySelector('[data-media-file]');
        const uploadButton = uploaderRoot;
        const uploadLabel = uploaderRoot.querySelector('[data-media-action-label]');
        const clearButton = uploaderRoot.querySelector('[data-media-clear]');
        const image = uploaderRoot.querySelector('[data-media-preview-image]');
        const placeholder = uploaderRoot.querySelector('[data-media-preview-placeholder]');

        if (
            !(hiddenInput instanceof HTMLInputElement)
            || !(actualInput instanceof HTMLInputElement)
            || !(fileInput instanceof HTMLInputElement)
            || !(uploadButton instanceof HTMLElement)
            || !(image instanceof HTMLImageElement)
            || !(placeholder instanceof Element)
        ) {
            return null;
        }

        return { hiddenInput, actualInput, fileInput, uploadButton, uploadLabel, clearButton, image, placeholder };
    };

    const syncMediaPreview = (uploaderRoot) => {
        const parts = getMediaParts(uploaderRoot);
        if (!parts) {
            return;
        }

        const { hiddenInput, actualInput, uploadLabel, clearButton, image, placeholder } = parts;
        const value = actualInput.value.trim() || hiddenInput.value.trim();
        const hasValue = value !== '';

        hiddenInput.value = value;
        actualInput.value = value;
        image.hidden = !hasValue;
        placeholder.hidden = hasValue;

        if (hasValue) {
            image.src = value;
        } else {
            image.removeAttribute('src');
        }

        if (clearButton instanceof HTMLButtonElement) {
            clearButton.hidden = !hasValue;
        }

        if (uploadLabel instanceof HTMLElement) {
            uploadLabel.textContent = uploaderRoot.dataset.mediaActionText ?? '更换图片';
        }
    };

    document.querySelectorAll('[data-media-uploader]').forEach((uploaderRoot) => {
        const parts = getMediaParts(uploaderRoot);
        if (!parts) {
            return;
        }

        parts.image.addEventListener('error', () => {
            parts.image.hidden = true;
            parts.placeholder.hidden = false;
            parts.image.removeAttribute('src');
        });

        syncMediaPreview(uploaderRoot);
    });

    document.addEventListener('click', (event) => {
        const clearButton = event.target instanceof HTMLElement ? event.target.closest('[data-media-clear]') : null;
        if (!(clearButton instanceof HTMLButtonElement)) {
            return;
        }

        event.preventDefault();
        const uploaderRoot = clearButton.closest('[data-media-uploader]');
        const parts = getMediaParts(uploaderRoot);
        if (!parts) {
            return;
        }

        parts.hiddenInput.value = '';
        parts.actualInput.value = '';
        parts.fileInput.value = '';
        syncMediaPreview(uploaderRoot);
    });

    document.addEventListener('change', async (event) => {
        const fileInput = event.target instanceof HTMLInputElement && event.target.matches('[data-media-file]')
            ? event.target
            : null;

        if (!fileInput) {
            return;
        }

        const uploaderRoot = fileInput.closest('[data-media-uploader]');
        const parts = getMediaParts(uploaderRoot);
        if (!parts) {
            return;
        }

        const file = fileInput.files?.[0];
        if (!file) {
            return;
        }

        const mediaSlot = uploaderRoot.dataset.mediaSlot ?? '';
        if (mediaSlot === '') {
            window.showMessage?.('图片上传配置缺失，请刷新页面后重试。', 'error');
            fileInput.value = '';
            return;
        }

        if (uploadUrl === '') {
            window.showMessage?.('图片上传地址缺失，请刷新页面后重试。', 'error');
            fileInput.value = '';
            return;
        }

        const originalText = parts.uploadLabel instanceof HTMLElement ? parts.uploadLabel.textContent : '';
        parts.uploadButton.classList.add('is-uploading');
        if (parts.uploadLabel instanceof HTMLElement) {
            parts.uploadLabel.textContent = '上传中...';
        }

        try {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('slot', mediaSlot);

            const response = await fetch(uploadUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload.url) {
                throw new Error(payload.message || '图片上传失败');
            }

            parts.hiddenInput.value = payload.url;
            parts.actualInput.value = payload.url;
            syncMediaPreview(uploaderRoot);
        } catch (error) {
            window.showMessage?.(error.message || '图片上传失败', 'error');
        } finally {
            parts.uploadButton.classList.remove('is-uploading');
            if (parts.uploadLabel instanceof HTMLElement) {
                parts.uploadLabel.textContent = originalText;
            }
            fileInput.value = '';
        }
    });

    form.addEventListener('submit', (event) => {
        const messages = [];
        let firstInvalid = null;

        Object.values(fields).forEach((field) => clearFieldError(field));

        Object.entries(fields).forEach(([key, field]) => {
            if (!field) {
                return;
            }

            const validator = validators[key];
            const value = field.value.trim();
            const message = validator ? validator(value) : '';

            if (message !== '') {
                setFieldError(field);
                messages.push(message);
                firstInvalid = firstInvalid || field;
            }
        });

        if (messages.length > 0) {
            event.preventDefault();
            window.showMessage?.([...new Set(messages)].join('，'), 'error');
            firstInvalid?.focus();
            firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    if (Array.isArray(serverValidationErrors) && serverValidationErrors.length > 0) {
        const messages = [...new Set(serverValidationErrors.filter((message) => typeof message === 'string' && message.trim() !== ''))];
        if (messages.length > 0) {
            window.showMessage?.(messages.join('，'), 'error');
        }
    }
})();
