(function () {
    var initSecurityEventFilters = function () {
    var filterRoot = document.querySelector('[data-security-event-filters]');
    if (!filterRoot || filterRoot.dataset.filtersBound === 'true') {
        return;
    }
    filterRoot.dataset.filtersBound = 'true';

    var buttons = Array.prototype.slice.call(filterRoot.querySelectorAll('[data-filter]'));
    var items = Array.prototype.slice.call(document.querySelectorAll('.security-event[data-rule-code]'));
    var emptyState = document.querySelector('[data-security-event-empty]');

    var applyFilter = function (filter) {
        var visibleCount = 0;

        items.forEach(function (item) {
            var match = filter === 'all'
                || (filter === 'high-risk' && item.dataset.riskLevel === 'high')
                || (filter === 'probe-abuse' && item.dataset.ruleCode === 'probe_abuse')
                || (filter === 'rate-limit' && item.dataset.ruleCode === 'rate_limit');

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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSecurityEventFilters, { once: true });
        return;
    }

    initSecurityEventFilters();
}());
