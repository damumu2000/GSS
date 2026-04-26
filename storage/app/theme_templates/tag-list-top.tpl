</head>
<body class="site-list-shell page-{{ current.page.type }}">
<!-- 头部导航 -->
  <header class="header">
    <div class="container">
      <div class="header-inner">
        <a href="{{ siteValue(key='home_url', default='/') }}" class="header-logo">
          <img class="header-logo-img" src="{{ siteValue(key='logo') }}" alt="{{ siteValue(key='name', default='官方网站') }}">
          <span class="logo-tooltip">返回首页</span>
        </a>
        <div class="header-right">
          <a href="{{ siteValue(key='home_url', default='/') }}" class="header-back">
            返回首页
          </a>
          <div class="search-box">
            <input id="site-keyword-search" name="keyword" type="text" class="search-input" placeholder="搜索" value="{{ current.page.keyword }}" data-search-input data-page-type="{{ current.page.type }}" autocomplete="off">
            <button type="button" class="search-submit" data-search-submit aria-label="搜索">
              <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </header>
