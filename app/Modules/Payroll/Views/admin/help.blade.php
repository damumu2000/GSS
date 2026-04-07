@extends('layouts.admin')

@section('title', '工资查询使用帮助 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 工资查询使用帮助')

@push('styles')
    <style>
        .payroll-help-header {
            padding: 28px 32px 22px;
            margin: -28px -28px 20px;
            background: #fff;
            border-bottom: 1px solid #f0f0f0;
        }
        .payroll-help-title { margin: 0; color: #262626; font-size: 20px; line-height: 1.4; font-weight: 700; }
        .payroll-help-desc {
            max-width: 760px;
            margin-top: 10px;
            color: #8c8c8c;
            font-size: 14px;
            line-height: 1.85;
        }
        .payroll-help-shell { display: grid; gap: 18px; margin-top: 18px; }
        .payroll-help-panel {
            padding: 24px 26px;
            border: 1px solid #eef2f6;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }
        .payroll-help-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(280px, 0.85fr);
            gap: 18px;
        }
        .payroll-help-intro {
            padding: 26px 28px;
            border-radius: 18px;
            background:
                radial-gradient(circle at top right, rgba(0, 80, 179, 0.08), transparent 38%),
                linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid #e8eef7;
        }
        .payroll-help-kicker {
            display: inline-flex;
            align-items: center;
            padding: 0 12px;
            min-height: 32px;
            border-radius: 999px;
            background: rgba(0, 80, 179, 0.08);
            color: var(--primary, #0050b3);
            font-size: 13px;
            font-weight: 700;
        }
        .payroll-help-intro-title {
            margin: 16px 0 10px;
            color: #1f2937;
            font-size: 28px;
            line-height: 1.35;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .payroll-help-intro-desc {
            margin: 0;
            color: #667085;
            font-size: 15px;
            line-height: 1.9;
        }
        .payroll-help-mini-list {
            display: grid;
            gap: 12px;
            margin-top: 20px;
        }
        .payroll-help-mini-item {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr);
            gap: 12px;
            align-items: flex-start;
        }
        .payroll-help-mini-no {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #dbe6f2;
            color: var(--primary, #0050b3);
            font-size: 13px;
            font-weight: 800;
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.04);
        }
        .payroll-help-mini-text strong {
            display: block;
            margin-bottom: 4px;
            color: #1f2937;
            font-size: 15px;
            line-height: 1.5;
            font-weight: 700;
        }
        .payroll-help-mini-text span {
            color: #667085;
            font-size: 14px;
            line-height: 1.8;
        }
        .payroll-help-side {
            padding: 24px 24px 22px;
            border-radius: 18px;
            background: #fbfcfe;
            border: 1px solid #eef2f6;
        }
        .payroll-help-side-title {
            margin: 0;
            color: #1f2937;
            font-size: 20px;
            line-height: 1.5;
            font-weight: 700;
        }
        .payroll-help-side-desc {
            margin: 8px 0 0;
            color: #8c8c8c;
            font-size: 14px;
            line-height: 1.85;
        }
        .payroll-help-checks {
            display: grid;
            gap: 12px;
            margin-top: 18px;
        }
        .payroll-help-check {
            display: grid;
            grid-template-columns: 20px minmax(0, 1fr);
            gap: 12px;
            align-items: start;
            padding: 12px 14px;
            border-radius: 14px;
            background: #fff;
            border: 1px solid #eef2f6;
        }
        .payroll-help-check-dot {
            width: 10px;
            height: 10px;
            margin-top: 7px;
            border-radius: 999px;
            background: #22c55e;
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.12);
        }
        .payroll-help-check strong {
            color: #1f2937;
            font-weight: 700;
        }
        @media (max-width: 960px) {
            .payroll-help-hero { grid-template-columns: 1fr; }
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
        <section class="payroll-help-panel">
            <div class="payroll-help-hero">
                <div class="payroll-help-intro">
                    <span class="payroll-help-kicker">上手很快</span>
                    <h2 class="payroll-help-intro-title">按月份建批次，传表后就能查</h2>
                    <p class="payroll-help-intro-desc">这套模块不用做复杂配置。你只要建好月份、上传工资表和绩效表、通过员工审核，前台就可以开始查询。</p>
                    <div class="payroll-help-mini-list">
                        <div class="payroll-help-mini-item">
                            <span class="payroll-help-mini-no">01</span>
                            <div class="payroll-help-mini-text">
                                <strong>先新增月份批次</strong>
                                <span>一个月份只建一个批次，后面所有工资和绩效都挂在这个月份下面。</span>
                            </div>
                        </div>
                        <div class="payroll-help-mini-item">
                            <span class="payroll-help-mini-no">02</span>
                            <div class="payroll-help-mini-text">
                                <strong>再上传对应表格</strong>
                                <span>工资表和绩效表分别上传，系统会自动解析并覆盖这个月原来的同类数据。</span>
                            </div>
                        </div>
                        <div class="payroll-help-mini-item">
                            <span class="payroll-help-mini-no">03</span>
                            <div class="payroll-help-mini-text">
                                <strong>审核后员工即可查询</strong>
                                <span>员工微信首次进入会先登记信息，管理员审核通过后，对方就能正常看到工资和绩效。</span>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="payroll-help-side">
                    <h2 class="payroll-help-side-title">上传前先看这 4 条</h2>
                    <p class="payroll-help-side-desc">这几条最容易影响导入结果，提前看一眼，后面会顺很多。</p>
                    <div class="payroll-help-checks">
                        <div class="payroll-help-check">
                            <span class="payroll-help-check-dot"></span>
                            <div><strong>第一列必须是姓名</strong>，而且同一张表里姓名不能重复。</div>
                        </div>
                        <div class="payroll-help-check">
                            <span class="payroll-help-check-dot"></span>
                            <div><strong>员工登记姓名要一致</strong>，否则前台会提示没有对应工资信息。</div>
                        </div>
                        <div class="payroll-help-check">
                            <span class="payroll-help-check-dot"></span>
                            <div><strong>重新上传会覆盖旧结果</strong>，不用重复建新月份。</div>
                        </div>
                        <div class="payroll-help-check">
                            <span class="payroll-help-check-dot"></span>
                            <div><strong>同名员工会被拦住</strong>，需要先联系管理员处理后再继续。</div>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

    </div>
@endsection
