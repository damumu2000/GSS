@extends('layouts.admin')

@section('title', '模块使用管理 - ' . $site->name . ' - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 站点管理 / 模块使用管理')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/platform-site-modules.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/platform-site-modules.js') }}" defer></script>
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">模块使用管理</h2>
            <div class="page-header-desc">站点：{{ $site->name }}（{{ $site->site_key }}）</div>
        </div>
    </section>

    @include('admin.platform.sites._modules_panel', ['embeddedModuleUi' => false])
@endsection
