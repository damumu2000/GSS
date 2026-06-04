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

    if (!window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
        document.querySelectorAll('.promo-floating--wander').forEach((floating) => {
            const start = (rect) => {
                if (floating.dataset.promoWanderStarted === '1' || !floating.isConnected) {
                    return;
                }

                floating.dataset.promoWanderStarted = '1';

                floating.style.left = `${Math.min(Math.max(rect.left, 0), Math.max(window.innerWidth - rect.width, 0))}px`;
                floating.style.top = `${Math.min(Math.max(rect.top, 0), Math.max(window.innerHeight - rect.height, 0))}px`;
                floating.style.right = 'auto';
                floating.style.bottom = 'auto';
                floating.style.transform = 'none';

                let x = 0;
                let y = 0;
                let dx = 0.78;
                let dy = 0.58;
                let paused = false;

                floating.addEventListener('mouseenter', () => {
                    paused = true;
                });

                floating.addEventListener('mouseleave', () => {
                    paused = false;
                });

                const move = () => {
                    if (!floating.isConnected) {
                        return;
                    }

                    if (paused) {
                        window.requestAnimationFrame(move);
                        return;
                    }

                    const edgePadding = 8;
                    const maxX = Math.max(window.innerWidth - floating.offsetWidth - edgePadding, edgePadding);
                    const maxY = Math.max(window.innerHeight - floating.offsetHeight - edgePadding, edgePadding);
                    const baseLeft = Number.parseFloat(floating.style.left) || 0;
                    const baseTop = Number.parseFloat(floating.style.top) || 0;
                    const minMoveX = edgePadding - baseLeft;
                    const minMoveY = edgePadding - baseTop;
                    const maxMoveX = maxX - baseLeft;
                    const maxMoveY = maxY - baseTop;

                    x += dx;
                    y += dy;

                    if (x <= minMoveX || x >= maxMoveX) {
                        dx *= -1;
                        x = Math.min(Math.max(x, minMoveX), maxMoveX);
                    }

                    if (y <= minMoveY || y >= maxMoveY) {
                        dy *= -1;
                        y = Math.min(Math.max(y, minMoveY), maxMoveY);
                    }

                    floating.style.transform = `translate3d(${x}px, ${y}px, 0)`;

                    window.requestAnimationFrame(move);
                };

                window.requestAnimationFrame(move);
            };

            const startWhenReady = (attempt = 0) => {
                if (floating.dataset.promoWanderStarted === '1' || !floating.isConnected) {
                    return;
                }

                const rect = floating.getBoundingClientRect();

                if (rect.width > 0 && rect.height > 0) {
                    start(rect);
                    return;
                }

                if (attempt >= 80) {
                    return;
                }

                window.setTimeout(() => {
                    window.requestAnimationFrame(() => startWhenReady(attempt + 1));
                }, 100);
            };

            const image = floating.querySelector('img');

            if (image && !image.complete) {
                image.addEventListener('load', () => {
                    window.requestAnimationFrame(() => startWhenReady());
                }, { once: true });
                image.addEventListener('error', () => {
                    window.requestAnimationFrame(() => startWhenReady());
                }, { once: true });
            }

            window.requestAnimationFrame(() => startWhenReady());
        });
    }

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
