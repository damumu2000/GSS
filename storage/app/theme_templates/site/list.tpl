{% include "top" %}
<style>
    .channel-hero { padding: 28px; margin-bottom: 22px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.86), rgba(239, 248, 244, 0.92)), linear-gradient(135deg, #eef8f3, #deeee6); }
    .channel-crumb, .channel-hero p { color: var(--muted); font-size: 14px; }
    .channel-hero h1 { margin: 14px 0 10px; font-size: clamp(32px, 4vw, 42px); line-height: 1.2; }
    .list-panel { padding: 24px; }
    .list-item + .list-item { margin-top: 18px; padding-top: 18px; border-top: 1px solid #eef2ef; }
    .list-meta { color: var(--muted); font-size: 13px; }
    .list-title { margin: 8px 0; font-size: 22px; line-height: 1.45; }
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
    .list-summary { color: #556862; line-height: 1.85; }
</style>
<div class="container">
    <section class="panel channel-hero">
        <div class="channel-crumb"><a href="/site-preview?site={{ site.site_key }}">{{ site.name }}</a> / {{ channel.name }}</div>
        <h1>{{ channel.name }}</h1>
        <p>此列表页通过栏目模板读取当前栏目的文章数据，数量、排序和筛选规则都交给模板标签控制。</p>
    </section>

    <section class="panel list-panel">
        {% if items %}
            {% for item in items %}
                <article class="list-item">
                    <div class="list-meta">{{ item.published_at }} · {{ item.channel_name }}</div>
                    <h2 class="list-title"><a href="{{ item.url }}">{{ item.title }}{% if item.is_recommend %}<span class="title-mark">精</span>{% endif %}</a></h2>
                    <div class="list-summary">{% if item.summary %}{{ item.summary }}{% else %}暂无摘要。{% endif %}</div>
                </article>
            {% endfor %}
        {% else %}
            <div class="list-summary">当前栏目暂无已发布内容。</div>
        {% endif %}
    </section>
</div>
{% include "foot" %}
