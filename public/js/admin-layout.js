(() => {
    const STORAGE_KEY = 'school-cms-admin-theme';
    const root = document.documentElement;
    const body = document.body;
    const themes = [
        'geek-blue',
        'mint-fresh',
        'sun-amber',
        'aurora-purple',
        'coral-rose',
        'ice-cyan',
        'candy-magenta',
        'sunset-orange',
        'lime-glow',
    ];

    const getCookie = (name) => {
        if (!document.cookie) {
            return '';
        }

        const match = document.cookie.split(';').map((item) => item.trim())
            .find((item) => item.startsWith(`${name}=`));
        return match ? decodeURIComponent(match.split('=').slice(1).join('=')) : '';
    };

    const setCookie = (name, value, maxAgeDays = 365) => {
        const maxAge = maxAgeDays * 24 * 60 * 60;
        document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=${maxAge}; samesite=lax`;
    };

    const applyTheme = (themeKey) => {
        const normalizedTheme = themes.includes(themeKey) ? themeKey : 'mint-fresh';
        root.classList.remove(...themes.map((item) => `admin-theme--${item}`));
        root.classList.add(`admin-theme--${normalizedTheme}`);

        document.querySelectorAll('[data-theme-choice]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.themeChoice === normalizedTheme);
        });

        window.localStorage.setItem(STORAGE_KEY, normalizedTheme);
        setCookie('admin_theme', normalizedTheme);
    };

    const currentTheme = window.localStorage.getItem(STORAGE_KEY) || getCookie('admin_theme') || 'mint-fresh';
    applyTheme(currentTheme);

    (() => {
        const SIDEBAR_SCROLL_KEY = 'school-cms-admin-sidebar-scroll';
        const sidebar = document.querySelector('[data-admin-sidebar]');
        const scrollContainer = sidebar?.querySelector('[data-admin-sidebar-scroll]');
        const fadeTop = sidebar?.querySelector('[data-sidebar-fade-top]');
        const fadeBottom = sidebar?.querySelector('[data-sidebar-fade-bottom]');
        const scrollIndicator = sidebar?.querySelector('.sidebar-scroll-indicator');
        const scrollThumb = sidebar?.querySelector('[data-sidebar-scroll-thumb]');
        const activeLink = scrollContainer?.querySelector('.menu-link.active');

        if (!scrollContainer) {
            return;
        }

        let scrollSaveFrame = null;
        let scrollActiveTimer = null;
        let dragState = null;

        const updateSidebarFades = () => {
            const maxScrollTop = Math.max(0, scrollContainer.scrollHeight - scrollContainer.clientHeight);

            fadeTop?.classList.toggle('is-visible', scrollContainer.scrollTop > 6);
            fadeBottom?.classList.toggle('is-visible', maxScrollTop - scrollContainer.scrollTop > 6);
        };

        const updateScrollThumb = () => {
            if (!scrollThumb) {
                return;
            }

            const containerHeight = scrollContainer.clientHeight;
            const scrollHeight = scrollContainer.scrollHeight;
            const maxScrollTop = Math.max(0, scrollHeight - containerHeight);

            if (scrollHeight <= containerHeight + 1) {
                scrollThumb.style.opacity = '0';
                return;
            }

            const trackHeight = Math.max(0, containerHeight - 16);
            const thumbHeight = Math.max(36, Math.round((containerHeight / scrollHeight) * trackHeight));
            const travelRange = Math.max(0, trackHeight - thumbHeight);
            const thumbTop = maxScrollTop === 0 ? 0 : Math.round((scrollContainer.scrollTop / maxScrollTop) * travelRange);

            scrollThumb.style.opacity = '1';
            scrollThumb.style.height = `${thumbHeight}px`;
            scrollThumb.style.transform = `translateY(${thumbTop}px)`;
        };

        const scrollToThumbOffset = (thumbOffset) => {
            const containerHeight = scrollContainer.clientHeight;
            const scrollHeight = scrollContainer.scrollHeight;
            const maxScrollTop = Math.max(0, scrollHeight - containerHeight);

            if (maxScrollTop <= 0) {
                scrollContainer.scrollTop = 0;
                return;
            }

            const trackHeight = Math.max(0, containerHeight - 16);
            const thumbHeight = Math.max(36, Math.round((containerHeight / scrollHeight) * trackHeight));
            const travelRange = Math.max(1, trackHeight - thumbHeight);
            const normalizedOffset = Math.min(Math.max(0, thumbOffset), travelRange);
            const nextScrollTop = (normalizedOffset / travelRange) * maxScrollTop;

            scrollContainer.scrollTop = nextScrollTop;
        };

        const saveScrollPosition = () => {
            window.sessionStorage.setItem(SIDEBAR_SCROLL_KEY, String(scrollContainer.scrollTop));
        };

        const queueSaveScrollPosition = () => {
            if (scrollSaveFrame !== null) {
                return;
            }

            scrollSaveFrame = window.requestAnimationFrame(() => {
                scrollSaveFrame = null;
                saveScrollPosition();
            });
        };

        const restoreScrollPosition = () => {
            const savedValue = window.sessionStorage.getItem(SIDEBAR_SCROLL_KEY);

            if (savedValue === null || savedValue === '') {
                return false;
            }

            const savedTop = Number(savedValue);
            if (!Number.isFinite(savedTop) || savedTop < 0) {
                return false;
            }

            scrollContainer.scrollTop = savedTop;
            updateSidebarFades();
            return true;
        };

        const revealActiveLink = () => {
            if (!activeLink) {
                updateSidebarFades();
                updateScrollThumb();
                return;
            }

            const containerHeight = scrollContainer.clientHeight;
            const linkTop = activeLink.offsetTop;
            const linkBottom = linkTop + activeLink.offsetHeight;
            const currentTop = scrollContainer.scrollTop;
            const currentBottom = currentTop + containerHeight;
            const targetTop = Math.max(0, linkTop - Math.max(24, containerHeight * 0.28));

            if (linkTop < currentTop || linkBottom > currentBottom) {
                scrollContainer.scrollTo({
                    top: targetTop,
                    behavior: 'auto',
                });
            }

            updateSidebarFades();
            updateScrollThumb();
        };

        if (!restoreScrollPosition()) {
            revealActiveLink();
        }

        scrollContainer.addEventListener('scroll', () => {
            updateSidebarFades();
            updateScrollThumb();
            queueSaveScrollPosition();
            sidebar?.classList.add('is-scroll-active');
            if (scrollActiveTimer !== null) {
                window.clearTimeout(scrollActiveTimer);
            }
            scrollActiveTimer = window.setTimeout(() => {
                sidebar?.classList.remove('is-scroll-active');
            }, 420);
        }, { passive: true });
        window.addEventListener('resize', () => {
            updateSidebarFades();
            updateScrollThumb();
        });
        window.addEventListener('pagehide', saveScrollPosition);
        window.addEventListener('beforeunload', saveScrollPosition);
        window.addEventListener('pageshow', () => {
            if (!restoreScrollPosition()) {
                revealActiveLink();
                return;
            }

            updateSidebarFades();
            updateScrollThumb();
        });

        scrollThumb?.addEventListener('pointerdown', (event) => {
            event.preventDefault();

            const thumbHeight = scrollThumb.offsetHeight || 36;
            dragState = {
                pointerId: event.pointerId,
                offsetY: event.clientY - scrollThumb.getBoundingClientRect().top,
                thumbHeight,
            };

            scrollThumb.setPointerCapture?.(event.pointerId);
            sidebar?.classList.add('is-scroll-active');
        });

        scrollIndicator?.addEventListener('pointerdown', (event) => {
            if (!scrollThumb || event.target === scrollThumb) {
                return;
            }

            event.preventDefault();

            const indicatorRect = scrollIndicator.getBoundingClientRect();
            const thumbHeight = scrollThumb.offsetHeight || 36;
            const thumbOffset = event.clientY - indicatorRect.top - (thumbHeight / 2);

            scrollToThumbOffset(thumbOffset);
            updateSidebarFades();
            updateScrollThumb();
            queueSaveScrollPosition();
            sidebar?.classList.add('is-scroll-active');
        });

        window.addEventListener('pointermove', (event) => {
            if (!dragState || event.pointerId !== dragState.pointerId || !scrollIndicator) {
                return;
            }

            event.preventDefault();

            const indicatorRect = scrollIndicator.getBoundingClientRect();
            const thumbOffset = event.clientY - indicatorRect.top - dragState.offsetY;

            scrollToThumbOffset(thumbOffset);
            updateSidebarFades();
            updateScrollThumb();
            queueSaveScrollPosition();
        });

        window.addEventListener('pointerup', (event) => {
            if (!dragState || event.pointerId !== dragState.pointerId) {
                return;
            }

            scrollThumb?.releasePointerCapture?.(event.pointerId);
            dragState = null;
        });

        window.addEventListener('pointercancel', (event) => {
            if (!dragState || event.pointerId !== dragState.pointerId) {
                return;
            }

            scrollThumb?.releasePointerCapture?.(event.pointerId);
            dragState = null;
        });

        updateSidebarFades();
        updateScrollThumb();
    })();

    function normalizeMessageText(message) {
        if (typeof message !== 'string') {
            return '';
        }

        let text = message.trim().replace(/\s+/g, ' ');
        if (text === '') {
            return '';
        }

        text = text
            .replace(/[。；、]+(?=\s*[，,])/g, '')
            .replace(/\s*[，,]\s*/g, '，')
            .replace(/[，。；、]+$/g, '')
            .trim();

        if (text === '') {
            return '';
        }

        return /[。！？]$/.test(text) ? text : `${text}。`;
    }

    const toastConfig = window.CMS_TOAST_CONFIG || {};
    const toastVisibleDuration = Number.isFinite(toastConfig.visibleDuration) ? toastConfig.visibleDuration : 5000;
    const toastExitDuration = Number.isFinite(toastConfig.exitDuration) ? toastConfig.exitDuration : 240;

    function showMessage(message, type = 'success') {
        const normalizedMessage = normalizeMessageText(message);
        if (!normalizedMessage) {
            return;
        }

        document.querySelectorAll('.toast').forEach((item) => item.remove());

        const toast = document.createElement('div');
        const normalizedType = type === 'error' ? 'error' : 'success';
        toast.className = `toast${normalizedType === 'error' ? ' is-error' : ''}`;
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.innerHTML = `
            <span class="toast-icon">
                ${normalizedType === 'error'
                    ? '<svg viewBox="0 0 24 24"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>'
                    : '<svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>'}
            </span>
            <span class="toast-text"></span>
        `;
        toast.querySelector('.toast-text').textContent = normalizedMessage;
        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.add('is-visible');
        });

        window.setTimeout(() => {
            toast.classList.remove('is-visible');
            window.setTimeout(() => {
                toast.remove();
            }, toastExitDuration);
        }, toastVisibleDuration);
    }

    window.formatAdminMessageText = normalizeMessageText;

    function inferMessageType(message) {
        if (typeof message !== 'string') {
            return 'success';
        }

        return /(失败|错误|不能|无权|禁止|驳回|不支持|请输入|请先|不能为空|未填写|必填|缺少)/.test(message) ? 'error' : 'success';
    }

    const switcher = document.querySelector('[data-theme-switcher]');
    if (switcher) {
        const trigger = switcher.querySelector('[data-theme-trigger]');
        trigger?.addEventListener('click', (event) => {
            event.stopPropagation();
            switcher.classList.toggle('is-open');
        });

        switcher.querySelectorAll('[data-theme-choice]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                applyTheme(button.dataset.themeChoice || 'mint-fresh');
            });
        });
    }

    const siteContextSwitcher = document.querySelector('[data-site-context-switcher]');
    const siteContextTrigger = siteContextSwitcher?.querySelector('[data-site-context-trigger]');
    const siteContextSearch = siteContextSwitcher?.querySelector('[data-site-context-search]');
    const siteContextInput = siteContextSwitcher?.querySelector('[data-site-context-input]');
    const siteContextForm = siteContextSwitcher?.querySelector('.site-context-switcher-form');
    const siteContextList = siteContextSwitcher?.querySelector('[data-site-context-list]');
    const siteContextOptions = Array.from(siteContextSwitcher?.querySelectorAll('[data-site-context-option]') || []);
    const siteContextEmpty = siteContextSwitcher?.querySelector('[data-site-context-empty]');

    const setSiteContextOpen = (isOpen) => {
        if (!siteContextSwitcher || !siteContextTrigger) {
            return;
        }

        siteContextSwitcher.classList.toggle('is-open', isOpen);
        siteContextTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        if (isOpen) {
            window.setTimeout(() => {
                siteContextSearch?.focus();
                siteContextSearch?.select();
            }, 10);
        }
    };

    const filterSiteContextOptions = () => {
        if (!siteContextSearch) {
            return;
        }

        const keyword = siteContextSearch.value.trim().toLowerCase();
        const matches = [];
        let visibleCount = 0;

        siteContextOptions.forEach((option, index) => {
            const name = (option.dataset.siteName || '').toLowerCase();
            const siteKey = (option.dataset.siteKey || '').toLowerCase();
            const matched = keyword === '' || name.includes(keyword) || siteKey.includes(keyword);

            option.hidden = !matched;

            if (matched) {
                visibleCount += 1;
                const startsWithName = keyword !== '' && name.startsWith(keyword);
                const startsWithKey = keyword !== '' && siteKey.startsWith(keyword);
                matches.push({
                    option,
                    index,
                    weight: startsWithName ? 0 : (startsWithKey ? 1 : 2),
                });
            }
        });

        if (siteContextList) {
            matches
                .sort((left, right) => (left.weight - right.weight) || (left.index - right.index))
                .forEach(({ option }) => {
                    siteContextList.appendChild(option);
                });
        }

        if (siteContextEmpty) {
            siteContextEmpty.hidden = visibleCount !== 0;
        }
    };

    siteContextTrigger?.addEventListener('click', (event) => {
        event.stopPropagation();
        const willOpen = !siteContextSwitcher?.classList.contains('is-open');
        setSiteContextOpen(willOpen);
        if (willOpen) {
            filterSiteContextOptions();
        }
    });

    siteContextSearch?.addEventListener('input', filterSiteContextOptions);

    siteContextOptions.forEach((option) => {
        option.addEventListener('click', () => {
            if (!siteContextInput || !siteContextForm) {
                return;
            }

            siteContextInput.value = option.dataset.siteId || '';
            siteContextForm.submit();
        });
    });

    const userMenu = document.querySelector('[data-user-menu]');
    const userMenuTrigger = userMenu?.querySelector('[data-user-menu-trigger]');
    const setUserMenuOpen = (isOpen) => {
        if (!userMenu || !userMenuTrigger) {
            return;
        }

        userMenu.classList.toggle('is-open', isOpen);
        userMenuTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    userMenuTrigger?.addEventListener('click', (event) => {
        event.stopPropagation();
        setUserMenuOpen(!userMenu?.classList.contains('is-open'));
    });

    document.addEventListener('click', (event) => {
        if (switcher && !switcher.contains(event.target)) {
            switcher.classList.remove('is-open');
        }

        if (siteContextSwitcher && !siteContextSwitcher.contains(event.target)) {
            setSiteContextOpen(false);
        }

        if (userMenu && !userMenu.contains(event.target)) {
            setUserMenuOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            switcher?.classList.remove('is-open');
            setSiteContextOpen(false);
            setUserMenuOpen(false);
        }
    });

    (() => {
        let activeTarget = null;
        let floatingTooltip = null;

        const isSvgTooltipTarget = (target) => target instanceof SVGElement;

        const removeFloatingTooltip = () => {
            floatingTooltip?.remove();
            floatingTooltip = null;
        };

        const shouldPlaceTooltipBelow = (target, tooltip) => {
            if (!target || !tooltip) {
                return false;
            }

            const rect = target.getBoundingClientRect();
            const tooltipHeight = tooltip.offsetHeight || 0;

            return rect.top < (tooltipHeight + 18);
        };

        const positionFloatingTooltip = (target, tooltip) => {
            if (!target || !tooltip) {
                return;
            }

            const rect = target.getBoundingClientRect();
            const placeBelow = shouldPlaceTooltipBelow(target, tooltip);

            tooltip.classList.toggle('is-below', placeBelow);
            tooltip.style.left = `${rect.left + (rect.width / 2)}px`;
            tooltip.style.top = placeBelow
                ? `${rect.bottom + 10}px`
                : `${rect.top - 10}px`;
        };

        const hideTooltip = () => {
            removeFloatingTooltip();
            const tooltip = activeTarget?.querySelector(':scope > .global-tooltip');
            tooltip?.remove();
            activeTarget?.classList.remove('has-global-tooltip');
            activeTarget = null;
        };

        const showTooltip = (target) => {
            if (!target) {
                return;
            }

            const label = target.dataset.tooltip;
            if (!label) {
                hideTooltip();
                return;
            }

            if (isSvgTooltipTarget(target)) {
                removeFloatingTooltip();
                const tooltip = document.createElement('span');
                tooltip.className = 'global-tooltip is-floating';
                tooltip.textContent = label;
                document.body.appendChild(tooltip);
                positionFloatingTooltip(target, tooltip);
                requestAnimationFrame(() => {
                    tooltip.classList.add('is-visible');
                });
                floatingTooltip = tooltip;
                return;
            }

            const previous = target.querySelector(':scope > .global-tooltip');
            previous?.remove();

            const tooltip = document.createElement('span');
            tooltip.className = 'global-tooltip';
            tooltip.textContent = label;
            target.classList.add('has-global-tooltip');
            target.appendChild(tooltip);

            tooltip.classList.toggle('is-below', shouldPlaceTooltipBelow(target, tooltip));

            requestAnimationFrame(() => {
                tooltip.classList.add('is-visible');
            });
        };

        document.addEventListener('mouseover', (event) => {
            const target = event.target.closest('[data-tooltip]');
            if (!target) {
                hideTooltip();
                return;
            }

            activeTarget = target;
            showTooltip(target);
        });

        document.addEventListener('mouseout', (event) => {
            if (!activeTarget) {
                return;
            }

            const relatedTarget = event.relatedTarget;
            if (relatedTarget && activeTarget.contains(relatedTarget)) {
                return;
            }

            if (event.target.closest('[data-tooltip]') === activeTarget) {
                hideTooltip();
            }
        });

        document.addEventListener('focusin', (event) => {
            const target = event.target.closest('[data-tooltip]');
            if (!target) {
                return;
            }

            activeTarget = target;
            showTooltip(target);
        });

        document.addEventListener('focusout', (event) => {
            if (event.target.closest('[data-tooltip]')) {
                hideTooltip();
            }
        });

        window.addEventListener('scroll', () => {
            if (!activeTarget || !floatingTooltip) {
                return;
            }

            positionFloatingTooltip(activeTarget, floatingTooltip);
        }, true);

        window.addEventListener('resize', () => {
            if (!activeTarget || !floatingTooltip) {
                return;
            }

            positionFloatingTooltip(activeTarget, floatingTooltip);
        });
    })();

    (() => {
        document.querySelectorAll('[data-menu-trigger]').forEach((trigger) => {
            const menu = trigger.closest('.action-menu');
            if (!menu) {
                return;
            }

            trigger.addEventListener('click', (event) => {
                event.stopPropagation();

                document.querySelectorAll('.action-menu.is-open').forEach((item) => {
                    if (item !== menu) {
                        item.classList.remove('is-open');
                    }
                });

                menu.classList.toggle('is-open');
            });
        });

        document.addEventListener('click', (event) => {
            document.querySelectorAll('.action-menu.is-open').forEach((menu) => {
                if (!menu.contains(event.target)) {
                    menu.classList.remove('is-open');
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                document.querySelectorAll('.action-menu.is-open').forEach((menu) => {
                    menu.classList.remove('is-open');
                });
            }
        });
    })();

    (() => {
        const modal = document.querySelector('.js-confirm-modal');
        if (!modal) {
            return;
        }

        const titleElement = modal.querySelector('.confirm-modal-title');
        const textElement = modal.querySelector('.js-confirm-text');
        const acceptButton = modal.querySelector('.js-confirm-accept');
        const cancelButtons = modal.querySelectorAll('.js-confirm-cancel');
        let onConfirm = null;

        const closeModal = () => {
            modal.classList.remove('is-visible');
            modal.setAttribute('aria-hidden', 'true');
            onConfirm = null;
            acceptButton.disabled = false;
            acceptButton.textContent = '确定';
        };

        window.closeConfirmDialog = closeModal;

        window.showConfirmDialog = ({
            title = '确认继续此操作？',
            text = '该操作将立即生效，请确认是否继续。',
            confirmText = '确定',
            onConfirm: confirmHandler = null,
        } = {}) => {
            titleElement.textContent = title;
            textElement.textContent = text;
            acceptButton.textContent = confirmText;
            acceptButton.disabled = false;
            onConfirm = typeof confirmHandler === 'function' ? confirmHandler : null;
            modal.classList.add('is-visible');
            modal.setAttribute('aria-hidden', 'false');
        };

        cancelButtons.forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-visible')) {
                closeModal();
            }
        });

        acceptButton.addEventListener('click', async () => {
            if (!onConfirm) {
                closeModal();
                return;
            }

            acceptButton.disabled = true;
            try {
                const result = onConfirm();

                if (result && typeof result.then === 'function') {
                    await result;
                }

                closeModal();
            } catch (error) {
                acceptButton.disabled = false;
            }
        });
    })();

    const resetLoadingButtons = () => {
        document.querySelectorAll('button[data-loading-text]').forEach((button) => {
            if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
            }

            button.classList.remove('is-loading');
            button.disabled = false;
            delete button.dataset.loadingApplied;
        });
    };

    resetLoadingButtons();
    window.resetLoadingButtons = resetLoadingButtons;
    window.showMessage = showMessage;
    window.inferMessageType = inferMessageType;
    window.addEventListener('pageshow', resetLoadingButtons);

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const linkedButtons = form.id
                ? Array.from(document.querySelectorAll(`button[form="${form.id}"][type="submit"]`))
                : [];

            const submitButtons = [...form.querySelectorAll('button[type="submit"]'), ...linkedButtons]
                .filter((button) => button.dataset.loadingText);

            submitButtons.forEach((button) => {
                if (button.dataset.loadingApplied === 'true') {
                    return;
                }

                button.dataset.loadingApplied = 'true';
                button.dataset.originalHtml = button.innerHTML;
                button.classList.add('is-loading');
                button.disabled = true;
                button.innerHTML = `<span class="button-spinner" aria-hidden="true"></span><span>${button.dataset.loadingText}</span>`;
            });

            window.setTimeout(() => {
                if (event.defaultPrevented) {
                    resetLoadingButtons();
                }
            }, 0);
        });
    });

    document.addEventListener('input', (event) => {
        const field = event.target.closest('input, textarea, select');
        if (!field || !field.classList.contains('is-error')) {
            return;
        }

        field.classList.remove('is-error');
        field.removeAttribute('aria-invalid');

        const container = field.closest('.field-group, label, .form-section, .role-create-body, .stack');
        const error = container?.querySelector('.form-error');
        if (error) {
            error.remove();
        }
    });

    if (body.dataset.adminStatusMessage) {
        showMessage(body.dataset.adminStatusMessage, body.dataset.adminStatusType || inferMessageType(body.dataset.adminStatusMessage));
    }
})();
