<!DOCTYPE html>
<html lang="zh-CN">
<head>
    @php
        $theme = $settings['theme_profile'] ?? [];
        $themeCode = $settings['theme'] ?? 'default';
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>提交留言 - {{ $settings['name'] }} - {{ $site->name }}</title>
        <link rel="stylesheet" href="{{ asset('css/guestbook-frontend-create.css') }}">

</head>
<body class="theme-{{ $themeCode }}">
    <div class="shell">
        <section class="card">
            <h1 class="title">提交留言</h1>

            <form method="post" action="{{ route('site.guestbook.store', $siteQuery) }}" data-guestbook-form data-guestbook-success-modal="guestbook-success-modal" data-guestbook-success-redirect="{{ route('site.guestbook.index', $siteQuery) }}" data-captcha-url="{{ $settings['captcha_enabled'] ? $captchaUrl : '' }}" data-captcha-verify-url="{{ $settings['captcha_enabled'] ? route('site.guestbook.captcha.verify', $siteQuery) : '' }}" data-csrf-token="{{ csrf_token() }}" novalidate>
                @csrf
                <input type="text" name="website" value="" hidden tabindex="-1" autocomplete="off" data-guestbook-honeypot>
                <div class="grid">
                    <label>
                        <span class="field-label">称呼</span>
                        <input class="field" type="text" name="name" value="{{ old('name') }}" placeholder="请输入真实姓名" maxlength="20" data-guestbook-field="name">
                        <div class="error" data-guestbook-error-for="name" hidden></div>
                        @error('name')<div class="error">{{ $message }}</div>@enderror
                    </label>

                    <label>
                        <span class="field-label">手机号码</span>
                        <input class="field" type="text" name="phone" value="{{ old('phone') }}" placeholder="请输入 11 位手机号码" maxlength="11" inputmode="numeric" data-guestbook-field="phone">
                        <div class="error" data-guestbook-error-for="phone" hidden></div>
                        @error('phone')<div class="error">{{ $message }}</div>@enderror
                    </label>

                    <label>
                        <span class="field-label">留言内容</span>
                        <div class="textarea-wrap">
                            <textarea class="field" name="content" placeholder="请输入留言内容" maxlength="1000" data-guestbook-field="content">{{ old('content') }}</textarea>
                            <span class="textarea-counter" data-guestbook-counter>0 / 1000</span>
                        </div>
                        <div class="error" data-guestbook-error-for="content" hidden></div>
                        @error('content')<div class="error">{{ $message }}</div>@enderror
                    </label>

                    @if ($settings['captcha_enabled'])
                        <label data-guestbook-captcha-block @if (! session('guestbook_captcha_required') && ! $errors->has('captcha')) hidden @endif>
                            <span class="field-label">验证码</span>
                            <div class="captcha-row">
                                <input class="field captcha-input" type="text" name="captcha" value="{{ old('captcha') }}" placeholder="请输入验证码" maxlength="4" inputmode="text" autocomplete="off" data-guestbook-field="captcha">
                                <img class="captcha-image" src="{{ $captchaUrl }}{{ str_contains($captchaUrl, '?') ? '&' : '?' }}t={{ time() }}" alt="验证码，点击更换" role="button" tabindex="0" data-guestbook-captcha-image data-guestbook-captcha-trigger>
                                <div class="error captcha-inline-error" data-guestbook-error-for="captcha" hidden></div>
                            </div>
                            @error('captcha')<div class="error">{{ $message }}</div>@enderror
                        </label>
                    @endif
                </div>

                @error('form')<div class="form-error">{{ $message }}</div>@enderror
                <div class="form-error" data-guestbook-form-error hidden></div>
                <div class="form-success" data-guestbook-success-feedback hidden></div>

                <div class="actions">
                    <button class="button" type="submit" data-guestbook-submit>提交留言</button>
                    <a class="button secondary" href="{{ route('site.guestbook.index', $siteQuery) }}">返回列表</a>
                </div>
            </form>
        </section>
    </div>

    <div id="guestbook-success-modal" class="guestbook-success-modal hidden">
        <div class="guestbook-success-backdrop" data-guestbook-success-backdrop></div>
        <div class="guestbook-success-dialog" role="dialog" aria-modal="true" aria-labelledby="guestbook-success-title">
            <h2 id="guestbook-success-title">提交成功</h2>
            <p data-guestbook-success-message>您的信息已提供成功。</p>
            <div class="guestbook-success-actions">
                <button type="button" class="button" data-guestbook-success-close>确定</button>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/guestbook-form.js') }}?v=20260513-guestbook-fix3"></script>
</body>
</html>
