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
    <style>
        :root {
            --primary: {{ $theme['primary'] ?? '#0050b3' }};
            --primary-deep: {{ $theme['primary_deep'] ?? '#003f8f' }};
            --primary-border: {{ $theme['primary_border'] ?? 'rgba(0,80,179,0.18)' }};
            --bg: {{ $theme['bg'] ?? '#f5f7fa' }};
            --panel: {{ $theme['panel'] ?? '#ffffff' }};
            --text: {{ $theme['text'] ?? '#1f2937' }};
            --muted: {{ $theme['muted'] ?? '#8c8c8c' }};
            --line: {{ $theme['line'] ?? '#e5e7eb' }};
            --danger: #dc2626;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "PingFang SC","Microsoft YaHei",sans-serif; background: var(--bg); color: var(--text); }
        .shell { width:min(780px, calc(100% - 24px)); margin: 28px auto; }
        .card { position: relative; padding: 26px 28px; border-radius: 20px; border: 1px solid var(--line); background: var(--panel); box-shadow: 0 8px 18px rgba(15,23,42,0.04); }
        .title { margin:0; font-size:28px; line-height:1.3; font-weight:700; }
        .desc { margin-top:10px; color:#667085; font-size:14px; line-height:1.85; }
        .grid { display:grid; gap:18px; margin-top:24px; }
        .field-label { display:block; margin-bottom:8px; color:#475467; font-size:13px; line-height:1.6; font-weight:700; }
        .field {
            width:100%; min-height:44px; padding:0 14px; border:1px solid #dbe2ea; border-radius:12px; background:#fff;
            color:#1f2937; font-size:14px; line-height:1.4;
        }
        textarea.field { min-height: 180px; padding: 12px 14px; resize: vertical; }
        .textarea-wrap { position: relative; }
        .textarea-counter {
            position: absolute;
            right: 14px;
            bottom: 12px;
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 0 8px;
            border-radius: 999px;
            background: rgba(255,255,255,0.92);
            color: #98a2b3;
            font-size: 12px;
            line-height: 1;
            font-weight: 700;
            pointer-events: none;
            transition: color 0.18s ease, background 0.18s ease;
        }
        .textarea-counter.is-near-limit {
            color: #b45309;
            background: rgba(254, 243, 199, 0.92);
        }
        .textarea-counter.is-over-limit {
            color: #dc2626;
            background: rgba(254, 226, 226, 0.92);
        }
        .captcha-row { display:grid; grid-template-columns: 180px 120px auto; gap:12px; align-items:center; justify-content:start; }
        .captcha-input {
            width: 180px;
            min-width: 0;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .captcha-image { width:120px; height:40px; border-radius:10px; border:1px solid #dbe2ea; background:#fff; display:block; }
        .button {
            display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:0 18px; border-radius:999px;
            border:1px solid transparent; background:var(--primary); color:#fff; font-size:14px; font-weight:700; cursor:pointer;
        }
        .button:hover { background: var(--primary-deep); }
        .button.secondary { background:#fff; color:var(--primary); border-color: var(--primary-border); }
        .button.secondary:hover { background:#fff; color:var(--primary-deep); border-color: var(--primary-border); }
        .actions { display:flex; gap:12px; flex-wrap:wrap; margin-top:24px; }
        .error { margin-top:8px; color:var(--danger); font-size:12px; line-height:1.7; }
        .error[hidden] { display: none; }
        .form-error {
            margin-top: 16px;
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(220, 38, 38, 0.08);
            border: 1px solid rgba(220, 38, 38, 0.16);
            color: var(--danger);
            font-size: 13px;
            line-height: 1.8;
            font-weight: 700;
        }
        .theme-china-red .card {
            border-top: 3px solid rgba(178, 34, 34, 0.56);
        }
        .theme-china-red .button {
            border-radius: 12px;
            letter-spacing: 0.03em;
        }
        .theme-china-red .field {
            border-radius: 10px;
        }
        .theme-education-green .card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 24px;
            bottom: 24px;
            width: 4px;
            border-radius: 999px;
            background: rgba(47, 143, 87, 0.18);
        }
        .theme-education-green .button {
            box-shadow: 0 8px 18px rgba(47, 143, 87, 0.12);
        }
        .theme-education-green .button.secondary {
            box-shadow: none;
        }
        .theme-education-green .field {
            border-radius: 14px;
        }
        .theme-vibrant-orange .card {
            border-radius: 22px;
            box-shadow: 0 12px 26px rgba(201, 106, 16, 0.06);
        }
        .theme-vibrant-orange .button {
            border-radius: 14px;
            box-shadow: 0 10px 22px rgba(242, 140, 40, 0.18);
        }
        .theme-vibrant-orange .button.secondary {
            box-shadow: none;
        }
        .theme-vibrant-orange .field {
            border-radius: 16px;
        }
        @media (max-width: 640px) {
            .card { padding: 20px 18px; }
            .title { font-size:24px; }
            .captcha-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="theme-{{ $themeCode }}">
    <div class="shell">
        <section class="card">
            <h1 class="title">提交留言</h1>

            <form method="post" action="{{ route('site.guestbook.store', $siteQuery) }}">
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
                                <img class="captcha-image" src="{{ $captchaUrl }}{{ str_contains($captchaUrl, '?') ? '&' : '?' }}t={{ time() }}" alt="验证码" id="guestbook-captcha-image">
                                <button class="button secondary" type="button" id="guestbook-refresh-captcha">换一张</button>
                            </div>
                            <div class="error" data-captcha-live-error hidden></div>
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

    <script>
        (() => {
            const image = document.getElementById('guestbook-captcha-image');
            const refresh = document.getElementById('guestbook-refresh-captcha');
            const textarea = document.querySelector('textarea[name="content"][data-textarea-limit]');
            const counter = document.querySelector('[data-textarea-counter]');
            const contentLiveError = document.querySelector('[data-content-live-error]');
            const nameInput = document.querySelector('input[name="name"]');
            const phoneInput = document.querySelector('input[name="phone"]');
            const captchaInput = document.querySelector('input[name="captcha"]');
            const nameLiveError = document.querySelector('[data-name-live-error]');
            const phoneLiveError = document.querySelector('[data-phone-live-error]');
            const captchaLiveError = document.querySelector('[data-captcha-live-error]');
            const form = document.querySelector('form');
            const captchaBase = @json($settings['captcha_enabled'] ? $captchaUrl : null);

            const syncNameValidation = () => {
                if (!nameInput || !nameLiveError) {
                    return true;
                }
                const raw = nameInput.value || '';
                const trimmed = raw.trim();
                let message = '';
                if (raw.length > 20) {
                    message = '称呼不能超过 20 个字符。';
                } else if (trimmed !== '' && Array.from(trimmed).length < 2) {
                    message = '称呼至少需要 2 个字符。';
                } else if (raw.length > 0 && trimmed === '') {
                    message = '称呼不能为空白字符，请重新填写。';
                } else if (trimmed !== '' && !/^[A-Za-z\u4E00-\u9FFF]+(?:[·•\s][A-Za-z\u4E00-\u9FFF]+)*$/.test(trimmed)) {
                    message = '称呼请填写真实姓名，仅支持中文、英文和间隔号。';
                }
                nameLiveError.textContent = message;
                nameLiveError.hidden = message === '';
                return message === '';
            };

            const syncPhoneValidation = () => {
                if (!phoneInput || !phoneLiveError) {
                    return true;
                }
                const raw = phoneInput.value || '';
                const trimmed = raw.replace(/\D+/g, '');
                if (raw !== trimmed) {
                    phoneInput.value = trimmed;
                }
                let message = '';
                if (trimmed.length > 11) {
                    message = '手机号码应为 11 位数字。';
                } else if (trimmed !== '' && !/^1[3-9]\d{9}$/.test(trimmed)) {
                    message = '手机号码格式不正确，请填写 11 位大陆手机号。';
                }
                phoneLiveError.textContent = message;
                phoneLiveError.hidden = message === '';
                return message === '';
            };

            const syncContentValidation = () => {
                if (!textarea || !contentLiveError) {
                    return true;
                }

                const raw = textarea.value || '';
                const trimmed = raw.replace(/\s+/g, '');
                let message = '';

                if (raw.length > 1000) {
                    message = '留言内容不能超过 1000 字。';
                } else if (raw.length > 0 && trimmed.length === 0) {
                    message = '留言内容不能为空白字符，请重新填写。';
                }

                contentLiveError.textContent = message;
                contentLiveError.hidden = message === '';

                return message === '';
            };

            const syncCaptchaValidation = () => {
                if (!captchaInput || !captchaLiveError) {
                    return true;
                }
                const raw = captchaInput.value || '';
                const trimmed = raw.trim();
                let message = '';
                if (raw.length > 4) {
                    message = '验证码应为 4 位字符，请重新输入。';
                } else if (trimmed !== '' && trimmed.length !== 4) {
                    message = '验证码应为 4 位字符，请重新输入。';
                }
                captchaLiveError.textContent = message;
                captchaLiveError.hidden = message === '';
                return message === '';
            };

            const syncCounter = () => {
                if (!textarea || !counter) {
                    return;
                }

                const limit = Number.parseInt(textarea.getAttribute('data-textarea-limit') || '1000', 10);
                const length = Array.from(textarea.value || '').length;
                counter.textContent = `${length} / ${limit}`;
                counter.classList.toggle('is-near-limit', length >= Math.max(0, limit - 120) && length <= limit);
                counter.classList.toggle('is-over-limit', length > limit);
            };

            if (nameInput) {
                nameInput.addEventListener('input', () => {
                    nameLiveError.hidden = true;
                });
                nameInput.addEventListener('blur', syncNameValidation);
            }

            if (phoneInput) {
                phoneInput.addEventListener('input', () => {
                    const raw = phoneInput.value || '';
                    const trimmed = raw.replace(/\D+/g, '');
                    if (raw !== trimmed) {
                        phoneInput.value = trimmed;
                    }
                    phoneLiveError.hidden = true;
                });
                phoneInput.addEventListener('blur', syncPhoneValidation);
            }

            if (textarea && counter) {
                textarea.addEventListener('input', () => {
                    syncCounter();
                    contentLiveError.hidden = true;
                });
                textarea.addEventListener('blur', syncContentValidation);
                syncCounter();
            }

            if (captchaInput) {
                captchaInput.addEventListener('input', () => {
                    captchaInput.value = captchaInput.value.toUpperCase();
                    captchaLiveError.hidden = true;
                });
                captchaInput.addEventListener('blur', syncCaptchaValidation);
            }

            if (form) {
                form.addEventListener('submit', (event) => {
                    const valid = [
                        syncNameValidation(),
                        syncPhoneValidation(),
                        syncContentValidation(),
                        syncCaptchaValidation(),
                    ].every(Boolean);
                    if (!valid) {
                        event.preventDefault();
                        if (!syncNameValidation()) {
                            nameInput?.focus();
                        } else if (!syncPhoneValidation()) {
                            phoneInput?.focus();
                        } else if (!syncContentValidation()) {
                            textarea?.focus();
                        } else if (!syncCaptchaValidation()) {
                            captchaInput?.focus();
                        }
                    }
                });
            }

            if (image && refresh && captchaBase) {
                refresh.addEventListener('click', () => {
                    image.src = `${captchaBase}${captchaBase.includes('?') ? '&' : '?'}t=${Date.now()}`;
                });
            }
        })();
    </script>
</body>
</html>
