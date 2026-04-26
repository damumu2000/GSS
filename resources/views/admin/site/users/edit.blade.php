@extends('layouts.admin')

@section('title', '编辑操作员 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 操作员管理 / 编辑操作员')

@php
    $selectedRoleValue = old('role_id', $selectedRoleId ? 'site:' . $selectedRoleId : '');
    $selectedStatusValue = (string) old('status', (string) $user->status);
    $selectedRoleName = collect($siteRoles)->firstWhere('id', (int) $selectedRoleId)?->name ?? '未分配';
    $statusLabel = $selectedStatusValue === '0' ? '停用' : '启用中';
    $selectedRoleCanManageContent = (bool) collect($siteRoles)->firstWhere('id', (int) $selectedRoleId)?->can_manage_content;
    $selectedManagedChannels = collect($channels)
        ->filter(fn ($channel) => in_array((int) $channel->id, $selectedChannelIds, true))
        ->values();
    $displayName = trim((string) old('name', $user->name)) ?: '未设置姓名';
    $displayUsername = trim((string) old('username', $user->username)) ?: '未设置账号';
    $globalFieldsReadonly = ! $canManageGlobalAccountFields;
    $avatarSeed = trim((string) old('name', $user->name)) ?: trim((string) old('username', $user->username)) ?: '账';
    $avatarInitial = function_exists('mb_substr') ? mb_substr($avatarSeed, 0, 1) : substr($avatarSeed, 0, 1);
@endphp

@push('styles')
    @include('admin.site.attachments._attachment_library_styles')
    <link rel="stylesheet" href="/css/site-users.css">
@endpush

