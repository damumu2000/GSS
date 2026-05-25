(function () {
    var initSecurityModals = function () {
        var body = document.body;
        var eventModal = document.getElementById('security-events-modal');
        var ipModal = document.getElementById('security-ips-modal');
        var activeModal = null;

        var openModal = function (modal) {
            if (!(modal instanceof HTMLElement)) {
                return;
            }

            activeModal = modal;
            modal.hidden = false;
            window.requestAnimationFrame(function () {
                modal.classList.add('is-open');
                body.classList.add('has-modal-open');
            });
        };

        var closeModal = function (modal) {
            if (!(modal instanceof HTMLElement) || modal.hidden) {
                return;
            }

            modal.classList.remove('is-open');
            if (activeModal === modal) {
                activeModal = null;
            }
            window.setTimeout(function () {
                modal.hidden = true;
                if (!document.querySelector('.security-modal.is-open')) {
                    body.classList.remove('has-modal-open');
                }
            }, 220);
        };

        document.querySelectorAll('[data-security-modal-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                var target = button.getAttribute('data-security-modal-open');
                openModal(target === 'ips' ? ipModal : eventModal);
            });
        });

        document.querySelectorAll('[data-security-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                closeModal(button.closest('.security-modal'));
            });
        });

        document.querySelectorAll('[data-security-modal-shell]').forEach(function (shell) {
            shell.addEventListener('click', function (event) {
                if (event.target === event.currentTarget) {
                    closeModal(shell.closest('.security-modal'));
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && activeModal) {
                closeModal(activeModal);
            }
        });
    };

    var initSecurityEventFilters = function () {
        var filterRoot = document.querySelector('[data-security-event-filters]');
        if (!filterRoot || filterRoot.dataset.filtersBound === 'true') {
            return;
        }
        filterRoot.dataset.filtersBound = 'true';

        var buttons = Array.prototype.slice.call(filterRoot.querySelectorAll('[data-filter]'));
        var items = Array.prototype.slice.call(document.querySelectorAll('#security-events-modal .security-event[data-risk-level]'));
        var emptyState = document.querySelector('#security-events-modal [data-security-event-empty]');

        var applyFilter = function (filter) {
            var visibleCount = 0;

            items.forEach(function (item) {
                var match = filter === 'all' || item.dataset.riskLevel === filter;

                item.hidden = !match;
                if (match) {
                    visibleCount += 1;
                }
            });

            if (emptyState) {
                emptyState.hidden = visibleCount > 0;
            }

            buttons.forEach(function (button) {
                button.classList.toggle('is-active', button.dataset.filter === filter);
            });
        };

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                applyFilter(button.dataset.filter || 'all');
            });
        });

        applyFilter('all');
    };

    var boot = function () {
        initSecurityModals();
        initSecurityEventFilters();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
        return;
    }

    boot();
}());
