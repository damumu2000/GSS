@extends('layouts.admin')

@section('title', '系统检查 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 系统检查')

@push('styles')
    <link rel="stylesheet" href="/css/platform-system-checks.css">
@endpush

@php
    $statusLabels = [
        'ok' => '正常',
        'warning' => '警告',
        'error' => '异常',
    ];
@endphp

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-header-title">系统检查</h2>
            <div class="page-header-desc">
                页面打开时会实时执行轻量检查，帮助快速确认数据库、运行环境、部署状态和本地静态资源是否正常。
            </div>
        </div>
    </div>

    <div class="system-check-shell">
        <section class="content-card">
            <div class="system-check-maintenance-actions">
                @foreach ($cacheActions as $action => $definition)
                    <div class="system-check-maintenance-card">
                        <div class="system-check-maintenance-card-body">
                            <div class="system-check-maintenance-title">{{ $definition['label'] }}</div>
                            <div class="system-check-maintenance-desc">{{ $definition['description'] ?? '' }}</div>
                        </div>
                        <form method="POST" action="{{ route('admin.platform.system-checks.cache.clear', ['action' => $action]) }}" data-confirm-submit data-confirm-text="确认清理{{ $definition['label'] }}吗？">
                            @csrf
                            <button type="submit" class="button system-check-maintenance-button">立即清理</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="system-check-overview">
            <section class="system-check-summary-card">
                <div class="system-check-summary-label">总状态</div>
                <div class="system-check-summary-value">{{ $statusLabels[$overallStatus] ?? '正常' }}</div>
                <div>
                    <span class="system-check-status-badge is-{{ $overallStatus }}">{{ $statusLabels[$overallStatus] ?? '正常' }}</span>
                </div>
                <div class="system-check-summary-note">本次检查时间：{{ $checkedAt->format('Y-m-d H:i:s') }}</div>
            </section>
            <section class="system-check-summary-card">
                <div class="system-check-summary-label">正常项</div>
                <div class="system-check-summary-value">{{ $counts['ok'] }}</div>
                <div class="system-check-summary-note">运行状态符合预期的检查分组。</div>
            </section>
            <section class="system-check-summary-card">
                <div class="system-check-summary-label">警告项</div>
                <div class="system-check-summary-value">{{ $counts['warning'] }}</div>
                <div class="system-check-summary-note">建议尽快关注，但当前不一定会立即影响运行。</div>
            </section>
            <section class="system-check-summary-card">
                <div class="system-check-summary-label">异常项</div>
                <div class="system-check-summary-value">{{ $counts['error'] }}</div>
                <div class="system-check-summary-note">存在需要先处理的明显问题。</div>
            </section>
        </div>

        @foreach ($groups as $group)
            <section class="system-check-group">
                <div class="system-check-group-header">
                    <div>
                        <h3 class="system-check-group-title">{{ $group['title'] }}</h3>
                        <div class="system-check-group-desc">{{ $group['summary'] }}</div>
                    </div>
                    <span class="system-check-status-badge is-{{ $group['status'] }}">{{ $statusLabels[$group['status']] ?? '正常' }}</span>
                </div>
                <div class="system-check-items">
                    @foreach ($group['items'] as $item)
                        <article class="system-check-item">
                            <div class="system-check-item-top">
                                <div class="system-check-item-label">{{ $item['label'] }}</div>
                                <span class="system-check-status-badge is-{{ $item['status'] }}">{{ $statusLabels[$item['status']] ?? '正常' }}</span>
                            </div>
                            <div class="system-check-item-value">{{ $item['value'] !== '' ? $item['value'] : '—' }}</div>
                            <div class="system-check-item-message">{{ $item['message'] }}</div>
                            @if (($item['suggestion'] ?? '') !== '' || ($item['details'] ?? '') !== '')
                                <div class="system-check-item-meta">
                                    @if (($item['suggestion'] ?? '') !== '')
                                        <div class="system-check-item-suggestion">建议：{{ $item['suggestion'] }}</div>
                                    @endif
                                    @if (($item['details'] ?? '') !== '')
                                        <div>详情：{{ $item['details'] }}</div>
                                    @endif
                                </div>
                            @endif
                            @if (($item['action_url'] ?? '') !== '')
                                <div class="system-check-item-actions">
                                    <form method="POST" action="{{ $item['action_url'] }}">
                                        @csrf
                                        <button type="submit" class="button button-primary">{{ $item['action_label'] ?? '立即处理' }}</button>
                                    </form>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endsection
