@extends('layouts.admin')

@section('title', '工资查询使用帮助 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 工资查询使用帮助')

@push('styles')
    <style>
        .payroll-help-header {
            padding: 24px 32px 18px;
            margin: -28px -28px 18px;
            background: #fff;
            border-bottom: 1px solid #f0f0f0;
        }
        .payroll-help-title { margin: 0; color: #262626; font-size: 20px; line-height: 1.4; font-weight: 700; }
        .payroll-help-desc {
            max-width: 680px;
            margin-top: 8px;
            color: #8c8c8c;
            font-size: 14px;
            line-height: 1.75;
        }
        .payroll-help-shell {
            display: grid;
            gap: 14px;
            margin-top: 18px;
        }
        .payroll-help-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }
        .payroll-help-card {
            padding: 20px;
            border: 1px solid #edf1f5;
            border-radius: 14px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }
        .payroll-help-kicker {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 0 10px;
            border-radius: 999px;
            background: #f3f6fa;
            color: #667085;
            font-size: 12px;
            font-weight: 700;
            line-height: 24px;
        }
        .payroll-help-section-title {
            margin: 12px 0 8px;
            color: #1f2937;
            font-size: 18px;
            line-height: 1.45;
            font-weight: 700;
        }
        .payroll-help-section-desc {
            margin: 0;
            color: #667085;
            font-size: 14px;
            line-height: 1.85;
        }
        .payroll-help-steps {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }
        .payroll-help-step {
            display: grid;
            grid-template-columns: 28px minmax(0, 1fr);
            gap: 10px;
            align-items: start;
            padding: 12px 14px;
            border-radius: 12px;
            background: #fafbfd;
            border: 1px solid #eef2f6;
        }
        .payroll-help-step-no {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 999px;
            background: #f2f6fb;
            color: #475467;
            font-size: 12px;
            font-weight: 700;
        }
        .payroll-help-step strong,
        .payroll-help-notes li strong {
            color: #1f2937;
            font-size: 14px;
            line-height: 1.6;
            font-weight: 700;
        }
        .payroll-help-step div,
        .payroll-help-notes li span,
        .payroll-help-scene-item {
            color: #667085;
            font-size: 14px;
            line-height: 1.75;
        }
        .payroll-help-notes {
            display: grid;
            gap: 10px;
            margin: 14px 0 0;
            padding: 0;
            list-style: none;
        }
        .payroll-help-notes li {
            display: grid;
            grid-template-columns: 10px minmax(0, 1fr);
            gap: 12px;
            align-items: start;
            padding: 12px 14px;
            border-radius: 12px;
            background: #fafbfd;
            border: 1px solid #eef2f6;
        }
        .payroll-help-note-dot {
            width: 8px;
            height: 8px;
            margin-top: 8px;
            border-radius: 999px;
            background: #6b7280;
            flex-shrink: 0;
        }
        .payroll-help-scenes {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }
        .payroll-help-scene-item {
            padding: 12px 14px;
            border-radius: 12px;
            background: #fafbfd;
            border: 1px solid #eef2f6;
        }
        .payroll-help-scene-item strong {
            display: block;
            margin-bottom: 4px;
            color: #1f2937;
            font-size: 14px;
            line-height: 1.6;
            font-weight: 700;
        }

        @media (max-width: 1100px) {
            .payroll-help-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="payroll-help-header">
        <div>
            <h1 class="payroll-help-title">工资查询使用帮助</h1>
            <div class="payroll-help-desc">这页主要帮你快速看懂怎么建月份、怎么传表格、员工前台会怎么使用。</div>
        </div>
    </div>

    @include('payroll::admin._nav')

    <div class="payroll-help-shell">
        <section class="payroll-help-grid">
            <div class="payroll-help-card">
                <span class="payroll-help-kicker">怎么开始</span>
                <h2 class="payroll-help-section-title">怎么开始</h2>
                <p class="payroll-help-section-desc">先建月份，再传表，最后审核员工。流程很短，按这个顺序做就行。</p>
                <div class="payroll-help-steps">
                    <div class="payroll-help-step">
                        <span class="payroll-help-step-no">1</span>
                        <div>
                            <strong>新增月份批次</strong>
                            一个批次对应一个月份。
                        </div>
                    </div>
                    <div class="payroll-help-step">
                        <span class="payroll-help-step-no">2</span>
                        <div>
                            <strong>上传工资表或绩效表</strong>
                            重新上传会覆盖这个月原来的同类数据。
                        </div>
                    </div>
                    <div class="payroll-help-step">
                        <span class="payroll-help-step-no">3</span>
                        <div>
                            <strong>审核员工后开始查询</strong>
                            审核通过后，员工才能在前台看到自己的数据。
                        </div>
                    </div>
                </div>
            </div>

            <div class="payroll-help-card">
                <span class="payroll-help-kicker">上传前检查</span>
                <h2 class="payroll-help-section-title">上传前先看这几条</h2>
                <p class="payroll-help-section-desc">这些地方最容易出错，提前看一眼会省很多事。</p>
                <ul class="payroll-help-notes">
                    <li>
                        <span class="payroll-help-note-dot"></span>
                        <span><strong>第一列必须是姓名。</strong> 同一张表里姓名不能重复。</span>
                    </li>
                    <li>
                        <span class="payroll-help-note-dot"></span>
                        <span><strong>员工登记姓名要一致。</strong> 不一致时，前台会提示没有对应工资信息。</span>
                    </li>
                    <li>
                        <span class="payroll-help-note-dot"></span>
                        <span><strong>重新上传会直接覆盖。</strong> 不需要再建新的月份批次。</span>
                    </li>
                    <li>
                        <span class="payroll-help-note-dot"></span>
                        <span><strong>同名员工会被拦住。</strong> 需要先处理重名数据，再继续导入。</span>
                    </li>
                </ul>
            </div>

            <div class="payroll-help-card">
                <span class="payroll-help-kicker">前台会发生什么</span>
                <h2 class="payroll-help-section-title">员工看到的流程</h2>
                <p class="payroll-help-section-desc">站点前台的使用路径也很简单，管理员只要把前面的数据准备好即可。</p>
                <div class="payroll-help-scenes">
                    <div class="payroll-help-scene-item">
                        <strong>第一次进入先登记</strong>
                        员工先填写姓名和手机号，提交后进入待审核。
                    </div>
                    <div class="payroll-help-scene-item">
                        <strong>审核通过后可查询</strong>
                        通过后就能按月份查看工资和绩效。
                    </div>
                    <div class="payroll-help-scene-item">
                        <strong>密码保护按设置生效</strong>
                        如果你开启了密码保护，员工需要先验证密码。
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
