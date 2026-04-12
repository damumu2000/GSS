@extends('layouts.admin')

@section('title', '编辑平台管理员 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 平台管理员 / 编辑平台管理员')

@php
    $selectedRoleId = (int) old('role_id', $selectedRoleId ?? 0);
    $selectedRoleName = collect($platformRoles)->firstWhere('id', $selectedRoleId)?->name ?? '未选择';
    $displayName = $user->name ?: $user->username;
    $superAdminRoleId = (int) ($superAdminRoleId ?? 0);
    $isSuperAdmin = (bool) ($isSuperAdmin ?? false);
    $isSelfEditing = (int) auth()->id() === (int) $user->id;
@endphp

@push('styles')
    @include('admin.platform.users._form_styles')
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">编辑平台管理员</h2>
            <div class="page-header-desc">当前正在维护 {{ $displayName }} 的账号信息、联系方式、安全设置与平台角色。</div>
        </div>
        <div class="topbar-right">
            <a class="button secondary" href="{{ route('admin.platform.users.index') }}">返回平台管理员</a>
            <button class="button" type="submit" form="platform-user-edit-form" data-loading-text="保存中...">保存平台管理员</button>
        </div>
    </section>

    <section class="platform-user-shell">
        <form id="platform-user-edit-form" method="POST" action="{{ route('admin.platform.users.update', $user->id) }}" data-validation-errors='@json($errors->all())'>
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
                                        <input class="field @error('username') is-error @enderror" id="username" type="text" name="username" value="{{ old('username', $user->username) }}" @error('username') aria-invalid="true" @enderror>
                                        <span class="field-note">作为平台后台登录账号使用，建议保持简洁稳定。</span>
                                        @error('username')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">姓名</span>
                                        <input class="field @error('name') is-error @enderror" id="name" type="text" name="name" value="{{ old('name', $user->name) }}" @error('name') aria-invalid="true" @enderror>
                                        <span class="field-note">用于列表展示、审计日志和平台内部识别。</span>
                                        @error('name')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>
                                </div>

                                <div class="platform-user-form-grid">
                                    <label class="field-group">
                                        <span class="field-label">邮箱</span>
                                        <input class="field @error('email') is-error @enderror" id="email" type="text" name="email" value="{{ old('email', $user->email) }}" @error('email') aria-invalid="true" @enderror>
                                        <span class="field-note">用于通知、找回信息或接收平台业务提醒，可留空。</span>
                                        @error('email')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">手机号</span>
                                        <input class="field @error('mobile') is-error @enderror" id="mobile" type="text" name="mobile" value="{{ old('mobile', $user->mobile) }}" @error('mobile') aria-invalid="true" @enderror>
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
                                    <span class="field-label">重置密码</span>
                                    <input class="field @error('password') is-error @enderror" id="password" type="password" name="password" placeholder="留空则不修改" @error('password') aria-invalid="true" @enderror>
                                    <span class="field-note">如需重置密码，请填写新的 8 位以上密码。</span>
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
                                <div class="platform-user-module-title">账号概览</div>
                            </div>
                            <div class="platform-user-module-body">
                                <div class="platform-user-status-badge">启用中</div>
                                <div class="platform-user-status-divider"></div>
                                <div class="platform-user-status-meta">
                                    <div class="platform-user-status-row">
                                        <span class="platform-user-status-label">账号名称</span>
                                        <span class="platform-user-status-value">{{ $displayName }}</span>
                                    </div>
                                    <div class="platform-user-status-row">
                                        <span class="platform-user-status-label">创建时间</span>
                                        <span class="platform-user-status-value">{{ optional($user->created_at)->format('Y-m-d') ?: '未记录' }}</span>
                                    </div>
                                    <div class="platform-user-status-row">
                                        <span class="platform-user-status-label">最近登录</span>
                                        <span class="platform-user-status-value">{{ optional($user->last_login_at)->format('Y-m-d H:i') ?: '暂无记录' }}</span>
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
                                <div class="platform-user-summary-card">
                                    <div class="platform-user-summary-list">
                                        <div class="platform-user-summary-item">
                                            <span class="platform-user-summary-key">当前角色</span>
                                            <span class="platform-user-summary-value">{{ $selectedRoleName }}</span>
                                        </div>
                                        <div class="platform-user-summary-item">
                                            <span class="platform-user-summary-key">账号标识</span>
                                            <span class="platform-user-summary-value">{{ $user->username }}</span>
                                        </div>
                                    </div>

                                    <div class="platform-user-role-grid">
                                        @if (($isSuperAdmin || $isSelfEditing) && $selectedRoleId > 0)
                                            <input type="hidden" name="role_id" value="{{ $selectedRoleId }}">
                                        @elseif ($isSuperAdmin && $superAdminRoleId > 0)
                                            <input type="hidden" name="role_id" value="{{ $superAdminRoleId }}">
                                        @endif
                                        @foreach ($platformRoles as $platformRole)
                                            <label class="platform-user-role-chip @if(($isSuperAdmin && $platformRole->code !== 'super_admin') || $isSelfEditing) is-disabled @endif">
                                                <input type="radio" name="role_id" value="{{ $platformRole->id }}" @checked($platformRole->id === $selectedRoleId) @disabled(($isSuperAdmin && $platformRole->code !== 'super_admin') || $isSelfEditing)>
                                                <span class="platform-user-role-dot"></span>
                                                <span class="platform-user-role-name">{{ $platformRole->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>

                                    <div class="platform-user-footer-note">
                                        @if ($isSuperAdmin)
                                            总管理员权限已锁定，不可移除或更改。
                                        @elseif ($isSelfEditing)
                                            当前登录账号不能修改自己的平台角色。
                                        @else
                                            保存后，该平台管理员的菜单可见范围和平台操作权限会立即按这里的单一角色配置生效。
                                        @endif
                                    </div>
                                </div>
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
