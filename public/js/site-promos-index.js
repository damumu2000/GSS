(() => {
    const configRoot = document.getElementById('site-promos-index-config');

    document.querySelectorAll('[data-promo-delete-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (typeof window.showConfirmDialog !== 'function') {
                return;
            }

            event.preventDefault();
            const name = form.getAttribute('data-promo-delete-name') || '该图宣位';

            window.showConfirmDialog({
                title: '确认删除图宣位？',
                text: `删除后将移除位点配置：${name}`,
                confirmText: '删除图宣位',
                onConfirm: () => form.submit(),
            });
        });
    });

    const callModal = document.getElementById('promo-call-modal');
    const callTitle = callModal?.querySelector('[data-promo-call-title]');
    const callDesc = callModal?.querySelector('[data-promo-call-desc]');
    const callCodeLabel = callModal?.querySelector('[data-promo-call-code-label]');
    const callCodeBlock = callModal?.querySelector('[data-promo-call-code-block]');
    const callExampleLabel = callModal?.querySelector('[data-promo-call-example-label]');
    const callExampleBlock = callModal?.querySelector('[data-promo-call-example-block]');
    const callCssBlock = callModal?.querySelector('[data-promo-call-css-block]');
    const callJsBlock = callModal?.querySelector('[data-promo-call-js-block]');
    const callNote = callModal?.querySelector('[data-promo-call-note]');
    const callParams = callModal?.querySelector('[data-promo-call-params]');

    const openCallModal = (button) => {
        if (!callModal || !callTitle || !callDesc || !callCodeLabel || !callCodeBlock || !callExampleLabel || !callExampleBlock || !callCssBlock || !callJsBlock || !callNote || !callParams) {
            return;
        }

        const name = button.getAttribute('data-promo-call-name') || '图宣位';
        const code = button.getAttribute('data-promo-call-code') || '';
        const mode = button.getAttribute('data-promo-call-mode') || 'single';
        const modeLabel = button.getAttribute('data-promo-call-mode-label') || mode;
        const limit = Number(button.getAttribute('data-promo-call-limit') || '1') || 1;
        const assignName = mode === 'single' ? 'promoItem' : 'promoItems';
        const callSnippet = mode === 'single'
            ? `{% set ${assignName} = promo('${code}') %}`
            : mode === 'floating'
                ? `{{ promoFloating('${code}') }}`
                : `{% set ${assignName} = promos('${code}', limit=${limit}) %}`;
        const promoSnippetStylesheet = '<link rel="stylesheet" href="/css/promo-snippets.css">';
        const promoSnippetScript = '<script src="/js/promo-snippets.js" defer></scr' + 'ipt>';
        const floatingSnippet = `${promoSnippetStylesheet}\n{{ promoFloating('${code}') }}\n${promoSnippetScript}`;
        const carouselSnippet = `<div class="promo-carousel" data-promo-carousel>\n  <div class="promo-carousel-track" data-promo-carousel-track>\n    {% for item in ${assignName} %}\n      <a class="promo-carousel-slide" href="{{ valueOr(value=item.link_url, default='#') }}" target="{{ valueOr(value=item.link_target, default='_self') }}"{% if item.link_target == '_blank' %} rel="noopener"{% endif %}>\n        <img src="{{ item.image_url }}" alt="{{ valueOr(value=item.image_alt, default=item.title) }}">\n        <span class="promo-carousel-copy">\n          {% if item.title %}<strong>{{ item.title }}</strong>{% endif %}\n          {% if item.subtitle %}<em>{{ item.subtitle }}</em>{% endif %}\n        </span>\n      </a>\n    {% endfor %}\n  </div>\n\n  <button class="promo-carousel-arrow is-prev" type="button" data-promo-carousel-prev aria-label="上一张">‹</button>\n  <button class="promo-carousel-arrow is-next" type="button" data-promo-carousel-next aria-label="下一张">›</button>\n\n  <div class="promo-carousel-dots">\n    {% for item in ${assignName} %}\n      <button class="promo-carousel-dot{% if loop.first %} is-active{% endif %}" type="button" data-promo-carousel-dot="{{ loop.index }}" aria-label="切换到第 {{ loop.iteration }} 张"></button>\n    {% endfor %}\n  </div>\n</div>\n\n${promoSnippetStylesheet}\n${promoSnippetScript}`;
        const exampleSnippet = mode === 'single'
            ? `{% if ${assignName} %}\n  <a href="{{ valueOr(value=${assignName}.link_url, default='#') }}" target="{{ valueOr(value=${assignName}.link_target, default='_self') }}"{% if ${assignName}.link_target == '_blank' %} rel="noopener"{% endif %}>\n    <img src="{{ ${assignName}.image_url }}" alt="{{ valueOr(value=${assignName}.image_alt, default=${assignName}.title) }}">\n  </a>\n{% endif %}`
            : mode === 'multi'
                ? carouselSnippet
                : mode === 'floating'
                    ? floatingSnippet
                    : `{% for item in ${assignName} %}\n  <a href="{{ valueOr(value=item.link_url, default='#') }}" target="{{ valueOr(value=item.link_target, default='_self') }}"{% if item.link_target == '_blank' %} rel="noopener"{% endif %}>\n    <img src="{{ item.image_url }}" alt="{{ valueOr(value=item.image_alt, default=item.title) }}">\n  </a>\n{% endfor %}`;

        callTitle.textContent = mode === 'single'
            ? '单图调用方法'
            : mode === 'multi'
                ? '多图调用方法'
                : mode === 'floating'
                    ? '漂浮图调用方法'
                    : `${modeLabel}调用方法`;
        callDesc.textContent = `图宣位：${name} · ${modeLabel}`;
        callCodeLabel.textContent = '引入数据';
        callCodeBlock.textContent = callSnippet;
        callExampleLabel.textContent = '代入模板示例';
        callExampleBlock.textContent = exampleSnippet;
        callCssBlock.textContent = '正在读取 /css/promo-snippets.css ...';
        fetch('/css/promo-snippets.css', { cache: 'no-store' })
            .then((response) => (response.ok ? response.text() : Promise.reject()))
            .then((cssText) => {
                callCssBlock.textContent = cssText.trim();
            })
            .catch(() => {
                callCssBlock.textContent = '无法读取 /css/promo-snippets.css，请确认该文件已发布到 public/css 目录。';
            });
        callJsBlock.textContent = '正在读取 /js/promo-snippets.js ...';
        fetch('/js/promo-snippets.js', { cache: 'no-store' })
            .then((response) => (response.ok ? response.text() : Promise.reject()))
            .then((jsText) => {
                callJsBlock.textContent = jsText.trim();
            })
            .catch(() => {
                callJsBlock.textContent = '无法读取 /js/promo-snippets.js，请确认该文件已发布到 public/js 目录。';
            });
        callNote.textContent = mode === 'single'
            ? '单图位推荐用 promo 调用，返回单条图宣数据。'
            : mode === 'multi'
                ? '多图位推荐用 promos 调用。需要轮播效果时，引入上面的 CSS 和 JS，并使用完整容器示例。'
                : mode === 'floating'
                    ? '漂浮图使用 promoFloating 调用。需要漂浮图的模板手动引入 CSS 和 JS，不用的模板不加载资源。'
                    : `当前位点适合用 promos 调用，返回图宣列表。建议 limit 不超过 ${limit}，模板里按 for 循环渲染即可。`;

        const tagParams = mode === 'single'
            ? [
                ['code', '图宣位代码，必填。写这个值，系统才能知道你要取哪一个图宣位。'],
            ]
            : mode === 'multi'
                ? [
                    ['code', '图宣位代码，必填。写这个值，系统才能找到对应的多图位。'],
                    ['limit', '取几条数据，可选。一般写成这个图宣位实际要显示的张数。'],
                ]
                : mode === 'floating'
                    ? [
                        ['code', '图宣位代码，必填。写这个值，系统才能找到对应的漂浮图位。'],
                    ]
                    : [
                        ['code', '图宣位代码，必填。写这个值，系统才能找到对应的图宣位。'],
                        ['limit', '取几条数据，可选。'],
                    ];
        const renderParamItems = (items) => items.map(([nameText, descText]) => `
            <div class="promo-call-param-item">
                <div class="promo-call-param-name">${nameText}</div>
                <div class="promo-call-param-desc">${descText}</div>
            </div>
        `).join('');
        callParams.innerHTML = `
            <div class="promo-call-param-group">
                <div class="promo-call-param-group-title">调用标签参数</div>
                <div class="promo-call-param-list">
                    ${renderParamItems(tagParams)}
                </div>
            </div>
        `;

        callModal.hidden = false;
        document.body.classList.add('has-modal-open');
    };

    const closeCallModal = () => {
        if (!callModal) {
            return;
        }

        callModal.hidden = true;
        document.body.classList.remove('has-modal-open');
    };

    document.querySelectorAll('[data-promo-call-trigger]').forEach((button) => {
        button.addEventListener('click', () => openCallModal(button));
    });

    callModal?.querySelectorAll('[data-promo-call-close]').forEach((element) => {
        element.addEventListener('click', closeCallModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && callModal && !callModal.hidden) {
            closeCallModal();
        }
    });

    document.querySelectorAll('.promo-card-preview').forEach((preview) => {
        const track = preview.querySelector('[data-promo-preview-track]');
        const dots = Array.from(preview.querySelectorAll('[data-promo-preview-dot]'));
        const prev = preview.querySelector('[data-promo-preview-prev]');
        const next = preview.querySelector('[data-promo-preview-next]');

        if (!track || dots.length <= 1) {
            return;
        }

        let activeIndex = 0;
        const slides = Array.from(track.querySelectorAll('.promo-card-preview-slide'));

        const sync = () => {
            slides[activeIndex]?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
            dots.forEach((dot, index) => {
                dot.classList.toggle('is-active', index === activeIndex);
            });
        };

        prev?.addEventListener('click', () => {
            activeIndex = activeIndex === 0 ? dots.length - 1 : activeIndex - 1;
            sync();
        });

        next?.addEventListener('click', () => {
            activeIndex = activeIndex === dots.length - 1 ? 0 : activeIndex + 1;
            sync();
        });

        sync();
    });

    const promoErrorMessage = configRoot?.dataset.promoErrorMessage || '';
    if (promoErrorMessage && typeof window.showMessage === 'function') {
        window.showMessage(promoErrorMessage, 'error');
    }
})();
