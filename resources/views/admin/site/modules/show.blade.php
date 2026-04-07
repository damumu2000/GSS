@extends('layouts.admin')

@section('title', $module['name'] . ' - 功能模块 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / ' . $module['name'])

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
        .module-card { padding: 20px 22px; border: 1px solid #eef2f6; border-radius: 16px; background: #ffffff; box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04); }
        .module-title { margin: 0; color: #1f2937; font-size: 18px; line-height: 1.5; font-weight: 700; }
        .module-desc { margin-top: 10px; color: #6b7280; font-size: 14px; line-height: 1.8; }
        .module-list { display: grid; gap: 10px; margin-top: 18px; }
        .module-list-item { color: #4b5563; font-size: 13px; line-height: 1.75; }
        @media (max-width: 768px) {
            .page-header { margin: -24px -18px 20px; padding: 18px; flex-direction: column; align-items: flex-start; }
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">{{ $module['name'] }}</h2>
            <div class="page-header-desc">当前站点已绑定该模块，后续具体业务入口、配置项和页面文件会继续归入这个模块内维护。</div>
        </div>
        <div class="page-header-actions">
            <a class="button secondary" href="{{ route('admin.site-dashboard') }}">返回站点工作台</a>
        </div>
    </section>

    <section class="module-card">
        <h3 class="module-title">{{ $module['name'] }}</h3>
        <div class="module-desc">{{ $module['description'] !== '' ? $module['description'] : '当前模块尚未补充说明。' }}</div>
        <div class="module-list">
            <div class="module-list-item"><strong>模块标识：</strong><code>{{ $module['code'] }}</code></div>
            <div class="module-list-item"><strong>模块版本：</strong>v{{ $module['version'] }}</div>
            <div class="module-list-item"><strong>模块目录：</strong>{{ $module['path'] }}</div>
            <div class="module-list-item"><strong>说明文件：</strong>{{ $module['manifest_path'] }}</div>
        </div>
    </section>
@endsection
