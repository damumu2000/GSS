@extends('layouts.admin')

@section('title', ($pageTitle ?? '操作日志') . ' - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / ' . ($pageTitle ?? '操作日志'))

@php
    $actionMap = [
        'create' => '新增',
        'update' => '更新',
        'delete' => '删除',
        'switch' => '切换',
        'restore' => '恢复',
        'upload' => '上传',
        'publish' => '发布',
        'disable' => '停用',
        'enable' => '启用',
        'login' => '登录',
        'logout' => '退出',
        'setting' => '配置',
        'restore_template' => '还原模板',
        'upload_library' => '上传资源',
        'upload_image' => '上传图片',
        'upload_media' => '上传媒体',
        'bulk_delete' => '批量删除',
        'bulk_restore' => '批量恢复',
        'bulk_publish' => '批量发布',
        'edit_template' => '编辑模板',
        'remove' => '删除',
        'create_site' => '新增站点',
        'update_site' => '更新站点',
        'delete_site' => '删除站点',
        'switch_theme' => '切换主题',
        'save_theme' => '保存模板',
        'update_settings' => '更新设置',
        'update_permissions' => '更新权限',
        'submit_review' => '提交审核',
        'approve_content' => '审核通过',
        'reject_content' => '驳回内容',
    ];

    $actionOptions = [
        '' => '全部动作',
        'create' => '新增',
        'update' => '更新',
        'delete' => '删除',
        'switch' => '切换',
        'restore' => '恢复',
        'upload' => '上传',
        'publish' => '发布',
        'disable' => '停用',
        'enable' => '启用',
        'login' => '登录',
        'logout' => '退出',
        'setting' => '配置',
        'restore_template' => '还原模板',
        'upload_library' => '上传资源',
        'upload_image' => '上传图片',
        'upload_media' => '上传媒体',
        'bulk_delete' => '批量删除',
        'bulk_restore' => '批量恢复',
        'bulk_publish' => '批量发布',
        'edit_template' => '编辑模板',
        'remove' => '删除',
        'create_site' => '新增站点',
        'update_site' => '更新站点',
        'delete_site' => '删除站点',
        'switch_theme' => '切换主题',
        'save_theme' => '保存模板',
        'update_settings' => '更新设置',
        'update_permissions' => '更新权限',
        'submit_review' => '提交审核',
        'approve_content' => '审核通过',
        'reject_content' => '驳回内容',
    ];
@endphp

@push('styles')
    <link rel="stylesheet" href="/css/logs.css">
@endpush

@section('content')
    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">{{ $pageTitle ?? '操作日志' }}</h2>
            <div class="page-header-desc">{{ $pageDescription ?? '展示平台级和当前站点相关的最近操作。' }}</div>
        </div>
    </section>

    <section class="log-filters-card">
        <form method="GET" action="{{ $formRoute ?? route('admin.logs.index') }}" class="filters">
            <div>
                <label for="keyword">关键词</label>
                <input class="field" id="keyword" type="text" name="keyword" value="{{ $keyword }}" placeholder="模块、动作、目标类型、操作者">
            </div>
            @if (($showScopeFilter ?? true) === true)
                <div>
                    <label for="scope">范围</label>
                    <div class="custom-select" data-select>
                        <select class="custom-select-native" id="scope" name="scope">
                            <option value="">全部范围</option>
                            <option value="platform" @selected($selectedScope === 'platform')>平台</option>
                            <option value="site" @selected($selectedScope === 'site')>站点</option>
                        </select>
                        <button class="custom-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">全部范围</button>
                        <div class="custom-select-panel" data-select-panel role="listbox"></div>
                    </div>
                </div>
            @endif
            <div>
                <label for="module">模块</label>
                <div class="custom-select" data-select>
                    <select class="custom-select-native" id="module" name="module">
                        <option value="">全部模块</option>
                        @foreach ($moduleOptions as $moduleOption)
                            <option value="{{ $moduleOption }}" @selected($selectedModule === $moduleOption)>{{ $moduleOption }}</option>
                        @endforeach
                    </select>
                    <button class="custom-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">全部模块</button>
                    <div class="custom-select-panel" data-select-panel role="listbox"></div>
                </div>
            </div>
            <div>
                <label for="action">动作</label>
                <div class="custom-select" data-select>
                    <select class="custom-select-native" id="action" name="action">
                        @foreach ($actionOptions as $actionValue => $actionLabel)
                            <option value="{{ $actionValue }}" @selected($selectedAction === $actionValue)>{{ $actionLabel }}</option>
                        @endforeach
                    </select>
                    <button class="custom-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">全部动作</button>
                    <div class="custom-select-panel" data-select-panel role="listbox"></div>
                </div>
            </div>
            <div class="filter-actions">
                <button class="button neutral-action" type="submit">查询</button>
            </div>
        </form>
    </section>

    <section class="log-table-panel">
        <div class="log-table-head">
            <h3 class="log-table-title">最近日志</h3>
            <span class="badge">{{ $logs->total() }} 条</span>
        </div>

        <div class="log-table-wrap">
            <table class="log-table">
                <thead>
                <tr>
                    <th>时间</th>
                    <th>范围</th>
                    <th>模块</th>
                    <th>动作</th>
                    <th>操作者</th>
                    <th>目标</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at }}</td>
                        <td><span class="log-badge">{{ $log->scope === 'platform' ? '平台' : '站点' }}</span></td>
                        <td><span class="log-badge">{{ $log->module ?: '-' }}</span></td>
                        <td class="log-action">{{ $actionMap[$log->action] ?? str_replace('_', ' ', $log->action) }}</td>
                        <td>{{ $log->user_name ?: $log->username ?: '系统' }}</td>
                        <td>{{ $log->target_display ?? (($log->target_type ?: '-') . ($log->target_id ? '#'.$log->target_id : '')) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty">当前筛选条件下没有操作日志。</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination log-pagination">{{ $logs->links() }}</div>
    </section>
@endsection

@push('scripts')
    <script src="/js/logs-index.js"></script>
@endpush
