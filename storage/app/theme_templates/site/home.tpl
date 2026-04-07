{% include "top" %}
{% set heroPromo = promo code="home.hero" %}
{% set carouselPromos = promos code="home.carousel" display_mode="carousel" limit="5" %}
<style>
    .hero-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
        gap: 24px;
    }
    .hero-card {
        padding: 34px;
        background:
            linear-gradient(135deg, rgba(255, 255, 255, 0.86), rgba(239, 248, 244, 0.92)),
            linear-gradient(135deg, #eef8f3, #deeee6);
    }
    .hero-card h1 { margin: 0; font-size: clamp(36px, 5vw, 54px); line-height: 1.1; }
    .hero-card p { margin: 18px 0 0; color: var(--muted); line-height: 1.9; font-size: 16px; }
    .hero-eyebrow {
        display: inline-flex; align-items: center; padding: 8px 14px; border-radius: 999px;
        background: rgba(255, 255, 255, 0.76); border: 1px solid rgba(29, 106, 94, 0.1);
        color: var(--primary-deep); font-size: 13px; letter-spacing: 0.08em; margin-bottom: 18px;
    }
    .hero-actions { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 28px; }
    .hero-button {
        display: inline-flex; align-items: center; justify-content: center; min-height: 46px;
        padding: 0 20px; border-radius: 999px; transition: 0.18s ease;
    }
    .hero-button.primary {
        background: linear-gradient(135deg, var(--primary), var(--primary-deep));
        color: #ffffff; box-shadow: 0 14px 30px rgba(29, 106, 94, 0.18);
    }
    .hero-button.secondary {
        border: 1px solid rgba(29, 106, 94, 0.1);
        background: rgba(255, 255, 255, 0.72);
        color: var(--primary-deep);
    }
    .hero-side { display: grid; gap: 14px; align-content: start; }
    .hero-side-card {
        padding: 20px; border-radius: 24px; background: rgba(255, 255, 255, 0.86);
        border: 1px solid rgba(29, 106, 94, 0.08);
    }
    .hero-side-card strong {
        display: block; font-size: 15px; margin-bottom: 10px; color: var(--primary-deep);
    }
    .hero-side-card div { color: var(--muted); line-height: 1.8; font-size: 14px; }
    .hero-promo-card {
        padding: 0;
        overflow: hidden;
        min-height: 100%;
        box-shadow: 0 18px 38px rgba(18, 55, 46, 0.08);
    }
    .hero-promo-link {
        display: grid;
        min-height: 100%;
        color: inherit;
    }
    .hero-promo-image {
        min-height: 220px;
        background: #e8f3ed;
    }
    .hero-promo-image img {
        width: 100%;
        height: 100%;
        min-height: 220px;
        object-fit: cover;
    }
    .hero-promo-copy {
        padding: 22px;
        display: grid;
        gap: 10px;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.96) 0%, rgba(247, 251, 249, 0.98) 100%);
    }
    .hero-promo-copy strong {
        font-size: 20px;
        line-height: 1.4;
        color: var(--primary-deep);
    }
    .hero-promo-copy span {
        color: var(--muted);
        line-height: 1.8;
        font-size: 14px;
    }
    .metrics-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-top: 18px; }
    .metric-card { padding: 18px 20px; }
    .metric-card strong { display: block; margin-bottom: 8px; color: var(--primary-deep); font-size: 32px; }
    .metric-card span { color: var(--muted); font-size: 14px; }
    .promo-carousel {
        margin-top: 22px;
        padding: 0;
        overflow: hidden;
        position: relative;
        isolation: isolate;
    }
    .promo-carousel-track {
        position: relative;
        min-height: 320px;
    }
    .promo-carousel-slide {
        display: none;
        position: relative;
        min-height: 320px;
        background: #dfeef5;
    }
    .promo-carousel-slide.is-active {
        display: block;
        animation: promoCarouselFade 0.36s ease;
    }
    .promo-carousel-slide img {
        width: 100%;
        height: 320px;
        object-fit: cover;
    }
    .promo-carousel-copy {
        position: absolute;
        left: 28px;
        right: 28px;
        bottom: 28px;
        display: grid;
        gap: 10px;
        color: #fff;
        z-index: 1;
    }
    .promo-carousel-copy strong {
        font-size: clamp(24px, 3vw, 34px);
        line-height: 1.2;
    }
    .promo-carousel-copy span {
        width: min(680px, 100%);
        color: rgba(255, 255, 255, 0.92);
        font-size: 15px;
        line-height: 1.8;
    }
    .promo-carousel-slide::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.04) 0%, rgba(15, 23, 42, 0.46) 100%);
    }
    .promo-carousel-dots {
        position: absolute;
        right: 22px;
        bottom: 20px;
        display: inline-flex;
        gap: 8px;
        z-index: 2;
    }
    .promo-carousel-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        border: 0;
        background: rgba(255, 255, 255, 0.42);
        cursor: pointer;
        padding: 0;
    }
    .promo-carousel-dot.is-active {
        width: 28px;
        background: rgba(255, 255, 255, 0.96);
    }
    .promo-carousel-nav {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 18px;
        pointer-events: none;
        z-index: 2;
    }
    .promo-carousel-arrow {
        width: 42px;
        height: 42px;
        border: 0;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.18);
        backdrop-filter: blur(10px);
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        pointer-events: auto;
        transition: transform 0.18s ease, background 0.18s ease;
    }
    .promo-carousel-arrow:hover {
        transform: translateY(-1px);
        background: rgba(255, 255, 255, 0.28);
    }
    .promo-carousel-arrow svg {
        width: 18px;
        height: 18px;
        stroke: currentColor;
        stroke-width: 2;
        fill: none;
        stroke-linecap: round;
        stroke-linejoin: round;
    }
    .promo-carousel-progress {
        position: absolute;
        left: 22px;
        right: 110px;
        bottom: 22px;
        height: 4px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.18);
        overflow: hidden;
        z-index: 2;
    }
    .promo-carousel-progress span {
        display: block;
        height: 100%;
        width: 0;
        border-radius: inherit;
        background: rgba(255, 255, 255, 0.96);
        transition: width 0.3s ease;
    }
    .section-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr); gap: 22px; margin-top: 22px; }
    .section-panel { padding: 24px; }
    .section-head { display: flex; justify-content: space-between; gap: 14px; align-items: end; margin-bottom: 18px; }
    .section-meta { color: var(--muted); font-size: 14px; }
    .news-feature {
        padding: 20px 22px; border-radius: 24px; background: linear-gradient(135deg, #f8fbf9, #eef6f1);
        border: 1px solid rgba(29, 106, 94, 0.08); margin-bottom: 16px;
    }
    .news-tag {
        display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 999px;
        background: rgba(29, 106, 94, 0.08); color: var(--primary-deep); font-size: 12px;
    }
    .title-mark {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 24px;
        margin-left: 10px;
        padding: 0 8px;
        border-radius: 999px;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
        vertical-align: middle;
        box-shadow: 0 8px 18px rgba(217, 119, 6, 0.18);
    }
    .news-feature h3 { margin: 14px 0 10px; font-size: 26px; line-height: 1.35; }
    .news-item + .news-item { margin-top: 16px; padding-top: 16px; border-top: 1px dashed rgba(29, 106, 94, 0.14); }
    .news-item h3 { margin: 0 0 8px; font-size: 18px; line-height: 1.5; }
    .news-item p, .news-empty, .page-card p, .info-card span { margin: 0; color: var(--muted); line-height: 1.85; }
    .sidebar-stack { display: grid; gap: 18px; }
    .page-list, .info-list { display: grid; gap: 14px; }
    .page-card, .info-card {
        padding: 18px; border-radius: 22px; background: #f8fbf9; border: 1px solid rgba(29, 106, 94, 0.08);
    }
    .page-card strong, .info-card strong { display: block; margin-bottom: 8px; font-size: 17px; line-height: 1.5; }
    .feedback-panel { margin-top: 22px; padding: 24px; }
    .feedback-shell {
        display: grid;
        grid-template-columns: minmax(0, 280px) minmax(0, 1fr);
        gap: 20px;
        align-items: start;
    }
    .feedback-summary {
        padding: 22px;
        border-radius: 24px;
        background: linear-gradient(180deg, #f7fbf9 0%, #eef6f1 100%);
        border: 1px solid rgba(29, 106, 94, 0.08);
    }
    .feedback-kicker {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 0 10px;
        border-radius: 999px;
        background: rgba(29, 106, 94, 0.08);
        color: var(--primary-deep);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.05em;
    }
    .feedback-summary h3 {
        margin: 16px 0 10px;
        font-size: 24px;
        line-height: 1.35;
        color: var(--primary-deep);
    }
    .feedback-summary p,
    .feedback-item p,
    .feedback-empty {
        margin: 0;
        color: var(--muted);
        line-height: 1.85;
    }
    .feedback-metrics {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-top: 18px;
    }
    .feedback-metric {
        padding: 14px 16px;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.8);
        border: 1px solid rgba(29, 106, 94, 0.08);
    }
    .feedback-metric strong {
        display: block;
        color: var(--primary-deep);
        font-size: 22px;
        line-height: 1.1;
    }
    .feedback-metric span {
        display: block;
        margin-top: 6px;
        color: var(--muted);
        font-size: 13px;
    }
    .feedback-list { display: grid; gap: 14px; }
    .feedback-item {
        padding: 18px 20px;
        border-radius: 22px;
        background: #f8fbf9;
        border: 1px solid rgba(29, 106, 94, 0.08);
    }
    .feedback-item-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        margin-bottom: 10px;
    }
    .feedback-item-head strong {
        color: var(--primary-deep);
        font-size: 15px;
        line-height: 1.4;
    }
    .feedback-item-head span {
        color: var(--muted);
        font-size: 12px;
        white-space: nowrap;
    }
    .feedback-reply {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px dashed rgba(29, 106, 94, 0.14);
    }
    .feedback-reply strong {
        display: inline-flex;
        align-items: center;
        margin-bottom: 6px;
        color: var(--primary-deep);
        font-size: 13px;
    }
    .feedback-empty {
        padding: 22px;
        border-radius: 22px;
        background: #f8fbf9;
        border: 1px dashed rgba(29, 106, 94, 0.18);
    }
    @media (max-width: 980px) {
        .hero-grid, .section-grid { grid-template-columns: 1fr; }
        .metrics-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .feedback-shell { grid-template-columns: 1fr; }
    }
    @media (max-width: 720px) {
        .hero-card { padding: 26px; }
        .metrics-grid { grid-template-columns: 1fr; }
        .section-head { flex-direction: column; align-items: flex-start; }
        .promo-carousel-copy {
            left: 20px;
            right: 20px;
            bottom: 54px;
        }
        .promo-carousel-copy strong {
            font-size: 22px;
        }
        .promo-carousel-copy span {
            font-size: 14px;
            line-height: 1.7;
        }
        .promo-carousel-nav {
            padding: 0 12px;
        }
        .promo-carousel-arrow {
            width: 36px;
            height: 36px;
        }
        .promo-carousel-progress {
            left: 20px;
            right: 92px;
            bottom: 18px;
        }
    }
    @keyframes promoCarouselFade {
        from { opacity: 0; transform: scale(1.01); }
        to { opacity: 1; transform: scale(1); }
    }
