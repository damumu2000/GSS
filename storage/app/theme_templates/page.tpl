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
          <h1 class="detail-title detail-title-center{% if page.title_bold %} is-bold{% endif %}{% if page.title_italic %} is-italic{% endif %}">
            {% if page.title_color %}
              <font color="{{ page.title_color }}">{{ page.title }}</font>
            {% else %}
              {{ page.title }}
            {% endif %}
          </h1>

          <div class="detail-content">
            {% if page.content_html %}
              {{{ page.content_html }}}
            {% else %}
              <p>{{ page.summary | plainText() | default('暂无页面内容') }}</p>
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
