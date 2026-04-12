(() => {
            const now = Date.now();

            document.querySelectorAll('[data-floating-promo]').forEach((element) => {
                const storageKey = element.getAttribute('data-floating-close-key') || '';
                const rememberClose = element.getAttribute('data-floating-remember-close') === '1';
                const expireHours = Number(element.getAttribute('data-floating-close-hours') || '24');

                if (rememberClose && storageKey) {
                    try {
                        const closedAt = Number(window.localStorage.getItem(storageKey) || '0');
                        const expireAt = closedAt + (expireHours * 60 * 60 * 1000);

                        if (closedAt > 0 && expireAt > now) {
                            element.remove();
                            return;
                        }

                        if (closedAt > 0 && expireAt <= now) {
                            window.localStorage.removeItem(storageKey);
                        }
                    } catch (error) {}
                }

                window.requestAnimationFrame(() => {
                    element.classList.add('is-ready');
                });

                element.querySelector('[data-floating-promo-close]')?.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    if (rememberClose && storageKey) {
                        try {
                            window.localStorage.setItem(storageKey, String(Date.now()));
                        } catch (error) {}
                    }

                    element.remove();
                });
            });
        })();