</style>
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
                    <h2 style="margin:0;font-size:28px;line-height:1.3;">校园新闻</h2>
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
                        <h2 style="margin:0;font-size:24px;line-height:1.3;">重点页面</h2>
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
                        <h2 style="margin:0;font-size:24px;line-height:1.3;">站点信息</h2>
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
                <h2 style="margin:0;font-size:28px;line-height:1.3;">留言反馈</h2>
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
<script>
    (() => {
        const carousel = document.querySelector('[data-promo-carousel]');
        if (!carousel) {
            return;
        }

        const slides = Array.from(carousel.querySelectorAll('[data-promo-carousel-slide]'));
        const dots = Array.from(carousel.querySelectorAll('[data-promo-carousel-dot]'));
        const prevButton = carousel.querySelector('[data-promo-carousel-prev]');
        const nextButton = carousel.querySelector('[data-promo-carousel-next]');
        const progress = carousel.querySelector('[data-promo-carousel-progress]');
        let currentIndex = slides.findIndex((slide) => slide.classList.contains('is-active'));
        let timer = null;

        if (slides.length <= 1) {
            return;
        }

        if (currentIndex < 0) {
            currentIndex = 0;
        }

        const activate = (index) => {
            currentIndex = index;
            slides.forEach((slide, slideIndex) => {
                slide.classList.toggle('is-active', slideIndex === index);
            });
            dots.forEach((dot, dotIndex) => {
                dot.classList.toggle('is-active', dotIndex === index);
            });
            if (progress) {
                progress.style.width = `${((index + 1) / slides.length) * 100}%`;
            }
        };

        const start = () => {
            stop();
            timer = window.setInterval(() => {
                activate((currentIndex + 1) % slides.length);
            }, 4600);
        };

        const stop = () => {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
        };

        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                activate(index);
                start();
            });
        });

        prevButton?.addEventListener('click', () => {
            activate((currentIndex - 1 + slides.length) % slides.length);
            start();
        });

        nextButton?.addEventListener('click', () => {
            activate((currentIndex + 1) % slides.length);
            start();
        });

        carousel.addEventListener('mouseenter', stop);
        carousel.addEventListener('mouseleave', start);
        activate(currentIndex);
        start();
    })();
</script>
{% include "foot" %}
