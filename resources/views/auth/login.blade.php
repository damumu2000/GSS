@php($adminBrandSettings = app(\App\Support\SystemSettings::class)->formDefaults())
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台登录 - {{ $adminBrandSettings['system_name'] }}</title>
    @if (! empty($adminBrandSettings['admin_favicon']))
        <link rel="icon" href="{{ $adminBrandSettings['admin_favicon'] }}">
    @endif
    <style>
        :root {
            --bg: #edf5f0;
            --panel: rgba(255, 255, 255, 0.9);
            --line: #d8e4dd;
            --text: #1b342d;
            --muted: #668078;
            --primary: #206a5d;
            --primary-dark: #174a42;
            --danger: #bf4f46;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(163, 214, 195, 0.75), transparent 24%),
                radial-gradient(circle at right bottom, rgba(32, 106, 93, 0.12), transparent 30%),
                linear-gradient(180deg, #f9fcfa 0%, var(--bg) 100%);
        }

        .login-shell {
            width: min(980px, 100%);
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.65);
            border-radius: 28px;
            background: var(--panel);
            backdrop-filter: blur(16px);
            box-shadow: 0 30px 80px rgba(26, 72, 62, 0.14);
        }

        .intro {
            padding: 48px;
            color: #f4fbf8;
            background:
                linear-gradient(160deg, rgba(20, 72, 63, 0.96), rgba(30, 112, 97, 0.92)),
                linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.08));
        }

        .intro h1 {
            margin: 0 0 16px;
            font-size: 36px;
            line-height: 1.15;
        }

        .intro p {
            margin: 0;
            color: rgba(244, 251, 248, 0.78);
            line-height: 1.8;
        }

        .intro-cards {
            display: grid;
            gap: 14px;
            margin-top: 32px;
        }

        .intro-card {
            padding: 16px 18px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.08);
        }

        .intro-card strong {
            display: block;
            margin-bottom: 8px;
        }

        .form-wrap {
            padding: 42px;
            display: flex;
            align-items: center;
        }

        .form-card {
            width: 100%;
        }

        .form-card h2 {
            margin: 0 0 10px;
            font-size: 28px;
        }

        .form-card p {
            margin: 0 0 28px;
            color: var(--muted);
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 14px 15px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            font: inherit;
            color: inherit;
        }

        .field {
            margin-bottom: 18px;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
            color: var(--muted);
            font-size: 14px;
        }

        .button {
            width: 100%;
            padding: 14px 16px;
            border: 0;
            border-radius: 16px;
            background: var(--primary);
            color: #fff;
            font: inherit;
            cursor: pointer;
        }

        .helper {
            margin-top: 18px;
            padding: 16px 18px;
            border: 1px dashed var(--line);
            border-radius: 16px;
            color: var(--muted);
            background: #f7fbf8;
            line-height: 1.7;
        }

        .warning {
            margin-bottom: 18px;
            padding: 14px 16px;
            border: 1px solid rgba(191, 79, 70, 0.2);
            border-radius: 16px;
            background: rgba(191, 79, 70, 0.06);
            color: var(--danger);
            font-size: 13px;
            line-height: 1.7;
        }

        .error {
            margin-top: 8px;
            color: var(--danger);
            font-size: 13px;
        }

        @media (max-width: 860px) {
            .login-shell {
                grid-template-columns: 1fr;
            }

            .intro,
            .form-wrap {
                padding: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="login-shell">
        <section class="intro">
            <h1>学校官网后台管理系统</h1>
            <p>面向小学到高中的多站点官网建设，聚焦内容发布、栏目维护、主题切换和站点统一运营。</p>

            <div class="intro-cards">
                <div class="intro-card">
                    <strong>多站点统一后台</strong>
                    一个后台切换管理多个学校官网。
                </div>
                <div class="intro-card">
                    <strong>主题化前台</strong>
                    默认主题清新大方，后续可扩展更多学校风格。
                </div>
                <div class="intro-card">
                    <strong>轻量但可扩展</strong>
                    先把学校官网高频场景做扎实，再逐步扩展。
                </div>
            </div>
        </section>

        <section class="form-wrap">
            <div class="form-card">
                <h2>后台登录</h2>
                <p>平台管理员与站点操作员均可在此登录，系统会自动进入对应的工作台。</p>

                @if (! empty($databaseHealthWarning))
                    <div class="warning">{{ $databaseHealthWarning }}</div>
                @endif

                @if (! empty($adminDisabledMessage))
                    <div class="warning">{{ $adminDisabledMessage }}</div>
                @endif

                <form method="POST" action="{{ route('login.store') }}">
                    @csrf

                    <div class="field">
                        <label for="username">用户名</label>
                        <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus>
                        @error('username')
                            <div class="error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="password">密码</label>
                        <input id="password" type="password" name="password" required>
                        @error('password')
                            <div class="error">{{ $message }}</div>
                        @enderror
                    </div>

                    <label class="checkbox">
                        <input type="checkbox" name="remember" value="1">
                        记住登录状态
                    </label>

                    <button class="button" type="submit">进入后台</button>
                </form>

                <div class="helper">
                    默认账号：`superadmin`<br>
                    默认密码：`ChangeMe123!`
                </div>
            </div>
        </section>
    </div>
</body>
</html>
