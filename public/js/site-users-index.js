(() => {
    document.querySelectorAll('[data-select]').forEach((selectRoot) => {
        const nativeSelect = selectRoot.querySelector('.custom-select-native');
        const trigger = selectRoot.querySelector('[data-select-trigger]');
        const panel = selectRoot.querySelector('[data-select-panel]');

        if (!nativeSelect || !trigger || !panel) {
            return;
        }

        const render = () => {
            const options = Array.from(nativeSelect.options);
            const selectedOption = options[nativeSelect.selectedIndex] ?? options[0];
            trigger.textContent = selectedOption?.textContent?.trim() || '';
            panel.innerHTML = options.map((option) => {
                const isActive = option.selected ? 'is-active' : '';
                return `
                    <button class="custom-select-option ${isActive}" type="button" data-value="${option.value}" role="option" aria-selected="${option.selected ? 'true' : 'false'}">
                        <span>${option.textContent}</span>
                        <svg class="custom-select-check" viewBox="0 0 16 16" aria-hidden="true"><path d="m3.5 8 2.5 2.5 6-6"/></svg>
                    </button>
                `;
            }).join('');
        };

        render();

        trigger.addEventListener('click', () => {
            document.querySelectorAll('[data-select].is-open').forEach((item) => {
                if (item !== selectRoot) {
                    item.classList.remove('is-open');
                    item.querySelector('[data-select-trigger]')?.setAttribute('aria-expanded', 'false');
                }
            });

            const open = !selectRoot.classList.contains('is-open');
            selectRoot.classList.toggle('is-open', open);
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        panel.addEventListener('click', (event) => {
            const option = event.target.closest('.custom-select-option');
            if (!option) {
                return;
            }

            nativeSelect.value = option.dataset.value ?? '';
            nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
            render();
            selectRoot.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        });

        nativeSelect.addEventListener('change', render);
    });

    document.addEventListener('click', (event) => {
        document.querySelectorAll('[data-select].is-open').forEach((selectRoot) => {
            if (!selectRoot.contains(event.target)) {
                selectRoot.classList.remove('is-open');
                selectRoot.querySelector('[data-select-trigger]')?.setAttribute('aria-expanded', 'false');
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('[data-select].is-open').forEach((selectRoot) => {
                selectRoot.classList.remove('is-open');
                selectRoot.querySelector('[data-select-trigger]')?.setAttribute('aria-expanded', 'false');
            });
        }
    });
})();
