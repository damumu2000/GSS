(() => {
    document.addEventListener('DOMContentLoaded', () => {
        const tabRoot = document.querySelector('[data-site-edit-tabs]');
        if (!(tabRoot instanceof HTMLElement)) {
            return;
        }

        const triggers = Array.from(tabRoot.querySelectorAll('[data-site-edit-tab-trigger]'));
        const panels = Array.from(document.querySelectorAll('[data-site-edit-tab-panel]'));
        if (triggers.length === 0 || panels.length === 0) {
            return;
        }

        const setActive = (tab) => {
            triggers.forEach((trigger) => {
                const active = trigger.getAttribute('data-site-edit-tab-trigger') === tab;
                trigger.classList.toggle('is-active', active);
                trigger.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            panels.forEach((panel) => {
                const active = panel.getAttribute('data-site-edit-tab-panel') === tab;
                panel.classList.toggle('is-hidden', !active);
            });

            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.replaceState({}, '', url.toString());
        };

        triggers.forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const tab = trigger.getAttribute('data-site-edit-tab-trigger') || 'basic';
                setActive(tab);
            });
        });

        setActive(tabRoot.getAttribute('data-active-tab') || 'basic');
    });
})();
