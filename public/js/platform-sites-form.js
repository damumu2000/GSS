(() => {
    const forms = Array.from(document.querySelectorAll('[data-platform-site-form]'));
    if (forms.length === 0) {
        return;
    }

    forms.forEach((form) => {
        const rawErrors = form.dataset.validationErrors || '[]';
        let serverValidationErrors = [];

        try {
            serverValidationErrors = JSON.parse(rawErrors);
        } catch (error) {
            serverValidationErrors = [];
        }

        if (Array.isArray(serverValidationErrors) && serverValidationErrors.length > 0) {
            const messages = [...new Set(serverValidationErrors.filter((message) => typeof message === 'string' && message.trim() !== ''))];
            if (messages.length > 0) {
                window.setTimeout(() => {
                    window.showMessage?.(messages.join('，'), 'error');
                }, 0);
            }
        }
    });

    document.querySelectorAll('[data-custom-select]').forEach((selectRoot) => {
        const nativeSelect = selectRoot.querySelector('[data-select-native]');
        const trigger = selectRoot.querySelector('[data-select-trigger]');
        const label = selectRoot.querySelector('[data-select-label]');
        const options = selectRoot.querySelectorAll('[data-select-option]');

        if (!nativeSelect || !trigger || !label || options.length === 0) {
            return;
        }

        const syncLabel = () => {
            const selected = nativeSelect.options[nativeSelect.selectedIndex];
            label.textContent = selected ? selected.textContent : '请选择';
        };

        const closeSelect = () => {
            selectRoot.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        };

        trigger.addEventListener('click', () => {
            const willOpen = !selectRoot.classList.contains('is-open');
            document.querySelectorAll('[data-custom-select].is-open').forEach((opened) => {
                opened.classList.remove('is-open');
                opened.querySelector('[data-select-trigger]')?.setAttribute('aria-expanded', 'false');
            });
            if (willOpen) {
                selectRoot.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
            }
        });

        options.forEach((optionButton) => {
            optionButton.addEventListener('click', () => {
                const value = optionButton.dataset.value ?? '';
                nativeSelect.value = value;
                options.forEach((item) => item.classList.remove('is-active'));
                optionButton.classList.add('is-active');
                syncLabel();
                closeSelect();
                nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });

        document.addEventListener('click', (event) => {
            if (!selectRoot.contains(event.target)) {
                closeSelect();
            }
        });

        syncLabel();
    });

    document.querySelectorAll('[data-admin-picker]').forEach((pickerRoot) => {
        const trigger = pickerRoot.querySelector('[data-admin-picker-trigger]');
        const summary = pickerRoot.querySelector('[data-admin-picker-summary]');
        const searchInput = pickerRoot.querySelector('[data-admin-picker-search-input]');
        const options = Array.from(pickerRoot.querySelectorAll('[data-admin-picker-option]'));
        const emptyState = pickerRoot.querySelector('[data-admin-picker-empty]');
        const placeholder = pickerRoot.dataset.pickerPlaceholder || '搜索并选择站点管理员';

        if (!trigger || !summary || !searchInput || options.length === 0) {
            return;
        }

        const renderSummary = () => {
            const checkedOptions = options.filter((option) => option.querySelector('input')?.checked);
            if (checkedOptions.length === 0) {
                summary.innerHTML = `<span class="admin-picker-placeholder">${placeholder}</span>`;
                return;
            }

            const visibleItems = checkedOptions.slice(0, 3).map((option) => `<span class="admin-picker-tag">${option.dataset.label ?? ''}</span>`);
            if (checkedOptions.length > 3) {
                visibleItems.push(`<span class="admin-picker-tag">+${checkedOptions.length - 3}</span>`);
            }
            summary.innerHTML = visibleItems.join('');
        };

        const updateFilter = () => {
            const keyword = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;
            options.forEach((option) => {
                const matched = keyword === '' || (option.dataset.keywords ?? '').toLowerCase().includes(keyword);
                option.classList.toggle('is-hidden', !matched);
                if (matched) {
                    visibleCount += 1;
                }
            });
            if (emptyState) {
                emptyState.hidden = visibleCount > 0;
            }
        };

        const closePicker = () => {
            pickerRoot.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        };

        trigger.addEventListener('click', () => {
            const willOpen = !pickerRoot.classList.contains('is-open');
            document.querySelectorAll('[data-admin-picker].is-open').forEach((opened) => {
                opened.classList.remove('is-open');
                opened.querySelector('[data-admin-picker-trigger]')?.setAttribute('aria-expanded', 'false');
            });
            if (willOpen) {
                pickerRoot.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
                searchInput.focus();
                updateFilter();
            }
        });

        options.forEach((option) => {
            const checkbox = option.querySelector('input');
            if (!checkbox) {
                return;
            }

            option.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                checkbox.checked = !checkbox.checked;
                renderSummary();
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            });

            checkbox.addEventListener('change', renderSummary);
        });

        searchInput.addEventListener('input', updateFilter);

        document.addEventListener('click', (event) => {
            if (!pickerRoot.contains(event.target)) {
                closePicker();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && pickerRoot.classList.contains('is-open')) {
                closePicker();
            }
        });

        renderSummary();
        updateFilter();
    });

    document.querySelectorAll('[data-domain-editor]').forEach((editorRoot) => {
        const list = editorRoot.querySelector('[data-domain-list]');
        const addButton = editorRoot.querySelector('[data-domain-add]');
        const hiddenField = editorRoot.querySelector('[data-domain-hidden]');

        if (!list || !addButton || !(hiddenField instanceof HTMLTextAreaElement)) {
            return;
        }

        const createTrashIcon = () => `
            <svg viewBox="0 0 16 16" aria-hidden="true">
                <path d="M2.5 4.5h11"/>
                <path d="M6.5 2.5h3"/>
                <path d="M5 6.5v5"/>
                <path d="M8 6.5v5"/>
                <path d="M11 6.5v5"/>
                <path d="M4.5 4.5l.5 8a1 1 0 0 0 1 .9h4a1 1 0 0 0 1-.9l.5-8"/>
            </svg>
        `;

        const createRow = (value = '') => {
            const row = document.createElement('div');
            row.className = 'domain-editor-row';
            row.dataset.domainRow = 'true';
            row.innerHTML = `
                <span class="domain-editor-badge is-secondary" data-domain-badge>附加域名</span>
                <input class="field" type="text" value="${value.replace(/"/g, '&quot;')}" placeholder="如 site.test" data-domain-input>
                <button class="domain-editor-remove" type="button" data-domain-remove data-tooltip="删除该域名">${createTrashIcon()}</button>
            `;
            return row;
        };

        const syncRows = () => {
            const rows = Array.from(list.querySelectorAll('[data-domain-row]'));
            const values = rows
                .map((row) => row.querySelector('[data-domain-input]')?.value.trim() ?? '')
                .filter((value) => value !== '');

            hiddenField.value = values.join('\n');

            rows.forEach((row, index) => {
                const badge = row.querySelector('[data-domain-badge]');
                if (badge) {
                    badge.textContent = index === 0 ? '主域名' : '附加域名';
                    badge.classList.toggle('is-secondary', index !== 0);
                }
            });
        };

        const bindRow = (row) => {
            const input = row.querySelector('[data-domain-input]');
            const removeButton = row.querySelector('[data-domain-remove]');

            if (input instanceof HTMLInputElement) {
                input.addEventListener('input', syncRows);
                input.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter') {
                        return;
                    }
                    event.preventDefault();
                    const nextRow = createRow('');
                    bindRow(nextRow);
                    list.appendChild(nextRow);
                    syncRows();
                    nextRow.querySelector('[data-domain-input]')?.focus();
                });
            }

            if (removeButton instanceof HTMLButtonElement) {
                removeButton.innerHTML = createTrashIcon();
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    row.remove();
                    syncRows();
                });
            }
        };

        addButton.addEventListener('click', () => {
            const row = createRow('');
            bindRow(row);
            list.appendChild(row);
            syncRows();
            row.querySelector('[data-domain-input]')?.focus();
        });

        list.querySelectorAll('[data-domain-row]').forEach((row) => bindRow(row));
        syncRows();
    });

    if (window.tinymce) {
        window.tinymce.init({
            selector: 'textarea.site-remark-rich-editor',
            min_height: 200,
            height: 260,
            language: 'zh-CN',
            language_url: '/assets/tinymce/langs/zh-CN.js',
            menubar: false,
            branding: false,
            promotion: false,
            license_key: 'gpl',
            convert_urls: false,
            relative_urls: false,
            plugins: 'autolink link lists code textcolor',
            toolbar: 'undo redo | bold italic underline forecolor backcolor | bullist numlist | link blockquote | removeformat code',
            content_style: 'body { font-family: PingFang SC, Microsoft YaHei, sans-serif; font-size: 14px; line-height: 1.8; }',
            setup(editor) {
                editor.on('change input undo redo', () => editor.save());
            }
        });
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const getMediaParts = (uploaderRoot) => {
        if (!(uploaderRoot instanceof HTMLElement)) {
            return null;
        }

        const hiddenInput = uploaderRoot.querySelector('[data-media-value]');
        const fileInput = uploaderRoot.querySelector('[data-media-file]');
        const uploadButton = uploaderRoot;
        const uploadLabel = uploaderRoot.querySelector('[data-media-action-label]');
        const clearButton = uploaderRoot.querySelector('[data-media-clear]');
        const image = uploaderRoot.querySelector('[data-media-preview-image]');
        const placeholder = uploaderRoot.querySelector('[data-media-preview-placeholder]');

        if (!(hiddenInput instanceof HTMLInputElement) || !(fileInput instanceof HTMLInputElement) || !(uploadButton instanceof HTMLElement) || !(image instanceof HTMLImageElement) || !(placeholder instanceof Element)) {
            return null;
        }

        return { uploaderRoot, hiddenInput, fileInput, uploadButton, uploadLabel, clearButton, image, placeholder };
    };

    const syncMediaPreview = (uploaderRoot) => {
        const parts = getMediaParts(uploaderRoot);
        if (!parts) {
            return;
        }

        const { hiddenInput, uploadButton, uploadLabel, clearButton, image, placeholder } = parts;
        const value = hiddenInput.value.trim();
        const hasValue = value !== '';

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
            uploadLabel.textContent = uploadButton.dataset.mediaActionText ?? '更换图片';
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
        if (clearButton instanceof HTMLButtonElement) {
            event.preventDefault();
            const uploaderRoot = clearButton.closest('[data-media-uploader]');
            const parts = getMediaParts(uploaderRoot);
            if (!parts) {
                return;
            }
            parts.hiddenInput.value = '';
            parts.fileInput.value = '';
            syncMediaPreview(uploaderRoot);
        }
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
        const siteId = uploaderRoot.dataset.mediaSiteId ?? '';
        const siteKey = document.getElementById('site_key') instanceof HTMLInputElement
            ? document.getElementById('site_key').value.trim()
            : '';
        const uploadUrl = uploaderRoot.dataset.mediaUploadUrl || '';

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

        if (siteId === '' && siteKey === '') {
            window.showMessage?.('请先填写站点标识，再上传站点图片。', 'error');
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
            if (siteId !== '') {
                formData.append('site_id', siteId);
            } else if (siteKey !== '') {
                formData.append('site_key', siteKey);
            }

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
})();
