@extends('layouts.admin')

@section('title', '数据库管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 数据库管理')

@push('styles')
    <link rel="stylesheet" href="/css/platform-database-index.css">
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">数据库管理</h2>
            <div class="page-header-desc">轻量查看系统数据表、字段结构和分页数据预览，并根据当前结构给出健康建议。</div>
        </div>
    </section>

    <section class="database-overview">
        <article class="overview-card">
            <div class="overview-label">数据库驱动</div>
            <div class="overview-value">{{ strtoupper($overview['driver']) }}</div>
            <div class="overview-note">
                当前连接：{{ $overview['database_name'] }}
                @if (! empty($overview['database_version']))
                    <br>版本：{{ $overview['database_version'] }}
                @endif
            </div>
        </article>
        <article class="overview-card">
            <div class="overview-label">数据表总数</div>
            <div class="overview-value">{{ $overview['table_count'] }}</div>
            <div class="overview-note">当前系统已识别的业务与系统表</div>
        </article>
        <article class="overview-card">
            <div class="overview-label">迁移状态</div>
            <div class="overview-value">{{ $overview['pending_migrations'] ? '待升级' : '正常' }}</div>
            <div class="overview-note">{{ $overview['pending_migrations'] ? '存在未执行迁移，请优先处理。' : '当前数据库结构与迁移记录一致。' }}</div>
        </article>
        <article class="overview-card">
            <div class="overview-label">建议关注</div>
            <div class="overview-value">{{ $overview['large_table_count'] + $overview['missing_primary_count'] + $overview['missing_timestamp_count'] }}</div>
            <div class="overview-note">包含大表、缺主键或缺时间字段的提示项</div>
        </article>
    </section>

    <section class="database-layout">
        <aside class="panel">
            <h3 class="panel-title">健康建议</h3>
            <div class="panel-desc">这部分基于当前驱动、迁移状态和表结构做轻量分析，不执行重型运维检查。</div>
            <div class="suggestion-list">
                @foreach ($overview['suggestions'] as $suggestion)
                    <div class="suggestion-item">{{ $suggestion }}</div>
                @endforeach
            </div>
        </aside>

        <div class="panel">
            <form class="table-search" method="GET" action="{{ route('admin.platform.database.index') }}">
                <div class="table-search-label">搜索数据表</div>
                <div class="table-search-input">
                    <input type="text" name="keyword" value="{{ $keyword }}" placeholder="输入表名关键字，例如 contents、attachments">
                </div>
                <button class="button" type="submit">筛选</button>
                <a class="button secondary" href="{{ route('admin.platform.database.index') }}">重置</a>
            </form>

            @if ($tables->isEmpty())
                <div class="empty-state">当前没有符合条件的数据表。</div>
            @else
                <div class="database-table-wrap">
                    <table class="database-table">
                        <thead>
                            <tr>
                                <th>表名</th>
                                <th>记录数</th>
                                <th>字段数</th>
                                <th>主键</th>
                                <th>说明</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tables as $table)
                                <tr>
                                    <td><code>{{ $table['name'] }}</code></td>
                                    <td>{{ number_format((int) $table['row_count']) }}</td>
                                    <td>{{ $table['column_count'] }}</td>
                                    <td>
                                        @if ($table['has_primary'])
                                            <span class="status-badge is-good">{{ $table['primary_key'] }}</span>
                                        @else
                                            <span class="status-badge is-warn">未识别</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="table-description" tabindex="0">
                                            {{ $table['label'] }}
                                            <span class="table-description-tooltip">{{ $table['description'] }}</span>
                                        </span>
                                    </td>
                                    <td><a class="button secondary" href="{{ route('admin.platform.database.show', ['table' => $table['name']]) }}">查看详情</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($tables->hasPages())
                    <div class="database-pagination">
                        {{ $tables->appends(request()->except('page'))->links() }}
                    </div>
                @endif
            @endif
        </div>
    </section>
@endsection
