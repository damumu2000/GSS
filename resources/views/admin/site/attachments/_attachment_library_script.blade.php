        @php
            $attachmentSystemSettings = app(\App\Support\SystemSettings::class);
            $attachmentLibraryRuleText = sprintf(
                "支持 %s\n单文件不超过 %dMB；图片不超过 %dMB；最大 %d×%d 像素。",
                strtoupper(implode(' / ', $attachmentSystemSettings->attachmentAllowedExtensions())),
                $attachmentSystemSettings->attachmentMaxSizeMb(),
                $attachmentSystemSettings->attachmentImageMaxSizeMb(),
                $attachmentSystemSettings->attachmentImageMaxWidth(),
                $attachmentSystemSettings->attachmentImageMaxHeight()
            );
        @endphp
        const attachmentLibraryRuleText = @json($attachmentLibraryRuleText);

        let attachmentLibraryEditorId = null;
        let attachmentLibraryMode = 'editor';
        let attachmentLibrarySession = null;
        let selectedAttachmentIds = [];
        let attachmentLibraryPage = 1;
        const attachmentLibraryPageSize = 9;
        let pendingImageAttachment = null;
        let attachmentLibraryWorkspaceEnabled = typeof attachmentLibraryWorkspaceAccess === 'undefined'
            ? false
            : Boolean(attachmentLibraryWorkspaceAccess);
        const attachmentLibraryFeedUrl = @json(route('admin.attachments.library-feed'));
        let attachmentLibraryLoading = false;
        let attachmentLibraryPagination = {
            page: 1,
            perPage: attachmentLibraryPageSize,
            total: 0,
            totalPages: 1,
        };

        function csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || document.querySelector('input[name="_token"]')?.value
                || '';
        }

        function isImageAttachment(attachment) {
            return ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes((attachment.extension || '').toLowerCase());
        }

        function attachmentSnippet(attachment) {
            if (isImageAttachment(attachment)) {
                return `<p><img src="${attachment.url}" alt="${attachment.name}"></p>`;
            }

            return `<p><a href="${attachment.url}" target="_blank" rel="noopener">${attachment.name}</a></p>`;
        }

        function imageSnippet(attachment, options = {}) {
            const width = options.width && options.width !== 'auto' ? `width:${options.width}%;` : '';
            const alignment = {
                left: 'margin:0 auto 0 0;',
                right: 'margin:0 0 0 auto;',
                center: 'margin:0 auto;',
            }[options.align || 'center'];
            const caption = (options.caption || '').trim();
            const radius = options.radius ? `border-radius:${options.radius}px;` : '';
            const spacing = options.spacing ? `margin-bottom:${options.spacing}px;` : '';
            const imgStyle = `${width}${alignment}${spacing}${radius}display:block;max-width:100%;height:auto;`;

            if (caption !== '') {
                return `<figure><img src="${attachment.url}" alt="${caption}" style="${imgStyle}"><figcaption style="margin-top:10px;text-align:center;color:#60756d;font-size:14px;">${caption}</figcaption></figure>`;
            }

            return `<p><img src="${attachment.url}" alt="${attachment.name}" style="${imgStyle}"></p>`;
        }

        function updateAttachmentSelectionSummary() {
            const summary = document.getElementById('attachment-library-selection');

            if (!summary) {
                return;
            }

            summary.textContent = `已选 ${selectedAttachmentIds.length} 项`;
        }

        function setAttachmentLibraryLoadingState(isLoading) {
            const grid = document.getElementById('attachment-library-grid');
            const pagination = document.getElementById('attachment-library-pagination');

            attachmentLibraryLoading = isLoading;

            if (!grid) {
                return;
            }

            if (isLoading) {
                grid.innerHTML = '<div class="attachment-library-empty">正在加载资源库...</div>';
                if (pagination) {
                    pagination.hidden = true;
                    pagination.innerHTML = '';
                }
            }
        }

        function renderAttachmentLibraryContextBar() {
            const contextBar = document.getElementById('attachment-library-contextbar');

            if (!contextBar) {
                return;
            }

            if (isAvatarLibraryMode()) {
                contextBar.innerHTML = `
                    <div class="attachment-library-singlebar">
                        <span class="muted">选择一张图片后会立即设置为头像。</span>
                    </div>
                `;
                return;
            }

            if (isCoverLibraryMode()) {
                contextBar.innerHTML = `
                    <div class="attachment-library-singlebar">
                        <span class="muted">选择一张图片后会立即设置为封面。</span>
                    </div>
                `;
                return;
            }

            contextBar.innerHTML = `
                <div class="attachment-library-bulkbar">
                    <span id="attachment-library-selection" class="muted attachment-library-selection-label">已选 0 项</span>
                    <div class="action-row">
                        <button id="attachment-library-insert-selected" class="button" type="button">批量插入</button>
                        <button id="attachment-library-clear-selected" class="button secondary" type="button">清空选择</button>
                    </div>
                </div>
            `;

            updateAttachmentSelectionSummary();
            bindAttachmentLibraryContextBarActions();
        }

        function bindAttachmentLibraryContextBarActions() {
            document.getElementById('attachment-library-insert-selected')?.addEventListener('click', insertSelectedAttachments);
            document.getElementById('attachment-library-clear-selected')?.addEventListener('click', () => {
                if (isAvatarLibraryMode() && attachmentLibrarySession?.onClear) {
                    attachmentLibrarySession.onClear();
                    closeAttachmentLibrary();
                    return;
                }

                if (isCoverLibraryMode()) {
                    clearCoverImage();
                    closeAttachmentLibrary();
                    return;
                }

                selectedAttachmentIds = [];
                updateAttachmentSelectionSummary();
                renderAttachmentLibrary();
            });
        }

        function refreshAttachmentLibrarySelects() {
            document.querySelectorAll('#attachment-library-modal [data-attachment-site-select]').forEach((selectRoot) => {
                selectRoot.__attachmentSelectRefresh?.();
            });
        }

        function attachmentLibraryFeedParams() {
            const params = new URLSearchParams();
            const imageOnly = attachmentLibraryMode === 'cover' || Boolean(attachmentLibrarySession?.imageOnly);

            params.set('mode', attachmentLibraryMode || 'editor');
            params.set('image_only', imageOnly ? '1' : '0');
            params.set('context', attachmentLibrarySession?.context || (attachmentLibraryMode === 'avatar' ? 'avatar' : 'workspace'));
            params.set('keyword', document.getElementById('attachment-library-search')?.value || '');
            params.set('filter', document.getElementById('attachment-library-filter')?.value || 'all');
            params.set('usage', document.getElementById('attachment-library-usage')?.value || 'all');
            params.set('sort', document.getElementById('attachment-library-sort')?.value || 'latest');
            params.set('page', String(attachmentLibraryPage));
            params.set('per_page', String(attachmentLibraryPageSize));

            return params;
        }

        async function reloadAttachmentLibrary(force = false) {
            if (!attachmentLibraryFeedUrl) {
                return;
            }

            if (attachmentLibraryLoading && !force) {
                return;
            }

            setAttachmentLibraryLoadingState(true);

            try {
                const response = await fetch(`${attachmentLibraryFeedUrl}?${attachmentLibraryFeedParams().toString()}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message || '资源库加载失败');
                }

                cmsAttachments = Array.isArray(payload.attachments) ? payload.attachments : [];

                if (typeof payload.workspaceAccess !== 'undefined') {
                    attachmentLibraryWorkspaceEnabled = Boolean(payload.workspaceAccess);
                }

                attachmentLibraryPagination = {
                    page: Number(payload?.pagination?.page || attachmentLibraryPage || 1),
                    perPage: Number(payload?.pagination?.perPage || attachmentLibraryPageSize),
                    total: Number(payload?.pagination?.total || cmsAttachments.length || 0),
                    totalPages: Math.max(1, Number(payload?.pagination?.totalPages || 1)),
                };
                attachmentLibraryPage = attachmentLibraryPagination.page;

                attachmentLibraryLoading = false;
                syncAttachmentLibraryUi();
                syncAttachmentSelects();
                renderAttachmentLibrary();
            } catch (error) {
                const grid = document.getElementById('attachment-library-grid');
                const pagination = document.getElementById('attachment-library-pagination');

                if (grid) {
                    grid.innerHTML = `<div class="attachment-library-empty">${error?.message || '资源库加载失败，请稍后重试。'}</div>`;
                }

                if (pagination) {
                    pagination.hidden = true;
                    pagination.innerHTML = '';
                }
            }
            finally {
                attachmentLibraryLoading = false;
            }
        }

        function isAvatarLibraryMode() {
            return attachmentLibrarySession?.mode === 'avatar';
        }

        function isCoverLibraryMode() {
            return attachmentLibraryMode === 'cover';
        }

        function isSingleSelectLibraryMode() {
            return isAvatarLibraryMode() || isCoverLibraryMode();
        }

        function canUseAttachmentWorkspaceActions() {
            return !isAvatarLibraryMode() || attachmentLibraryWorkspaceEnabled;
        }

        function clearCoverImage() {
            const input = document.getElementById('cover_image');

            if (!input) {
                return;
            }

            input.value = '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function syncAttachmentLibraryUi() {
            const title = document.getElementById('attachment-library-title');
            const headerDesc = title?.nextElementSibling;
            const searchInput = document.getElementById('attachment-library-search');
            const uploadTrigger = document.getElementById('attachment-library-upload-trigger');
            const uploadWrap = uploadTrigger?.closest('.attachment-library-upload');
            const insertSelectedButton = document.getElementById('attachment-library-insert-selected');
            const clearSelectedButton = document.getElementById('attachment-library-clear-selected');
            const panel = document.querySelector('#attachment-library-modal .attachment-library-panel');
            const usageFilterSelect = document.getElementById('attachment-library-usage');
            const usageFilterWrap = usageFilterSelect?.closest('.site-select');

            panel?.classList.toggle('is-single-select', isSingleSelectLibraryMode());
            renderAttachmentLibraryContextBar();

            if (isAvatarLibraryMode()) {
                if (headerDesc) {
                    headerDesc.textContent = attachmentLibraryWorkspaceEnabled
                        ? attachmentLibraryRuleText
                        : '仅显示当前账号可用于头像的图片资源。';
                }
                if (searchInput) {
                    searchInput.placeholder = '搜索头像图片';
                }
                if (uploadTrigger) {
                    uploadTrigger.textContent = '上传头像';
                }
                if (uploadWrap) {
                    uploadWrap.hidden = !attachmentLibraryWorkspaceEnabled;
                }
                if (usageFilterWrap) {
                    usageFilterWrap.hidden = !attachmentLibraryWorkspaceEnabled;
                }
                if (insertSelectedButton) {
                    insertSelectedButton.textContent = '插入头像';
                }
                if (clearSelectedButton) {
                    clearSelectedButton.textContent = '清除头像';
                }
                return;
            }

            if (isCoverLibraryMode()) {
                if (headerDesc) {
                    headerDesc.textContent = attachmentLibraryRuleText;
                }
                if (searchInput) {
                    searchInput.placeholder = '搜索封面图片';
                }
                if (uploadTrigger) {
                    uploadTrigger.textContent = '上传封面';
                }
                if (uploadWrap) {
                    uploadWrap.hidden = false;
                }
                if (usageFilterWrap) {
                    usageFilterWrap.hidden = false;
                }
                if (insertSelectedButton) {
                    insertSelectedButton.textContent = '插入封面';
                }
                if (clearSelectedButton) {
                    clearSelectedButton.textContent = '清除封面';
                }
                return;
            }

            if (headerDesc) {
                headerDesc.textContent = attachmentLibraryRuleText;
            }
            if (searchInput) {
                searchInput.placeholder = '搜索文件名';
            }
            if (uploadTrigger) {
                uploadTrigger.textContent = '上传新资源';
            }
            if (uploadWrap) {
                uploadWrap.hidden = false;
            }
            if (usageFilterWrap) {
                usageFilterWrap.hidden = false;
            }
            if (insertSelectedButton) {
                insertSelectedButton.textContent = '批量插入';
            }
            if (clearSelectedButton) {
                clearSelectedButton.textContent = '清空选择';
            }
        }

        function initializeAttachmentLibrarySelects() {
            document.querySelectorAll('#attachment-library-modal [data-attachment-site-select]').forEach((selectRoot) => {
                const nativeSelect = selectRoot.querySelector('.site-select-native');
                const trigger = selectRoot.querySelector('[data-select-trigger]');
                const panel = selectRoot.querySelector('[data-select-panel]');

                if (!nativeSelect || !trigger || !panel) {
                    return;
                }

                if (selectRoot.dataset.attachmentSelectInitialized === '1') {
                    selectRoot.__attachmentSelectRefresh?.();
                    return;
                }

                const buildOptions = () => {
                    panel.innerHTML = '';

                    Array.from(nativeSelect.options).forEach((option) => {
                        const optionButton = document.createElement('button');
                        optionButton.type = 'button';
                        optionButton.className = `site-select-option${option.selected ? ' is-active' : ''}`;
                        optionButton.dataset.value = option.value;
                        optionButton.innerHTML = `
                            <span>${option.textContent || ''}</span>
                            <svg class="site-select-check" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5 6.5 11.5 12.5 4.5"/></svg>
                        `;

                        optionButton.addEventListener('click', () => {
                            nativeSelect.value = option.value;
                            Array.from(nativeSelect.options).forEach((nativeOption) => {
                                nativeOption.selected = nativeOption.value === option.value;
                            });
                            trigger.textContent = option.textContent || '';
                            buildOptions();
                            selectRoot.classList.remove('is-open');
                            trigger.setAttribute('aria-expanded', 'false');
                            nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                        });

                        panel.appendChild(optionButton);
                    });
                };

                const refreshSelect = () => {
                    trigger.textContent = nativeSelect.options[nativeSelect.selectedIndex]?.textContent || '';
                    buildOptions();
                };

                trigger.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const nextState = !selectRoot.classList.contains('is-open');

                    document.querySelectorAll('#attachment-library-modal [data-attachment-site-select].is-open').forEach((openSelect) => {
                        openSelect.classList.remove('is-open');
                        openSelect.querySelector('[data-select-trigger]')?.setAttribute('aria-expanded', 'false');
                    });

                    if (nextState) {
                        selectRoot.classList.add('is-open');
                        trigger.setAttribute('aria-expanded', 'true');
                    }
                });

                document.addEventListener('click', (event) => {
                    if (!selectRoot.contains(event.target)) {
                        selectRoot.classList.remove('is-open');
                        trigger.setAttribute('aria-expanded', 'false');
                    }
                });

                nativeSelect.addEventListener('change', refreshSelect);

                refreshSelect();
                selectRoot.__attachmentSelectRefresh = refreshSelect;
                selectRoot.dataset.attachmentSelectInitialized = '1';
            });
        }

        async function deleteAttachmentFromLibrary(attachmentId) {
            const response = await fetch(
                attachmentDeleteUrlTemplate.replace('__ATTACHMENT__', String(attachmentId)),
                {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                }
            );

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(data.message || '资源删除失败');
            }

            selectedAttachmentIds = selectedAttachmentIds.filter((id) => id !== attachmentId);
            await reloadAttachmentLibrary(true);
            setAttachmentUploadStatus(data.message || '附件已删除。');
        }

        function closeAttachmentUsageModal() {
            const usageModal = document.getElementById('attachment-usage-modal');
            if (usageModal) {
                usageModal.hidden = true;
            }
        }

        function renderAttachmentUsageItems(items) {
            const usageList = document.getElementById('attachment-usage-list');
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
        }

        async function openAttachmentUsageModal(attachment) {
            const usageModal = document.getElementById('attachment-usage-modal');
            const usageDesc = document.getElementById('attachment-usage-desc');
            const usageList = document.getElementById('attachment-usage-list');
            const usageLoading = document.getElementById('attachment-usage-loading');
            const usageEmpty = document.getElementById('attachment-usage-empty');

            if (!usageModal || !usageDesc || !usageList || !usageLoading || !usageEmpty) {
                return;
            }

            usageModal.hidden = false;
            usageDesc.textContent = `正在查看：${attachment.name}`;
            usageLoading.hidden = false;
            usageList.hidden = true;
            usageEmpty.hidden = true;
            usageList.innerHTML = '';

            try {
                const response = await fetch(
                    attachmentUsageUrlTemplate.replace('__ATTACHMENT__', String(attachment.id)),
                    {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    }
                );
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

                renderAttachmentUsageItems(payload.items);
                usageList.hidden = false;
            } catch (error) {
                usageLoading.hidden = true;
                usageEmpty.hidden = false;
                usageEmpty.textContent = error?.message || '加载引用详情失败，请稍后重试。';
            }
        }

        function syncAttachmentSelects() {
            document.querySelectorAll('select[name="attachment_ids[]"]').forEach((select) => {
                const selectedValues = Array.from(select.options).filter((option) => option.selected).map((option) => option.value);
                select.innerHTML = cmsAttachments.map((attachment) => {
                    const selected = selectedValues.includes(String(attachment.id)) ? ' selected' : '';
                    const extensionLabel = attachment.extension ? ` (${attachment.extension.toUpperCase()})` : '';
                    return `<option value="${attachment.id}"${selected}>${attachment.name}${extensionLabel}</option>`;
                }).join('');
            });
        }

        function setAttachmentUploadStatus(message, isError = false) {
            const status = document.getElementById('attachment-library-upload-status');

            if (!status) {
                return;
            }

            status.textContent = message;
            status.style.color = isError ? '#c94f45' : '';
        }

        function insertAttachmentLink(targetId, fileName, fileUrl) {
            const editor = window.tinymce?.get(targetId);
            const snippet = `<p><a href="${fileUrl}" target="_blank" rel="noopener">${fileName}</a></p>`;

            if (editor) {
                editor.insertContent(snippet);
                editor.focus();
                return;
            }

            const textarea = document.getElementById(targetId);
            if (!textarea) {
                return;
            }

            const plainSnippet = `[${fileName}](${fileUrl})`;
            const current = textarea.value || '';
            const spacer = current && !current.endsWith('\n') ? '\n' : '';
            textarea.value = `${current}${spacer}${plainSnippet}`;
            textarea.focus();
        }

        function applyCoverImageFromAttachment(attachment) {
            const input = document.getElementById('cover_image');

            if (!input || !isImageAttachment(attachment)) {
                return;
            }

            input.value = attachment.url;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            closeAttachmentLibrary();
        }

        function insertAttachmentFromLibrary(attachment) {
            if (attachmentLibrarySession?.onSelect) {
                attachmentLibrarySession.onSelect(attachment);
                closeAttachmentLibrary();
                return;
            }

            if (attachmentLibraryMode === 'cover') {
                applyCoverImageFromAttachment(attachment);
                return;
            }

            const editor = attachmentLibraryEditorId ? window.tinymce?.get(attachmentLibraryEditorId) : null;

            if (editor) {
                editor.insertContent(attachmentSnippet(attachment));
                editor.focus();
            } else {
                insertAttachmentLink('content', attachment.name, attachment.url);
            }

            closeAttachmentLibrary();
        }

        function openImageInsertPanel(attachment) {
            pendingImageAttachment = attachment;
            document.getElementById('image-insert-panel')?.removeAttribute('hidden');
            document.getElementById('image-insert-width').value = '60';
            document.getElementById('image-insert-align').value = 'center';
            document.getElementById('image-insert-radius').value = '12';
            document.getElementById('image-insert-spacing').value = '20';
            document.getElementById('image-insert-caption').value = '';
            refreshAttachmentLibrarySelects();
        }

        function closeImageInsertPanel() {
            pendingImageAttachment = null;
            document.getElementById('image-insert-panel')?.setAttribute('hidden', 'hidden');
        }

        function confirmImageInsert() {
            if (!pendingImageAttachment) {
                return;
            }

            if (attachmentLibraryMode === 'cover') {
                applyCoverImageFromAttachment(pendingImageAttachment);
                closeImageInsertPanel();
                return;
            }

            const editor = attachmentLibraryEditorId ? window.tinymce?.get(attachmentLibraryEditorId) : null;
            const snippet = imageSnippet(pendingImageAttachment, {
                width: document.getElementById('image-insert-width')?.value || '60',
                align: document.getElementById('image-insert-align')?.value || 'center',
                radius: document.getElementById('image-insert-radius')?.value || '12',
                spacing: document.getElementById('image-insert-spacing')?.value || '20',
                caption: document.getElementById('image-insert-caption')?.value || '',
            });

            if (editor) {
                editor.insertContent(snippet);
                editor.focus();
            }

            closeAttachmentLibrary();
        }

        function insertSelectedAttachments() {
            if (isAvatarLibraryMode() && attachmentLibrarySession?.onClear && selectedAttachmentIds.length === 0) {
                attachmentLibrarySession.onClear();
                closeAttachmentLibrary();
                return;
            }

            if (isCoverLibraryMode() && selectedAttachmentIds.length === 0) {
                clearCoverImage();
                closeAttachmentLibrary();
                return;
            }

            if (attachmentLibrarySession?.onSelect) {
                const firstSelectedAttachment = selectedAttachmentIds
                    .map((attachmentId) => cmsAttachments.find((item) => item.id === attachmentId))
                    .find(Boolean);

                if (!firstSelectedAttachment) {
                    setAttachmentUploadStatus('请先选择至少一个资源。', true);
                    return;
                }

                attachmentLibrarySession.onSelect(firstSelectedAttachment);
                closeAttachmentLibrary();
                return;
            }

            if (attachmentLibraryMode === 'cover') {
                const firstImage = selectedAttachmentIds
                    .map((attachmentId) => cmsAttachments.find((item) => item.id === attachmentId))
                    .find((attachment) => attachment && isImageAttachment(attachment));

                if (!firstImage) {
                    setAttachmentUploadStatus('封面图模式下请选择一张图片。', true);
                    return;
                }

                applyCoverImageFromAttachment(firstImage);
                return;
            }

            const selectedAttachments = selectedAttachmentIds
                .map((attachmentId) => cmsAttachments.find((item) => item.id === attachmentId))
                .filter(Boolean);

            if (selectedAttachments.length === 0) {
                setAttachmentUploadStatus('请先选择至少一个资源。', true);
                return;
            }

            const editor = attachmentLibraryEditorId ? window.tinymce?.get(attachmentLibraryEditorId) : null;

            if (editor) {
                editor.insertContent(selectedAttachments.map((attachment) => attachmentSnippet(attachment)).join(''));
                editor.focus();
            } else {
                selectedAttachments.forEach((attachment) => {
                    insertAttachmentLink('content', attachment.name, attachment.url);
                });
            }

            closeAttachmentLibrary();
        }

        function sortedAttachments(items, sort) {
            const list = [...items];

            if (sort === 'oldest') {
                return list.sort((a, b) => {
                    const aTime = a.createdAt ? Date.parse(a.createdAt) : 0;
                    const bTime = b.createdAt ? Date.parse(b.createdAt) : 0;

                    if (aTime !== bTime) {
                        return aTime - bTime;
                    }

                    return a.id - b.id;
                });
            }

            return list.sort((a, b) => {
                const aTime = a.createdAt ? Date.parse(a.createdAt) : 0;
                const bTime = b.createdAt ? Date.parse(b.createdAt) : 0;

                if (bTime !== aTime) {
                    return bTime - aTime;
                }

                return b.id - a.id;
            });
        }

        function attachmentDimensionLabel(attachment) {
            const width = Number(attachment?.width || 0);
            const height = Number(attachment?.height || 0);

            if (width <= 0 || height <= 0) {
                return '';
            }

            return `${width}×${height}`;
        }

        function attachmentCreatedAtLabel(attachment) {
            if (!attachment?.createdAt) {
                return '--';
            }

            const date = new Date(attachment.createdAt);
            if (Number.isNaN(date.getTime())) {
                return '--';
            }

            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return `${month}-${day}`;
        }

        function renderAttachmentLibrary() {
            const grid = document.getElementById('attachment-library-grid');
            const pagination = document.getElementById('attachment-library-pagination');

            if (attachmentLibraryLoading) {
                return;
            }

            if (!grid) {
                return;
            }

            if (cmsAttachments.length === 0) {
                grid.innerHTML = '<div class="attachment-library-empty">没有找到符合条件的资源。</div>';
                if (pagination) {
                    pagination.hidden = true;
                    pagination.innerHTML = '';
                }
                return;
            }

            grid.innerHTML = cmsAttachments.map((attachment) => `
                <div class="attachment-library-card${attachment.usageCount > 0 ? ' is-used' : ''}${selectedAttachmentIds.includes(attachment.id) ? ' selected' : ''}">
                    <label class="attachment-library-select">
                        <input type="checkbox" data-attachment-select="${attachment.id}" ${selectedAttachmentIds.includes(attachment.id) ? 'checked' : ''}>
                    </label>
                    <div class="attachment-library-preview">
                        ${isImageAttachment(attachment)
                            ? `<img src="${attachment.url}" alt="${attachment.name}">`
                            : `<span>${(attachment.extension || 'FILE').toUpperCase()}</span>`}
                    </div>
                    <div class="attachment-library-meta">
                        <div class="attachment-library-name">${attachment.name}</div>
                        <div class="attachment-library-ext">${(attachment.extension || 'file').toUpperCase()}${isImageAttachment(attachment) ? ' · 图片资源' : ' · 附件链接'}${isImageAttachment(attachment) && attachmentDimensionLabel(attachment) ? ` <span class="attachment-library-dimension">· ${attachmentDimensionLabel(attachment)}</span>` : ''}</div>
                        <div class="attachment-library-usage">
                            <span>引用 ${attachment.usageCount || 0} 次</span>
                            ${attachment.usageCount > 0 && canUseAttachmentWorkspaceActions() ? `
                                <button class="attachment-library-usage-link" type="button" data-attachment-usage="${attachment.id}">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12Z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    查看
                                </button>
                            ` : ''}
                            <span class="attachment-library-submeta">${attachmentCreatedAtLabel(attachment)} · ${attachment.uploadedByName || '未记录'}</span>
                        </div>
                    </div>
                    <div class="attachment-library-actions">
                        <button class="button" type="button" data-attachment-insert="${attachment.id}">${isAvatarLibraryMode() ? '插入头像' : (isCoverLibraryMode() ? '插入封面' : (isImageAttachment(attachment) ? '插入图片' : '插入链接'))}</button>
                        <a class="button secondary" href="${attachment.url}" target="_blank">预览文件</a>
                        ${attachment.usageCount > 0 && canUseAttachmentWorkspaceActions()
                            ? '<span class="attachment-library-used-note">已引用</span>'
                            : (canUseAttachmentWorkspaceActions()
                                ? `<button class="button secondary danger-lite attachment-library-delete" type="button" data-attachment-delete="${attachment.id}">删除</button>`
                                : '')}
                    </div>
                </div>
            `).join('');

            if (pagination) {
                pagination.hidden = attachmentLibraryPagination.totalPages <= 1;
                pagination.innerHTML = attachmentLibraryPagination.totalPages > 1
                    ? renderAttachmentLibraryPagination(attachmentLibraryPagination.totalPages)
                    : '';
            }

            grid.querySelectorAll('[data-attachment-insert]').forEach((button) => {
                button.addEventListener('click', () => {
                    const attachmentId = Number(button.getAttribute('data-attachment-insert'));
                    const attachment = cmsAttachments.find((item) => item.id === attachmentId);

                    if (attachment) {
                        if (attachmentLibrarySession?.onSelect) {
                            attachmentLibrarySession.onSelect(attachment);
                            closeAttachmentLibrary();
                            return;
                        }

                        if (attachmentLibraryMode !== 'cover' && isImageAttachment(attachment)) {
                            openImageInsertPanel(attachment);
                        } else {
                            insertAttachmentFromLibrary(attachment);
                        }
                    }
                });
            });

            grid.querySelectorAll('[data-attachment-select]').forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    const attachmentId = Number(checkbox.getAttribute('data-attachment-select'));

                    if (checkbox.checked) {
                        selectedAttachmentIds = isSingleSelectLibraryMode()
                            ? [attachmentId]
                            : [...new Set([...selectedAttachmentIds, attachmentId])];
                    } else {
                        selectedAttachmentIds = selectedAttachmentIds.filter((id) => id !== attachmentId);
                    }

                    updateAttachmentSelectionSummary();
                    renderAttachmentLibrary();
                });
            });

            grid.querySelectorAll('[data-attachment-delete]').forEach((button) => {
                button.addEventListener('click', () => {
                    const attachmentId = Number(button.getAttribute('data-attachment-delete'));
                    const attachment = cmsAttachments.find((item) => item.id === attachmentId);

                    if (!attachment) {
                        return;
                    }

                    const executeDelete = async () => {
                        try {
                            await deleteAttachmentFromLibrary(attachmentId);
                            if (typeof window.closeConfirmDialog === 'function') {
                                window.closeConfirmDialog();
                            }
                        } catch (error) {
                            setAttachmentUploadStatus(error.message || '资源删除失败', true);
                            if (typeof window.closeConfirmDialog === 'function') {
                                window.closeConfirmDialog();
                            }
                        }
                    };

                    if (typeof window.showConfirmDialog === 'function') {
                        window.showConfirmDialog({
                            title: '确认删除该资源？',
                            text: `删除后将无法恢复：${attachment.name}`,
                            confirmText: '删除资源',
                            onConfirm: executeDelete,
                        });
                        return;
                    }

                    if (window.confirm(`确认删除资源“${attachment.name}”吗？`)) {
                        executeDelete();
                    }
                });
            });

            grid.querySelectorAll('[data-attachment-usage]').forEach((button) => {
                button.addEventListener('click', () => {
                    const attachmentId = Number(button.getAttribute('data-attachment-usage'));
                    const attachment = cmsAttachments.find((item) => item.id === attachmentId);

                    if (!attachment) {
                        return;
                    }

                    openAttachmentUsageModal(attachment);
                });
            });

            updateAttachmentSelectionSummary();

            pagination?.querySelectorAll('[data-attachment-page]').forEach((button) => {
                button.addEventListener('click', async () => {
                    attachmentLibraryPage = Number(button.getAttribute('data-attachment-page')) || 1;
                    await reloadAttachmentLibrary(true);
                });
            });

            pagination?.querySelector('[data-attachment-prev]')?.addEventListener('click', async () => {
                if (attachmentLibraryPage > 1) {
                    attachmentLibraryPage -= 1;
                    await reloadAttachmentLibrary(true);
                }
            });

            pagination?.querySelector('[data-attachment-next]')?.addEventListener('click', async () => {
                if (attachmentLibraryPage < attachmentLibraryPagination.totalPages) {
                    attachmentLibraryPage += 1;
                    await reloadAttachmentLibrary(true);
                }
            });
        }

        function renderAttachmentLibraryPagination(totalPages) {
            const pages = [];
            const start = Math.max(1, attachmentLibraryPage - 2);
            const end = Math.min(totalPages, start + 4);
            const normalizedStart = Math.max(1, end - 4);

            for (let page = normalizedStart; page <= end; page += 1) {
                pages.push(page);
            }

            const pageMarkup = pages.map((page) => `
                <button class="pagination-page${page === attachmentLibraryPage ? ' is-active' : ''}" type="button" data-attachment-page="${page}">${page}</button>
            `).join('');

            return `
                <nav aria-label="资源库分页">
                    <div class="pagination-shell">
                        <button class="pagination-button${attachmentLibraryPage === 1 ? ' is-disabled' : ''}" type="button" data-attachment-prev>
                            <svg class="pagination-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"></path></svg>
                            上一页
                        </button>
                        <div class="pagination-pages">${pageMarkup}</div>
                        <button class="pagination-button${attachmentLibraryPage === totalPages ? ' is-disabled' : ''}" type="button" data-attachment-next>
                            下一页
                            <svg class="pagination-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"></path></svg>
                        </button>
                    </div>
                </nav>
            `;
        }

        async function openSiteAttachmentLibraryInternal(editorId = 'content', mode = 'editor') {
            const options = typeof editorId === 'object' && editorId !== null ? editorId : null;

            attachmentLibrarySession = options ? {
                editorId: options.editorId || 'content',
                mode: options.mode || 'picker',
                context: options.context || (options.mode === 'avatar' ? 'avatar' : 'workspace'),
                imageOnly: Boolean(options.imageOnly),
                onSelect: typeof options.onSelect === 'function' ? options.onSelect : null,
                onClose: typeof options.onClose === 'function' ? options.onClose : null,
            } : null;

            attachmentLibraryEditorId = attachmentLibrarySession?.editorId || editorId;
            attachmentLibraryMode = attachmentLibrarySession?.mode || mode;
            const modal = document.getElementById('attachment-library-modal');
            const filterSelect = document.getElementById('attachment-library-filter');
            const fileInput = document.getElementById('attachment-library-file');

            if (!modal) {
                return;
            }

            modal.hidden = false;
            document.body.style.overflow = 'hidden';
            selectedAttachmentIds = [];
            attachmentLibraryPage = 1;
            closeImageInsertPanel();
            setAttachmentUploadStatus('');
            if (filterSelect) {
                filterSelect.value = (attachmentLibraryMode === 'cover' || attachmentLibrarySession?.imageOnly) ? 'image' : 'all';
            }
            if (fileInput) {
                fileInput.setAttribute('accept', (attachmentLibraryMode === 'cover' || attachmentLibrarySession?.imageOnly) ? 'image/*' : '');
            }
            syncAttachmentLibraryUi();
            initializeAttachmentLibrarySelects();
            await reloadAttachmentLibrary(true);
            document.getElementById('attachment-library-search')?.focus();
        }

        function closeAttachmentLibrary() {
            const modal = document.getElementById('attachment-library-modal');
            const filterSelect = document.getElementById('attachment-library-filter');
            const fileInput = document.getElementById('attachment-library-file');

            if (!modal) {
                return;
            }

            modal.hidden = true;
            document.body.style.overflow = '';
            selectedAttachmentIds = [];
            attachmentLibraryMode = 'editor';
            const session = attachmentLibrarySession;
            attachmentLibrarySession = null;
            attachmentLibraryPage = 1;
            if (filterSelect) {
                filterSelect.value = 'all';
            }
            if (fileInput) {
                fileInput.removeAttribute('accept');
            }
            closeImageInsertPanel();
            setAttachmentUploadStatus('');
            updateAttachmentSelectionSummary();
            syncAttachmentLibraryUi();
            session?.onClose?.();
        }

        async function uploadAttachmentToLibrary(file) {
            const formData = new FormData();
            formData.append('file', file);
            setAttachmentUploadStatus(`正在上传 ${file.name}...`);

            const response = await fetch('{{ route('admin.attachments.library-upload') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
                credentials: 'same-origin',
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.attachment) {
                throw new Error(data.message || '资源上传失败');
            }

            await reloadAttachmentLibrary(true);
            setAttachmentUploadStatus(`已上传：${data.attachment.name}`);

            return data.attachment;
        }

        function initializeAttachmentLibrary() {
            window.openSiteAttachmentLibrary = function (options = {}) {
                return openSiteAttachmentLibraryInternal(options);
            };
            window.closeSiteAttachmentLibrary = function () {
                return closeAttachmentLibrary();
            };

            document.querySelectorAll('[data-open-cover-library]').forEach((element) => {
                element.addEventListener('click', () => {
                    window.openSiteAttachmentLibrary({
                        editorId: 'content',
                        mode: 'cover',
                        context: 'content',
                        imageOnly: true,
                    });
                });
            });

            document.querySelectorAll('[data-close-attachment-library]').forEach((element) => {
                element.addEventListener('click', closeAttachmentLibrary);
            });
            document.querySelectorAll('[data-close-attachment-usage]').forEach((element) => {
                element.addEventListener('click', closeAttachmentUsageModal);
            });

            document.getElementById('attachment-library-search')?.addEventListener('input', () => {
                attachmentLibraryPage = 1;
                reloadAttachmentLibrary(true);
            });
            document.getElementById('attachment-library-filter')?.addEventListener('change', () => {
                attachmentLibraryPage = 1;
                reloadAttachmentLibrary(true);
            });
            document.getElementById('attachment-library-usage')?.addEventListener('change', () => {
                attachmentLibraryPage = 1;
                reloadAttachmentLibrary(true);
            });
            document.getElementById('attachment-library-sort')?.addEventListener('change', () => {
                attachmentLibraryPage = 1;
                reloadAttachmentLibrary(true);
            });
            document.getElementById('image-insert-confirm')?.addEventListener('click', confirmImageInsert);
            document.getElementById('image-insert-cancel')?.addEventListener('click', closeImageInsertPanel);
            document.getElementById('attachment-library-upload-trigger')?.addEventListener('click', () => {
                document.getElementById('attachment-library-file')?.click();
            });
            document.getElementById('attachment-library-file')?.addEventListener('change', async (event) => {
                const input = event.target;
                const file = input.files?.[0];

                if (!file) {
                    return;
                }

                try {
                    const attachment = await uploadAttachmentToLibrary(file);
                    insertAttachmentFromLibrary(attachment);
                } catch (error) {
                    setAttachmentUploadStatus(error.message || '资源上传失败', true);
                } finally {
                    input.value = '';
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeAttachmentLibrary();
                    closeAttachmentUsageModal();
                }
            });

            syncAttachmentLibraryUi();
        }

        initializeAttachmentLibrary();
