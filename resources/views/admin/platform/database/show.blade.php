@extends('layouts.admin')

@section('title', $detail['table'] . ' - 数据库管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 数据库管理 / ' . $detail['table'])

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
        .database-shell { display: grid; grid-template-columns: 1fr; gap: 18px; }
        .panel {
            padding: 20px 22px;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            background: #ffffff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
            min-width: 0;
        }
        .detail-nav {
            display: inline-flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 10px;
            border-radius: 18px;
            border: 1px solid #eef2f6;
            background: #ffffff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }
        .detail-nav-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 16px;
            border-radius: 12px;
            color: #667085;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
        }
        .detail-nav-link.is-active {
            background: #eef5ff;
            color: #1d4ed8;
            box-shadow: inset 0 0 0 1px #d7e7ff;
        }
        .section + .section { margin-top: 22px; }
        .section-title { margin: 0; color: #1f2937; font-size: 16px; line-height: 1.5; font-weight: 700; }
        .section-desc { margin-top: 6px; color: #8c8c8c; font-size: 13px; line-height: 1.7; }
        .meta-grid { display: grid; gap: 12px; }
        .meta-row { display: grid; gap: 4px; }
        .meta-label { color: #98a2b3; font-size: 12px; line-height: 1.6; font-weight: 700; }
        .meta-value { color: #1f2937; font-size: 14px; line-height: 1.7; word-break: break-word; }
        .table-wrap { margin-top: 14px; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 820px; }
        .data-table th,
        .data-table td {
            padding: 13px 12px;
            border-bottom: 1px solid #eef2f6;
            text-align: left;
            color: #475467;
            font-size: 13px;
            line-height: 1.7;
            vertical-align: top;
        }
        .data-table th { color: #98a2b3; font-size: 12px; font-weight: 700; }
        .data-table code { font-size: 12px; color: #344054; }
        .badge {
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
        .badge.is-good { background: rgba(16, 185, 129, 0.10); color: #059669; }
        .badge.is-warn { background: rgba(245, 158, 11, 0.12); color: #b45309; }
        .suggestion-list { display: grid; gap: 10px; margin-top: 14px; }
        .suggestion-item {
            padding: 12px 14px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #eef2f6;
            color: #475467;
            font-size: 13px;
            line-height: 1.75;
        }
        .database-pagination {
            margin-top: 20px;
        }
        .database-pagination nav {
            width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            padding-top: 2px;
        }
        .database-pagination .pagination-shell {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: nowrap;
            min-width: max-content;
        }
        .database-pagination .pagination-pages {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .database-pagination .pagination-button,
        .database-pagination .pagination-page,
        .database-pagination .pagination-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            height: 32px;
            min-width: 32px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            color: #4b5563;
            font-size: 13px;
            line-height: 1;
            text-decoration: none;
            transition: all 0.2s;
        }
        .database-pagination .pagination-page {
            width: 32px;
            padding: 0;
        }
        .database-pagination .pagination-button {
            border: 0;
            background: transparent;
            min-width: auto;
            padding: 0 4px;
            color: #4b5563;
        }
        .database-pagination .pagination-button:hover,
        .database-pagination .pagination-page:hover {
            transform: translateY(-1px);
            background: #f9fafb;
            border-color: #d1d5db;
        }
        .database-pagination .pagination-button:hover {
            background: transparent;
            border-color: transparent;
            color: #262626;
        }
        .database-pagination .pagination-page.is-active,
        .database-pagination .pagination-page.is-active:visited {
            border-color: #374151 !important;
            background: #374151 !important;
            color: #ffffff !important;
            font-weight: 600;
            transform: none;
        }
        .database-pagination .pagination-button.is-disabled,
        .database-pagination .pagination-page.is-disabled,
        .database-pagination .pagination-ellipsis {
            color: #c0c4cc;
            cursor: not-allowed;
        }
        .database-pagination .pagination-button.is-disabled:hover,
        .database-pagination .pagination-page.is-disabled:hover {
            transform: none;
            background: #ffffff;
            border-color: #e5e7eb;
        }
        .database-pagination .pagination-button.is-disabled,
        .database-pagination .pagination-button.is-disabled:hover {
            background: transparent;
            border-color: transparent;
        }
        .database-pagination .pagination-icon {
            width: 14px;
            height: 14px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
        }
        @media (max-width: 960px) {
            .page-header { margin: -24px -18px 20px; padding: 18px; flex-direction: column; align-items: flex-start; }
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">{{ $detail['table'] }}</h2>
            <div class="page-header-desc">只读查看当前数据表的字段结构与分页数据，敏感字段会自动脱敏显示。</div>
        </div>
        <div class="page-header-actions">
            <a class="button secondary" href="{{ route('admin.platform.database.index') }}">返回数据库管理</a>
        </div>
    </section>

    <div class="database-shell">
        <nav class="detail-nav" aria-label="数据库详情切换">
            <a class="detail-nav-link {{ $activeTab === 'structure' ? 'is-active' : '' }}" href="{{ route('admin.platform.database.show', ['table' => $detail['table'], 'tab' => 'structure']) }}">字段结构</a>
            <a class="detail-nav-link {{ $activeTab === 'data' ? 'is-active' : '' }}" href="{{ route('admin.platform.database.show', ['table' => $detail['table'], 'tab' => 'data']) }}">数据预览</a>
            <a class="detail-nav-link {{ $activeTab === 'meta' ? 'is-active' : '' }}" href="{{ route('admin.platform.database.show', ['table' => $detail['table'], 'tab' => 'meta']) }}">表属性</a>
        </nav>

        <section class="panel">
            @if ($activeTab === 'structure')
            <div class="section">
                <h3 class="section-title">字段结构</h3>
                <div class="section-desc">展示字段类型、是否可空、默认值以及索引情况。</div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>字段</th>
                                <th>类型</th>
                                <th>可空</th>
                                <th>默认值</th>
                                <th>主键</th>
                                <th>索引</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detail['columns'] as $column)
                                <tr>
                                    <td><code>{{ $column['name'] }}</code></td>
                                    <td>{{ $column['type'] }}</td>
                                    <td>{{ $column['nullable'] ? '是' : '否' }}</td>
                                    <td>{{ $column['default'] === null ? 'NULL' : (string) $column['default'] }}</td>
                                    <td>
                                        @if ($column['primary'])
                                            <span class="badge is-good">主键</span>
                                        @else
                                            <span class="badge">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $column['indexes'] !== [] ? implode('、', $column['indexes']) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @elseif ($activeTab === 'data')

            <div class="section">
                <h3 class="section-title">数据预览</h3>
                <div class="section-desc">当前每页展示 10 条数据，仅用于只读查看和排查。</div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                @foreach ($detail['columns'] as $column)
                                    <th>{{ $column['name'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($detail['paginator'] as $row)
                                <tr>
                                    @foreach ($detail['columns'] as $column)
                                        <td>{{ $row[$column['name']] ?? '—' }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($detail['columns']) }}">当前数据表暂无记录。</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($detail['paginator']->hasPages())
                    <div class="database-pagination">
                        {{ $detail['paginator']->appends(['tab' => 'data'])->withQueryString()->links() }}
                    </div>
                @endif
            </div>
            @else
            <div class="meta-grid">
                <div class="meta-row">
                    <div class="meta-label">数据库驱动</div>
                    <div class="meta-value">{{ strtoupper($overview['driver']) }}</div>
                </div>
                <div class="meta-row">
                    <div class="meta-label">所在库</div>
                    <div class="meta-value">{{ $overview['database_name'] }}</div>
                </div>
                <div class="meta-row">
                    <div class="meta-label">记录数</div>
                    <div class="meta-value">{{ number_format((int) $detail['row_count']) }}</div>
                </div>
                <div class="meta-row">
                    <div class="meta-label">主键</div>
                    <div class="meta-value">{{ $detail['primary_key'] }}</div>
                </div>
            </div>

            <div class="section">
                <h3 class="section-title">健康建议</h3>
                <div class="suggestion-list">
                    @foreach ($detail['recommendations'] as $suggestion)
                        <div class="suggestion-item">{{ $suggestion }}</div>
                    @endforeach
                </div>
            </div>
            @endif
        </section>
    </div>
@endsection
