{% include "head" %}
{{ themeStyle(path='list.css') }}
{% include 'tag-list-top' %}

  <!-- 主内容区 -->
  <main class="main main-list">
    <div class="container">
      <div class="main-layout">

        <!-- 左侧新闻列表 -->
        <div class="news-list">
          {% set pageData = contentPage(type='article', per_page=5, page_name='page', window=2, order_by='published_at', order_dir='desc', keyword=current.page.keyword) %}
          {% set newsList = pageData.items %}
          {% set pager = pageData.pagination %}

          {% if newsList %}
            {% for item in newsList %}
              {% set shortSummary = item.summary | plainText() | default(item.content_html | plainText()) | truncate(70) | default('暂无内容') %}

              <a href="{{ item.url }}" class="news-card">
                <div class="news-card-content">
                  <div class="news-card-meta">
                    {% if item.is_top %}
                      <span class="news-category news-category-icon has-tooltip important" data-tooltip="置顶" aria-label="置顶">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 16.5L12 10.5L18 16.5"/>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6.5H18"/>
                        </svg>
                      </span>
                    {% endif %}
                    {% if item.is_recommend %}
                      <span class="news-category news-category-icon has-tooltip notice" data-tooltip="精华" aria-label="精华">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2l2.9 6 6.6.9-4.8 4.6 1.2 6.5-5.9-3.2-5.9 3.2 1.2-6.5L2.5 8.9 9.1 8 12 2z"/>
                        </svg>
                      </span>
                    {% endif %}
                    <span class="news-date">
                      {{ item.published_at | formatDate('Y-m-d', '--') }}
                    </span>
                  </div>
                  <h2 class="news-card-title{% if item.is_top %} is-top{% endif %}{% if item.title_bold %} is-bold{% endif %}{% if item.title_italic %} is-italic{% endif %}">
                    {% if item.title_color %}
                      <font color="{{ item.title_color }}">{{ item.title }}</font>
                    {% else %}
                      {{ item.title }}
                    {% endif %}
                  </h2>
                  <p class="news-card-summary">{{ shortSummary }}</p>
                  <div class="news-card-footer">
                    <span class="news-stat">{{ item.channel_name | default(current.channel.name) }}</span>
                  </div>
                </div>
                {% if item.cover_image %}
                  <div class="news-card-image">
                    <img src="{{ item.cover_image }}" alt="{{ item.title }}" width="220" height="150" loading="lazy" decoding="async">
                  </div>
                {% endif %}
              </a>
            {% endfor %}
          {% else %}
            <div class="search-empty">
              <div class="search-empty-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
              </div>
              <p class="search-empty-text">暂无新闻内容</p>
            </div>
          {% endif %}

          <!-- 分页 -->
          {% if pager %}
            <div class="pagination">
              {% if pager.has_prev %}
                {% if pager.prev_url %}
                  <a href="{{ pager.prev_url }}" class="pagination-btn" aria-label="上一页">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                  </a>
                {% else %}
                  <span class="pagination-btn" aria-disabled="true">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                  </span>
                {% endif %}
              {% else %}
                <span class="pagination-btn" aria-disabled="true">
                  <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                  </svg>
                </span>
              {% endif %}

              {% for p in pager.pages %}
                {% if p.type == 'ellipsis' %}
                  <span class="pagination-ellipsis">{{ p.label }}</span>
                {% else %}
                  <a href="{{ p.url }}" class="pagination-btn{% if p.is_current %} active{% endif %}">{{ p.label }}</a>
                {% endif %}
              {% endfor %}

              {% if pager.has_next %}
                {% if pager.next_url %}
                  <a href="{{ pager.next_url }}" class="pagination-btn" aria-label="下一页">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                  </a>
                {% else %}
                  <span class="pagination-btn" aria-disabled="true">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                  </span>
                {% endif %}
              {% else %}
                <span class="pagination-btn" aria-disabled="true">
                  <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </span>
              {% endif %}
            </div>
          {% endif %}

        </div>

        <!-- 右侧边栏 -->
        {% include 'tag-list-right' %}

      </div>
    </div>
  </main>


  <script src="{{ themeAsset(path='list.js') }}"></script>
</body>
</html>
