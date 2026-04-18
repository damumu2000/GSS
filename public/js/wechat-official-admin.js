(() => {
    const forms = Array.from(document.querySelectorAll('[data-wechat-menu-form]'));
    if (forms.length === 0) {
        return;
    }

    const syncForm = (form) => {
        const levelField = form.querySelector('[data-wechat-menu-level]');
        const typeFields = Array.from(form.querySelectorAll('[data-wechat-menu-type]'));
        const parentWrap = form.querySelector('[data-wechat-menu-parent-wrap]');
        const level = levelField ? String(levelField.value || '1') : '1';
        const checkedTypeField = typeFields.find((field) => field.checked) || typeFields[0] || null;
        const type = checkedTypeField ? String(checkedTypeField.value || 'view') : 'view';

        if (parentWrap) {
            parentWrap.hidden = level !== '2';
        }

        form.querySelectorAll('[data-wechat-menu-value-wrap]').forEach((wrap) => {
            wrap.hidden = wrap.getAttribute('data-wechat-menu-value-wrap') !== type;
        });
    };

    forms.forEach((form) => {
        const levelField = form.querySelector('[data-wechat-menu-level]');
        const typeFields = Array.from(form.querySelectorAll('[data-wechat-menu-type]'));

        levelField?.addEventListener('change', () => syncForm(form));
        typeFields.forEach((field) => field.addEventListener('change', () => syncForm(form)));

        syncForm(form);
    });
})();
