{% include "head" %}
{{ themeStyle path="theme.css" }}
{{ themeStyle path="list-grid.css" }}
{% include "top" %}
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
                    <div class="grid-summary grid-summary--spaced">{% if item.summary %}{{ item.summary }}{% else %}暂无摘要。{% endif %}</div>
                </article>
            {% endfor %}
        </section>
    {% else %}
        <section class="panel grid-empty-state">当前栏目暂无已发布内容。</section>
    {% endif %}
</div>
{% include "foot" %}
