/* site content editor */
/* Extracted from Blade to support stricter CSP script-src policy. */

document.querySelectorAll('[data-editor-switcher]').forEach((switcher) => {
    const buttons = Array.from(switcher.querySelectorAll('[data-pane-target]'));
    const panes = Array.from(document.querySelectorAll('[data-editor-pane]'));
    const panelWrapper = document.querySelector('[data-editor-panels]');
    const mainStack = document.querySelector('[data-editor-main]');
    let activeTarget = 'main';

    const activatePane = (target) => {
        activeTarget = target;

        buttons.forEach((button) => {
            button.classList.toggle('is-active', button.getAttribute('data-pane-target') === target);
        });

        panes.forEach((pane) => {
            pane.classList.toggle('is-active', pane.getAttribute('data-editor-pane') === target);
        });

        const showSecondaryPane = Boolean(target && target !== 'main');
        panelWrapper?.classList.toggle('is-active', showSecondaryPane);
        mainStack?.classList.toggle('is-hidden', showSecondaryPane);
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.getAttribute('data-pane-target');
            activatePane(activeTarget === target ? 'main' : target);
        });
    });

    activatePane('main');
});

const siteContentEditConfig = document.getElementById('site-content-edit-config');
const siteContentTypeLabel = siteContentEditConfig?.dataset.typeLabel || '文章';
const bilibiliVideoResolveUrl = siteContentEditConfig?.dataset.bilibiliResolveUrl || '';
const imageUploadUrl = siteContentEditConfig?.dataset.imageUploadUrl || '';
const richImportUrl = siteContentEditConfig?.dataset.richImportUrl || '';
const richImageFetchUrl = siteContentEditConfig?.dataset.richImageFetchUrl || '';
const siteContentEditErrors = JSON.parse(siteContentEditConfig?.dataset.editorErrors || '[]');

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value
        || '';
}

(() => {
    const input = document.getElementById('cover_image');
    const preview = document.querySelector('[data-cover-preview]');
    const card = document.querySelector('.content-cover-card');
    const removeButton = document.querySelector('[data-cover-remove]');

    if (!input || !preview || !card) {
        return;
    }

    const ensureImage = () => {
        let img = preview.querySelector('[data-cover-image]');
        if (!img) {
            img = document.createElement('img');
            img.setAttribute('data-cover-image', '');
            img.alt = '封面图预览';
            preview.appendChild(img);
        }
        return img;
    };

    const ensurePlaceholder = () => {
        let placeholder = preview.querySelector('[data-cover-placeholder]');
        if (!placeholder) {
            placeholder = document.createElement('div');
            placeholder.setAttribute('data-cover-placeholder', '');
            placeholder.className = 'content-cover-placeholder';
            placeholder.textContent = `${siteContentTypeLabel}封面`;
            preview.appendChild(placeholder);
        }
        return placeholder;
    };

    const renderCover = () => {
        const value = input.value.trim();
        const img = preview.querySelector('[data-cover-image]');
        const placeholder = ensurePlaceholder();

        if (!value) {
            if (img) img.remove();
            placeholder.hidden = false;
            card.classList.remove('has-cover');
            return;
        }

        const image = ensureImage();
        image.src = value;
        image.onerror = () => {
            image.remove();
            ensurePlaceholder().hidden = false;
            card.classList.remove('has-cover');
        };
        placeholder.hidden = true;
        card.classList.add('has-cover');
    };

    input.addEventListener('input', renderCover);
    renderCover();

    removeButton?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        input.value = '';
        renderCover();
    });
})();

(() => {
    const floatingActions = document.querySelector('.content-editor-floating-actions');
    const pageSaveButton = document.querySelector('[data-page-save-button]');

    if (!floatingActions || !pageSaveButton || !('IntersectionObserver' in window)) {
        return;
    }

    const observer = new IntersectionObserver(([entry]) => {
        floatingActions.classList.toggle('is-visible', entry.intersectionRatio < 0.88);
    }, {
        root: null,
        rootMargin: '-50px 0px 0px 0px',
        threshold: [0, 0.25, 0.55, 0.8, 0.88, 1],
    });

    observer.observe(pageSaveButton);
})();

document.querySelectorAll('[data-style-toggle]').forEach((toggle) => {
    const input = toggle.querySelector('input[type="checkbox"]');
    if (!input) {
        return;
    }

    const sync = () => toggle.classList.toggle('is-active', input.checked);
    input.addEventListener('change', sync);
    sync();
});

document.querySelectorAll('.content-status-options').forEach((group) => {
    const options = Array.from(group.querySelectorAll('.content-status-option'));
    const pageSaveButton = document.querySelector('[data-page-save-button]');
    const pageSaveLabel = document.querySelector('[data-page-save-label]');
    const floatingSaveButton = document.querySelector('[data-floating-save-button]');

    const sync = () => {
        options.forEach((option) => {
            const input = option.querySelector('input[type="radio"]');
            option.classList.toggle('is-active', Boolean(input?.checked));
        });

        const checkedInput = group.querySelector('input[type="radio"]:checked');
        if (!checkedInput || !pageSaveButton || !pageSaveLabel) {
            return;
        }

        const nextLabel = checkedInput.value === 'published'
            ? pageSaveButton.dataset.labelPublished
            : pageSaveButton.dataset.labelDraft;

        if (!nextLabel) {
            return;
        }

        pageSaveLabel.textContent = nextLabel;

        if (floatingSaveButton) {
            floatingSaveButton.setAttribute('data-tip', nextLabel);
            floatingSaveButton.setAttribute('aria-label', nextLabel);
        }
    };

    options.forEach((option) => {
        const input = option.querySelector('input[type="radio"]');
        if (!input) {
            return;
        }

        option.addEventListener('click', () => {
            if (input.disabled) {
                return;
            }
            input.checked = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            sync();
        });

        input.addEventListener('change', sync);
    });

    sync();
});

(() => {
    const toolbar = document.querySelector('[data-title-toolbar]');
    const trigger = document.querySelector('[data-color-trigger]');
    const picker = document.querySelector('[data-color-picker]');
    const input = document.getElementById('title_color');
    const titleInput = document.getElementById('title');
    const boldInput = document.getElementById('title_bold');
    const italicInput = document.getElementById('title_italic');
    const reset = document.querySelector('[data-color-reset]');

    if (!toolbar || !trigger || !picker || !input) {
        return;
    }

    const syncTitlePreview = () => {
        if (!titleInput) {
            return;
        }

        const colorValue = (input.value || '').trim().toLowerCase();
        titleInput.classList.remove(
            'is-title-color-royal-blue',
            'is-title-color-bright-blue',
            'is-title-color-violet',
            'is-title-color-rose',
            'is-title-color-green',
            'is-title-color-amber',
            'is-title-color-red'
        );

        const colorClassMap = {
            '#0047ab': 'is-title-color-royal-blue',
            '#2563eb': 'is-title-color-bright-blue',
            '#7c3aed': 'is-title-color-violet',
            '#db2777': 'is-title-color-rose',
            '#059669': 'is-title-color-green',
            '#d97706': 'is-title-color-amber',
            '#dc2626': 'is-title-color-red',
        };

        if (colorValue && colorClassMap[colorValue]) {
            titleInput.classList.add(colorClassMap[colorValue]);
        }

        titleInput.classList.toggle('is-title-bold', Boolean(boldInput?.checked));
        titleInput.classList.toggle('is-title-italic', Boolean(italicInput?.checked));
    };

    const sync = () => {
        document.querySelectorAll('[data-color-swatch]').forEach((swatch) => {
            const swatchColor = (swatch.dataset.color || '').toLowerCase();
            swatch.classList.toggle('is-active', swatchColor === input.value.toLowerCase());
        });
        reset?.classList.toggle('is-active', input.value === '');
        trigger.classList.toggle('has-selection', input.value !== '');

        const currentColor = (input.value || '').trim().toLowerCase();
        const buttonColorClasses = [
            'has-color-royal-blue',
            'has-color-bright-blue',
            'has-color-violet',
            'has-color-rose',
            'has-color-green',
            'has-color-amber',
            'has-color-red',
        ];
        trigger.classList.remove(...buttonColorClasses);

        const buttonColorClassMap = {
            '#0047ab': 'has-color-royal-blue',
            '#2563eb': 'has-color-bright-blue',
            '#7c3aed': 'has-color-violet',
            '#db2777': 'has-color-rose',
            '#059669': 'has-color-green',
            '#d97706': 'has-color-amber',
            '#dc2626': 'has-color-red',
        };

        if (currentColor && buttonColorClassMap[currentColor]) {
            trigger.classList.add(buttonColorClassMap[currentColor]);
        }
        syncTitlePreview();
    };

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        picker.classList.toggle('is-open');
    });

    document.querySelectorAll('[data-color-swatch]').forEach((swatch) => {
        swatch.addEventListener('click', () => {
            input.value = swatch.dataset.color || '';
            picker.classList.remove('is-open');
            sync();
        });
    });

    reset?.addEventListener('click', () => {
        input.value = '';
        picker.classList.remove('is-open');
        sync();
    });

    boldInput?.addEventListener('change', syncTitlePreview);
    italicInput?.addEventListener('change', syncTitlePreview);

    document.addEventListener('click', (event) => {
        if (!toolbar.contains(event.target)) {
            picker.classList.remove('is-open');
        }
    });

    sync();
})();

