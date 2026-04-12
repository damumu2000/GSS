@extends('layouts.admin')

@section('title', $module['name'] . ' - 模块管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 模块管理 / ' . $module['name'])

@php
    $moduleFolder = basename((string) ($module['path'] ?? ''));
@endphp

@push('styles')
    <link rel="stylesheet" href="/css/platform-modules-show.css">
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
