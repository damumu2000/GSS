@extends('layouts.admin')

@section('title', '新增主题 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 主题市场 / 新增主题')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/platform-theme-form.css') }}">
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">新增主题</h2>
            <div class="page-header-desc">创建新的平台主题记录，补充代码、版本和说明后，后续即可统一维护主题库并分配给站点。</div>
        </div>
        <div class="topbar-right">
            <a class="button secondary" href="{{ route('admin.platform.themes.index') }}">返回主题市场</a>
            <button class="button" type="submit" form="theme-create-form" data-loading-text="创建中...">创建主题</button>
        </div>
    </section>

    <div class="theme-shell">
        <section class="theme-card">
            <form id="theme-create-form" method="POST" action="{{ route('admin.platform.themes.store') }}">
                @csrf

                <div class="theme-section">
                    <h3 class="theme-section-title">基础信息</h3>
                    <div class="theme-section-desc">创建新的主题记录，补充名称、代码、版本与说明。</div>
                    <div class="stack theme-section-stack">
                        <div class="theme-form-grid">
                            <label class="field-group">
                                <span class="field-label">主题名称</span>
                                <input class="field @error('name') is-error @enderror" id="name" type="text" name="name" value="{{ old('name') }}" @error('name') aria-invalid="true" @enderror>
                                @error('name')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </label>

                            <label class="field-group">
                                <span class="field-label">主题代码</span>
                                <input class="field @error('code') is-error @enderror" id="code" type="text" name="code" value="{{ old('code') }}" @error('code') aria-invalid="true" @enderror>
                                @error('code')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </label>
                        </div>

                        <div class="theme-form-grid">
                            <label class="field-group">
                                <span class="field-label">版本号</span>
                                <input class="field @error('version') is-error @enderror" id="version" type="text" name="version" value="{{ old('version', '1.0.0') }}" @error('version') aria-invalid="true" @enderror>
                                @error('version')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </label>

                            <label class="field-group">
                                <span class="field-label">使用规则</span>
                                <input class="field" type="text" value="创建后可绑定给站点" readonly>
                            </label>
                        </div>

                        <label class="field-group">
                            <span class="field-label">主题描述</span>
                            <textarea class="field textarea @error('description') is-error @enderror" id="description" name="description" rows="6" @error('description') aria-invalid="true" @enderror>{{ old('description') }}</textarea>
                            @error('description')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>
                </div>

                <div class="theme-section">
                    <h3 class="theme-section-title">文件说明</h3>
                    <div class="theme-file-list">
                        <div class="theme-file-empty">主题创建后，这里会显示对应主题目录下的模板文件说明。</div>
                    </div>
                </div>

                <div class="theme-form-actions">
                    <button class="button" type="submit" data-loading-text="创建中...">创建主题</button>
                </div>
            </form>
        </section>

        <aside class="theme-side-card">
            <div class="theme-meta">
                <div class="theme-meta-row">
                    <div class="theme-meta-label">主题状态</div>
                    <div class="theme-meta-value">创建后可继续维护并绑定站点</div>
                </div>
                <div class="theme-meta-row">
                    <div class="theme-meta-label">当前版本</div>
                    <div class="theme-meta-value">v{{ old('version', '1.0.0') }}</div>
                </div>
                <div class="theme-meta-row">
                    <div class="theme-meta-label">主题目录</div>
                    <div class="theme-meta-value">创建后生成至 <code>storage/app/theme_templates/&lt;主题代码&gt;</code></div>
                </div>
                <div class="theme-meta-row">
                    <div class="theme-meta-label">维护说明</div>
                    <div class="theme-meta-value">创建成功后，系统会自动生成当前版本记录，后续可继续进入详情页调整信息。</div>
                </div>
            </div>
        </aside>
    </div>
@endsection
