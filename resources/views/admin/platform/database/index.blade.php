@extends('layouts.admin')

@section('title', '数据库管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 数据库管理')

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
        .database-overview { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 18px; }
        .overview-card {
            padding: 18px 20px;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }
        .overview-label { color: #98a2b3; font-size: 12px; line-height: 1.6; font-weight: 700; }
        .overview-value { margin-top: 8px; color: #1f2937; font-size: 24px; line-height: 1.25; font-weight: 700; }
        .overview-note { margin-top: 6px; color: #6b7280; font-size: 13px; line-height: 1.7; }
        .database-layout { display: grid; grid-template-columns: 1fr; gap: 18px; }
        .panel {
            padding: 20px 22px;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            background: #ffffff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
            min-width: 0;
        }
        .panel-title { margin: 0; color: #1f2937; font-size: 16px; line-height: 1.5; font-weight: 700; }
        .panel-desc { margin-top: 6px; color: #8c8c8c; font-size: 13px; line-height: 1.7; }
        .table-search {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .table-search-label {
            color: #1f2937;
            font-size: 14px;
            line-height: 1.4;
            font-weight: 700;
            white-space: nowrap;
        }
        .table-search-input {
            width: min(560px, 100%);
        }
        .table-search-input input {
            width: 100%;
        }
        .database-table-wrap { overflow-x: auto; }
        .database-table { width: 100%; border-collapse: collapse; min-width: 760px; }
        .database-table th,
        .database-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #eef2f6;
            text-align: left;
            color: #475467;
            font-size: 13px;
            line-height: 1.7;
            vertical-align: top;
        }
        .database-table th { color: #98a2b3; font-size: 12px; font-weight: 700; }
        .database-table code { font-size: 12px; color: #344054; }
        .table-description {
            position: relative;
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 12px;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid #e9eef5;
            color: #475467;
            font-size: 12px;
            line-height: 1;
            font-weight: 700;
            cursor: help;
        }
        .table-description-tooltip {
            position: absolute;
            left: 50%;
            bottom: calc(100% + 10px);
            transform: translateX(-50%) translateY(4px);
            min-width: 240px;
            max-width: 360px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(15, 23, 42, 0.94);
            color: #ffffff;
            font-size: 12px;
            line-height: 1.6;
            font-weight: 500;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.16);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.18s ease, transform 0.18s ease;
            z-index: 20;
            white-space: normal;
        }
        .table-description-tooltip::after {
            content: '';
            position: absolute;
            left: 50%;
            top: 100%;
            width: 12px;
            height: 8px;
            background: rgba(15, 23, 42, 0.94);
            transform: translateX(-50%);
            clip-path: polygon(50% 100%, 0 0, 100% 0);
        }
        .table-description:hover .table-description-tooltip,
        .table-description:focus-visible .table-description-tooltip {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }
        .status-badge {
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
        .status-badge.is-good { background: rgba(16, 185, 129, 0.10); color: #059669; }
        .status-badge.is-warn { background: rgba(245, 158, 11, 0.12); color: #b45309; }
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
        .empty-state {
            padding: 36px 24px;
            text-align: center;
            color: #8c8c8c;
            border: 1px dashed #dbe4ee;
            border-radius: 16px;
            background: #ffffff;
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
        @media (max-width: 1200px) {
            .database-overview { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 768px) {
            .page-header { margin: -24px -18px 20px; padding: 18px; flex-direction: column; align-items: flex-start; }
            .database-overview { grid-template-columns: 1fr; }
            .table-search {
                align-items: stretch;
            }
            .table-search-input {
                width: 100%;
            }
        }
    </style>
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
