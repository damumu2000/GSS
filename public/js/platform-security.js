(() => {
    const switches = [
        'security_site_protection_enabled',
        'security_block_bad_path_enabled',
        'security_block_sql_injection_enabled',
        'security_block_xss_enabled',
        'security_block_path_traversal_enabled',
        'security_block_bad_upload_enabled',
        'security_block_bad_client_enabled',
        'security_block_bad_method_enabled',
        'security_block_bad_payload_enabled',
        'security_rate_limit_enabled',
        'security_scan_probe_enabled',
        'security_malicious_auto_block_enabled',
    ];

    const syncSwitches = () => {
        switches.forEach((name) => {
            const input = document.getElementById(name);
            const label = document.getElementById(`${name}_label`);

            if (input && label) {
                label.textContent = input.checked ? '已开启' : '未开启';
            }
        });
    };

    document.addEventListener('change', (event) => {
        if (event.target instanceof HTMLInputElement && switches.includes(event.target.id)) {
            syncSwitches();
        }
    });

    const tabRoots = document.querySelectorAll('[data-platform-security-tabs]');

    tabRoots.forEach((root) => {
        const triggers = Array.from(root.querySelectorAll('[data-platform-security-tab-trigger]'));
        const panels = Array.from(root.querySelectorAll('[data-platform-security-tab-panel]'));

        const activateTab = (name) => {
            triggers.forEach((trigger) => {
                const active = trigger.dataset.platformSecurityTabTrigger === name;
                trigger.classList.toggle('is-active', active);
                trigger.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            panels.forEach((panel) => {
                const active = panel.dataset.platformSecurityTabPanel === name;
                panel.classList.toggle('is-active', active);
                panel.hidden = !active;
            });
        };

        triggers.forEach((trigger) => {
            trigger.addEventListener('click', () => {
                activateTab(trigger.dataset.platformSecurityTabTrigger);
            });
        });
    });

    syncSwitches();
})();
