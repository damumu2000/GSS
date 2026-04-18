@php($adminBrandSettings = app(\App\Support\SystemSettings::class)->formDefaults())
@php($loginHeroImage = '/BG_1800x2400.jpg')
@php($loginSiteName = $loginSiteBrand?->name ?: '站点')
@php($loginSeoTitle = $loginSiteBrand?->seo_title ?: $loginSiteName)
@php($loginSeoKeywords = $loginSiteBrand?->seo_keywords)
@php($loginSeoDescription = $loginSiteBrand?->seo_description ?: $loginSiteName)
@php($showLoginCaptchaField = ! empty($loginCaptchaRequired) || $errors->has('captcha'))
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统登录 - {{ $loginSiteName }}</title>
    @if (! empty($loginSeoKeywords))
        <meta name="keywords" content="{{ $loginSeoKeywords }}">
    @endif
    @if (! empty($loginSeoDescription))
        <meta name="description" content="{{ $loginSeoDescription }}">
    @endif
    @if (! empty($loginSiteBrand?->favicon))
        <link rel="icon" href="{{ $loginSiteBrand->favicon }}">
    @endif
    <link rel="stylesheet" href="/css/login.css">
</head>
<body data-login-messages='@json(array_values(array_filter(array_merge(
    ! empty($databaseHealthWarning) ? [$databaseHealthWarning] : [],
    ! empty($adminDisabledMessage) ? [$adminDisabledMessage] : [],
    $errors->all()
))))' data-login-captcha-base="{{ route('login.captcha') }}" data-login-captcha-check="{{ route('login.captcha.check') }}" data-login-captcha-required="{{ ! empty($loginCaptchaRequired) ? '1' : '0' }}">
    <div class="page">
        <section class="intro">
            <div class="intro-bg" aria-hidden="true">
                <img class="intro-bg-image" src="{{ $loginHeroImage }}" alt="">
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
                                <input id="password" type="password" name="password" placeholder="请输入您的密码" autocomplete="current-password" required>
                                <button class="toggle-password" type="button" data-toggle-password aria-label="显示或隐藏密码">
                                    <svg data-eye-open viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg class="is-hidden" data-eye-closed viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m3 3 18 18"/>
                                        <path d="M10.7 5.1A11.6 11.6 0 0 1 12 5c6.5 0 10 7 10 7a19.2 19.2 0 0 1-4.1 4.8"/>
                                        <path d="M6.6 6.7C3.8 8.5 2 12 2 12s3.5 7 10 7c1.7 0 3.2-.4 4.5-1"/>
                                        <path d="M9.9 9.9A3 3 0 0 0 14.1 14.1"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        @if ($showLoginCaptchaField)
                            <div class="field login-captcha-field">
                                <label for="captcha">验证码</label>
                                <div class="captcha-row">
                                    <div class="input-wrap captcha">
                                        <input id="captcha" type="text" name="captcha" value="{{ old('captcha') }}" maxlength="4" inputmode="text" autocomplete="off" required>
                                    </div>
                                    <div class="captcha-image-wrap" id="login-captcha-trigger" tabindex="0" role="button" aria-label="验证码，点击更换">
                                        <img class="captcha-image" src="{{ route('login.captcha') }}?t={{ time() }}" alt="验证码" id="login-captcha-image">
                                        <span class="captcha-tooltip">点击更换</span>
                                    </div>
                                </div>
                                <div class="captcha-hint">为保障账号安全，请填写图形验证码。</div>
                            </div>
                        @endif

                        <div class="row login-options">
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

    <script src="/js/toast-config.js"></script>
    <script src="/js/login.js"></script>
</body>
</html>
