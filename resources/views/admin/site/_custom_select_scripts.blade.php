<script>
    (() => {
        document.querySelectorAll('[data-site-select]').forEach((selectRoot) => {
            if (selectRoot.closest('#attachment-library-modal')) {
                return;
            }

            const nativeSelect = selectRoot.querySelector('.site-select-native');
            const trigger = selectRoot.querySelector('[data-select-trigger]');
            const panel = selectRoot.querySelector('[data-select-panel]');
            const isTreeSelect = selectRoot.classList.contains('channel-parent-select');
            let searchKeyword = '';
            let isComposing = false;

            if (!nativeSelect || !trigger || !panel) {
                return;
            }

            const buildOptions = () => {
                panel.innerHTML = '';

                let optionsHost = panel;
                let searchInput = null;
                let clearButton = null;

                if (isTreeSelect) {
                    const searchWrap = document.createElement('div');
                    searchWrap.className = 'site-select-search';
                    const searchInner = document.createElement('div');
                    searchInner.className = 'site-select-search-inner';
                    searchInput = document.createElement('input');
                    searchInput.type = 'text';
                    searchInput.className = 'site-select-search-input';
                    searchInput.placeholder = '搜索栏目';
                    searchInput.value = searchKeyword;
                    clearButton = document.createElement('button');
                    clearButton.type = 'button';
                    clearButton.className = 'site-select-search-clear';
                    clearButton.hidden = searchKeyword.trim() === '';
                    clearButton.setAttribute('aria-label', '清除搜索');
                    clearButton.innerHTML = '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 4l8 8"/><path d="M12 4 4 12"/></svg>';
                    searchInner.addEventListener('mousedown', (event) => {
                        event.stopPropagation();
                    });
                    searchInner.addEventListener('click', (event) => {
                        event.stopPropagation();
                    });
                    searchInner.appendChild(searchInput);
                    searchInner.appendChild(clearButton);
                    searchWrap.appendChild(searchInner);
                    panel.appendChild(searchWrap);

                    optionsHost = document.createElement('div');
                    optionsHost.className = 'site-select-options';
                    panel.appendChild(optionsHost);
                }

                const optionEntries = Array.from(nativeSelect.options).map((option) => ({
                    option,
                    value: option.value,
                    label: (option.textContent || '').trim(),
                    depth: Number.parseInt(option.dataset.depth || '0', 10) || 0,
                    hasChildren: option.dataset.hasChildren || '0',
                    ancestors: [],
                }));

                if (isTreeSelect) {
                    const stack = [];
                    optionEntries.forEach((entry) => {
                        if (entry.value === '') {
                            return;
                        }

                        while (stack.length > entry.depth) {
                            stack.pop();
                        }

                        entry.ancestors = stack.map((ancestor) => ancestor.value);
                        stack[entry.depth] = entry;
                    });
                }

                const keyword = searchKeyword.trim().toLowerCase();
                const matchedValues = new Set(
                    optionEntries
                        .filter((entry) => entry.value !== '' && (keyword === '' || entry.label.toLowerCase().includes(keyword)))
                        .map((entry) => entry.value)
                );

                let visibleOptionCount = 0;
                let visibleNamedCount = 0;

                optionEntries.forEach((entry) => {
                    const { option } = entry;
                    const shouldShow = !isTreeSelect
                        || option.value === ''
                        || keyword === ''
                        || matchedValues.has(entry.value)
                        || entry.ancestors.some((ancestorValue) => matchedValues.has(ancestorValue));

                    if (!shouldShow) {
                        return;
                    }

                    const optionButton = document.createElement('button');
                    optionButton.type = 'button';
                    optionButton.className = 'site-select-option';
                    optionButton.dataset.value = option.value;
                    optionButton.dataset.depth = option.dataset.depth || '0';
                    optionButton.dataset.hasChildren = option.dataset.hasChildren || '0';

                    if (isTreeSelect && option.dataset.depth) {
                        optionButton.classList.add('is-tree-option');
                        optionButton.style.setProperty('--option-depth', option.dataset.depth);
                    }

                    if (option.selected) {
                        optionButton.classList.add('is-active');
                        trigger.textContent = option.textContent;
                    }

                    const optionIcon = !isTreeSelect || option.value === ''
                        ? ''
                        : (option.dataset.hasChildren === '1'
                            ? `<svg class="site-select-option-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M1.75 4.75A1.5 1.5 0 0 1 3.25 3.25h3.1c.38 0 .74.14 1.03.39l.9.79c.18.15.41.24.64.24h3.83a1.5 1.5 0 0 1 1.5 1.5v5.58a1.5 1.5 0 0 1-1.5 1.5H3.25a1.5 1.5 0 0 1-1.5-1.5V4.75Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>`
                            : `<svg class="site-select-option-icon is-leaf" viewBox="0 0 16 16" aria-hidden="true"><path d="M4.25 1.75h4.29c.4 0 .78.16 1.06.44l2.21 2.21c.28.28.44.66.44 1.06v7.29a1.5 1.5 0 0 1-1.5 1.5h-6.5a1.5 1.5 0 0 1-1.5-1.5v-9.5a1.5 1.5 0 0 1 1.5-1.5Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/><path d="M8.5 1.75V4.5c0 .41.34.75.75.75H12" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>`);

                    optionButton.innerHTML = `
                        <span class="site-select-option-label">${optionIcon}<span class="site-select-option-label-text">${option.textContent}</span></span>
                        <svg class="site-select-check" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5 6.5 11.5 12.5 4.5"/></svg>
                    `;

                    optionButton.addEventListener('click', () => {
                        nativeSelect.value = option.value;
                        Array.from(nativeSelect.options).forEach((nativeOption) => {
                            nativeOption.selected = nativeOption.value === option.value;
                        });
                        buildOptions();
                        closeSelect();
                        nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    });

                    optionsHost.appendChild(optionButton);
                    visibleOptionCount += 1;
                    if (option.value !== '') {
                        visibleNamedCount += 1;
                    }
                });

                if (isTreeSelect) {
                    searchInput?.addEventListener('compositionstart', () => {
                        isComposing = true;
                    });

                    searchInput?.addEventListener('compositionend', (event) => {
                        isComposing = false;
                        searchKeyword = event.target.value || '';
                        buildOptions();
                        panel.querySelector('.site-select-search-input')?.focus();
                    });

                    searchInput?.addEventListener('input', (event) => {
                        if (isComposing) {
                            searchKeyword = event.target.value || '';
                            return;
                        }

                        searchKeyword = event.target.value || '';
                        buildOptions();
                        panel.querySelector('.site-select-search-input')?.focus();
                    });

                    clearButton?.addEventListener('mousedown', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                    });

                    clearButton?.addEventListener('click', () => {
                        isComposing = false;
                        searchKeyword = '';
                        buildOptions();
                        panel.querySelector('.site-select-search-input')?.focus();
                    });

                    if (visibleOptionCount === 0 || (keyword !== '' && visibleNamedCount === 0)) {
                        const empty = document.createElement('div');
                        empty.className = 'site-select-empty';
                        empty.textContent = '没有找到匹配的栏目';
                        optionsHost.appendChild(empty);
                    }
                }
            };

            const closeSelect = () => {
                selectRoot.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
            };

            trigger.addEventListener('click', () => {
                const nextState = !selectRoot.classList.contains('is-open');

                document.querySelectorAll('[data-site-select].is-open').forEach((openSelect) => {
                    openSelect.classList.remove('is-open');
                    openSelect.querySelector('[data-select-trigger]')?.setAttribute('aria-expanded', 'false');
                });

                if (nextState) {
                    selectRoot.classList.add('is-open');
                    trigger.setAttribute('aria-expanded', 'true');
                    if (isTreeSelect) {
                        window.requestAnimationFrame(() => {
                            panel.querySelector('.site-select-search-input')?.focus();
                        });
                    }
                }
            });

            document.addEventListener('click', (event) => {
                if (!selectRoot.contains(event.target)) {
                    closeSelect();
                }
            });

            buildOptions();
        });
    })();
</script>
