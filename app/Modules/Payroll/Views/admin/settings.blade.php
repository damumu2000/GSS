@extends('layouts.admin')

@section('title', '工资查询配置 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 工资查询配置')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/payroll-admin-header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/payroll-admin-settings.css') }}">
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
                <div class="payroll-grid payroll-grid--spaced">
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
                <div class="payroll-grid payroll-grid--single">
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
