(function () {
    var activeTip = null;

    function closeTip() {
        if (activeTip && typeof activeTip.remove === 'function') {
            activeTip.remove();
        }

        activeTip = null;
    }

    function continueTo(link) {
        var href = link.getAttribute('href') || '';

        closeTip();

        if (!href) {
            return;
        }

        if (link.target === '_blank') {
            window.open(href, '_blank', 'noopener,noreferrer');
            return;
        }

        window.location.href = href;
    }

    function showTip(link) {
        var host = link.getAttribute('data-cms-external-host') || '外部网站';
        var backdrop = document.createElement('div');
        var tip = document.createElement('div');

        closeTip();

        backdrop.className = 'cms-external-tip-backdrop';
        tip.className = 'cms-external-tip';
        tip.setAttribute('role', 'status');
        tip.innerHTML = [
            '<p class="cms-external-tip__title">即将打开外部链接</p>',
            '<p class="cms-external-tip__desc">目标：' + escapeHtml(host) + '<br>请确认来源可信，注意下载风险和隐私安全。</p>',
            '<div class="cms-external-tip__actions">',
            '<button class="cms-external-tip__button" type="button" data-cms-external-cancel>取消</button>',
            '<button class="cms-external-tip__button cms-external-tip__button--primary" type="button" data-cms-external-open>继续打开</button>',
            '</div>'
        ].join('');

        tip.querySelector('[data-cms-external-cancel]').addEventListener('click', closeTip);
        tip.querySelector('[data-cms-external-open]').addEventListener('click', function () {
            continueTo(link);
        });
        backdrop.addEventListener('click', closeTip);

        document.body.appendChild(backdrop);
        document.body.appendChild(tip);
        activeTip = {
            remove: function () {
                if (tip.parentNode) {
                    tip.parentNode.removeChild(tip);
                }

                if (backdrop.parentNode) {
                    backdrop.parentNode.removeChild(backdrop);
                }
            }
        };
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    document.addEventListener('click', function (event) {
        var canFindClosest = event.target && typeof event.target.closest === 'function';
        var link = canFindClosest
            ? event.target.closest('a[data-cms-external-link="1"]')
            : null;

        if (!link) {
            if (activeTip && (!canFindClosest || !event.target.closest('.cms-external-tip'))) {
                closeTip();
            }

            return;
        }

        event.preventDefault();
        event.stopPropagation();
        showTip(link);
    });
})();
