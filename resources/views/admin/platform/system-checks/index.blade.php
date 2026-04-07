@extends('layouts.admin')

@section('title', '系统检查 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 系统检查')

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

        .page-header-title {
            margin: 0;
            color: #262626;
            font-size: 20px;
            line-height: 1.4;
            font-weight: 700;
        }

        .page-header-desc {
            margin-top: 8px;
            color: #8c8c8c;
            font-size: 14px;
            line-height: 1.7;
        }

        .system-check-shell {
            display: grid;
            gap: 18px;
        }

        .system-check-overview {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .system-check-summary-card,
        .system-check-group {
            border: 1px solid #eef2f6;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }

        .system-check-summary-card {
            padding: 18px 20px;
            display: grid;
            gap: 8px;
        }

        .system-check-summary-label {
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.6;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .system-check-summary-value {
            color: #111827;
            font-size: 26px;
            line-height: 1.2;
            font-weight: 800;
        }

        .system-check-summary-note {
            color: #6b7280;
            font-size: 13px;
            line-height: 1.7;
        }

        .system-check-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 80px;
            min-height: 32px;
            padding: 0 12px;
            border-radius: 999px;
            font-size: 12px;
            line-height: 1;
            font-weight: 700;
        }

        .system-check-status-badge.is-ok {
            background: rgba(22, 163, 74, 0.1);
            color: #15803d;
        }

        .system-check-status-badge.is-warning {
            background: rgba(245, 158, 11, 0.12);
            color: #b45309;
        }

        .system-check-status-badge.is-error {
            background: rgba(239, 68, 68, 0.1);
            color: #b91c1c;
        }

        .system-check-group {
            overflow: hidden;
        }

        .system-check-group-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            border-bottom: 1px solid #f3f4f6;
        }

        .system-check-group-title {
            margin: 0;
            color: #111827;
            font-size: 17px;
            line-height: 1.5;
            font-weight: 700;
        }

        .system-check-group-desc {
            margin-top: 6px;
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.7;
        }

        .system-check-items {
            display: grid;
            gap: 12px;
            padding: 18px 20px 20px;
        }

        .system-check-item {
            display: grid;
            gap: 8px;
            padding: 16px 18px;
            border: 1px solid #f1f5f9;
            border-radius: 14px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        }

        .system-check-item-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .system-check-item-label {
            color: #1f2937;
            font-size: 14px;
            line-height: 1.6;
            font-weight: 700;
        }

        .system-check-item-value {
            color: #111827;
            font-size: 14px;
            line-height: 1.7;
            font-weight: 600;
        }

        .system-check-item-message {
            color: #4b5563;
            font-size: 13px;
            line-height: 1.8;
        }

        .system-check-item-meta {
            display: grid;
            gap: 4px;
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.8;
        }

        .system-check-item-suggestion {
            color: #1d4ed8;
        }

        @media (max-width: 1080px) {
            .system-check-overview {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 720px) {
            .system-check-overview {
                grid-template-columns: 1fr;
            }

            .system-check-group-header,
            .system-check-item-top {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
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
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endsection
