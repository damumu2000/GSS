<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $batch->month_key)->format('Y年n月') }} {{ $sheetType === 'salary' ? '工资条' : '绩效' }}详情 - {{ $site->name }}</title>
    <style>
        :root {
            --primary: #4da2ff;
            --bg: #eaf4ff;
            --line: rgba(116,151,191,0.18);
            --text: #1f2937;
            --muted: #8a94a6;
            --panel: rgba(255,255,255,0.84);
        }
        * { box-sizing: border-box; }
        body { margin:0; background: linear-gradient(180deg, #eff7ff 0%, #e8f2ff 100%); color: var(--text); font-family:"PingFang SC","Microsoft YaHei",sans-serif; }
        a { color: inherit; text-decoration: none; }
        .shell { width:min(440px,calc(100% - 24px)); margin:0 auto; min-height:100vh; padding:14px 0 28px; }
        .topbar { text-align:center; color:#111827; font-size:22px; font-weight:700; }
        .subbar { margin-top:4px; text-align:center; color:var(--muted); font-size:12px; }
        .panel {
            margin-top:18px;
            padding:20px 18px;
            border-radius:22px;
            border:1px solid var(--line);
            background:var(--panel);
            box-shadow:0 18px 36px rgba(37,119,216,0.08);
            backdrop-filter:blur(10px);
        }
        .headline {
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:12px;
        }
        .title { margin:0; color:#111827; font-size:22px; font-weight:700; line-height:1.5; }
        .back-link { color:#374151; font-size:14px; font-weight:700; white-space:nowrap; }
        .table-head,
        .row {
            display:grid;
            grid-template-columns: minmax(0, 1fr) 110px;
            align-items:center;
        }
        .table-head {
            margin-top:18px;
            min-height:44px;
            padding:0 14px;
            border-radius:0;
            background:var(--primary);
            color:#fff;
            font-size:14px;
            font-weight:700;
        }
        .row {
            min-height:52px;
            padding:0 14px;
            background:rgba(255,255,255,0.72);
            border-bottom:1px solid rgba(116,151,191,0.18);
        }
        .row:last-child { border-bottom:none; }
        .label-cell { color:#334155; font-size:15px; line-height:1.7; }
        .value-cell { text-align:right; color:#111827; font-size:15px; font-weight:600; }
    </style>
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
                <span style="text-align:right;">金额（元）</span>
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
