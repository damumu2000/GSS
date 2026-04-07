{% include "top" %}
<style>
    .page-layout { display: grid; grid-template-columns: minmax(0, 1fr) 290px; gap: 22px; }
    .page-panel, .page-side { padding: 30px; }
    .page-tag { display: inline-flex; align-items: center; padding: 7px 12px; border-radius: 999px; background: var(--primary-soft); color: var(--primary-deep); font-size: 12px; }
    .page-title { margin: 18px 0 12px; font-size: clamp(32px, 4vw, 42px); line-height: 1.25; }
    .page-summary { color: var(--muted); margin-bottom: 22px; line-height: 1.85; font-size: 15px; }
    .page-content { line-height: 1.95; color: #324943; }
    .page-content img { max-width: 100%; height: auto; border-radius: 18px; }
    .page-content figure { margin: 24px 0; }
    .page-content figcaption { margin-top: 10px; text-align: center; color: var(--muted); font-size: 14px; }
    .page-content table { width: 100%; border-collapse: collapse; margin: 18px 0; }
    .page-content th, .page-content td { border: 1px solid #dce7e2; padding: 10px 12px; text-align: left; }
    .page-content blockquote { margin: 18px 0; padding: 14px 18px; background: #f2f8f5; border-left: 4px solid #9eb8ae; border-radius: 12px; }
    .page-content a { color: var(--primary); }
    .page-side-title { margin: 0 0 14px; font-size: 18px; color: var(--primary-deep); }
    .page-side-card { padding: 18px; border-radius: 22px; background: linear-gradient(135deg, #f5faf7, #e8f2ec); border: 1px solid rgba(29, 106, 94, 0.08); }
    .page-side-card strong { display: block; font-size: 20px; margin-bottom: 8px; }
    .page-side-card div { color: var(--muted); line-height: 1.8; font-size: 14px; }
    @media (max-width: 960px) { .page-layout { grid-template-columns: 1fr; } }
</style>
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
            <div style="margin-top: 8px;">{% if site.address %}{{ site.address }}{% else %}学校地址待设置{% endif %}</div>
            <div>{% if site.contact_phone %}{{ site.contact_phone }}{% else %}联系电话待设置{% endif %}</div>
            <div>{% if site.filing_number %}{{ site.filing_number }}{% else %}备案号待设置{% endif %}</div>
        </div>
    </aside>
</div>
{% include "foot" %}
