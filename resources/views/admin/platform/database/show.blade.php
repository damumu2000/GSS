@extends('layouts.admin')

@section('title', $detail['table'] . ' - 数据库管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 数据库管理 / ' . $detail['table'])

@push('styles')
    <link rel="stylesheet" href="/css/platform-database-show.css">
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
