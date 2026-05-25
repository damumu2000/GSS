(function () {
    var initSecurityModals = function () {
        var body = document.body;
        var eventModal = document.getElementById('security-events-modal');
        var ipModal = document.getElementById('security-ips-modal');
        var activeModal = null;
        var params = new URLSearchParams(window.location.search);
        var parser = new DOMParser();

        var getModalKey = function (modal) {
            if (!(modal instanceof HTMLElement)) {
                return '';
            }

            return modal.id === 'security-ips-modal' ? 'ips' : 'events';
        };

        var stripModalQuery = function () {
            var nextUrl = new URL(window.location.href);
            ['security_modal', 'security_event_filter', 'security_event_page', 'security_ip_page'].forEach(function (key) {
                nextUrl.searchParams.delete(key);
            });

            var current = window.location.pathname + window.location.search + window.location.hash;
            var target = nextUrl.pathname + nextUrl.search + nextUrl.hash;

            if (current !== target) {
                window.history.replaceState({}, document.title, target);
            }
        };

        var getModalByKey = function (key) {
            return key === 'ips' ? ipModal : eventModal;
        };

        var updateModalContent = function (modalKey, html) {
            var currentModal = getModalByKey(modalKey);
            if (!(currentModal instanceof HTMLElement)) {
                return;
            }

            var documentNode = parser.parseFromString(html, 'text/html');
            var freshModal = documentNode.getElementById(currentModal.id);
            var currentInner = currentModal.querySelector('.security-modal-inner');
            var freshInner = freshModal ? freshModal.querySelector('.security-modal-inner') : null;

            if (!(currentInner instanceof HTMLElement) || !(freshInner instanceof HTMLElement)) {
                return;
            }

            currentInner.innerHTML = freshInner.innerHTML;

            var message = documentNode.body && documentNode.body.dataset
                ? documentNode.body.dataset.adminStatusMessage
                : '';
            var type = documentNode.body && documentNode.body.dataset
                ? documentNode.body.dataset.adminStatusType
                : '';

            if (message && typeof window.showMessage === 'function') {
                window.showMessage(message, type || 'success');
            }
        };

        var requestModal = function (modalKey, url, options) {
            return window.fetch(url, Object.assign({
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Requested-Modal': modalKey,
                },
            }, options || {})).then(function (response) {
                if (!response.ok) {
                    throw new Error('Request failed');
                }

                return response.text();
            }).then(function (html) {
                updateModalContent(modalKey, html);
                openModal(getModalByKey(modalKey));
                stripModalQuery();
            });
        };

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

        document.addEventListener('click', function (event) {
            var closeTrigger = event.target.closest('[data-security-modal-close]');
            if (closeTrigger instanceof HTMLElement) {
                closeModal(closeTrigger.closest('.security-modal'));
                return;
            }

            var trigger = event.target.closest('[data-security-modal-link], .security-modal-pagination a');
            if (!(trigger instanceof HTMLAnchorElement)) {
                return;
            }

            var modal = trigger.closest('.security-modal');
            if (!(modal instanceof HTMLElement)) {
                return;
            }

            event.preventDefault();

            requestModal(getModalKey(modal), trigger.href).catch(function () {
                window.location.href = trigger.href;
            });
        });

        document.addEventListener('submit', function (event) {
            var form = event.target.closest('form[data-security-modal-request]');
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            var modal = form.closest('.security-modal');
            if (!(modal instanceof HTMLElement)) {
                return;
            }

            event.preventDefault();

            requestModal(getModalKey(modal), form.action, {
                method: (form.getAttribute('method') || 'POST').toUpperCase(),
                body: new window.FormData(form),
            }).catch(function () {
                form.submit();
            });
        });

        if (params.get('security_modal') === 'events') {
            openModal(eventModal);
            stripModalQuery();
        } else if (params.get('security_modal') === 'ips') {
            openModal(ipModal);
            stripModalQuery();
        }
    };

    var boot = function () {
        initSecurityModals();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
        return;
    }

    boot();
}());
