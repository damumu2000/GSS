(() => {
        const root = document.querySelector('[data-hero-carousel]');
        if (!root) {
            return;
        }

        const slides = Array.from(root.querySelectorAll('[data-hero-slide]'));
        const dots = Array.from(root.querySelectorAll('[data-hero-dot]'));
        let activeIndex = slides.findIndex((slide) => slide.classList.contains('is-active'));
        let timer = null;

        if (slides.length <= 1) {
            return;
        }

        if (activeIndex < 0) {
            activeIndex = 0;
        }

        const activate = (index) => {
            activeIndex = index;
            slides.forEach((slide, slideIndex) => {
                slide.classList.toggle('is-active', slideIndex === index);
            });
            dots.forEach((dot, dotIndex) => {
                dot.classList.toggle('is-active', dotIndex === index);
            });
        };

        const restart = () => {
            if (timer) {
                window.clearInterval(timer);
            }
            timer = window.setInterval(() => {
                activate((activeIndex + 1) % slides.length);
            }, 4800);
        };

        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                activate(index);
                restart();
            });
        });

        activate(activeIndex);
        restart();
    })();
