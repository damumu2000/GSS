@extends('layouts.admin')

@section('title', '新增操作员 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 操作员管理 / 新增操作员')

@php
    $selectedRoleValue = (string) old('role_id', '');
    $selectedRoleId = (int) preg_replace('/^site:/', '', $selectedRoleValue);
    $selectedStatusValue = (string) old('status', '1');
    $selectedRoleName = collect($siteRoles)->firstWhere('id', $selectedRoleId)?->name ?? '未分配';
    $statusLabel = $selectedStatusValue === '0' ? '停用' : '启用中';
    $selectedRoleCanManageContent = (bool) collect($siteRoles)->firstWhere('id', $selectedRoleId)?->can_manage_content;
    $draftDisplayName = trim((string) old('name', '')) ?: '待填写姓名';
    $draftDisplayUsername = trim((string) old('username', '')) ?: '待设置账号';
    $draftAvatarSeed = trim((string) old('name', '')) ?: trim((string) old('username', '')) ?: '新';
    $draftAvatarInitial = function_exists('mb_substr') ? mb_substr($draftAvatarSeed, 0, 1) : substr($draftAvatarSeed, 0, 1);
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
            <h2 class="page-header-title">新增操作员</h2>
            <div class="page-header-desc">当前站点：{{ $currentSite->name }}。先填写账号基础信息，再分配操作角色和联系方式。</div>
        </div>
        <div class="topbar-right">
            <a class="button secondary" href="{{ route('admin.site-users.index') }}">返回操作员管理</a>
            <button class="button" type="submit" form="site-user-create-form" data-loading-text="创建中...">创建操作员</button>
        </div>
    </section>

    <section class="site-user-shell">
        <form id="site-user-create-form" method="POST" action="{{ route('admin.site-users.store') }}" data-validation-errors='@json($errors->all())' data-require-password="1">
            @csrf

            <div class="site-user-body">
                <div class="site-user-layout-grid">
                    <div class="site-user-column">
                        @if ($canManageStatusSelection)
                        <section class="site-user-module">
                            <div class="site-user-module-header">
                                <span class="site-user-module-accent"></span>
                                <div class="site-user-module-title">账号状态</div>
                            </div>
                            <div class="site-user-module-body site-user-status-module-body">
                                <div class="site-user-status-layout">
                                    <div class="site-user-status-hero">
                                        <input type="hidden" name="avatar" id="avatar" value="{{ old('avatar') }}">
                                        <div class="site-user-status-avatar-card" data-avatar-trigger>
                                            <div class="site-user-status-avatar" data-avatar-preview>
                                                <span class="site-user-status-avatar-fallback" data-status-avatar data-fallback="新">{{ $draftAvatarInitial }}</span>
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
                                            <div class="site-user-status-copy-name" data-status-display-name data-empty-name="待填写姓名">{{ $draftDisplayName }}</div>
                                            <div class="site-user-status-copy-subline">
                                                <span>后台操作员账号</span>
                                                <code data-status-display-username data-empty-username="待设置账号">{{ $draftDisplayUsername }}</code>
                                            </div>
                                            <div class="site-user-status-avatar-note" data-avatar-note>点击头像，从资源库选择或上传。</div>
                                        </div>

                                        <div class="site-user-status-side" aria-hidden="true">
                                            <div class="site-user-status-side-item">
                                                <span class="site-user-status-side-label">创建时间</span>
                                                <span class="site-user-status-side-value">创建后记录</span>
                                            </div>
                                            <div class="site-user-status-side-item">
                                                <span class="site-user-status-side-label">最后登录</span>
                                                <span class="site-user-status-side-value">暂无记录</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="site-user-status-main">
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
                                            <span class="field-note">停用后，账号创建后也无法登录当前后台。</span>
                                            @error('status')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
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
                                        <input class="field @error('username') is-error @enderror" id="username" type="text" name="username" value="{{ old('username') }}" maxlength="32" @error('username') aria-invalid="true" @enderror>
                                        <span class="field-meta">
                                            <span class="field-note">作为后台登录账号使用，建议保持简洁稳定。</span>
                                            @error('username')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">姓名</span>
                                        <input class="field @error('name') is-error @enderror" id="name" type="text" name="name" value="{{ old('name') }}" maxlength="50" @error('name') aria-invalid="true" @enderror>
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
                                        <input class="field @error('email') is-error @enderror" id="email" type="text" name="email" value="{{ old('email') }}" maxlength="255" @error('email') aria-invalid="true" @enderror>
                                        <span class="field-meta">
                                            <span class="field-note">用于接收通知或找回信息，可留空。</span>
                                            @error('email')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">手机号</span>
                                        <input class="field @error('mobile') is-error @enderror" id="mobile" type="text" name="mobile" value="{{ old('mobile') }}" @error('mobile') aria-invalid="true" @enderror>
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
                                            <label class="role-choice {{ $selectedRoleValue === ('site:' . $siteRole->id) ? 'is-active' : '' }}" data-can-manage-content="{{ $siteRole->can_manage_content ? '1' : '0' }}">
                                                <input type="radio" name="role_id" value="site:{{ $siteRole->id }}" @checked($selectedRoleValue === ('site:' . $siteRole->id))>
                                                <span class="role-choice-check" aria-hidden="true"></span>
                                                <span class="role-choice-name">{{ $siteRole->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <span class="field-meta">
                                        <span class="field-note">一个操作员只能绑定一个操作角色，后续可在编辑页调整。</span>
                                        @error('role_id')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </span>
                                </div>
                            </div>
                        </section>
                        @endif

                        <section class="site-user-module">
                            <div class="site-user-module-header">
                                <span class="site-user-module-accent"></span>
                                <div class="site-user-module-title">安全设置</div>
                            </div>
                            <div class="site-user-module-body">
                                <label class="field-group">
                                    <span class="field-label">初始密码</span>
                                    <input class="field @error('password') is-error @enderror" id="password" type="password" name="password" @error('password') aria-invalid="true" @enderror>
                                    <span class="field-meta">
                                        <span class="field-note">请设置 8 位以上密码，创建后用户可自行修改。</span>
                                        @error('password')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </span>
                                </label>
                            </div>
                        </section>

                        <section class="site-user-module">
                            <div class="site-user-module-header">
                                <span class="site-user-module-accent"></span>
                                <div class="site-user-module-title">备注信息</div>
                            </div>
                            <div class="site-user-module-body">
                                <div class="field-group">
                                    <textarea class="field textarea site-user-remark-textarea site-user-remark-rich-editor @error('remark') is-error @enderror" id="remark" name="remark" maxlength="10000" @error('remark') aria-invalid="true" @enderror>{{ old('remark') }}</textarea>
                                    <span class="field-meta">
                                        <span class="field-note">用于记录该操作员的交接说明、职责补充或维护备注，支持精简富文本格式。</span>
                                        @error('remark')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </span>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="site-user-column">
                        <section class="site-user-module js-channel-permission-module">
                            <div class="site-user-module-header">
                                <span class="site-user-module-accent"></span>
                                <div class="site-user-module-title">可管理栏目</div>
                            </div>
                            <div class="site-user-module-body">
                                <div class="channel-placeholder js-channel-placeholder" @if($selectedRoleCanManageContent) hidden @endif>
                                    <div class="channel-placeholder-copy">
                                        <span class="channel-placeholder-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24">
                                                <path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h4l2 2H18.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-9Z"></path>
                                            </svg>
                                        </span>
                                        <div class="channel-placeholder-title">暂无可管理栏目</div>
                                        <div class="channel-placeholder-note">选择具备内容管理权限的操作角色后，这里会自动显示对应的栏目权限配置。</div>
                                    </div>
                                </div>

                                <div class="channel-panel js-channel-real-panel" @unless($selectedRoleCanManageContent) hidden @endunless>
                                    <div class="channel-tree-wrap">
                                        @foreach ($channels as $channel)
                                            <label class="channel-option" data-depth="{{ (int) ($channel->tree_depth ?? 0) }}">
                                                <span class="channel-tree-guides" aria-hidden="true">
                                                    @foreach (($channel->tree_ancestors ?? []) as $hasLine)
                                                        <span class="channel-tree-guide {{ $hasLine ? 'is-active' : '' }}"></span>
                                                    @endforeach
                                                    @if ((int) ($channel->tree_depth ?? 0) > 0)
                                                        <span class="channel-tree-branch {{ !empty($channel->tree_is_last) ? 'is-last' : '' }}"></span>
                                                    @endif
                                                </span>
                                                <input type="checkbox" name="channel_ids[]" value="{{ $channel->id }}" @checked(in_array($channel->id, $selectedChannelIds, true))>
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
                                    <div class="channel-panel-desc">默认不勾选可管理添加所有栏目的文章内容。</div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            <div class="site-user-form-footer">
                <div class="helper-text">创建成功后会立即回到列表页，并通过顶部提示框反馈结果。</div>
            </div>
        </form>
    </section>

    @include('admin.site.attachments._attachment_library_modal')
@endsection
