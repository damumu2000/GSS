(() => {
    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const noticeModal = document.getElementById('platform-notice-modal');
    const noticeModalTitle = document.getElementById('platform-notice-modal-title');
    const noticeModalDate = document.getElementById('platform-notice-modal-date');
    const noticeModalSummary = document.getElementById('platform-notice-modal-summary');
    const noticeModalContent = document.getElementById('platform-notice-modal-content');
    const noticeModalLink = document.getElementById('platform-notice-modal-link');
    const noticeDetailCache = new Map();

    const closeNoticeModal = () => {
        if (!noticeModal || noticeModal.hidden) {
            return;
        }

        noticeModal.classList.remove('is-open');
        window.setTimeout(() => {
            noticeModal.hidden = true;
        }, 220);
    };

    const openNoticeModal = (payload) => {
        if (!noticeModal || !noticeModalTitle || !noticeModalDate || !noticeModalSummary || !noticeModalContent || !noticeModalLink) {
            return;
        }

        noticeModalTitle.textContent = payload.title || '平台公告';
        noticeModalDate.textContent = payload.date || '--';

        if (payload.summary) {
            noticeModalSummary.hidden = false;
            noticeModalSummary.textContent = payload.summary;
        } else {
            noticeModalSummary.hidden = true;
            noticeModalSummary.textContent = '';
        }

        noticeModalContent.innerHTML = payload.contentHtml && payload.contentHtml.trim() !== ''
            ? payload.contentHtml
            : '<p>暂无公告内容。</p>';
        noticeModalLink.href = payload.link || '#';
        noticeModal.hidden = false;
        window.requestAnimationFrame(() => {
            noticeModal.classList.add('is-open');
        });
    };

    const fetchNoticeDetail = async (url) => {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    };

    document.querySelectorAll('[data-notice-trigger]').forEach((item) => {
        item.addEventListener('click', async () => {
            const detailUrl = item.getAttribute('data-notice-detail-url') || '';
            openNoticeModal({
                title: item.getAttribute('data-notice-title') || '官闪闪公告栏',
                date: item.getAttribute('data-notice-date') || '--',
                link: item.getAttribute('data-notice-link') || '#',
                summary: item.getAttribute('data-notice-summary') || '',
                contentHtml: '<p>内容加载中...</p>',
            });

            if (!detailUrl) {
                noticeModalContent.innerHTML = '<p>暂无公告内容。</p>';
                return;
            }

            if (noticeDetailCache.has(detailUrl)) {
                const payload = noticeDetailCache.get(detailUrl);
                openNoticeModal({
                    title: payload.title || item.getAttribute('data-notice-title') || '官闪闪公告栏',
                    date: payload.date || item.getAttribute('data-notice-date') || '--',
                    link: payload.link || item.getAttribute('data-notice-link') || '#',
                    summary: payload.summary || item.getAttribute('data-notice-summary') || '',
                    contentHtml: payload.content_html || '<p>暂无公告内容。</p>',
                });
                return;
            }

            try {
                const payload = await fetchNoticeDetail(detailUrl);
                noticeDetailCache.set(detailUrl, payload);

                openNoticeModal({
                    title: payload.title || item.getAttribute('data-notice-title') || '官闪闪公告栏',
                    date: payload.date || item.getAttribute('data-notice-date') || '--',
                    link: payload.link || item.getAttribute('data-notice-link') || '#',
                    summary: payload.summary || item.getAttribute('data-notice-summary') || '',
                    contentHtml: payload.content_html || '<p>暂无公告内容。</p>',
                });
            } catch (error) {
                noticeModalContent.innerHTML = '<p>公告内容加载失败，请稍后重试。</p>';
            }
        });
    });

    noticeModal?.querySelectorAll('[data-notice-close]').forEach((element) => {
        element.addEventListener('click', closeNoticeModal);
    });

    noticeModal?.querySelector('[data-notice-shell]')?.addEventListener('click', (event) => {
        if (event.target === event.currentTarget) {
            closeNoticeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeNoticeModal();
        }
    });

    document.querySelectorAll('[data-insight-ring]').forEach((ringShell) => {
        const valueNode = ringShell.querySelector('[data-insight-ring-value]');
        const labelNode = ringShell.querySelector('[data-insight-ring-label]');
        const detailNode = ringShell.querySelector('[data-insight-ring-detail]');
        const defaultState = {
            value: ringShell.dataset.defaultValue || '--',
            label: ringShell.dataset.defaultLabel || '',
            detail: ringShell.dataset.defaultDetail || '',
            segment: 'default',
        };

        if (!valueNode || !labelNode) {
            return;
        }

        const applyState = (state) => {
            valueNode.textContent = state.value;
            labelNode.textContent = state.label;
            if (detailNode) {
                detailNode.textContent = state.detail || '';
            }
            ringShell.dataset.activeSegment = state.segment;
        };

        applyState(defaultState);

        ringShell.querySelectorAll('[data-insight-segment]').forEach((segment) => {
            const segmentState = {
                value: segment.dataset.value || '--',
                label: segment.dataset.label || '',
                detail: segment.dataset.detail || '',
                segment: segment.dataset.segment || 'default',
            };

            segment.addEventListener('mouseenter', () => applyState(segmentState));
            segment.addEventListener('focus', () => applyState(segmentState));
            segment.addEventListener('mouseleave', () => applyState(defaultState));
            segment.addEventListener('blur', () => applyState(defaultState));
        });
    });

    document.querySelectorAll('[data-recent-feed-url]').forEach((item) => {
        item.addEventListener('click', (event) => {
            if (event.target.closest('a, button, form')) {
                return;
            }

            const url = item.getAttribute('data-recent-feed-url');
            const target = item.getAttribute('data-recent-feed-target') || '';

            if (!url) {
                return;
            }

            if (target === '_blank') {
                window.open(url, '_blank', 'noopener');
                return;
            }

            window.location.href = url;
        });
    });

    document.querySelectorAll('[data-system-status-panel]').forEach((panel) => {
        const statusUrl = panel.getAttribute('data-status-url') || '';
        const checkedAtNode = panel.querySelector('[data-system-status-checked-at]');
        const loadingNode = panel.querySelector('[data-system-status-loading]');
        const listNode = panel.querySelector('[data-system-status-list]');
        const emptyNode = panel.querySelector('[data-system-status-empty]');
        const errorNode = panel.querySelector('[data-system-status-error]');
        const actionStatusSelector = panel.getAttribute('data-system-check-action-status-target') || '';
        const actionStatusNode = actionStatusSelector ? document.querySelector(actionStatusSelector) : null;

        if (! statusUrl || ! checkedAtNode || ! loadingNode || ! listNode || ! emptyNode || ! errorNode) {
            return;
        }

        const setState = (state) => {
            loadingNode.hidden = state !== 'loading';
            listNode.hidden = state !== 'ready';
            emptyNode.hidden = state !== 'empty';
            errorNode.hidden = state !== 'error';
        };

        const renderItems = (items) => {
            listNode.innerHTML = items.map((item) => {
                const title = escapeHtml(item.title || '');
                const meta = escapeHtml(item.meta || '');
                const state = escapeHtml(item.state || '');
                const statusClass = escapeHtml(item.status_class || 'draft');
                const actionUrl = typeof item.action_url === 'string' ? item.action_url : '';
                const titleMarkup = actionUrl
                    ? `<a class="recent-feed-title" href="${escapeHtml(actionUrl)}">${title}</a>`
                    : `<div class="recent-feed-title">${title}</div>`;

                return `
                    <article class="recent-feed-item system-status-item">
                        <div class="system-status-head">
                            ${titleMarkup}
                            <span class="status-badge recent-feed-status ${statusClass}">${state}</span>
                        </div>
                        <div class="system-status-meta">${meta}</div>
                    </article>
                `;
            }).join('');
        };

        const updateActionStatus = (status) => {
            if (! actionStatusNode) {
                return;
            }

            const normalizedStatus = ['error', 'draft'].includes(status)
                ? 'error'
                : (['warning', 'pending'].includes(status) ? 'warning' : 'ok');

            actionStatusNode.classList.remove('is-error', 'is-warning');

            if (normalizedStatus === 'ok') {
                actionStatusNode.hidden = true;
                actionStatusNode.textContent = '';
                return;
            }

            actionStatusNode.hidden = false;
            actionStatusNode.textContent = normalizedStatus === 'error' ? '异常' : '警告';
            actionStatusNode.classList.add(normalizedStatus === 'error' ? 'is-error' : 'is-warning');
        };

        setState('loading');

        fetch(statusUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (! response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return response.json();
            })
            .then((payload) => {
                checkedAtNode.textContent = payload.checked_at
                    ? `最近检查：${payload.checked_at}`
                    : '系统状态已加载';

                const items = Array.isArray(payload.items) ? payload.items : [];
                updateActionStatus(payload.overall_status || 'ok');

                if (items.length === 0) {
                    setState('empty');
                    return;
                }

                renderItems(items);
                setState('ready');
            })
            .catch(() => {
                checkedAtNode.textContent = '系统状态加载失败';
                updateActionStatus('error');
                setState('error');
            });
    });
})();
