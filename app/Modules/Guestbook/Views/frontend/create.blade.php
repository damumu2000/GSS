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

            <form method="post" action="{{ route('site.guestbook.store', $siteQuery) }}" data-captcha-base="{{ $settings['captcha_enabled'] ? $captchaUrl : '' }}" data-captcha-verify="{{ $settings['captcha_enabled'] ? route('site.guestbook.captcha.verify', $siteQuery) : '' }}">
                @csrf
                <div class="grid">
                    <label>
                        <span class="field-label">称呼</span>
                        <input class="field" type="text" name="name" value="{{ old('name') }}" placeholder="请输入真实姓名" maxlength="20">
                        <div class="error" data-name-live-error hidden></div>
                        @error('name')<div class="error">{{ $message }}</div>@enderror
                    </label>

                    <label>
                        <span class="field-label">手机号码</span>
                        <input class="field" type="text" name="phone" value="{{ old('phone') }}" placeholder="请输入 11 位手机号码" maxlength="11" inputmode="numeric">
                        <div class="error" data-phone-live-error hidden></div>
                        @error('phone')<div class="error">{{ $message }}</div>@enderror
                    </label>

                    <label>
                        <span class="field-label">留言内容</span>
                        <div class="textarea-wrap">
                            <textarea class="field" name="content" placeholder="请输入留言内容" maxlength="1000" data-textarea-limit="1000">{{ old('content') }}</textarea>
                            <span class="textarea-counter" data-textarea-counter>0 / 1000</span>
                        </div>
                        <div class="error" data-content-live-error hidden></div>
                        @error('content')<div class="error">{{ $message }}</div>@enderror
                    </label>

                    @if ($settings['captcha_enabled'])
                        <label>
                            <span class="field-label">验证码</span>
                            <div class="captcha-row">
                                <input class="field captcha-input" type="text" name="captcha" value="{{ old('captcha') }}" placeholder="请输入验证码" maxlength="4" inputmode="text" autocomplete="off">
                                <img class="captcha-image" src="{{ $captchaUrl }}{{ str_contains($captchaUrl, '?') ? '&' : '?' }}t={{ time() }}" alt="验证码，点击更换" id="guestbook-captcha-image" role="button" tabindex="0">
                                <div class="error captcha-inline-error" data-captcha-live-error hidden></div>
                            </div>
                            @error('captcha')<div class="error">{{ $message }}</div>@enderror
                        </label>
                    @endif
                </div>

                @error('form')<div class="form-error">{{ $message }}</div>@enderror

                <div class="actions">
                    <button class="button" type="submit">提交留言</button>
                    <a class="button secondary" href="{{ route('site.guestbook.index', $siteQuery) }}">返回列表</a>
                </div>
            </form>
        </section>
    </div>

    <script src="{{ asset('js/guestbook-frontend-create.js') }}"></script>
</body>
</html>
