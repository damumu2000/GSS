</head>
<body>
    <header class="site-header">
        <div class="container site-header-inner">
            <a class="brand" href="/site-preview?site={{ site.site_key }}">
                <span class="brand-logo">
                    {% if site.logo %}
                        <img src="{{ site.logo }}" alt="{{ site.name }}">
                    {% else %}
                        校
                    {% endif %}
                </span>
                <span>
                    <span class="brand-title">{{ site.name }}</span>
                    <span class="brand-subtitle">{% if site.address %}{{ site.address }}{% else %}面向学校官网建设的内容展示模板{% endif %}</span>
                </span>
            </a>

            <nav class="site-nav">
                {% for navItem in navItems %}
                    {% if activeChannelSlug == navItem.slug %}
                        <a class="is-active" href="{{ navItem.url }}"{% if navItem.target == '_blank' %} target="_blank" rel="noopener noreferrer"{% endif %}>{{ navItem.name }}</a>
                    {% else %}
                        <a href="{{ navItem.url }}"{% if navItem.target == '_blank' %} target="_blank" rel="noopener noreferrer"{% endif %}>{{ navItem.name }}</a>
                    {% endif %}
                {% endfor %}
            </nav>
        </div>
    </header>

    <main class="theme-main">
