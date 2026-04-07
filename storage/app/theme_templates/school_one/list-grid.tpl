{% include "top" %}
<style>
    .channel-hero {
        padding: 30px 32px;
        margin-bottom: 20px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .channel-hero h1 {
        margin: 14px 0 10px;
        color: var(--primary);
        font-size: clamp(32px, 4vw, 44px);
    }
    .channel-hero p,
    .channel-crumb,
    .grid-meta,
    .grid-summary {
        color: var(--muted);
    }
    .grid-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }
    .grid-card { padding: 24px; }
    .grid-tag {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 999px;
        background: var(--primary-soft);
        color: var(--primary);
        font-size: 12px;
        margin-bottom: 10px;
    }
    .grid-title {
        margin: 0 0 8px;
        font-size: 24px;
        line-height: 1.45;
        color: var(--primary);
    }
    .title-mark {
        display: inline-flex;
        align-items: center;
        min-height: 22px;
        margin-left: 10px;
        padding: 0 8px;
        border-radius: 999px;
        background: var(--primary-soft);
        color: var(--primary);
        font-size: 12px;
        font-weight: 600;
    }
    .grid-summary {
        line-height: 1.8;
    }
    @media (max-width: 900px) {
        .grid-list { grid-template-columns: 1fr; }
    }
</style>
<div class="container">
    <section class="panel channel-hero">
        <div class="channel-crumb"><a href="/site-preview?site={{ site.site_key }}">{{ site.name }}</a> / {{ channel.name }}</div>
        <h1>{{ channel.name }}</h1>
        <p>适用于图文并重的栏目页，以规整卡片布局承载学校专题、校园文化与特色栏目内容。</p>
    </section>

    {% if items %}
        <section class="grid-list">
            {% for item in items %}
                <article class="panel grid-card">
                    <span class="grid-tag">{{ channel.name }}</span>
                    <h2 class="grid-title"><a href="{{ item.url }}">{{ item.title }}{% if item.is_recommend %}<span class="title-mark">推荐</span>{% endif %}</a></h2>
                    <div class="grid-meta">{{ item.published_at }}</div>
                    <div class="grid-summary" style="margin-top: 10px;">{% if item.summary %}{{ item.summary }}{% else %}暂无摘要。{% endif %}</div>
                </article>
            {% endfor %}
        </section>
    {% else %}
        <section class="panel" style="padding: 24px; color: var(--muted);">当前栏目暂无已发布内容。</section>
    {% endif %}
</div>
{% include "foot" %}
