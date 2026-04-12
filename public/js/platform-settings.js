(() => {
    const allowedTabs = ['basic', 'upload', 'security', 'access'];
    const currentTabInput = document.getElementById('current_tab');
    const form = document.getElementById('system-settings-form');

    const syncSwitch = () => {
        const input = document.getElementById('admin_enabled');
        const label = document.getElementById('admin_enabled_label');
        const resizeInput = document.getElementById('attachment_image_auto_resize');
        const resizeLabel = document.getElementById('attachment_image_auto_resize_label');
        const compressInput = document.getElementById('attachment_image_auto_compress');
        const compressLabel = document.getElementById('attachment_image_auto_compress_label');
        const securitySwitches = [
            'security_site_protection_enabled',
            'security_block_bad_path_enabled',
            'security_block_sql_injection_enabled',
            'security_block_xss_enabled',
            'security_block_path_traversal_enabled',
            'security_block_bad_upload_enabled',
            'security_rate_limit_enabled',
        ];

        if (input && label) {
            label.textContent = input.checked ? '已开启' : '未开启';
        }

        if (resizeInput && resizeLabel) {
            resizeLabel.textContent = resizeInput.checked ? '已开启' : '未开启';
        }

        if (compressInput && compressLabel) {
            compressLabel.textContent = compressInput.checked ? '已开启' : '未开启';
        }

        securitySwitches.forEach((name) => {
            const switchInput = document.getElementById(name);
            const switchLabel = document.getElementById(`${name}_label`);

            if (switchInput && switchLabel) {
                switchLabel.textContent = switchInput.checked ? '已开启' : '未开启';
            }
        });
    };

    const activateTab = (tab, syncUrl = true) => {
        const normalizedTab = allowedTabs.includes(tab) ? tab : 'basic';

        document.querySelectorAll('[data-settings-tab-trigger]').forEach((button) => {
            const isActive = button.getAttribute('data-settings-tab-trigger') === normalizedTab;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        document.querySelectorAll('[data-settings-tab-panel]').forEach((panel) => {
            panel.classList.toggle('is-active', panel.getAttribute('data-settings-tab-panel') === normalizedTab);
        });

        if (currentTabInput) {
            currentTabInput.value = normalizedTab;
        }

        if (syncUrl) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', normalizedTab);
            window.history.replaceState({}, '', url.toString());
        }
    };

    const renderAssetPreview = (slot) => {
        const fileInput = document.getElementById(`admin_${slot}_file`);
        const preview = document.querySelector(`[data-system-preview="${slot}"]`);
        const note = document.querySelector(`[data-system-note="${slot}"]`);
        const clearInput = document.getElementById(`admin_${slot}_clear`);

        if (!preview || !note || !clearInput) {
            return;
        }

        let image = preview.querySelector(`[data-system-preview-image="${slot}"]`);
        let empty = preview.querySelector(`[data-system-preview-empty="${slot}"]`);
        const initialImage = image?.getAttribute('src') || '';

        if (clearInput.value === '1' || initialImage === '') {
            if (image) {
                image.remove();
            }

            if (!empty) {
                empty = document.createElement('div');
                empty.className = 'settings-media-empty';
                empty.setAttribute('data-system-preview-empty', slot);
                empty.textContent = slot === 'logo' ? '当前还没有设置后台 Logo' : '当前还没有设置后台 ICO';
                preview.appendChild(empty);
            }

            note.textContent = slot === 'logo'
                ? '推荐显示高度控制在 36px 内。'
                : '建议使用清晰的小图标素材。';
            return;
        }

        if (empty) {
            empty.remove();
        }

        if (!image) {
            image = document.createElement('img');
            image.setAttribute('data-system-preview-image', slot);
            image.alt = slot === 'logo' ? '后台 Logo 预览' : '后台 ICO 预览';
            preview.appendChild(image);
        }

        image.src = initialImage;
        note.textContent = initialImage;
    };

    document.querySelectorAll('[data-settings-tab-trigger]').forEach((button) => {
        button.addEventListener('click', () => {
            activateTab(button.getAttribute('data-settings-tab-trigger') || 'basic');
        });
    });

    ['logo', 'favicon'].forEach((slot) => {
        const fileInput = document.getElementById(`admin_${slot}_file`);
        const clearInput = document.getElementById(`admin_${slot}_clear`);
        const clearTrigger = document.querySelector(`[data-system-clear-trigger="${slot}"]`);

        fileInput?.addEventListener('change', () => {
            const preview = document.querySelector(`[data-system-preview="${slot}"]`);
            const note = document.querySelector(`[data-system-note="${slot}"]`);

            if (!fileInput || !preview || !note) {
                return;
            }

            const file = fileInput.files?.[0];
            let image = preview.querySelector(`[data-system-preview-image="${slot}"]`);
            let empty = preview.querySelector(`[data-system-preview-empty="${slot}"]`);

            if (!file) {
                return;
            }

            if (empty) {
                empty.remove();
            }

            if (!image) {
                image = document.createElement('img');
                image.setAttribute('data-system-preview-image', slot);
                image.alt = slot === 'logo' ? '后台 Logo 预览' : '后台 ICO 预览';
                preview.appendChild(image);
            }

            image.src = URL.createObjectURL(file);
            note.textContent = file.name;
            clearInput.value = '0';
        });

        clearTrigger?.addEventListener('click', () => {
            if (!fileInput || !clearInput) {
                return;
            }

            fileInput.value = '';
            clearInput.value = '1';
            const existingImage = document.querySelector(`[data-system-preview="${slot}"] [data-system-preview-image="${slot}"]`);
            existingImage?.setAttribute('src', '');
            renderAssetPreview(slot);
        });

        renderAssetPreview(slot);
    });

    document.getElementById('admin_enabled')?.addEventListener('change', syncSwitch);
    document.getElementById('attachment_image_auto_resize')?.addEventListener('change', syncSwitch);
    document.getElementById('attachment_image_auto_compress')?.addEventListener('change', syncSwitch);
    [
        'security_site_protection_enabled',
        'security_block_bad_path_enabled',
        'security_block_sql_injection_enabled',
        'security_block_xss_enabled',
        'security_block_path_traversal_enabled',
        'security_block_bad_upload_enabled',
        'security_rate_limit_enabled',
    ].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', syncSwitch);
    });

    activateTab(form?.dataset.activeTab || 'basic', false);
    syncSwitch();
})();
