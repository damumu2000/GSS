<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ! empty($manageMode) ? '密码管理' : '请输入密码' }} - {{ $site->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/payroll-frontend-password.css') }}">
</head>
<body>
    <div class="shell">
        <div class="topbar">{{ ! empty($manageMode) ? '密码管理' : '请输入密码' }}</div>
        <div class="subbar">{{ $site->name }}</div>

        <section class="panel">
            <div class="headline">
                <h1 class="title">{{ ! empty($manageMode) ? '管理自定义密码' : '请输入密码' }}</h1>
                <a class="back-link" href="{{ route('site.payroll.index', $siteQuery) }}">返回列表</a>
            </div>

            @if (session('status'))
                <div class="success">{{ session('status') }}</div>
            @endif

            @if (! empty($manageMode))
                <div class="hint">开启后，微信自动登录成功后仍需输入自定义密码，验证通过后才能进入工资查询页面。</div>

                <div class="status-card">
                    <div>
                        <strong>当前密码状态</strong>
                        <span>{{ $employee->password_enabled ? '已开启密码保护，下次从微信进入前会先要求输入密码。' : '当前未开启密码保护，微信登录后可直接进入工资列表。' }}</span>
                    </div>
                    <span class="status-pill">{{ $employee->password_enabled ? '已开启' : '未开启' }}</span>
                </div>

                <form method="POST" action="{{ route('site.payroll.password.save', $siteQuery) }}" data-manage-mode="1">
                    @csrf

                    <label class="toggle-card">
                        <span class="toggle-copy">
                            <strong>开启密码保护</strong>
                            <span>开启后，微信自动登录成功仍需输入密码验证。</span>
                        </span>
                        <input type="checkbox" name="password_enabled" value="1" @checked(old('password_enabled', (bool) $employee->password_enabled)) data-password-toggle>
                    </label>

                    <label class="field {{ old('password_enabled', (bool) $employee->password_enabled) ? '' : 'is-muted' }}" data-password-field>
                        <span class="label">新密码</span>
                        <input class="input" type="password" name="password" maxlength="32" placeholder="{{ $employee->password_enabled ? '如需修改请输入新密码' : '开启时请输入 4-32 位密码' }}" data-payroll-password>
                        <div class="helper">仅在开启密码保护并填写新密码时更新，支持 4-32 位字符。</div>
                        @error('password')<div class="error field-error">{{ $message }}</div>@else <div class="error field-error is-hidden" data-password-error></div>@enderror
                    </label>

                    <label class="field {{ old('password_enabled', (bool) $employee->password_enabled) ? '' : 'is-muted' }}" data-password-confirmation-field>
                        <span class="label">确认密码</span>
                        <input class="input" type="password" name="password_confirmation" maxlength="32" placeholder="请再次输入密码" data-payroll-password-confirmation>
                        <div class="error field-error is-hidden" data-password-confirmation-error></div>
                    </label>

                    <div class="actions">
                        <div class="action-note">关闭密码保护后，下次通过微信进入时将直接进入工资列表。</div>
                        <button class="button" type="submit" data-submit-button>保存设置</button>
                    </div>
                </form>

                <div class="notes">
                    <strong>使用说明</strong>
                    开启密码后，只有输入正确密码才能进入工资查询。<br>
                    如果忘记密码，请联系管理员在后台执行“重置密码”。
                </div>
            @else
                <div class="hint">当前账户已开启密码保护，请先输入自定义密码，验证通过后再进入工资信息列表。</div>

                <div class="status-card">
                    <div>
                        <strong>当前进入方式</strong>
                        <span>微信身份已识别成功，本次只需要再完成密码验证即可进入工资查询页面。</span>
                    </div>
                    <span class="status-pill">待验证</span>
                </div>

                <form method="POST" action="{{ route('site.payroll.password.unlock', $siteQuery) }}" data-manage-mode="0">
                    @csrf

                    <label class="field">
                        <span class="label">登录密码</span>
                        <input class="input" type="password" name="password" maxlength="32" placeholder="请输入当前密码" data-payroll-password>
                        <div class="helper">密码正确后会自动返回工资列表。</div>
                        @error('password')<div class="error field-error">{{ $message }}</div>@else <div class="error field-error is-hidden" data-password-error></div>@enderror
                    </label>

                    <div class="actions">
                        <div class="action-note">如果连续多次输错密码，系统会暂时限制再次尝试。</div>
                        <button class="button" type="submit" data-submit-button>验证进入</button>
                    </div>
                </form>
            @endif
        </section>
    </div>

    <script src="{{ asset('js/payroll-frontend-password.js') }}"></script>
</body>
</html>
