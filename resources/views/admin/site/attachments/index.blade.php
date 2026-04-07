@extends('layouts.admin')

@section('title', '资源库管理 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '资源库管理')

@php
    $attachmentSystemSettings = app(\App\Support\SystemSettings::class);
    $attachmentAutoCompressLabel = $attachmentSystemSettings->attachmentImageAutoCompressEnabled() ? '自动压缩已开启' : '自动压缩未开启';
    $attachmentRuleTooltipLines = [
        '支持 ' . strtoupper(implode(' / ', $attachmentSystemSettings->attachmentAllowedExtensions())),
        sprintf(
            '单文件不超过 %dMB；图片不超过 %dMB；最大 %d×%d 像素。',
            $attachmentSystemSettings->attachmentMaxSizeMb(),
            $attachmentSystemSettings->attachmentImageMaxSizeMb(),
            $attachmentSystemSettings->attachmentImageMaxWidth(),
            $attachmentSystemSettings->attachmentImageMaxHeight()
        ),
    ];
@endphp

@push('styles')
    @include('admin.site._custom_select_styles')
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

        .page-header-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }

        .page-header-actions .button {
            min-height: 44px;
            padding: 0 18px;
            border-radius: 16px;
        }

        .attachment-panel {
            background: transparent;
            border: 0;
            border-radius: 0;
            box-shadow: none;
        }

        .attachment-main {
            padding: 0;
        }

        .attachment-section-title {
            margin: 0;
            color: var(--color-text-main, #1f2937);
            font-size: 22px;
            line-height: 1.35;
            font-weight: 700;
        }

        .attachment-section-desc {
            margin-top: 8px;
            color: #8b94a7;
            font-size: 14px;
            line-height: 1.7;
        }

        .attachment-toolbar {
            display: grid;
            gap: 18px;
            margin-top: 0;
            padding: 20px 22px;
            border-radius: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
            border: 1px solid #eef1f5;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }

        .attachment-toolbar-grid {
            display: grid;
            grid-template-columns: minmax(260px, 1.3fr) repeat(3, minmax(160px, 0.9fr)) auto;
            gap: 16px 14px;
            align-items: end;
        }

        .attachment-toolbar-top {
            display: flex;
            justify-content: flex-end;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .attachment-filter-item {
            display: grid;
            gap: 8px;
            min-width: 0;
        }

        .attachment-filter-item label {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 600;
            white-space: nowrap;
            margin: 0;
        }

        .attachment-filter-item.is-search {
            grid-column: span 1;
        }

        .attachment-filter-item .field,
        .attachment-filter-item .site-select {
            width: 100%;
        }

        .attachment-toolbar #keyword.field {
            color: #595959;
            font-size: 14px;
            font-weight: 400;
            letter-spacing: 0;
        }

        .attachment-toolbar #keyword.field::placeholder {
            color: #8c8c8c;
            font-weight: 400;
        }

        .attachment-bulk-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: nowrap;
            justify-content: flex-end;
            min-width: max-content;
        }

        .attachment-bulk-row {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 22px;
            padding-left: 2px;
            position: relative;
            z-index: 20;
        }

        .attachment-unused-filter {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 36px;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid rgba(239, 68, 68, 0.16);
            background: rgba(254, 242, 242, 0.88);
            color: #dc2626;
            font-size: 13px;
            line-height: 1;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .attachment-unused-filter::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: currentColor;
            opacity: 0.88;
        }

        .attachment-unused-filter:hover {
            background: rgba(254, 226, 226, 0.92);
            border-color: rgba(239, 68, 68, 0.22);
            color: #b91c1c;
            transform: translateY(-1px);
        }

        .attachment-unused-filter.is-active {
            background: rgba(239, 68, 68, 0.12);
            border-color: rgba(239, 68, 68, 0.24);
            color: #b91c1c;
        }

        .attachment-filter-note {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 14px;
            padding: 10px 14px;
            border-radius: 16px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            font-size: 13px;
            line-height: 1.6;
            font-weight: 600;
        }

        .attachment-filter-note a {
            color: #c2410c;
            text-decoration: none;
            font-weight: 700;
        }

        .attachment-filter-note a:hover {
            color: #9a3412;
        }

        .attachment-toolbar label {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 600;
            letter-spacing: 0;
            white-space: nowrap;
            margin: 0;
        }

        .attachment-upload-desc,
        .attachment-tip {
            color: #8b94a7;
            font-size: 13px;
            line-height: 1.7;
        }

        .attachment-list-panel {
            margin-top: 18px;
            padding: 20px 22px;
            border-radius: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
            border: 1px solid #eef1f5;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }

        .attachment-summary-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .attachment-summary-status {
            color: #7c8aa0;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 600;
        }

        .attachment-rule-hint {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border: 0;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.05);
            color: #667085;
            cursor: help;
            transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease;
        }

        .attachment-rule-hint:hover,
        .attachment-rule-hint:focus-visible {
            background: rgba(15, 23, 42, 0.09);
            color: #344054;
            transform: translateY(-1px);
            outline: none;
        }

        .attachment-rule-hint svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.9;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .attachment-rule-tooltip {
            position: absolute;
            right: 0;
            bottom: calc(100% + 12px);
            transform: translateY(4px);
            min-width: 320px;
            max-width: 360px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(17, 24, 39, 0.96);
            color: #ffffff;
            font-size: 12px;
            line-height: 1.7;
            text-align: left;
            white-space: normal;
            box-shadow: 0 18px 32px rgba(15, 23, 42, 0.18);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.18s ease, transform 0.18s ease;
            z-index: 30;
        }

        .attachment-rule-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            right: 8px;
            border-width: 6px;
            border-style: solid;
            border-color: rgba(17, 24, 39, 0.96) transparent transparent transparent;
        }

        .attachment-rule-tooltip-line + .attachment-rule-tooltip-line {
            margin-top: 2px;
        }

        .attachment-rule-hint:hover .attachment-rule-tooltip,
        .attachment-rule-hint:focus-visible .attachment-rule-tooltip {
            opacity: 1;
            transform: translateY(0);
        }

        .attachment-library-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-top: 22px;
        }

        .attachment-card {
            position: relative;
            display: grid;
            gap: 14px;
            padding: 16px;
            border-radius: 24px;
            border: 1px solid #dbe7e0;
            background: #f7fbf8;
            transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
        }

        .attachment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 34px rgba(17, 31, 27, 0.08);
            border-color: #cfe1d8;
        }

        .attachment-card.is-used {
            background: #f8fbff;
            border-color: #dbe7f5;
        }

        .attachment-select {
            position: absolute;
            top: 14px;
            right: 14px;
        }

        .attachment-select .attachment-checkbox {
            width: 22px;
            height: 22px;
            margin: 0;
            border-radius: 7px;
            accent-color: var(--primary);
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .attachment-preview {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 156px;
            border-radius: 18px;
            background: linear-gradient(135deg, #eef6f2 0%, #e6f2ec 100%);
            overflow: hidden;
            color: #5f756d;
            font-weight: 700;
            font-size: 28px;
            letter-spacing: 0.04em;
        }

        .attachment-card.is-used .attachment-preview {
            background: linear-gradient(135deg, #eff5ff 0%, #e8f0ff 100%);
        }

        .attachment-preview img {
            width: 100%;
            height: 156px;
            object-fit: cover;
            display: block;
        }

        .attachment-name {
            color: var(--color-text-main, #1f2937);
            font-size: 15px;
            line-height: 1.6;
            font-weight: 500;
            word-break: break-all;
        }

        .attachment-meta {
            display: grid;
            gap: 8px;
        }

        .attachment-meta-line {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            color: #778196;
            font-size: 13px;
            line-height: 1.5;
        }

        .attachment-meta-line strong {
            color: #4b5565;
            font-weight: 600;
        }

        .attachment-dimension {
            color: #94a3b8;
            font-size: 11px;
            line-height: 1.4;
            font-weight: 500;
        }

        .attachment-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .attachment-actions-left,
        .attachment-actions-right {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .attachment-used-note {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 13px;
            line-height: 1;
            font-weight: 700;
            padding: 0 2px;
        }

        .attachment-used-note::before {
            content: '';
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #52c41a;
            border: 0;
            box-shadow: 0 0 0 0 rgba(82, 196, 26, 0.24);
            animation: attachment-used-pulse 1.8s ease-out infinite;
        }

        @keyframes attachment-used-pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(82, 196, 26, 0.24);
            }

            70% {
                box-shadow: 0 0 0 6px rgba(82, 196, 26, 0.1);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(82, 196, 26, 0);
            }
        }

        .attachment-card .button,
        .attachment-card .button.secondary,
        .attachment-sidebar .button {
            min-height: 38px;
            padding: 0 14px;
            font-size: 13px;
            border-radius: 14px;
        }

        .attachment-danger {
            color: #6b7280;
        }

        .attachment-danger:hover {
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.22);
            background: rgba(254, 242, 242, 0.92);
        }

        .attachment-usage-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: 8px;
            border: 0;
            background: transparent;
            color: #4b5563;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            padding: 0;
        }

        .attachment-usage-link:hover {
            color: #111827;
        }

        .attachment-usage-link svg {
            width: 13px;
            height: 13px;
            stroke: currentColor;
            stroke-width: 1.9;
            fill: none;
        }

        .attachment-usage-modal[hidden] { display: none; }

        .attachment-usage-modal {
            position: fixed;
            inset: 0;
            z-index: 2450;
        }

        .attachment-usage-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.48);
            backdrop-filter: blur(8px);
        }

        .attachment-usage-panel {
            position: relative;
            width: min(860px, calc(100vw - 40px));
            max-height: calc(100vh - 56px);
            margin: 28px auto;
            padding: 28px;
            border-radius: 28px;
            background: #fff;
            border: 1px solid #eef1f5;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16);
            overflow: auto;
        }

        .attachment-usage-header {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
        }

        .attachment-usage-title {
            margin: 0;
            color: #1f2937;
            font-size: 24px;
            line-height: 1.35;
            font-weight: 700;
        }

        .attachment-usage-desc {
            margin-top: 8px;
            color: #8b94a7;
            font-size: 14px;
            line-height: 1.7;
            word-break: break-all;
        }

        .attachment-usage-close {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            border: 1px solid #e7ebf1;
            background: #fff;
            color: #667085;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .attachment-usage-close:hover {
            background: #f8fafc;
            color: #344054;
        }

        .attachment-usage-close svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 1.9;
            fill: none;
        }

        .attachment-usage-list {
            display: grid;
            gap: 14px;
            margin-top: 22px;
        }

        .attachment-usage-item {
            padding: 18px 18px 16px;
            border-radius: 20px;
            border: 1px solid #eef1f5;
            background: linear-gradient(180deg, #ffffff 0%, #fafcff 100%);
        }

        .attachment-usage-item-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
        }

        .attachment-usage-item-title {
            margin: 0;
            color: #1f2937;
            font-size: 16px;
            line-height: 1.6;
            font-weight: 700;
        }

        .attachment-usage-item-updated {
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.5;
            white-space: nowrap;
        }

        .attachment-usage-badges {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .attachment-usage-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 28px;
            padding: 0 12px;
            border-radius: 999px;
            background: #f5f7fb;
            color: #4b5563;
            font-size: 12px;
            font-weight: 700;
        }

        .attachment-usage-badge.is-position {
            background: rgba(0, 71, 171, 0.08);
            color: var(--primary);
        }

        .attachment-usage-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 14px;
        }

        .attachment-usage-empty {
            margin-top: 22px;
            padding: 36px 20px;
            text-align: center;
            color: #8b94a7;
            border-radius: 20px;
            border: 1px dashed #d8e1eb;
            background: #fbfdff;
        }

        .attachment-usage-loading {
            margin-top: 22px;
            color: #8b94a7;
            font-size: 14px;
            line-height: 1.7;
        }

        .attachment-empty {
            margin-top: 22px;
            padding: 42px 20px;
            text-align: center;
            color: #8b94a7;
            border-radius: 24px;
            border: 1px dashed #d8e1eb;
            background: #fbfdff;
        }

        .attachment-pagination {
            padding: 18px 6px 4px;
            margin-top: 10px;
        }

        .attachment-pagination nav {
            width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            padding-top: 2px;
        }

        .attachment-pagination .pagination-shell {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: nowrap;
            min-width: max-content;
        }

        .attachment-pagination .pagination-pages {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .attachment-pagination .pagination-button,
        .attachment-pagination .pagination-page,
        .attachment-pagination .pagination-ellipsis {
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

        .attachment-pagination .pagination-page {
            width: 32px;
            padding: 0;
        }

        .attachment-pagination .pagination-button {
            border: 0;
            background: transparent;
            min-width: auto;
            padding: 0 4px;
            color: #4b5563;
        }

        .attachment-pagination .pagination-button:hover,
        .attachment-pagination .pagination-page:hover {
            transform: translateY(-1px);
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .attachment-pagination .pagination-button:hover {
            background: transparent;
            border-color: transparent;
            color: #262626;
        }

        .attachment-pagination .pagination-page.is-active,
        .attachment-pagination .pagination-page.is-active:visited {
            border-color: #374151 !important;
            background: #374151 !important;
            color: #ffffff !important;
            font-weight: 600;
            transform: none;
        }

        .attachment-pagination .pagination-button.is-disabled,
        .attachment-pagination .pagination-page.is-disabled,
        .attachment-pagination .pagination-ellipsis {
            color: #c0c4cc;
            cursor: not-allowed;
        }

        .attachment-pagination .pagination-button.is-disabled:hover,
        .attachment-pagination .pagination-page.is-disabled:hover {
            transform: none;
            background: #ffffff;
            border-color: #e5e7eb;
        }

        .attachment-pagination .pagination-button.is-disabled,
        .attachment-pagination .pagination-button.is-disabled:hover {
            background: transparent;
            border-color: transparent;
        }

        .attachment-pagination .pagination-icon {
            width: 14px;
            height: 14px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
        }

        @media (max-width: 920px) {
            .attachment-toolbar-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .attachment-bulk-actions {
                grid-column: 1 / -1;
                justify-content: flex-end;
            }

            .attachment-library-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 720px) {
            .page-header {
                margin: -24px -18px 20px;
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
            }

            .page-header-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .attachment-main { padding: 18px; }

            .attachment-toolbar {
                padding: 16px;
            }

            .attachment-toolbar-grid {
                grid-template-columns: 1fr;
            }

            .attachment-bulk-actions {
                justify-content: flex-start;
            }

            .attachment-library-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">资源库管理</h2>
            <div class="page-header-desc">统一管理站点文件，支持筛选、预览、批量处理与上传。</div>
        </div>
        <div class="page-header-actions">
            <form id="attachment-upload-form" method="POST" action="{{ route('admin.attachments.store') }}" enctype="multipart/form-data">
                @csrf
                <input id="attachment-upload-file" type="file" name="file" hidden required>
            </form>
            <button id="attachment-upload-trigger" class="button" type="button">上传新资源</button>
        </div>
    </section>

    <section class="attachment-panel attachment-main">
            <div class="attachment-toolbar">
                <form method="GET" action="{{ route('admin.attachments.index') }}" class="attachment-toolbar-grid">
                    <div class="attachment-filter-item is-search">
                        <label for="keyword">搜索</label>
                        <input class="field" id="keyword" type="text" name="keyword" value="{{ $keyword }}" placeholder="搜索文件名">
                    </div>
                    <div class="attachment-filter-item">
                        <label for="filter">类型</label>
                        <div class="site-select" data-site-select>
                            <select id="filter" name="filter" class="field site-select-native">
                                <option value="all" @selected($selectedFilter === 'all')>全部类型</option>
                                <option value="image" @selected($selectedFilter === 'image')>仅图片</option>
                                <option value="file" @selected($selectedFilter === 'file')>仅文件</option>
                            </select>
                            <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">
                                {{ $selectedFilter === 'image' ? '仅图片' : ($selectedFilter === 'file' ? '仅文件' : '全部类型') }}
                            </button>
                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                        </div>
                    </div>
                    <div class="attachment-filter-item">
                        <label for="usage">引用</label>
                        <div class="site-select" data-site-select>
                            <select id="usage" name="usage" class="field site-select-native">
                                <option value="all" @selected($selectedUsage === 'all')>全部引用状态</option>
                                <option value="used" @selected($selectedUsage === 'used')>仅已引用</option>
                                <option value="unused" @selected($selectedUsage === 'unused')>仅未引用</option>
                            </select>
                            <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">
                                {{ $selectedUsage === 'used' ? '仅已引用' : ($selectedUsage === 'unused' ? '仅未引用' : '全部引用状态') }}
                            </button>
                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                        </div>
                    </div>
                    <div class="attachment-filter-item">
                        <label for="sort">排序</label>
                        <div class="site-select" data-site-select>
                            <select id="sort" name="sort" class="field site-select-native">
                                <option value="latest" @selected($selectedSort === 'latest')>最新上传</option>
                                <option value="oldest" @selected($selectedSort === 'oldest')>最早上传</option>
                            </select>
                            <button class="site-select-trigger" type="button" data-select-trigger aria-haspopup="listbox" aria-expanded="false">
                                {{ $selectedSort === 'oldest' ? '最早上传' : '最新上传' }}
                            </button>
                            <div class="site-select-panel" data-select-panel role="listbox"></div>
                        </div>
                    </div>
                    <div class="attachment-bulk-actions">
                        <button class="button neutral-action" type="submit">筛选</button>
                        <a class="button secondary neutral-action" href="{{ route('admin.attachments.index') }}">重置</a>
                    </div>
                </form>
                @if ($unusedDays === 30)
                    <div class="attachment-filter-note">
                        当前仅显示上传满 30 天且未被引用的资源
                        <a href="{{ route('admin.attachments.index') }}">清除筛选</a>
                    </div>
                @endif
                @error('file')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="attachment-list-panel">
                <div class="panel-header" style="padding:0; border:0; margin:0 0 18px;">
                    <div></div>
                    <span class="badge attachment-summary-badge">
                        <span>
                            {{ $attachments->total() }} 个文件 · 已用 {{ $attachmentTotalSizeLabel }} / {{ $attachmentStorageLimitLabel }}
                            <span class="attachment-summary-status"> · {{ $attachmentAutoCompressLabel }}</span>
                        </span>
                        <button class="attachment-rule-hint" type="button" aria-label="查看上传限制说明">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M12 10v6"></path>
                                <path d="M12 7.5h.01"></path>
                            </svg>
                            <span class="attachment-rule-tooltip" role="tooltip">
                                @foreach ($attachmentRuleTooltipLines as $line)
                                    <span class="attachment-rule-tooltip-line">{{ $line }}</span>
                                @endforeach
                            </span>
                        </button>
                    </span>
                </div>

                @if ($attachments->isEmpty())
                    <div class="attachment-empty">当前站点还没有上传附件，可以直接使用上方按钮上传新的站点资源。</div>
                @else
                    <form id="attachment-bulk-form" method="POST" action="{{ route('admin.attachments.bulk') }}">
                        @csrf
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                    </form>
                    <div class="attachment-library-grid">
                    @foreach ($attachments as $attachment)
                        @php
                            $isImage = in_array(strtolower((string) $attachment->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                            $previewText = strtoupper((string) ($attachment->extension ?: 'FILE'));
                            $dimensionLabel = $isImage && $attachment->width && $attachment->height
                                ? sprintf('%d×%d', (int) $attachment->width, (int) $attachment->height)
                                : '';
                        @endphp
                        <article class="attachment-card {{ $attachment->usage_count > 0 ? 'is-used' : '' }}">
                            <label class="attachment-select">
                                <input class="attachment-checkbox" type="checkbox" name="ids[]" value="{{ $attachment->id }}" form="attachment-bulk-form">
                            </label>
                            <div class="attachment-preview">
                                @if ($isImage && $attachment->url)
                                    <img src="{{ $attachment->url }}" alt="{{ $attachment->origin_name }}">
                                @else
                                    {{ $previewText }}
                                @endif
                            </div>
                            <div class="attachment-name">{{ $attachment->origin_name }}</div>
                            <div class="attachment-meta">
                                <div class="attachment-meta-line">
                                    <span>
                                        {{ strtoupper($attachment->extension ?: '-') }}{{ $isImage ? ' · 图片资源' : ' · 附件文件' }}
                                        @if ($dimensionLabel !== '')
                                            <span class="attachment-dimension"> · {{ $dimensionLabel }}</span>
                                        @endif
                                    </span>
                                    <strong>{{ number_format($attachment->size / 1024, 1) }} KB</strong>
                                </div>
                                <div class="attachment-meta-line">
                                    <span>
                                        引用 {{ $attachment->usage_count }} 次
                                        @if ($attachment->usage_count > 0)
                                            <button class="attachment-usage-link"
                                                    type="button"
                                                    data-attachment-usage-trigger
                                                    data-attachment-id="{{ $attachment->id }}"
                                                    data-attachment-name="{{ $attachment->origin_name }}">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12Z"/>
                                                    <circle cx="12" cy="12" r="3"/>
                                                </svg>
                                                查看
                                            </button>
                                        @endif
                                    </span>
                                    <span>
                                        {{ \Illuminate\Support\Carbon::parse($attachment->created_at)->format('m-d H:i') }}
                                        · {{ $attachment->uploaded_by_name ?? '未记录' }}
                                    </span>
                                </div>
                            </div>
                            <div class="attachment-actions">
                                <div class="attachment-actions-left">
                                    @if ($attachment->url)
                                        <a class="button secondary" href="{{ $attachment->url }}" target="_blank">预览文件</a>
                                    @endif
                                </div>
                                <div class="attachment-actions-right">
                                    @if ($attachment->usage_count > 0)
                                        <span class="attachment-used-note" aria-label="该附件已被引用">已引用</span>
                                    @else
                                        <form id="attachment-delete-form-{{ $attachment->id }}" method="POST" action="{{ route('admin.attachments.destroy', $attachment->id) }}">
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                            <button class="button secondary attachment-danger"
                                                    type="button"
                                                    data-attachment-delete-trigger
                                                    data-form-id="attachment-delete-form-{{ $attachment->id }}"
                                                    data-attachment-name="{{ $attachment->origin_name }}">删除</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                    </div>
                    <div class="attachment-bulk-row">
                        <button id="attachment-select-all" class="button neutral-action" type="button">全选</button>
                        <button id="attachment-bulk-submit" class="button neutral-action" type="submit" form="attachment-bulk-form">批量删除</button>
                        <a class="attachment-unused-filter {{ $unusedDays === 30 ? 'is-active' : '' }}"
                           href="{{ route('admin.attachments.index', ['unused_days' => 30]) }}">
                            30天未引用资源
                        </a>
                    </div>
                    <div class="attachment-pagination">{{ $attachments->links() }}</div>
                @endif
            </div>
    </section>

    <div id="attachment-usage-modal" class="attachment-usage-modal" hidden>
        <div class="attachment-usage-backdrop" data-close-attachment-usage></div>
        <div class="attachment-usage-panel" role="dialog" aria-modal="true" aria-labelledby="attachment-usage-title">
            <div class="attachment-usage-header">
                <div>
                    <h3 class="attachment-usage-title" id="attachment-usage-title">引用详情</h3>
                    <div class="attachment-usage-desc" id="attachment-usage-desc">正在加载附件引用信息...</div>
                </div>
                <button class="attachment-usage-close" type="button" data-close-attachment-usage aria-label="关闭">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>
            </div>
            <div class="attachment-usage-loading" id="attachment-usage-loading">正在整理该附件的引用内容...</div>
            <div class="attachment-usage-list" id="attachment-usage-list" hidden></div>
            <div class="attachment-usage-empty" id="attachment-usage-empty" hidden>当前没有找到可见的引用内容。</div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('admin.site._custom_select_scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const trigger = document.getElementById('attachment-upload-trigger');
            const fileInput = document.getElementById('attachment-upload-file');
            const form = document.getElementById('attachment-upload-form');
            if (!trigger || !fileInput || !form) {
                return;
            }

            trigger.addEventListener('click', () => {
                fileInput.click();
            });

            fileInput.addEventListener('change', () => {
                const file = fileInput.files?.[0];

                if (!file) {
                    return;
                }

                form.submit();
            });

            const bulkSubmit = document.getElementById('attachment-bulk-submit');
            const bulkForm = document.getElementById('attachment-bulk-form');
            const selectAllButton = document.getElementById('attachment-select-all');

            selectAllButton?.addEventListener('click', () => {
                const checkboxes = Array.from(document.querySelectorAll('.attachment-checkbox'));

                if (!checkboxes.length) {
                    return;
                }

                const shouldSelectAll = checkboxes.some((checkbox) => !checkbox.checked);

                checkboxes.forEach((checkbox) => {
                    checkbox.checked = shouldSelectAll;
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });

            bulkSubmit?.addEventListener('click', (event) => {
                event.preventDefault();

                if (!bulkForm) {
                    return;
                }

                const checked = bulkForm.querySelectorAll('.attachment-checkbox:checked').length
                    || document.querySelectorAll('.attachment-checkbox:checked').length;

                if (!checked) {
                    showMessage('请先勾选需要批量处理的附件。');
                    return;
                }

                window.showConfirmDialog({
                    title: '确认批量删除附件？',
                    text: `将尝试处理 ${checked} 个附件，已被引用的文件会自动跳过。`,
                    confirmText: '批量删除',
                    onConfirm: () => bulkForm.submit(),
                });
            });

            document.querySelectorAll('[data-attachment-delete-trigger]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();

                    const formId = button.dataset.formId;
                    const attachmentName = button.dataset.attachmentName || '该附件';
                    const formElement = formId ? document.getElementById(formId) : null;

                    if (!formElement) {
                        return;
                    }

                    window.showConfirmDialog({
                        title: '确认删除附件？',
                        text: `删除后将无法恢复：${attachmentName}`,
                        confirmText: '删除附件',
                        onConfirm: () => formElement.submit(),
                    });
                });
            });

            const usageModal = document.getElementById('attachment-usage-modal');
            const usageDesc = document.getElementById('attachment-usage-desc');
            const usageList = document.getElementById('attachment-usage-list');
            const usageLoading = document.getElementById('attachment-usage-loading');
            const usageEmpty = document.getElementById('attachment-usage-empty');
            const usageEndpointTemplate = @json(route('admin.attachments.usages', ['attachment' => '__ATTACHMENT__']));

            const closeUsageModal = () => {
                if (!usageModal) {
                    return;
                }

                usageModal.hidden = true;
            };

            const renderUsageItems = (items) => {
                if (!usageList) {
                    return;
                }

                usageList.innerHTML = items.map((item) => `
                    <article class="attachment-usage-item">
                        <div class="attachment-usage-item-header">
                            <h4 class="attachment-usage-item-title">${item.title}</h4>
                            <div class="attachment-usage-item-updated">${item.updated_at}</div>
                        </div>
                        <div class="attachment-usage-badges">
                            <span class="attachment-usage-badge">${item.type_label}</span>
                            <span class="attachment-usage-badge">${item.channel_name}</span>
                            ${item.status_label ? `<span class="attachment-usage-badge">${item.status_label}</span>` : ''}
                            ${(item.relation_labels || []).map((label) => `
                                <span class="attachment-usage-badge is-position">${label}</span>
                            `).join('')}
                        </div>
                        ${(item.edit_url || item.view_url) ? `
                        <div class="attachment-usage-actions">
                            ${item.edit_url ? `<a class="button secondary neutral-action" href="${item.edit_url}">编辑内容</a>` : ''}
                            ${item.view_url ? `<a class="button secondary neutral-action" href="${item.view_url}" target="_blank" rel="noreferrer">查看前台</a>` : ''}
                        </div>
                        ` : ''}
                    </article>
                `).join('');
            };

            document.querySelectorAll('[data-attachment-usage-trigger]').forEach((button) => {
                button.addEventListener('click', async () => {
                    if (!usageModal || !usageDesc || !usageList || !usageLoading || !usageEmpty) {
                        return;
                    }

                    const attachmentId = button.dataset.attachmentId;
                    const attachmentName = button.dataset.attachmentName || '该附件';

                    usageModal.hidden = false;
                    usageDesc.textContent = `正在查看：${attachmentName}`;
                    usageLoading.hidden = false;
                    usageList.hidden = true;
                    usageEmpty.hidden = true;
                    usageList.innerHTML = '';

                    try {
                        const response = await fetch(usageEndpointTemplate.replace('__ATTACHMENT__', attachmentId), {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        const payload = await response.json();

                        if (!response.ok) {
                            throw new Error(payload.message || '加载引用详情失败。');
                        }

                        usageDesc.textContent = `附件：${payload.attachment.name}`;
                        usageLoading.hidden = true;

                        if (!payload.items || payload.items.length === 0) {
                            usageEmpty.hidden = false;
                            return;
                        }

                        renderUsageItems(payload.items);
                        usageList.hidden = false;
                    } catch (error) {
                        usageLoading.hidden = true;
                        usageEmpty.hidden = false;
                        usageEmpty.textContent = error?.message || '加载引用详情失败，请稍后重试。';
                    }
                });
            });

            usageModal?.querySelectorAll('[data-close-attachment-usage]').forEach((button) => {
                button.addEventListener('click', closeUsageModal);
            });
        });
    </script>
@endpush
