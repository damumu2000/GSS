@extends('layouts.admin')

@section('title', '工资查询配置 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 工资查询配置')

@push('styles')
    <style>
        .payroll-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            padding: 24px 32px;
            margin: -28px -28px 24px;
            background: #fff;
            border-bottom: 1px solid #f0f0f0;
        }
        .payroll-header-title { margin: 0; color: #262626; font-size: 20px; line-height: 1.4; font-weight: 700; }
        .payroll-header-desc { margin-top: 8px; color: #8c8c8c; font-size: 14px; line-height: 1.75; }
        .payroll-shell { display: grid; gap: 18px; width: 100%; margin-top: 18px; }
        .payroll-panel {
            padding: 22px 24px 24px;
            border: 1px solid #eef2f6;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }
        .payroll-panel-title { margin: 0; color: #262626; font-size: 17px; line-height: 1.5; font-weight: 700; }
        .payroll-panel-desc { margin-top: 8px; color: #8c8c8c; font-size: 13px; line-height: 1.8; }
        .payroll-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 22px; margin-top: 18px; }
        .payroll-field { display: grid; gap: 8px; min-width: 0; }
        .payroll-field--wide { grid-column: 1 / -1; }
        .payroll-field--compact .input { max-width: min(760px, 50%); }
        .payroll-label { color: #4b5563; font-size: 13px; line-height: 1.5; font-weight: 700; }
        .payroll-note { color: #98a2b3; font-size: 12px; line-height: 1.7; }
        .payroll-secret-row {
            display: block;
        }
        .payroll-toggle-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-top: 18px; }
        .payroll-toggle-card {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            align-items: center;
            gap: 16px;
            padding: 14px 16px;
            border: 1px solid #eef1f5;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
        }
        .payroll-toggle-control {
            position: relative;
            width: 52px;
            height: 30px;
            flex-shrink: 0;
            overflow: hidden;
        }
        .payroll-toggle-input {
            position: absolute;
            opacity: 0;
            inset: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            cursor: pointer;
            z-index: 2;
        }
        .payroll-toggle-track {
            position: relative;
            width: 52px;
            height: 30px;
            border-radius: 999px;
            border: 1px solid #d8dee8;
            background: #eef2f7;
            display: inline-flex;
            align-items: center;
            transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .payroll-toggle-track::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 22px;
            height: 22px;
            border-radius: 999px;
            background: #ffffff;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.14);
            transition: transform 0.2s ease, background 0.2s ease;
        }
        .payroll-toggle-copy {
            display: grid;
            gap: 2px;
        }
        .payroll-toggle-card:hover .payroll-toggle-track {
            border-color: rgba(0, 71, 171, 0.22);
        }
        .payroll-toggle-control:has(.payroll-toggle-input:checked) .payroll-toggle-track {
            background: rgba(0, 71, 171, 0.12);
            border-color: rgba(0, 71, 171, 0.28);
            box-shadow: 0 0 0 3px rgba(0, 71, 171, 0.08);
        }
        .payroll-toggle-control:has(.payroll-toggle-input:checked) .payroll-toggle-track::after {
            transform: translateX(22px);
            background: var(--primary, #0047AB);
        }
        .payroll-toggle-control:has(.payroll-toggle-input:focus-visible) .payroll-toggle-track {
            box-shadow: 0 0 0 3px rgba(0, 71, 171, 0.10);
            border-color: rgba(0, 71, 171, 0.3);
        }
        .payroll-actions { display: flex; justify-content: flex-end; gap: 10px; }
        @media (max-width: 960px) {
            .payroll-grid,
            .payroll-toggle-grid { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@section('content')
    <form method="POST" action="{{ route('admin.payroll.settings.update') }}">
        @csrf

        <div class="payroll-header">
            <div>
                <h1 class="payroll-header-title">工资查询配置</h1>
                <div class="payroll-header-desc">集中维护工资查询模块开关、前台注册策略和微信登录参数。</div>
            </div>
            <div class="page-header-actions">
                <button class="button" type="submit">保存配置</button>
            </div>
        </div>

        @include('payroll::admin._nav')

        <div class="payroll-shell">
            <section class="payroll-panel">
                <h2 class="payroll-panel-title">模块开关</h2>
                <div class="payroll-panel-desc">控制工资查询入口和首次微信访问时的登记策略。</div>
                <div class="payroll-toggle-grid">
                    <label class="payroll-toggle-card">
                        <span class="payroll-toggle-control">
                            <input class="payroll-toggle-input" type="checkbox" name="enabled" value="1" @checked(! empty($settings['enabled']))>
                            <span class="payroll-toggle-track"></span>
                        </span>
                        <span class="payroll-toggle-copy">
                            <span class="payroll-label">启用工资查询模块</span>
                            <span class="payroll-note">关闭后，该功能前端将停止使用。</span>
                        </span>
                    </label>

                    <label class="payroll-toggle-card">
                        <span class="payroll-toggle-control">
                            <input class="payroll-toggle-input" type="checkbox" name="registration_enabled" value="1" @checked(! empty($settings['registration_enabled']))>
                            <span class="payroll-toggle-track"></span>
                        </span>
                        <span class="payroll-toggle-copy">
                            <span class="payroll-label">允许前台自动注册</span>
                            <span class="payroll-note">关闭后，微信首次打开页面将提示已禁止自动注册。</span>
                        </span>
                    </label>
                </div>
                <div class="payroll-grid" style="margin-top: 18px;">
                    <label class="payroll-field payroll-field--wide payroll-field--compact">
                        <span class="payroll-label">注册关闭提示文案</span>
                        <input class="input" type="text" name="registration_disabled_message" value="{{ old('registration_disabled_message', $settings['registration_disabled_message']) }}">
                        @error('registration_disabled_message')<div class="field-error">{{ $message }}</div>@enderror
                    </label>
                </div>
            </section>

            <section class="payroll-panel">
                <h2 class="payroll-panel-title">微信登录配置</h2>
                <div class="payroll-panel-desc">
                    APPID 和 APPSECRET 请到微信公众平台对应公众号的开发设置中获取，填写后即可用于网页授权登录。
                </div>
                <div class="payroll-grid" style="grid-template-columns: 1fr;">
                    <label class="payroll-field payroll-field--compact">
                        <span class="payroll-label">APPID</span>
                        <input class="input" type="text" name="wechat_app_id" value="{{ old('wechat_app_id', $settings['wechat_app_id']) }}">
                        @error('wechat_app_id')<div class="field-error">{{ $message }}</div>@enderror
                    </label>

                    <label class="payroll-field payroll-field--compact">
                        <span class="payroll-label">APPSECRET</span>
                        <div class="payroll-secret-row">
                            <input class="input" type="password" name="wechat_app_secret" value="{{ old('wechat_app_secret', '') }}" autocomplete="new-password" placeholder="已保存密钥，留空则保持不变">
                        </div>
                        <div class="payroll-note">已保存密钥时默认不回显，留空提交不会清除当前 APPSECRET。</div>
                        @error('wechat_app_secret')<div class="field-error">{{ $message }}</div>@enderror
                    </label>

                </div>
            </section>
        </div>
    </form>
@endsection
