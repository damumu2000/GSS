(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const grid = document.querySelector('[data-promo-item-grid]');
    const emptyState = document.querySelector('[data-promo-item-empty]');
    const badge = document.querySelector('[data-item-count-badge]');
    const editor = document.querySelector('[data-promo-item-editor]');
    const editorForm = document.querySelector('[data-promo-item-editor-form]');
    const editorTitle = document.getElementById('promo-item-editor-title');
    const drawerErrors = document.querySelector('[data-drawer-errors]');
    const preview = document.querySelector('[data-drawer-image-preview]');
    const previewNote = document.querySelector('[data-drawer-image-note]');
    const drawerSubmit = document.querySelector('[data-drawer-submit]');
    const attachmentInput = document.getElementById('drawer_attachment_id');
    const config = document.getElementById('site-promo-items-config');

    if (!grid || !editor || !editorForm || !attachmentInput || !preview || !config) {
        return;
    }

    const rawItems = JSON.parse(config.dataset.itemSheets || '{}');
    const items = new Map(Object.entries(rawItems).map(([id, item]) => [Number(id), item]));
    const maxItems = Number(grid.dataset.maxItems || 0);
    const displayMode = config.dataset.displayMode || '';
    let editingItemId = null;

    const storeUrl = grid.dataset.storeUrl || '';
    const updateUrlTemplate = grid.dataset.updateUrlTemplate || '';
    const replaceImageUrlTemplate = grid.dataset.replaceImageUrlTemplate || '';
    const duplicateUrlTemplate = grid.dataset.duplicateUrlTemplate || '';
    const toggleUrlTemplate = grid.dataset.toggleUrlTemplate || '';
    const destroyUrlTemplate = grid.dataset.destroyUrlTemplate || '';

    const defaults = {
        id: null,
        attachment_id: 0,
        attachment_name: '',
        attachment_url: '',
        title: '',
        subtitle: '',
        link_url: '',
        link_target: '_self',
        status: 1,
        start_at: '',
        end_at: '',
        display_payload: {
            position: 'right-bottom',
            animation: 'float',
            offset_x: 24,
            offset_y: 24,
            width: 180,
            height: '',
            z_index: 120,
            show_on: 'all',
            closable: true,
            remember_close: true,
            close_expire_hours: 24,
        },
    };

    const upsertAttachmentCache = (attachment) => {
        const resolvedId = Number(attachment?.id || attachment?.attachment_id || 0);

        if (resolvedId < 1) {
            return null;
        }

        const normalized = {
            id: resolvedId,
            name: attachment?.name || attachment?.attachment_name || '',
            url: attachment?.url || attachment?.attachment_url || '',
            extension: String(attachment?.extension || '').toLowerCase(),
        };
        const existingIndex = cmsAttachments.findIndex((item) => Number(item.id) === resolvedId);

        if (existingIndex >= 0) {
            cmsAttachments[existingIndex] = { ...cmsAttachments[existingIndex], ...normalized };
        } else {
            cmsAttachments.push(normalized);
        }

        return cmsAttachments.find((item) => Number(item.id) === resolvedId) || null;
    };

    const cloneItem = (item) => JSON.parse(JSON.stringify(item));

    const toDateTimeLocal = (value) => {
        if (!value) {
            return '';
        }

        return String(value).replace(' ', 'T').slice(0, 16);
    };

    const formatDateRange = (item) => `${item.start_at || '立即生效'} ~ ${item.end_at || '长期有效'}`;
    const replaceTemplate = (template, itemId) => template.replace('__ITEM__', String(itemId));

    const updateCountBadge = () => {
        if (badge) {
            badge.textContent = `${items.size} / ${maxItems}`;
        }
    };

    const syncSelectValue = (selectElement, value) => {
        if (!selectElement) {
            return;
        }

        selectElement.value = value;
        Array.from(selectElement.options).forEach((option) => {
            option.selected = option.value === value;
        });

        const root = selectElement.closest('[data-site-select]');
        const trigger = root?.querySelector('[data-select-trigger]');
        const panel = root?.querySelector('[data-select-panel]');
        const selectedOption = Array.from(selectElement.options).find((option) => option.value === value);

        if (trigger) {
            trigger.textContent = selectedOption?.textContent || '';
        }

        if (panel) {
            panel.querySelectorAll('.site-select-option').forEach((button) => {
                button.classList.toggle('is-active', button.dataset.value === value);
            });
        }
    };

    const renderDrawerImage = (attachmentId, fallbackAttachment = null) => {
        const attachment = cmsAttachments.find((item) => Number(item.id) === Number(attachmentId))
            || upsertAttachmentCache(fallbackAttachment);
        preview.innerHTML = '';

        if (!attachment) {
            const placeholder = document.createElement('span');
            placeholder.textContent = '点击选择图宣图片';
            placeholder.setAttribute('data-drawer-image-placeholder', '');
            preview.appendChild(placeholder);
            previewNote.textContent = '未选择图片，点击上方区域即可从资源库选图。';
            return null;
        }

        const img = document.createElement('img');
        img.src = attachment.url || '';
        img.alt = attachment.name || '图宣图片';
        preview.appendChild(img);
        previewNote.textContent = '已选择图片，可点击上方图片重新更换。';
        return attachment;
    };

    const fillForm = (item) => {
        attachmentInput.value = item.attachment_id ? String(item.attachment_id) : '';
        document.getElementById('drawer_title').value = item.title || '';
        document.getElementById('drawer_subtitle').value = item.subtitle || '';
        document.getElementById('drawer_link_url').value = item.link_url || '';
        document.getElementById('drawer_start_at').value = toDateTimeLocal(item.start_at);
        document.getElementById('drawer_end_at').value = toDateTimeLocal(item.end_at);
        syncSelectValue(document.getElementById('drawer_link_target'), item.link_target || '_self');
        syncSelectValue(document.getElementById('drawer_status'), String(item.status ?? 1));

        if (displayMode === 'floating') {
            const payload = item.display_payload || {};
            syncSelectValue(document.getElementById('drawer_floating_position'), payload.position || 'right-bottom');
            syncSelectValue(document.getElementById('drawer_floating_animation'), payload.animation || 'float');
            document.getElementById('drawer_floating_offset_x').value = payload.offset_x ?? 24;
            document.getElementById('drawer_floating_offset_y').value = payload.offset_y ?? 24;
            document.getElementById('drawer_floating_width').value = payload.width ?? 180;
            document.getElementById('drawer_floating_height').value = payload.height ?? '';
            document.getElementById('drawer_floating_z_index').value = payload.z_index ?? 120;
            syncSelectValue(document.getElementById('drawer_floating_show_on'), payload.show_on || 'all');
            syncSelectValue(document.getElementById('drawer_floating_closable'), String(payload.closable === false ? 0 : 1));
            syncSelectValue(document.getElementById('drawer_floating_remember_close'), String(payload.remember_close === false ? 0 : 1));
            document.getElementById('drawer_floating_close_expire_hours').value = payload.close_expire_hours ?? 24;
        }

        renderDrawerImage(item.attachment_id, {
            attachment_id: item.attachment_id,
            attachment_name: item.attachment_name,
            attachment_url: item.attachment_url,
        });
    };

    const closeEditor = () => {
        editingItemId = null;
        drawerErrors.hidden = true;
        drawerErrors.innerHTML = '';
        editor.hidden = true;
        document.body.classList.remove('has-modal-open');
    };

    const openEditor = (itemId = null) => {
        if (itemId === null && items.size >= maxItems) {
            window.showMessage?.('当前图宣位已达到最大图宣数量限制，请先删除或停用其他图宣内容。', 'error');
            return;
        }

        editingItemId = itemId;
        const item = itemId === null ? cloneItem(defaults) : cloneItem(items.get(Number(itemId)) || defaults);
        editorTitle.textContent = itemId === null ? '新增图宣内容' : '编辑图宣内容';
        drawerSubmit.textContent = itemId === null ? '创建图宣内容' : '保存图宣内容';
        drawerErrors.hidden = true;
        drawerErrors.innerHTML = '';
        fillForm(item);
        editor.hidden = false;
        document.body.classList.add('has-modal-open');
    };

    const renderCardHtml = (item) => {
        const title = item.title || item.attachment_name || '未命名图宣';
        const subtitle = item.subtitle ? `<div class="promo-item-subtitle">${escapeHtml(item.subtitle)}</div>` : '';
        const linkLine = item.link_url ? `<div>链接：${escapeHtml(item.link_url)}</div>` : '';
        const badgeClassMap = {
            active: '',
            disabled: ' is-muted',
            scheduled: ' is-warning',
            expired: ' is-danger',
        };
        const badgeClass = badgeClassMap[item.effective_status] || ' is-muted';
        const badgeText = item.effective_status_label || '已停用';
        const toggleText = Number(item.status) === 1 ? '停用' : '启用';
        const imageMarkup = item.attachment_url
            ? `<img src="${escapeHtml(item.attachment_url)}" alt="${escapeHtml(title)}">`
            : '<span>点击选择图宣图片</span>';

        return `
            <div class="promo-item-card-head">
                <span class="promo-item-status-badge${badgeClass}">${badgeText}</span>
                <span class="promo-item-drag-handle" aria-label="拖拽排序" data-tooltip="拖拽排序">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h8v2H8zM8 11h8v2H8zM8 16h8v2H8z"/></svg>
                </span>
            </div>
            <button class="promo-item-preview-button" type="button" data-replace-item-image="${item.id}">
                <div class="promo-item-preview">${imageMarkup}</div>
            </button>
            <div>
                <div class="promo-item-title">${escapeHtml(title)}</div>
                ${subtitle}
            </div>
            <div class="promo-item-meta">
                <div>文件：${escapeHtml(item.attachment_name || '未选择图片')}</div>
                <div>时间：${escapeHtml(formatDateRange(item))}</div>
                ${linkLine}
            </div>
            <div class="promo-item-actions">
                <form method="POST" action="${replaceTemplate(duplicateUrlTemplate, item.id)}">
                    <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                    <button class="button secondary neutral-action" type="submit">复制</button>
                </form>
                <form method="POST" action="${replaceTemplate(toggleUrlTemplate, item.id)}">
                    <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                    <button class="button secondary neutral-action" type="submit">${toggleText}</button>
                </form>
                <button class="button secondary neutral-action" type="button" data-open-item-editor="${item.id}">编辑</button>
                <form method="POST" action="${replaceTemplate(destroyUrlTemplate, item.id)}" data-promo-item-delete-form data-promo-item-delete-name="${escapeHtml(title)}">
                    <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                    <button class="button secondary neutral-action" type="submit">删除</button>
                </form>
            </div>
        `;
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const formatErrorMessages = (messages) => {
        const normalized = [...new Set((messages || [])
            .map((message) => String(message || '').trim())
            .filter((message) => message !== '')
            .map((message) => message.replace(/[，。；、]+$/u, '')))];

        return normalized.length > 0 ? `${normalized.join('，')}。` : '保存失败，请稍后重试。';
    };

    const bindDeleteConfirmation = (form) => {
        form?.addEventListener('submit', (event) => {
            if (typeof window.showConfirmDialog !== 'function') {
                return;
            }

            event.preventDefault();
            const name = form.getAttribute('data-promo-item-delete-name') || '该图宣内容';

            window.showConfirmDialog({
                title: '确认删除图宣内容？',
                text: `删除后将解除图片引用并移除配置：${name}`,
                confirmText: '删除图宣内容',
                onConfirm: () => form.submit(),
            });
        });
    };

    const upsertCard = (item, insertAtTop = false) => {
        items.set(Number(item.id), item);

        let row = grid.querySelector(`[data-promo-item-id="${item.id}"]`);
        const isNew = !row;

        if (!row) {
            row = document.createElement('article');
            row.className = 'promo-item-card';
            row.id = `promo-item-${item.id}`;
            row.dataset.promoItemRow = '';
            row.dataset.promoItemId = String(item.id);
        }

        row.innerHTML = renderCardHtml(item);

        if (isNew) {
            if (insertAtTop && grid.firstChild) {
                grid.prepend(row);
            } else {
                grid.appendChild(row);
            }
        }

        bindDeleteConfirmation(row.querySelector('[data-promo-item-delete-form]'));
        emptyState.hidden = items.size > 0;
        grid.hidden = items.size === 0;
        updateCountBadge();
    };

    const submitDrawer = async (event) => {
        event.preventDefault();
        const formData = new FormData(editorForm);
        const targetUrl = editingItemId === null ? storeUrl : replaceTemplate(updateUrlTemplate, editingItemId);

        drawerErrors.hidden = true;
        drawerErrors.innerHTML = '';
        drawerSubmit.disabled = true;

        try {
            const response = await fetch(targetUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: formData,
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const errors = payload.errors ? Object.values(payload.errors).flat() : [payload.message || '保存失败，请稍后重试。'];
                window.showMessage?.(formatErrorMessages(errors), 'error');
                return;
            }

            upsertCard(payload.item, editingItemId === null);
            closeEditor();
            window.showMessage?.(payload.message || '图宣内容已保存。');
        } finally {
            drawerSubmit.disabled = false;
        }
    };

    const replaceImage = async (itemId, attachment) => {
        const response = await fetch(replaceTemplate(replaceImageUrlTemplate, itemId), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ attachment_id: attachment.id }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || '图宣图片更新失败，请稍后重试。');
        }

        upsertCard(payload.item);

        if (editingItemId === itemId) {
            fillForm(payload.item);
        }

        return payload;
    };

    document.querySelectorAll('[data-promo-item-delete-form]').forEach(bindDeleteConfirmation);

    document.addEventListener('click', (event) => {
        const createTrigger = event.target.closest('[data-open-create-drawer]');
        if (createTrigger) {
            openEditor(null);
            return;
        }

        const editTrigger = event.target.closest('[data-open-item-editor]');
        if (editTrigger) {
            openEditor(Number(editTrigger.getAttribute('data-open-item-editor')));
            return;
        }

        const replaceTrigger = event.target.closest('[data-replace-item-image]');
        if (replaceTrigger) {
            const itemId = Number(replaceTrigger.getAttribute('data-replace-item-image'));
            window.openSiteAttachmentLibrary?.({
                mode: 'picker',
                context: 'promo',
                imageOnly: true,
                onSelect: async (attachment) => {
                    try {
                        const payload = await replaceImage(itemId, attachment);
                        window.showMessage?.(payload.message || '图宣图片已更新。');
                    } catch (error) {
                        window.showMessage?.(error.message || '图宣图片更新失败。', 'error');
                    }
                },
            });
            return;
        }

        if (event.target.closest('[data-open-drawer-image-library]')) {
            window.openSiteAttachmentLibrary?.({
                mode: 'picker',
                context: 'promo',
                imageOnly: true,
                onSelect: (attachment) => {
                    attachmentInput.value = String(attachment.id || '');
                    renderDrawerImage(attachment.id, attachment);
                },
            });
            return;
        }

        if (event.target.closest('[data-close-item-editor]')) {
            closeEditor();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !editor.hidden) {
            closeEditor();
        }
    });

    editorForm.addEventListener('submit', submitDrawer);

    if (window.Sortable) {
        const getOrderedIds = () => Array.from(grid.querySelectorAll('[data-promo-item-row]'))
            .map((row) => Number(row.dataset.promoItemId));

        const saveReorder = async (orderedIds) => {
            const response = await fetch(grid.dataset.reorderUrl || '', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ ordered_ids: orderedIds }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload.message || '图宣内容排序保存失败，请稍后重试。');
            }

            orderedIds.forEach((itemId, index) => {
                const currentItem = items.get(itemId);
                if (currentItem) {
                    currentItem.sort = (index + 1) * 10;
                }
            });

            return payload;
        };

        Sortable.create(grid, {
            animation: 180,
            handle: '.promo-item-drag-handle',
            draggable: '[data-promo-item-row]',
            ghostClass: 'is-ghost',
            chosenClass: 'is-chosen',
            dragClass: 'is-dragging',
            async onEnd(event) {
                const row = event.item;

                if (event.oldIndex === event.newIndex) {
                    return;
                }

                row.classList.add('is-saving');

                try {
                    const payload = await saveReorder(getOrderedIds());
                    window.showMessage?.(payload.message || '图宣内容排序已保存。');
                } catch (error) {
                    window.showMessage?.(error.message || '图宣内容排序保存失败，页面将刷新恢复。', 'error');
                    window.setTimeout(() => window.location.reload(), 500);
                } finally {
                    row.classList.remove('is-saving');
                }
            },
        });
    }

    const promoItemError = (config.dataset.promoItemError || '').trim();
    if (promoItemError && typeof window.showMessage === 'function') {
        window.showMessage(promoItemError, 'error');
    }
})();
