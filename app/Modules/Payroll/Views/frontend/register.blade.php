<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登记信息 - {{ $site->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/payroll-frontend-register.css') }}">
</head>
<body>
    <div class="shell">
        <div class="topbar">登记信息</div>
        <div class="subbar">{{ $site->name }}</div>

        <section class="panel">
            <div class="headline">
                <h1 class="title">首次进入请完善信息</h1>
                @if ($employee && $employee->status === 'pending')
                    <span class="status-pill is-pending">审核中</span>
                @else
                    <span class="status-pill is-ready">待提交</span>
                @endif
            </div>

            <div class="desc">微信身份识别完成后，如果当前系统中还没有您的员工信息，请登记姓名和手机号码。管理员审核通过后，您就可以直接进入工资查询页面。</div>

            @if (session('status'))
                <div class="notice is-success">{{ session('status') }}</div>
            @endif

            @if ($employee && $employee->status === 'pending')
                <div class="notice is-pending">当前登记信息已提交，正在等待管理员审核。如需更正姓名或手机号码，可直接修改后再次提交。</div>
            @endif

            @if ($errors->has('register'))
                <div class="error error--spaced">{{ $errors->first('register') }}</div>
            @endif

            <form method="POST" action="{{ route('site.payroll.register.store', $siteQuery) }}">
                @csrf

                <div class="field-grid">
                    <label class="field">
                        <span class="label">姓名</span>
                        <input class="input" type="text" name="name" value="{{ old('name', $employee->name ?? '') }}" maxlength="20" placeholder="请输入真实姓名" data-payroll-name>
                        <div class="helper">仅支持中文、英文和间隔号，长度 2-20 个字符。</div>
                        @error('name')<div class="error field-error">{{ $message }}</div>@else <div class="error field-error is-hidden" data-name-error></div>@enderror
                    </label>

                    <label class="field">
                        <span class="label">手机号码</span>
                        <input class="input" type="tel" name="mobile" value="{{ old('mobile', $employee->mobile ?? '') }}" maxlength="11" inputmode="numeric" placeholder="请输入 11 位手机号码" data-payroll-mobile>
                        <div class="helper">建议填写本人常用手机号，便于审核与后续联系。</div>
                        @error('mobile')<div class="error field-error">{{ $message }}</div>@else <div class="error field-error is-hidden" data-mobile-error></div>@enderror
                    </label>
                </div>

                <div class="actions">
                    <div class="action-note">提交后将进入审核状态，审核通过后可直接通过微信进入工资查询。</div>
                    <button class="button" type="submit" data-submit-button>{{ $employee && $employee->status === 'pending' ? '更新信息' : '提交信息' }}</button>
                </div>
            </form>
        </section>
    </div>

    <script src="{{ asset('js/payroll-frontend-register.js') }}"></script>
</body>
</html>
