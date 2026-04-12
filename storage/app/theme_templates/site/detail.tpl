{% include "head" %}
{{ themeStyle path="theme.css" }}
{{ themeStyle path="detail.css" }}
{% include "top" %}
<div class="article-wrap">
    <article class="panel article-panel">
        <h1 class="article-title">{{ article.title }}{% if article.is_recommend %}<span class="title-mark">精华</span>{% endif %}</h1>
        <div class="article-meta">{{ article.channel_name }} · {{ article.published_at }}</div>
        <div class="article-content">
            {% if article.content_html %}
                {{{ article.content_html }}}
            {% else %}
                暂无正文内容。
            {% endif %}
        </div>

        {% if attachments %}
            <h2 class="article-section-title">附件下载</h2>
            <div class="link-list">
                {% for attachment in attachments %}
                    <a class="link-item" href="{{ attachment.url }}" target="_blank">
                        <span>{{ attachment.origin_name }}</span>
                        <span>{{ attachment.extension_upper }} / {{ attachment.size_kb }} KB</span>
                    </a>
                {% endfor %}
            </div>
        {% endif %}
    </article>
</div>
{% include "foot" %}
