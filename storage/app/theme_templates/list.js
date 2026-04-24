/* 新闻列表页脚本：仅处理服务端关键词搜索 */
(function () {
  'use strict';

  function resolveSearchUrl(searchInput) {
    var pageType = (searchInput && searchInput.dataset && searchInput.dataset.pageType) || '';
    if (pageType === 'article' || pageType === 'page') {
      var allContentLink = document.querySelector('.sidebar-tree > .sidebar-link');
      if (allContentLink && allContentLink.getAttribute('href')) {
        return new URL(allContentLink.getAttribute('href'), window.location.origin);
      }
    }

    return new URL(window.location.href);
  }

  function submitKeywordSearch(input, searchInput) {
    var keyword = (input || '').slice(0, 64).trim();
    var url = resolveSearchUrl(searchInput);
    var currentKeyword = (url.searchParams.get('keyword') || '').trim();

    if (keyword === currentKeyword) {
      return;
    }

    if (keyword) {
      url.searchParams.set('keyword', keyword);
    } else {
      url.searchParams.delete('keyword');
    }

    url.searchParams.delete('page');
    window.location.href = url.toString();
  }

  function initSearch() {
    var searchInput = document.querySelector('[data-search-input]');
    if (!searchInput) {
      return;
    }

    var searchSubmit = document.querySelector('[data-search-submit]');

    searchInput.addEventListener('keydown', function (event) {
      if (event.key !== 'Enter') {
        return;
      }
      event.preventDefault();
      submitKeywordSearch(searchInput.value, searchInput);
    });

    if (!searchSubmit) {
      return;
    }

    searchSubmit.addEventListener('click', function () {
      submitKeywordSearch(searchInput.value, searchInput);
    });
  }

  function initHistoryBack() {
    var backLink = document.querySelector('[data-history-back]');
    if (!backLink) {
      return;
    }

    backLink.addEventListener('click', function (event) {
      event.preventDefault();
      window.history.back();
    });
  }

  function initPage() {
    initSearch();
    initHistoryBack();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPage);
  } else {
    initPage();
  }
})();
