(function () {
    var initSecurityTypeHeightSync = function () {
        var sync = function () {
            var mediaQuery = window.matchMedia('(min-width: 1181px)');
            var leftPanel = document.querySelector('.security-grid > .security-panel:not(.security-panel--types)');
            var typePanel = document.querySelector('.security-panel--types');
            var typeList = typePanel ? typePanel.querySelector('.security-types') : null;

            if (!(leftPanel instanceof HTMLElement) || !(typePanel instanceof HTMLElement) || !(typeList instanceof HTMLElement)) {
                return;
            }

            if (!mediaQuery.matches) {
                typePanel.style.height = '';
                typeList.style.height = '';
                return;
            }

            var panelFixedHeight = 750;
            var panelRect = typePanel.getBoundingClientRect();
            var listRect = typeList.getBoundingClientRect();
            var panelStyles = window.getComputedStyle(typePanel);
            var bottomPadding = parseFloat(panelStyles.paddingBottom || '0') || 0;
            var targetHeight = Math.floor(panelFixedHeight - (listRect.top - panelRect.top) - bottomPadding);

            if (panelFixedHeight > 0) {
                typePanel.style.height = panelFixedHeight + 'px';
            } else {
                typePanel.style.height = '';
            }

            if (targetHeight > 160) {
                typeList.style.height = targetHeight + 'px';
            } else {
                typeList.style.height = '';
            }
        };

        var scheduleSync = function () {
            window.requestAnimationFrame(sync);
        };

        scheduleSync();
        window.addEventListener('load', scheduleSync, { once: true });
        window.addEventListener('resize', sync);

        if ('ResizeObserver' in window) {
            var leftPanel = document.querySelector('.security-grid > .security-panel:not(.security-panel--types)');
            var typePanel = document.querySelector('.security-panel--types');
            var observer = new window.ResizeObserver(scheduleSync);

            if (leftPanel instanceof HTMLElement) {
                observer.observe(leftPanel);
            }

            if (typePanel instanceof HTMLElement) {
                observer.observe(typePanel);
            }
        }
    };

    var initSecurityModals = function () {
        var body = document.body;
        var eventModal = document.getElementById('security-events-modal');
        var ipModal = document.getElementById('security-ips-modal');
        var activeModal = null;
        var params = new URLSearchParams(window.location.search);
        var parser = new DOMParser();
        var ipListSnapshot = '';
        var ipListScrollTop = 0;

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

            if (modalKey === 'ips') {
                ipListSnapshot = freshInner.innerHTML;
                ipListScrollTop = 0;
            }

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

        var escapeHtml = function (value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        var renderIpDetail = function (html) {
            var currentInner = ipModal instanceof HTMLElement
                ? ipModal.querySelector('.security-modal-inner')
                : null;

            if (!(currentInner instanceof HTMLElement)) {
                return false;
            }

            if (!ipListSnapshot) {
                ipListSnapshot = currentInner.innerHTML;
            }

            var scrollBox = ipModal.querySelector('.security-modal-scroll');
            ipListScrollTop = scrollBox instanceof HTMLElement ? scrollBox.scrollTop : 0;

            var documentNode = parser.parseFromString(html, 'text/html');
            var detailContent = documentNode.querySelector('[data-security-ip-detail-content]');

            if (!(detailContent instanceof HTMLElement)) {
                return false;
            }

            var ipTitle = detailContent.querySelector('.security-card-value--ip');
            var ipLabel = ipTitle instanceof HTMLElement ? ipTitle.textContent.trim() : 'IP 详情';

            currentInner.innerHTML = [
                '<div class="security-modal-topbar security-modal-topbar--detail">',
                    '<div class="security-modal-heading">',
                        '<div class="security-modal-heading-row">',
                            '<div>',
                                '<h3 class="security-modal-title" id="security-ips-modal-title">IP 详情</h3>',
                                '<div class="security-modal-subtitle">', escapeHtml(ipLabel), ' 的命中画像和最近拦截记录</div>',
                            '</div>',
                            '<div class="security-modal-title-actions">',
                                '<button class="security-modal-back" type="button" data-security-ip-detail-back>返回排行</button>',
                                '<button class="security-modal-close" type="button" data-security-modal-close aria-label="关闭 IP 详情">',
                                    '<svg viewBox="0 0 24 24" aria-hidden="true">',
                                        '<path d="M6 6l12 12"></path>',
                                        '<path d="M18 6 6 18"></path>',
                                    '</svg>',
                                '</button>',
                            '</div>',
                        '</div>',
                    '</div>',
                '</div>',
                '<div class="security-modal-frame security-modal-frame--detail">',
                    '<div class="security-modal-detail-body">',
                        detailContent.innerHTML,
                    '</div>',
                '</div>',
            ].join('');

            if (scrollBox instanceof HTMLElement) {
                scrollBox.scrollTop = 0;
            }

            openModal(ipModal);

            return true;
        };

        var restoreIpList = function () {
            var currentInner = ipModal instanceof HTMLElement
                ? ipModal.querySelector('.security-modal-inner')
                : null;
            var scrollBox = ipModal instanceof HTMLElement
                ? ipModal.querySelector('.security-modal-scroll')
                : null;

            if (!(currentInner instanceof HTMLElement) || !ipListSnapshot) {
                return;
            }

            currentInner.innerHTML = ipListSnapshot;

            if (scrollBox instanceof HTMLElement) {
                window.requestAnimationFrame(function () {
                    scrollBox.scrollTop = ipListScrollTop;
                });
            }
        };

        var isIpDetailMode = function () {
            return ipModal instanceof HTMLElement
                && ipModal.querySelector('[data-security-ip-detail-back]') instanceof HTMLElement;
        };

        var openModal = function (modal) {
            if (!(modal instanceof HTMLElement)) {
                return;
            }

            if (modal === ipModal && isIpDetailMode()) {
                restoreIpList();
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

            if (modal === ipModal && isIpDetailMode()) {
                restoreIpList();
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

            var backTrigger = event.target.closest('[data-security-ip-detail-back]');
            if (backTrigger instanceof HTMLElement) {
                restoreIpList();
                return;
            }

            var detailTrigger = event.target.closest('[data-security-ip-detail-link]');
            if (detailTrigger instanceof HTMLAnchorElement) {
                event.preventDefault();

                window.fetch(detailTrigger.href, {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Requested-Modal': 'ips-detail',
                    },
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }

                    return response.text();
                }).then(function (html) {
                    if (!renderIpDetail(html)) {
                        window.location.href = detailTrigger.href;
                    }
                }).catch(function () {
                    window.location.href = detailTrigger.href;
                });

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
        initSecurityTypeHeightSync();
        initSecurityModals();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
        return;
    }

    boot();
}());
