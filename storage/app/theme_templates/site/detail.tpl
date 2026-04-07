{% include "top" %}
<style>
    .article-wrap { width: min(980px, calc(100% - 40px)); margin: 0 auto; }
    .article-panel { padding: 30px; }
    .article-title { margin: 0 0 12px; font-size: clamp(32px, 4vw, 42px); line-height: 1.28; }
    .title-mark {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 26px;
        height: 26px;
        margin-left: 12px;
        padding: 0 9px;
        border-radius: 999px;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
        vertical-align: middle;
        box-shadow: 0 10px 20px rgba(217, 119, 6, 0.18);
    }
    .article-meta { color: var(--muted); font-size: 14px; margin-bottom: 22px; }
    .article-content { line-height: 1.95; color: #334942; }
    .article-content img { max-width: 100%; height: auto; border-radius: 16px; }
    .article-content figure { margin: 24px 0; }
    .article-content figcaption { margin-top: 10px; text-align: center; color: var(--muted); font-size: 14px; }
    .article-content table { width: 100%; border-collapse: collapse; margin: 18px 0; }
    .article-content th, .article-content td { border: 1px solid #dce8e2; padding: 10px 12px; text-align: left; }
    .article-content blockquote { margin: 18px 0; padding: 14px 18px; background: #f3f8f5; border-left: 4px solid #9db9ae; border-radius: 12px; }
    .article-content a { color: var(--primary); }
    .article-section-title { margin: 28px 0 14px; font-size: 18px; color: var(--primary-deep); }
    .link-list { display: grid; gap: 10px; }
    .link-item {
        display: flex; justify-content: space-between; gap: 12px; align-items: center; padding: 14px 16px;
        background: #f7fbf9; border-radius: 18px; border: 1px solid rgba(29, 106, 94, 0.08); color: #4c6760;
    }
</style>
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
