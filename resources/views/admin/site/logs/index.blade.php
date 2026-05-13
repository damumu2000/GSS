@extends('layouts.admin')

@section('title', ($pageTitle ?? '操作日志') . ' - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / ' . ($pageTitle ?? '操作日志'))

@php
    $moduleMap = [
        'auth' => '登录认证',
        'site' => '站点管理',
        'user' => '平台管理员',
        'site_user' => '站点操作员',
        'platform_role' => '平台角色',
        'site_role' => '站点角色',
        'role' => '站点角色',
        'channel' => '栏目管理',
        'content' => '内容管理',
        'attachment' => '资源库',
        'theme' => '模板管理',
        'setting' => '站点设置',
        'system_setting' => '系统设置',
        'system_check' => '系统检查',
        'recycle_bin' => '回收站',
        'promo' => '图宣管理',
        'guestbook' => '留言板',
        'article_review' => '文章审核',
        'module' => '模块管理',
    ];

    $actionMap = config('cms.operation_log_action_labels', []);
    $actionOptions = ['' => '全部动作'] + $actionMap;
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
                            <option value="{{ $moduleOption }}" @selected($selectedModule === $moduleOption)>{{ $moduleMap[$moduleOption] ?? ('未定义模块（'.$moduleOption.'）') }}</option>
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
                    @php
                        $actionText = $actionMap[$log->action] ?? ('未定义动作（'.$log->action.'）');
                        $payload = null;
                        if (is_string($log->payload) && trim($log->payload) !== '') {
                            $decoded = json_decode($log->payload, true);
                            if (is_array($decoded)) {
                                $payload = $decoded;
                            }
                        }
                        $riskHints = [];
                        if (is_array($payload)) {
                            if (!empty($payload['ip']) && is_string($payload['ip'])) {
                                $riskHints[] = 'IP：'.trim($payload['ip']);
                            }
                            if (!empty($payload['phone']) && is_string($payload['phone'])) {
                                $riskHints[] = '手机号：'.trim($payload['phone']);
                            }
                        }
                        $actionTooltip = $riskHints !== [] ? ($actionText.'｜'.implode('｜', $riskHints)) : $actionText;
                    @endphp
                    <tr>
                        <td>{{ $log->created_at }}</td>
                        <td><span class="log-badge">{{ $log->scope === 'platform' ? '平台' : '站点' }}</span></td>
                        <td><span class="log-badge">{{ $log->module ? ($moduleMap[$log->module] ?? ('未定义模块（'.$log->module.'）')) : '-' }}</span></td>
                        <td class="log-action-cell" data-tooltip="{{ $actionTooltip }}">
                            <span class="log-action">{{ $actionText }}</span>
                        </td>
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
