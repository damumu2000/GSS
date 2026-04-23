{% include "head" %}
{{ themeStyle(path='theme.css') }}
{{ themeStyle(path='detail-focus.css') }}
{% include "top" %}
<div class="container detail-layout">
    <article class="panel detail-paper">
        <h1 class="detail-title">{{ article.title }}{% if article.is_recommend %}<span class="title-mark">精华</span>{% endif %}</h1>
        <div class="detail-meta">
            <span>栏目：{{ article.channel_name }}</span>
            <span>发布时间：{{ article.published_at }}</span>
            <span>作者：{{ article.author }}</span>
        </div>

        <div class="detail-content">
            {% if article.content_html %}
                {{{ article.content_html }}}
            {% else %}
                {% if article.summary %}{{ article.summary }}{% else %}暂无内容。{% endif %}
            {% endif %}
        </div>

        {% if attachments %}
            <h2 class="detail-section-title">附件下载</h2>
            <div class="detail-links">
                {% for attachment in attachments %}
                    <a class="detail-link" href="{{ attachment.url }}" target="_blank">
                        <span>{{ attachment.origin_name }}</span>
                        <span>{{ attachment.extension_upper }} / {{ attachment.size_kb }} KB</span>
                    </a>
                {% endfor %}
            </div>
        {% endif %}

        <h2 class="detail-section-title">上下篇</h2>
        <div class="detail-links">
            {% if previousArticle %}
                <a class="detail-link" href="{{ previousArticle.url }}"><span>上一篇</span><span>{{ previousArticle.title }}{% if previousArticle.is_recommend %}<span class="title-mark">精</span>{% endif %}</span></a>
            {% else %}
                <div class="detail-link"><span>上一篇</span><span>没有了</span></div>
            {% endif %}

            {% if nextArticle %}
                <a class="detail-link" href="{{ nextArticle.url }}"><span>下一篇</span><span>{{ nextArticle.title }}{% if nextArticle.is_recommend %}<span class="title-mark">精</span>{% endif %}</span></a>
            {% else %}
                <div class="detail-link"><span>下一篇</span><span>没有了</span></div>
            {% endif %}
        </div>

        {% if relatedArticles %}
            <h2 class="detail-section-title">相关推荐</h2>
            <div class="detail-links">
                {% for relatedArticle in relatedArticles %}
                    <a class="detail-link" href="{{ relatedArticle.url }}">
                        <span>{{ relatedArticle.title }}{% if relatedArticle.is_recommend %}<span class="title-mark">精</span>{% endif %}</span>
                        <span>{{ relatedArticle.published_at }}</span>
                    </a>
                {% endfor %}
            </div>
        {% endif %}
    </article>

    <aside class="panel detail-side">
        <h2 class="detail-side-title">站点信息</h2>
        <div class="detail-site-card">
            <strong>{{ site.name }}</strong>
            <div>{% if site.address %}{{ site.address }}{% else %}学校地址待设置{% endif %}</div>
            <div class="detail-site-card-text">{% if site.contact_phone %}{{ site.contact_phone }}{% else %}联系电话待设置{% endif %}</div>
            <div>{% if site.contact_email %}{{ site.contact_email }}{% else %}联系邮箱待设置{% endif %}</div>
            <div>{% if site.filing_number %}{{ site.filing_number }}{% else %}备案号待设置{% endif %}</div>
        </div>
    </aside>
</div>
{% include "foot" %}
