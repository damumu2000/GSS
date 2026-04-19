@extends('layouts.admin')

@php
    $workspaceTitle = match($workspacePanel ?? 'editor') {
        'create' => '创建模板',
        'snapshots' => '模板快照',
        default => '模板编辑',
    };
@endphp

@section('title', $workspaceTitle . ' - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 模板管理 / ' . $workspaceTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/site-theme-editor.css') }}">
@endpush

@section('content')
    @php
        $sourceType = (string) ($templateMeta['source'] ?? 'default');
        $sourceLabel = match ($sourceType) {
            'override' => '站点自定义模板',
            'custom' => '站点模板',
            default => '当前模板',
        };
        $sourceBadgeClass = $sourceType === 'override' ? ' is-override' : '';
        $showSourceBadge = $sourceType !== 'custom';
        $templateGroupMeta = [
            'templates' => ['title' => '模板文件', 'desc' => '所有 TPL 模板文件与公共结构模板'],
            'styles' => ['title' => 'CSS 文件', 'desc' => '当前主题使用的样式文件'],
            'scripts' => ['title' => 'JS 文件', 'desc' => '当前主题使用的脚本文件'],
        ];
        $editorModalOpen = session('keep_theme_editor_open') || $errors->has('template_title') || $errors->has('template_source');
        $editorErrors = array_values(array_unique(array_merge(
            $errors->get('template_title'),
            $errors->get('template_source'),
        )));
        $createTemplateErrors = $errors->createTemplate;
        $oldTemplatePrefix = old('template_prefix', 'list');
        $oldTemplateTitle = old('template_title', '');
        $oldTemplateSuffix = old('template_suffix', '');
        $themeAssetErrors = $errors->themeAssets;
        $themeAssetsModalOpen = $themeAssetErrors->isNotEmpty() || request()->boolean('open_assets');
        $editorRouteSiteTemplateParam = ['site_template_id' => $siteTemplateId];
    @endphp

    <section class="page-header">
        <div>
            <h2 class="page-header-title">模板编辑</h2>
            <div class="page-header-desc">
                @if ($workspacePanel === 'create')
                    正在工作台中创建自定义模板，创建完成后会直接回到对应模板。
                @elseif ($workspacePanel === 'snapshots')
                    正在工作台中查看模板快照与历史对比，不会跳出当前模板上下文。
                @else
                    在左侧选择模板文件，再打开弹窗专注编辑源码。
                @endif
            </div>
        </div>
        <div class="topbar-right">
            <a class="button secondary" href="{{ route('admin.themes.index') }}">返回模板管理</a>
            <a class="button secondary" href="{{ \App\Support\SiteFrontendUrl::homeUrl($currentSite) }}" target="_blank">预览前台</a>
        </div>
    </section>

    <div class="workspace-shell">
        <section class="editor-panel is-template-tree-panel" data-template-tree-panel>
            <div class="editor-panel-header">
                <div class="editor-panel-header-main">
                    <span class="editor-panel-accent"></span>
                    <div class="editor-panel-title">模板树</div>
                </div>
                <div class="editor-panel-header-theme">
                    <span class="template-badge">{{ $themeName }}</span>
                </div>
            </div>
            <div class="editor-panel-body template-tree-panel-scroll" data-template-tree-scroll-shell>
                <div class="template-tree-panel-body" data-template-tree-scroll-body>
                    <div class="template-tree-search">
                        <input class="field" type="text" placeholder="搜索模板名称或文件名" data-template-tree-search>
                    </div>
                    <div class="template-tree-groups">
                        @foreach ($templateGroups as $group)
                            @php
                                $groupMeta = $templateGroupMeta[$group['key']] ?? ['title' => $group['title'], 'desc' => '模板文件分组'];
                            @endphp
                            <section class="template-tree-group" data-template-group>
                                <div class="template-tree-group-head">
                                    <div>
                                        <div class="template-tree-group-title">{{ $groupMeta['title'] }}</div>
                                        <div class="template-tree-group-desc">{{ $groupMeta['desc'] }}</div>
                                    </div>
                                    <span class="template-tree-group-count">{{ $group['items']->count() }}</span>
                                </div>
                                <div class="template-tree-list">
                                    @foreach ($group['items'] as $item)
                                        <a class="template-tree-link" href="{{ route('admin.themes.editor', array_filter(array_merge($editorRouteSiteTemplateParam, ['template' => $item['key'], 'panel' => $workspacePanel === 'snapshots' ? 'snapshots' : null]))) }}" data-template-tree-link data-search-text="{{ strtolower($item['label'].' '.$item['file']) }}">
                                            <article class="template-tree-item @if ($item['key'] === $template) is-active @endif">
                                                <div class="template-tree-item-head">
                                                    <div class="template-tree-item-title">{{ $item['label'] }}</div>
                                                </div>
                                                <div class="template-tree-item-subline">
                                                    <div class="template-tree-item-file">{{ $item['file'] }}</div>
                                                    @if (($item['source'] ?? 'default') !== 'custom')
                                                        <span class="template-badge{{ ($item['source'] ?? 'default') === 'override' ? ' is-override' : '' }}">
                                                            {{ ($item['source'] ?? 'default') === 'override' ? '站点自定义模板' : '平台默认' }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </article>
                                        </a>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section class="editor-panel">
            <div class="editor-panel-header">
                <div class="editor-panel-header-main">
                    <span class="editor-panel-accent"></span>
                        <div class="editor-panel-title">
                        @if ($workspacePanel === 'create')
                            创建模板
                        @elseif ($workspacePanel === 'snapshots')
                            模板快照
                        @else
                            模板工作台
                        @endif
                    </div>
                </div>
                <div class="editor-panel-header-actions">
                    <a class="button neutral-action editor-doc-button @if($workspacePanel === 'editor') is-active @endif" href="{{ route('admin.themes.editor', array_merge($editorRouteSiteTemplateParam, ['template' => $template])) }}" @if($workspacePanel === 'editor') aria-current="page" @endif>模板编辑</a>
                    <a class="button neutral-action editor-doc-button @if($workspacePanel === 'create') is-active @endif" href="{{ route('admin.themes.editor.template-create-form', array_merge($editorRouteSiteTemplateParam, ['template' => $template])) }}" @if($workspacePanel === 'create') aria-current="page" @endif>创建模板</a>
                    <a class="button neutral-action editor-doc-button @if($workspacePanel === 'snapshots') is-active @endif" href="{{ route('admin.themes.snapshots', array_merge($editorRouteSiteTemplateParam, ['template' => $template])) }}" @if($workspacePanel === 'snapshots') aria-current="page" @endif>模板快照</a>
                    <a class="button neutral-action editor-doc-button" href="{{ $templateQuickGuideUrl }}" target="_blank">模版开发文档</a>
                </div>
            </div>
            <div class="editor-panel-body">
                @if ($workspacePanel === 'create')
                    <form method="POST" action="{{ route('admin.themes.editor.template-create') }}" id="theme-template-create-form" novalidate>
                        @csrf
                        <input type="hidden" name="site_template_id" value="{{ $siteTemplateId }}">
                        <div class="workspace-form-grid is-compact">
                            <label class="field-group workspace-field-fixed">
                                <span class="field-label">模板标题</span>
                                <input class="field @if($createTemplateErrors->has('template_title')) is-error @endif" type="text" name="template_title" value="{{ $oldTemplateTitle }}" placeholder="如 校园新闻模板" data-template-title-limit="10">
                            </label>

                            <label class="field-group workspace-field-fixed">
                                <span class="field-label">模板标识</span>
                                <input class="field @if($createTemplateErrors->has('template_suffix')) is-error @endif" type="text" name="template_suffix" value="{{ $oldTemplateSuffix }}" placeholder="如 news、campus-focus" maxlength="40" data-template-suffix autocomplete="off">
                            </label>
                        </div>

                        <div class="workspace-form-grid is-compact">
                            <label class="field-group workspace-field-fixed">
                                <span class="field-label">模板类型</span>
                                <div class="custom-select @if($createTemplateErrors->has('template_prefix')) is-error @endif" data-custom-select data-template-prefix-select>
                                    <select class="custom-select-native" name="template_prefix" data-select-native data-template-prefix-input>
                                        <option value="list" @selected($oldTemplatePrefix === 'list')>列表模板</option>
                                        <option value="detail" @selected($oldTemplatePrefix === 'detail')>详情模板</option>
                                        <option value="page" @selected($oldTemplatePrefix === 'page')>单页模板</option>
                                        <option value="css" @selected($oldTemplatePrefix === 'css')>CSS 文件</option>
                                        <option value="js" @selected($oldTemplatePrefix === 'js')>JS 文件</option>
                                    </select>
                                    <button class="custom-select-trigger" type="button" data-select-trigger aria-expanded="false">
                                        <span data-select-label>{{ ['list' => '列表模板', 'detail' => '详情模板', 'page' => '单页模板', 'css' => 'CSS 文件', 'js' => 'JS 文件'][$oldTemplatePrefix] ?? '列表模板' }}</span>
                                    </button>
                                    <div class="custom-select-panel">
                                        @foreach (['list' => '列表模板', 'detail' => '详情模板', 'page' => '单页模板', 'css' => 'CSS 文件', 'js' => 'JS 文件'] as $prefixValue => $prefixLabel)
                                            <button class="custom-select-option @if($oldTemplatePrefix === $prefixValue) is-active @endif" type="button" data-select-option data-value="{{ $prefixValue }}">
                                                <span>{{ $prefixLabel }}</span>
                                                <svg class="custom-select-check" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5 6.5 11.5 12.5 4.5"/></svg>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </label>

                        </div>

                        <div class="workspace-action-row">
                            <button class="button" type="submit" data-template-create-submit>创建模板</button>
                        </div>
                    </form>
                @elseif ($workspacePanel === 'snapshots')
                    <section class="snapshot-intro-card">
                        <div class="snapshot-intro-title">{{ $templateMeta['label'] ?? $template }}</div>
                        <div class="snapshot-intro-desc">
                            快照最多保留 5 个，已收藏的快照会优先保留，不会被新快照自动覆盖清理。
                        </div>
                    </section>

                    @if ($templateHistory->isEmpty())
                        <div class="workspace-empty">当前模板还没有可用快照。</div>
                    @else
                        <div class="history-list">
                            @foreach ($templateHistory as $historyItem)
                                <article class="history-card">
                                    <div class="history-card-head">
                                        <div>
                                            <div class="history-card-title-row">
                                                <form method="POST" action="{{ route('admin.themes.editor.template-snapshot-favorite') }}">
                                                    @csrf
                                                    <input type="hidden" name="site_template_id" value="{{ $siteTemplateId }}">
                                                    <input type="hidden" name="template" value="{{ $template }}">
                                                    <input type="hidden" name="version_id" value="{{ $historyItem->id }}">
                                                    @if ($compareVersion)
                                                        <input type="hidden" name="version" value="{{ $compareVersion->id }}">
                                                    @endif
                                                    <button class="snapshot-favorite-button{{ !empty($historyItem->is_favorite) ? ' is-active' : '' }}" type="submit" data-template-snapshot-favorite-button data-tooltip="{{ !empty($historyItem->is_favorite) ? '已收藏，点击取消收藏' : '收藏后不会被新快照自动清理' }}" aria-label="{{ !empty($historyItem->is_favorite) ? '取消收藏快照' : '收藏快照' }}">
                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                                                    </button>
                                                </form>
                                                <div class="history-card-title">
                                                    {{ match($historyItem->action) {
                                                        'edit_template' => '保存模板前快照',
                                                        'create_template' => '创建模板前快照',
                                                        'delete_template' => '删除模板前快照',
                                                        'rollback_template' => '回滚模板前快照',
                                                        default => '模板历史快照',
                                                    } }}
                                                </div>
                                            </div>
                                            <div class="history-card-meta">
                                                {{ \Illuminate\Support\Carbon::parse($historyItem->created_at)->format('Y-m-d H:i:s') }}
                                                ·
                                                {{ match($historyItem->source_type) {
                                                    'override' => '站点自定义模板',
                                                    'custom' => '站点模板',
                                                    'default' => '平台默认',
                                                    'missing' => '创建前为空',
                                                    default => '模板快照',
                                                } }}
                                                @if (!empty($historyItem->is_favorite))
                                                    · 已收藏
                                                @endif
                                            </div>
                                        </div>
                                        <div class="history-card-actions">
                                            <a class="button secondary" href="{{ route('admin.themes.snapshots', array_merge($editorRouteSiteTemplateParam, ['template' => $template, 'version' => $historyItem->id])) }}" data-template-compare-link>查看对比</a>
                                            <form method="POST" action="{{ route('admin.themes.editor.template-rollback') }}">
                                                @csrf
                                                <input type="hidden" name="site_template_id" value="{{ $siteTemplateId }}">
                                                <input type="hidden" name="template" value="{{ $template }}">
                                                <input type="hidden" name="version_id" value="{{ $historyItem->id }}">
                                                <button class="button secondary" type="submit" data-template-rollback-button>回滚到此版</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.themes.editor.template-snapshot-delete') }}">
                                                @csrf
                                                <input type="hidden" name="site_template_id" value="{{ $siteTemplateId }}">
                                                <input type="hidden" name="template" value="{{ $template }}">
                                                <input type="hidden" name="version_id" value="{{ $historyItem->id }}">
                                                <button class="button secondary" type="submit" data-template-snapshot-delete-button>删除快照</button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                @else
                    <section class="summary-card">
                        <div class="summary-card-title">{{ $templateMeta['label'] ?? $template }}</div>
                        <div class="summary-card-meta">
                            <span class="template-badge">{{ $themeCode }}</span>
                            @if ($showSourceBadge)
                                <span class="template-badge{{ $sourceBadgeClass }}">{{ $sourceLabel }}</span>
                            @endif
                            @if ($latestTemplateVersion)
                                <span class="template-badge">可回滚</span>
                            @endif
                        </div>
                        <div class="summary-card-actions">
                            <button class="button" type="button" data-open-editor-modal>编辑源码</button>
                            @if (($templateMeta['source'] ?? 'default') === 'custom')
                                <button class="button secondary" type="submit" form="theme-delete-form">删除模板</button>
                            @endif
                        </div>

                        <div class="summary-detail-grid">
                            <section class="summary-side-card">
                                <div class="summary-side-label">模板文件</div>
                                <div class="summary-side-value">{{ $templateMeta['file'] ?? \App\Support\ThemeTemplateLocator::editorFilename($template) }}</div>
                                <div class="summary-side-note">当前编辑对象的实际模板文件名。</div>
                            </section>

                            @if ($sourceType !== 'custom')
                                <section class="summary-side-card">
                                    <div class="summary-side-label">模板来源</div>
                                    <div class="summary-side-value">{{ $sourceLabel }}</div>
                                    <div class="summary-side-note">
                                        @if ($sourceType === 'default')
                                            保存后会在当前站点模板目录生成自定义版本。
                                        @else
                                            当前模板文件已经存在站点级自定义版本。
                                        @endif
                                    </div>
                                </section>
                            @endif

                            @if ($latestTemplateVersion)
                                <section class="summary-side-card">
                                    <div class="summary-side-label">最近保存</div>
                                    <div class="summary-side-value">{{ \Illuminate\Support\Carbon::parse($latestTemplateVersion->created_at)->format('Y-m-d H:i') }}</div>
                                    <div class="summary-side-note">可在源码弹窗中快速回滚上一版，或进入模板快照查看完整历史。</div>
                                </section>
                            @endif
                        </div>
                    </section>
                @endif

                @if (($templateMeta['source'] ?? 'default') === 'custom')
                    <form id="theme-delete-form" method="POST" action="{{ route('admin.themes.editor.template-delete') }}">
                        @csrf
                        <input type="hidden" name="site_template_id" value="{{ $siteTemplateId }}">
                        <input type="hidden" name="template" value="{{ $template }}">
                    </form>
                @endif

                @if ($latestTemplateVersion)
                    <form id="theme-rollback-form" method="POST" action="{{ route('admin.themes.editor.template-rollback') }}">
                        @csrf
                        <input type="hidden" name="site_template_id" value="{{ $siteTemplateId }}">
                        <input type="hidden" name="template" value="{{ $template }}">
                    </form>
                @endif

                @if ($workspacePanel === 'editor')
                    <section class="summary-card theme-assets-card">
                        <div class="summary-card-title">模板资源</div>
                        <div class="summary-card-meta">
                            <span class="template-badge">assets/</span>
                            <span class="template-badge">{{ $themeAssetsTotalCount }} 个资源</span>
                            <span class="template-badge">模板资源已用 {{ $themeAssetUsageLabel }}</span>
                        </div>
                        <div class="theme-assets-capacity">
                            <div class="theme-assets-capacity-labels">
                                <span>模板资源 {{ $themeAssetUsageLabel }}</span>
                                <span>站点总容量 {{ $totalStorageUsageLabel }} / {{ $storageLimitLabel }}</span>
                            </div>
                            <div class="theme-assets-capacity-bars" aria-hidden="true">
                                <div class="theme-assets-capacity-bar-row">
                                    <span class="theme-assets-capacity-bar-label">站点总量</span>
                                    <progress class="theme-assets-capacity-progress is-total" max="100" value="{{ $totalStorageUsagePercent }}"></progress>
                                </div>
                                <div class="theme-assets-capacity-bar-row">
                                    <span class="theme-assets-capacity-bar-label">模板资源</span>
                                    <progress class="theme-assets-capacity-progress is-theme" max="100" value="{{ $themeAssetUsagePercent }}"></progress>
                                </div>
                        </div>
                    </div>
                        <div class="theme-assets-summary-actions">
                            <button class="button secondary is-library" type="button" data-open-theme-assets-modal data-theme-assets-mode="manage">打开模板资源</button>
                        </div>
                    </section>
                @endif
            </div>
        </section>
    </div>

    @if ($workspacePanel === 'snapshots' && $compareVersion)
        <section class="history-compare-modal" data-history-compare-modal>
            <div class="history-compare-backdrop" data-history-compare-close></div>
            <div class="history-compare-dialog" role="dialog" aria-modal="true" aria-labelledby="history-compare-title">
                <div class="history-compare-dialog-head">
                    <div>
                        <div class="history-compare-dialog-title" id="history-compare-title">模板对比</div>
                        <div class="history-compare-dialog-desc">
                            当前内容与历史版本 {{ \Illuminate\Support\Carbon::parse($compareVersion->created_at)->format('Y-m-d H:i:s') }} 的差异对比。
                        </div>
                    </div>
                    <div class="history-compare-dialog-actions">
                        <form method="POST" action="{{ route('admin.themes.editor.template-rollback') }}">
                            @csrf
                            <input type="hidden" name="site_template_id" value="{{ $siteTemplateId }}">
                            <input type="hidden" name="template" value="{{ $template }}">
                            <input type="hidden" name="version_id" value="{{ $compareVersion->id }}">
                            <button class="button secondary" type="submit" data-template-rollback-button>回滚到此版</button>
                        </form>
                        <button class="history-compare-close" type="button" data-history-compare-close aria-label="关闭对比弹窗">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>
                </div>
                <div class="history-compare-workspace">
                    <div class="history-compare-grid">
                        <div class="history-compare-panel-head">当前内容</div>
                        <div class="history-compare-panel-head">历史版本 {{ \Illuminate\Support\Carbon::parse($compareVersion->created_at)->format('Y-m-d H:i:s') }}</div>
                    </div>
                    <div class="history-diff-scroll">
                        <table class="history-diff-table">
                            <tbody>
                                @foreach ($diffRows as $row)
                                    <tr class="history-diff-row{{ !empty($row['is_changed']) ? ' is-changed' : '' }}" @if (!empty($row['is_changed'])) data-first-diff @endif>
                                        <td class="history-diff-line">{{ $row['current_line_no'] ?? '' }}</td>
                                        <td class="history-diff-content history-diff-side">
                                            @php($currentContent = (string) ($row['current_content'] ?? ''))
                                            <pre class="history-diff-code">{{ $currentContent !== '' ? $currentContent : ' ' }}</pre>
                                        </td>
                                        <td class="history-diff-line">{{ $row['history_line_no'] ?? '' }}</td>
                                        <td class="history-diff-content history-diff-side">
                                            @php($historyContent = (string) ($row['history_content'] ?? ''))
                                            @if (!empty($row['history_empty_note']))
                                                <span class="history-diff-empty">{{ $row['history_empty_note'] }}</span>
                                            @else
                                                <pre class="history-diff-code">{{ $historyContent !== '' ? $historyContent : ' ' }}</pre>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="editor-modal @if($editorModalOpen) is-open @endif" data-editor-modal>
        <div class="editor-modal-backdrop" data-close-editor-modal></div>
        <div class="editor-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="template-editor-modal-title">
                <div class="editor-modal-head">
                    <div>
                        <div class="editor-modal-title" id="template-editor-modal-title">编辑模板</div>
                        <div class="editor-modal-desc">{{ $templateMeta['label'] ?? $template }}（{{ $templateMeta['file'] ?? \App\Support\ThemeTemplateLocator::editorFilename($template) }}）</div>
                    </div>
                    <div class="editor-modal-actions">
                        <button class="button" type="submit" form="theme-editor-form" data-loading-text="保存中...">保存模板源码</button>
                        <button class="button secondary is-library" type="button" data-open-theme-assets-modal data-theme-assets-mode="insert">模板资源</button>
                        @if ($latestTemplateVersion)
                            <button class="button secondary" type="submit" form="theme-rollback-form">回滚上一版</button>
                        @endif
                    <a class="button neutral-action editor-doc-button" href="{{ route('admin.themes.snapshots', array_merge($editorRouteSiteTemplateParam, ['template' => $template])) }}">模板快照</a>
                    <button class="editor-modal-close" type="button" data-close-editor-modal aria-label="关闭源码编辑弹窗">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
            </div>

            <form id="theme-editor-form" class="editor-modal-form" method="POST" action="{{ route('admin.themes.editor.update') }}" novalidate>
                @csrf
                <input type="hidden" name="site_template_id" value="{{ $siteTemplateId }}">
                <input type="hidden" name="template" value="{{ $template }}">
                <div class="editor-modal-body">
                    <div class="editor-modal-fields">
                        <div class="field-group template-title-group">
                            <label class="field-label" for="template_title">模板标题</label>
                            <input class="field template-title-field @error('template_title') is-error @enderror" id="template_title" name="template_title" type="text" value="{{ old('template_title', $templateTitle) }}" placeholder="如 校园新闻模板" data-template-title-limit="10">
                        </div>

                        <div class="field-group">
                            <span class="field-label">当前主题</span>
                            <div class="template-status-stack">
                                <span class="template-badge">{{ $themeCode }}</span>
                                @if ($showSourceBadge)
                                    <span class="template-badge{{ $sourceBadgeClass }}">{{ $sourceLabel }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="field-group editor-source-group">
                        <label class="field-label" for="template_source">{{ $templateSourceFieldLabel }}</label>
                        <div class="code-editor-shell">
                            <div class="code-editor-gutter" aria-hidden="true">
                                <div class="code-editor-gutter-inner" id="template_source_gutter">
                                    <span class="code-editor-gutter-line">1</span>
                                </div>
                            </div>
                            <div class="code-editor-main">
                                <textarea class="code-area" id="template_source" name="template_source" spellcheck="false" wrap="off">{{ old('template_source') }}</textarea>
                                <textarea id="template_source_bootstrap" hidden aria-hidden="true">{{ old('template_source', $templateSource) }}</textarea>
                            </div>
                        </div>
                        <div class="editor-modal-footer">
                            <span class="field-note">保存前会再次进行模板标题和模板语法校验。TPL 模板请使用 <code>themeStyle</code> / <code>themeScript</code> 引入资源，不支持内联 <code>style</code>、内联 <code>script</code> 和事件属性。</span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="theme-assets-modal @if($themeAssetsModalOpen) is-open @endif @if($themeAssetsModalMode === 'manage') is-manage-mode @endif" data-theme-assets-modal data-mode="{{ $themeAssetsModalMode }}" data-theme-assets-ready="{{ $themeAssetsModalOpen ? '1' : '0' }}">
        <div class="theme-assets-modal-backdrop" data-close-theme-assets-modal></div>
        <div class="theme-assets-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="theme-assets-modal-title">
            <form class="theme-assets-upload" method="POST" action="{{ route('admin.themes.editor.asset-upload') }}" enctype="multipart/form-data" data-theme-assets-upload-form>
                @csrf
                <input type="hidden" name="site_template_id" value="{{ $siteTemplateId }}">
                <input type="hidden" name="template" value="{{ $template }}">
                <input type="hidden" name="open_assets" value="1">
            <div class="theme-assets-modal-head">
                <div class="theme-assets-modal-head-copy">
                    <div class="theme-assets-modal-title" id="theme-assets-modal-title">模板资源</div>
                    <div class="theme-assets-modal-desc">
                        管理当前主题目录下的 <code>assets/</code> 资源，支持上传、替换、路径插入。
                        <span class="theme-assets-upload-limit">
                            <span class="template-badge">支持 JPG / PNG / GIF / WEBP / SVG / WOFF / WOFF2 / JSON</span>
                            <span class="template-badge">单文件最大 10 MB</span>
                        </span>
                    </div>
                </div>
                    <div class="theme-assets-modal-actions">
                    <button class="theme-assets-modal-close" type="button" data-close-theme-assets-modal aria-label="关闭模板资源弹窗">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
            </div>
                <div class="theme-assets-modal-body">
                    <input class="theme-assets-file-input" type="file" name="asset" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.woff,.woff2,.json" hidden data-theme-assets-file-input>
                    <div class="theme-assets-toolbar">
                        <div class="theme-assets-toolbar-search">
                            <input type="search" placeholder="搜索资源名称或路径" value="{{ $assetKeyword ?? '' }}" data-theme-assets-search>
                        </div>
                        <div class="theme-assets-toolbar-filter">
                            <select data-theme-assets-type>
                                <option value="all" @selected(($assetType ?? 'all') === 'all')>全部类型</option>
                                <option value="image" @selected(($assetType ?? 'all') === 'image')>图片</option>
                                <option value="font" @selected(($assetType ?? 'all') === 'font')>字体</option>
                                <option value="json" @selected(($assetType ?? 'all') === 'json')>JSON</option>
                                <option value="file" @selected(($assetType ?? 'all') === 'file')>其他</option>
                            </select>
                        </div>
                        <button class="button secondary theme-assets-filter-button" type="button" data-theme-assets-search-trigger>筛选</button>
                        <button class="button secondary theme-assets-reset-button" type="button" data-theme-assets-reset-trigger>重置</button>
                        <div class="theme-assets-toolbar-actions">
                        <div class="theme-assets-modal-stats">
                            <span class="template-badge">
                                {{ ($themeAssetsModalOpen ? ($themeAssetsFilteredCount ?? $themeAssetsTotalCount) : null) ? ($themeAssetsFilteredCount ?? $themeAssetsTotalCount) : '...' }} 个资源
                            </span>
                            <span class="template-badge">已用 {{ $themeAssetUsageLabel }}</span>
                        </div>
                            <button class="button" type="button" data-theme-assets-upload-trigger>上传资源</button>
                        </div>
                    </div>
                    @if ($themeAssetErrors->has('asset'))
                        <span class="form-error">{{ $themeAssetErrors->first('asset') }}</span>
                    @endif

                @if (!$themeAssetsModalOpen)
                    <div class="workspace-empty is-compact">加载模板资源中...</div>
                @elseif ($themeAssets->isEmpty())
                    <div class="workspace-empty is-compact">
                        {{ ($assetKeyword ?? '') !== '' || (($assetType ?? 'all') !== 'all') ? '没有匹配的资源。' : '当前模板目录下还没有资源文件。' }}
                    </div>
                @else
                    <div class="theme-assets-list">
                        @foreach ($themeAssets as $asset)
                            <article class="theme-asset-item" data-theme-asset-card data-asset-path="{{ $asset['path'] }}" data-asset-name="{{ $asset['name'] }}" data-asset-type="{{ $asset['asset_type'] ?? 'file' }}" data-asset-source="{{ $asset['source'] }}">
                                <div class="theme-asset-preview{{ $asset['is_previewable_image'] ? ' is-image' : '' }}{{ $asset['is_previewable_image'] ? ' is-clickable' : '' }}" @if($asset['is_previewable_image']) role="button" tabindex="0" data-theme-asset-preview-trigger data-asset-url="{{ $asset['url'] }}" data-asset-name="{{ $asset['name'] }}" @endif>
                                    @if ($asset['is_previewable_image'])
                                        <img src="{{ $asset['url'] }}" alt="{{ $asset['name'] }}">
                                    @else
                                        <span>{{ $asset['kind'] }}</span>
                                    @endif
                                    @if (!empty($asset['show_large_image_warning']))
                                        <div class="theme-asset-warning theme-asset-warning-overlay">
                                            <span class="theme-asset-warning-icon">!</span>
                                            <span>图片过大谨慎使用，会严重拖慢网站访问速度，建议压缩或更换！</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="theme-asset-main">
                                    <div class="theme-asset-name" data-tooltip="{{ $asset['path'] }}">{{ $asset['name'] }}</div>
                                    <div class="theme-asset-meta">
                                        <span>{{ strtoupper($asset['extension'] ?: 'FILE') }}</span>
                                        <span>{{ $asset['size_label'] }}</span>
                                        @if (!empty($asset['dimensions_label']))
                                            <span class="theme-asset-dimensions">{{ $asset['dimensions_label'] }}</span>
                                        @endif
                                        <span class="template-badge theme-asset-source-badge{{ $asset['source'] === 'override' ? ' is-override' : '' }}">{{ $asset['source'] === 'override' ? '站点自定义资源' : '平台默认资源' }}</span>
                                        <span class="theme-asset-updated">更新 {{ $asset['updated_label'] }}</span>
                                    </div>
                                </div>
                                <div class="theme-asset-actions">
                                    <button class="button neutral-action editor-doc-button theme-asset-action-button" type="button" data-insert-theme-asset data-asset-path="{{ $asset['path'] }}">插入路径</button>
                                    <form class="theme-asset-replace-form" method="POST" action="{{ route('admin.themes.editor.asset-upload') }}" enctype="multipart/form-data" data-theme-assets-replace-form>
                                        @csrf
                                        <input type="hidden" name="site_template_id" value="{{ $siteTemplateId }}">
                                        <input type="hidden" name="template" value="{{ $template }}">
                                        <input type="hidden" name="open_assets" value="1">
                                        <input type="hidden" name="replace_asset_path" value="{{ $asset['path'] }}">
                                        <input class="theme-asset-replace-input" type="file" name="asset" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.woff,.woff2,.json" hidden data-theme-asset-replace-input>
                                        <button class="button neutral-action editor-doc-button theme-asset-action-button" type="button" data-theme-asset-replace-trigger>替换</button>
                                    </form>
                                    @if ($asset['source'] === 'override')
                                        <form class="theme-asset-delete-form" method="POST" action="{{ route('admin.themes.editor.asset-delete') }}" data-theme-assets-delete-form>
                                            @csrf
                                            <input type="hidden" name="site_template_id" value="{{ $siteTemplateId }}">
                                            <input type="hidden" name="template" value="{{ $template }}">
                                            <input type="hidden" name="open_assets" value="1">
                                            <input type="hidden" name="asset_path" value="{{ $asset['path'] }}">
                                            <button class="button secondary theme-asset-action-button" type="submit">删除</button>
                                        </form>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                    @if ($themeAssets->hasPages())
                        <div class="theme-assets-pagination">
                            <nav aria-label="模板资源分页">
                                <div class="pagination-shell">
                                    @if ($themeAssets->onFirstPage())
                                        <span class="pagination-button is-disabled">上一页</span>
                                    @else
                                        <a class="pagination-button" href="{{ $themeAssets->previousPageUrl() }}">上一页</a>
                                    @endif

                                    <div class="pagination-pages">
                                        @for ($page = 1; $page <= $themeAssets->lastPage(); $page++)
                                            @if ($page === $themeAssets->currentPage())
                                                <span class="pagination-page is-active">{{ $page }}</span>
                                            @else
                                                <a class="pagination-page" href="{{ $themeAssets->url($page) }}">{{ $page }}</a>
                                            @endif
                                        @endfor
                                    </div>

                                    @if ($themeAssets->hasMorePages())
                                        <a class="pagination-button" href="{{ $themeAssets->nextPageUrl() }}">下一页</a>
                                    @else
                                        <span class="pagination-button is-disabled">下一页</span>
                                    @endif
                                </div>
                            </nav>
                        </div>
                    @endif
                @endif
            </div>
            </form>
        </div>
    </section>

    <section class="theme-asset-preview-modal" data-theme-asset-preview-modal hidden>
        <div class="theme-asset-preview-backdrop" data-close-theme-asset-preview></div>
        <div class="theme-asset-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="theme-asset-preview-title">
            <div class="theme-asset-preview-head">
                <div class="theme-asset-preview-title" id="theme-asset-preview-title">资源预览</div>
                <button class="theme-assets-modal-close" type="button" data-close-theme-asset-preview aria-label="关闭资源预览">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="theme-asset-preview-body">
                <img src="" alt="" data-theme-asset-preview-image>
            </div>
            <div class="theme-asset-preview-caption" data-theme-asset-preview-caption></div>
        </div>
    </section>

    <div
        id="theme-editor-config"
        hidden
        data-server-editor-errors='@json($editorErrors)'
        data-server-create-errors='@json(array_values(array_unique($createTemplateErrors->all())))'
        data-compare-clean-url="{{ route('admin.themes.snapshots', array_merge($editorRouteSiteTemplateParam, ['template' => $template])) }}"
        data-theme-assets-open="{{ $themeAssetsModalOpen ? '1' : '0' }}"
    ></div>
@endsection

@push('scripts')
    <script src="{{ asset('js/site-theme-editor.js') }}"></script>
@endpush
