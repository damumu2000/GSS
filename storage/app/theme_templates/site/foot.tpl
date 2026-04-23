    </main>
    {% set floatingPromos = promos(code='global.floating', display_mode='floating', limit=2) %}

    {% if floatingPromos %}
        {% for promoItem in floatingPromos %}
            <div
                class="theme-floating-promo theme-floating-promo--{{ promoItem.display.position }} theme-floating-promo--offset-x-{{ promoItem.display.offset_x_token }} theme-floating-promo--offset-y-{{ promoItem.display.offset_y_token }} theme-floating-promo--width-{{ promoItem.display.width_token }} theme-floating-promo--z-{{ promoItem.display.z_index_token }} is-anim-{{ promoItem.display.animation }}{% if promoItem.display.height_token %} theme-floating-promo--height-{{ promoItem.display.height_token }}{% endif %}{% if promoItem.display.show_on == 'pc' %} is-show-on-pc{% endif %}{% if promoItem.display.show_on == 'mobile' %} is-show-on-mobile{% endif %}"
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
            <div class="site-footer-card">
                <div class="site-footer-copy">
                    <div>
                        {{ site.name }}
                        {% if site.filing_number %} · 备案号：{{ site.filing_number }}{% endif %}
                    </div>
                    <div>{% if site.address %}{{ site.address }}{% else %}联系地址待完善{% endif %}</div>
                </div>
                <div class="site-footer-meta">
                    <div>{% if site.contact_phone %}{{ site.contact_phone }}{% else %}联系电话待完善{% endif %}</div>
                    <div>{% if site.contact_email %}{{ site.contact_email }}{% else %}联系邮箱待完善{% endif %}</div>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
