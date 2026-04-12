@extends('layouts.admin')

@section('title', '模块管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 模块管理')

@push('styles')
    <link rel="stylesheet" href="/css/platform-modules-index.css">
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">模块管理</h2>
            <div class="page-header-desc">统一维护平台可用模块、查看模块说明，并控制模块在全平台范围内的启用状态。</div>
        </div>
    </section>

    @if ($modules->isEmpty())
        <div class="empty-state">当前还没有已注册的模块，请先在 <code>app/Modules</code> 目录下准备模块骨架。</div>
    @else
        <section class="module-grid">
            @foreach ($modules as $module)
                <article class="module-card">
                    <div class="module-card-head">
                        <div>
                            <h3 class="module-name">{{ $module['name'] }}</h3>
                            <div class="module-code">{{ $module['code'] }}</div>
                        </div>
                        <span class="module-status {{ (($module['missing_manifest'] ?? false) || ($module['invalid_manifest'] ?? false)) ? 'is-missing' : ($module['status'] ? 'is-on' : '') }}">{{ ($module['invalid_manifest'] ?? false) ? '配置异常' : (($module['missing_manifest'] ?? false) ? '文件缺失' : ($module['status'] ? '已启用' : '已禁用')) }}</span>
                    </div>
                    <div class="module-meta">
                        <span class="badge-soft">{{ $module['scope'] === 'site' ? '站点模块' : '平台模块' }}</span>
                        <span class="badge-soft">v{{ $module['version'] }}</span>
                        @if ($module['author'] !== '')
                            <span class="badge-soft">{{ $module['author'] }}</span>
                        @endif
                    </div>
                    <div class="module-desc">{{ $module['description'] !== '' ? $module['description'] : '当前模块尚未补充说明。' }}</div>
                    @if (($module['missing_manifest'] ?? false) || ($module['invalid_manifest'] ?? false))
                        <div class="module-desc is-warning-note">
                            @if ($module['invalid_manifest'] ?? false)
                                {{ $module['manifest_error'] ?? 'module.json 配置异常，请检查模块清单。' }}
                            @else
                                模块目录或 <code>module.json</code> 已缺失，当前仅保留数据库记录。
                            @endif
                        </div>
                    @endif
                    <div class="module-stats">
                        <span>{{ count($module['files'] ?? []) }} 个文件</span>
                        <span>{{ count($module['settings'] ?? []) }} 项配置说明</span>
                        <span>{{ count($module['permissions'] ?? []) }} 个权限点</span>
                    </div>
                    <div class="module-actions">
                        <a class="button secondary" href="{{ route('admin.platform.modules.show', $module['code']) }}">查看模块</a>
                    </div>
                </article>
            @endforeach
        </section>
    @endif
@endsection