(() => {
    const form = document.getElementById('content-editor-form');
    const titleInput = document.getElementById('title');
    const contentTextarea = document.getElementById('content');
    const contentEditorBody = document.querySelector('.content-editor-body');

    if (!form || !titleInput || !contentTextarea || !contentEditorBody) {
        return;
    }

    const hasMeaningfulContent = (html) => {
        const raw = String(html || '').trim();

        if (raw === '') {
            return false;
        }

        if (/<(img|video|iframe|embed|object|audio|table|blockquote|pre|ul|ol)\b/i.test(raw)) {
            return true;
        }

        const temp = document.createElement('div');
        temp.innerHTML = raw;
        const text = (temp.textContent || temp.innerText || '').replace(/[\u00A0\u200B-\u200D\uFEFF\s]+/g, '');

        return text !== '';
    };

    const clearTitleError = () => {
        titleInput.classList.remove('is-error');
        titleInput.removeAttribute('aria-invalid');
    };

    const clearContentError = () => {
        contentTextarea.classList.remove('is-error');
        contentTextarea.removeAttribute('aria-invalid');
        contentEditorBody.classList.remove('is-error');
    };

    const validateForm = () => {
        tinymce.triggerSave();

        let isValid = true;
        let firstInvalid = null;

        clearTitleError();
        clearContentError();

        if (titleInput.value.trim() === '') {
            titleInput.classList.add('is-error');
            titleInput.setAttribute('aria-invalid', 'true');
            firstInvalid = firstInvalid || titleInput;
            isValid = false;
        }

        if (!hasMeaningfulContent(contentTextarea.value)) {
            contentTextarea.classList.add('is-error');
            contentTextarea.setAttribute('aria-invalid', 'true');
            contentEditorBody.classList.add('is-error');
            firstInvalid = firstInvalid || contentTextarea;
            isValid = false;
        }

        if (!isValid) {
            const messages = [];
            if (titleInput.classList.contains('is-error')) {
                messages.push('请输入标题');
            }
            if (contentEditorBody.classList.contains('is-error')) {
                messages.push('请输入正文内容');
            }
            showMessage(`${messages.join('，')}。`, 'error');
            firstInvalid?.focus();
            firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        return isValid;
    };

    form.addEventListener('submit', (event) => {
        if (!validateForm()) {
            event.preventDefault();
        }
    });

    titleInput.addEventListener('input', clearTitleError);

    document.addEventListener('tinymce-editor-ready', (event) => {
        if (event.detail?.id !== 'content') {
            return;
        }

        const editor = tinymce.get('content');
        editor?.on('input change undo redo SetContent', clearContentError);
    });
})();

if (siteContentEditErrors.length > 0) {
    showMessage(siteContentEditErrors.join('，'), 'error');
}

const emojiPickerCatalog = {
    recent: { label: '最近使用', items: [] },
    smile: {
        label: '常用笑脸',
        items: [
            { emoji: '😀', name: '开心' }, { emoji: '😁', name: '大笑' }, { emoji: '😂', name: '笑哭' }, { emoji: '😉', name: '眨眼' },
            { emoji: '😊', name: '微笑' }, { emoji: '🙂', name: '轻笑' }, { emoji: '😄', name: '欢笑' }, { emoji: '😆', name: '超开心' },
            { emoji: '😌', name: '放松' }, { emoji: '😍', name: '喜欢' }, { emoji: '🤩', name: '惊喜' }, { emoji: '🥳', name: '庆祝' },
            { emoji: '😎', name: '酷' }, { emoji: '🥰', name: '甜蜜' }, { emoji: '😇', name: '祝福' }, { emoji: '🤗', name: '拥抱' },
            { emoji: '🤭', name: '偷笑' }, { emoji: '🤔', name: '思考' }, { emoji: '😴', name: '睡觉' }, { emoji: '🥹', name: '感动' },
            { emoji: '😭', name: '大哭' }, { emoji: '😡', name: '生气' }, { emoji: '😮', name: '惊讶' }, { emoji: '🫶', name: '比心' },
            { emoji: '😺', name: '猫咪开心' }, { emoji: '🤍', name: '白心' }, { emoji: '💖', name: '粉心' }, { emoji: '🩵', name: '浅蓝心' }
        ]
    },
    gesture: {
        label: '互动手势',
        items: [
            { emoji: '👍', name: '点赞' }, { emoji: '👎', name: '点踩' }, { emoji: '👏', name: '鼓掌' }, { emoji: '🙌', name: '欢呼' },
            { emoji: '👋', name: '招手' }, { emoji: '🤝', name: '握手' }, { emoji: '🙏', name: '感谢' }, { emoji: '✌️', name: '胜利' },
            { emoji: '🤞', name: '好运' }, { emoji: '👌', name: '可以' }, { emoji: '👉', name: '指向' }, { emoji: '👈', name: '返回' },
            { emoji: '👇', name: '下看' }, { emoji: '☝️', name: '提醒' }, { emoji: '💪', name: '加油' }, { emoji: '🫡', name: '致意' }
        ]
    },
    life: {
        label: '生活氛围',
        items: [
            { emoji: '🎉', name: '礼花' }, { emoji: '🎈', name: '气球' }, { emoji: '🎁', name: '礼物' }, { emoji: '🎵', name: '音乐' },
            { emoji: '📚', name: '书本' }, { emoji: '🧡', name: '橙心' }, { emoji: '☕', name: '咖啡' }, { emoji: '🍎', name: '苹果' },
            { emoji: '🍉', name: '西瓜' }, { emoji: '🏆', name: '奖杯' }, { emoji: '🎓', name: '学位帽' }, { emoji: '🖼️', name: '图片' }
        ]
    },
    nature: {
        label: '自然氛围',
        items: [
            { emoji: '🌱', name: '幼苗' }, { emoji: '🌿', name: '绿叶' }, { emoji: '🍃', name: '微风' }, { emoji: '🌸', name: '花朵' },
            { emoji: '🌷', name: '郁金香' }, { emoji: '🌻', name: '向日葵' }, { emoji: '🍀', name: '四叶草' }, { emoji: '🌈', name: '彩虹' },
            { emoji: '☀️', name: '太阳' }, { emoji: '🌤️', name: '晴朗' }, { emoji: '⛅', name: '多云' }, { emoji: '🌧️', name: '小雨' },
            { emoji: '❄️', name: '雪花' }, { emoji: '🌙', name: '月亮' }, { emoji: '⭐', name: '星星' }, { emoji: '✨', name: '闪光' },
            { emoji: '🌊', name: '海浪' }, { emoji: '⛰️', name: '山峰' }, { emoji: '🌾', name: '麦穗' }, { emoji: '🍁', name: '枫叶' }
        ]
    },
    notice: {
        label: '提示强调',
        items: [
            { emoji: '📢', name: '公告' }, { emoji: '📣', name: '通知' }, { emoji: '📌', name: '置顶' }, { emoji: '🔥', name: '热门' },
            { emoji: '🎯', name: '重点' }, { emoji: '✅', name: '完成' }, { emoji: '⚠️', name: '提醒' }, { emoji: '❗', name: '强调' },
            { emoji: '❓', name: '疑问' }, { emoji: '💡', name: '灵感' }, { emoji: '🆕', name: '全新' }, { emoji: '📎', name: '附件' },
            { emoji: '🔔', name: '铃铛' }, { emoji: '📝', name: '记录' }, { emoji: '📍', name: '定位' }, { emoji: '🏷️', name: '标签' },
            { emoji: '📅', name: '日程' }, { emoji: '📤', name: '发布' }, { emoji: '🛠️', name: '维护' }, { emoji: '🔒', name: '安全' }
        ]
    }
};
let emojiPickerEditorId = null;
let emojiPickerCategory = 'smile';
let emojiPickerSearch = '';

function emojiRecentKey() {
    return 'cms_recent_emojis';
}

function loadRecentEmojis() {
    try {
        return JSON.parse(window.localStorage.getItem(emojiRecentKey()) || '[]');
    } catch (error) {
        return [];
    }
}

function saveRecentEmoji(entry) {
    const recent = loadRecentEmojis().filter((item) => item.emoji !== entry.emoji);
    recent.unshift(entry);
    window.localStorage.setItem(emojiRecentKey(), JSON.stringify(recent.slice(0, 12)));
}

function getEmojiItems() {
    const category = emojiPickerCatalog[emojiPickerCategory] || emojiPickerCatalog.smile;
    const search = emojiPickerSearch.trim().toLowerCase();

    if (!search) {
        return category.items;
    }

    return Object.values(emojiPickerCatalog)
        .flatMap((group) => group.items)
        .filter((item, index, list) => list.findIndex((candidate) => candidate.emoji === item.emoji) === index)
        .filter((item) => `${item.emoji}${item.name}`.toLowerCase().includes(search));
}

function renderEmojiPicker() {
    const categoryContainer = document.getElementById('emoji-picker-categories');
    const grid = document.getElementById('emoji-picker-grid');
    const search = document.getElementById('emoji-picker-search');

    if (!categoryContainer || !grid) {
        return;
    }

    if (search && search.value !== emojiPickerSearch) {
        search.value = emojiPickerSearch;
    }

    categoryContainer.innerHTML = Object.entries(emojiPickerCatalog)
        .filter(([key, group]) => key !== 'recent' || group.items.length)
        .map(([key, group]) => `
            <button class="emoji-picker-category ${emojiPickerCategory === key ? 'is-active' : ''}" type="button" data-emoji-category="${key}">
                ${group.label}
            </button>
        `)
        .join('');

    const items = getEmojiItems();
    grid.innerHTML = items.length
        ? items.map((item) => `
            <button class="emoji-picker-item" type="button" data-emoji="${item.emoji}" data-emoji-name="${item.name}">
                <span class="emoji-picker-glyph">${item.emoji}</span>
                <span class="emoji-picker-name">${item.name}</span>
            </button>
        `).join('')
        : '<div class="emoji-picker-empty">没有找到匹配的表情，换个关键词试试。</div>';

    categoryContainer.querySelectorAll('[data-emoji-category]').forEach((button) => {
        button.addEventListener('click', () => {
            emojiPickerCategory = button.dataset.emojiCategory || 'smile';
            renderEmojiPicker();
        });
    });

    grid.querySelectorAll('[data-emoji]').forEach((button) => {
        button.addEventListener('click', () => {
            const emoji = button.dataset.emoji || '';
            const name = button.dataset.emojiName || '';
            const editor = emojiPickerEditorId ? tinymce.get(emojiPickerEditorId) : null;

            if (!editor || !emoji) {
                return;
            }

            editor.insertContent(emoji);
            editor.save();
            saveRecentEmoji({ emoji, name });
            closeEmojiPicker();
        });
    });
}

function openEmojiPicker(editorId) {
    const modal = document.getElementById('emoji-picker-modal');
    const search = document.getElementById('emoji-picker-search');

    if (!modal) {
        return;
    }

    emojiPickerEditorId = editorId;
    emojiPickerCatalog.recent.items = loadRecentEmojis();
    emojiPickerCategory = emojiPickerCatalog.recent.items.length ? 'recent' : 'smile';
    emojiPickerSearch = '';
    modal.hidden = false;
    renderEmojiPicker();
    window.requestAnimationFrame(() => search?.focus());
}

function closeEmojiPicker() {
    const modal = document.getElementById('emoji-picker-modal');
    if (modal) {
        modal.hidden = true;
    }
}

function initializeEmojiPicker() {
    const modal = document.getElementById('emoji-picker-modal');
    const search = document.getElementById('emoji-picker-search');

    if (!modal || !search) {
        return;
    }

    modal.querySelectorAll('[data-close-emoji-picker]').forEach((button) => {
        button.addEventListener('click', closeEmojiPicker);
    });

    search.addEventListener('input', () => {
        emojiPickerSearch = search.value;
        renderEmojiPicker();
    });
}

initializeEmojiPicker();

let videoEmbedEditorId = 'content';
let videoEmbedEditingNode = null;

function setVideoEmbedError(message = '') {
    const error = document.getElementById('video-embed-error');
    if (!error) {
        return;
    }

    if (message) {
        error.textContent = message;
        error.hidden = false;
        return;
    }

    error.textContent = '';
    error.hidden = true;
}

function closeVideoEmbed() {
    const modal = document.getElementById('video-embed-modal');
    if (modal) {
        modal.hidden = true;
    }
    videoEmbedEditingNode = null;
    setVideoEmbedError('');
}

function syncSiteSelectValue(selectElement, value) {
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

    if (trigger) {
        const selectedOption = Array.from(selectElement.options).find((option) => option.value === value);
        trigger.textContent = selectedOption?.textContent || '';
    }

    if (panel) {
        panel.querySelectorAll('.site-select-option').forEach((optionButton) => {
            optionButton.classList.toggle('is-active', optionButton.dataset.value === value);
        });
    }
}

function findVideoEmbedNode(node) {
    if (!node) {
        return null;
    }

    if (node.nodeType === Node.ELEMENT_NODE && node.matches?.('.bilibili-video-embed[data-bilibili-video="1"]')) {
        return node;
    }

    return node.closest?.('.bilibili-video-embed[data-bilibili-video="1"]') || null;
}

function clearSelectedVideoEmbed(editor) {
    const body = editor?.getBody?.();
    if (!body) {
        return;
    }

    body.querySelectorAll('.bilibili-video-embed.is-selected').forEach((node) => {
        node.classList.remove('is-selected');
    });
}

function selectVideoEmbedNode(editor, node) {
    if (!editor || !node) {
        return;
    }

    clearSelectedVideoEmbed(editor);
    node.classList.add('is-selected');
    editor.selection.select(node);
    editor.focus();
}

function buildBilibiliEmbedHtml(resolved, width, height, align) {
    const alignLabel = ({ left: '居左', center: '居中', right: '居右' })[align] || '居中';

    return `
        <div class="bilibili-video-embed mceNonEditable" data-bilibili-video="1" data-aid="${resolved.aid}" data-bvid="${resolved.bvid}" data-cid="${resolved.cid}" data-p="${resolved.page}" data-width="${width}" data-height="${height}" data-align="${align}">
            <div class="bilibili-video-embed__title">哔哩哔哩视频</div>
            <div class="bilibili-video-embed__meta">${resolved.bvid} · ${width} × ${height} · ${alignLabel}</div>
        </div>
    `;
}

function openVideoEmbed(editorId = 'content', existingNode = null) {
    const modal = document.getElementById('video-embed-modal');
    const urlInput = document.getElementById('video-embed-url');
    const widthInput = document.getElementById('video-embed-width');
    const heightInput = document.getElementById('video-embed-height');
    const alignInput = document.getElementById('video-embed-align');
    const title = document.getElementById('video-embed-title');
    const confirmButton = document.getElementById('video-embed-confirm');

    if (!modal) {
        return;
    }

    videoEmbedEditorId = editorId;
    videoEmbedEditingNode = existingNode;
    setVideoEmbedError('');

    if (existingNode) {
        if (urlInput) {
            urlInput.value = `https://www.bilibili.com/video/${existingNode.getAttribute('data-bvid') || ''}/`;
        }
        if (widthInput) {
            widthInput.value = (existingNode.getAttribute('data-width') || '80%').trim();
        }
        if (heightInput) {
            heightInput.value = (existingNode.getAttribute('data-height') || '450px').replace(/px$/i, '').trim();
        }
        syncSiteSelectValue(alignInput, (existingNode.getAttribute('data-align') || 'center').trim() || 'center');
        if (title) {
            title.textContent = '编辑视频';
        }
        if (confirmButton) {
            confirmButton.textContent = '保存视频';
        }
    } else {
        if (urlInput) {
            urlInput.value = '';
        }
        if (widthInput) {
            widthInput.value = '90%';
        }
        if (heightInput) {
            heightInput.value = '500';
        }
        syncSiteSelectValue(alignInput, 'center');
        if (title) {
            title.textContent = '插入视频';
        }
        if (confirmButton) {
            confirmButton.textContent = '插入视频';
        }
    }

    if (urlInput && !existingNode) {
        urlInput.value = '';
    }
    modal.hidden = false;
    window.requestAnimationFrame(() => urlInput?.focus());
}

function normalizeVideoWidth(value) {
    const raw = String(value || '').trim();
    if (!raw) {
        return '90%';
    }
    if (/^\d+$/.test(raw)) {
        return `${raw}px`;
    }
    if (/^\d+(px|%|vw|rem|em)$/i.test(raw)) {
        return raw;
    }
    throw new Error('视频宽度只支持数字、px、%、vw、rem、em。');
}

function normalizeVideoHeight(value) {
    const raw = String(value || '').trim();
    if (!raw) {
        return '500px';
    }
    if (/^\d+$/.test(raw)) {
        return `${raw}px`;
    }
    if (/^\d+(px|vh|rem|em)$/i.test(raw)) {
        return raw;
    }
    throw new Error('视频高度只支持数字、px、vh、rem、em。');
}

async function resolveBilibiliVideoUrl(rawUrl) {
    const urlText = String(rawUrl || '').trim();

    if (!urlText) {
        throw new Error('请先输入哔哩哔哩视频地址。');
    }

    const response = await fetch(bilibiliVideoResolveUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
        body: JSON.stringify({ url: urlText }),
    });

    const json = await response.json().catch(() => ({}));
    if (!response.ok || !json.embed_url || !json.aid || !json.bvid || !json.cid) {
        throw new Error(json.message || '视频解析失败，请确认哔哩哔哩地址可访问。');
    }

    return json;
}

