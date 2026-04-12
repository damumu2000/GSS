@extends('layouts.admin')

@section('title', '工资查询使用帮助 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 工资查询使用帮助')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/payroll-admin-header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/payroll-admin-help.css') }}">
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
