<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>工资信息列表 - {{ $site->name }}</title>
    <style>
        :root {
            --primary: #4da2ff;
            --primary-deep: #2577d8;
            --bg: #eaf4ff;
            --panel: rgba(255, 255, 255, 0.84);
            --line: rgba(116, 151, 191, 0.18);
            --text: #1f2937;
            --muted: #8a94a6;
            --danger: #c62828;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: linear-gradient(180deg, #eff7ff 0%, #e8f2ff 100%);
            color: var(--text);
            font-family: "PingFang SC","Microsoft YaHei",sans-serif;
        }
        a { color: inherit; text-decoration: none; }
        button { font: inherit; }
        .shell {
            width: min(440px, calc(100% - 24px));
            margin: 0 auto;
            min-height: 100vh;
            padding: 14px 0 28px;
        }
        .topbar {
            text-align: center;
            color: #111827;
            font-size: 22px;
            font-weight: 700;
        }
        .subbar {
            margin-top: 4px;
            text-align: center;
            color: var(--muted);
            font-size: 12px;
        }
        .panel {
            margin-top: 18px;
            padding: 18px;
            border-radius: 22px;
            border: 1px solid var(--line);
            background: var(--panel);
            box-shadow: 0 18px 36px rgba(37, 119, 216, 0.08);
            backdrop-filter: blur(10px);
        }
        .greeting {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
        }
        .greeting-name { color: #111827; font-size: 16px; font-weight: 700; }
        .greeting-actions {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .greeting-link {
            color: var(--danger);
            font-size: 13px;
            font-weight: 700;
            background: transparent;
            border: none;
            padding: 0;
            cursor: pointer;
        }
        .section-title {
            margin-top: 18px;
            color: #111827;
            font-size: 20px;
            font-weight: 700;
        }
        .month-head {
            margin-top: 14px;
            padding: 0 14px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 78px 78px;
            align-items: center;
            min-height: 44px;
            border-radius: 14px;
            background: var(--primary);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
        }
        .month-row {
            margin-top: 12px;
            padding: 0 14px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 78px 78px;
            align-items: center;
            min-height: 62px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(77, 162, 255, 0.10);
        }
        .month-name { color: #334155; font-size: 15px; font-weight: 600; }
        .month-link {
            text-align: center;
            color: var(--danger);
            font-size: 14px;
            font-weight: 700;
        }
        .month-link.is-disabled { color: #c0c7d4; pointer-events: none; }
        .empty-state {
            margin-top: 14px;
            padding: 28px 18px;
            border-radius: 18px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.9;
            background: rgba(255, 255, 255, 0.76);
            border: 1px dashed rgba(116, 151, 191, 0.28);
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="topbar">薪资信息列表</div>
        <div class="subbar">{{ $site->name }}</div>

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
                <span>请选择时间</span>
                <span style="text-align:center;">工资条</span>
                <span style="text-align:center;">绩效</span>
            </div>

            @forelse ($batches as $batch)
                <div class="month-row">
                    <span class="month-name">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $batch['month_key'])->format('Y年n月') }}</span>
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
        </section>
    </div>
</body>
</html>
