@extends('layouts.admin')

@section('title', '新增平台管理员 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 平台管理员 / 新增平台管理员')

@php
    $selectedRoleId = (int) old('role_id', 0);
@endphp

@push('styles')
    @include('admin.platform.users._form_styles')
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">新增平台管理员</h2>
            <div class="page-header-desc">创建新的平台级账号，并分配平台角色以控制站点管理、主题市场和平台配置能力。</div>
        </div>
        <div class="topbar-right">
            <a class="button secondary" href="{{ route('admin.platform.users.index') }}">返回平台管理员</a>
            <button class="button" type="submit" form="platform-user-create-form" data-loading-text="创建中...">创建平台管理员</button>
        </div>
    </section>

    <section class="platform-user-shell">
        <form id="platform-user-create-form" method="POST" action="{{ route('admin.platform.users.store') }}">
            @csrf

            <div class="platform-user-body">
                <div class="platform-user-layout-grid">
                    <div class="platform-user-column">
                        <section class="platform-user-module">
                            <div class="platform-user-module-header">
                                <span class="platform-user-module-accent"></span>
                                <div class="platform-user-module-title">基础信息</div>
                            </div>
                            <div class="platform-user-module-body">
                                <div class="platform-user-form-grid">
                                    <label class="field-group">
                                        <span class="field-label">用户名</span>
                                        <input class="field @error('username') is-error @enderror" id="username" type="text" name="username" value="{{ old('username') }}" @error('username') aria-invalid="true" @enderror>
                                        <span class="field-note">作为平台后台登录账号使用，建议保持简洁稳定。</span>
                                        @error('username')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">姓名</span>
                                        <input class="field @error('name') is-error @enderror" id="name" type="text" name="name" value="{{ old('name') }}" @error('name') aria-invalid="true" @enderror>
                                        <span class="field-note">用于列表展示、审计日志和平台内部识别。</span>
                                        @error('name')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>
                                </div>

                                <div class="platform-user-form-grid">
                                    <label class="field-group">
                                        <span class="field-label">邮箱</span>
                                        <input class="field @error('email') is-error @enderror" id="email" type="text" name="email" value="{{ old('email') }}" @error('email') aria-invalid="true" @enderror>
                                        <span class="field-note">用于通知、找回信息或接收平台业务提醒，可留空。</span>
                                        @error('email')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">手机号</span>
                                        <input class="field @error('mobile') is-error @enderror" id="mobile" type="text" name="mobile" value="{{ old('mobile') }}" @error('mobile') aria-invalid="true" @enderror>
                                        <span class="field-note">用于平台联系或后续短信提醒，可留空。</span>
                                        @error('mobile')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>
                                </div>
                            </div>
                        </section>

                        <section class="platform-user-module">
                            <div class="platform-user-module-header">
                                <span class="platform-user-module-accent"></span>
                                <div class="platform-user-module-title">安全设置</div>
                            </div>
                            <div class="platform-user-module-body">
                                <label class="field-group">
                                    <span class="field-label">初始密码</span>
                                    <input class="field @error('password') is-error @enderror" id="password" type="password" name="password" @error('password') aria-invalid="true" @enderror>
                                    <span class="field-note">请设置 8 位以上密码，创建后管理员可自行修改。</span>
                                    @error('password')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                </label>
                            </div>
                        </section>
                    </div>

                    <div class="platform-user-column">
                        <section class="platform-user-module">
                            <div class="platform-user-module-header">
                                <span class="platform-user-module-accent"></span>
                                <div class="platform-user-module-title">创建说明</div>
                            </div>
                            <div class="platform-user-module-body">
                                <div class="platform-user-status-badge">待创建</div>
                                <div class="platform-user-status-divider"></div>
                                <div class="platform-user-status-meta">
                                    <div class="platform-user-status-row">
                                        <span class="platform-user-status-label">账号状态</span>
                                        <span class="platform-user-status-value">创建后自动启用</span>
                                    </div>
                                    <div class="platform-user-status-row">
                                        <span class="platform-user-status-label">当前角色</span>
                                        <span class="platform-user-status-value">{{ $selectedRoleId ? '已选择' : '未选择' }}</span>
                                    </div>
                                    <div class="platform-user-status-row">
                                        <span class="platform-user-status-label">创建结果</span>
                                        <span class="platform-user-status-value">立即生效</span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="platform-user-module">
                            <div class="platform-user-module-header">
                                <span class="platform-user-module-accent"></span>
                                <div class="platform-user-module-title">平台角色</div>
                            </div>
                            <div class="platform-user-module-body">
                                <div class="platform-user-role-grid">
                                    @foreach ($platformRoles as $platformRole)
                                        <label class="platform-user-role-chip">
                                            <input type="radio" name="role_id" value="{{ $platformRole->id }}" @checked($platformRole->id === $selectedRoleId)>
                                            <span class="platform-user-role-dot"></span>
                                            <span class="platform-user-role-name">{{ $platformRole->name }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                <div class="platform-user-footer-note">创建成功后，该账号会立即出现在平台管理员列表中，并按所选单一角色获得对应平台权限。</div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
    @include('admin.platform.users._form_scripts')
@endpush