async function insertBilibiliVideo() {
    const editor = videoEmbedEditorId ? tinymce.get(videoEmbedEditorId) : null;
    const urlInput = document.getElementById('video-embed-url');
    const widthInput = document.getElementById('video-embed-width');
    const heightInput = document.getElementById('video-embed-height');
    const alignInput = document.getElementById('video-embed-align');

    if (!editor || !urlInput || !widthInput || !heightInput || !alignInput) {
        return;
    }

    try {
        const resolved = await resolveBilibiliVideoUrl(urlInput.value);
        const width = normalizeVideoWidth(widthInput.value);
        const height = normalizeVideoHeight(heightInput.value);
        const align = alignInput.value || 'center';
        const embedHtml = buildBilibiliEmbedHtml(resolved, width, height, align);

        if (videoEmbedEditingNode) {
            editor.dom.setOuterHTML(videoEmbedEditingNode, embedHtml);
            const nodes = editor.getBody().querySelectorAll('.bilibili-video-embed[data-bilibili-video="1"]');
            const latestNode = nodes[nodes.length - 1];
            if (latestNode) {
                selectVideoEmbedNode(editor, latestNode);
            }
        } else {
            editor.insertContent(embedHtml);
        }
        editor.save();
        editor.focus();
        closeVideoEmbed();
    } catch (error) {
        setVideoEmbedError(error instanceof Error ? error.message : '视频插入失败，请检查链接格式。');
    }
}

