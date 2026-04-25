{% include "head" %}
{{ themeStyle(path='list.css') }}
{% include 'tag-list-top' %}

  <main class="main main-list">
    <div class="container">
      <div class="main-layout">
        <section class="detail-main">
          <div class="detail-meta-row">
            <span class="news-category notice">站点已开通</span>
            <span class="news-date">模板可继续完善</span>
          </div>

          <h1 class="detail-title">站点已经准备好了，接下来可以开始整理首页内容。</h1>

          <div class="detail-content">
            <p>当前站点已经开通，默认模板文件也已准备好。你可以先从首页开始调整，也可以直接参考已有页面，把栏目、文章和单页内容逐步接上。</p>
            <p>如果需要修改模板结构，建议先看一遍 <a href="/docs/theme-template-development-guide.html" target="_blank" rel="noopener">模版开发文档</a>。文档里整理了模板标签、常用写法和保存校验规则，照着改会稳妥很多。</p>
            <p>目前默认模板已经包含列表页、文章详情页和单页详情页，可以作为制作新页面的参考。先小范围调整，确认前台展示正常后，再继续扩展更多版块。</p>
          </div>
        </section>

        <aside class="sidebar">
          <div class="sidebar-card">
            <h2 class="sidebar-title">编辑建议</h2>
            <div class="sidebar-nav">
              <a class="sidebar-link" href="/docs/theme-template-development-guide.html" target="_blank" rel="noopener">查看模版开发文档</a>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </main>

  <script src="{{ themeAsset(path='list.js') }}"></script>
</body>
</html>