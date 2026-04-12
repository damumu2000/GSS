(() => {
        const carousel = document.querySelector('[data-promo-carousel]');
        if (!carousel) {
            return;
        }

        const slides = Array.from(carousel.querySelectorAll('[data-promo-carousel-slide]'));
        const dots = Array.from(carousel.querySelectorAll('[data-promo-carousel-dot]'));
        const prevButton = carousel.querySelector('[data-promo-carousel-prev]');
        const nextButton = carousel.querySelector('[data-promo-carousel-next]');
        const progress = carousel.querySelector('[data-promo-carousel-progress]');
        let currentIndex = slides.findIndex((slide) => slide.classList.contains('is-active'));
        let timer = null;

        if (slides.length <= 1) {
            return;
        }

        if (currentIndex < 0) {
            currentIndex = 0;
        }

        const activate = (index) => {
            currentIndex = index;
            slides.forEach((slide, slideIndex) => {
                slide.classList.toggle('is-active', slideIndex === index);
            });
            dots.forEach((dot, dotIndex) => {
                dot.classList.toggle('is-active', dotIndex === index);
            });
            if (progress) {
                progress.style.width = `${((index + 1) / slides.length) * 100}%`;
            }
        };

        const start = () => {
            stop();
            timer = window.setInterval(() => {
                activate((currentIndex + 1) % slides.length);
            }, 4600);
        };

        const stop = () => {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
        };

        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                activate(index);
                start();
            });
        });

        prevButton?.addEventListener('click', () => {
            activate((currentIndex - 1 + slides.length) % slides.length);
            start();
        });

        nextButton?.addEventListener('click', () => {
            activate((currentIndex + 1) % slides.length);
            start();
        });

        carousel.addEventListener('mouseenter', stop);
        carousel.addEventListener('mouseleave', start);
        activate(currentIndex);
        start();
    })();
