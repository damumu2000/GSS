@extends('layouts.admin')

@section('title', $module['name'] . ' - 功能模块 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / ' . $module['name'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/site-modules-show.css') }}">
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