@push('scripts')
    <script src="/vendor/tinymce/tinymce.min.js"></script>
    @include('admin.site.attachments._attachment_library_scripts')
    <script src="/js/site-users-form.js"></script>
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">编辑操作员</h2>
            <div class="page-header-desc">当前站点：{{ $currentSite->name }}。正在维护 {{ $user->name ?: $user->username }} 的账号信息、角色分配和联系方式。</div>
        </div>
        <div class="topbar-right">
            <a class="button secondary" href="{{ route('admin.site-users.index') }}">返回操作员管理</a>
            <button class="button" type="submit" form="site-user-edit-form" data-loading-text="保存中...">保存操作员</button>
        </div>
    </section>

    <section class="site-user-shell">
        <form id="site-user-edit-form" method="POST" action="{{ route('admin.site-users.update', $user->id) }}" data-validation-errors='@json($errors->all())' data-require-password="0">
            @csrf

            <div class="site-user-body">
                <div class="site-user-layout-grid">
                    <div class="site-user-column">
                        @if ($isSelfEditing)
                        <div class="site-user-status-hero">
                            <input type="hidden" name="avatar" id="avatar" value="{{ old('avatar', $user->avatar) }}">
                            <div class="site-user-status-avatar-card" data-avatar-trigger>
                                <div class="site-user-status-avatar" data-avatar-preview>
                                    <span class="site-user-status-avatar-fallback" data-status-avatar data-fallback="账">{{ $avatarInitial }}</span>
                                </div>
                                <div class="site-user-status-avatar-actions">更换头像</div>
                                <button class="site-user-status-avatar-remove" type="button" data-avatar-remove aria-label="清除头像">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M6 6l12 12M18 6L6 18"></path>
                                    </svg>
                                </button>
                            </div>
                            <div class="site-user-status-copy">
                                <div class="site-user-status-copy-eyebrow">Account Profile</div>
                                <div class="site-user-status-copy-name" data-status-display-name data-empty-name="未设置姓名">{{ $displayName }}</div>
                                <div class="site-user-status-copy-subline">
                                    <span>后台操作员账号</span>
                                    <code data-status-display-username data-empty-username="未设置账号">{{ $displayUsername }}</code>
                                </div>
                                <div class="site-user-status-avatar-note" data-avatar-note>点击头像，从资源库选择或上传。</div>
                            </div>

                            <div class="site-user-status-side">
                                <div class="site-user-status-side-item">
                                    <span class="site-user-status-side-label">创建时间</span>
                                    <span class="site-user-status-side-value">{{ optional($user->created_at)->format('Y-m-d H:i') ?: '未记录' }}</span>
                                </div>
                                <div class="site-user-status-side-item">
                                    <span class="site-user-status-side-label">最后登录</span>
                                    <span class="site-user-status-side-value">{{ optional($user->last_login_at)->format('Y-m-d H:i') ?: '暂无记录' }}</span>
                                </div>
                            </div>
                        </div>
                        @elseif ($canManageStatusSelection)
                        <section class="site-user-module">
                            <div class="site-user-module-header">
                                <span class="site-user-module-accent"></span>
                                <div class="site-user-module-title">账号状态</div>
                            </div>
                            <div class="site-user-module-body site-user-status-module-body">
                                <div class="site-user-status-layout">
                                    <div class="site-user-status-hero">
                                        <input type="hidden" name="avatar" id="avatar" value="{{ old('avatar', $user->avatar) }}">
                                        <div class="site-user-status-avatar-card" data-avatar-trigger>
                                            <div class="site-user-status-avatar" data-avatar-preview>
                                                <span class="site-user-status-avatar-fallback" data-status-avatar data-fallback="账">{{ $avatarInitial }}</span>
                                            </div>
                                            <div class="site-user-status-avatar-actions">更换头像</div>
                                            <button class="site-user-status-avatar-remove" type="button" data-avatar-remove aria-label="清除头像">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M6 6l12 12M18 6L6 18"></path>
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="site-user-status-copy">
                                            <div class="site-user-status-copy-eyebrow">Account Profile</div>
                                            <div class="site-user-status-copy-name" data-status-display-name data-empty-name="未设置姓名">{{ $displayName }}</div>
                                            <div class="site-user-status-copy-subline">
                                                <span>后台操作员账号</span>
                                                <code data-status-display-username data-empty-username="未设置账号">{{ $displayUsername }}</code>
                                            </div>
                                            <div class="site-user-status-avatar-note" data-avatar-note>{{ $canManageGlobalAccountFields ? '点击头像，从资源库选择或上传。' : '该账号还绑定了其他站点，基础资料由平台统一维护。' }}</div>
                                        </div>

                                        <div class="site-user-status-side">
                                            <div class="site-user-status-side-item">
                                                <span class="site-user-status-side-label">创建时间</span>
                                                <span class="site-user-status-side-value">{{ optional($user->created_at)->format('Y-m-d H:i') ?: '未记录' }}</span>
                                            </div>
                                            <div class="site-user-status-side-item">
                                                <span class="site-user-status-side-label">最后登录</span>
                                                <span class="site-user-status-side-value">{{ optional($user->last_login_at)->format('Y-m-d H:i') ?: '暂无记录' }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="site-user-status-main">
                                        @if ($canManageStatusSelection)
                                        <div class="status-choice-grid">
                                            @foreach (['1' => '启用', '0' => '停用'] as $statusValue => $statusLabel)
                                                <label class="status-choice {{ $selectedStatusValue === (string) $statusValue ? 'is-active' : '' }}">
                                                    <input type="radio" name="status" value="{{ $statusValue }}" @checked($selectedStatusValue === (string) $statusValue)>
                                                    <span class="status-choice-check" aria-hidden="true"></span>
                                                    <span class="status-choice-label">{{ $statusLabel }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <span class="field-meta">
                                            <span class="field-note">停用后，账号将无法继续登录当前后台。</span>
                                            @error('status')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
                                        @else
                                        <div class="site-user-status-meta">
                                            <div class="site-user-status-row">
                                                <span class="site-user-status-label">当前状态</span>
                                                <span class="site-user-status-badge {{ $selectedStatusValue === '0' ? 'is-offline' : '' }}">{{ $statusLabel }}</span>
                                            </div>
                                        </div>
                                        <span class="field-meta">
                                            <span class="field-note">当前账号可自行更换头像，账号状态由具备操作员管理权限的管理员维护。</span>
                                        </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </section>
                        @endif

                        <section class="site-user-module">
                            <div class="site-user-module-header">
                                <span class="site-user-module-accent"></span>
                                <div class="site-user-module-title">基础信息</div>
                            </div>
                            <div class="site-user-module-body">
                                <div class="site-user-form-grid">
                                    <label class="field-group">
                                        <span class="field-label">用户名</span>
                                        <input class="field {{ ($isSelfEditing || $globalFieldsReadonly) ? 'is-readonly' : '' }} @error('username') is-error @enderror" id="username" type="text" name="username" value="{{ old('username', $user->username) }}" maxlength="32" @if($isSelfEditing || $globalFieldsReadonly) readonly aria-readonly="true" @endif @error('username') aria-invalid="true" @enderror>
                                        <span class="field-meta">
                                            <span class="field-note">{{ $globalFieldsReadonly ? '该账号还绑定了其他站点，登录账号由平台统一维护。' : ($isSelfEditing ? '当前登录账号不能修改自己的用户名。' : '作为后台登录账号使用，建议保持简洁稳定。') }}</span>
                                            @error('username')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">姓名</span>
                                        <input class="field {{ $globalFieldsReadonly ? 'is-readonly' : '' }} @error('name') is-error @enderror" id="name" type="text" name="name" value="{{ old('name', $user->name) }}" maxlength="50" @if($globalFieldsReadonly) readonly aria-readonly="true" @endif @error('name') aria-invalid="true" @enderror>
                                        <span class="field-meta">
                                            <span class="field-note">在列表和审计日志中显示的管理员名称。</span>
                                            @error('name')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
                                    </label>
                                </div>

                                <div class="site-user-form-grid">
                                    <label class="field-group">
                                        <span class="field-label">邮箱</span>
                                        <input class="field {{ $globalFieldsReadonly ? 'is-readonly' : '' }} @error('email') is-error @enderror" id="email" type="text" name="email" value="{{ old('email', $user->email) }}" maxlength="255" @if($globalFieldsReadonly) readonly aria-readonly="true" @endif @error('email') aria-invalid="true" @enderror>
                                        <span class="field-meta">
                                            <span class="field-note">用于接收通知或找回信息，可留空。</span>
                                            @error('email')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">手机号</span>
                                        <input class="field {{ $globalFieldsReadonly ? 'is-readonly' : '' }} @error('mobile') is-error @enderror" id="mobile" type="text" name="mobile" value="{{ old('mobile', $user->mobile) }}" @if($globalFieldsReadonly) readonly aria-readonly="true" @endif @error('mobile') aria-invalid="true" @enderror>
                                        <span class="field-meta">
                                            <span class="field-note">用于展示联系方式或后续短信提醒，可留空。</span>
                                            @error('mobile')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </section>

                        @if ($canManageRoleSelection)
                        <section class="site-user-module">
                            <div class="site-user-module-header">
                                <span class="site-user-module-accent"></span>
                                <div class="site-user-module-title">操作角色</div>
                            </div>
                            <div class="site-user-module-body">
                                <div class="field-group">
                                    <div class="role-choice-grid">
                                        @foreach ($siteRoles as $siteRole)
                                            <label class="role-choice" data-can-manage-content="{{ $siteRole->can_manage_content ? '1' : '0' }}">
                                                <input type="radio" name="role_id" value="site:{{ $siteRole->id }}" @checked((string) $selectedRoleValue === ('site:' . $siteRole->id))>
                                                <span class="role-choice-check" aria-hidden="true"></span>
                                                <span class="role-choice-name">{{ $siteRole->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <span class="field-meta">
                                        <span class="field-note">一个操作员只能绑定一个操作角色。</span>
                                        @error('role_id')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </span>
                                </div>
                            </div>
                        </section>
                        @endif

                        @if ($canManageGlobalAccountFields)
                        <section class="site-user-module">
                            <div class="site-user-module-header">
                                <span class="site-user-module-accent"></span>
                                <div class="site-user-module-title">安全设置</div>
                            </div>
                            <div class="site-user-module-body">
                                <label class="field-group">
                                    <span class="field-label">重置密码</span>
                                    <input class="field @error('password') is-error @enderror" id="password" type="password" name="password" placeholder="留空则不修改" @error('password') aria-invalid="true" @enderror>
                                    <span class="field-meta">
                                        <span class="field-note">如需重置密码，请填写新的 8 位以上密码。</span>
                                        @error('password')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </span>
                                </label>
                            </div>
                        </section>
                        @endif

                        @if (! $isSelfEditing && $canManageGlobalAccountFields)
                        <section class="site-user-module">
                            <div class="site-user-module-header">
                                <span class="site-user-module-accent"></span>
                                <div class="site-user-module-title">备注信息</div>
                            </div>
                            <div class="site-user-module-body">
                                <div class="field-group">
                                    <textarea class="field textarea site-user-remark-textarea site-user-remark-rich-editor @error('remark') is-error @enderror" id="remark" name="remark" maxlength="10000" @error('remark') aria-invalid="true" @enderror>{{ old('remark', $user->remark) }}</textarea>
                                    <span class="field-meta">
                                        <span class="field-note">用于记录该操作员的交接说明、职责补充或维护备注，支持精简富文本格式。</span>
                                        @error('remark')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </span>
                                </div>
                            </div>
                        </section>
                        @endif
                    </div>

                    <div class="site-user-column">
                        <section class="site-user-module js-channel-permission-module" data-readonly="{{ $isSelfEditing ? '1' : '0' }}">
                            <div class="site-user-module-header">
                                <span class="site-user-module-accent"></span>
                                <div class="site-user-module-title">可管理栏目</div>
                            </div>
                            <div class="site-user-module-body">
                                <div class="channel-placeholder js-channel-placeholder" @if(($isSelfEditing && $selectedManagedChannels->isNotEmpty()) || (! $isSelfEditing && $selectedRoleCanManageContent)) hidden @endif>
                                    <div class="channel-placeholder-copy">
                                        <span class="channel-placeholder-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24">
                                                <path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h4l2 2H18.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-9Z"></path>
                                            </svg>
                                        </span>
                                        <div class="channel-placeholder-title">暂无可管理栏目</div>
                                        <div class="channel-placeholder-note">
                                            @if ($isSelfEditing)
                                                当前账号未设置按栏目限制的内容管理权限。
                                            @else
                                                选择具备内容管理权限的操作角色后，这里会自动显示对应的栏目权限配置。
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="channel-panel js-channel-real-panel" @if(($isSelfEditing && $selectedManagedChannels->isEmpty()) || (! $isSelfEditing && ! $selectedRoleCanManageContent)) hidden @endif>
                                    <div class="channel-tree-wrap">
                                        @foreach ($isSelfEditing ? $selectedManagedChannels : collect($channels) as $channel)
                                            <label class="channel-option" data-depth="{{ (int) ($channel->tree_depth ?? 0) }}">
                                                <span class="channel-tree-guides" aria-hidden="true">
                                                    @foreach (($channel->tree_ancestors ?? []) as $hasLine)
                                                        <span class="channel-tree-guide {{ $hasLine ? 'is-active' : '' }}"></span>
                                                    @endforeach
                                                    @if ((int) ($channel->tree_depth ?? 0) > 0)
                                                        <span class="channel-tree-branch {{ !empty($channel->tree_is_last) ? 'is-last' : '' }}"></span>
                                                    @endif
                                                </span>
                                                <input
                                                    type="checkbox"
                                                    @if (! $isSelfEditing) name="channel_ids[]" @endif
                                                    value="{{ $channel->id }}"
                                                    @checked(in_array($channel->id, $selectedChannelIds, true))
                                                    @if ($isSelfEditing) disabled @endif
                                                >
                                                <span class="permission-check" aria-hidden="true"></span>
                                                <span class="channel-copy">
                                                    <span class="channel-name">{{ $channel->name }}</span>
                                                    <span class="channel-meta">
                                                        @if (!empty($channel->tree_has_children))
                                                            上级栏目
                                                        @else
                                                            可管理发布
                                                        @endif
                                                    </span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="channel-panel-desc">
                                        @if ($isSelfEditing)
                                            当前账号在这些栏目内具备内容管理权限，此处仅供查看。
                                        @else
                                            默认不勾选可管理添加所有栏目的文章内容。
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </section>

                        @if (! $isSelfEditing)
                        <section class="site-user-danger-card">
                            <div class="site-user-danger-header">
                                <span class="site-user-danger-accent"></span>
                                <div class="site-user-danger-title">删除操作员</div>
                            </div>
                            <div class="site-user-danger-body">
                                <div class="site-user-danger-note">{{ $globalFieldsReadonly ? '删除后，仅解除该操作员在当前站点的权限，不影响其在其他站点的账号。' : '删除后，该操作员账号及其站点绑定关系都会被清除，且操作不可恢复。请确认当前账号不再需要继续保留。' }}</div>
                                <div class="site-user-danger-actions">
                                    <button
                                        class="site-user-danger-button js-site-user-delete"
                                        type="button"
                                        data-form-id="delete-site-user-{{ $user->id }}"
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                        <span>删除操作员</span>
                                    </button>
                                </div>
                            </div>
                        </section>
                        @endif
                    </div>
                </div>
            </div>

        </form>
    </section>

    @include('admin.site.attachments._attachment_library_modal')

    @if (! $isSelfEditing)
        <form id="delete-site-user-{{ $user->id }}" method="POST" action="{{ route('admin.site-users.destroy', $user->id) }}" class="u-hidden-form">
            @csrf
        </form>
    @endif
@endsection
