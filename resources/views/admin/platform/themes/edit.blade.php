@extends('layouts.admin')

@section('title', '编辑主题 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 主题市场 / 编辑主题')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/platform-theme-form.css') }}">
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">编辑主题 <span class="theme-code-tag">{{ $theme->code }}</span></h2>
            <div class="page-header-desc">继续维护当前主题的名称、版本和主题说明，变更后会立即同步到平台主题库。</div>
        </div>
        <div class="topbar-right">
            <a class="button secondary" href="{{ route('admin.platform.themes.index') }}">返回主题市场</a>
            <button class="button" type="submit" form="theme-edit-form" data-loading-text="保存中...">保存主题信息</button>
        </div>
    </section>

    <div class="theme-shell">
        <section class="theme-card">
            <form id="theme-edit-form" method="POST" action="{{ route('admin.platform.themes.update', $theme->id) }}">
                @csrf

                <div class="theme-section">
                    <h3 class="theme-section-title">基础信息</h3>
                    <div class="theme-section-desc">维护当前主题的名称、版本与说明内容。</div>
                    <div class="stack theme-section-stack">
                        <div class="theme-form-grid">
                            <label class="field-group">
                                <span class="field-label">主题名称</span>
                                <input class="field @error('name') is-error @enderror" id="name" type="text" name="name" value="{{ old('name', $theme->name) }}" @error('name') aria-invalid="true" @enderror>
                                @error('name')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </label>

                            <div class="field-group">
                                <span class="field-label">主题代码</span>
                                <div class="theme-meta-value"><code>{{ $theme->code }}</code></div>
                            </div>
                        </div>

                        <div class="theme-form-grid">
                            <label class="field-group">
                                <span class="field-label">版本号</span>
                                <input class="field @error('version') is-error @enderror" id="version" type="text" name="version" value="{{ old('version', $version->version ?? '1.0.0') }}" @error('version') aria-invalid="true" @enderror>
                                @error('version')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </label>

                            <label class="field-group">
                                <span class="field-label">使用规则</span>
                                <input class="field" type="text" value="长期保留并可继续绑定站点" readonly>
                            </label>
                        </div>

                        <label class="field-group">
                            <span class="field-label">主题描述</span>
                            <textarea class="field textarea @error('description') is-error @enderror" id="description" name="description" rows="6" @error('description') aria-invalid="true" @enderror>{{ old('description', $theme->description) }}</textarea>
                            @error('description')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>
                </div>

                <div class="theme-section">
                    <h3 class="theme-section-title">文件说明</h3>
                    <div class="theme-file-list">
                        @forelse ($themeFiles as $file)
                            @php
                                $displayFile = $theme->code.'/'.ltrim($file, '/');
                            @endphp
                            <div class="theme-file-item" tabindex="0">
                                <span class="theme-file-item-text">{{ $displayFile }}</span>
                                <div class="theme-file-item-tooltip">{{ $displayFile }}</div>
                            </div>
                        @empty
                            <div class="field-note">当前主题目录下尚未发现模板文件。</div>
                        @endforelse
                    </div>
                </div>

                <div class="theme-form-actions">
                    <button class="button" type="submit" data-loading-text="保存中...">保存主题信息</button>
                </div>
            </form>
        </section>

        <aside class="theme-side-card">
            <div class="theme-meta">
                <div class="theme-meta-row">
                    <div class="theme-meta-label">主题名称</div>
                    <div class="theme-meta-value">{{ $theme->name }}</div>
                </div>
                <div class="theme-meta-row">
                    <div class="theme-meta-label">主题代码</div>
                    <div class="theme-meta-value"><code>{{ $theme->code }}</code></div>
                </div>
                <div class="theme-meta-row">
                    <div class="theme-meta-label">当前版本</div>
                    <div class="theme-meta-value">v{{ $version->version ?? '1.0.0' }}</div>
                </div>
                <div class="theme-meta-row">
                    <div class="theme-meta-label">主题目录</div>
                    <div class="theme-meta-value">{{ $themeRoot }}</div>
                </div>
                <div class="theme-meta-row">
                    <div class="theme-meta-label">模板文件数</div>
                    <div class="theme-meta-value">{{ count($themeFiles) }} 个文件</div>
                </div>
                <div class="theme-meta-row">
                    <div class="theme-meta-label">维护说明</div>
                    <div class="theme-meta-value">保存后，当前主题的名称、版本和说明会立即同步到平台主题市场中。</div>
                </div>
            </div>
        </aside>
    </div>
@endsection
