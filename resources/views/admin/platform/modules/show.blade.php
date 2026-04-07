@extends('layouts.admin')

@section('title', $module['name'] . ' - 模块管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 模块管理 / ' . $module['name'])

@php
    $moduleFolder = basename((string) ($module['path'] ?? ''));
@endphp

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
        .module-shell { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; }
        .module-card, .module-side-card {
            padding: 20px 22px;
            border: 1px solid #eef2f6;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
            min-width: 0;
        }

        .module-section + .module-section { margin-top: 22px; }
        .module-section-title { margin: 0; color: #1f2937; font-size: 16px; line-height: 1.5; font-weight: 700; }
        .module-list { display: grid; gap: 10px; margin-top: 14px; }
        .module-list-item { color: #4b5563; font-size: 13px; line-height: 1.75; }
        .module-file-list { display: grid; gap: 8px; margin-top: 14px; min-width: 0; }
        .module-file-item {
            position: relative;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #eef2f6;
            background: #f8fafc;
            color: #475467;
            font-size: 12px;
            line-height: 1.7;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            cursor: default;
            min-width: 0;
            width: 100%;
            max-width: 100%;
            transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }
        .module-file-item-text {
            display: block;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .module-file-item:hover,
        .module-file-item:focus-within {
            border-color: #dbe5f1;
            background: #ffffff;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
            transform: translateY(-1px);
        }
        .module-file-item-tooltip {
            position: absolute;
            left: 12px;
            bottom: calc(100% + 10px);
            width: min(520px, calc(100vw - 72px));
            max-width: calc(100vw - 72px);
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(17, 24, 39, 0.96);
            color: #ffffff;
            font-size: 12px;
            line-height: 1.7;
            white-space: normal;
            word-break: break-all;
            box-shadow: 0 18px 32px rgba(15, 23, 42, 0.18);
            opacity: 0;
            pointer-events: none;
            transform: translateY(4px);
            transition: opacity 0.18s ease, transform 0.18s ease;
            z-index: 20;
        }
        .module-file-item-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 24px;
            border-width: 6px;
            border-style: solid;
            border-color: rgba(17, 24, 39, 0.96) transparent transparent transparent;
        }
        .module-file-item:hover .module-file-item-tooltip,
        .module-file-item:focus-within .module-file-item-tooltip {
            opacity: 1;
            transform: translateY(0);
        }

        .module-meta { display: grid; gap: 12px; }
        .module-meta-row { display: grid; gap: 4px; }
        .module-meta-label { color: #98a2b3; font-size: 12px; line-height: 1.5; font-weight: 700; }
        .module-meta-value { color: #1f2937; font-size: 14px; line-height: 1.7; word-break: break-word; }
        .module-status {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 12px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #6b7280;
            font-size: 12px;
            font-weight: 700;
        }

        .module-status.is-on {
            background: rgba(16, 185, 129, 0.10);
            color: #059669;
        }

        .module-status.is-missing {
            background: rgba(245, 158, 11, 0.12);
            color: #b45309;
        }

        .module-side-actions { display: grid; gap: 10px; margin-top: 18px; }

        @media (max-width: 960px) {
            .page-header {
                margin: -24px -18px 20px;
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
            }

            .module-shell { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">{{ $module['name'] }}</h2>
            <div class="page-header-desc">{{ $module['description'] !== '' ? $module['description'] : '当前模块尚未补充详细说明。' }}</div>
        </div>
        <div class="page-header-actions">
            <a class="button secondary" href="{{ route('admin.platform.modules.index') }}">返回模块管理</a>
        </div>
    </section>

    <div class="module-shell">
        <section class="module-card">
            <div class="module-section">
                <h3 class="module-section-title">配置说明</h3>
                <div class="module-list">
                    @forelse ($module['settings'] as $setting)
                        <div class="module-list-item">{{ $setting }}</div>
                    @empty
                        <div class="module-list-item muted">当前模块尚未声明可配置项。</div>
                    @endforelse
                </div>
            </div>

            <div class="module-section">
                <h3 class="module-section-title">权限说明</h3>
                <div class="module-list">
                    @forelse ($module['permissions'] as $permission)
                        <div class="module-list-item"><code>{{ $permission }}</code></div>
                    @empty
                        <div class="module-list-item muted">当前模块尚未声明权限点。</div>
                    @endforelse
                </div>
            </div>

            <div class="module-section">
                <h3 class="module-section-title">数据表说明</h3>
                <div class="module-list">
                    @forelse ($module['tables'] ?? [] as $table)
                        <div class="module-list-item"><code>{{ $table }}</code></div>
                    @empty
                        <div class="module-list-item muted">当前模块尚未声明数据表。</div>
                    @endforelse
                </div>
            </div>

            <div class="module-section">
                <h3 class="module-section-title">文件说明</h3>
                <div class="module-file-list">
                    @forelse ($module['files'] as $file)
                        @php
                            $displayFile = ($moduleFolder !== '' ? $moduleFolder.'/' : '').ltrim($file, '/');
                        @endphp
                        <div class="module-file-item" tabindex="0">
                            <span class="module-file-item-text">{{ $displayFile }}</span>
                            <div class="module-file-item-tooltip">{{ $displayFile }}</div>
                        </div>
                    @empty
                        <div class="module-list-item muted">当前模块目录下尚未发现文件。</div>
                    @endforelse
                </div>
            </div>

            @if (! empty($module['notes']))
                <div class="module-section">
                    <h3 class="module-section-title">补充说明</h3>
                    <div class="module-list">
                        @foreach ($module['notes'] as $note)
                            <div class="module-list-item">{{ $note }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (($module['missing_manifest'] ?? false) || ($module['invalid_manifest'] ?? false))
                <div class="module-section">
                    <h3 class="module-section-title">文件状态</h3>
                    <div class="module-list">
                        <div class="module-list-item">
                            @if ($module['invalid_manifest'] ?? false)
                                {{ $module['manifest_error'] ?? 'module.json 配置异常，请检查模块清单。' }}
                            @else
                                当前模块目录或 <code>module.json</code> 已缺失，平台暂时只能保留数据库记录供排查使用。
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </section>

        <aside class="module-side-card">
            <div class="module-meta">
                <div class="module-meta-row">
                    <div class="module-meta-label">模块状态</div>
                    <div class="module-meta-value"><span class="module-status {{ (($module['missing_manifest'] ?? false) || ($module['invalid_manifest'] ?? false)) ? 'is-missing' : ($module['status'] ? 'is-on' : '') }}">{{ ($module['invalid_manifest'] ?? false) ? '配置异常' : (($module['missing_manifest'] ?? false) ? '文件缺失' : ($module['status'] ? '已启用' : '已禁用')) }}</span></div>
                </div>
                <div class="module-meta-row">
                    <div class="module-meta-label">模块标识</div>
                    <div class="module-meta-value"><code>{{ $module['code'] }}</code></div>
                </div>
                <div class="module-meta-row">
                    <div class="module-meta-label">适用范围</div>
                    <div class="module-meta-value">{{ $module['scope'] === 'site' ? '站点模块' : '平台模块' }}</div>
                </div>
                <div class="module-meta-row">
                    <div class="module-meta-label">模块版本</div>
                    <div class="module-meta-value">v{{ $module['version'] }}</div>
                </div>
                <div class="module-meta-row">
                    <div class="module-meta-label">模块作者</div>
                    <div class="module-meta-value">{{ $module['author'] !== '' ? $module['author'] : '未填写' }}</div>
                </div>
                <div class="module-meta-row">
                    <div class="module-meta-label">模块目录</div>
                    <div class="module-meta-value">{{ $module['path'] }}</div>
                </div>
            </div>

            @if (! ($module['missing_manifest'] ?? false) && ! ($module['invalid_manifest'] ?? false))
                <div class="module-side-actions">
                    <form method="post" action="{{ route('admin.platform.modules.toggle', $module['code']) }}">
                        @csrf
                        <button class="button" type="submit">{{ $module['status'] ? '禁用模块' : '启用模块' }}</button>
                    </form>
                </div>
            @endif
        </aside>
    </div>
@endsection
