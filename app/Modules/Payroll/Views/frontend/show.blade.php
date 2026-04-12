<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $batch->month_key)->format('Y年n月') }} {{ $sheetType === 'salary' ? '工资条' : '绩效' }}详情 - {{ $site->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/payroll-frontend-show.css') }}">
</head>
<body>
    <div class="shell">
        <div class="topbar">工资信息</div>
        <div class="subbar">{{ $site->name }}</div>

        <section class="panel">
            <div class="headline">
                <h1 class="title">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $batch->month_key)->format('Y年n月') }} {{ $sheetType === 'salary' ? '工资条' : '绩效' }}详情</h1>
                <a class="back-link" href="{{ route('site.payroll.index', $siteQuery) }}">返回列表</a>
            </div>

            <div class="table-head">
                <span>项目名称</span>
                <span class="table-head-value">金额（元）</span>
            </div>

            @foreach ($items as $item)
                <div class="row">
                    <span class="label-cell">{{ $item['label'] ?? '' }}</span>
                    <span class="value-cell">{{ $item['value'] ?? '' }}</span>
                </div>
            @endforeach
        </section>
    </div>
</body>
</html>
