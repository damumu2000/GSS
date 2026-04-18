@extends('layouts.admin')

@section('title', '接口日志 - 微信公众号 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 微信公众号接口日志')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/wechat-official-admin.css') }}">
@endpush

@section('content')
    @php
        $wechatLogPageItems = $wechatOfficialLogs->getCollection();
        $wechatLogStatCards = [
            ['label' => '当前页日志', 'value' => $wechatLogPageItems->count()],
            ['label' => '成功', 'value' => $wechatLogPageItems->where('status', 'success')->count()],
            ['label' => '失败', 'value' => $wechatLogPageItems->where('status', 'failed')->count()],
            ['label' => '接口通道', 'value' => $wechatLogPageItems->pluck('channel')->filter()->unique()->count()],
        ];
    @endphp
    <section class="wechat-official-header">
        <div>
            <h1 class="wechat-official-title">接口日志</h1>
            <div class="wechat-official-desc">集中查看公众号配置检测、菜单同步和后续文章推送、素材同步的接口结果。</div>
        </div>
    </section>

    @include('wechat_official::admin._nav')

    <section class="wechat-official-grid wechat-official-grid--compact">
        @foreach ($wechatLogStatCards as $stat)
            <article class="wechat-official-stat-card">
                <span class="wechat-official-stat-label">{{ $stat['label'] }}</span>
                <strong class="wechat-official-stat-value">{{ $stat['value'] }}</strong>
            </article>
        @endforeach
    </section>

    <section class="wechat-official-panel">
        @if ($wechatOfficialLogs->isEmpty())
            <div class="wechat-official-placeholder">
                <h2>当前没有日志</h2>
                <p>后续执行菜单同步、文章推送或素材同步后，会自动记录接口结果。</p>
            </div>
        @else
            <div class="wechat-official-log-list">
                @foreach ($wechatOfficialLogs as $log)
                    @php
                        $requestPayload = trim((string) ($log->request_payload ?? ''));
                        $responsePayload = trim((string) ($log->response_payload ?? ''));
                        $prettyRequestPayload = $requestPayload;
                        $prettyResponsePayload = $responsePayload;

                        if ($requestPayload !== '') {
                            $decodedRequestPayload = json_decode($requestPayload, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedRequestPayload)) {
                                $prettyRequestPayload = json_encode($decodedRequestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            }
                        }

                        if ($responsePayload !== '') {
                            $decodedResponsePayload = json_decode($responsePayload, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedResponsePayload)) {
                                $prettyResponsePayload = json_encode($decodedResponsePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            }
                        }
                    @endphp
                    <article class="wechat-official-log-item">
                        <div class="wechat-official-log-head">
                            <div>
                                <div class="wechat-official-log-title">{{ $log->action }}</div>
                                <div class="wechat-official-log-meta">{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }} · {{ $log->channel }}</div>
                            </div>
                            <span class="wechat-official-inline-tag {{ $log->status === 'success' ? 'is-success' : 'is-danger' }}">{{ $log->status === 'success' ? '成功' : '失败' }}</span>
                        </div>
                        @if (!empty($log->message))
                            <div class="wechat-official-log-message">{{ $log->message }}</div>
                        @endif
                        @if ($requestPayload !== '' || $responsePayload !== '')
                            <div class="wechat-official-log-payloads">
                                @if ($requestPayload !== '')
                                    <details class="wechat-official-log-details">
                                        <summary>查看请求数据</summary>
                                        <pre class="wechat-official-log-code">{{ $prettyRequestPayload }}</pre>
                                    </details>
                                @endif
                                @if ($responsePayload !== '')
                                    <details class="wechat-official-log-details">
                                        <summary>查看响应数据</summary>
                                        <pre class="wechat-official-log-code">{{ $prettyResponsePayload }}</pre>
                                    </details>
                                @endif
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>

            <div class="wechat-official-pagination">
                {{ $wechatOfficialLogs->links() }}
            </div>
        @endif
    </section>
@endsection
