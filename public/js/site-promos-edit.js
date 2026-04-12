(() => {
    const configRoot = document.getElementById('site-promo-edit-config');
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

    const modeInput = document.getElementById('display_mode');
    const scopeInput = document.getElementById('page_scope');
    const maxItemsInput = document.getElementById('max_items');
    const shell = document.querySelector('[data-promo-preview-shell]');
    const chip = document.querySelector('[data-promo-preview-chip]');
    const sceneText = document.querySelector('[data-promo-preview-scene]');
    const maxItemsNote = document.querySelector('[data-promo-max-items-note]');

    if (modeInput && scopeInput && shell && chip && sceneText) {
        const modeLabels = parseJson(configRoot?.dataset.modeLabels, {});
        const maxItemLimits = {
            single: 1,
            floating: 2,
            carousel: 10,
        };
        const scenes = {
            single: '适合首页主视觉、栏目头图、详情页头图等单图位点。',
            carousel: '适合首页轮播、专题推荐、多图活动位等连续展示场景。',
            floating: '适合右下角活动入口、通知提示、节日挂件等漂浮图。'
        };

        const syncPreview = () => {
            const mode = modeInput.value || 'single';
            const isSingle = mode === 'single';
            const currentLimit = maxItemLimits[mode] || 20;

            if (maxItemsInput) {
                maxItemsInput.setAttribute('max', String(currentLimit));

                if (isSingle) {
                    maxItemsInput.value = '1';
                    maxItemsInput.setAttribute('readonly', 'readonly');
                    maxItemsInput.setAttribute('aria-readonly', 'true');
                } else {
                    maxItemsInput.removeAttribute('readonly');
                    maxItemsInput.removeAttribute('aria-readonly');

                    if ((Number(maxItemsInput.value || '0') || 0) < 1) {
                        maxItemsInput.value = '1';
                    }

                    if ((Number(maxItemsInput.value || '0') || 0) > currentLimit) {
                        maxItemsInput.value = String(currentLimit);
                    }
                }
            }

            if (maxItemsNote) {
                maxItemsNote.textContent = mode === 'single'
                    ? '单图模式固定为 1，无需单独设置。'
                    : mode === 'floating'
                        ? '漂浮图模式最多支持 2 项，避免页面遮挡。'
                        : '轮播图模式最多支持 10 项，按实际需要调整。';
            }

            shell.dataset.mode = mode;
            chip.textContent = modeLabels[mode] || mode;
            sceneText.textContent = scenes[mode] || '适合模板图宣展示。';
        };

        [modeInput, scopeInput, maxItemsInput].forEach((input) => {
            input?.addEventListener('input', syncPreview);
            input?.addEventListener('change', syncPreview);
        });

        syncPreview();
    }

    const messages = parseJson(configRoot?.dataset.errorMessages, []);
    const formattedMessage = [...new Set((messages || [])
        .map((message) => String(message || '').trim())
        .filter((message) => message !== '')
        .map((message) => message.replace(/[，。；、]+$/u, '')))]
        .join('，') + '。';

    if (formattedMessage !== '。' && typeof window.showMessage === 'function') {
        window.showMessage(formattedMessage, 'error');
    }
})();
