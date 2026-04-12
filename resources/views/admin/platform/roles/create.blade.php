@extends('layouts.admin')

@section('title', '新增平台角色 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 平台角色管理 / 新增平台角色')

@push('styles')
    <link rel="stylesheet" href="/css/platform-roles.css">
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">新增平台角色</h2>
            <div class="page-header-desc">先填写平台角色基础信息，创建成功后会进入详情页继续配置平台权限。</div>
        </div>
        <div class="topbar-right">
            <a class="button secondary" href="{{ route('admin.platform.roles.index') }}">返回平台角色</a>
            <button class="button" type="submit" form="platform-role-create-form" data-loading-text="创建中...">创建平台角色</button>
        </div>
    </section>

    <section class="role-create-card">
        <form id="platform-role-create-form" method="POST" action="{{ route('admin.platform.roles.store') }}">
            @csrf

            <div class="role-create-body stack">
                <div class="form-grid-2">
                    <label class="field-group">
                        <span class="field-label">角色名称</span>
                        <input class="field @error('name') is-error @enderror" type="text" name="name" value="{{ old('name') }}" placeholder="例如：数据运营" @error('name') aria-invalid="true" @enderror>
                        <span class="field-note">在平台角色列表和详情页中展示给管理员查看的中文名称。</span>
                        @error('name')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="field-group">
                        <span class="field-label">角色标识</span>
                        <input class="field @error('code') is-error @enderror" type="text" name="code" value="{{ old('code') }}" placeholder="例如：data_operator" @error('code') aria-invalid="true" @enderror>
                        <span class="field-note">只能使用小写字母、数字和下划线，且需以字母开头。</span>
                        @error('code')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <label class="field-group">
                    <span class="field-label">角色说明</span>
                    <textarea class="field textarea @error('description') is-error @enderror" name="description" placeholder="例如：负责数据库管理、日志查看和平台级运营支持。" @error('description') aria-invalid="true" @enderror>{{ old('description') }}</textarea>
                    <span class="field-note">用于帮助团队快速理解该平台角色的职责边界。</span>
                    @error('description')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </label>
            </div>

            <div class="role-create-footer">
                <div class="helper-text">创建成功后会自动进入角色详情页，继续配置平台权限。</div>
                <div class="action-row">
                    <a class="button secondary" href="{{ route('admin.platform.roles.index') }}">取消</a>
                </div>
            </div>
        </form>
    </section>
@endsection
