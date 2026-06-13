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
        var policyModal = document.getElementById('security-policies-modal');
        var activeModal = null;
        var params = new URLSearchParams(window.location.search);
        var parser = new DOMParser();
        var ipListSnapshot = '';
        var ipListScrollTop = 0;

        var getModalKey = function (modal) {
            if (!(modal instanceof HTMLElement)) {
                return '';
            }

            if (modal.id === 'security-ips-modal') {
                return 'ips';
            }

            if (modal.id === 'security-policies-modal') {
                return 'policies';
            }

            return 'events';
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
            if (key === 'ips') {
                return ipModal;
            }

            if (key === 'policies') {
                return policyModal;
            }

            return eventModal;
        };

        var syncPolicyState = function (documentNode, sourceModalKey) {
            var currentSummary = document.querySelector('[data-security-policy-summary]');
            var freshSummary = documentNode.querySelector('[data-security-policy-summary]');

            if (currentSummary instanceof HTMLElement && freshSummary instanceof HTMLElement) {
                currentSummary.innerHTML = freshSummary.innerHTML;
            }

            if (sourceModalKey === 'policies' || !(policyModal instanceof HTMLElement)) {
                return;
            }

            var freshPolicyModal = documentNode.getElementById('security-policies-modal');
            var currentPolicyInner = policyModal.querySelector('.security-modal-inner');
            var freshPolicyInner = freshPolicyModal ? freshPolicyModal.querySelector('.security-modal-inner') : null;

            if (currentPolicyInner instanceof HTMLElement && freshPolicyInner instanceof HTMLElement) {
                currentPolicyInner.innerHTML = freshPolicyInner.innerHTML;
            }
        };

        var updateModalContent = function (modalKey, html) {
            var currentModal = getModalByKey(modalKey);
            if (!(currentModal instanceof HTMLElement)) {
                return;
            }

            var documentNode = parser.parseFromString(html, 'text/html');
            syncPolicyState(documentNode, modalKey);

            var freshModal = documentNode.getElementById(currentModal.id);
            var currentInner = currentModal.querySelector('.security-modal-inner');
            var freshInner = freshModal ? freshModal.querySelector('.security-modal-inner') : null;

            if (!(currentInner instanceof HTMLElement) || !(freshInner instanceof HTMLElement)) {
                return;
            }

            if (modalKey === 'policies') {
                var currentFrame = currentInner.querySelector('.security-modal-frame');
                var freshFrame = freshInner.querySelector('.security-modal-frame');

                if (currentFrame instanceof HTMLElement && freshFrame instanceof HTMLElement) {
                    currentFrame.innerHTML = freshFrame.innerHTML;
                } else {
                    currentInner.innerHTML = freshInner.innerHTML;
                }
            } else {
                currentInner.innerHTML = freshInner.innerHTML;
            }

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

        var csrfToken = function () {
            var token = document.querySelector('meta[name="csrf-token"]');
            return token instanceof Element ? token.getAttribute('content') || '' : '';
        };

        var notify = function (message, type) {
            var text = String(message || '').trim();
            if (text === '') {
                return;
            }

            if (typeof window.showMessage === 'function') {
                window.showMessage(text, type || 'success');
                return;
            }

            window.alert(text);
        };

        var formActionUrl = function (form) {
            var action = form.getAttribute('action') || '';
            return new URL(action, window.location.href).toString();
        };

        var firstValidationMessage = function (payload) {
            if (!payload || typeof payload !== 'object') {
                return '';
            }

            if (payload.errors && typeof payload.errors === 'object') {
                var fields = Object.keys(payload.errors);
                for (var i = 0; i < fields.length; i++) {
                    var messages = payload.errors[fields[i]];
                    if (Array.isArray(messages) && messages.length > 0) {
                        return String(messages[0] || '');
                    }
                }
            }

            return typeof payload.message === 'string' ? payload.message : '';
        };

        var responseErrorMessage = function (response, text) {
            var contentType = response.headers.get('content-type') || '';

            if (contentType.indexOf('application/json') !== -1) {
                try {
                    var message = firstValidationMessage(JSON.parse(text));
                    if (message) {
                        return message;
                    }
                } catch (error) {
                    return '';
                }
            }

            if (text) {
                var documentNode = parser.parseFromString(text, 'text/html');
                var flashMessage = documentNode.body && documentNode.body.dataset
                    ? documentNode.body.dataset.adminStatusMessage
                    : '';

                if (flashMessage) {
                    return flashMessage;
                }
            }

            if (response.status === 419) {
                return '登录状态已过期，请刷新页面后重试。';
            }

            if (response.status === 403) {
                return '当前账号无权执行该操作。';
            }

            return '操作失败，请稍后重试。';
        };

        var requestModal = function (modalKey, url, options) {
            var modal = getModalByKey(modalKey);
            var wasOpen = modal instanceof HTMLElement && !modal.hidden && modal.classList.contains('is-open');

            return window.fetch(url, Object.assign({
                credentials: 'same-origin',
                headers: {
                    'Accept': 'text/html, application/xhtml+xml',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Requested-Modal': modalKey,
                },
            }, options || {})).then(function (response) {
                if (!response.ok) {
                    return response.text().then(function (text) {
                        throw new Error(responseErrorMessage(response, text));
                    });
                }

                return response.text();
            }).then(function (html) {
                updateModalContent(modalKey, html);
                if (!wasOpen) {
                    openModal(modal);
                }
                stripModalQuery();
            });
        };

        var modalRefreshUrl = function (modalKey, form) {
            var url = new URL(window.location.href);
            url.searchParams.set('security_modal', modalKey);

            if (modalKey === 'ips') {
                var page = form.querySelector('input[name="security_ip_page"]');
                var pageValue = page instanceof HTMLInputElement ? page.value : '';
                if (pageValue) {
                    url.searchParams.set('security_ip_page', pageValue);
                }
            }

            return url.toString();
        };

        var requestIpPolicy = function (modalKey, form) {
            return window.fetch(formActionUrl(form), {
                method: (form.getAttribute('method') || 'POST').toUpperCase(),
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Requested-Modal': modalKey,
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: new window.FormData(form),
            }).then(function (response) {
                return response.text().then(function (text) {
                    var payload = {};

                    if (text) {
                        try {
                            payload = JSON.parse(text);
                        } catch (error) {
                            payload = {};
                        }
                    }

                    if (!response.ok) {
                        throw new Error(firstValidationMessage(payload) || responseErrorMessage(response, text));
                    }

                    if (payload.message) {
                        notify(payload.message, 'success');
                    }

                    return requestModal(modalKey, modalRefreshUrl(modalKey, form));
                });
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
                                '<button class="security-modal-back" type="button" data-security-ip-detail-back>返回</button>',
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

            if (!modal.hidden && modal.classList.contains('is-open')) {
                activeModal = modal;
                body.classList.add('has-modal-open');
                return;
            }

            if (modal === ipModal && modal.hidden && isIpDetailMode()) {
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
                openModal(getModalByKey(target));
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
            var target = event.target instanceof Element ? event.target : null;
            var form = target ? target.closest('form[data-security-modal-request]') : null;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            var modal = form.closest('.security-modal');
            if (!(modal instanceof HTMLElement)) {
                return;
            }

            if (typeof form.onsubmit === 'function' && form.onsubmit.call(form, event) === false) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            if (form.dataset.securitySubmitting === '1') {
                return;
            }

            form.dataset.securitySubmitting = '1';
            var submitter = event.submitter instanceof HTMLButtonElement ? event.submitter : null;
            if (submitter) {
                submitter.disabled = true;
            }

            Promise.resolve().then(function () {
                var modalKey = getModalKey(modal);
                var actionUrl = formActionUrl(form);

                if (actionUrl.indexOf('/security/ip-policy') !== -1) {
                    return requestIpPolicy(modalKey, form);
                }

                return requestModal(modalKey, actionUrl, {
                    method: (form.getAttribute('method') || 'POST').toUpperCase(),
                    body: new window.FormData(form),
                });
            }).catch(function (error) {
                notify(error.message || '操作失败，请稍后重试。', 'error');
            }).finally(function () {
                delete form.dataset.securitySubmitting;
                if (submitter) {
                    submitter.disabled = false;
                }
            });
        }, true);

        if (params.get('security_modal') === 'events') {
            openModal(eventModal);
            stripModalQuery();
        } else if (params.get('security_modal') === 'ips') {
            openModal(ipModal);
            stripModalQuery();
        } else if (params.get('security_modal') === 'policies') {
            openModal(policyModal);
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
