@extends('layouts.admin')

@section('title', '模块管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 模块管理')

@push('styles')
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-start;
            padding: 24px 32px;
            margin: -28px -28px 24px;
            background: #ffffff;
            border-bottom: 1px solid #f0f0f0;
        }

        .page-header-title { margin: 0; color: #262626; font-size: 20px; line-height: 1.4; font-weight: 700; }
        .page-header-desc { margin-top: 8px; color: #8c8c8c; font-size: 14px; line-height: 1.7; }
        .module-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
        .module-card {
            display: grid;
            gap: 14px;
            padding: 18px;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }

        .module-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .module-name { margin: 0; color: #1f2937; font-size: 16px; line-height: 1.5; font-weight: 700; }
        .module-code {
            margin-top: 4px;
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.6;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }

        .module-status {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 0 10px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #6b7280;
            font-size: 12px;
            font-weight: 700;
        }

        .module-status.is-on {
            background: rgba(16, 185, 129, 0.10);
            color: #059669;
        }

        .module-status.is-missing {
            background: rgba(245, 158, 11, 0.12);
            color: #b45309;
        }

        .module-meta { display: flex; gap: 8px; flex-wrap: wrap; }
        .module-meta .badge-soft { background: #f5f7fa; color: #4b5563; }
        .module-desc { color: #6b7280; font-size: 13px; line-height: 1.75; min-height: 44px; }
        .module-stats { display: flex; gap: 12px; flex-wrap: wrap; color: #98a2b3; font-size: 12px; line-height: 1.6; }
        .module-actions { display: flex; justify-content: flex-end; }
        .empty-state {
            padding: 42px 24px;
            text-align: center;
            color: #8c8c8c;
            border: 1px dashed #dbe4ee;
            border-radius: 16px;
            background: #ffffff;
        }

        @media (max-width: 1200px) {
            .module-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 768px) {
            .page-header {
                margin: -24px -18px 20px;
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
            }

            .module-grid { grid-template-columns: 1fr; }
        }
    </style>
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
                        <div class="module-desc" style="min-height:auto;color:#b45309;">
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
