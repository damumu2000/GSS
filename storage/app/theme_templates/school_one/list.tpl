{% include "top" %}
<style>
    .channel-hero {
        padding: 30px 32px;
        margin-bottom: 20px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .channel-crumb,
    .channel-hero p,
    .list-meta {
        color: var(--muted);
        font-size: 14px;
    }
    .channel-hero h1 {
        margin: 14px 0 10px;
        color: var(--primary);
        font-size: clamp(32px, 4vw, 42px);
        line-height: 1.2;
    }
    .list-panel { padding: 28px 32px; }
    .list-item + .list-item {
        margin-top: 22px;
        padding-top: 22px;
        border-top: 1px solid #eef2f7;
    }
    .list-title {
        margin: 10px 0;
        font-size: 26px;
        line-height: 1.45;
        color: var(--primary);
    }
    .title-mark {
        display: inline-flex;
        align-items: center;
        min-height: 22px;
        padding: 0 8px;
        margin-left: 10px;
        border-radius: 999px;
        background: var(--primary-soft);
        color: var(--primary);
        font-size: 12px;
        font-weight: 600;
    }
    .list-summary {
        color: #4b5563;
        line-height: 1.9;
    }
</style>
<div class="container">
    <section class="panel channel-hero">
        <div class="channel-crumb"><a href="/site-preview?site={{ site.site_key }}">{{ site.name }}</a> / {{ channel.name }}</div>
        <h1>{{ channel.name }}</h1>
        <p>以简洁、规整的信息结构展示当前栏目内容，适合新闻动态、通知公告等标准学校官网栏目。</p>
    </section>

    <section class="panel list-panel">
        {% if items %}
            {% for item in items %}
                <article class="list-item">
                    <div class="list-meta">{{ item.published_at }} · {{ item.channel_name }}</div>
                    <h2 class="list-title"><a href="{{ item.url }}">{{ item.title }}{% if item.is_recommend %}<span class="title-mark">推荐</span>{% endif %}</a></h2>
                    <div class="list-summary">{% if item.summary %}{{ item.summary }}{% else %}暂无摘要。{% endif %}</div>
                </article>
            {% endfor %}
        {% else %}
            <div class="list-summary">当前栏目暂无已发布内容。</div>
        {% endif %}
    </section>
</div>
{% include "foot" %}
