{% include "head" %}
{{ themeStyle(path='list.css') }}
{% include 'tag-list-top' %}

  <main class="main main-list">
    <div class="container">
      <div class="main-layout">
        <article class="detail-main">
          <a href="#" class="detail-back-btn has-tooltip" data-history-back data-tooltip="返回上一页" aria-label="返回上一页">
            <svg width="14" height="14" viewBox="0 0 48 48" fill="none" stroke="currentColor" aria-hidden="true">
              <path d="M12.9998 8L6 14L12.9998 21" stroke-linecap="round" stroke-linejoin="round" stroke-width="4"/>
              <path d="M6 14H28.9938C35.8768 14 41.7221 19.6204 41.9904 26.5C42.2739 33.7696 36.2671 40 28.9938 40H11.9984" stroke-linecap="round" stroke-linejoin="round" stroke-width="4"/>
            </svg></a>
          <h1 class="detail-title{% if article.title_bold %} is-bold{% endif %}{% if article.title_italic %} is-italic{% endif %}">
            {% if article.title_color %}
              <font color="{{ article.title_color }}">{{ article.title }}</font>
            {% else %}
              {{ article.title }}
            {% endif %}
          </h1>
          <div class="detail-meta-row">
            {% if article.is_top %}
              <span class="news-category news-category-icon has-tooltip important" data-tooltip="置顶" aria-label="置顶">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 16.5L12 10.5L18 16.5"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6.5H18"/>
                </svg>
              </span>
            {% endif %}
            {% if article.is_recommend %}
              <span class="news-category news-category-icon has-tooltip notice" data-tooltip="精华" aria-label="精华">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2l2.9 6 6.6.9-4.8 4.6 1.2 6.5-5.9-3.2-5.9 3.2 1.2-6.5L2.5 8.9 9.1 8 12 2z"/>
                </svg>
              </span>
            {% endif %}
            <span class="news-date">{{ article.published_at | formatDate('Y-m-d', '--') }}</span>
            <span class="news-stat">{{ article.channel_name | default('未分类') }}</span>
          </div>
          <div class="detail-content">
            {% if article.content_html %}
              {{{ article.content_html }}}
            {% else %}
              <p>{{ article.summary | plainText() | default('暂无正文内容') }}</p>
            {% endif %}
          </div>
        </article>
        {% include 'tag-list-right' %}
      </div>
    </div>
  </main>

  <script src="{{ themeAsset(path='list.js') }}"></script>
</body>
</html>