function initializeVideoEmbed() {
    const modal = document.getElementById('video-embed-modal');
    const confirmButton = document.getElementById('video-embed-confirm');
    const urlInput = document.getElementById('video-embed-url');

    if (!modal || !confirmButton) {
        return;
    }

    modal.querySelectorAll('[data-close-video-embed]').forEach((button) => {
        button.addEventListener('click', closeVideoEmbed);
    });

    confirmButton.addEventListener('click', insertBilibiliVideo);
    urlInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            insertBilibiliVideo();
        }
    });
}

initializeVideoEmbed();

(() => {
    document.querySelectorAll('[data-content-channel-select]').forEach((selectRoot) => {
        const trigger = selectRoot.querySelector('[data-content-channel-trigger]');
        const panel = selectRoot.querySelector('[data-content-channel-panel]');
        const searchWrap = selectRoot.querySelector('[data-channel-search]');
        const searchInput = selectRoot.querySelector('[data-channel-search-input]');
        const clearButton = selectRoot.querySelector('[data-channel-search-clear]');
        const options = Array.from(selectRoot.querySelectorAll('[data-channel-option]'));
        const checkboxes = Array.from(selectRoot.querySelectorAll('[data-channel-checkbox]'));

        if (!trigger || !panel || checkboxes.length === 0) {
            return;
        }

        const updateSummary = () => {
            const selected = checkboxes.filter((checkbox) => checkbox.checked);
            if (selected.length === 0) {
                trigger.textContent = '请选择栏目';
                return;
            }

            const previewNames = selected
                .slice(0, 5)
                .map((checkbox) => checkbox.dataset.channelName || '')
                .filter(Boolean);

            trigger.textContent = selected.length <= 5
                ? previewNames.join('、')
                : `${previewNames.join('、')} 等${selected.length}个栏目`;
        };

        const closePanel = () => {
            selectRoot.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        };

        const filterOptions = () => {
            const keyword = String(searchInput?.value || '').trim().toLowerCase();
            const visibleOptions = new Set();

            searchWrap?.classList.toggle('has-value', keyword !== '');

            if (keyword === '') {
                options.forEach((option) => option.classList.remove('is-hidden'));
                return;
            }

            options.forEach((option, index) => {
                const haystack = [
                    String(option.dataset.channelKeyword || ''),
                    String(option.textContent || ''),
                ].join(' ').toLowerCase();

                if (!haystack.includes(keyword)) {
                    return;
                }

                visibleOptions.add(option);
                const currentDepth = Number(option.dataset.depth || '0');
                if (currentDepth <= 0) {
                    return;
                }

                for (let cursor = index - 1, depth = currentDepth; cursor >= 0 && depth > 0; cursor -= 1) {
                    const candidate = options[cursor];
                    const candidateDepth = Number(candidate.dataset.depth || '0');

                    if (candidateDepth === depth - 1) {
                        visibleOptions.add(candidate);
                        depth = candidateDepth;
                    }
                }
            });

            options.forEach((option) => {
                option.classList.toggle('is-hidden', !visibleOptions.has(option));
            });
        };

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const nextState = !selectRoot.classList.contains('is-open');

            document.querySelectorAll('[data-content-channel-select].is-open').forEach((openRoot) => {
                openRoot.classList.remove('is-open');
                openRoot.querySelector('[data-content-channel-trigger]')?.setAttribute('aria-expanded', 'false');
            });

            if (nextState) {
                selectRoot.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
                searchInput?.focus();
            }
        });

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', updateSummary);
        });

        searchInput?.addEventListener('input', filterOptions);
        searchInput?.addEventListener('change', filterOptions);
        searchInput?.addEventListener('keyup', filterOptions);
        clearButton?.addEventListener('click', () => {
            if (!searchInput) {
                return;
            }

            searchInput.value = '';
            filterOptions();
            searchInput.focus();
        });

        document.addEventListener('click', (event) => {
            if (!selectRoot.contains(event.target)) {
                closePanel();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && selectRoot.classList.contains('is-open')) {
                closePanel();
            }
        });

        filterOptions();
        updateSummary();
    });
})();

