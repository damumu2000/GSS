(() => {
    document.querySelectorAll('.log-filters-card [data-select]').forEach((selectRoot) => {
        const nativeSelect = selectRoot.querySelector('.custom-select-native');
        const trigger = selectRoot.querySelector('[data-select-trigger]');
        const panel = selectRoot.querySelector('[data-select-panel]');

        if (!nativeSelect || !trigger || !panel) {
            return;
        }

        const shouldEnableSearch = nativeSelect.options.length > 8;
        const searchInput = shouldEnableSearch ? document.createElement('input') : null;
        const optionsWrap = document.createElement('div');
        const emptyState = document.createElement('div');

        optionsWrap.className = 'custom-select-options';
        emptyState.className = 'custom-select-empty';
        emptyState.textContent = '没有匹配的动作';

        const closeSelect = () => {
            selectRoot.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        };

        const filterOptions = (keyword) => {
            const normalizedKeyword = keyword.trim().toLowerCase();
            let visibleCount = 0;

            optionsWrap.querySelectorAll('.custom-select-option').forEach((optionButton) => {
                const text = optionButton.textContent?.toLowerCase() ?? '';
                const matched = normalizedKeyword === '' || text.includes(normalizedKeyword);
                optionButton.hidden = !matched;

                if (matched) {
                    visibleCount += 1;
                }
            });

            selectRoot.classList.toggle('is-filtering-empty', visibleCount === 0);
        };

        const buildOptions = () => {
            panel.innerHTML = '';

            if (searchInput) {
                searchInput.type = 'search';
                searchInput.className = 'custom-select-search';
                searchInput.placeholder = nativeSelect.id === 'action' ? '搜索动作' : '搜索';
                panel.appendChild(searchInput);
            }

            panel.appendChild(optionsWrap);
            panel.appendChild(emptyState);
            optionsWrap.innerHTML = '';

            Array.from(nativeSelect.options).forEach((option) => {
                const optionButton = document.createElement('button');
                optionButton.type = 'button';
                optionButton.className = 'custom-select-option';
                optionButton.dataset.value = option.value;

                if (option.selected) {
                    optionButton.classList.add('is-active');
                    trigger.textContent = option.textContent;
                }

                optionButton.innerHTML = `
                    <span>${option.textContent}</span>
                    <svg class="custom-select-check" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5 6.5 11.5 12.5 4.5"/></svg>
                `;

                optionButton.addEventListener('click', () => {
                    nativeSelect.value = option.value;
                    Array.from(nativeSelect.options).forEach((nativeOption) => {
                        nativeOption.selected = nativeOption.value === option.value;
                    });
                    buildOptions();
                    closeSelect();
                });

                optionsWrap.appendChild(optionButton);
            });

            if (searchInput) {
                searchInput.value = '';
                filterOptions('');
            }
        };

        trigger.addEventListener('click', () => {
            const nextState = !selectRoot.classList.contains('is-open');
            document.querySelectorAll('.log-filters-card [data-select].is-open').forEach((openSelect) => {
                openSelect.classList.remove('is-open');
                const openTrigger = openSelect.querySelector('[data-select-trigger]');
                openTrigger?.setAttribute('aria-expanded', 'false');
            });
            if (nextState) {
                selectRoot.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
                if (searchInput) {
                    queueMicrotask(() => searchInput.focus());
                }
            }
        });

        document.addEventListener('click', (event) => {
            if (!selectRoot.contains(event.target)) {
                closeSelect();
            }
        });

        buildOptions();

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                filterOptions(searchInput.value);
            });
        }
    });
})();
