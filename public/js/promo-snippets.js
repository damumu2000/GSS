(() => {
    if (window.__promoSnippetsInitialized) {
        return;
    }

    window.__promoSnippetsInitialized = true;

    const storage = {
        get(key) {
            try {
                return window.localStorage.getItem(key);
            } catch (error) {
                return '';
            }
        },
        set(key, value) {
            try {
                window.localStorage.setItem(key, value);
            } catch (error) {
                // Storage can be unavailable in restricted browser contexts.
            }
        },
        remove(key) {
            try {
                window.localStorage.removeItem(key);
            } catch (error) {
                // Storage can be unavailable in restricted browser contexts.
            }
        },
    };

    document.querySelectorAll('.promo-floating').forEach((floating) => {
        const storageKey = floating.dataset.floatingKey || '';
        const rawExpireAt = storageKey ? storage.get(storageKey) : '';
        const expireAt = Number(rawExpireAt || '0');

        if (expireAt > Date.now()) {
            floating.remove();
            return;
        }

        if (storageKey && rawExpireAt && expireAt <= Date.now()) {
            storage.remove(storageKey);
        }
    });

    document.querySelectorAll('[data-floating-close]').forEach((button) => {
        button.addEventListener('click', () => {
            const floating = button.closest('.promo-floating');

            if (! floating) {
                return;
            }

            const storageKey = floating.dataset.floatingKey || '';
            const expireHours = Number(floating.dataset.floatingExpire || '24') || 24;

            if (storageKey) {
                const expireAt = Date.now() + expireHours * 60 * 60 * 1000;
                storage.set(storageKey, String(expireAt));
            }

            floating.remove();
        });
    });

    document.querySelectorAll('[data-promo-carousel]').forEach((carousel) => {
        const slides = Array.from(carousel.querySelectorAll('.promo-carousel-slide'));
        const dots = Array.from(carousel.querySelectorAll('[data-promo-carousel-dot]'));
        const prev = carousel.querySelector('[data-promo-carousel-prev]');
        const next = carousel.querySelector('[data-promo-carousel-next]');
        const dotsWrap = carousel.querySelector('.promo-carousel-dots');

        if (dots.length <= 1 || slides.length <= 1) {
            if (prev) {
                prev.hidden = true;
            }

            if (next) {
                next.hidden = true;
            }

            if (dotsWrap) {
                dotsWrap.hidden = true;
            }

            return;
        }

        let activeIndex = 0;
        let timerId = 0;

        const sync = () => {
            slides[activeIndex]?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
            dots.forEach((dot, index) => {
                dot.classList.toggle('is-active', index === activeIndex);
            });
        };

        const goTo = (index) => {
            activeIndex = (index + dots.length) % dots.length;
            sync();
        };

        const restart = () => {
            if (timerId) {
                window.clearInterval(timerId);
            }

            timerId = window.setInterval(() => {
                goTo(activeIndex + 1);
            }, 5000);
        };

        prev?.addEventListener('click', () => {
            goTo(activeIndex - 1);
            restart();
        });

        next?.addEventListener('click', () => {
            goTo(activeIndex + 1);
            restart();
        });

        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                goTo(index);
                restart();
            });
        });

        sync();
        restart();
    });
})();
