<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ! empty($manageMode) ? '隐私密码' : '请输入密码' }} - {{ $site->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/payroll-frontend-password.css') }}">
</head>
<body>
    <div class="shell">
        <section class="panel">
            <div class="headline">
                <h1 class="title">{{ ! empty($manageMode) ? '隐私密码' : '请输入密码' }}</h1>
                @if (! empty($manageMode))
                    <a class="button secondary" href="{{ route('site.payroll.index', $siteQuery) }}">返回列表</a>
                @endif
            </div>

            @if (session('status'))
                <div class="success">{{ session('status') }}</div>
            @endif

            @if (! empty($manageMode))
                <div class="hint">开启隐私密码功能后需要输入正确密码才能进入。</div>

                <form method="POST" action="{{ route('site.payroll.password.save', $siteQuery) }}" data-manage-mode="1">
                    @csrf

                    <label class="switch-card">
                        <span class="switch-copy">隐私密码开关</span>
                        <span class="switch-control">
                            <input type="checkbox" name="password_enabled" value="1" @checked(old('password_enabled', (bool) $employee->password_enabled)) data-password-toggle>
                            <span class="switch-slider" aria-hidden="true"></span>
                        </span>
                    </label>

                    <label class="field {{ old('password_enabled', (bool) $employee->password_enabled) ? '' : 'is-muted' }}" data-password-field>
                        <span class="label">新密码</span>
                        <input class="input" type="password" name="password" maxlength="15" placeholder="{{ $employee->password_enabled ? '如需修改请输入新密码' : '开启时请输入 4-15 位密码' }}" data-payroll-password>
                        <div class="helper">仅在开启密码保护并填写新密码时更新，支持 4-15 位字符。</div>
                        @error('password')<div class="error field-error">{{ $message }}</div>@else <div class="error field-error is-hidden" data-password-error></div>@enderror
                    </label>

                    <label class="field {{ old('password_enabled', (bool) $employee->password_enabled) ? '' : 'is-muted' }}" data-password-confirmation-field>
                        <span class="label">确认密码</span>
                        <input class="input" type="password" name="password_confirmation" maxlength="15" placeholder="请再次输入密码" data-payroll-password-confirmation>
                        <div class="error field-error is-hidden" data-password-confirmation-error></div>
                    </label>

                    <div class="actions">
                        <button class="button" type="submit" data-submit-button>保存设置</button>
                    </div>
                </form>
            @else
                <div class="hint">当前账户已开启隐私密码保护，请正确输入密码进入。</div>

                <form method="POST" action="{{ route('site.payroll.password.unlock', $siteQuery) }}" data-manage-mode="0">
                    @csrf

                    <label class="field">
                        <span class="label">登录密码</span>
                        <input class="input" type="password" name="password" maxlength="15" placeholder="请输入当前密码" data-payroll-password>
                        @error('password')<div class="error field-error">{{ $message }}</div>@else <div class="error field-error is-hidden" data-password-error></div>@enderror
                    </label>

                    <div class="actions">
                        <button class="button" type="submit" data-submit-button>验证进入</button>
                    </div>
                </form>
            @endif
        </section>
    </div>

    <script src="{{ asset('js/payroll-frontend-password.js') }}"></script>
</body>
</html>
