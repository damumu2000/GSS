{% include "top" %}
<style>
    .page-wrap { width: min(980px, calc(100% - 40px)); margin: 0 auto; }
    .page-panel { padding: 30px; }
    .page-title { margin: 0 0 12px; font-size: clamp(32px, 4vw, 42px); line-height: 1.28; }
    .page-summary { color: var(--muted); margin-bottom: 22px; line-height: 1.85; font-size: 15px; }
    .page-content { line-height: 1.95; color: #334942; }
    .page-content img { max-width: 100%; height: auto; border-radius: 16px; }
    .page-content figure { margin: 24px 0; }
    .page-content figcaption { margin-top: 10px; text-align: center; color: var(--muted); font-size: 14px; }
    .page-content table { width: 100%; border-collapse: collapse; margin: 18px 0; }
    .page-content th, .page-content td { border: 1px solid #dce8e2; padding: 10px 12px; text-align: left; }
    .page-content blockquote { margin: 18px 0; padding: 14px 18px; background: #f3f8f5; border-left: 4px solid #9db9ae; border-radius: 12px; }
    .page-content a { color: var(--primary); }
</style>
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
