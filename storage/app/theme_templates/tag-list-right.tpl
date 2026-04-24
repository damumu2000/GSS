<aside class="sidebar">
  <div class="sidebar-card">
    <nav class="sidebar-nav sidebar-tree">
      {% set rootChannels = channels(type='list', status=1, parent_id=null, limit=20) %}
      {% set firstListChannel = first(value=rootChannels) %}
      {% if firstListChannel %}
        <a href="/cat/{{ firstListChannel.slug }}?site={{ site.site_key }}&all=1" class="sidebar-link{% if current.page.show_all %} active{% endif %}">全部内容</a>
      {% else %}
        <a href="/?site={{ site.site_key }}" class="sidebar-link{% if current.page.show_all %} active{% endif %}">全部内容</a>
      {% endif %}
      {% if rootChannels %}
        {% for root in rootChannels %}
          <a href="{{ root.url }}" class="sidebar-link{% if not current.page.show_all %}{% if root.is_active %} active{% endif %}{% endif %}">{{ root.name }}</a>
          {% set childChannels = children(channel_id=root.id, limit=20) %}
          {% if childChannels %}
            <div class="sidebar-tree-children">
              {% for child in childChannels %}
                <a href="{{ child.url }}" class="sidebar-link sidebar-link-child{% if not current.page.show_all %}{% if child.is_active %} active{% endif %}{% endif %}">{{ child.name }}</a>
              {% endfor %}
            </div>
          {% endif %}
        {% endfor %}
      {% endif %}
    </nav>
  </div>

  <div class="sidebar-card">
    <h3 class="sidebar-title">精华内容</h3>
    <nav class="sidebar-nav">
      {% set essenceList = contentList(type='article', is_recommend=true, channel_id=current.channel.id, limit=5, order_by='published_at', order_dir='desc') %}
      {% if essenceList %}
        {% for item in essenceList %}
          <a href="{{ item.url }}" class="sidebar-link sidebar-link-essence">
            <span class="essence-title">{{ item.title }}</span>
          </a>
        {% endfor %}
      {% else %}
        {% set siteEssenceList = contentList(type='article', is_recommend=true, channel_id=0, limit=5, order_by='published_at', order_dir='desc') %}
        {% if siteEssenceList %}
          {% for item in siteEssenceList %}
            <a href="{{ item.url }}" class="sidebar-link sidebar-link-essence">
              <span class="essence-title">{{ item.title }}</span>
            </a>
          {% endfor %}
        {% else %}
          <span class="sidebar-link">暂无精华内容</span>
        {% endif %}
      {% endif %}
    </nav>
  </div>

  <div class="sidebar-copyright">
    © 2026 {{ siteValue(key='name', default='--') }} · <a href="">联系我们</a>
  </div>
</aside>
