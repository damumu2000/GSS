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
    const shell = document.querySelector('[data-promo-preview-shell]');
    const chip = document.querySelector('[data-promo-preview-chip]');
    const sceneText = document.querySelector('[data-promo-preview-scene]');

    if (modeInput && shell && chip && sceneText) {
        const modeLabels = parseJson(configRoot?.dataset.modeLabels, {});
        const scenes = {
            single: '适合首页主视觉、栏目头图、详情页头图等单图位点。',
            multi: '适合首页轮播、专题推荐、多图活动位等连续展示场景。',
            floating: '适合右下角活动入口、通知提示、节日挂件等漂浮图。'
        };

        const syncPreview = () => {
            const mode = modeInput.value || 'single';

            shell.dataset.mode = mode;
            chip.textContent = modeLabels[mode] || mode;
            sceneText.textContent = scenes[mode] || '适合模板图宣展示。';
        };

        [modeInput].forEach((input) => {
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
