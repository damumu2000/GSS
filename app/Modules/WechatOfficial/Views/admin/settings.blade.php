@extends('layouts.admin')

@section('title', '微信公众号配置 - 功能模块 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 微信公众号配置')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/wechat-official-admin.css') }}">
@endpush

@section('content')
    <form method="POST" action="{{ route('admin.wechat-official.settings.update') }}">
        @csrf

        <section class="page-header">
            <div class="page-header-main">
                <h2 class="page-header-title">公众号配置</h2>
                <div class="page-header-desc">先把公众号基础参数落好，后面的菜单同步、文章推送和素材同步就可以直接使用。</div>
            </div>
            <div class="page-header-actions">
                <button class="button" type="submit">保存配置</button>
            </div>
        </section>

        @include('wechat_official::admin._nav')

        <div class="wechat-official-settings-shell">
            <section class="wechat-official-panel">
                <div class="wechat-official-panel-head">
                    <div>
                        <h2 class="wechat-official-panel-title">基础参数</h2>
                        <div class="wechat-official-panel-desc">敏感信息采用加密存储，留空提交时会保留当前已保存的值。</div>
                    </div>
                </div>
                <div class="wechat-official-form-grid">
                    <div class="wechat-official-toggle-row">
                        <div class="wechat-official-toggle-grid">
                            <label class="wechat-official-toggle-card">
                                <span class="wechat-official-toggle-control">
                                    <input class="wechat-official-toggle-input" type="checkbox" name="enabled" value="1" @checked(! empty($settings['enabled']))>
                                    <span class="wechat-official-toggle-track"></span>
                                </span>
                                <span class="wechat-official-toggle-copy">
                                    <span class="wechat-official-label">启用微信公众号模块</span>
                                    <span class="wechat-official-note">关闭后，菜单、文章、素材和日志入口将暂停使用；配置页仍可进入重新启用。</span>
                                </span>
                            </label>
                        </div>
                        <div class="wechat-official-toggle-side">
                            <button class="button button-secondary" type="submit" formaction="{{ route('admin.wechat-official.settings.check') }}">检测配置</button>
                        </div>
                    </div>

                    <label class="wechat-official-field">
                        <span class="wechat-official-label">公众号名称</span>
                        <input class="input" type="text" name="official_name" value="{{ old('official_name', $settings['official_name']) }}">
                    </label>

                    <label class="wechat-official-field">
                        <span class="wechat-official-label">AppID</span>
                        <input class="input" type="text" name="app_id" value="{{ old('app_id', $settings['app_id']) }}">
                    </label>

                    <label class="wechat-official-field">
                        <span class="wechat-official-label">AppSecret</span>
                        <input class="input" type="password" name="app_secret" value="" autocomplete="new-password" placeholder="已保存时留空可保持不变">
                    </label>

                    <label class="wechat-official-field">
                        <span class="wechat-official-label">Token</span>
                        <input class="input" type="password" name="token" value="" autocomplete="new-password" placeholder="已保存时留空可保持不变">
                    </label>

                    <label class="wechat-official-field">
                        <span class="wechat-official-label">EncodingAESKey</span>
                        <input class="input" type="password" name="encoding_aes_key" value="" autocomplete="new-password" placeholder="43 位密钥，已保存时留空可保持不变">
                    </label>
                </div>
            </section>

        </div>
    </form>
@endsection
