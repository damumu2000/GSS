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
        const scopeLabel = button.getAttribute('data-promo-call-scope-label') || '当前页面';
        const limit = Number(button.getAttribute('data-promo-call-limit') || '1') || 1;
        const assignName = mode === 'single' ? 'promoItem' : 'promoItems';
        const callSnippet = mode === 'single'
            ? `{% set ${assignName} = promo(code='${code}') %}`
            : `{% set ${assignName} = promos(code='${code}', display_mode='${mode}', limit=${limit}) %}`;
        const promoSnippetStylesheet = '<link rel="stylesheet" href="/css/promo-snippets.css">';
        const promoSnippetScript = '<script src="/js/promo-snippets.js" defer></scr' + 'ipt>';
        const floatingSnippet = `{% for item in ${assignName} %}\n  <div\n    class="promo-floating promo-floating--{{ item.display.position }} promo-floating--{{ item.display.animation }}{% if item.display.show_on == 'pc' %} promo-floating--pc-only{% else %}{% if item.display.show_on == 'mobile' %} promo-floating--mobile-only{% endif %}{% endif %}"\n    data-floating-offset-x="{{ item.display.offset_x_token }}"\n    data-floating-offset-y="{{ item.display.offset_y_token }}"\n    data-floating-width="{{ item.display.width_token }}"\n    data-floating-height="{% if item.display.height_token %}{{ item.display.height_token }}{% endif %}"\n    data-floating-z="{{ item.display.z_index_token }}"\n    data-floating-key="{% if item.display.remember_close %}{{ item.display.close_storage_key }}{% endif %}"\n    data-floating-expire="{{ item.display.close_expire_hours }}"\n  >\n    <a class="promo-floating-link" href="{{ valueOr(value=item.link_url, default='#') }}" target="{{ valueOr(value=item.link_target, default='_self') }}"{% if item.link_target == '_blank' %} rel="noopener"{% endif %}>\n      <img src="{{ item.image_url }}" alt="{{ valueOr(value=item.image_alt, default=item.title) }}">\n    </a>\n\n    {% if item.display.closable %}\n      <button\n        class="promo-floating-close"\n        type="button"\n        aria-label="关闭漂浮图"\n        data-floating-close\n      >×</button>\n    {% endif %}\n  </div>\n{% endfor %}\n\n${promoSnippetStylesheet}\n${promoSnippetScript}`;
        const carouselSnippet = `<div class="promo-carousel" data-promo-carousel>\n  <div class="promo-carousel-track" data-promo-carousel-track>\n    {% for item in ${assignName} %}\n      <a class="promo-carousel-slide" href="{{ valueOr(value=item.link_url, default='#') }}" target="{{ valueOr(value=item.link_target, default='_self') }}"{% if item.link_target == '_blank' %} rel="noopener"{% endif %}>\n        <img src="{{ item.image_url }}" alt="{{ valueOr(value=item.image_alt, default=item.title) }}">\n        <span class="promo-carousel-copy">\n          {% if item.title %}<strong>{{ item.title }}</strong>{% endif %}\n          {% if item.subtitle %}<em>{{ item.subtitle }}</em>{% endif %}\n        </span>\n      </a>\n    {% endfor %}\n  </div>\n\n  <button class="promo-carousel-arrow is-prev" type="button" data-promo-carousel-prev aria-label="上一张">‹</button>\n  <button class="promo-carousel-arrow is-next" type="button" data-promo-carousel-next aria-label="下一张">›</button>\n\n  <div class="promo-carousel-dots">\n    {% for item in ${assignName} %}\n      <button class="promo-carousel-dot{% if loop.first %} is-active{% endif %}" type="button" data-promo-carousel-dot="{{ loop.index }}" aria-label="切换到第 {{ loop.iteration }} 张"></button>\n    {% endfor %}\n  </div>\n</div>\n\n${promoSnippetStylesheet}\n${promoSnippetScript}`;
        const exampleSnippet = mode === 'single'
            ? `{% if ${assignName} %}\n  <a href="{{ valueOr(value=${assignName}.link_url, default='#') }}" target="{{ valueOr(value=${assignName}.link_target, default='_self') }}"{% if ${assignName}.link_target == '_blank' %} rel="noopener"{% endif %}>\n    <img src="{{ ${assignName}.image_url }}" alt="{{ valueOr(value=${assignName}.image_alt, default=${assignName}.title) }}">\n  </a>\n{% endif %}`
            : mode === 'carousel'
                ? carouselSnippet
                : mode === 'floating'
                    ? floatingSnippet
                    : `{% for item in ${assignName} %}\n  <a href="{{ valueOr(value=item.link_url, default='#') }}" target="{{ valueOr(value=item.link_target, default='_self') }}"{% if item.link_target == '_blank' %} rel="noopener"{% endif %}>\n    <img src="{{ item.image_url }}" alt="{{ valueOr(value=item.image_alt, default=item.title) }}">\n  </a>\n{% endfor %}`;

        callTitle.textContent = mode === 'single'
            ? '单图调用方法'
            : mode === 'carousel'
                ? '轮播图调用方法'
                : mode === 'floating'
                    ? '漂浮图调用方法'
                    : `${modeLabel}调用方法`;
        callDesc.textContent = `图宣位：${name} · ${scopeLabel} · ${modeLabel}`;
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
            ? '单图位推荐用 promo 调用，返回单条图宣数据。所属栏目和模板名称如果已配置，前台会自动按当前页面上下文优先匹配。'
            : mode === 'carousel'
                ? '轮播图建议直接使用完整容器、轨道、翻页按钮和圆点导航。上面的示例已经包含基础切换和自动轮播逻辑，拿过去就能直接改样式落地。'
                : mode === 'floating'
                    ? '漂浮图建议直接读取 item.display 下的位置、动画、宽度、层级和关闭记忆参数。上面的示例已经包含了常用的定位、动效和关闭记忆逻辑。'
                    : `当前位点适合用 promos 调用，返回图宣列表。建议 limit 不超过 ${limit}，模板里按 for 循环渲染即可。`;

        const tagParams = mode === 'single'
            ? [
                ['code', '图宣位代码，必填。写这个值，系统才能知道你要取哪一个图宣位。'],
                ['page_scope', '页面范围，可选。不写时一般会按当前页面自动判断。'],
                ['template_name', '模板名，可选。如果这个图宣位区分模板，可以用它指定要取哪套模板下的数据。'],
                ['channel_id', '栏目 id，可选。如果这个图宣位跟栏目有关，可以用它指定栏目。'],
            ]
            : mode === 'carousel'
                ? [
                    ['code', '图宣位代码，必填。写这个值，系统才能找到对应的轮播图位。'],
                    ['page_scope', '页面范围，可选。不写时一般会按当前页面自动判断。'],
                    ['display_mode', '展示模式，建议写成 carousel。这样能明确按轮播图方式取数据。'],
                    ['template_name', '模板名，可选。如果轮播位区分模板，可以用它指定模板。'],
                    ['channel_id', '栏目 id，可选。如果轮播位跟栏目绑定，可以用它指定栏目。'],
                    ['limit', '取几条数据，可选。一般写成这个图宣位实际要显示的张数。'],
                ]
                : mode === 'floating'
                    ? [
                        ['code', '图宣位代码，必填。写这个值，系统才能找到对应的漂浮图位。'],
                        ['page_scope', '页面范围，可选。不写时一般会按当前页面自动判断。'],
                        ['display_mode', '展示模式，建议写成 floating。这样能明确按漂浮图方式取数据。'],
                        ['template_name', '模板名，可选。如果漂浮图位区分模板，可以用它指定模板。'],
                        ['channel_id', '栏目 id，可选。如果漂浮图位跟栏目绑定，可以用它指定栏目。'],
                        ['limit', '取几条数据，可选。漂浮图通常建议写 1，避免同时出来多个漂浮挂件。'],
                    ]
                    : [
                        ['code', '图宣位代码，必填。写这个值，系统才能找到对应的图宣位。'],
                        ['page_scope', '页面范围，可选。'],
                        ['display_mode', '展示模式，用来告诉系统你想按哪种方式取数据。'],
                        ['template_name', '模板名，可选。'],
                        ['channel_id', '栏目 id，可选。'],
                        ['limit', '取几条数据，可选。'],
                    ];
        const floatingParams = mode === 'floating'
            ? [
                ['item.display.position', '漂浮位置。比如右下、左中、右上，前台用它决定挂件贴在哪个角或哪一侧。'],
                ['item.display.animation', '动画效果。比如轻浮动、呼吸、摇摆，前台可以直接按这个值切换动画样式。'],
                ['item.display.offset_x_token', '横向偏移档位。调用示例用 data 属性交给外部 CSS 生效，避免写内嵌样式。'],
                ['item.display.offset_y_token', '纵向偏移。控制离上边或下边多远。'],
                ['item.display.width_token', '漂浮图宽度档位。外部 CSS 会按这个值匹配对应宽度。'],
                ['item.display.height_token', '漂浮图高度档位。没填时一般按图片原比例显示。'],
                ['item.display.z_index_token', '层级档位。外部 CSS 会按这个值控制前后层级。'],
                ['item.display.show_on', '显示端。可区分全端、仅电脑端、仅手机端。'],
                ['item.display.closable', '是否允许关闭。前台可根据它决定要不要显示关闭按钮。'],
                ['item.display.remember_close', '是否记住关闭状态。打开后，用户关掉一次，下次访问可以继续隐藏。'],
                ['item.display.close_expire_hours', '关闭记忆时长，单位是小时。过了这个时间可以重新显示。'],
                ['item.display.close_storage_key', '关闭记忆用的本地缓存 key。前台如果要做“关闭后暂时不再显示”，就会用到它。'],
            ]
            : [];
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
            ${floatingParams.length ? `
            <div class="promo-call-param-group">
                <div class="promo-call-param-group-title">漂浮图专属参数</div>
                <div class="promo-call-param-list">
                    ${renderParamItems(floatingParams)}
                </div>
            </div>
            ` : ''}
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
