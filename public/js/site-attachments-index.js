document.addEventListener('DOMContentLoaded', () => {
    const configRoot = document.getElementById('attachment-index-config');
    const trigger = document.getElementById('attachment-upload-trigger');
    const fileInput = document.getElementById('attachment-upload-file');
    const replaceInput = document.getElementById('attachment-replace-file');
    const form = document.getElementById('attachment-upload-form');
    const uploadStatus = document.getElementById('attachment-upload-status');
    if (!trigger || !fileInput || !form) {
        return;
    }

    let pendingReplaceMeta = null;
    const uploadUrl = configRoot?.dataset.uploadUrl || '';
    const replaceUrlTemplate = configRoot?.dataset.replaceUrlTemplate || '';
    const usageEndpointTemplate = configRoot?.dataset.usageUrlTemplate || '';

    const setUploadStatus = (message, isError = false) => {
        if (!uploadStatus) {
            return;
        }

        uploadStatus.textContent = message || '';
        uploadStatus.classList.toggle('is-error', Boolean(isError));
    };

    const setUploadBusy = (isBusy) => {
        trigger.disabled = isBusy;
        fileInput.disabled = isBusy;
    };

    const extractUploadErrors = (payload) => {
        if (Array.isArray(payload?.errors)) {
            return payload.errors
                .map((item) => item?.message || '')
                .filter((message) => message !== '');
        }

        if (payload?.errors && typeof payload.errors === 'object') {
            return Object.values(payload.errors)
                .flat()
                .filter((message) => typeof message === 'string' && message !== '');
        }

        if (typeof payload?.message === 'string' && payload.message !== '') {
            return [payload.message];
        }

        return [];
    };

    const uploadAttachments = (files) => new Promise((resolve, reject) => {
        if (!uploadUrl) {
            reject(new Error('资源上传地址未配置'));
            return;
        }

        const formData = new FormData();
        files.forEach((file) => {
            formData.append(files.length > 1 ? 'files[]' : 'file', file);
        });

        const xhr = new XMLHttpRequest();
        xhr.open('POST', uploadUrl, true);
        xhr.withCredentials = true;
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.upload.addEventListener('progress', (event) => {
            if (!event.lengthComputable) {
                return;
            }

            const percent = Math.max(1, Math.min(100, Math.round((event.loaded / event.total) * 100)));
            setUploadStatus(`正在上传 ${files.length} 个资源（${percent}%）...`);
        });

        xhr.addEventListener('load', () => {
            let payload = {};

            try {
                payload = JSON.parse(xhr.responseText || '{}');
            } catch (error) {
                payload = {};
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(payload);
                return;
            }

            const errors = extractUploadErrors(payload);
            reject(new Error(errors[0] || '资源上传失败'));
        });

        xhr.addEventListener('error', () => reject(new Error('资源上传失败')));
        xhr.send(formData);
    });

    trigger.addEventListener('click', () => {
        fileInput.click();
    });

    fileInput.addEventListener('change', async () => {
        const files = Array.from(fileInput.files || []);

        if (!files.length) {
            return;
        }

        setUploadBusy(true);

        try {
            const payload = await uploadAttachments(files);
            const uploadedCount = Array.isArray(payload.attachments)
                ? payload.attachments.length
                : (payload.attachment ? 1 : 0);
            const errors = extractUploadErrors(payload);

            if (uploadedCount <= 0) {
                throw new Error(errors[0] || payload.message || '资源上传失败');
            }

            setUploadStatus(payload.message || `已上传 ${uploadedCount} 个资源。`, errors.length > 0);
            window.setTimeout(() => {
                window.location.reload();
            }, errors.length > 0 ? 1200 : 600);
        } catch (error) {
            setUploadStatus(error?.message || '资源上传失败', true);
        } finally {
            setUploadBusy(false);
            fileInput.value = '';
        }
    });

    const bulkSubmit = document.getElementById('attachment-bulk-submit');
    const bulkForm = document.getElementById('attachment-bulk-form');
    const selectAllButton = document.getElementById('attachment-select-all');

    selectAllButton?.addEventListener('click', () => {
        const checkboxes = Array.from(document.querySelectorAll('.attachment-checkbox'));

        if (!checkboxes.length) {
            return;
        }

        const shouldSelectAll = checkboxes.some((checkbox) => !checkbox.checked);

        checkboxes.forEach((checkbox) => {
            checkbox.checked = shouldSelectAll;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    bulkSubmit?.addEventListener('click', (event) => {
        event.preventDefault();

        if (!bulkForm) {
            return;
        }

        const checked = bulkForm.querySelectorAll('.attachment-checkbox:checked').length
            || document.querySelectorAll('.attachment-checkbox:checked').length;

        if (!checked) {
            showMessage('请先勾选需要批量处理的附件。');
            return;
        }

        window.showConfirmDialog({
            title: '确认批量删除附件？',
            text: `将尝试处理 ${checked} 个附件，已被引用的文件会自动跳过。`,
            confirmText: '批量删除',
            onConfirm: () => bulkForm.submit(),
        });
    });

    document.querySelectorAll('[data-attachment-delete-trigger]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();

            const formId = button.dataset.formId;
            const attachmentName = button.dataset.attachmentName || '该附件';
            const formElement = formId ? document.getElementById(formId) : null;

            if (!formElement) {
                return;
            }

            window.showConfirmDialog({
                title: '确认删除附件？',
                text: `删除后将无法恢复：${attachmentName}`,
                confirmText: '删除附件',
                onConfirm: () => formElement.submit(),
            });
        });
    });

    document.querySelectorAll('[data-attachment-replace-trigger]').forEach((button) => {
        button.addEventListener('click', () => {
            const attachmentId = button.dataset.attachmentId || '';
            const attachmentExtension = (button.dataset.attachmentExtension || '').trim().toLowerCase();

            if (!replaceInput || attachmentId === '' || attachmentExtension === '' || replaceUrlTemplate === '') {
                return;
            }

            const openPicker = () => {
                pendingReplaceMeta = {
                    id: attachmentId,
                    extension: attachmentExtension,
                };
                replaceInput.accept = `.${attachmentExtension}`;
                replaceInput.click();
            };

            window.showConfirmDialog({
                title: '确认替换该资源？',
                text: [
                    '替换后将直接覆盖原文件，原路径保持不变。',
                    '原文件内容会被新文件替换，所有引用会立即生效。',
                    '新文件必须与原附件保持相同后缀名。',
                ].join('\n'),
                confirmText: '选择替换文件',
                onConfirm: () => {
                    if (typeof window.closeConfirmDialog === 'function') {
                        window.closeConfirmDialog();
                    }
                    openPicker();
                },
            });
        });
    });

    replaceInput?.addEventListener('change', async (event) => {
        const input = event.target;
        const file = input.files?.[0];
        const meta = pendingReplaceMeta;

        if (!file || !meta || replaceUrlTemplate === '') {
            pendingReplaceMeta = null;
            input.value = '';
            input.removeAttribute('accept');
            return;
        }

        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch(replaceUrlTemplate.replace('__ATTACHMENT__', String(meta.id)), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData,
                credentials: 'same-origin',
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload.message || '资源替换失败');
            }

            showMessage(payload.message || '附件已替换，原路径保持不变。');
            window.location.reload();
        } catch (error) {
            showMessage(error?.message || '资源替换失败', 'error');
        } finally {
            pendingReplaceMeta = null;
            input.value = '';
            input.removeAttribute('accept');
        }
    });

    const usageModal = document.getElementById('attachment-usage-modal');
    const usageDesc = document.getElementById('attachment-usage-desc');
    const usageList = document.getElementById('attachment-usage-list');
    const usageLoading = document.getElementById('attachment-usage-loading');
    const usageEmpty = document.getElementById('attachment-usage-empty');

    const closeUsageModal = () => {
        if (!usageModal) {
            return;
        }

        usageModal.hidden = true;
    };

    const renderUsageItems = (items) => {
        if (!usageList) {
            return;
        }

        usageList.innerHTML = items.map((item) => `
            <article class="attachment-usage-item">
                <div class="attachment-usage-item-header">
                    <h4 class="attachment-usage-item-title">${item.title}</h4>
                    <div class="attachment-usage-item-updated">${item.updated_at}</div>
                </div>
                <div class="attachment-usage-badges">
                    <span class="attachment-usage-badge">${item.type_label}</span>
                    <span class="attachment-usage-badge">${item.channel_name}</span>
                    ${item.status_label ? `<span class="attachment-usage-badge">${item.status_label}</span>` : ''}
                    ${(item.relation_labels || []).map((label) => `
                        <span class="attachment-usage-badge is-position">${label}</span>
                    `).join('')}
                </div>
                ${(item.edit_url || item.view_url) ? `
                <div class="attachment-usage-actions">
                    ${item.edit_url ? `<a class="button secondary neutral-action" href="${item.edit_url}">编辑内容</a>` : ''}
                    ${item.view_url ? `<a class="button secondary neutral-action" href="${item.view_url}" target="_blank" rel="noreferrer">查看前台</a>` : ''}
                </div>
                ` : ''}
            </article>
        `).join('');
    };

    document.querySelectorAll('[data-attachment-usage-trigger]').forEach((button) => {
        button.addEventListener('click', async () => {
            if (!usageModal || !usageDesc || !usageList || !usageLoading || !usageEmpty || usageEndpointTemplate === '') {
                return;
            }

            const attachmentId = button.dataset.attachmentId;
            const attachmentName = button.dataset.attachmentName || '该附件';

            usageModal.hidden = false;
            usageDesc.textContent = `正在查看：${attachmentName}`;
            usageLoading.hidden = false;
            usageList.hidden = true;
            usageEmpty.hidden = true;
            usageList.innerHTML = '';

            try {
                const response = await fetch(usageEndpointTemplate.replace('__ATTACHMENT__', attachmentId), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.message || '加载引用详情失败。');
                }

                usageDesc.textContent = `附件：${payload.attachment.name}`;
                usageLoading.hidden = true;

                if (!payload.items || payload.items.length === 0) {
                    usageEmpty.hidden = false;
                    return;
                }

                renderUsageItems(payload.items);
                usageList.hidden = false;
            } catch (error) {
                usageLoading.hidden = true;
                usageEmpty.hidden = false;
                usageEmpty.textContent = error?.message || '加载引用详情失败，请稍后重试。';
            }
        });
    });

    usageModal?.querySelectorAll('[data-close-attachment-usage]').forEach((button) => {
        button.addEventListener('click', closeUsageModal);
    });
});
