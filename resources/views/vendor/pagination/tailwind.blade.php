@if ($paginator->hasPages())
    <nav role="navigation" aria-label="分页导航">
        <div class="pagination-shell">
            @if ($paginator->onFirstPage())
                <span class="pagination-button is-disabled" aria-disabled="true" aria-label="上一页">
                    <svg class="pagination-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M9.5 3.5 5 8l4.5 4.5"/></svg>
                    <span>上一页</span>
                </span>
            @else
                <a class="pagination-button" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="上一页">
                    <svg class="pagination-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M9.5 3.5 5 8l4.5 4.5"/></svg>
                    <span>上一页</span>
                </a>
            @endif

            <div class="pagination-pages">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="pagination-ellipsis" aria-disabled="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="pagination-page is-active" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="pagination-page" href="{{ $url }}" aria-label="第 {{ $page }} 页">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            @if ($paginator->hasMorePages())
                <a class="pagination-button" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="下一页">
                    <span>下一页</span>
                    <svg class="pagination-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M6.5 3.5 11 8l-4.5 4.5"/></svg>
                </a>
            @else
                <span class="pagination-button is-disabled" aria-disabled="true" aria-label="下一页">
                    <span>下一页</span>
                    <svg class="pagination-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M6.5 3.5 11 8l-4.5 4.5"/></svg>
                </span>
            @endif
        </div>
    </nav>
@endif
