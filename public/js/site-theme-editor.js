(() => {
    const configRoot = document.getElementById('theme-editor-config');
    const templateTreePanel = document.querySelector('[data-template-tree-panel]');
    const parseJson = (value, fallback) => {
        if (!value) {
            return fallback;
        }

        try {
            return JSON.parse(value);
        } catch (error) {
            return fallback;
        }
    };

    const serverEditorErrors = parseJson(configRoot?.dataset.serverEditorErrors, []);
    const serverCreateErrors = parseJson(configRoot?.dataset.serverCreateErrors, []);
    const compareCleanUrl = configRoot?.dataset.compareCleanUrl || '';
    const editor = document.getElementById('template_source');
    const gutter = document.getElementById('template_source_gutter');
    const resetForm = document.getElementById('theme-reset-form');
    const deleteForm = document.getElementById('theme-delete-form');
    const rollbackForm = document.getElementById('theme-rollback-form');
    const modal = document.querySelector('[data-editor-modal]');
    let themeAssetsModal = document.querySelector('[data-theme-assets-modal]');
    const themeAssetPreviewModal = document.querySelector('[data-theme-asset-preview-modal]');
    const themeAssetPreviewImage = document.querySelector('[data-theme-asset-preview-image]');
    const themeAssetPreviewCaption = document.querySelector('[data-theme-asset-preview-caption]');
    const openButtons = document.querySelectorAll('[data-open-editor-modal]');
    const openThemeAssetsButtons = document.querySelectorAll('[data-open-theme-assets-modal]');
    const closeButtons = document.querySelectorAll('[data-close-editor-modal]');
    const closeThemeAssetsButtons = document.querySelectorAll('[data-close-theme-assets-modal]');
    const editorForm = document.getElementById('theme-editor-form');
    const titleInput = document.getElementById('template_title');
    const editorBootstrap = document.getElementById('template_source_bootstrap');
    let initialTitle = titleInput ? titleInput.value : '';
    let initialSource = editor ? editor.value : '';
    let editorViewportFrame = null;
    let lineNumberFrame = null;
    let lastLineCount = 0;
    let cachedLineElements = [];
    let lastSelectionRange = { start: 0, end: 0 };
    let themeAssetsRequestId = 0;

    const validateTemplateTitleLimit = (input) => {
        if (!input) {
            return true;
        }

        const limit = Number.parseInt(input.getAttribute('data-template-title-limit') || '0', 10);
        const value = (input.value || '').trim();
        const isValid = limit <= 0 || value.length <= limit;
        input.classList.toggle('is-error', !isValid);

        if (!isValid && typeof window.showMessage === 'function') {
            window.showMessage(`模板标题不能超过 ${limit} 个字。`, 'error');
        }

        return isValid;
    };

    const normalizeTemplateSuffix = (value, { finalize = false } = {}) => {
        const source = String(value || '').toLowerCase();
        let normalized = '';

        for (const char of source) {
            if (/[a-z0-9]/.test(char)) {
                normalized += char;
                continue;
            }

            if ((char === '-' || char === '_') && normalized !== '' && /[a-z0-9]$/.test(normalized)) {
                normalized += char;
            }
        }

        return finalize
            ? normalized.replace(/[-_]+$/g, '')
            : normalized;
    };

    const bindTemplateSuffixInput = (input) => {
        if (!input) {
            return;
        }

        const sanitizeValue = ({ finalize = false } = {}) => {
            const normalized = normalizeTemplateSuffix(input.value, { finalize });

            if (input.value !== normalized) {
                const nextCursor = Math.min(normalized.length, input.selectionStart ?? normalized.length);
                input.value = normalized;
                input.setSelectionRange(nextCursor, nextCursor);
            }
        };

        input.addEventListener('input', () => sanitizeValue());
        input.addEventListener('blur', () => sanitizeValue({ finalize: true }));
        sanitizeValue({ finalize: true });
    };

    if (serverEditorErrors.length > 0 && typeof window.showMessage === 'function') {
        window.showMessage(serverEditorErrors.join('，'), 'error');
    }

    if (serverCreateErrors.length > 0 && typeof window.showMessage === 'function') {
        window.showMessage(serverCreateErrors.join('，'), 'error');
    }

    document.querySelectorAll('[data-custom-select]').forEach((selectRoot) => {
        const nativeSelect = selectRoot.querySelector('[data-select-native]');
        const trigger = selectRoot.querySelector('[data-select-trigger]');
        const label = selectRoot.querySelector('[data-select-label]');
        const options = selectRoot.querySelectorAll('[data-select-option]');

        if (!nativeSelect || !trigger || !label) {
            return;
        }

        const close = () => {
            selectRoot.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        };

        const open = () => {
            document.querySelectorAll('[data-custom-select].is-open').forEach((opened) => {
                if (opened !== selectRoot) {
                    opened.classList.remove('is-open');
                    opened.querySelector('[data-select-trigger]')?.setAttribute('aria-expanded', 'false');
                }
            });

            selectRoot.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
        };

        trigger.addEventListener('click', () => {
            if (selectRoot.classList.contains('is-open')) {
                close();
            } else {
                open();
            }
        });

        options.forEach((option) => {
            option.addEventListener('click', () => {
                const value = option.getAttribute('data-value') || '';
                nativeSelect.value = value;
                label.textContent = option.querySelector('span')?.textContent || value;
                options.forEach((item) => item.classList.remove('is-active'));
                option.classList.add('is-active');
                close();
                nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });

        document.addEventListener('click', (event) => {
            if (!selectRoot.contains(event.target)) {
                close();
            }
        });
    });


    const syncBodyScroll = () => {
        const compareModal = document.querySelector('[data-history-compare-modal]');
        const compareModalOpen = Boolean(compareModal && !compareModal.hidden && compareModal.classList.contains('is-ready'));
        const themeAssetsOpen = Boolean(themeAssetsModal && themeAssetsModal.classList.contains('is-open'));
        const themeAssetPreviewOpen = Boolean(themeAssetPreviewModal && !themeAssetPreviewModal.hidden);
        document.body.classList.toggle('has-modal-open', (modal && modal.classList.contains('is-open')) || themeAssetsOpen || themeAssetPreviewOpen || compareModalOpen);
    };

    const GUTTER_LINE_HEIGHT = 24;
    const GUTTER_BUFFER_LINES = 6;

    const countLines = (value) => {
        if (!value) {
            return 1;
        }

        let total = 1;
        for (let index = 0; index < value.length; index += 1) {
            if (value.charCodeAt(index) === 10) {
                total += 1;
            }
        }
        return total;
    };

    const renderLineNumbers = () => {
        if (!editor || !gutter) {
            return;
        }

        const total = Math.max(countLines(editor.value), 1);
        const gutterInner = document.getElementById('template_source_gutter');
        if (!gutterInner) {
            return;
        }
        const viewportHeight = editor.clientHeight || 0;
        const scrollTop = editor.scrollTop || 0;
        const visibleStart = Math.max(1, Math.floor(scrollTop / GUTTER_LINE_HEIGHT) + 1 - GUTTER_BUFFER_LINES);
        const visibleEnd = Math.min(
            total,
            Math.ceil((scrollTop + viewportHeight) / GUTTER_LINE_HEIGHT) + GUTTER_BUFFER_LINES
        );

        if (total === lastLineCount && cachedLineElements.length
            && cachedLineElements[0]?.dataset.line === String(visibleStart)
            && cachedLineElements[cachedLineElements.length - 1]?.dataset.line === String(visibleEnd)) {
            return;
        }

        lastLineCount = total;
        const translateOffset = (visibleStart - 1) * GUTTER_LINE_HEIGHT - scrollTop;
        gutterInner.style.transform = `translateY(${translateOffset}px)`;
        gutterInner.innerHTML = Array.from({ length: visibleEnd - visibleStart + 1 }, (_, index) => {
            const lineNumber = visibleStart + index;
            return `<span class="code-editor-gutter-line" data-line="${lineNumber}">${lineNumber}</span>`;
        }).join('');
        cachedLineElements = Array.from(gutterInner.querySelectorAll('.code-editor-gutter-line'));
        lastSelectionRange = { start: 0, end: 0 };
        syncEditorSelectionHighlight();
    };

    const requestLineNumberRender = () => {
        if (lineNumberFrame !== null) {
            return;
        }

        lineNumberFrame = window.requestAnimationFrame(() => {
            lineNumberFrame = null;
            renderLineNumbers();
        });
    };

    const syncEditorGutterScroll = () => {
        if (!editor || !gutter) {
            return;
        }
        gutter.scrollTop = editor.scrollTop;
    };

    const syncEditorViewportState = () => {
        syncEditorGutterScroll();
        renderLineNumbers();
        syncEditorSelectionHighlight();
    };

    const requestEditorViewportSync = () => {
        if (editorViewportFrame !== null) {
            return;
        }

        editorViewportFrame = window.requestAnimationFrame(() => {
            editorViewportFrame = null;
            syncEditorViewportState();
        });
    };

    const getLineFromOffset = (offset) => {
        if (!editor || !gutter) {
            return 1;
        }

        const normalizedOffset = Math.max(0, Math.min(editor.value.length, offset ?? 0));
        let total = 1;
        for (let index = 0; index < normalizedOffset; index += 1) {
            if (editor.value.charCodeAt(index) === 10) {
                total += 1;
            }
        }
        return Math.max(1, total);
    };

    const syncEditorSelectionHighlight = () => {
        if (!editor || !gutter) {
            return;
        }

        const startLine = getLineFromOffset(editor.selectionStart ?? 0);
        const endLine = getLineFromOffset(editor.selectionEnd ?? editor.selectionStart ?? 0);
        if (startLine === lastSelectionRange.start && endLine === lastSelectionRange.end) {
            return;
        }

        cachedLineElements.forEach((lineElement) => {
            const lineNumber = Number.parseInt(lineElement.dataset.line || '0', 10);
            const isActive = lineNumber >= startLine && lineNumber <= endLine;
            lineElement.classList.toggle('is-active', isActive);
        });
        lastSelectionRange = { start: startLine, end: endLine };
    };

    const hasUnsavedChanges = () => {
        if (!editor || !titleInput) {
            return false;
        }

        return editor.value !== initialSource || titleInput.value !== initialTitle;
    };

    const resetEditorDirtyBaseline = () => {
        if (!editor || !titleInput) {
            return;
        }

        initialSource = editor.value;
        initialTitle = titleInput.value;
    };

    const hydrateEditorSource = () => {
        if (!editor || !editorBootstrap || editor.value) {
            return;
        }

        editor.value = editorBootstrap.value || '';
        resetEditorDirtyBaseline();
        window.requestAnimationFrame(() => {
            editor.scrollTop = 0;
            editor.scrollLeft = 0;
            editor.setSelectionRange(0, 0);
            requestLineNumberRender();
            syncEditorViewportState();
        });
    };

    const closeModal = () => {
        if (!modal) {
            return;
        }

        if (hasUnsavedChanges()) {
            if (typeof window.showConfirmDialog === 'function') {
                window.showConfirmDialog({
                    title: '确认关闭源码编辑？',
                    text: '当前有未保存的修改，关闭后这些修改将不会保留。',
                    confirmText: '仍然关闭',
                    onConfirm: () => {
                        modal.classList.remove('is-open');
                        syncBodyScroll();
                    },
                });
                return;
            }

            if (!window.confirm('当前有未保存的修改，确认关闭源码编辑吗？')) {
                return;
            }
        }

        modal.classList.remove('is-open');
        syncBodyScroll();
    };

    if (editor && gutter) {
        hydrateEditorSource();
        editor.addEventListener('input', requestLineNumberRender);
        editor.addEventListener('scroll', requestEditorViewportSync, { passive: true });
        editor.addEventListener('click', syncEditorSelectionHighlight);
        editor.addEventListener('keyup', syncEditorSelectionHighlight);
        editor.addEventListener('focus', syncEditorSelectionHighlight);
        editor.addEventListener('mouseup', syncEditorSelectionHighlight);
        editor.addEventListener('select', syncEditorSelectionHighlight);
        requestLineNumberRender();
    }

    const insertAtCursor = (textarea, text) => {
        if (!textarea) {
            return;
        }

        const start = textarea.selectionStart ?? textarea.value.length;
        const end = textarea.selectionEnd ?? textarea.value.length;
        const current = textarea.value || '';
        textarea.value = `${current.slice(0, start)}${text}${current.slice(end)}`;
        const nextPosition = start + text.length;
        textarea.selectionStart = nextPosition;
        textarea.selectionEnd = nextPosition;
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.focus();
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            modal?.classList.add('is-open');
            syncBodyScroll();
            window.setTimeout(() => {
                hydrateEditorSource();
                resetEditorDirtyBaseline();
                requestLineNumberRender();
                syncEditorViewportState();
                editor?.focus();
                window.requestAnimationFrame(() => {
                    editor.scrollTop = 0;
                    editor.scrollLeft = 0;
                    editor.setSelectionRange(0, 0);
                    requestLineNumberRender();
                    syncEditorViewportState();
                });
            }, 30);
        });
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    const setThemeAssetsMode = (mode = 'manage') => {
        if (!themeAssetsModal) {
            return;
        }

        const nextMode = mode === 'insert' ? 'insert' : 'manage';
        themeAssetsModal.dataset.mode = nextMode;
        themeAssetsModal.classList.toggle('is-manage-mode', nextMode === 'manage');
    };

    const openThemeAssetsModal = (mode = 'manage') => {
        if (!themeAssetsModal) {
            return;
        }

        if (themeAssetsModal.dataset.themeAssetsReady !== '1') {
            const nextUrl = new URL(window.location.href, window.location.origin);
            nextUrl.searchParams.set('open_assets', '1');
            nextUrl.searchParams.set('open_assets_mode', mode === 'insert' ? 'insert' : 'manage');
            nextUrl.searchParams.set('_theme_assets_refresh', String(Date.now()));
            fetchThemeAssetsModal(nextUrl.toString());
            return;
        }

        setThemeAssetsMode(mode);
        themeAssetsModal.classList.add('is-open');
        syncBodyScroll();
    };

    const closeThemeAssetsModal = () => {
        if (!themeAssetsModal) {
            return;
        }

        themeAssetsModal.classList.remove('is-open');
        syncBodyScroll();

        const nextUrl = new URL(window.location.href, window.location.origin);
        if (nextUrl.searchParams.has('open_assets')) {
            nextUrl.searchParams.delete('open_assets');
            nextUrl.searchParams.delete('open_assets_mode');
            nextUrl.searchParams.delete('asset_page');
            nextUrl.searchParams.delete('_theme_assets_refresh');
            nextUrl.searchParams.delete('asset_keyword');
            nextUrl.searchParams.delete('asset_type');
            window.history.replaceState({}, document.title, nextUrl.toString());
        }
    };

    const openThemeAssetPreviewModal = (url, name = '') => {
        if (!themeAssetPreviewModal || !themeAssetPreviewImage) {
            return;
        }

        themeAssetPreviewImage.src = url || '';
        themeAssetPreviewImage.alt = name || '模板资源预览';

        if (themeAssetPreviewCaption) {
            themeAssetPreviewCaption.textContent = name || '';
        }

        themeAssetPreviewModal.hidden = false;
        syncBodyScroll();
    };

    const closeThemeAssetPreviewModal = () => {
        if (!themeAssetPreviewModal || !themeAssetPreviewImage) {
            return;
        }

        themeAssetPreviewModal.hidden = true;
        themeAssetPreviewImage.src = '';
        themeAssetPreviewImage.alt = '';

        if (themeAssetPreviewCaption) {
            themeAssetPreviewCaption.textContent = '';
        }

        syncBodyScroll();
    };

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value
        || '';

    const setThemeAssetsLoadingState = (isLoading) => {
        if (!themeAssetsModal) {
            return;
        }

        themeAssetsModal.classList.toggle('is-loading', isLoading);
    };

    const themeAssetsRefreshUrl = () => {
        const nextUrl = new URL(window.location.href, window.location.origin);
        nextUrl.searchParams.set('open_assets', '1');
        nextUrl.searchParams.set('open_assets_mode', themeAssetsModal?.dataset.mode === 'insert' ? 'insert' : 'manage');
        nextUrl.searchParams.set('_theme_assets_refresh', String(Date.now()));
        return nextUrl.toString();
    };

    const buildThemeAssetsFormData = (form) => {
        const formData = new FormData(form);

        if (!formData.has('_token')) {
            formData.append('_token', csrfToken());
        }

        if (!formData.has('open_assets')) {
            formData.append('open_assets', '1');
        }

        if (!formData.has('open_assets_mode')) {
            formData.append('open_assets_mode', themeAssetsModal?.dataset.mode === 'insert' ? 'insert' : 'manage');
        }

        const searchValue = themeAssetsModal?.querySelector('[data-theme-assets-search]')?.value || '';
        const typeValue = themeAssetsModal?.querySelector('[data-theme-assets-type]')?.value || '';
        if (searchValue !== '') {
            formData.append('asset_keyword', searchValue);
        }
        if (typeValue !== '') {
            formData.append('asset_type', typeValue);
        }

        return formData;
    };

    const buildThemeAssetsUploadFormData = (form, file) => {
        const formData = new FormData();
        const template = form.querySelector('input[name="template"]')?.value || '';
        const openAssets = form.querySelector('input[name="open_assets"]')?.value || '1';
        const openAssetsMode = themeAssetsModal?.dataset.mode === 'insert' ? 'insert' : 'manage';
        const searchValue = themeAssetsModal?.querySelector('[data-theme-assets-search]')?.value || '';
        const typeValue = themeAssetsModal?.querySelector('[data-theme-assets-type]')?.value || '';

        formData.append('_token', csrfToken());
        formData.append('template', template);
        formData.append('open_assets', openAssets);
        formData.append('open_assets_mode', openAssetsMode);
        if (searchValue !== '') {
            formData.append('asset_keyword', searchValue);
        }
        if (typeValue !== '') {
            formData.append('asset_type', typeValue);
        }

        if (file) {
            formData.append('asset', file);
        }

        return formData;
    };

    const buildThemeAssetsReplaceFormData = (form, file) => {
        const formData = buildThemeAssetsUploadFormData(form, file);
        const replaceAssetPath = form.querySelector('input[name="replace_asset_path"]')?.value || '';

        if (replaceAssetPath !== '') {
            formData.append('replace_asset_path', replaceAssetPath);
        }

        return formData;
    };

    const replaceThemeAssetsModal = (html) => {
        const parser = new DOMParser();
        const documentNode = parser.parseFromString(html, 'text/html');
        const nextModal = documentNode.querySelector('[data-theme-assets-modal]');

        if (!themeAssetsModal || !nextModal) {
            return false;
        }

        themeAssetsModal.replaceWith(nextModal);
        themeAssetsModal = nextModal;
        themeAssetsModal.dataset.themeAssetsReady = '1';
        setThemeAssetsMode(themeAssetsModal.dataset.mode || 'manage');
        themeAssetsModal.classList.add('is-open');
        syncBodyScroll();

        return true;
    };

    const triggerThemeAssetsSearch = () => {
        if (!themeAssetsModal) {
            return;
        }

        const searchValue = themeAssetsModal.querySelector('[data-theme-assets-search]')?.value || '';
        const typeValue = themeAssetsModal.querySelector('[data-theme-assets-type]')?.value || 'all';
        const nextUrl = new URL(window.location.href, window.location.origin);
        nextUrl.searchParams.set('open_assets', '1');
        nextUrl.searchParams.set('open_assets_mode', themeAssetsModal?.dataset.mode === 'insert' ? 'insert' : 'manage');
        nextUrl.searchParams.set('asset_page', '1');
        nextUrl.searchParams.set('_theme_assets_refresh', String(Date.now()));

        if (searchValue.trim() !== '') {
            nextUrl.searchParams.set('asset_keyword', searchValue.trim());
        } else {
            nextUrl.searchParams.delete('asset_keyword');
        }

        if (typeValue && typeValue !== 'all') {
            nextUrl.searchParams.set('asset_type', typeValue);
        } else {
            nextUrl.searchParams.delete('asset_type');
        }

        fetchThemeAssetsModal(nextUrl.toString());
    };

    const themeAssetsErrorMessage = () => {
        if (!themeAssetsModal) {
            return '';
        }

        const message = themeAssetsModal.querySelector('.form-error');

        return message?.textContent?.trim() || '';
    };

    const fetchThemeAssetsModal = async (url, options = {}) => {
        if (!url) {
            return;
        }

        const requestId = ++themeAssetsRequestId;
        setThemeAssetsLoadingState(true);

        try {
            const response = await fetch(url, {
                method: options.method || 'GET',
                body: options.body || null,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'text/html, application/xhtml+xml',
                    ...(options.headers || {}),
                },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (response.status === 419) {
                throw new Error('登录状态已失效，请刷新页面后重试。');
            }

            const html = await response.text();

            if (requestId !== themeAssetsRequestId) {
                return;
            }

            if (!replaceThemeAssetsModal(html)) {
                const nextUrl = new URL(window.location.href, window.location.origin);
                nextUrl.searchParams.set('open_assets', '1');
                window.location.href = nextUrl.toString();
                return;
            }

            const errorMessage = themeAssetsErrorMessage();
            if (errorMessage !== '') {
                window.showMessage?.(errorMessage, 'error');
                return;
            }

            if (options.successMessage) {
                window.showMessage?.(options.successMessage);
            }
        } catch (error) {
            window.showMessage?.(error?.message || '模板资源操作失败，请稍后再试。', 'error');
        } finally {
            if (requestId === themeAssetsRequestId) {
                setThemeAssetsLoadingState(false);
            }
        }
    };

    document.addEventListener('submit', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLFormElement)) {
            return;
        }

        if (target.matches('[data-theme-assets-upload-form]')) {
            event.preventDefault();
            const formData = buildThemeAssetsFormData(target);
            fetchThemeAssetsModal(target.action, {
                method: 'POST',
                body: formData,
                successMessage: '模板资源已上传。',
            });
            return;
        }

        if (target.matches('[data-theme-assets-delete-form]')) {
            event.preventDefault();
            const formData = buildThemeAssetsFormData(target);
            fetchThemeAssetsModal(target.action, {
                method: 'POST',
                body: formData,
                successMessage: '模板资源已删除。',
            });
        }
    });

    document.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        const closeTrigger = target.closest('[data-close-theme-assets-modal]');
        if (closeTrigger && !target.closest('[data-open-theme-assets-modal]')) {
            closeThemeAssetsModal();
            return;
        }

        const openTrigger = target.closest('[data-open-theme-assets-modal]');
        if (openTrigger) {
            openThemeAssetsModal(openTrigger.getAttribute('data-theme-assets-mode') || 'manage');
            return;
        }

        const insertTrigger = target.closest('[data-insert-theme-asset]');
        if (insertTrigger) {
            const assetPath = (insertTrigger.getAttribute('data-asset-path') || '').trim();

            if (assetPath !== '') {
                insertAtCursor(editor, assetPath);
                closeThemeAssetsModal();
                window.showMessage?.('模板资源路径已插入源码。');
            }

            return;
        }

        const previewTrigger = target.closest('[data-theme-asset-preview-trigger]');
        if (previewTrigger) {
            openThemeAssetPreviewModal(
                previewTrigger.getAttribute('data-asset-url') || '',
                previewTrigger.getAttribute('data-asset-name') || ''
            );
            return;
        }

        const replaceTrigger = target.closest('[data-theme-asset-replace-trigger]');
        if (replaceTrigger) {
            const form = replaceTrigger.closest('form');
            const input = form?.querySelector('[data-theme-asset-replace-input]');
            if (input) {
                input.value = '';
            }
            input?.click();
            return;
        }

        const uploadTrigger = target.closest('[data-theme-assets-upload-trigger]');
        if (uploadTrigger) {
            const form = uploadTrigger.closest('[data-theme-assets-upload-form]');
            const input = form?.querySelector('[data-theme-assets-file-input]');
            if (input) {
                input.value = '';
            }
            input?.click();
            return;
        }

        if (target.closest('[data-close-theme-asset-preview]')) {
            closeThemeAssetPreviewModal();
            return;
        }

        const paginationLink = target.closest('.theme-assets-pagination a');
        if (paginationLink instanceof HTMLAnchorElement) {
            event.preventDefault();
            fetchThemeAssetsModal(paginationLink.href);
        }

        const searchTrigger = target.closest('[data-theme-assets-search-trigger]');
        if (searchTrigger) {
            triggerThemeAssetsSearch();
        }

        const resetTrigger = target.closest('[data-theme-assets-reset-trigger]');
        if (resetTrigger && themeAssetsModal) {
            const searchInput = themeAssetsModal.querySelector('[data-theme-assets-search]');
            const typeSelect = themeAssetsModal.querySelector('[data-theme-assets-type]');
            if (searchInput) {
                searchInput.value = '';
            }
            if (typeSelect) {
                typeSelect.value = 'all';
            }
            triggerThemeAssetsSearch();
        }
    });

    document.addEventListener('change', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLInputElement) && !(target instanceof HTMLSelectElement)) {
            return;
        }

        if (target.matches('[data-theme-assets-type]')) {
            return;
        }

        if (target.matches('[data-theme-asset-replace-input]')) {
            if (!target.files || target.files.length === 0) {
                return;
            }

            const form = target.closest('form');

            if (!form) {
                return;
            }

            const formData = buildThemeAssetsReplaceFormData(form, target.files[0]);
            fetchThemeAssetsModal(form.action, {
                method: 'POST',
                body: formData,
                successMessage: '模板资源已替换。',
            });

            target.value = '';
            return;
        }

        if (target.matches('[data-theme-assets-file-input]')) {
            if (target.files && target.files.length > 0) {
                const form = target.closest('form');

                if (form) {
                    const formData = buildThemeAssetsUploadFormData(form, target.files[0]);
                    fetchThemeAssetsModal(form.action, {
                        method: 'POST',
                        body: formData,
                        successMessage: '模板资源已上传。',
                    });
                }
            }

            target.value = '';
        }
    });

    document.addEventListener('input', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLInputElement)) {
            return;
        }

        if (target.matches('[data-theme-assets-search]')) {
            return;
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        const target = event.target;
        if (!(target instanceof HTMLInputElement)) {
            return;
        }

        if (target.matches('[data-theme-assets-search]')) {
            event.preventDefault();
            triggerThemeAssetsSearch();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            const previewTrigger = event.target instanceof Element
                ? event.target.closest('[data-theme-asset-preview-trigger]')
                : null;

            if (previewTrigger) {
                event.preventDefault();
                openThemeAssetPreviewModal(
                    previewTrigger.getAttribute('data-asset-url') || '',
                    previewTrigger.getAttribute('data-asset-name') || ''
                );
            }
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && themeAssetPreviewModal && !themeAssetPreviewModal.hidden) {
            closeThemeAssetPreviewModal();
            return;
        }

        if (event.key === 'Escape' && themeAssetsModal?.classList.contains('is-open')) {
            closeThemeAssetsModal();
            return;
        }

        if (event.key === 'Escape' && modal?.classList.contains('is-open')) {
            closeModal();
        }
    });

    syncBodyScroll();
    if (configRoot?.dataset.themeAssetsOpen === '1') {
        openThemeAssetsModal(themeAssetsModal?.dataset.mode || 'manage');
    }

    const treeSearchInput = document.querySelector('[data-template-tree-search]');
    const templateTreeScrollShell = document.querySelector('[data-template-tree-scroll-shell]');
    const themeEditorDesktopMedia = window.matchMedia('(min-width: 1161px)');
    let templateTreeHeightFrame = null;
    let templateTreeHeightRule = null;
    const createForm = document.getElementById('theme-template-create-form');
    const createTemplateSuffixInput = createForm?.querySelector('[data-template-suffix]');
    const templateTreeScrollKey = (() => {
        if (!configRoot) {
            return 'theme-editor:template-tree-scroll';
        }

        const siteId = configRoot.dataset.siteId || 'site';
        const themeCode = configRoot.dataset.themeCode || 'theme';
        const panel = configRoot.dataset.workspacePanel || 'editor';

        return `theme-editor:template-tree-scroll:${siteId}:${themeCode}:${panel}`;
    })();

    const themeEditorStylesheet = () => Array.from(document.styleSheets).find((sheet) => {
        try {
            return Boolean(sheet.href && sheet.href.includes('/css/site-theme-editor.css'));
        } catch (error) {
            return false;
        }
    });

    const ensureTemplateTreeHeightRule = () => {
        if (templateTreeHeightRule) {
            return templateTreeHeightRule;
        }

        const stylesheet = themeEditorStylesheet();

        if (!stylesheet) {
            return null;
        }

        try {
            const ruleIndex = stylesheet.cssRules.length;
            stylesheet.insertRule('.editor-panel.is-template-tree-panel[data-tree-height-bound="1"] {}', ruleIndex);
            templateTreeHeightRule = stylesheet.cssRules[ruleIndex];
        } catch (error) {
            templateTreeHeightRule = null;
        }

        return templateTreeHeightRule;
    };

    const syncTemplateTreeHeight = () => {
        if (!templateTreePanel) {
            return;
        }

        const rule = ensureTemplateTreeHeightRule();

        if (!rule) {
            return;
        }

        if (!themeEditorDesktopMedia.matches) {
            templateTreePanel.removeAttribute('data-tree-height-bound');
            rule.style.removeProperty('height');
            rule.style.removeProperty('max-height');
            return;
        }

        const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        const panelTop = templateTreePanel.getBoundingClientRect().top;
        const availableHeight = Math.max(320, Math.floor(viewportHeight - panelTop - 24));

        templateTreePanel.setAttribute('data-tree-height-bound', '1');
        rule.style.setProperty('height', `${availableHeight}px`);
        rule.style.setProperty('max-height', `${availableHeight}px`);
    };

    const requestTemplateTreeHeightSync = () => {
        if (templateTreeHeightFrame !== null) {
            return;
        }

        templateTreeHeightFrame = window.requestAnimationFrame(() => {
            templateTreeHeightFrame = null;
            syncTemplateTreeHeight();
        });
    };

    const persistTemplateTreeScrollPosition = () => {
        if (!templateTreeScrollShell) {
            return;
        }

        try {
            window.sessionStorage.setItem(templateTreeScrollKey, String(templateTreeScrollShell.scrollTop || 0));
        } catch (error) {
            // Ignore storage access issues and preserve default behavior.
        }
    };

    const restoreTemplateTreeScrollPosition = () => {
        if (!templateTreeScrollShell) {
            return;
        }

        let savedPosition = 0;

        try {
            savedPosition = Number.parseInt(window.sessionStorage.getItem(templateTreeScrollKey) || '0', 10) || 0;
        } catch (error) {
            savedPosition = 0;
        }

        if (savedPosition <= 0) {
            return;
        }

        window.requestAnimationFrame(() => {
            templateTreeScrollShell.scrollTop = savedPosition;
        });
    };

    bindTemplateSuffixInput(createTemplateSuffixInput);

    function applyTemplateTreeFilter() {
        const keyword = (treeSearchInput?.value || '').trim().toLowerCase();

        document.querySelectorAll('[data-template-tree-link]').forEach((link) => {
            const text = (link.getAttribute('data-search-text') || '').toLowerCase();
            const isVisible = keyword === '' || text.includes(keyword);
            link.hidden = !isVisible;
            link.hidden = !isVisible;
        });

        document.querySelectorAll('[data-template-group]').forEach((group) => {
            const visibleItems = group.querySelectorAll('[data-template-tree-link]:not([hidden])').length;
            group.hidden = visibleItems === 0;
            group.hidden = visibleItems === 0;
        });
    }

    if (treeSearchInput) {
        treeSearchInput.addEventListener('input', applyTemplateTreeFilter);
        treeSearchInput.addEventListener('change', applyTemplateTreeFilter);
        treeSearchInput.addEventListener('keyup', applyTemplateTreeFilter);
        applyTemplateTreeFilter();
    }

    templateTreeScrollShell?.addEventListener('scroll', persistTemplateTreeScrollPosition, { passive: true });
    document.querySelectorAll('[data-template-tree-link]').forEach((link) => {
        link.addEventListener('click', persistTemplateTreeScrollPosition);
    });

    requestTemplateTreeHeightSync();
    restoreTemplateTreeScrollPosition();
    window.addEventListener('resize', requestTemplateTreeHeightSync, { passive: true });
    window.addEventListener('scroll', requestTemplateTreeHeightSync, { passive: true });
    themeEditorDesktopMedia.addEventListener('change', requestTemplateTreeHeightSync);

    createForm?.addEventListener('submit', (event) => {
        const createTitleInput = createForm.querySelector('[name="template_title"]');
        if (createTemplateSuffixInput) {
            createTemplateSuffixInput.value = normalizeTemplateSuffix(createTemplateSuffixInput.value, { finalize: true });
        }
        if (!validateTemplateTitleLimit(createTitleInput)) {
            event.preventDefault();
            createTitleInput?.focus();
        }
    });

    editorForm?.addEventListener('submit', (event) => {
        if (!validateTemplateTitleLimit(titleInput)) {
            event.preventDefault();
            titleInput?.focus();
        }
    });

    const bindDangerForm = (form, options) => {
        if (!form) {
            return;
        }

        form.addEventListener('submit', (event) => {
            if (typeof window.showConfirmDialog === 'function') {
                event.preventDefault();
                window.showConfirmDialog({
                    title: options.title,
                    text: options.text,
                    confirmText: options.confirmText,
                    onConfirm: () => form.submit(),
                });
                return;
            }

            if (!window.confirm(options.text)) {
                event.preventDefault();
            }
        });
    };

    bindDangerForm(resetForm, {
        title: '确认恢复平台默认模板？',
        text: '恢复后，当前站点对这个模板的自定义修改会被移除。',
        confirmText: '恢复默认',
    });

    bindDangerForm(deleteForm, {
        title: '确认删除自定义模板？',
        text: '删除后，这个站点新增模板会立即从当前主题中移除。',
        confirmText: '删除模板',
    });

    bindDangerForm(rollbackForm, {
        title: '确认回滚到上一版？',
        text: '回滚后，当前模板会恢复到上一版快照内容。',
        confirmText: '回滚模板',
    });

    document.querySelectorAll('[data-template-snapshot-delete-button]').forEach((button) => {
        const form = button.closest('form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', (event) => {
            if (typeof window.showConfirmDialog !== 'function') {
                return;
            }

            event.preventDefault();
            window.showConfirmDialog({
                title: '确认删除这个模板快照？',
                text: '删除后，这条历史快照将无法恢复，请确认是否继续。',
                confirmText: '删除快照',
                onConfirm: () => form.submit(),
            });
        });
    });

    document.querySelectorAll('[data-template-snapshot-favorite-button]').forEach((button) => {
        button.addEventListener('click', () => {
            button.classList.add('is-active');
            button.classList.add('is-bounce');
            window.setTimeout(() => {
                button.classList.remove('is-bounce');
            }, 180);
        });
    });

    const firstDiffRow = document.querySelector('[data-first-diff]');
    if (firstDiffRow) {
        firstDiffRow.scrollIntoView({ block: 'center' });
    }

    const compareModal = document.querySelector('[data-history-compare-modal]');
    if (compareModal) {
        document.body.classList.add('has-modal-open');
        window.requestAnimationFrame(() => {
            compareModal.classList.add('is-ready');
        });

        const closeCompareModal = () => {
            compareModal.classList.remove('is-ready');
            document.body.classList.remove('has-modal-open');

            if (compareCleanUrl !== '') {
                window.history.replaceState({}, '', compareCleanUrl);
            }

            window.setTimeout(() => {
                compareModal.hidden = true;
            }, 180);
        };

        compareModal.querySelectorAll('[data-history-compare-close]').forEach((element) => {
            element.addEventListener('click', closeCompareModal);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeCompareModal();
            }
        });
    }
})();
