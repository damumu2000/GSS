{% include "top" %}
<style>
    .channel-hero { padding: 30px; margin-bottom: 22px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.84), rgba(239, 248, 244, 0.9)), linear-gradient(135deg, #eef8f3, #deeee6); }
    .channel-hero h1 { margin: 14px 0 10px; font-size: clamp(32px, 4vw, 44px); }
    .channel-hero p, .channel-crumb { color: var(--muted); }
    .grid-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
    .grid-card { padding: 22px; }
    .grid-tag { display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 999px; background: var(--primary-soft); color: var(--primary-deep); font-size: 12px; margin-bottom: 10px; }
    .grid-title { margin: 0 0 8px; font-size: 22px; line-height: 1.45; }
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
    .grid-meta, .grid-summary { color: var(--muted); line-height: 1.8; }
    @media (max-width: 900px) { .grid-list { grid-template-columns: 1fr; } }
</style>
<div class="container">
    <section class="panel channel-hero">
        <div class="channel-crumb"><a href="/site-preview?site={{ site.site_key }}">{{ site.name }}</a> / {{ channel.name }}</div>
        <h1>{{ channel.name }}</h1>
        <p>网格列表模板适合图文感更强的栏目场景，模板内部可直接通过动态标签控制文章调用策略。</p>
    </section>

    {% if items %}
        <section class="grid-list">
            {% for item in items %}
                <article class="panel grid-card">
                    <span class="grid-tag">{{ channel.name }}</span>
                    <h2 class="grid-title"><a href="{{ item.url }}">{{ item.title }}{% if item.is_recommend %}<span class="title-mark">精</span>{% endif %}</a></h2>
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
