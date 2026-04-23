{% include "head" %}
{{ themeStyle(path='theme.css') }}
{{ themeStyle(path='page-clean.css') }}
{% include "top" %}
<div class="container page-layout">
    <section class="panel page-panel">
        <span class="page-tag">{{ channel.name }}</span>
        <h1 class="page-title">{{ page.title }}</h1>
        {% if page.summary %}
            <div class="page-summary">{{ page.summary }}</div>
        {% endif %}
        <div class="page-content">
            {% if page.content_html %}
                {{{ page.content_html }}}
            {% else %}
                暂无内容。
            {% endif %}
        </div>
    </section>

    <aside class="panel page-side">
        <h2 class="page-side-title">页面信息</h2>
        <div class="page-side-card">
            <strong>{{ site.name }}</strong>
            <div>{{ channel.name }}</div>
            <div class="page-side-card-text">{% if site.address %}{{ site.address }}{% else %}学校地址待设置{% endif %}</div>
            <div>{% if site.contact_phone %}{{ site.contact_phone }}{% else %}联系电话待设置{% endif %}</div>
            <div>{% if site.filing_number %}{{ site.filing_number }}{% else %}备案号待设置{% endif %}</div>
        </div>
    </aside>
</div>
{% include "foot" %}
