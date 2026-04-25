@extends('layouts.admin')

@section('title', ($title ?? '站点已过期') . ' - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / ' . ($title ?? '站点已过期'))

@section('content')
    <section class="empty-state">
        <h3 class="empty-state-title">{{ $title ?? '站点后台功能已限制' }}</h3>
        <div class="empty-state-desc">
            {{ $message ?? '你所属的站点已经过期多日，目前已限制后台功能，请联系客服尽快续费，未续费站点将会进行数据清理。' }}
        </div>
    </section>
@endsection
