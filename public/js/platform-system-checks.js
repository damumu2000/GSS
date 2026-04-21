(() => {
    const shell = document.querySelector('[data-system-check-tabs]');

    if (!shell) {
        return;
    }

    const allowedTabs = ['cache', 'base'];
    const initialTab = shell.getAttribute('data-active-tab') || 'base';

    const activateTab = (tab, syncUrl = true) => {
        const targetTab = allowedTabs.includes(tab) ? tab : 'base';

        document.querySelectorAll('[data-system-check-tab-trigger]').forEach((button) => {
            const isActive = button.getAttribute('data-system-check-tab-trigger') === targetTab;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        document.querySelectorAll('[data-system-check-tab-panel]').forEach((panel) => {
            panel.classList.toggle('is-active', panel.getAttribute('data-system-check-tab-panel') === targetTab);
        });

        if (syncUrl) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', targetTab);
            window.history.replaceState({}, '', url.toString());
        }
    };

    document.querySelectorAll('[data-system-check-tab-trigger]').forEach((button) => {
        button.addEventListener('click', () => {
            activateTab(button.getAttribute('data-system-check-tab-trigger') || 'base');
        });
    });

    activateTab(initialTab, false);
})();
