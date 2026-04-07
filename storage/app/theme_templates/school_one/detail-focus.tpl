{% include "top" %}
<style>
    .detail-layout { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 22px; }
    .detail-paper, .detail-side { padding: 28px; }
    .detail-title { margin: 0 0 14px; color: var(--primary); font-size: clamp(32px, 4vw, 44px); line-height: 1.28; }
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
    .detail-meta { display: flex; flex-wrap: wrap; gap: 10px 18px; color: var(--muted); font-size: 14px; margin-bottom: 22px; }
    .detail-content { line-height: 1.95; color: #374151; font-size: 16px; }
    .detail-content img { max-width: 100%; height: auto; border-radius: 8px; }
    .detail-content figure { margin: 28px 0; }
    .detail-content figcaption { margin-top: 12px; text-align: center; color: var(--muted); font-size: 14px; }
    .detail-content table { width: 100%; border-collapse: collapse; margin: 18px 0; }
    .detail-content th, .detail-content td { border: 1px solid #dbe3ec; padding: 10px 12px; text-align: left; }
    .detail-content blockquote { margin: 18px 0; padding: 14px 18px; background: #f7f9fc; border-left: 4px solid #9ab0c8; border-radius: 8px; }
    .detail-content a { color: var(--primary); }
    .detail-section-title { margin: 32px 0 14px; font-size: 18px; color: var(--primary); }
    .detail-links { display: grid; gap: 10px; }
    .detail-link {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        padding: 14px 16px;
        background: #f8fbff;
        border-radius: 8px;
        border: 1px solid #e5ebf2;
        color: #4b5563;
    }
    .detail-side-title { margin: 0 0 14px; font-size: 18px; color: var(--primary); }
    .detail-site-card {
        padding: 18px;
        border-radius: 8px;
        background: #f8fbff;
        border: 1px solid #e5ebf2;
    }
    .detail-site-card strong { display: block; margin-bottom: 8px; font-size: 20px; }
    .detail-site-card div { color: var(--muted); line-height: 1.8; font-size: 14px; }
    @media (max-width: 960px) { .detail-layout { grid-template-columns: 1fr; } }
</style>
<div class="container detail-layout">
    <article class="panel detail-paper">
        <h1 class="detail-title">{{ article.title }}{% if article.is_recommend %}<span class="title-mark">推荐</span>{% endif %}</h1>
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
                <a class="detail-link" href="{{ previousArticle.url }}"><span>上一篇</span><span>{{ previousArticle.title }}</span></a>
            {% else %}
                <div class="detail-link"><span>上一篇</span><span>没有了</span></div>
            {% endif %}

            {% if nextArticle %}
                <a class="detail-link" href="{{ nextArticle.url }}"><span>下一篇</span><span>{{ nextArticle.title }}</span></a>
            {% else %}
                <div class="detail-link"><span>下一篇</span><span>没有了</span></div>
            {% endif %}
        </div>

        {% if relatedArticles %}
            <h2 class="detail-section-title">相关推荐</h2>
            <div class="detail-links">
                {% for relatedArticle in relatedArticles %}
                    <a class="detail-link" href="{{ relatedArticle.url }}">
                        <span>{{ relatedArticle.title }}</span>
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
            <div style="margin-top: 8px;">{% if site.contact_phone %}{{ site.contact_phone }}{% else %}联系电话待设置{% endif %}</div>
            <div>{% if site.contact_email %}{{ site.contact_email }}{% else %}联系邮箱待设置{% endif %}</div>
            <div>{% if site.filing_number %}{{ site.filing_number }}{% else %}备案号待设置{% endif %}</div>
        </div>
    </aside>
</div>
{% include "foot" %}
