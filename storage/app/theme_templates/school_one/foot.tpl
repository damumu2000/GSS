    </main>
    {% set floatingPromos = promos code="global.floating" display_mode="floating" limit="2" %}

    {% if floatingPromos %}
        {% for promoItem in floatingPromos %}
            <div
                class="theme-floating-promo{% if promoItem.display.show_on == 'pc' %} is-show-on-pc{% endif %}{% if promoItem.display.show_on == 'mobile' %} is-show-on-mobile{% endif %}"
                style="{{ promoItem.display.style }}"
                data-floating-promo
                data-floating-close-key="{{ promoItem.display.close_storage_key }}"
                data-floating-close-hours="{{ promoItem.display.close_expire_hours }}"
                data-floating-remember-close="{% if promoItem.display.remember_close %}1{% else %}0{% endif %}"
            >
                <a href="{% if promoItem.link_url %}{{ promoItem.link_url }}{% else %}#{% endif %}"{% if promoItem.link_target == '_blank' %} target="_blank" rel="noopener noreferrer"{% endif %}>
                    <img src="{{ promoItem.image_url }}" alt="{{ promoItem.image_alt }}">
                </a>
                {% if promoItem.display.closable %}
                    <button class="theme-floating-promo-close" type="button" aria-label="关闭漂浮图" data-floating-promo-close>&times;</button>
                {% endif %}
            </div>
        {% endfor %}
    {% endif %}

    <footer class="site-footer">
        <div class="container">
            <div class="footer-card">
                <div>
                    <h2 class="footer-title">{{ site.name }}</h2>
                    <div class="footer-copy">
                        <div>{% if site.address %}{{ site.address }}{% else %}学校地址待完善{% endif %}</div>
                        <div>{% if site.contact_phone %}{{ site.contact_phone }}{% else %}联系电话待完善{% endif %}{% if site.contact_email %} · {{ site.contact_email }}{% endif %}</div>
                        <div>{% if site.filing_number %}备案号：{{ site.filing_number }}{% else %}备案信息待完善{% endif %}</div>
                    </div>
                    <div class="footer-links">
                        <a href="/site-preview?site={{ site.site_key }}">首页</a>
                        <a href="#">学校概况</a>
                        <a href="#">新闻动态</a>
                        <a href="#">招生信息</a>
                        <a href="#">师资力量</a>
                        <a href="#">联系我们</a>
                    </div>
                </div>
                <div class="footer-side">
                    <div class="footer-social">
                        <span>微</span>
                        <span>博</span>
                        <span>视</span>
                    </div>
                    <div class="footer-meta">© {{ site.name }}. All rights reserved.</div>
                </div>
            </div>
        </div>
    </footer>
    <script>
        (() => {
            const now = Date.now();

            document.querySelectorAll('[data-floating-promo]').forEach((element) => {
                const storageKey = element.getAttribute('data-floating-close-key') || '';
                const rememberClose = element.getAttribute('data-floating-remember-close') === '1';
                const expireHours = Number(element.getAttribute('data-floating-close-hours') || '24');

                if (rememberClose && storageKey) {
                    try {
                        const closedAt = Number(window.localStorage.getItem(storageKey) || '0');
                        const expireAt = closedAt + (expireHours * 60 * 60 * 1000);

                        if (closedAt > 0 && expireAt > now) {
                            element.remove();
                            return;
                        }

                        if (closedAt > 0 && expireAt <= now) {
                            window.localStorage.removeItem(storageKey);
                        }
                    } catch (error) {}
                }

                window.requestAnimationFrame(() => {
                    element.classList.add('is-ready');
                });

                element.querySelector('[data-floating-promo-close]')?.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    if (rememberClose && storageKey) {
                        try {
                            window.localStorage.setItem(storageKey, String(Date.now()));
                        } catch (error) {}
                    }

                    element.remove();
                });
            });
        })();
    </script>
</body>
</html>
