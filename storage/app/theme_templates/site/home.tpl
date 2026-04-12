{% include "head" %}
{{ themeStyle path="theme.css" }}
{{ themeStyle path="home.css" }}
{% include "top" %}
{% set heroPromo = promo code="home.hero" %}
{% set carouselPromos = promos code="home.carousel" display_mode="carousel" limit="5" %}
{% set newsItems = contentList type="article" limit="6" order="published_at_desc" %}
{% set pageItems = contentList type="page" limit="4" order="updated_at_desc" status=null %}
{% set stats = stats %}
{% set guestbook = guestbookStats %}
{% set feedbackItems = guestbookMessages limit="4" fields="display_no,name,summary,reply_summary,created_at_label,detail_url" %}
<div class="container">
    <section class="hero-grid">
        <article class="panel hero-card">
            <span class="hero-eyebrow">SCHOOL PORTAL · {{ site.site_key }}</span>
            <h1>{{ site.name }}</h1>
            <p>{% if site.seo_description %}{{ site.seo_description }}{% else %}围绕校园新闻、通知公告、校务公开与学校介绍，提供稳定、清晰、便于长期维护的官网展示空间。{% endif %}</p>
            <div class="hero-actions">
                {% if primaryChannel %}
                    <a class="hero-button primary" href="{{ primaryChannel.url }}"{% if primaryChannel.target == '_blank' %} target="_blank" rel="noopener noreferrer"{% endif %}>进入栏目</a>
                {% else %}
                    <a class="hero-button primary" href="/site-preview?site={{ site.site_key }}">返回首页</a>
                {% endif %}
                {% if newsItems %}
                    <a class="hero-button secondary" href="{{ newsItems.0.url }}">查看最新文章</a>
                {% endif %}
            </div>
        </article>

        <aside class="hero-side">
            {% if heroPromo %}
                <article class="panel hero-promo-card">
                    <a class="hero-promo-link" href="{% if heroPromo.link_url %}{{ heroPromo.link_url }}{% else %}#{% endif %}"{% if heroPromo.link_target == '_blank' %} target="_blank" rel="noopener noreferrer"{% endif %}>
                        <div class="hero-promo-image">
                            <img src="{{ heroPromo.image_url }}" alt="{{ heroPromo.image_alt }}">
                        </div>
                        <div class="hero-promo-copy">
                            <strong>{% if heroPromo.title %}{{ heroPromo.title }}{% else %}校园图宣{% endif %}</strong>
                            <span>{% if heroPromo.subtitle %}{{ heroPromo.subtitle }}{% else %}通过后台图宣管理可替换此处首页主图与跳转内容。{% endif %}</span>
                        </div>
                    </a>
                </article>
            {% else %}
                <div class="hero-side-card">
                    <strong>站点联系</strong>
                    <div>{% if site.contact_phone %}{{ site.contact_phone }}{% else %}联系电话待完善{% endif %}</div>
                    <div>{% if site.contact_email %}{{ site.contact_email }}{% else %}联系邮箱待完善{% endif %}</div>
                </div>
                <div class="hero-side-card">
                    <strong>网站地址</strong>
                    <div>{% if site.address %}{{ site.address }}{% else %}学校地址待完善{% endif %}</div>
                </div>
                <div class="hero-side-card">
                    <strong>备案信息</strong>
                    <div>{% if site.filing_number %}{{ site.filing_number }}{% else %}备案号待设置{% endif %}</div>
                </div>
            {% endif %}
        </aside>
    </section>

    <section class="metrics-grid">
        <article class="panel metric-card"><strong>{{ stats.channels }}</strong><span>启用栏目</span></article>
        <article class="panel metric-card"><strong>{{ stats.articles }}</strong><span>已发布文章</span></article>
        <article class="panel metric-card"><strong>{{ stats.pages }}</strong><span>单页面</span></article>
        <article class="panel metric-card"><strong>{{ stats.status }}</strong><span>站点状态</span></article>
    </section>

    {% if carouselPromos %}
        <section class="panel promo-carousel" data-promo-carousel>
            <div class="promo-carousel-track">
                {% for item in carouselPromos %}
                    <a
                        class="promo-carousel-slide{% if loop.first %} is-active{% endif %}"
                        href="{% if item.link_url %}{{ item.link_url }}{% else %}#{% endif %}"
                        data-promo-carousel-slide
                        {% if item.link_target == '_blank' %}target="_blank" rel="noopener noreferrer"{% endif %}
                    >
                        <img src="{{ item.image_url }}" alt="{{ item.image_alt }}">
                        <div class="promo-carousel-copy">
                            <strong>{% if item.title %}{{ item.title }}{% else %}校园轮播图宣{% endif %}</strong>
                            <span>{% if item.subtitle %}{{ item.subtitle }}{% else %}通过后台图宣管理可配置首页轮播图、跳转地址与排序。{% endif %}</span>
                        </div>
                    </a>
                {% endfor %}
            </div>
            {% if carouselPromos|length > 1 %}
                <div class="promo-carousel-nav">
                    <button class="promo-carousel-arrow" type="button" aria-label="上一张" data-promo-carousel-prev>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button class="promo-carousel-arrow" type="button" aria-label="下一张" data-promo-carousel-next>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                    </button>
                </div>
                <div class="promo-carousel-progress" aria-hidden="true"><span data-promo-carousel-progress></span></div>
            {% endif %}
            <div class="promo-carousel-dots">
                {% for item in carouselPromos %}
                    <button class="promo-carousel-dot{% if loop.first %} is-active{% endif %}" type="button" data-promo-carousel-dot aria-label="切换到第 {{ loop.iteration }} 张"></button>
                {% endfor %}
            </div>
        </section>
    {% endif %}

    <section class="section-grid">
        <article class="panel section-panel">
            <div class="section-head">
                <div>
                    <h2 class="section-title section-title--large">校园新闻</h2>
                    <div class="section-meta">内容数量、排序与栏目来源由模板标签控制。</div>
                </div>
            </div>

            {% if newsItems %}
                <div class="news-feature">
                    <span class="news-tag">最新发布</span>
                    <h3><a href="{{ newsItems.0.url }}">{{ newsItems.0.title }}{% if newsItems.0.is_recommend %}<span class="title-mark">精</span>{% endif %}</a></h3>
                    <p>{% if newsItems.0.summary %}{{ newsItems.0.summary }}{% else %}暂无摘要。{% endif %}</p>
                </div>

                {% for item in newsItems %}
                    {% if not loop.first %}
                        <article class="news-item">
                            <h3><a href="{{ item.url }}">{{ item.title }}{% if item.is_recommend %}<span class="title-mark">精</span>{% endif %}</a></h3>
                            <p>{% if item.summary %}{{ item.summary }}{% else %}暂无摘要。{% endif %}</p>
                        </article>
                    {% endif %}
                {% endfor %}
            {% else %}
                <div class="news-empty">当前站点暂无已发布新闻内容。</div>
            {% endif %}
        </article>

        <aside class="sidebar-stack">
            <section class="panel section-panel">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">重点页面</h2>
                        <div class="section-meta">站点介绍、联系我们等页面会自动进入此区块。</div>
                    </div>
                </div>
                <div class="page-list">
                    {% for item in pageItems %}
                        <a class="page-card" href="{{ item.url }}">
                            <strong>{{ item.title }}</strong>
                            <p>{% if item.summary %}{{ item.summary }}{% else %}点击查看页面内容。{% endif %}</p>
                        </a>
                    {% endfor %}
                </div>
            </section>

            <section class="panel section-panel">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">站点信息</h2>
                        <div class="section-meta">此区域统一读取站点设置中的真实信息。</div>
                    </div>
                </div>
                <div class="info-list">
                    <div class="info-card">
                        <strong>学校名称</strong>
                        <span>{{ site.name }}</span>
                    </div>
                    <div class="info-card">
                        <strong>联系地址</strong>
                        <span>{% if site.address %}{{ site.address }}{% else %}学校地址待完善{% endif %}</span>
                    </div>
                    <div class="info-card">
                        <strong>备案号</strong>
                        <span>{% if site.filing_number %}{{ site.filing_number }}{% else %}备案号待设置{% endif %}</span>
                    </div>
                </div>
            </section>
        </aside>
    </section>

    <section class="panel feedback-panel">
        <div class="section-head">
            <div>
                <h2 class="section-title section-title--large">留言反馈</h2>
                <div class="section-meta">示例直接调用 guestbookStats 和 guestbookMessages，可自动联动留言板开关、姓名显示和回复后展示规则。</div>
            </div>
        </div>
        <div class="feedback-shell">
            <div class="feedback-summary">
                <span class="feedback-kicker">GUESTBOOK</span>
                <h3>{% if guestbook.enabled %}公开留言展示{% else %}留言板暂不可用{% endif %}</h3>
                <p>{% if guestbook.enabled %}首页可直接展示留言摘要、回复摘要与统计信息，不需要额外维护第二份数据。{% else %}{{ guestbook.message }}。启用留言板模块后，这里会自动切换为公开留言列表。{% endif %}</p>
                <div class="feedback-metrics">
                    <div class="feedback-metric">
                        <strong>{{ guestbook.total }}</strong>
                        <span>累计公开留言</span>
                    </div>
                    <div class="feedback-metric">
                        <strong>{{ guestbook.replied }}</strong>
                        <span>已回复留言</span>
                    </div>
                </div>
            </div>

            <div class="feedback-list">
                {% if guestbook.enabled and feedbackItems %}
                    {% for item in feedbackItems %}
                        <article class="feedback-item">
                            <div class="feedback-item-head">
                                <strong>#{{ item.display_no }} · {{ item.name }}</strong>
                                <span>{{ item.created_at_label }}</span>
                            </div>
                            <p>{{ item.summary }}</p>
                            {% if item.reply_summary %}
                                <div class="feedback-reply">
                                    <strong>回复摘要</strong>
                                    <p>{{ item.reply_summary }}</p>
                                </div>
                            {% endif %}
                        </article>
                    {% endfor %}
                {% else %}
                    <div class="feedback-empty">
                        {% if guestbook.enabled %}
                            当前还没有可展示的公开留言内容。
                        {% else %}
                            {{ guestbook.message }}。
                        {% endif %}
                    </div>
                {% endif %}
            </div>
        </div>
    </section>
</div>
{{ themeScript path="home.js" }}
{% include "foot" %}