(() => {
    document.querySelectorAll('[data-datetime-trigger]').forEach((trigger) => {
        const wrapper = trigger.closest('.content-datetime-field');
        const input = wrapper?.querySelector('.content-datetime-input');

        if (!input) {
            return;
        }

        const openPicker = () => {
            if (typeof input.showPicker === 'function') {
                input.showPicker();
                return;
            }
            input.focus();
            input.click();
        };

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            openPicker();
        });

        wrapper?.addEventListener('click', (event) => {
            if (event.target === input || event.target === trigger) {
                return;
            }
            openPicker();
        });
    });
})();

function attachResourceLibraryMenubarButton(editor) {
    return editor;
}

function looksLikeArticleFooterDate(text) {
    return /^(\d{4})[年\-\/.](\d{1,2})[月\-\/.](\d{1,2})日?$/.test(text);
}

function looksLikeArticleFooterMeta(text) {
    if (text.length < 4 || text.length > 36) {
        return false;
    }
    return /(图、文|图文|编辑|记者|来源|摄影|撰稿|审核)/.test(text);
}

function resetArticleClassSet(node, classNames) {
    if (!node) {
        return;
    }
    classNames.forEach((className) => node.classList.remove(className));
}

function splitMixedMediaParagraphs(root) {
    const mediaSelector = 'img, table, iframe, video, figure, .bilibili-video-embed';

    root.querySelectorAll('p').forEach((node) => {
        const mediaNodes = Array.from(node.querySelectorAll(':scope > img, :scope > table, :scope > iframe, :scope > video, :scope > figure, :scope > .bilibili-video-embed'));
        const text = (node.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();

        if (mediaNodes.length === 0 || text === '') {
            return;
        }

        mediaNodes.forEach((mediaNode) => {
            const wrapper = document.createElement('p');
            wrapper.appendChild(mediaNode.cloneNode(true));
            node.parentNode?.insertBefore(wrapper, node.nextSibling);
            mediaNode.remove();
        });
    });

    root.querySelectorAll('p').forEach((node) => {
        if ((node.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim() === '' && !node.querySelector(mediaSelector)) {
            node.remove();
        }
    });
}

function normalizeArticleFooter(root) {
    const paragraphs = Array.from(root.querySelectorAll('p')).filter((node) => !node.querySelector('img, table, iframe, video, figure, .bilibili-video-embed'));
    const tailParagraphs = paragraphs.slice(-4);

    tailParagraphs.forEach((node) => {
        const text = (node.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
        if (!text) {
            return;
        }

        const isDateLine = looksLikeArticleFooterDate(text);
        const isMetaLine = looksLikeArticleFooterMeta(text);
        if (!isDateLine && !isMetaLine) {
            return;
        }

        resetArticleClassSet(node, [
            'cms-article-paragraph--footer-date',
            'cms-article-paragraph--footer-meta',
        ]);
        node.classList.add(isDateLine ? 'cms-article-paragraph--footer-date' : 'cms-article-paragraph--footer-meta');
    });
}

function collapseMediaSpacing(root) {
    root.querySelectorAll('p').forEach((node) => {
        const hasMediaContent = Boolean(node.querySelector('img, table, iframe, video, figure, .bilibili-video-embed'));
        if (!hasMediaContent) {
            return;
        }

        const previous = node.previousElementSibling;
        if (!previous || previous.tagName?.toLowerCase() !== 'p') {
            return;
        }

        const previousText = (previous.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
        const previousHasMedia = Boolean(previous.querySelector('img, table, iframe, video, figure, .bilibili-video-embed'));

        if (!previousHasMedia && previousText === '') {
            previous.remove();
            return;
        }

        if (!previousHasMedia && previousText !== '') {
            previous.classList.add('cms-article-paragraph--before-media');
        }
    });
}

function tightenMediaTopSpacing(root) {
    root.querySelectorAll('p').forEach((node) => {
        const mediaNode = node.querySelector(':scope > img, :scope > figure, :scope > .bilibili-video-embed, :scope > table, :scope > iframe, :scope > video');
        if (!mediaNode) {
            return;
        }

        const previous = node.previousElementSibling;
        const previousTag = previous?.tagName?.toLowerCase() || '';
        const previousText = previous ? (previous.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim() : '';
        const previousHasMedia = previous ? Boolean(previous.querySelector('img, table, iframe, video, figure, .bilibili-video-embed')) : false;

        if (previous && previousTag === 'p' && previousText !== '' && !previousHasMedia) {
            if (mediaNode.matches('img, figure')) {
                mediaNode.classList.add('cms-article-image--offset-top');
            }
            if (mediaNode.matches('.bilibili-video-embed')) {
                mediaNode.classList.add('cms-article-video--offset-top');
            }
        }
    });
}

function normalizeLeadingParagraphIndent(root) {
    const paragraphs = Array.from(root.querySelectorAll('p')).filter((node) => {
        const text = (node.textContent || '').replace(/\u00a0/g, ' ').trim();
        const hasMediaContent = Boolean(node.querySelector('img, table, iframe, video, figure, .bilibili-video-embed'));
        return text !== '' && !hasMediaContent;
    });

    const firstParagraph = paragraphs[0];
    if (firstParagraph) {
        firstParagraph.classList.add('cms-article-paragraph--lead');
    }
}

function stripParagraphLeadingWhitespace(node) {
    if (!node || node.tagName?.toLowerCase() !== 'p') {
        return;
    }

    for (const child of Array.from(node.childNodes)) {
        if (child.nodeType !== Node.TEXT_NODE) {
            break;
        }

        const text = child.textContent || '';
        if (text === '') {
            child.remove();
            continue;
        }

        const trimmed = text.replace(/^[\s\u00a0\u3000]+/u, '');
        if (trimmed === '') {
            child.remove();
            continue;
        }

        child.textContent = trimmed;
        break;
    }
}

function normalizeArticleTypography(root) {
    splitMixedMediaParagraphs(root);

    root.querySelectorAll('p, li, td, th, figcaption, blockquote').forEach((node) => {
        node.classList.add('cms-article-copy');
    });

    root.querySelectorAll('p').forEach((node) => {
        const text = (node.textContent || '').replace(/\u00a0/g, ' ').trim();
        const hasMediaContent = Boolean(node.querySelector('img, table, iframe, video, figure, .bilibili-video-embed'));

        resetArticleClassSet(node, [
            'cms-article-paragraph',
            'cms-article-paragraph--text',
            'cms-article-paragraph--media',
            'cms-article-paragraph--lead',
            'cms-article-paragraph--before-media',
            'cms-article-paragraph--footer-date',
            'cms-article-paragraph--footer-meta',
        ]);
        node.classList.add('cms-article-paragraph');

        if (hasMediaContent) {
            Array.from(node.childNodes).forEach((child) => {
                if (child.nodeType === Node.TEXT_NODE && (child.textContent || '').replace(/\u00a0/g, ' ').trim() === '') {
                    child.remove();
                }
            });
            node.classList.add('cms-article-paragraph--media');
            return;
        }

        if (text === '') {
            node.remove();
            return;
        }

        stripParagraphLeadingWhitespace(node);
        node.classList.add('cms-article-paragraph--text');
    });

    root.querySelectorAll('h1, h2, h3, h4').forEach((node) => {
        const tag = node.tagName.toLowerCase();
        resetArticleClassSet(node, [
            'cms-article-heading',
            'cms-article-heading--h1',
            'cms-article-heading--h2',
            'cms-article-heading--h3',
            'cms-article-heading--h4',
        ]);
        node.classList.add('cms-article-heading', `cms-article-heading--${tag}`);
    });

    root.querySelectorAll('ul, ol').forEach((node) => node.classList.add('cms-article-list'));
    root.querySelectorAll('li').forEach((node) => node.classList.add('cms-article-list-item'));
    root.querySelectorAll('blockquote').forEach((node) => node.classList.add('cms-article-blockquote'));
    root.querySelectorAll('table').forEach((node) => node.classList.add('cms-article-table'));
    root.querySelectorAll('table td, table th').forEach((node) => node.classList.add('cms-article-table-cell'));
    root.querySelectorAll('table th').forEach((node) => node.classList.add('cms-article-table-cell--head'));

    root.querySelectorAll('img').forEach((node) => {
        const hasExplicitWidth = Boolean((node.style.width || '').trim()) || node.hasAttribute('width');
        node.classList.add('cms-article-image');
        if (!hasExplicitWidth) {
            node.classList.add('cms-article-image--default-width');
        }
    });

    root.querySelectorAll('figure').forEach((node) => node.classList.add('cms-article-figure'));
    root.querySelectorAll('figcaption').forEach((node) => node.classList.add('cms-article-figure-caption'));

    root.querySelectorAll('span[style]').forEach((node) => node.removeAttribute('style'));

    normalizeArticleFooter(root);
    collapseMediaSpacing(root);
    normalizeLeadingParagraphIndent(root);
    tightenMediaTopSpacing(root);
}

function applySmartTypesetting(editor) {
    const rawHtml = editor.getContent({ format: 'html' }).trim();
    if (rawHtml === '') {
        showStyledEditorToast({
            title: '排版提示',
            text: '请先输入文章内容，再使用一键排版。',
            timeout: 5000,
        });
        return;
    }

    const parser = new DOMParser();
    const documentFragment = parser.parseFromString(`<div data-typesetting-root>${rawHtml}</div>`, 'text/html');
    const root = documentFragment.body.querySelector('[data-typesetting-root]');
    if (!root) {
        return;
    }

    normalizeArticleTypography(root);
    editor.undoManager.transact(() => {
        editor.setContent(root.innerHTML);
        editor.save();
    });
    showArticleTypesettingToast();
}

function showStyledEditorToast(options = {}) {
    const toastConfig = window.CMS_TOAST_CONFIG || {};
    const title = String(options.title || '操作提示');
    const text = String(options.text || '');
    const toastVisibleDuration = Number.isFinite(options.timeout)
        ? Number(options.timeout)
        : (Number.isFinite(toastConfig.visibleDuration) ? toastConfig.visibleDuration : 5000);
    const toastExitDuration = Number.isFinite(toastConfig.exitDuration) ? toastConfig.exitDuration : 240;

    document.querySelectorAll('.article-typesetting-toast').forEach((node) => node.remove());

    const toast = document.createElement('div');
    toast.className = 'article-typesetting-toast';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');

    const icon = document.createElement('span');
    icon.className = 'article-typesetting-toast__icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = `
        <svg viewBox="0 0 24 24">
            <path d="m12 3 1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z"></path>
            <path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z"></path>
        </svg>
    `;

    const body = document.createElement('span');
    body.className = 'article-typesetting-toast__body';

    const titleNode = document.createElement('strong');
    titleNode.className = 'article-typesetting-toast__title';
    titleNode.textContent = title;

    const textNode = document.createElement('span');
    textNode.className = 'article-typesetting-toast__text';
    textNode.textContent = text;

    body.appendChild(titleNode);
    body.appendChild(textNode);
    toast.appendChild(icon);
    toast.appendChild(body);

    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('is-visible'));

    window.setTimeout(() => {
        toast.classList.remove('is-visible');
        window.setTimeout(() => toast.remove(), toastExitDuration);
    }, toastVisibleDuration);
}

function openEditorNotice(editor, text, type = 'info', timeout = 2800) {
    const title = type === 'success' ? '导入成功' : '导入提示';

    showStyledEditorToast({
        title,
        text,
        timeout,
    });
}

function isImportableImageSource(src) {
    const value = String(src || '').trim();
    if (value === '') {
        return false;
    }

    return /^data:image\//i.test(value) || /^https?:\/\//i.test(value);
}

function countImportableImagesInHtml(html) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(`<div data-import-count-wrap>${html}</div>`, 'text/html');
    const root = doc.body.querySelector('[data-import-count-wrap]');

    if (!root) {
        return 0;
    }

    return Array.from(root.querySelectorAll('img'))
        .filter((node) => isImportableImageSource(node.getAttribute('src') || ''))
        .length;
}

function stripImagesFromImportedHtml(html) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(`<div data-import-strip-wrap>${html}</div>`, 'text/html');
    const root = doc.body.querySelector('[data-import-strip-wrap]');

    if (!root) {
        return html;
    }

    root.querySelectorAll('img').forEach((node) => node.remove());

    return root.innerHTML;
}

function confirmImportImageSync(imageCount) {
    if (!Number.isFinite(imageCount) || imageCount <= 0) {
        return Promise.resolve(true);
    }

    const message = `本次导入的内容包含${imageCount}张图片，是否将图片同步上传到资源库中。`;

    return new Promise((resolve) => {
        if (typeof window.showConfirmDialog !== 'function') {
            resolve(window.confirm(message));
            return;
        }

        const modal = document.querySelector('.js-confirm-modal');
        const cancelButtons = modal ? Array.from(modal.querySelectorAll('.js-confirm-cancel')) : [];
        let settled = false;

        const finish = (value) => {
            if (settled) {
                return;
            }

            settled = true;
            cancelButtons.forEach((button) => button.removeEventListener('click', onCancel));
            document.removeEventListener('keydown', onEscape, true);
            resolve(value);
        };

        const onCancel = () => finish(false);
        const onEscape = (event) => {
            if (event.key === 'Escape') {
                finish(false);
            }
        };

        cancelButtons.forEach((button) => button.addEventListener('click', onCancel, { once: true }));
        document.addEventListener('keydown', onEscape, true);

        window.showConfirmDialog({
            title: '同步图片到资源库？',
            text: message,
            confirmText: '确认同步',
            onConfirm: () => finish(true),
        });
    });
}

function dataUrlToFile(dataUrl, filename = 'import-image.png') {
    const match = String(dataUrl || '').match(/^data:([^;]+);base64,(.+)$/i);
    if (!match) {
        return null;
    }

    const mimeType = (match[1] || 'image/png').toLowerCase();
    if (!mimeType.startsWith('image/')) {
        return null;
    }

    const extension = mimeType.split('/')[1] || 'png';
    const binary = window.atob(match[2] || '');
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i += 1) {
        bytes[i] = binary.charCodeAt(i);
    }

    return new File([bytes], `${filename}.${extension}`.replace(/\.+/g, '.'), { type: mimeType });
}

async function remoteImageUrlToFile(url, filename = 'import-image') {
    if (!richImageFetchUrl) {
        throw new Error('导入图片抓取接口未配置');
    }

    const payload = new FormData();
    payload.append('url', url);

    const response = await fetch(richImageFetchUrl, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: payload,
        credentials: 'same-origin',
    });
    const result = await response.json().catch(() => ({}));
    if (response.status === 429) {
        throw new Error('操作过于频繁，请稍后再试。');
    }
    if (!response.ok || !result?.data_url) {
        throw new Error(result?.message || '图片下载失败');
    }

    const file = dataUrlToFile(String(result.data_url || ''), filename);
    if (!file) {
        throw new Error('图片格式不支持');
    }

    return file;
}

async function uploadImportedImage(file) {
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch(imageUploadUrl, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: formData,
        credentials: 'same-origin',
    });

    const payload = await response.json().catch(() => ({}));
    if (response.status === 429) {
        throw new Error('操作过于频繁，请稍后再试。');
    }
    if (!response.ok || !payload?.location) {
        throw new Error(payload?.message || '图片上传失败');
    }

    return String(payload.location);
}

async function replaceEmbeddedImagesForImport(html, onProgress = null) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(`<div data-import-wrap>${html}</div>`, 'text/html');
    const root = doc.body.querySelector('[data-import-wrap]');

    if (!root) {
        return { html, uploaded: 0, failed: 0, failures: [] };
    }

    const images = Array.from(root.querySelectorAll('img')).filter((node) => isImportableImageSource(node.getAttribute('src') || ''));
    if (images.length === 0) {
        return { html: root.innerHTML, uploaded: 0, failed: 0, failures: [] };
    }

    if (images.length > 20) {
        throw new Error(`图片数量超出限制：当前 ${images.length} 张，最多允许 20 张。`);
    }

    let uploaded = 0;
    const failures = [];
    const total = images.length;
    const notifyProgress = typeof onProgress === 'function'
        ? onProgress
        : () => {};

    notifyProgress({
        total,
        uploaded,
        failed: 0,
        remaining: total,
    });

    for (let i = 0; i < images.length; i += 1) {
        const node = images[i];
        const src = (node.getAttribute('src') || '').trim();
        let file = null;

        if (/^data:image\//i.test(src)) {
            file = dataUrlToFile(src, `import-${Date.now()}-${i + 1}`);
        } else if (/^https?:\/\//i.test(src)) {
            try {
                file = await remoteImageUrlToFile(src, `import-${Date.now()}-${i + 1}`);
            } catch (error) {
                failures.push(`第 ${i + 1} 张图片下载失败，已跳过`);
                node.remove();
                notifyProgress({
                    total,
                    uploaded,
                    failed: failures.length,
                    remaining: Math.max(0, total - uploaded - failures.length),
                });
                continue;
            }
        }

        if (!file) {
            failures.push(`第 ${i + 1} 张图片格式不支持`);
            node.remove();
            notifyProgress({
                total,
                uploaded,
                failed: failures.length,
                remaining: Math.max(0, total - uploaded - failures.length),
            });
            continue;
        }

        try {
            const location = await uploadImportedImage(file);
            node.setAttribute('src', location);
            uploaded += 1;
        } catch (error) {
            failures.push(`第 ${i + 1} 张图片上传失败`);
            node.remove();
        }

        notifyProgress({
            total,
            uploaded,
            failed: failures.length,
            remaining: Math.max(0, total - uploaded - failures.length),
        });
    }

    return {
        html: root.innerHTML,
        uploaded,
        failed: images.length - uploaded,
        failures,
    };
}

async function importRichContent(editor, payload) {
    if (!richImportUrl) {
        openEditorNotice(editor, '导入接口未配置，请联系管理员。', 'warning');
        return;
    }

    const importingWord = typeof payload?.has === 'function' && payload.has('file');
    const preparingText = importingWord
        ? '文章和图片资源正在导入中，正在解析 Word 内容，请耐心等待完成。'
        : '文章和图片资源正在导入中，正在处理粘贴内容，请耐心等待完成。';
    openEditorNotice(editor, preparingText, 'info', 5000);
    const pendingNoticeTimer = window.setInterval(() => {
        openEditorNotice(editor, preparingText, 'info', 5000);
    }, 4500);

    try {
        const response = await fetch(richImportUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: payload,
            credentials: 'same-origin',
        });
        const result = await response.json().catch(() => ({}));
        if (response.status === 429) {
            throw new Error('操作过于频繁，请稍后再试。');
        }

        if (!response.ok || !result?.html) {
            throw new Error(result?.message || '导入失败，请稍后重试。');
        }

        const importedHtml = String(result.html || '');
        const importableImageCount = countImportableImagesInHtml(importedHtml);
        const shouldSyncImages = await confirmImportImageSync(importableImageCount);

        const uploadResult = shouldSyncImages
            ? await replaceEmbeddedImagesForImport(importedHtml, ({ total, uploaded, remaining }) => {
                if (total <= 0) {
                    return;
                }

                openEditorNotice(
                    editor,
                    `文章和图片资源正在导入中，已导入 ${uploaded} 张，还剩余 ${remaining} 张，请耐心等待完成。`,
                    'info',
                    6000
                );
            })
            : {
                html: stripImagesFromImportedHtml(importedHtml),
                uploaded: 0,
                failed: 0,
                failures: [],
            };

        const finalHtml = String(uploadResult.html || '').trim();
        if (finalHtml === '') {
            throw new Error('导入内容为空，请检查原始文档。');
        }

        editor.undoManager.transact(() => {
            editor.insertContent(finalHtml);
            editor.save();
        });

        const serverWarnings = Array.isArray(result?.warnings) ? result.warnings.filter((item) => typeof item === 'string' && item.trim() !== '') : [];
        serverWarnings.slice(0, 2).forEach((warning) => {
            openEditorNotice(editor, warning, 'info', 5000);
        });

        if (!shouldSyncImages && importableImageCount > 0) {
            openEditorNotice(editor, `已按你的选择仅导入文本，未同步 ${importableImageCount} 张图片。`, 'info', 5000);
            return;
        }

        if (uploadResult.failed > 0) {
            openEditorNotice(editor, `导入完成：图片成功 ${uploadResult.uploaded} 张，失败 ${uploadResult.failed} 张。`, 'warning', 5000);
            uploadResult.failures.slice(0, 2).forEach((message) => {
                openEditorNotice(editor, message, 'warning', 5000);
            });
            return;
        }

        openEditorNotice(editor, `导入完成：文本已插入，图片成功 ${uploadResult.uploaded} 张。`, 'success', 5000);
    } finally {
        window.clearInterval(pendingNoticeTimer);
    }
}

function createWordImportInput(editor) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.docx,.doc,.wps';
    input.hidden = true;
    input.addEventListener('change', async () => {
        const file = input.files?.[0];
        input.value = '';

        if (!file) {
            return;
        }

        const formData = new FormData();
        formData.append('file', file);

        try {
            await importRichContent(editor, formData);
        } catch (error) {
            openEditorNotice(editor, error instanceof Error ? error.message : '导入失败，请稍后重试。', 'warning', 5000);
        }
    });

    document.body.appendChild(input);
    return input;
}

function bindSmartPasteImport(editor) {
    editor.on('paste', async (event) => {
        const clipboardData = event?.clipboardData || event?.originalEvent?.clipboardData;
        const html = clipboardData?.getData?.('text/html') || '';

        if (!html || html.trim() === '') {
            return;
        }

        const likelyOfficeOrRichContent = /class=["'][^"']*Mso|urn:schemas-microsoft-com|<img\b/i.test(html);
        if (!likelyOfficeOrRichContent) {
            return;
        }

        event.preventDefault();
        const formData = new FormData();
        formData.append('html', html);

        try {
            await importRichContent(editor, formData);
        } catch (error) {
            openEditorNotice(editor, error instanceof Error ? error.message : '粘贴导入失败，请稍后重试。', 'warning', 5000);
        }
    });
}

function showArticleTypesettingToast() {
    showStyledEditorToast({
        title: '排版已优化完成',
        text: '正文已统一为 14px，段落、列表和表格也一起整理好了。',
    });
}

function clearEditorFormatting(editor) {
    const body = editor?.getBody?.();
    const embeds = body ? Array.from(body.querySelectorAll('.bilibili-video-embed[data-bilibili-video="1"]')) : [];

    if (embeds.length === 0) {
        editor.execCommand('RemoveFormat');
        editor.save();
        return;
    }

    const placeholders = embeds.map((node, index) => {
        const token = `__CMS_BILIBILI_EMBED_${Date.now()}_${index}__`;
        const textNode = editor.getDoc().createTextNode(token);
        node.replaceWith(textNode);
        return { token, html: node.outerHTML };
    });

    editor.execCommand('RemoveFormat');

    let restoredHtml = editor.getContent({ format: 'html' });
    placeholders.forEach(({ token, html }) => {
        restoredHtml = restoredHtml.replace(token, html);
    });

    const parser = new DOMParser();
    const documentFragment = parser.parseFromString(`<div data-clear-format-root>${restoredHtml}</div>`, 'text/html');
    const root = documentFragment.body.querySelector('[data-clear-format-root]');

    const isEmptyParagraph = (element) => {
        if (!element || element.tagName?.toLowerCase() !== 'p') {
            return false;
        }

        return (element.innerHTML || '')
            .replace(/&nbsp;/gi, ' ')
            .replace(/<br\s*\/?>/gi, ' ')
            .replace(/\s+/g, '') === '';
    };

    root?.querySelectorAll('.bilibili-video-embed[data-bilibili-video="1"]').forEach((node) => {
        while (isEmptyParagraph(node.previousElementSibling)) {
            node.previousElementSibling.remove();
        }

        while (isEmptyParagraph(node.nextElementSibling)) {
            node.nextElementSibling.remove();
        }
    });

    restoredHtml = root?.innerHTML || restoredHtml;

    editor.undoManager.transact(() => {
        editor.setContent(restoredHtml);
        editor.save();
    });
}

tinymce.init({
    selector: 'textarea.rich-editor',
    height: 520,
    language: 'zh-CN',
    language_url: '/assets/tinymce/langs/zh-CN.js',
    toolbar_mode: 'wrap',
    menubar: false,
    branding: false,
    promotion: false,
    license_key: 'gpl',
    entity_encoding: 'raw',
    convert_urls: false,
    relative_urls: false,
    images_upload_url: imageUploadUrl,
    automatic_uploads: true,
    images_reuse_filename: false,
    plugins: 'autolink anchor code codesample fullscreen image link lists media noneditable searchreplace table visualblocks wordcount',
    noneditable_class: 'mceNonEditable',
    content_css: ['/css/site-content-render.css'],
    content_style: 'body { font-family: sans-serif; font-size: 15px; line-height: 1.85; } .bilibili-video-embed { width: fit-content; max-width: 100%; margin: 20px auto; padding: 16px 18px; border: 1px solid #e5e7eb; border-radius: 16px; background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%); color: #334155; text-align: center; cursor: pointer; transition: box-shadow .18s ease, border-color .18s ease, transform .18s ease; } .bilibili-video-embed:hover { border-color: #94a3b8; box-shadow: 0 0 0 2px rgba(0, 71, 171, 0.08); } .bilibili-video-embed.is-selected { border-color: #0047AB; box-shadow: 0 0 0 2px rgba(0, 71, 171, 0.18); } .bilibili-video-embed__title { font-size: 14px; font-weight: 700; color: #1e3a8a; } .bilibili-video-embed__meta { margin-top: 6px; font-size: 12px; color: #64748b; }',
    font_family_formats: '默认字体=sans-serif;宋体=SimSun,STSong,serif;黑体=SimHei,Heiti SC,sans-serif;楷体=KaiTi,Kaiti SC,serif;仿宋=FangSong,STFangsong,serif;Arial=Arial,Helvetica,sans-serif;Times New Roman=Times New Roman,Times,serif;Courier New=Courier New,Courier,monospace',
    setup(editor) {
        editor.ui.registry.addButton('linkCn', { icon: 'link', tooltip: '插入链接', onAction: () => editor.execCommand('mceLink') });
        editor.ui.registry.addButton('mediaCn', { text: '媒体', tooltip: '插入媒体', onAction: () => editor.execCommand('mceMedia') });
        editor.ui.registry.addButton('schoolVideoEmbed', {
            text: '视频',
            tooltip: '插入哔哩哔哩视频',
            onAction: () => openVideoEmbed(editor.id),
        });
        editor.ui.registry.addButton('quoteCn', { icon: 'quote', tooltip: '引用', onAction: () => editor.execCommand('mceBlockQuote') });
        editor.ui.registry.addButton('codeSampleCn', { icon: 'sourcecode', tooltip: '代码演示', onAction: () => editor.execCommand('Codesample') });
        editor.ui.registry.addButton('codeCn', { icon: 'code-sample', tooltip: '内容源码', onAction: () => editor.execCommand('mceCodeEditor') });
        editor.ui.registry.addButton('clearCn', { text: '清', tooltip: '清除格式', onAction: () => clearEditorFormatting(editor) });
        editor.ui.registry.addButton('smartArticleFormat', {
            text: '排版',
            tooltip: '一键排版',
            onAction: () => applySmartTypesetting(editor),
        });
        editor.ui.registry.addIcon('word-import', '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M5 3.75A1.75 1.75 0 0 1 6.75 2h7.5a1.75 1.75 0 0 1 1.75 1.75V7h1.75A1.75 1.75 0 0 1 19.5 8.75v11.5A1.75 1.75 0 0 1 17.75 22h-11.5A1.75 1.75 0 0 1 4.5 20.25V3.75Zm1.5 0v16.5c0 .14.11.25.25.25h11a.25.25 0 0 0 .25-.25V8.75a.25.25 0 0 0-.25-.25H15.5V3.75a.25.25 0 0 0-.25-.25h-8.5a.25.25 0 0 0-.25.25Zm3.1 8.35h1.2l.85 4.05.94-2.74h1.02l.95 2.74.84-4.05h1.2l-1.5 6.15h-1.01l-1-2.9-1.01 2.9H11.1l-1.5-6.15Z"/></svg>');
        const wordImportInput = createWordImportInput(editor);
        editor.ui.registry.addButton('wordImportCn', {
            icon: 'word-import',
            text: 'Word！',
            tooltip: '导入 Word/WPS 内容（图文）',
            onAction: () => wordImportInput.click(),
        });
        editor.ui.registry.addToggleButton('visualBlocksCn', {
            text: '显示块',
            tooltip: '显示块',
            onAction: () => editor.execCommand('mceVisualBlocks'),
            onSetup: (api) => editor.formatter.formatChanged('visualblocks', (state) => api.setActive(state)),
        });
        editor.ui.registry.addIcon('school-library', '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M4 6.25A2.25 2.25 0 0 1 6.25 4h3.43l1.6 1.75h6.47A2.25 2.25 0 0 1 20 8v8.75A2.25 2.25 0 0 1 17.75 19h-11.5A2.25 2.25 0 0 1 4 16.75V6.25Zm2.25-.75a.75.75 0 0 0-.75.75v.5h12.5V8a.75.75 0 0 0-.75-.75h-6.88l-1.6-1.75H6.25Zm11.25 3H5.75v8.25c0 .41.34.75.75.75h10.99a.75.75 0 0 0 .75-.75V8.5ZM8.2 15.6l1.95-2.28 1.45 1.62 2.28-2.72L16.8 15.6H8.2Z"/></svg>');
        editor.ui.registry.addButton('schoolResourceLibrary', {
            icon: 'school-library',
            text: '资源库',
            tooltip: '打开资源库',
            onAction: () => window.openSiteAttachmentLibrary?.({
                editorId: editor.id,
                mode: 'editor',
                context: 'content',
            }),
        });
        editor.ui.registry.addButton('schoolEmojiPicker', {
            text: '表情',
            tooltip: '插入表情',
            onAction: () => openEmojiPicker(editor.id),
        });
        editor.ui.registry.addButton('schoolFullscreen', {
            text: '全屏',
            tooltip: '全屏编辑',
            onAction: () => editor.execCommand('mceFullScreen'),
        });
        editor.on('init', () => {
            attachResourceLibraryMenubarButton(editor);
            window.setTimeout(() => attachResourceLibraryMenubarButton(editor), 120);
            window.setTimeout(() => attachResourceLibraryMenubarButton(editor), 320);
            document.dispatchEvent(new CustomEvent('tinymce-editor-ready', { detail: { id: editor.id } }));
        });
        bindSmartPasteImport(editor);
        editor.on('click', (event) => {
            const node = findVideoEmbedNode(event.target);
            if (node) {
                selectVideoEmbedNode(editor, node);
                return;
            }
            clearSelectedVideoEmbed(editor);
        });
        editor.on('contextmenu', (event) => {
            const node = findVideoEmbedNode(event.target);
            if (!node) {
                return;
            }
            event.preventDefault();
            selectVideoEmbedNode(editor, node);
            openVideoEmbed(editor.id, node);
        });
        editor.on('keydown', (event) => {
            const node = findVideoEmbedNode(editor.selection.getNode());
            if (!node || !['Backspace', 'Delete'].includes(event.key)) {
                return;
            }
            event.preventDefault();
            node.remove();
            clearSelectedVideoEmbed(editor);
            editor.save();
        });
        editor.on('change input undo redo', () => editor.save());
    },
    toolbar: 'undo redo wordImportCn fontfamily fontsize | bold italic underline forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent table visualblocks quoteCn linkCn codeSampleCn codeCn clearCn schoolVideoEmbed schoolEmojiPicker smartArticleFormat schoolResourceLibrary schoolFullscreen',
    images_upload_handler: (blobInfo, progress) => new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', imageUploadUrl);
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                progress(e.loaded / e.total * 100);
            }
        };

        xhr.onload = () => {
            if (xhr.status < 200 || xhr.status >= 300) {
                reject(`图片上传失败（${xhr.status}）`);
                return;
            }

            const json = JSON.parse(xhr.responseText || '{}');
            if (!json.location) {
                reject('图片上传失败，返回数据不完整');
                return;
            }

            resolve(json.location);
        };

        xhr.onerror = () => reject('图片上传失败');

        const formData = new FormData();
        formData.append('file', blobInfo.blob(), blobInfo.filename());
        xhr.send(formData);
    })
});
