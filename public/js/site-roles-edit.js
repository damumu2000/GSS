(() => {
    document.querySelector('.js-select-all')?.addEventListener('click', () => {
        document
            .querySelectorAll('input[name="permission_ids[]"]')
            .forEach((checkbox) => {
                checkbox.checked = true;
            });
    });

    document.querySelector('.js-select-none')?.addEventListener('click', () => {
        document
            .querySelectorAll('input[name="permission_ids[]"]')
            .forEach((checkbox) => {
                checkbox.checked = false;
            });
    });
})();
