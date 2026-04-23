{% include "head" %}
{{ themeStyle(path='theme.css') }}
{{ themeStyle(path='page.css') }}
{% include "top" %}
<div class="page-wrap">
    <article class="panel page-panel">
        <h1 class="page-title">{{ page.title }}</h1>
        <div class="page-summary">{{ page.channel_name }}</div>
        <div class="page-content">
            {% if page.content_html %}
                {{{ page.content_html }}}
            {% else %}
                {% if page.summary %}{{ page.summary }}{% else %}暂无页面内容。{% endif %}
            {% endif %}
        </div>
    </article>
</div>
{% include "foot" %}
