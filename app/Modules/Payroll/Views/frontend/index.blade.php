<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>工资信息列表 - {{ $site->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/payroll-frontend-index.css') }}">
</head>
<body>
    <div class="shell">
        <section class="panel">
            <div class="greeting">
                <div class="greeting-name">{{ $employee->name }}，你好！</div>
                <div class="greeting-actions">
                    <a class="greeting-link" href="{{ route('site.payroll.password.manage', $siteQuery) }}">密码管理</a>
                    <form method="POST" action="{{ route('site.payroll.logout', $siteQuery) }}">
                        @csrf
                        <button class="greeting-link" type="submit">安全退出</button>
                    </form>
                </div>
            </div>

            <div class="section-title">我的薪资信息列表</div>

            <div class="month-head">
                <span>时间</span>
                <span class="month-head-cell">工资条</span>
                <span class="month-head-cell">绩效</span>
            </div>

            @forelse ($batches as $batch)
                <div class="month-row">
                    <span class="month-name">{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $batch['month_key'])->format('Y年m月') }}</span>
                    @if ($batch['has_salary'])
                        <a class="month-link" href="{{ route('site.payroll.show', ['batch' => $batch['batch_id'], 'type' => 'salary'] + $siteQuery) }}">工资条</a>
                    @else
                        <span class="month-link is-disabled">暂无</span>
                    @endif

                    @if ($batch['has_performance'])
                        <a class="month-link" href="{{ route('site.payroll.show', ['batch' => $batch['batch_id'], 'type' => 'performance'] + $siteQuery) }}">绩效</a>
                    @else
                        <span class="month-link is-disabled">暂无</span>
                    @endif
                </div>
            @empty
                <div class="empty-state">暂无您的工资信息。<br>如您刚完成登记，请等待管理员审核并导入对应月份工资表后再查看。</div>
            @endforelse

            @if ($batches->hasPages())
                @php($pager = $batches->appends($siteQuery))
                <div class="payroll-pagination">
                    <nav class="payroll-pager" role="navigation" aria-label="月份分页导航">
                        @if ($pager->onFirstPage())
                            <span class="payroll-pager-button is-disabled" aria-disabled="true">首页</span>
                        @else
                            <a class="payroll-pager-button" href="{{ $pager->url(1) }}" rel="first">首页</a>
                        @endif

                        @if ($pager->onFirstPage())
                            <span class="payroll-pager-button is-disabled" aria-disabled="true">上一页</span>
                        @else
                            <a class="payroll-pager-button" href="{{ $pager->previousPageUrl() }}" rel="prev">上一页</a>
                        @endif

                        <span class="payroll-pager-status">{{ $pager->currentPage() }} / {{ $pager->lastPage() }}</span>

                        @if ($pager->hasMorePages())
                            <a class="payroll-pager-button" href="{{ $pager->nextPageUrl() }}" rel="next">下一页</a>
                        @else
                            <span class="payroll-pager-button is-disabled" aria-disabled="true">下一页</span>
                        @endif

                        @if ($pager->hasMorePages())
                            <a class="payroll-pager-button" href="{{ $pager->url($pager->lastPage()) }}" rel="last">尾页</a>
                        @else
                            <span class="payroll-pager-button is-disabled" aria-disabled="true">尾页</span>
                        @endif
                    </nav>
                </div>
            @endif
        </section>
    </div>
</body>
</html>
