@extends('layouts.admin')

@section('title', '新增主题 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 主题市场 / 新增主题')

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

        .theme-shell { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; }
        .theme-card, .theme-side-card {
            padding: 20px 22px;
            border: 1px solid #eef2f6;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
            min-width: 0;
        }
        .theme-section + .theme-section { margin-top: 22px; }
        .theme-section-title { margin: 0; color: #1f2937; font-size: 16px; line-height: 1.5; font-weight: 700; }
        .theme-section-desc { margin-top: 8px; color: #8c8c8c; font-size: 13px; line-height: 1.7; }

        .theme-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .field-group {
            display: grid;
            gap: 8px;
        }

        .field-label {
            color: #262626;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.5;
        }

        .field-note {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.7;
        }

        .custom-select {
            position: relative;
        }

        .custom-select-native {
            position: absolute;
            inset: 0;
            opacity: 0;
            pointer-events: none;
        }

        .custom-select-trigger {
            width: 100%;
            height: 38px;
            padding: 0 36px 0 12px;
            border: 1px solid #e5e6eb;
            border-radius: 8px;
            background: #ffffff;
            color: #595959;
            font: inherit;
            font-size: 13px;
            font-weight: 400;
            line-height: 38px;
            text-align: left;
            cursor: pointer;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
            position: relative;
        }

        .custom-select-trigger:hover {
            border-color: #d9d9d9;
        }

        .custom-select.is-open .custom-select-trigger,
        .custom-select-trigger:focus-visible {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft-strong);
        }

        .custom-select.is-error .custom-select-trigger {
            border-color: #ff4d4f;
            box-shadow: 0 0 0 3px rgba(255, 77, 79, 0.08);
        }

        .custom-select-trigger::after {
            content: "";
            position: absolute;
            right: 12px;
            top: 50%;
            width: 12px;
            height: 12px;
            transform: translateY(-50%);
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12' fill='none'%3E%3Cpath d='M3 4.5L6 7.5L9 4.5' stroke='%2398A2B3' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center/12px 12px no-repeat;
            transition: transform 0.18s ease;
        }

        .custom-select.is-open .custom-select-trigger::after {
            transform: translateY(-50%) rotate(180deg);
        }

        .custom-select-panel {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            padding: 6px;
            border: 1px solid #f0f0f0;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
            opacity: 0;
            pointer-events: none;
            transform: translateY(-2px) scale(0.95);
            transform-origin: top center;
            transition: opacity 0.16s ease, transform 0.16s ease;
            z-index: 1500;
        }

        .custom-select.is-open .custom-select-panel {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0) scale(1);
        }

        .custom-select-option {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: #595959;
            font: inherit;
            font-size: 13px;
            font-weight: 400;
            line-height: 1.4;
            text-align: left;
            cursor: pointer;
        }

        .custom-select-option:hover,
        .custom-select-option.is-active {
            background: var(--primary-soft);
            color: var(--primary-dark);
        }

        .custom-select-check {
            width: 14px;
            height: 14px;
            stroke: var(--primary);
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            opacity: 0;
            flex-shrink: 0;
        }

        .custom-select-option.is-active .custom-select-check {
            opacity: 1;
        }

        .theme-meta { display: grid; gap: 12px; }
        .theme-meta-row { display: grid; gap: 4px; }
        .theme-meta-label { color: #98a2b3; font-size: 12px; line-height: 1.5; font-weight: 700; }
        .theme-meta-value { color: #1f2937; font-size: 14px; line-height: 1.7; word-break: break-word; }
        .theme-file-list { display: grid; gap: 8px; margin-top: 14px; min-width: 0; }
        .theme-file-empty {
            padding: 14px 16px;
            border-radius: 10px;
            border: 1px dashed #dbe5f1;
            background: #f8fafc;
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.7;
        }
        .theme-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .page-header {
                margin: -24px -18px 20px;
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
            }

            .theme-shell { grid-template-columns: 1fr; }
            .theme-form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">新增主题</h2>
            <div class="page-header-desc">创建新的平台主题记录，补充代码、版本和说明后，后续即可统一维护主题库并分配给站点。</div>
        </div>
        <div class="topbar-right">
            <a class="button secondary" href="{{ route('admin.platform.themes.index') }}">返回主题市场</a>
            <button class="button" type="submit" form="theme-create-form" data-loading-text="创建中...">创建主题</button>
        </div>
    </section>

    <div class="theme-shell">
        <section class="theme-card">
            <form id="theme-create-form" method="POST" action="{{ route('admin.platform.themes.store') }}">
                @csrf

                <div class="theme-section">
                    <h3 class="theme-section-title">基础信息</h3>
                    <div class="theme-section-desc">创建新的主题记录，补充名称、代码、版本与说明。</div>
                    <div class="stack" style="margin-top: 16px;">
                        <div class="theme-form-grid">
                            <label class="field-group">
                                <span class="field-label">主题名称</span>
                                <input class="field @error('name') is-error @enderror" id="name" type="text" name="name" value="{{ old('name') }}" @error('name') aria-invalid="true" @enderror>
                                @error('name')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </label>

                            <label class="field-group">
                                <span class="field-label">主题代码</span>
                                <input class="field @error('code') is-error @enderror" id="code" type="text" name="code" value="{{ old('code') }}" @error('code') aria-invalid="true" @enderror>
                                @error('code')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </label>
                        </div>

                        <div class="theme-form-grid">
                            <label class="field-group">
                                <span class="field-label">版本号</span>
                                <input class="field @error('version') is-error @enderror" id="version" type="text" name="version" value="{{ old('version', '1.0.0') }}" @error('version') aria-invalid="true" @enderror>
                                @error('version')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </label>

                            <label class="field-group">
                                <span class="field-label">使用规则</span>
                                <input class="field" type="text" value="创建后可绑定给站点" readonly>
                            </label>
                        </div>

                        <label class="field-group">
                            <span class="field-label">主题描述</span>
                            <textarea class="field textarea @error('description') is-error @enderror" id="description" name="description" rows="6" @error('description') aria-invalid="true" @enderror>{{ old('description') }}</textarea>
                            @error('description')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>
                </div>

                <div class="theme-section">
                    <h3 class="theme-section-title">文件说明</h3>
                    <div class="theme-file-list">
                        <div class="theme-file-empty">主题创建后，这里会显示对应主题目录下的模板文件说明。</div>
                    </div>
                </div>

                <div class="theme-form-actions">
                    <button class="button" type="submit" data-loading-text="创建中...">创建主题</button>
                </div>
            </form>
        </section>

        <aside class="theme-side-card">
            <div class="theme-meta">
                <div class="theme-meta-row">
                    <div class="theme-meta-label">主题状态</div>
                    <div class="theme-meta-value">创建后可继续维护并绑定站点</div>
                </div>
                <div class="theme-meta-row">
                    <div class="theme-meta-label">当前版本</div>
                    <div class="theme-meta-value">v{{ old('version', '1.0.0') }}</div>
                </div>
                <div class="theme-meta-row">
                    <div class="theme-meta-label">主题目录</div>
                    <div class="theme-meta-value">创建后生成至 <code>storage/app/theme_templates/&lt;主题代码&gt;</code></div>
                </div>
                <div class="theme-meta-row">
                    <div class="theme-meta-label">维护说明</div>
                    <div class="theme-meta-value">创建成功后，系统会自动生成当前版本记录，后续可继续进入详情页调整信息。</div>
                </div>
            </div>
        </aside>
    </div>
@endsection
