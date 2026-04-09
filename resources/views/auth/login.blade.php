@php($adminBrandSettings = app(\App\Support\SystemSettings::class)->formDefaults())
@php($loginHeroImage = '/BG_1800x2400.jpg')
@php($loginSiteName = $loginSiteBrand?->name ?: '站点')
@php($loginSeoTitle = $loginSiteBrand?->seo_title ?: $loginSiteName)
@php($loginSeoKeywords = $loginSiteBrand?->seo_keywords)
@php($loginSeoDescription = $loginSiteBrand?->seo_description ?: $loginSiteName)
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台登录 - {{ $loginSeoTitle }}</title>
    @if (! empty($loginSeoKeywords))
        <meta name="keywords" content="{{ $loginSeoKeywords }}">
    @endif
    @if (! empty($loginSeoDescription))
        <meta name="description" content="{{ $loginSeoDescription }}">
    @endif
    @if (! empty($loginSiteBrand?->favicon))
        <link rel="icon" href="{{ $loginSiteBrand->favicon }}">
    @endif
    <style>
        :root {
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-300: #cbd5e1;
            --slate-400: #94a3b8;
            --slate-500: #64748b;
            --slate-600: #475569;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --emerald-50: #ecfdf5;
            --emerald-100: #d1fae5;
            --emerald-400: #34d399;
            --emerald-500: #10b981;
            --emerald-600: #059669;
            --emerald-700: #047857;
            --teal-600: #0f766e;
            --cyan-700: #155e75;
            --white-08: rgba(255, 255, 255, 0.08);
            --white-10: rgba(255, 255, 255, 0.10);
            --white-15: rgba(255, 255, 255, 0.15);
            --white-20: rgba(255, 255, 255, 0.20);
            --danger: #cb5d5d;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            min-height: 100vh;
            width: 100%;
            display: flex;
            background: var(--slate-50);
            color: var(--slate-800);
            font-family: "PingFang SC", "SF Pro Display", "Segoe UI", "Microsoft YaHei", sans-serif;
        }

        .page {
            min-height: 100vh;
            width: 100%;
            display: flex;
        }

        .intro {
            display: none;
            position: relative;
            overflow: hidden;
            width: 50%;
        }

        .intro-bg {
            position: absolute;
            inset: 0;
            background: url('{{ $loginHeroImage }}') center center / cover no-repeat;
        }

        .intro-bg::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(7, 26, 38, 0.18) 0%, rgba(7, 26, 38, 0.42) 100%);
        }

        .intro-content {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            width: 100%;
            padding: 56px 56px 56px 64px;
        }

        .brand {
            position: absolute;
            left: 39px;
            bottom: 47px;
            margin-bottom: 0;
        }

        .brand-row {
            display: flex;
            align-items: center;
            gap: 0;
        }

        .brand-mark {
            width: 86px;
            height: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: visible;
        }

        .brand-mark svg {
            width: 38px;
            height: 38px;
            color: #ffffff;
        }

        .brand-mark img {
            width: 100%;
            height: auto;
            display: block;
            object-fit: contain;
            opacity: 0.5;
        }

        .brand-copy,
        .feature-list,
        .stats {
            display: none;
        }

        .hero {
            position: absolute;
            left: 67%;
            top: 83%;
            margin: 0;
            max-width: none;
            width: max-content;
            transform: translate(-50%, -50%);
            text-align: center;
            pointer-events: none;
        }

        .hero h1 {
            margin: 0;
            color: rgba(255, 255, 255, 0.7);
            font-size: clamp(36px, 4vw, 68px);
            font-weight: 700;
            font-family: "Microsoft YaHei", "PingFang SC", "Noto Sans SC", sans-serif;
            line-height: 1;
            letter-spacing: 0.06em;
            text-shadow: 0 14px 34px rgba(6, 24, 36, 0.24);
            white-space: nowrap;
        }

        .hero h1 span {
            display: inline;
            margin-top: 0;
            margin-left: 26px;
            color: rgba(255, 255, 255, 0.7);
        }

        .hero-note {
            margin-top: 22px;
            margin-left: 0;
            display: block;
            color: rgba(241, 245, 249, 0.82);
            font-size: clamp(10px, 0.9vw, 13px);
            font-weight: 500;
            letter-spacing: clamp(0.12em, 0.24vw, 0.28em);
            text-transform: uppercase;
            white-space: nowrap;
        }

        .intro-version {
            position: absolute;
            left: 64px;
            bottom: 26px;
            color: rgba(255, 255, 255, 0.48);
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.08em;
        }

        .stats strong {
            display: block;
            color: #ffffff;
            font-size: 32px;
            font-weight: 700;
            line-height: 1.2;
        }

        .stats span {
            display: block;
            margin-top: 4px;
            color: rgba(209, 250, 229, 0.7);
            font-size: 13px;
        }

        .auth {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: var(--slate-50);
        }

        .auth-inner {
            width: 100%;
            max-width: 420px;
        }

        .login-card {
            padding: 32px 40px;
            border-radius: 24px;
            background: #ffffff;
            box-shadow: 0 20px 40px rgba(226, 232, 240, 0.7);
        }

        .login-head {
            margin-bottom: 32px;
        }

        .login-head h2 {
            margin: 0 0 8px;
            color: var(--slate-800);
            font-size: 28px;
            font-weight: 700;
            line-height: 1.3;
        }

        .login-head p {
            margin: 0;
            color: var(--slate-500);
            font-size: 14px;
            line-height: 1.8;
        }

        .field {
            margin-bottom: 20px;
        }

        .field label {
            display: block;
            margin-bottom: 8px;
            color: var(--slate-700);
            font-size: 14px;
            font-weight: 500;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap svg.icon {
            position: absolute;
            left: 16px;
            top: 50%;
            width: 20px;
            height: 20px;
            color: var(--slate-400);
            transform: translateY(-50%);
            pointer-events: none;
        }

        .input-wrap input {
            width: 100%;
            height: 48px;
            padding: 0 16px 0 48px;
            border: 1px solid var(--slate-200);
            border-radius: 14px;
            background: var(--slate-50);
            color: var(--slate-800);
            font: inherit;
            outline: none;
            transition: background 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .input-wrap input:focus {
            background: #ffffff;
            border-color: rgba(16, 185, 129, 0.48);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12);
        }

        .input-wrap.password input {
            padding-right: 48px;
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            color: var(--slate-400);
            transform: translateY(-50%);
            border: 0;
            padding: 0;
            background: transparent;
            cursor: pointer;
        }

        .toggle-password svg {
            width: 20px;
            height: 20px;
        }

        .row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--slate-600);
            font-size: 14px;
            cursor: pointer;
        }

        .remember input {
            width: 16px;
            height: 16px;
            accent-color: var(--emerald-500);
        }

        .link {
            color: var(--emerald-600);
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
        }

        .submit {
            width: 100%;
            height: 52px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(90deg, var(--emerald-500), var(--teal-600));
            color: #ffffff;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 16px 32px rgba(16, 185, 129, 0.25);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 36px rgba(16, 185, 129, 0.3);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 28px;
            min-height: 78px;
            padding: 10px 0;
        }

        .divider::before,
        .divider::after {
            content: "";
            flex: 1 1 auto;
            height: 1px;
            background: var(--slate-200);
        }

        .divider span {
            flex: 0 0 auto;
            color: var(--slate-400);
            font-size: 14px;
            font-weight: 600;
            line-height: 1;
        }

        .alt-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            justify-items: center;
        }

        .alt-button {
            width: 38%;
            min-width: 180px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid var(--slate-200);
            border-radius: 14px;
            background: #ffffff;
            color: var(--slate-600);
            font: inherit;
            font-size: 14px;
            cursor: pointer;
        }

        .alt-button svg {
            width: 20px;
            height: 20px;
        }

        .auth-footer {
            margin-top: 32px;
            text-align: center;
        }

        .auth-footer p {
            margin: 0;
            color: var(--slate-500);
            font-size: 14px;
        }

        .auth-footer .link {
            font-weight: 600;
        }

        .footer-links {
            margin-top: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            color: var(--slate-400);
            font-size: 12px;
        }

        .footer-links a {
            color: inherit;
            text-decoration: none;
        }

        .toast {
            position: fixed;
            top: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(-14px);
            z-index: 9999;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-width: 280px;
            max-width: min(520px, calc(100vw - 32px));
            padding: 12px 24px;
            border-radius: 8px;
            background: #ffffff;
            color: var(--slate-800);
            border: 1px solid rgba(82, 196, 26, 0.16);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.28s ease, transform 0.28s ease;
        }

        .toast.is-error {
            border-color: rgba(239, 68, 68, 0.22);
            background: linear-gradient(180deg, #fffafa 0%, #fff4f4 100%);
            box-shadow: 0 10px 28px rgba(239, 68, 68, 0.12);
        }

        .toast.is-visible {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        .toast-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 999px;
            background: #52c41a;
            color: #ffffff;
            flex-shrink: 0;
        }

        .toast.is-error .toast-icon {
            background: #ef4444;
        }

        .toast-icon svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .toast-text {
            font-size: 14px;
            line-height: 1.6;
            color: #262626;
            white-space: pre-line;
        }

        .confirm-modal {
            position: fixed;
            inset: 0;
            z-index: 6000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }

        .confirm-modal.is-visible {
            opacity: 1;
            pointer-events: auto;
        }

        .confirm-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.32);
            backdrop-filter: blur(4px);
        }

        .confirm-modal-dialog {
            position: relative;
            width: min(460px, calc(100vw - 32px));
            padding: 24px;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 22px 40px rgba(15, 23, 42, 0.18);
        }

        .confirm-modal-head {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
        }

        .confirm-modal-icon {
            width: 44px;
            height: 44px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff7e6;
            color: #faad14;
        }

        .confirm-modal-icon svg {
            width: 22px;
            height: 22px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.9;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .confirm-modal-title {
            margin: 0;
            color: var(--slate-800);
            font-size: 22px;
            font-weight: 700;
            line-height: 1.4;
        }

        .confirm-modal-copy {
            display: grid;
            gap: 12px;
        }

        .confirm-modal-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 0;
            color: var(--slate-500);
            font-size: 14px;
            line-height: 1.8;
        }

        .confirm-modal-note-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            margin-top: 3px;
            color: var(--emerald-600);
            flex-shrink: 0;
        }

        .confirm-modal-note-icon svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .confirm-modal-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 24px;
        }

        .confirm-modal-button {
            min-width: 104px;
            height: 42px;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(90deg, var(--emerald-500), var(--teal-600));
            color: #ffffff;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 10px 22px rgba(16, 185, 129, 0.22);
        }

        @media (min-width: 1024px) {
            .intro {
                display: flex;
            }

            .auth {
                width: 50%;
                padding: 0 24px;
            }

            .mobile-brand {
                display: none;
            }
        }

        @media (max-width: 1023px) {
            .auth {
                padding: 24px;
            }

            .login-card {
                padding: 32px 32px;
            }

            .hero {
                position: static;
                left: auto;
                top: auto;
                transform: none;
                margin-top: 28px;
                text-align: left;
                pointer-events: auto;
            }

            .hero h1 {
                font-size: 48px;
                white-space: normal;
            }

            .hero h1 span {
                display: block;
                margin-left: 0;
                margin-top: 8px;
            }

            .intro-version {
                left: 24px;
                bottom: 20px;
                font-size: 10px;
            }

            .brand {
                position: static;
                left: auto;
                bottom: auto;
                margin-bottom: 24px;
            }

            .brand-mark img {
                opacity: 1;
            }
        }

        @media (max-width: 640px) {
            body {
                display: block;
            }

            .auth {
                padding: 20px;
            }

            .login-card {
                padding: 28px 24px;
                border-radius: 20px;
            }

            .row {
                align-items: flex-start;
                flex-direction: column;
            }

            .alt-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <section class="intro">
            <div class="intro-bg" aria-hidden="true">
            </div>

            <div class="intro-content">
                <div class="brand">
                    <div class="brand-row">
                        <div class="brand-mark">
                            <img src="/logo-login.png" alt="{{ $loginSiteName }} logo">
                        </div>
                    </div>
                </div>

                <div class="hero">
                    <h1>更好用<span>更安全</span></h1>
                    <div class="hero-note">Better Experience · Better Security</div>
                </div>

                <div class="intro-version">Version {{ $adminBrandSettings['system_version'] ?? '1.0.0' }}</div>
            </div>
        </section>

        <section class="auth">
            <div class="auth-inner">
                <div class="login-card">
                    <div class="login-head">
                        <h2>欢迎登录</h2>
                    <p>请输入您的账号信息以访问管理平台</p>
                    </div>

                    <form method="POST" action="{{ route('login.store') }}" novalidate data-login-form>
                        @csrf

                        <div class="field">
                            <label for="username">账号</label>
                            <div class="input-wrap">
                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M20 21a8 8 0 1 0-16 0"/>
                                    <circle cx="12" cy="8" r="4"/>
                                </svg>
                                <input id="username" type="text" name="username" placeholder="请输入您的账号" value="{{ old('username') }}" required autofocus>
                            </div>
                        </div>

                        <div class="field">
                            <label for="password">密码</label>
                            <div class="input-wrap password">
                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <rect x="4" y="11" width="16" height="9" rx="2"/>
                                    <path d="M8 11V8a4 4 0 1 1 8 0v3"/>
                                </svg>
                                <input id="password" type="password" name="password" placeholder="请输入您的密码" required>
                                <button class="toggle-password" type="button" data-toggle-password aria-label="显示或隐藏密码">
                                    <svg data-eye-open viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg data-eye-closed viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" style="display:none;">
                                        <path d="m3 3 18 18"/>
                                        <path d="M10.7 5.1A11.6 11.6 0 0 1 12 5c6.5 0 10 7 10 7a19.2 19.2 0 0 1-4.1 4.8"/>
                                        <path d="M6.6 6.7C3.8 8.5 2 12 2 12s3.5 7 10 7c1.7 0 3.2-.4 4.5-1"/>
                                        <path d="M9.9 9.9A3 3 0 0 0 14.1 14.1"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="row" style="margin-bottom:20px;">
                            <label class="remember">
                                <input type="checkbox" name="remember" value="1">
                                <span>记住我</span>
                            </label>
                            <a class="link" href="#" data-open-password-help>忘记密码？</a>
                        </div>

                        <button class="submit" type="submit">登录</button>

                        <div class="divider">
                            <span>或使用以下方式登录</span>
                        </div>

                        <div class="alt-grid">
                            <button class="alt-button" type="button">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="#07C160" d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178A1.17 1.17 0 0 1 4.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178 1.17 1.17 0 0 1-1.162-1.178c0-.651.52-1.18 1.162-1.18zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 0 1 .598.082l1.584.926a.272.272 0 0 0 .14.047c.134 0 .24-.111.24-.247 0-.06-.023-.12-.038-.177l-.327-1.233a.582.582 0 0 1-.023-.156.49.49 0 0 1 .201-.398C23.024 18.48 24 16.82 24 14.98c0-3.21-2.931-5.837-6.656-6.088V8.89c-.135-.01-.27-.027-.407-.032zm-2.53 3.274c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982zm4.844 0c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.969-.982z"/>
                                </svg>
                                <span>微信登录</span>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="auth-footer">
                    <p>还没有账号？ <a class="link" href="#">申请入驻</a></p>
                    <div class="footer-links">
                        <a href="#">服务协议</a>
                        <span>|</span>
                        <a href="#">帮助中心</a>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="confirm-modal" id="password-help-modal" aria-hidden="true">
        <div class="confirm-modal-backdrop" data-close-password-help></div>
        <div class="confirm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="password-help-title">
            <div class="confirm-modal-head">
                <span class="confirm-modal-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 18h.01"></path>
                        <path d="M9.09 9a3 3 0 1 1 5.82 1c0 2-3 3-3 3"></path>
                        <path d="M12 2 3 6v6c0 5 3.8 9.7 9 10 5.2-.3 9-5 9-10V6l-9-4Z"></path>
                    </svg>
                </span>
                <h3 class="confirm-modal-title" id="password-help-title">忘记密码了？</h3>
            </div>
            <div class="confirm-modal-copy">
                <p class="confirm-modal-note">
                    <span class="confirm-modal-note-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 5v14"></path>
                            <path d="M5 12h14"></path>
                        </svg>
                    </span>
                    <span>站点操作员忘记密码时，请联系你所在站点的管理员协助处理。</span>
                </p>
                <p class="confirm-modal-note">
                    <span class="confirm-modal-note-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 5v14"></path>
                            <path d="M5 12h14"></path>
                        </svg>
                    </span>
                    <span>管理员忘记密码时，请联系所属客服或平台支持人员。为了保护账号安全，登录页不直接提供找回密码入口。</span>
                </p>
            </div>
            <div class="confirm-modal-actions">
                <button class="confirm-modal-button" type="button" data-close-password-help>我知道了</button>
            </div>
        </div>
    </div>

    <script>
        function showToast(message, type = 'success') {
            document.querySelectorAll('.toast').forEach((item) => item.remove());

            const toast = document.createElement('div');
            const normalizedType = type === 'error' ? 'error' : 'success';
            toast.className = `toast${normalizedType === 'error' ? ' is-error' : ''}`;
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            toast.innerHTML = `
                <span class="toast-icon">
                    ${normalizedType === 'error'
                        ? '<svg viewBox="0 0 24 24"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>'
                        : '<svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>'}
                </span>
                <span class="toast-text"></span>
            `;
            toast.querySelector('.toast-text').textContent = message;
            document.body.appendChild(toast);

            requestAnimationFrame(() => {
                toast.classList.add('is-visible');
            });

            window.setTimeout(() => {
                toast.classList.remove('is-visible');
                window.setTimeout(() => {
                    toast.remove();
                }, 240);
            }, 3000);
        }

        const loginMessages = @json(array_values(array_filter(array_merge(
            ! empty($databaseHealthWarning) ? [$databaseHealthWarning] : [],
            ! empty($adminDisabledMessage) ? [$adminDisabledMessage] : [],
            $errors->all()
        ))));

        if (Array.isArray(loginMessages) && loginMessages.length > 0) {
            loginMessages.forEach((message, index) => {
                window.setTimeout(() => showToast(message, 'error'), index * 220);
            });
        }

        const passwordHelpModal = document.getElementById('password-help-modal');
        const openPasswordHelp = document.querySelector('[data-open-password-help]');

        function togglePasswordHelpModal(visible) {
            if (!passwordHelpModal) {
                return;
            }

            passwordHelpModal.classList.toggle('is-visible', visible);
            passwordHelpModal.setAttribute('aria-hidden', visible ? 'false' : 'true');
        }

        openPasswordHelp?.addEventListener('click', (event) => {
            event.preventDefault();
            togglePasswordHelpModal(true);
        });

        passwordHelpModal?.querySelectorAll('[data-close-password-help]').forEach((trigger) => {
            trigger.addEventListener('click', () => togglePasswordHelpModal(false));
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                togglePasswordHelpModal(false);
            }
        });

        document.querySelector('[data-login-form]')?.addEventListener('submit', (event) => {
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const username = usernameInput instanceof HTMLInputElement ? usernameInput.value.trim() : '';
            const password = passwordInput instanceof HTMLInputElement ? passwordInput.value : '';

            if (usernameInput instanceof HTMLInputElement) {
                usernameInput.value = username;
            }

            if (username === '') {
                event.preventDefault();
                showToast('请输入账号后再登录。', 'error');
                usernameInput?.focus();
                return;
            }

            if (password.trim() === '') {
                event.preventDefault();
                showToast('请输入密码后再登录。', 'error');
                passwordInput?.focus();
            }
        });

        document.querySelectorAll('[data-toggle-password]').forEach((button) => {
            button.addEventListener('click', () => {
                const wrapper = button.closest('.input-wrap');
                const input = wrapper?.querySelector('input');
                const openIcon = button.querySelector('[data-eye-open]');
                const closedIcon = button.querySelector('[data-eye-closed]');

                if (!(input instanceof HTMLInputElement) || !openIcon || !closedIcon) {
                    return;
                }

                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                openIcon.style.display = isPassword ? 'none' : '';
                closedIcon.style.display = isPassword ? '' : 'none';
            });
        });
    </script>
</body>
</html>
