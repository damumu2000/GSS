(() => {
    const noticeModal = document.getElementById('platform-notice-modal');
    const noticeModalTitle = document.getElementById('platform-notice-modal-title');
    const noticeModalDate = document.getElementById('platform-notice-modal-date');
    const noticeModalSummary = document.getElementById('platform-notice-modal-summary');
    const noticeModalContent = document.getElementById('platform-notice-modal-content');
    const noticeModalLink = document.getElementById('platform-notice-modal-link');
    let previousBodyOverflow = '';

    const closeNoticeModal = () => {
        if (!noticeModal || noticeModal.hidden) {
            return;
        }

        noticeModal.classList.remove('is-open');
        window.setTimeout(() => {
            noticeModal.hidden = true;
            document.body.classList.toggle('has-modal-open', previousBodyOverflow === 'hidden');
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
        previousBodyOverflow = document.body.classList.contains('has-modal-open') ? 'hidden' : '';
        document.body.classList.add('has-modal-open');
        window.requestAnimationFrame(() => {
            noticeModal.classList.add('is-open');
        });
    };

    document.querySelectorAll('[data-notice-trigger]').forEach((item) => {
        item.addEventListener('click', () => {
            const templateId = item.getAttribute('data-notice-content-id');
            const contentTemplate = templateId ? document.getElementById(templateId) : null;

            openNoticeModal({
                title: item.getAttribute('data-notice-title') || '官闪闪公告栏',
                date: item.getAttribute('data-notice-date') || '--',
                link: item.getAttribute('data-notice-link') || '#',
                summary: item.getAttribute('data-notice-summary') || '',
                contentHtml: contentTemplate ? contentTemplate.innerHTML.trim() : '',
            });
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
})();
