<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ! empty($manageMode) ? '密码管理' : '请输入密码' }} - {{ $site->name }}</title>
    <style>
        :root {
            --primary: #4da2ff;
            --bg: #eaf4ff;
            --panel: rgba(255,255,255,0.84);
            --line: rgba(116,151,191,0.18);
            --text: #1f2937;
            --muted: #8a94a6;
            --danger: #c62828;
            --success-bg: rgba(16, 185, 129, 0.10);
            --success-text: #059669;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: linear-gradient(180deg, #eff7ff 0%, #e8f2ff 100%); color: var(--text); font-family: "PingFang SC","Microsoft YaHei",sans-serif; }
        a { color: inherit; text-decoration: none; }
        .shell { width: min(440px, calc(100% - 24px)); margin: 0 auto; min-height: 100vh; padding: 14px 0 28px; }
        .topbar { text-align: center; color: #111827; font-size: 22px; font-weight: 700; }
        .subbar { margin-top: 4px; text-align: center; color: var(--muted); font-size: 12px; }
        .panel {
            margin-top: 18px;
            padding: 20px 18px;
            border-radius: 22px;
            border: 1px solid var(--line);
            background: var(--panel);
            box-shadow: 0 18px 36px rgba(37,119,216,0.08);
            backdrop-filter: blur(10px);
        }
        .headline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .title { margin: 0; font-size: 22px; font-weight: 700; color: #111827; }
        .back-link { color: #374151; font-size: 14px; font-weight: 700; white-space: nowrap; }
        .hint { margin-top: 12px; color: #667085; font-size: 14px; line-height: 1.9; }
        .success { margin-top: 14px; padding: 14px 16px; border-radius: 16px; color: var(--success-text); background: var(--success-bg); font-size: 14px; line-height: 1.8; }
        .status-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 16px;
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(255,255,255,0.72);
            border: 1px solid rgba(116,151,191,0.18);
        }
        .status-card strong { display: block; color: #111827; font-size: 14px; }
        .status-card span { display: block; margin-top: 4px; color: #98a2b3; font-size: 12px; line-height: 1.7; }
        .status-pill {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 12px;
            border-radius: 999px;
            background: rgba(0, 80, 179, 0.08);
            color: #0050b3;
            font-size: 12px;
            font-weight: 700;
        }
        .field { display: grid; gap: 8px; margin-top: 16px; }
        .label { color: #4b5563; font-size: 14px; font-weight: 700; }
        .input {
            width: 100%;
            height: 52px;
            padding: 0 16px;
            border-radius: 16px;
            border: 1px solid rgba(116,151,191,0.24);
            background: #fff;
            color: #111827;
            font-size: 16px;
            outline: none;
        }
        .input:focus { border-color: rgba(77,162,255,0.55); box-shadow: 0 0 0 4px rgba(77,162,255,0.12); }
        .helper { color: #98a2b3; font-size: 12px; line-height: 1.7; }
        .error { margin-top: 6px; color: var(--danger); font-size: 13px; line-height: 1.7; }
        .field-error.is-hidden { display: none; }
        .toggle-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 16px;
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(255,255,255,0.72);
            border: 1px solid rgba(116,151,191,0.18);
        }
        .toggle-copy strong { display: block; color: #111827; font-size: 14px; }
        .toggle-copy span { display: block; margin-top: 4px; color: #98a2b3; font-size: 12px; line-height: 1.7; }
        .toggle-card input { width: 20px; height: 20px; accent-color: var(--primary); }
        .actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-top: 20px;
        }
        .action-note { color: #98a2b3; font-size: 12px; line-height: 1.7; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 104px;
            height: 42px;
            padding: 0 22px;
            border: none;
            border-radius: 12px;
            background: var(--primary);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }
        .button[disabled] { opacity: .55; cursor: not-allowed; }
        .notes {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(255,255,255,0.72);
            border: 1px solid rgba(116,151,191,0.18);
            color: #475467;
            font-size: 13px;
            line-height: 1.9;
        }
        .notes strong { display: block; margin-bottom: 6px; color: #111827; }
        .field.is-muted .input {
            background: #f8fafc;
            color: #98a2b3;
        }
    </style>
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

                <form method="POST" action="{{ route('site.payroll.password.save', $siteQuery) }}">
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

                <form method="POST" action="{{ route('site.payroll.password.unlock', $siteQuery) }}">
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

    <script>
        (() => {
            const form = document.querySelector('form');
            if (!form) return;

            const passwordInput = form.querySelector('[data-payroll-password]');
            const passwordError = form.querySelector('[data-password-error]');
            const confirmInput = form.querySelector('[data-payroll-password-confirmation]');
            const confirmError = form.querySelector('[data-password-confirmation-error]');
            const submitButton = form.querySelector('[data-submit-button]');
            const toggle = form.querySelector('[data-password-toggle]');
            const passwordField = form.querySelector('[data-password-field]');
            const confirmationField = form.querySelector('[data-password-confirmation-field]');
            const manageMode = {{ ! empty($manageMode) ? 'true' : 'false' }};

            const toggleError = (node, message) => {
                if (!node) return;
                node.textContent = message || '';
                node.classList.toggle('is-hidden', !message);
            };

            const syncPasswordFieldState = () => {
                if (!manageMode || !toggle) return;
                const enabled = toggle.checked;
                passwordField?.classList.toggle('is-muted', !enabled);
                confirmationField?.classList.toggle('is-muted', !enabled);
                if (passwordInput) passwordInput.disabled = !enabled;
                if (confirmInput) confirmInput.disabled = !enabled;
                if (!enabled) {
                    toggleError(passwordError, '');
                    toggleError(confirmError, '');
                    if (passwordInput) passwordInput.value = '';
                    if (confirmInput) confirmInput.value = '';
                }
            };

            const validatePassword = () => {
                const value = passwordInput?.value || '';
                if (!manageMode) {
                    if (value.trim() === '') {
                        toggleError(passwordError, '请输入密码。');
                        return false;
                    }
                    toggleError(passwordError, '');
                    return true;
                }

                if (!toggle?.checked) {
                    toggleError(passwordError, '');
                    return true;
                }

                if (value.trim() === '') {
                    toggleError(passwordError, '开启密码保护后，请输入新的密码。');
                    return false;
                }

                if (value.length < 4) {
                    toggleError(passwordError, '密码至少需要 4 位。');
                    return false;
                }

                toggleError(passwordError, '');
                return true;
            };

            const validateConfirmation = () => {
                if (!manageMode || !confirmInput) return true;
                if (!toggle?.checked) {
                    toggleError(confirmError, '');
                    return true;
                }

                if ((passwordInput?.value || '') !== confirmInput.value) {
                    toggleError(confirmError, '两次输入的密码不一致。');
                    return false;
                }

                toggleError(confirmError, '');
                return true;
            };

            syncPasswordFieldState();
            passwordInput?.addEventListener('blur', validatePassword);
            confirmInput?.addEventListener('blur', validateConfirmation);
            toggle?.addEventListener('change', () => {
                syncPasswordFieldState();
            });

            form.addEventListener('submit', (event) => {
                const valid = validatePassword() & validateConfirmation();
                if (!valid) {
                    event.preventDefault();
                    submitButton?.removeAttribute('disabled');
                    return;
                }

                submitButton?.setAttribute('disabled', 'disabled');
            });
        })();
    </script>
</body>
</html>
