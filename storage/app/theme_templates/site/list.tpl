{% include "head" %}
{{ themeStyle path="theme.css" }}
{{ themeStyle path="list.css" }}
{% include "top" %}
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
