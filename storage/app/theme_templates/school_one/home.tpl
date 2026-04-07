{% include "top" %}
{% set heroPromo = promo code="home.hero" %}
{% set carouselPromos = promos code="home.carousel" display_mode="carousel" limit="5" %}
{% set newsItems = contentList type="article" limit="6" order="published_at_desc" %}
{% set noticeItems = contentList type="article" limit="5" order="published_at_desc" %}
{% set pageItems = contentList type="page" limit="4" order="updated_at_desc" status=null %}
{% set stats = stats %}
<style>
    .hero {
        position: relative;
        min-height: 520px;
        overflow: hidden;
        border-radius: var(--radius);
        border: 1px solid var(--line);
        background: #dbe7f3;
    }
    .hero-slide {
        position: absolute;
        inset: 0;
        display: none;
    }
    .hero-slide.is-active {
        display: block;
    }
    .hero-media,
    .hero-media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .hero-media {
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, #d9e3ee 0%, #eef3f8 100%);
    }
    .hero-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, rgba(0, 28, 58, 0.74) 0%, rgba(0, 38, 77, 0.54) 45%, rgba(0, 38, 77, 0.18) 100%);
    }
    .hero-content {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: flex-end;
        min-height: 520px;
        padding: 56px;
    }
    .hero-copy {
        width: min(640px, 100%);
        color: #ffffff;
    }
    .hero-kicker {
        display: inline-flex;
        align-items: center;
        min-height: 32px;
        padding: 0 12px;
        border: 1px solid rgba(255, 255, 255, 0.28);
        border-radius: 999px;
        color: rgba(255, 255, 255, 0.88);
        font-size: 12px;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    .hero-title {
        margin: 18px 0 0;
        font-size: clamp(36px, 5vw, 56px);
        line-height: 1.14;
        font-weight: 700;
    }
    .hero-text {
        margin-top: 18px;
        color: rgba(255, 255, 255, 0.88);
        font-size: 16px;
        line-height: 1.9;
    }
    .hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 28px;
    }
    .hero-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 46px;
        padding: 0 20px;
        border-radius: 8px;
        border: 1px solid transparent;
        font-size: 14px;
        font-weight: 600;
    }
    .hero-button.primary {
        background: #ffffff;
        color: var(--primary);
    }
    .hero-button.secondary {
        border-color: rgba(255, 255, 255, 0.28);
        color: #ffffff;
        background: transparent;
    }
    .hero-dots {
        position: absolute;
        right: 24px;
        bottom: 24px;
        z-index: 2;
        display: flex;
        gap: 8px;
    }
    .hero-dot {
        width: 10px;
        height: 10px;
        border: 0;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.42);
        cursor: pointer;
        padding: 0;
    }
    .hero-dot.is-active {
        width: 28px;
        background: #ffffff;
    }
    .stats-grid,
    .feature-grid {
        display: grid;
        gap: 18px;
        margin-top: 28px;
    }
    .stats-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .stat-card,
    .feature-card,
    .news-card,
    .notice-card {
        padding: 24px;
    }
    .stat-card strong {
        display: block;
        color: var(--primary);
        font-size: 30px;
        line-height: 1.1;
        font-weight: 700;
    }
    .stat-card span {
        display: block;
        margin-top: 10px;
        color: var(--muted);
        font-size: 14px;
    }
    .feature-header,
    .news-header {
        margin-top: 42px;
        margin-bottom: 18px;
    }
    .feature-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .feature-card h3 {
        margin: 0;
        color: var(--primary);
        font-size: 18px;
        line-height: 1.45;
    }
    .feature-card p {
        margin: 14px 0 0;
        color: var(--muted);
        font-size: 14px;
        line-height: 1.9;
    }
    .news-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr);
        gap: 20px;
        margin-top: 18px;
    }
    .news-cover {
        width: 100%;
        height: 260px;
        border-radius: 8px;
        overflow: hidden;
        background: #e6ebf2;
    }
    .news-cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .news-badge {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 0 10px;
        border-radius: 999px;
        background: var(--primary-soft);
        color: var(--primary);
        font-size: 12px;
        font-weight: 600;
    }
    .news-card h3 {
        margin: 18px 0 10px;
        font-size: 30px;
        line-height: 1.35;
        color: var(--primary);
    }
    .news-card p {
        margin: 0;
        color: var(--muted);
        line-height: 1.9;
    }
    .news-meta,
    .notice-meta {
        margin-top: 16px;
        color: var(--muted);
        font-size: 13px;
    }
    .notice-list {
        display: grid;
        gap: 14px;
    }
    .notice-item + .notice-item {
        padding-top: 14px;
        border-top: 1px solid #eef2f7;
    }
    .notice-item a {
        display: block;
        color: var(--text);
        font-size: 16px;
        line-height: 1.75;
        font-weight: 600;
    }
    .campus-layout {
        display: grid;
        grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr);
        gap: 20px;
        margin-top: 28px;
    }
    .campus-panel {
        padding: 28px;
    }
    .campus-list {
        display: grid;
        gap: 14px;
        margin-top: 18px;
    }
    .campus-item {
        display: grid;
        gap: 6px;
        padding-bottom: 14px;
        border-bottom: 1px solid #eef2f7;
    }
    .campus-item:last-child {
        padding-bottom: 0;
        border-bottom: 0;
    }
    .campus-item strong {
        color: var(--primary);
        font-size: 16px;
    }
    .campus-item span,
    .info-grid div {
        color: var(--muted);
        line-height: 1.85;
        font-size: 14px;
    }
    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px 20px;
        margin-top: 18px;
    }
    .guestbook-layout {
        display: grid;
        grid-template-columns: minmax(0, 300px) minmax(0, 1fr);
        gap: 20px;
        margin-top: 28px;
    }
    .guestbook-summary,
    .guestbook-card {
        padding: 28px;
    }
    .guestbook-pill {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 0 10px;
        border-radius: 999px;
        background: var(--primary-soft);
        color: var(--primary);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.05em;
    }
    .guestbook-summary h3 {
        margin: 16px 0 10px;
        color: var(--primary);
        font-size: 26px;
        line-height: 1.35;
    }
    .guestbook-summary p,
    .guestbook-empty,
    .guestbook-item p {
        margin: 0;
        color: var(--muted);
        line-height: 1.85;
    }
    .guestbook-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-top: 18px;
    }
    .guestbook-stat {
        padding: 14px 16px;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px solid #eef2f7;
    }
    .guestbook-stat strong {
        display: block;
        color: var(--primary);
        font-size: 24px;
        line-height: 1.1;
    }
    .guestbook-stat span {
        display: block;
        margin-top: 6px;
        color: var(--muted);
        font-size: 13px;
    }
    .guestbook-list {
        display: grid;
        gap: 14px;
    }
    .guestbook-item + .guestbook-item {
        padding-top: 14px;
        border-top: 1px solid #eef2f7;
    }
    .guestbook-item-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        margin-bottom: 10px;
    }
    .guestbook-item-head strong {
        color: var(--primary);
        font-size: 15px;
        line-height: 1.4;
    }
    .guestbook-item-head span {
        color: var(--muted);
        font-size: 12px;
        white-space: nowrap;
    }
    .guestbook-reply {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px dashed #e3eaf2;
    }
    .guestbook-reply strong {
        display: inline-flex;
        margin-bottom: 6px;
        color: var(--primary);
        font-size: 13px;
    }
    .guestbook-empty {
        padding: 18px 0 0;
    }
    @media (max-width: 1024px) {
        .stats-grid,
        .feature-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .news-layout,
        .campus-layout,
        .guestbook-layout {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 720px) {
        .hero {
            min-height: 440px;
        }
        .hero-content {
            min-height: 440px;
            padding: 28px 24px;
        }
        .stats-grid,
        .feature-grid,
        .info-grid {
            grid-template-columns: 1fr;
        }
        .hero-title {
            font-size: 34px;
        }
        .news-card h3 {
            font-size: 24px;
        }
    }
</style>
{% set guestbook = guestbookStats %}
{% set feedbackItems = guestbookMessages limit="3" fields="display_no,name,summary,reply_summary,created_at_label" %}
<div class="container">
    <section class="hero panel" data-hero-carousel>
        {% if carouselPromos %}
            {% for item in carouselPromos %}
                <div class="hero-slide{% if loop.first %} is-active{% endif %}" data-hero-slide>
                    <div class="hero-media">
                        <img src="{{ item.image_url }}" alt="{{ item.image_alt }}">
                    </div>
                    <div class="hero-overlay"></div>
                    <div class="hero-content">
                        <div class="hero-copy">
                            <span class="hero-kicker">Modern School Website</span>
                            <h1 class="hero-title">{% if item.title %}{{ item.title }}{% else %}{{ site.name }}{% endif %}</h1>
                            <div class="hero-text">{% if item.subtitle %}{{ item.subtitle }}{% else %}{% if site.seo_description %}{{ site.seo_description }}{% else %}以清晰的信息结构与稳定的内容展示，呈现学校教育理念、校园风貌与最新动态。{% endif %}{% endif %}</div>
                            <div class="hero-actions">
                                {% if primaryChannel %}
                                    <a class="hero-button primary" href="{{ primaryChannel.url }}"{% if primaryChannel.target == '_blank' %} target="_blank" rel="noopener noreferrer"{% endif %}>进入核心栏目</a>
                                {% else %}
                                    <a class="hero-button primary" href="/site-preview?site={{ site.site_key }}">访问首页</a>
                                {% endif %}
                                {% if newsItems %}
                                    <a class="hero-button secondary" href="{{ newsItems.0.url }}">查看最新新闻</a>
                                {% endif %}
                            </div>
                        </div>
                    </div>
                </div>
            {% endfor %}
            {% if carouselPromos|length > 1 %}
                <div class="hero-dots">
                    {% for item in carouselPromos %}
                        <button class="hero-dot{% if loop.first %} is-active{% endif %}" type="button" data-hero-dot aria-label="切换到第 {{ loop.iteration }} 张"></button>
                    {% endfor %}
                </div>
            {% endif %}
        {% else %}
            <div class="hero-slide is-active" data-hero-slide>
                <div class="hero-media">
                    {% if heroPromo %}
                        <img src="{{ heroPromo.image_url }}" alt="{{ heroPromo.image_alt }}">
                    {% endif %}
                </div>
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <div class="hero-copy">
                        <span class="hero-kicker">Modern School Website</span>
                        <h1 class="hero-title">{{ site.name }}</h1>
                        <div class="hero-text">{% if site.seo_description %}{{ site.seo_description }}{% else %}以极简、清晰、稳定的信息设计，呈现现代学校官网的标准内容结构。{% endif %}</div>
                        <div class="hero-actions">
                            {% if primaryChannel %}
                                <a class="hero-button primary" href="{{ primaryChannel.url }}"{% if primaryChannel.target == '_blank' %} target="_blank" rel="noopener noreferrer"{% endif %}>进入核心栏目</a>
                            {% endif %}
                            <a class="hero-button secondary" href="#school-features">了解学校特色</a>
                        </div>
                    </div>
                </div>
            </div>
        {% endif %}
    </section>

    <section class="stats-grid">
        <article class="panel stat-card"><strong>{{ stats.channels }}</strong><span>栏目数量</span></article>
        <article class="panel stat-card"><strong>{{ stats.articles }}</strong><span>新闻内容</span></article>
        <article class="panel stat-card"><strong>{{ stats.pages }}</strong><span>单页信息</span></article>
        <article class="panel stat-card"><strong>{{ stats.status }}</strong><span>站点状态</span></article>
    </section>

    <header class="feature-header" id="school-features">
        <h2 class="section-title">学校办学特色</h2>
        <div class="section-desc">采用三栏至四栏的信息卡片布局，统一展示学校理念、教学实力与校园氛围。</div>
    </header>
    <section class="feature-grid">
        <article class="panel feature-card">
            <h3>国际化视野</h3>
            <p>构建开放协同的教育生态，培养面向未来的综合素养。</p>
        </article>
        <article class="panel feature-card">
            <h3>卓越教学</h3>
            <p>围绕课程质量、教学研究与学生成长，打造稳定高效的教学体系。</p>
        </article>
        <article class="panel feature-card">
            <h3>校园文化</h3>
            <p>坚持以人为本，在课程之外营造真实、温暖、有秩序的校园生活。</p>
        </article>
        <article class="panel feature-card">
            <h3>智慧校园</h3>
            <p>通过信息化平台连接教学、管理与服务，提升校园运行效率。</p>
        </article>
    </section>

    <header class="news-header">
        <h2 class="section-title">新闻与通知</h2>
        <div class="section-desc">左侧焦点新闻，右侧文字通知列表，保持阅读路径清晰、层级明确。</div>
    </header>
    <section class="news-layout">
        <article class="panel news-card">
            {% if newsItems and newsItems.0 %}
                <div class="news-cover">
                    {% if heroPromo %}
                        <img src="{{ heroPromo.image_url }}" alt="{{ heroPromo.image_alt }}">
                    {% endif %}
                </div>
                <div class="news-badge" style="margin-top:18px;">焦点新闻</div>
                <h3><a href="{{ newsItems.0.url }}">{{ newsItems.0.title }}</a></h3>
                <p>{% if newsItems.0.summary %}{{ newsItems.0.summary }}{% else %}点击查看该新闻的详细内容。{% endif %}</p>
                <div class="news-meta">{{ newsItems.0.channel_name }} · {{ newsItems.0.published_at }}</div>
            {% else %}
                <div class="news-badge">焦点新闻</div>
                <h3>当前暂无已发布内容</h3>
                <p>可以在后台发布文章后自动展示到这里。</p>
            {% endif %}
        </article>

        <aside class="panel notice-card">
            <div class="news-badge">通知公告</div>
            <div class="notice-list" style="margin-top:18px;">
                {% if noticeItems %}
                    {% for item in noticeItems %}
                        <article class="notice-item">
                            <a href="{{ item.url }}">{{ item.title }}</a>
                            <div class="notice-meta">{{ item.published_at }}</div>
                        </article>
                    {% endfor %}
                {% else %}
                    <article class="notice-item">
                        <a href="#">当前暂无通知内容</a>
                        <div class="notice-meta">发布后将在这里展示</div>
                    </article>
                {% endif %}
            </div>
        </aside>
    </section>

    <section class="campus-layout">
        <article class="panel campus-panel">
            <h2 class="section-title" style="font-size:28px;">重点栏目</h2>
            <div class="section-desc">自动读取网站中的单页内容，适合展示招生信息、办学理念、校园生活等核心模块。</div>
            <div class="campus-list">
                {% if pageItems %}
                    {% for item in pageItems %}
                        <a class="campus-item" href="{{ item.url }}">
                            <strong>{{ item.title }}</strong>
                            <span>{% if item.summary %}{{ item.summary }}{% else %}点击查看详细页面内容。{% endif %}</span>
                        </a>
                    {% endfor %}
                {% else %}
                    <div class="campus-item">
                        <strong>暂无重点栏目内容</strong>
                        <span>创建单页后，这里会自动呈现相关信息。</span>
                    </div>
                {% endif %}
            </div>
        </article>

        <aside class="panel campus-panel">
            <h2 class="section-title" style="font-size:28px;">学校信息</h2>
            <div class="section-desc">展示学校基础资料与联络方式，帮助访客快速建立信任与联系通道。</div>
            <div class="info-grid">
                <div>
                    <strong style="display:block;color:var(--primary);margin-bottom:8px;">学校名称</strong>
                    <span>{{ site.name }}</span>
                </div>
                <div>
                    <strong style="display:block;color:var(--primary);margin-bottom:8px;">联系地址</strong>
                    <span>{% if site.address %}{{ site.address }}{% else %}学校地址待完善{% endif %}</span>
                </div>
                <div>
                    <strong style="display:block;color:var(--primary);margin-bottom:8px;">联系电话</strong>
                    <span>{% if site.contact_phone %}{{ site.contact_phone }}{% else %}联系电话待完善{% endif %}</span>
                </div>
                <div>
                    <strong style="display:block;color:var(--primary);margin-bottom:8px;">备案信息</strong>
                    <span>{% if site.filing_number %}{{ site.filing_number }}{% else %}备案号待完善{% endif %}</span>
                </div>
            </div>
        </aside>
    </section>

    <section class="guestbook-layout">
        <article class="panel guestbook-summary">
            <span class="guestbook-pill">留言反馈</span>
            <h3>{% if guestbook.enabled %}公开留言展示{% else %}留言板暂未开放{% endif %}</h3>
            <p>{% if guestbook.enabled %}这块示例直接调用 guestbookStats 和 guestbookMessages，可自动联动留言板启用状态、姓名显示规则和回复后展示规则。{% else %}{{ guestbook.message }}。启用后，这里会自动切换为公开留言列表。{% endif %}</p>
            <div class="guestbook-stats">
                <div class="guestbook-stat">
                    <strong>{{ guestbook.total }}</strong>
                    <span>累计公开留言</span>
                </div>
                <div class="guestbook-stat">
                    <strong>{{ guestbook.replied }}</strong>
                    <span>已回复留言</span>
                </div>
            </div>
        </article>

        <aside class="panel guestbook-card">
            <div class="news-badge">最新互动</div>
            {% if guestbook.enabled and feedbackItems %}
                <div class="guestbook-list" style="margin-top:18px;">
                    {% for item in feedbackItems %}
                        <article class="guestbook-item">
                            <div class="guestbook-item-head">
                                <strong>#{{ item.display_no }} · {{ item.name }}</strong>
                                <span>{{ item.created_at_label }}</span>
                            </div>
                            <p>{{ item.summary }}</p>
                            {% if item.reply_summary %}
                                <div class="guestbook-reply">
                                    <strong>回复摘要</strong>
                                    <p>{{ item.reply_summary }}</p>
                                </div>
                            {% endif %}
                        </article>
                    {% endfor %}
                </div>
            {% else %}
                <div class="guestbook-empty">
                    {% if guestbook.enabled %}
                        当前还没有可展示的公开留言内容。
                    {% else %}
                        {{ guestbook.message }}。
                    {% endif %}
                </div>
            {% endif %}
        </aside>
    </section>
</div>
<script>
    (() => {
        const root = document.querySelector('[data-hero-carousel]');
        if (!root) {
            return;
        }

        const slides = Array.from(root.querySelectorAll('[data-hero-slide]'));
        const dots = Array.from(root.querySelectorAll('[data-hero-dot]'));
        let activeIndex = slides.findIndex((slide) => slide.classList.contains('is-active'));
        let timer = null;

        if (slides.length <= 1) {
            return;
        }

        if (activeIndex < 0) {
            activeIndex = 0;
        }

        const activate = (index) => {
            activeIndex = index;
            slides.forEach((slide, slideIndex) => {
                slide.classList.toggle('is-active', slideIndex === index);
            });
            dots.forEach((dot, dotIndex) => {
                dot.classList.toggle('is-active', dotIndex === index);
            });
        };

        const restart = () => {
            if (timer) {
                window.clearInterval(timer);
            }
            timer = window.setInterval(() => {
                activate((activeIndex + 1) % slides.length);
            }, 4800);
        };

        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                activate(index);
                restart();
            });
        });

        activate(activeIndex);
        restart();
    })();
</script>
{% include "foot" %}
