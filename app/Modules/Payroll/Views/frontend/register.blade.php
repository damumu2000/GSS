<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登记信息 - {{ $site->name }}</title>
    <style>
        :root {
            --primary: #4da2ff;
            --primary-deep: #2577d8;
            --bg: #eaf4ff;
            --panel: rgba(255,255,255,0.84);
            --line: rgba(116,151,191,0.18);
            --text: #1f2937;
            --muted: #8a94a6;
            --warning-bg: rgba(245, 158, 11, 0.12);
            --warning-text: #b45309;
            --success-bg: rgba(16, 185, 129, 0.10);
            --success-text: #059669;
            --danger: #c62828;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: linear-gradient(180deg, #eff7ff 0%, #e8f2ff 100%); color: var(--text); font-family: "PingFang SC","Microsoft YaHei",sans-serif; }
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
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .title { margin: 0; font-size: 22px; font-weight: 700; color: #111827; }
        .status-pill {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }
        .status-pill.is-pending { color: var(--warning-text); background: var(--warning-bg); }
        .status-pill.is-ready { color: var(--success-text); background: var(--success-bg); }
        .desc { margin-top: 10px; color: #667085; font-size: 14px; line-height: 1.9; }
        .notice {
            margin-top: 16px;
            padding: 14px 16px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.8;
        }
        .notice.is-pending { color: var(--warning-text); background: var(--warning-bg); }
        .notice.is-success { color: var(--success-text); background: var(--success-bg); }
        .field-grid {
            display: grid;
            gap: 16px;
            margin-top: 18px;
        }
        .field { display: grid; gap: 8px; }
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
        .error { color: var(--danger); font-size: 13px; line-height: 1.7; }
        .field-error.is-hidden { display: none; }
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
            min-width: 112px;
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
    </style>
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
                <div class="error" style="margin-top: 14px;">{{ $errors->first('register') }}</div>
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

    <script>
        (() => {
            const form = document.querySelector('form');
            if (!form) return;

            const nameInput = form.querySelector('[data-payroll-name]');
            const mobileInput = form.querySelector('[data-payroll-mobile]');
            const nameError = form.querySelector('[data-name-error]');
            const mobileError = form.querySelector('[data-mobile-error]');
            const submitButton = form.querySelector('[data-submit-button]');
            const namePattern = /^[\u4e00-\u9fa5A-Za-z·]{2,20}$/;
            const mobilePattern = /^1[3-9]\d{9}$/;

            const toggleError = (node, message) => {
                if (!node) return;
                node.textContent = message || '';
                node.classList.toggle('is-hidden', !message);
            };

            const validateName = () => {
                const value = (nameInput?.value || '').trim();
                if (value === '') {
                    toggleError(nameError, '请填写姓名。');
                    return false;
                }
                if (!namePattern.test(value)) {
                    toggleError(nameError, '姓名仅支持中文、英文和间隔号，长度 2-20 个字符。');
                    return false;
                }
                toggleError(nameError, '');
                return true;
            };

            const validateMobile = () => {
                const value = (mobileInput?.value || '').replace(/\D+/g, '');
                if (mobileInput) mobileInput.value = value;
                if (value === '') {
                    toggleError(mobileError, '请填写手机号码。');
                    return false;
                }
                if (!mobilePattern.test(value)) {
                    toggleError(mobileError, '请填写 11 位大陆手机号。');
                    return false;
                }
                toggleError(mobileError, '');
                return true;
            };

            nameInput?.addEventListener('blur', validateName);
            mobileInput?.addEventListener('input', () => {
                mobileInput.value = mobileInput.value.replace(/\D+/g, '').slice(0, 11);
            });
            mobileInput?.addEventListener('blur', validateMobile);

            form.addEventListener('submit', (event) => {
                const valid = validateName() & validateMobile();
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
