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
    <style>
        @include('admin.site.attachments._attachment_library_styles')

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

        .workspace-shell {
            display: grid;
            grid-template-columns: 360px minmax(0, 1fr);
            gap: 20px;
            align-items: start;
        }

        .editor-panel {
            border: 1px solid #eef2f6;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
            overflow: visible;
        }

        .editor-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .editor-panel-header-main {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .editor-panel-header-theme {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            min-width: 0;
            color: var(--text-soft);
            font-size: 13px;
            line-height: 1.5;
            font-weight: 700;
        }

        .editor-panel-header-theme .template-badge {
            max-width: 180px;
            justify-content: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .editor-panel-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .editor-panel-header-actions .editor-doc-button {
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background 0.18s ease, color 0.18s ease;
        }

        .editor-panel-header-actions .editor-doc-button:hover {
            transform: translateY(-1px);
            border-color: rgba(0, 71, 171, 0.14);
            background: #fbfdff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
        }

        .editor-panel-header-actions .button.neutral-action.editor-doc-button.is-active,
        .editor-panel-header-actions .button.neutral-action.editor-doc-button.is-active:visited {
            transform: translateY(-1px);
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
            box-shadow: 0 10px 24px rgba(0, 71, 171, 0.18);
        }

        .editor-panel-accent {
            width: 4px;
            height: 18px;
            border-radius: 999px;
            background: var(--primary);
            flex-shrink: 0;
        }

        .editor-panel-title {
            color: #1f2937;
            font-size: 16px;
            line-height: 1.5;
            font-weight: 700;
        }

        .editor-panel-body {
            padding: 20px;
        }

        .template-tree-panel-scroll {
            position: relative;
            height: var(--template-tree-max-height, calc(100vh - 164px));
        }

        .template-tree-panel-body {
            height: 100%;
            max-height: var(--template-tree-max-height, calc(100vh - 164px));
            overflow-y: auto;
            padding-right: 8px;
            scrollbar-width: auto;
            scrollbar-color: color-mix(in srgb, var(--primary, #0047AB) 58%, #ffffff) color-mix(in srgb, var(--primary, #0047AB) 10%, #ffffff);
        }

        .template-tree-panel-body::-webkit-scrollbar {
            width: 14px;
            height: 14px;
        }

        .template-tree-panel-body::-webkit-scrollbar-track {
            border-radius: 999px;
            background: color-mix(in srgb, var(--primary, #0047AB) 10%, #ffffff);
        }

        .template-tree-panel-body::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: color-mix(in srgb, var(--primary, #0047AB) 58%, #ffffff);
            border: 2px solid transparent;
            background-clip: padding-box;
        }

        .template-tree-panel-body::-webkit-scrollbar-thumb:hover {
            background: color-mix(in srgb, var(--primary, #0047AB) 78%, #ffffff);
            border: 2px solid transparent;
            background-clip: padding-box;
        }

        .template-tree-search {
            margin-bottom: 14px;
        }

        .template-tree-search .field {
            min-height: 40px;
        }

        .template-tree-groups {
            display: grid;
            gap: 14px;
        }

        .template-tree-group {
            display: grid;
            gap: 8px;
        }

        .template-tree-group-title {
            color: #667085;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .template-tree-list {
            display: grid;
            gap: 2px;
        }

        .template-tree-item {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 8px 10px 8px 14px;
            border: 0;
            border-radius: 10px;
            background: transparent;
            transition: background 0.18s ease, color 0.18s ease;
        }

        .template-tree-item:hover {
            background: #f8fafc;
        }

        .template-tree-item.is-active {
            background: color-mix(in srgb, var(--primary) 7%, #ffffff);
        }

        .template-tree-item::before {
            content: "";
            position: absolute;
            left: 0;
            top: 8px;
            bottom: 8px;
            width: 3px;
            border-radius: 999px;
            background: var(--primary);
            opacity: 0;
            transition: opacity 0.18s ease;
        }

        .template-tree-item.is-active::before {
            opacity: 1;
        }

        .template-tree-item-head {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .template-tree-item-title {
            color: #1f2937;
            font-size: 13px;
            line-height: 1.45;
            font-weight: 600;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .template-tree-item-subline {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .template-tree-item-file {
            color: #98a2b3;
            font-size: 11px;
            line-height: 1.4;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .template-badge {
            display: inline-flex;
            align-items: center;
            min-height: 22px;
            padding: 0 8px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 11px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .template-badge.is-override {
            background: #eef6ff;
            color: #1d4ed8;
        }

        .template-badge.is-custom {
            background: #eefaf1;
            color: #15803d;
        }

        .workspace-note {
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.7;
        }

        .workspace-form-grid {
            display: grid;
            gap: 16px;
        }

        .workspace-form-grid.is-balanced,
        .workspace-form-grid.is-compact {
            grid-template-columns: minmax(0, 420px) minmax(0, 420px);
            justify-content: start;
            column-gap: 48px;
            row-gap: 28px;
        }

        .workspace-form-grid + .workspace-form-grid {
            margin-top: 40px;
        }

        .workspace-field-fixed {
            width: 100%;
            max-width: 100%;
        }

        .workspace-action-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 48px;
            max-width: calc(980px + 56px);
        }

        .custom-select {
            position: relative;
            width: 100%;
            max-width: 100%;
        }

        .custom-select-native {
            position: absolute;
            inset: 0;
            opacity: 0;
            pointer-events: none;
        }

        .custom-select-trigger {
            width: 100%;
            min-height: 38px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 0 14px;
            border-radius: 8px;
            border: 1px solid #e5e6eb;
            background: #ffffff;
            color: #262626;
            font-size: 13px;
            line-height: 1.4;
            font-weight: 500;
            text-align: left;
            cursor: pointer;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .custom-select-trigger span,
        .custom-select-option span {
            min-width: 0;
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .custom-select-trigger:hover {
            background: #fafafa;
        }

        .custom-select.is-open .custom-select-trigger,
        .custom-select-trigger:focus-visible {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.08);
        }

        .custom-select-trigger::after {
            content: "";
            width: 8px;
            height: 8px;
            border-right: 1.5px solid #98a2b3;
            border-bottom: 1.5px solid #98a2b3;
            transform: rotate(45deg);
            margin-top: -3px;
            flex-shrink: 0;
            transition: transform 0.2s ease, margin-top 0.2s ease;
        }

        .custom-select.is-open .custom-select-trigger::after {
            transform: rotate(-135deg);
            margin-top: 3px;
        }

        .custom-select-panel {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 2400;
            display: grid;
            gap: 4px;
            padding: 6px;
            border-radius: 8px;
            border: 1px solid #f0f0f0;
            background: #ffffff;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-4px) scale(0.98);
            transform-origin: top center;
            transition: opacity 0.18s ease, transform 0.18s ease, visibility 0.18s ease;
            max-height: 280px;
            overflow-y: auto;
        }

        .custom-select.is-open .custom-select-panel {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .custom-select-option {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: #595959;
            font-size: 13px;
            line-height: 1.5;
            text-align: left;
            cursor: pointer;
            transition: background 0.18s ease, color 0.18s ease;
        }

        .custom-select-option:hover,
        .custom-select-option.is-active {
            background: #f6ffed;
            color: var(--primary);
        }

        .custom-select-check {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 1.8;
            fill: none;
            opacity: 0;
            flex-shrink: 0;
        }

        .custom-select-option.is-active .custom-select-check {
            opacity: 1;
        }

        .picker-trigger {
            width: 100%;
            min-height: 38px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 0 14px;
            border-radius: 8px;
            border: 1px solid #e5e6eb;
            background: #ffffff;
            color: #262626;
            font-size: 13px;
            line-height: 1.4;
            font-weight: 500;
            text-align: left;
            cursor: pointer;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            min-width: 0;
            overflow: hidden;
        }

        .picker-trigger:hover {
            background: #fafafa;
        }

        .picker-trigger:focus-visible {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.08);
        }

        .picker-trigger-label {
            min-width: 0;
            flex: 1;
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .picker-trigger::after {
            content: "";
            width: 8px;
            height: 8px;
            border-right: 1.5px solid #98a2b3;
            border-bottom: 1.5px solid #98a2b3;
            transform: rotate(45deg);
            margin-top: -3px;
            flex-shrink: 0;
        }

        .starter-picker-modal {
            position: fixed;
            inset: 0;
            z-index: 3600;
            display: grid;
            place-items: center;
            padding: 16px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.18s ease;
        }

        .starter-picker-modal.is-open {
            opacity: 1;
            pointer-events: auto;
        }

        .starter-picker-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.42);
            backdrop-filter: blur(4px);
        }

        .starter-picker-dialog {
            position: relative;
            width: min(760px, calc(100vw - 32px));
            max-height: min(720px, calc(100vh - 32px));
            display: grid;
            grid-template-rows: auto 1fr;
            border: 1px solid #e8edf4;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16);
            overflow: hidden;
            transform: translateY(8px) scale(0.985);
            transition: transform 0.18s ease;
        }

        .starter-picker-modal.is-open .starter-picker-dialog {
            transform: translateY(0) scale(1);
        }

        .starter-picker-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            border-bottom: 1px solid #eef2f6;
            background: linear-gradient(180deg, #ffffff 0%, #fafcff 100%);
        }

        .starter-picker-title {
            color: #1f2937;
            font-size: 17px;
            line-height: 1.5;
            font-weight: 700;
        }

        .starter-picker-desc {
            margin-top: 6px;
            color: #8b94a7;
            font-size: 13px;
            line-height: 1.7;
        }

        .starter-picker-close {
            width: 38px;
            height: 38px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #ffffff;
            color: #667085;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .starter-picker-close svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 1.9;
            fill: none;
        }

        .starter-picker-list {
            min-height: 0;
            overflow-y: auto;
            padding: 14px;
            display: grid;
            gap: 8px;
        }

        .starter-picker-group {
            display: grid;
            gap: 8px;
        }

        .starter-picker-group + .starter-picker-group {
            margin-top: 10px;
        }

        .starter-picker-group-title {
            color: #667085;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 2px 2px 0;
        }

        .starter-picker-group.is-recommended-group .starter-picker-group-title {
            color: var(--primary);
        }

        .starter-picker-option {
            width: 100%;
            display: block;
            padding: 14px 16px;
            border: 1px solid #edf2f7;
            border-radius: 14px;
            background: #ffffff;
            text-align: left;
            cursor: pointer;
            transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
        }

        .starter-picker-option:hover {
            border-color: rgba(0, 71, 171, 0.18);
            background: #fbfdff;
        }

        .starter-picker-option.is-recommended {
            border-color: rgba(0, 71, 171, 0.16);
            background:
                linear-gradient(180deg, rgba(0, 71, 171, 0.04) 0%, rgba(0, 71, 171, 0.015) 100%),
                #ffffff;
        }

        .starter-picker-option.is-active {
            border-color: rgba(22, 163, 74, 0.22);
            background: #f6ffed;
            box-shadow: 0 10px 24px rgba(22, 163, 74, 0.08);
        }

        .starter-picker-option-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .starter-picker-option-title {
            color: #1f2937;
            font-size: 14px;
            line-height: 1.6;
            font-weight: 700;
            word-break: break-word;
        }

        .starter-picker-option-badge {
            display: none;
            align-items: center;
            justify-content: center;
            min-height: 24px;
            padding: 0 10px;
            border-radius: 999px;
            background: rgba(0, 71, 171, 0.08);
            color: var(--primary);
            font-size: 11px;
            line-height: 1;
            font-weight: 700;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .starter-picker-option.is-recommended .starter-picker-option-badge {
            display: inline-flex;
        }

        .history-list {
            display: grid;
            gap: 14px;
            margin-top: 20px;
        }

        .history-card {
            padding: 16px 18px;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            background: #ffffff;
        }

        .history-card-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }

        .history-card-title-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .history-card-title {
            color: #1f2937;
            font-size: 15px;
            line-height: 1.5;
            font-weight: 700;
        }

        .history-card-meta {
            margin-top: 6px;
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.7;
        }

        .history-card-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .snapshot-intro-card {
            padding: 18px 20px;
            border: 1px solid #eef2f6;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        }

        .snapshot-intro-title {
            color: #1f2937;
            font-size: 18px;
            line-height: 1.5;
            font-weight: 700;
        }

        .snapshot-intro-desc {
            margin-top: 14px;
            color: #8b94a7;
            font-size: 13px;
            line-height: 1.7;
        }

        .workspace-empty {
            margin-top: 18px;
            padding: 40px 24px;
            border: 1px dashed #dbe4ee;
            border-radius: 18px;
            background:
                radial-gradient(circle at top center, rgba(0, 71, 171, 0.05), transparent 42%),
                linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            color: #8b94a7;
            font-size: 14px;
            line-height: 1.8;
            text-align: center;
        }

        .workspace-empty::before {
            content: '';
            display: block;
            width: 44px;
            height: 44px;
            margin: 0 auto 14px;
            border-radius: 14px;
            background:
                radial-gradient(circle at center, rgba(0, 71, 171, 0.12) 0, rgba(0, 71, 171, 0.08) 52%, rgba(0, 71, 171, 0.03) 100%);
        }

        .snapshot-favorite-button {
            width: 32px;
            height: 32px;
            border: 0;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.04);
            color: #98a2b3;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease;
        }

        .snapshot-favorite-button:hover {
            background: rgba(15, 23, 42, 0.08);
            color: #475467;
        }

        .snapshot-favorite-button.is-active {
            background: color-mix(in srgb, var(--primary) 10%, #ffffff);
            color: var(--primary);
        }

        .snapshot-favorite-button svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }

        .template-tree-link {
            display: block;
            text-decoration: none;
        }

        .summary-card {
            padding: 24px 26px;
            border: 1px solid #eef2f6;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        }

        .summary-card-title {
            color: #1f2937;
            font-size: 18px;
            line-height: 1.5;
            font-weight: 700;
        }

        .summary-card-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .summary-card-meta .template-badge {
            min-height: 30px;
        }

        .summary-card-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 22px;
        }

        .summary-side-card {
            padding: 18px 20px;
            border: 1px solid #eef2f6;
            border-radius: 16px;
            background: #ffffff;
        }

        .summary-side-label {
            color: #667085;
            font-size: 12px;
            line-height: 1.5;
            font-weight: 700;
        }

        .summary-side-value {
            margin-top: 10px;
            color: #1f2937;
            font-size: 15px;
            line-height: 1.6;
            font-weight: 700;
            word-break: break-word;
        }

        .summary-side-note {
            margin-top: 8px;
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.7;
        }

        .editor-modal {
            position: fixed;
            inset: 0;
            z-index: 3200;
            display: grid;
            place-items: center;
            padding: 12px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.18s ease;
        }

        .editor-modal.is-open {
            opacity: 1;
            pointer-events: auto;
        }

        .editor-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.42);
            backdrop-filter: blur(6px);
        }

        .editor-modal-dialog {
            position: relative;
            width: min(1560px, calc(100vw - 24px));
            height: calc(100vh - 24px);
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            border: 1px solid #e8edf4;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 30px 70px rgba(15, 23, 42, 0.18);
            overflow: hidden;
            transform: translateY(10px) scale(0.985);
            transition: transform 0.18s ease;
        }

        .editor-modal.is-open .editor-modal-dialog {
            transform: translateY(0) scale(1);
        }

        .attachment-library-modal {
            z-index: 3800;
        }

        .attachment-usage-modal {
            z-index: 3850;
        }

        .editor-modal-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            border-bottom: 1px solid #eef2f6;
            background: linear-gradient(180deg, #ffffff 0%, #fafcff 100%);
        }

        .editor-modal-title {
            color: #1f2937;
            font-size: 17px;
            line-height: 1.5;
            font-weight: 700;
        }

        .editor-modal-desc {
            margin-top: 6px;
            color: #8b94a7;
            font-size: 13px;
            line-height: 1.7;
        }

        .editor-modal-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .editor-modal-actions .button.is-library {
            border-color: color-mix(in srgb, var(--primary, #0047AB) 16%, #d8dee8);
            background: color-mix(in srgb, var(--primary, #0047AB) 10%, #ffffff);
            color: var(--primary, #0047AB);
            box-shadow: 0 8px 18px color-mix(in srgb, var(--primary, #0047AB) 10%, transparent);
        }

        .editor-modal-actions .button.is-library:hover {
            border-color: color-mix(in srgb, var(--primary, #0047AB) 22%, #cfd8e3);
            background: color-mix(in srgb, var(--primary, #0047AB) 14%, #ffffff);
            color: color-mix(in srgb, var(--primary, #0047AB) 88%, #0f172a);
        }

        .editor-modal-close {
            width: 38px;
            height: 38px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #ffffff;
            color: #667085;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.18s ease, color 0.18s ease, border-color 0.18s ease;
        }

        .editor-modal-close:hover {
            background: #f8fafc;
            color: #344054;
            border-color: #d8dee8;
        }

        .editor-modal-close svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 1.9;
            fill: none;
        }

        .editor-modal-body {
            height: 100%;
            min-height: 0;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            gap: 18px;
            padding: 18px 20px 20px;
            background: #ffffff;
            overflow: hidden;
        }

        .editor-modal-form {
            min-height: 0;
            height: 100%;
            display: grid;
        }

        .editor-modal-fields {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .field-group {
            display: grid;
            gap: 6px;
        }

        .editor-source-group {
            height: 100%;
            min-height: 0;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto;
        }

        .field-label {
            color: #667085;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 700;
        }

        .template-title-group,
        .template-title-field {
            width: min(100%, 420px);
        }

        .field-note {
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.7;
        }

        .code-area,
        textarea.code-area {
            min-height: 350px;
            height: 100%;
            width: 100%;
            display: block;
            box-sizing: border-box;
            padding: 12px 16px;
            margin: 0;
            font-family: "SFMono-Regular", "JetBrains Mono", "Menlo", monospace;
            font-size: 13px;
            line-height: 24px;
            white-space: pre !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
            tab-size: 4;
            resize: none;
            border: 0;
            box-shadow: none;
            background: transparent;
            position: relative;
            z-index: 2;
            overflow: auto;
            background: #ffffff;
            scrollbar-gutter: stable both-edges;
        }

        .code-editor-shell {
            display: grid;
            grid-template-columns: 56px minmax(0, 1fr);
            min-height: 0;
            height: 100%;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
        }

        .code-editor-gutter {
            height: 100%;
            box-sizing: border-box;
            margin: 0;
            overflow: hidden;
            border-right: 1px solid #eef2f6;
            background: #f9fafb;
            user-select: none;
            pointer-events: none;
        }

        .code-editor-gutter-inner {
            box-sizing: border-box;
            margin: 0;
            padding: 12px 10px;
            color: #9ca3af;
            font-family: "SFMono-Regular", "JetBrains Mono", "Menlo", monospace;
            font-size: 13px;
            line-height: 24px;
            text-align: right;
            white-space: nowrap;
            transform: translateY(0);
            will-change: transform;
            backface-visibility: hidden;
        }

        .code-editor-gutter-line {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 24px;
            line-height: 24px;
            padding: 0;
            border-radius: 8px;
            transition: background 0.12s ease, color 0.12s ease;
        }

        .code-editor-gutter-line.is-active {
            background: color-mix(in srgb, var(--primary, #0047AB) 14%, #ffffff);
            color: var(--primary, #0047AB);
            font-weight: 700;
        }

        .code-editor-main {
            height: 100%;
            min-height: 0;
            overflow: hidden;
            position: relative;
            background: #ffffff;
        }

        .code-editor-main .code-area {
            min-height: 100%;
            height: 100%;
            padding: 12px 16px;
        }

        .editor-modal-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 14px;
        }

        .template-source-badge {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 0 12px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 12px;
            font-weight: 700;
        }

        .template-source-badge.is-override {
            background: #eef6ff;
            color: #1d4ed8;
        }

        .template-source-badge.is-custom {
            background: #eefaf1;
            color: #15803d;
        }

        .summary-detail-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-top: 28px;
        }

        .history-compare-modal {
            position: fixed;
            inset: 0;
            z-index: 3600;
            display: grid;
            place-items: center;
            padding: 12px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.18s ease;
        }

        .history-compare-modal.is-ready {
            opacity: 1;
            pointer-events: auto;
        }

        .history-compare-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.48);
            backdrop-filter: blur(6px);
        }

        .history-compare-dialog {
            position: relative;
            width: min(1680px, calc(100vw - 24px));
            height: calc(100vh - 18px);
            display: grid;
            grid-template-rows: auto 1fr;
            border: 1px solid #e7ebf2;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 30px 70px rgba(15, 23, 42, 0.18);
            overflow: hidden;
            transform: translateY(10px) scale(0.985);
            transition: transform 0.18s ease;
        }

        .history-compare-modal.is-ready .history-compare-dialog {
            transform: translateY(0) scale(1);
        }

        .history-compare-dialog-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            border-bottom: 1px solid #eef2f6;
            background: linear-gradient(180deg, #ffffff 0%, #fafcff 100%);
        }

        .history-compare-dialog-title {
            color: #1f2937;
            font-size: 17px;
            line-height: 1.5;
            font-weight: 700;
        }

        .history-compare-dialog-desc {
            margin-top: 6px;
            color: #8b94a7;
            font-size: 13px;
            line-height: 1.7;
        }

        .history-compare-dialog-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .history-compare-close {
            width: 38px;
            height: 38px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #ffffff;
            color: #667085;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .history-compare-close svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 1.9;
            fill: none;
        }

        .history-compare-workspace {
            min-height: 0;
            display: grid;
            grid-template-rows: auto 1fr;
            background: #ffffff;
        }

        .history-compare-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .history-compare-panel-head {
            padding: 16px 18px;
            border-bottom: 1px solid #eef2f6;
            color: #1f2937;
            font-size: 16px;
            line-height: 1.5;
            font-weight: 700;
        }

        .history-compare-panel-head + .history-compare-panel-head {
            border-left: 1px solid #eef2f6;
        }

        .history-diff-scroll {
            min-height: 0;
            height: 100%;
            overflow: auto;
            background: #fbfdff;
        }

        .history-diff-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .history-diff-row {
            border-bottom: 1px solid #eef2f6;
        }

        .history-diff-row.is-changed {
            background: var(--primary-soft-strong);
        }

        .history-diff-line {
            width: 56px;
            padding: 10px 8px 10px 12px;
            color: #98a2b3;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            line-height: 1.7;
            text-align: right;
            vertical-align: top;
            user-select: none;
            border-right: 1px solid #eef2f6;
            background: rgba(248, 250, 252, 0.86);
        }

        .history-diff-row.is-changed .history-diff-line {
            color: var(--primary);
            background: color-mix(in srgb, var(--primary) 10%, #ffffff);
        }

        .history-diff-content {
            padding: 10px 14px;
            vertical-align: top;
        }

        .history-diff-code {
            margin: 0;
            min-height: 20px;
            color: #1f2937;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            line-height: 1.7;
            white-space: pre-wrap;
            word-break: break-word;
            display: block;
        }

        .history-diff-side {
            width: calc((100% - 112px) / 2);
        }

        .history-diff-row .history-diff-side:nth-child(3) {
            border-left: 1px solid #eef2f6;
        }

        .history-diff-row.is-changed .history-diff-side {
            background: color-mix(in srgb, var(--primary) 8%, #ffffff);
        }

        .history-diff-empty {
            color: #98a2b3;
            font-style: italic;
        }

        @media (max-width: 1160px) {
            .workspace-shell,
            .summary-detail-grid {
                grid-template-columns: 1fr;
            }

            .workspace-form-grid.is-balanced,
            .workspace-form-grid.is-compact {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .page-header,
            .editor-panel-header,
            .editor-modal-head,
            .editor-modal-fields {
                flex-direction: column;
                align-items: stretch;
            }

            .editor-panel-header-actions,
            .editor-modal-actions {
                justify-content: flex-start;
            }

            .history-card-head,
            .history-card-actions,
            .snapshot-toolbar,
            .history-compare-dialog-head {
                flex-direction: column;
                align-items: stretch;
            }

            .history-card-actions,
            .history-compare-dialog-actions {
                justify-content: flex-start;
            }

            .editor-modal {
                padding: 8px;
            }

            .editor-modal-dialog {
                width: calc(100vw - 16px);
                height: calc(100vh - 16px);
            }

            .code-editor-shell {
                grid-template-columns: 44px minmax(0, 1fr);
            }

            .history-compare-dialog {
                width: calc(100vw - 16px);
                height: calc(100vh - 16px);
            }

        }
    </style>
@endpush

@section('content')
    @php
        $sourceLabel = ($templateMeta['source'] ?? 'default') === 'override'
            ? '站点自定义模板'
            : (($templateMeta['source'] ?? 'default') === 'custom' ? '站点新增' : '平台默认');
        $editorModalOpen = $errors->has('template_title') || $errors->has('template_source');
        $createTemplateErrors = $errors->createTemplate;
        $oldTemplatePrefix = old('template_prefix', 'list');
        $oldTemplateTitle = old('template_title', '');
        $oldTemplateSuffix = old('template_suffix', '');
        $oldStarterTemplate = old('starter_template', 'blank');
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
            <a class="button secondary" href="{{ route('site.home', ['site' => $currentSite->site_key]) }}" target="_blank">预览前台</a>
        </div>
    </section>

    <div class="workspace-shell">
        <section class="editor-panel">
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
                            <section class="template-tree-group" data-template-group>
                                <div class="template-tree-group-title">{{ $group['title'] }}</div>
                                <div class="template-tree-list">
                                    @foreach ($group['items'] as $item)
                                        <a class="template-tree-link" href="{{ route('admin.themes.editor', ['template' => $item['file'], 'panel' => $workspacePanel === 'snapshots' ? 'snapshots' : null]) }}" data-template-tree-link data-search-text="{{ strtolower($item['label'].' '.$item['file']) }}">
                                            <article class="template-tree-item @if ($item['file'] === $template) is-active @endif">
                                                <div class="template-tree-item-head">
                                                    <div class="template-tree-item-title">{{ $item['label'] }}</div>
                                                </div>
                                                <div class="template-tree-item-subline">
                                                    <div class="template-tree-item-file">{{ $item['file'] }}.tpl</div>
                                                    <span class="template-badge{{ ($item['source'] ?? 'default') === 'override' ? ' is-override' : (($item['source'] ?? 'default') === 'custom' ? ' is-custom' : '') }}">
                                                        {{ ($item['source'] ?? 'default') === 'override' ? '站点自定义模板' : (($item['source'] ?? 'default') === 'custom' ? '站点新增' : '平台默认') }}
                                                    </span>
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
                    <a class="button neutral-action editor-doc-button @if($workspacePanel === 'editor') is-active @endif" href="{{ route('admin.themes.editor', ['template' => $template]) }}" @if($workspacePanel === 'editor') aria-current="page" @endif>模板编辑</a>
                    <a class="button neutral-action editor-doc-button @if($workspacePanel === 'create') is-active @endif" href="{{ route('admin.themes.editor.template-create-form', ['template' => $template]) }}" @if($workspacePanel === 'create') aria-current="page" @endif>创建模板</a>
                    <a class="button neutral-action editor-doc-button @if($workspacePanel === 'snapshots') is-active @endif" href="{{ route('admin.themes.snapshots', ['template' => $template]) }}" @if($workspacePanel === 'snapshots') aria-current="page" @endif>模板快照</a>
                    <a class="button neutral-action editor-doc-button" href="{{ $templateQuickGuideUrl }}" target="_blank">速查表</a>
                </div>
            </div>
            <div class="editor-panel-body">
                @if ($workspacePanel === 'create')
                    <form method="POST" action="{{ route('admin.themes.editor.template-create') }}" id="theme-template-create-form" novalidate>
                        @csrf
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
                                    </select>
                                    <button class="custom-select-trigger" type="button" data-select-trigger aria-expanded="false">
                                        <span data-select-label>{{ ['list' => '列表模板', 'detail' => '详情模板', 'page' => '单页模板'][$oldTemplatePrefix] ?? '列表模板' }}</span>
                                    </button>
                                    <div class="custom-select-panel">
                                        @foreach (['list' => '列表模板', 'detail' => '详情模板', 'page' => '单页模板'] as $prefixValue => $prefixLabel)
                                            <button class="custom-select-option @if($oldTemplatePrefix === $prefixValue) is-active @endif" type="button" data-select-option data-value="{{ $prefixValue }}">
                                                <span>{{ $prefixLabel }}</span>
                                                <svg class="custom-select-check" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5 6.5 11.5 12.5 4.5"/></svg>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </label>

                            <label class="field-group workspace-field-fixed">
                                <span class="field-label">模板框架</span>
                                <input type="hidden" name="starter_template" value="{{ $oldStarterTemplate }}" data-starter-template-input>
                                <button class="picker-trigger @if($createTemplateErrors->has('starter_template')) is-error @endif" type="button" data-open-starter-picker>
                                    <span class="picker-trigger-label" data-starter-picker-label>
                                        {{ collect($starterOptions)->firstWhere('value', $oldStarterTemplate)['label'] ?? (collect($starterOptions)->first()['label'] ?? '请选择') }}
                                    </span>
                                </button>
                            </label>
                        </div>

                        <div class="workspace-action-row">
                            <button class="button" type="submit" data-template-create-submit>创建模板</button>
                        </div>

                        <input type="hidden" name="current_template" value="{{ $currentTemplate }}">
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
                                                        'reset_template' => '恢复默认前快照',
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
                                                    'custom' => '站点新增',
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
                                            <a class="button secondary" href="{{ route('admin.themes.snapshots', ['template' => $template, 'version' => $historyItem->id]) }}" data-template-compare-link>查看对比</a>
                                            <form method="POST" action="{{ route('admin.themes.editor.template-rollback') }}">
                                                @csrf
                                                <input type="hidden" name="template" value="{{ $template }}">
                                                <input type="hidden" name="version_id" value="{{ $historyItem->id }}">
                                                <button class="button secondary" type="submit" data-template-rollback-button>回滚到此版</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.themes.editor.template-snapshot-delete') }}">
                                                @csrf
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
                            <span class="template-badge{{ ($templateMeta['source'] ?? 'default') === 'override' ? ' is-override' : (($templateMeta['source'] ?? 'default') === 'custom' ? ' is-custom' : '') }}">{{ $sourceLabel }}</span>
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
                                <div class="summary-side-value">{{ $template }}.tpl</div>
                                <div class="summary-side-note">当前编辑对象的实际模板文件名。</div>
                            </section>

                            <section class="summary-side-card">
                                <div class="summary-side-label">模板来源</div>
                                <div class="summary-side-value">{{ $sourceLabel }}</div>
                                <div class="summary-side-note">
                                    @if (($templateMeta['source'] ?? 'default') === 'default')
                                        保存后会在站点目录生成自定义版本。
                                    @elseif (($templateMeta['source'] ?? 'default') === 'override')
                                        当前模板基于平台默认模板进行了站点级自定义。
                                    @else
                                        这是站点新增模板，可直接删除。
                                    @endif
                                </div>
                            </section>

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

                @if (($templateMeta['source'] ?? 'default') === 'override')
                    <form id="theme-reset-form" method="POST" action="{{ route('admin.themes.editor.template-reset') }}">
                        @csrf
                        <input type="hidden" name="template" value="{{ $template }}">
                    </form>
                @elseif (($templateMeta['source'] ?? 'default') === 'custom')
                    <form id="theme-delete-form" method="POST" action="{{ route('admin.themes.editor.template-delete') }}">
                        @csrf
                        <input type="hidden" name="template" value="{{ $template }}">
                    </form>
                @endif

                @if ($latestTemplateVersion)
                    <form id="theme-rollback-form" method="POST" action="{{ route('admin.themes.editor.template-rollback') }}">
                        @csrf
                        <input type="hidden" name="template" value="{{ $template }}">
                    </form>
                @endif
            </div>
        </section>
    </div>

    @include('admin.site.attachments._attachment_library_modal')

    <section class="starter-picker-modal" data-starter-picker-modal>
        <div class="starter-picker-backdrop" data-close-starter-picker></div>
        <div class="starter-picker-dialog" role="dialog" aria-modal="true" aria-labelledby="starter-picker-title">
            <div class="starter-picker-head">
                <div>
                    <div class="starter-picker-title" id="starter-picker-title">选择模板框架</div>
                    <div class="starter-picker-desc">选择一个已有模板作为起始内容，或从空白模板骨架开始创建。</div>
                </div>
                <button class="starter-picker-close" type="button" data-close-starter-picker aria-label="关闭基础内容选择">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="starter-picker-list">
                @foreach ($starterOptionGroups as $starterGroup)
                    <section class="starter-picker-group" data-starter-picker-group data-group-key="{{ $starterGroup['key'] }}">
                        <div class="starter-picker-group-title">{{ $starterGroup['title'] }}</div>
                        @foreach ($starterGroup['items'] as $starterOption)
                            <button class="starter-picker-option @if($oldStarterTemplate === $starterOption['value']) is-active @endif" type="button" data-starter-picker-option data-value="{{ $starterOption['value'] }}" data-group-key="{{ $starterOption['group_key'] ?? $starterGroup['key'] }}">
                                <span class="starter-picker-option-head">
                                    <span class="starter-picker-option-title">{{ $starterOption['label'] }}</span>
                                    <span class="starter-picker-option-badge">推荐</span>
                                </span>
                            </button>
                        @endforeach
                    </section>
                @endforeach
            </div>
        </div>
    </section>

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
                        <div class="editor-modal-title" id="template-editor-modal-title">编辑模板源码</div>
                        <div class="editor-modal-desc">{{ $templateMeta['label'] ?? $template }}（{{ $template }}.tpl）</div>
                    </div>
                    <div class="editor-modal-actions">
                        <button class="button" type="submit" form="theme-editor-form" data-loading-text="保存中...">保存模板源码</button>
                        <button class="button secondary is-library" type="button" data-open-template-attachment-library>资源库</button>
                        @if ($latestTemplateVersion)
                            <button class="button secondary" type="submit" form="theme-rollback-form">回滚上一版</button>
                        @endif
                    @if (($templateMeta['source'] ?? 'default') === 'override')
                        <button class="button secondary" type="submit" form="theme-reset-form">恢复默认</button>
                    @endif
                    <a class="button neutral-action editor-doc-button" href="{{ route('admin.themes.snapshots', ['template' => $template]) }}">模板快照</a>
                    <button class="editor-modal-close" type="button" data-close-editor-modal aria-label="关闭源码编辑弹窗">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
            </div>

            <form id="theme-editor-form" class="editor-modal-form" method="POST" action="{{ route('admin.themes.editor.update') }}" novalidate>
                @csrf
                <input type="hidden" name="template" value="{{ $template }}">
                <div class="editor-modal-body">
                    <div class="editor-modal-fields">
                        <div class="field-group template-title-group">
                            <label class="field-label" for="template_title">模板标题</label>
                            <input class="field template-title-field @error('template_title') is-error @enderror" id="template_title" name="template_title" type="text" value="{{ old('template_title', $templateTitle) }}" placeholder="如 校园新闻模板" data-template-title-limit="10">
                            @error('template_title')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="field-group">
                            <span class="field-label">当前主题</span>
                            <div class="template-status-stack">
                                <span class="template-badge">{{ $themeCode }}</span>
                                <span class="template-badge{{ ($templateMeta['source'] ?? 'default') === 'override' ? ' is-override' : (($templateMeta['source'] ?? 'default') === 'custom' ? ' is-custom' : '') }}">{{ $sourceLabel }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="field-group editor-source-group">
                        <label class="field-label" for="template_source">TPL 模板源码</label>
                        <div class="code-editor-shell">
                            <div class="code-editor-gutter" aria-hidden="true">
                                <div class="code-editor-gutter-inner" id="template_source_gutter">
                                    <span class="code-editor-gutter-line">1</span>
                                </div>
                            </div>
                            <div class="code-editor-main">
                                <textarea class="code-area" id="template_source" name="template_source" spellcheck="false" wrap="off">{{ old('template_source', $templateSource) }}</textarea>
                            </div>
                        </div>
                        @error('template_source')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                        <div class="editor-modal-footer">
                            <span class="field-note">保存前会再次进行模板标题和模板语法校验。</span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        let cmsAttachments = [];
        const attachmentLibraryWorkspaceAccess = @json($attachmentLibraryWorkspaceAccess);
        const attachmentDeleteUrlTemplate = @json(route('admin.attachments.destroy', ['attachment' => '__ATTACHMENT__']));
        const attachmentUsageUrlTemplate = @json(route('admin.attachments.usages', ['attachment' => '__ATTACHMENT__']));

        @include('admin.site.attachments._attachment_library_script')

        (() => {
            const serverCreateErrors = @json(array_values(array_unique($createTemplateErrors->all())));
            const starterRecommendations = @json($starterRecommendations);
            const editor = document.getElementById('template_source');
            const gutter = document.getElementById('template_source_gutter');
            const resetForm = document.getElementById('theme-reset-form');
            const deleteForm = document.getElementById('theme-delete-form');
            const rollbackForm = document.getElementById('theme-rollback-form');
            const modal = document.querySelector('[data-editor-modal]');
            const openButtons = document.querySelectorAll('[data-open-editor-modal]');
            const closeButtons = document.querySelectorAll('[data-close-editor-modal]');
            const attachmentLibraryButtons = document.querySelectorAll('[data-open-template-attachment-library]');
            const editorForm = document.getElementById('theme-editor-form');
            const titleInput = document.getElementById('template_title');
            const initialTitle = titleInput ? titleInput.value : '';
            const initialSource = editor ? editor.value : '';
            const starterPickerModal = document.querySelector('[data-starter-picker-modal]');
            const starterPickerInput = document.querySelector('[data-starter-template-input]');
            const starterPickerLabel = document.querySelector('[data-starter-picker-label]');
            let editorViewportFrame = null;

            const validateTemplateTitleLimit = (input) => {
                if (!input) {
                    return true;
                }

                const limit = Number.parseInt(input.getAttribute('data-template-title-limit') || '0', 10);
                const value = (input.value || '').trim();
                const isValid = limit <= 0 || value.length <= limit;
                input.classList.toggle('is-error', !isValid);

                if (!isValid && typeof window.showMessage === 'function') {
                    window.showMessage(`模板标题不能超过 ${limit} 个字。`, 'error');
                }

                return isValid;
            };

            const normalizeTemplateSuffix = (value, { finalize = false } = {}) => {
                const source = String(value || '').toLowerCase();
                let normalized = '';

                for (const char of source) {
                    if (/[a-z0-9]/.test(char)) {
                        normalized += char;
                        continue;
                    }

                    if ((char === '-' || char === '_') && normalized !== '' && /[a-z0-9]$/.test(normalized)) {
                        normalized += char;
                    }
                }

                return finalize
                    ? normalized.replace(/[-_]+$/g, '')
                    : normalized;
            };

            const bindTemplateSuffixInput = (input) => {
                if (!input) {
                    return;
                }

                const sanitizeValue = ({ finalize = false } = {}) => {
                    const normalized = normalizeTemplateSuffix(input.value, { finalize });

                    if (input.value !== normalized) {
                        const nextCursor = Math.min(normalized.length, input.selectionStart ?? normalized.length);
                        input.value = normalized;
                        input.setSelectionRange(nextCursor, nextCursor);
                    }
                };

                input.addEventListener('input', () => sanitizeValue());
                input.addEventListener('blur', () => sanitizeValue({ finalize: true }));
                sanitizeValue({ finalize: true });
            };

            if (serverCreateErrors.length > 0 && typeof window.showMessage === 'function') {
                window.showMessage(serverCreateErrors.join('，'), 'error');
            }

            document.querySelectorAll('[data-custom-select]').forEach((selectRoot) => {
                const nativeSelect = selectRoot.querySelector('[data-select-native]');
                const trigger = selectRoot.querySelector('[data-select-trigger]');
                const label = selectRoot.querySelector('[data-select-label]');
                const options = selectRoot.querySelectorAll('[data-select-option]');

                if (!nativeSelect || !trigger || !label) {
                    return;
                }

                const close = () => {
                    selectRoot.classList.remove('is-open');
                    trigger.setAttribute('aria-expanded', 'false');
                };

                const open = () => {
                    document.querySelectorAll('[data-custom-select].is-open').forEach((opened) => {
                        if (opened !== selectRoot) {
                            opened.classList.remove('is-open');
                            opened.querySelector('[data-select-trigger]')?.setAttribute('aria-expanded', 'false');
                        }
                    });

                    selectRoot.classList.add('is-open');
                    trigger.setAttribute('aria-expanded', 'true');
                };

                trigger.addEventListener('click', () => {
                    if (selectRoot.classList.contains('is-open')) {
                        close();
                    } else {
                        open();
                    }
                });

                options.forEach((option) => {
                    option.addEventListener('click', () => {
                        const value = option.getAttribute('data-value') || '';
                        nativeSelect.value = value;
                        label.textContent = option.querySelector('span')?.textContent || value;
                        options.forEach((item) => item.classList.remove('is-active'));
                        option.classList.add('is-active');
                        close();
                        nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                });

                document.addEventListener('click', (event) => {
                    if (!selectRoot.contains(event.target)) {
                        close();
                    }
                });
            });

            const syncBodyScroll = () => {
                const compareModal = document.querySelector('[data-history-compare-modal]');
                const starterPickerOpen = starterPickerModal && starterPickerModal.classList.contains('is-open');
                const compareModalOpen = Boolean(compareModal && !compareModal.hidden && compareModal.classList.contains('is-ready'));
                document.body.style.overflow = (modal && modal.classList.contains('is-open')) || compareModalOpen || starterPickerOpen ? 'hidden' : '';
            };

            const renderLineNumbers = () => {
                if (!editor || !gutter) {
                    return;
                }

                const total = Math.max(editor.value.split('\n').length, 1);
                gutter.innerHTML = Array.from({ length: total }, (_, index) => `<span class="code-editor-gutter-line">${index + 1}</span>`).join('');
                syncEditorViewportState();
            };

            const syncEditorGutterScroll = () => {
                if (!editor || !gutter) {
                    return;
                }
                gutter.style.transform = `translate3d(0, ${-editor.scrollTop}px, 0)`;
            };

            const syncEditorViewportState = () => {
                syncEditorGutterScroll();
                syncEditorSelectionHighlight();
            };

            const requestEditorViewportSync = () => {
                if (editorViewportFrame !== null) {
                    return;
                }

                editorViewportFrame = window.requestAnimationFrame(() => {
                    editorViewportFrame = null;
                    syncEditorViewportState();
                });
            };

            const getLineFromOffset = (offset) => {
                if (!editor || !gutter) {
                    return 1;
                }

                const total = Math.max(editor.value.split('\n').length, 1);
                const normalizedOffset = Math.max(0, Math.min(editor.value.length, offset ?? 0));
                return Math.max(1, Math.min(total, editor.value.slice(0, normalizedOffset).split('\n').length));
            };

            const syncEditorSelectionHighlight = () => {
                if (!editor || !gutter) {
                    return;
                }

                const startLine = getLineFromOffset(editor.selectionStart ?? 0);
                const endLine = getLineFromOffset(editor.selectionEnd ?? editor.selectionStart ?? 0);
                gutter.querySelectorAll('.code-editor-gutter-line').forEach((lineElement, index) => {
                    const lineNumber = index + 1;
                    lineElement.classList.toggle('is-active', lineNumber >= startLine && lineNumber <= endLine);
                });
            };

            const hasUnsavedChanges = () => {
                if (!editor || !titleInput) {
                    return false;
                }

                return editor.value !== initialSource || titleInput.value !== initialTitle;
            };

            const closeModal = () => {
                if (!modal) {
                    return;
                }

                if (hasUnsavedChanges()) {
                    if (typeof window.showConfirmDialog === 'function') {
                        window.showConfirmDialog({
                            title: '确认关闭源码编辑？',
                            text: '当前有未保存的修改，关闭后这些修改将不会保留。',
                            confirmText: '仍然关闭',
                            onConfirm: () => {
                                modal.classList.remove('is-open');
                                syncBodyScroll();
                            },
                        });
                        return;
                    }

                    if (!window.confirm('当前有未保存的修改，确认关闭源码编辑吗？')) {
                        return;
                    }
                }

                modal.classList.remove('is-open');
                syncBodyScroll();
            };

            if (editor && gutter) {
                editor.addEventListener('input', renderLineNumbers);
                editor.addEventListener('scroll', requestEditorViewportSync, { passive: true });
                editor.addEventListener('click', syncEditorSelectionHighlight);
                editor.addEventListener('keyup', syncEditorSelectionHighlight);
                editor.addEventListener('focus', syncEditorSelectionHighlight);
                editor.addEventListener('mouseup', syncEditorSelectionHighlight);
                editor.addEventListener('select', syncEditorSelectionHighlight);
                renderLineNumbers();
            }

            const insertAtCursor = (textarea, text) => {
                if (!textarea) {
                    return;
                }

                const start = textarea.selectionStart ?? textarea.value.length;
                const end = textarea.selectionEnd ?? textarea.value.length;
                const current = textarea.value || '';
                textarea.value = `${current.slice(0, start)}${text}${current.slice(end)}`;
                const nextPosition = start + text.length;
                textarea.selectionStart = nextPosition;
                textarea.selectionEnd = nextPosition;
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                textarea.focus();
            };

            openButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    modal?.classList.add('is-open');
                    syncBodyScroll();
                    window.setTimeout(() => {
                        renderLineNumbers();
                        syncEditorViewportState();
                        editor?.focus();
                    }, 30);
                });
            });

            attachmentLibraryButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    window.openSiteAttachmentLibrary?.({
                        mode: 'picker',
                        context: 'theme',
                        imageOnly: true,
                        onSelect: (attachment) => {
                            const relativeUrl = (attachment?.relativeUrl || '').trim();

                            if (relativeUrl === '') {
                                window.showMessage?.('未找到该资源的站点路径，暂时无法插入。', 'error');
                                return;
                            }

                            insertAtCursor(editor, relativeUrl);
                            window.showMessage?.('站点资源路径已插入模板源码。');
                        },
                    });
                });
            });

            closeButtons.forEach((button) => {
                button.addEventListener('click', closeModal);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && starterPickerModal?.classList.contains('is-open')) {
                    closeStarterPicker();
                    return;
                }

                if (event.key === 'Escape' && modal?.classList.contains('is-open')) {
                    closeModal();
                }
            });

            syncBodyScroll();

            const treeSearchInput = document.querySelector('[data-template-tree-search]');
            const treePanelShell = document.querySelector('[data-template-tree-scroll-shell]');
            const treePanelBody = document.querySelector('[data-template-tree-scroll-body]');
            const createForm = document.getElementById('theme-template-create-form');
            const createTemplateSuffixInput = createForm?.querySelector('[data-template-suffix]');
            const templatePrefixInput = createForm?.querySelector('[data-template-prefix-input]');
            const starterPickerOptions = Array.from(document.querySelectorAll('[data-starter-picker-option]'));
            const starterPickerGroups = Array.from(document.querySelectorAll('[data-starter-picker-group]'));
            let starterPickerTouched = Boolean(starterPickerInput && starterPickerInput.value && starterPickerInput.value !== 'blank');

            bindTemplateSuffixInput(createTemplateSuffixInput);

            function applyTemplateTreeFilter() {
                const keyword = (treeSearchInput?.value || '').trim().toLowerCase();

                document.querySelectorAll('[data-template-tree-link]').forEach((link) => {
                    const text = (link.getAttribute('data-search-text') || '').toLowerCase();
                    const isVisible = keyword === '' || text.includes(keyword);
                    link.hidden = !isVisible;
                    link.style.display = isVisible ? '' : 'none';
                });

                document.querySelectorAll('[data-template-group]').forEach((group) => {
                    const visibleItems = group.querySelectorAll('[data-template-tree-link]:not([hidden])').length;
                    group.hidden = visibleItems === 0;
                    group.style.display = visibleItems === 0 ? 'none' : '';
                });
            }

            if (treeSearchInput) {
                treeSearchInput.addEventListener('input', applyTemplateTreeFilter);
                treeSearchInput.addEventListener('change', applyTemplateTreeFilter);
                treeSearchInput.addEventListener('keyup', applyTemplateTreeFilter);
                applyTemplateTreeFilter();
            }

            const syncTemplateTreeHeight = () => {
                if (!treePanelBody) {
                    return;
                }

                const rect = treePanelShell?.getBoundingClientRect() || treePanelBody.getBoundingClientRect();
                const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
                const bottomGap = window.innerWidth <= 760 ? 24 : 28;
                const available = Math.max(280, Math.floor(viewportHeight - rect.top - bottomGap));
                treePanelShell?.style.setProperty('--template-tree-max-height', `${available}px`);
                treePanelBody.style.setProperty('--template-tree-max-height', `${available}px`);
            };

            const scheduleTemplateTreeSync = () => {
                window.requestAnimationFrame(() => {
                    syncTemplateTreeHeight();
                });
            };

            syncTemplateTreeHeight();
            scheduleTemplateTreeSync();
            window.setTimeout(scheduleTemplateTreeSync, 120);
            window.setTimeout(scheduleTemplateTreeSync, 360);
            window.addEventListener('load', scheduleTemplateTreeSync);
            window.addEventListener('resize', scheduleTemplateTreeSync);

            if (window.ResizeObserver) {
                const treeResizeObserver = new ResizeObserver(() => {
                    scheduleTemplateTreeSync();
                });
                treePanelShell && treeResizeObserver.observe(treePanelShell);
                treePanelBody && treeResizeObserver.observe(treePanelBody);
            }

            if (window.MutationObserver && treePanelBody) {
                const treeMutationObserver = new MutationObserver(() => {
                    scheduleTemplateTreeSync();
                });
                treeMutationObserver.observe(treePanelBody, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['hidden', 'style', 'class'],
                });
            }

            createForm?.addEventListener('submit', (event) => {
                const createTitleInput = createForm.querySelector('[name="template_title"]');
                if (createTemplateSuffixInput) {
                    createTemplateSuffixInput.value = normalizeTemplateSuffix(createTemplateSuffixInput.value, { finalize: true });
                }
                if (!validateTemplateTitleLimit(createTitleInput)) {
                    event.preventDefault();
                    createTitleInput?.focus();
                }
            });

            editorForm?.addEventListener('submit', (event) => {
                if (!validateTemplateTitleLimit(titleInput)) {
                    event.preventDefault();
                    titleInput?.focus();
                }
            });

            const starterRecommendationForPrefix = () => {
                const prefix = templatePrefixInput?.value || 'list';
                return starterRecommendations?.[prefix] || null;
            };

            const selectedStarterOption = () => {
                if (!starterPickerInput) {
                    return null;
                }

                return document.querySelector(`[data-starter-picker-option][data-value="${CSS.escape(starterPickerInput.value)}"]`);
            };

            const selectedStarterGroupKey = () => {
                return selectedStarterOption()?.getAttribute('data-group-key') || '';
            };

            const updateStarterPickerRecommendationState = () => {
                const recommendation = starterRecommendationForPrefix();
                const recommendedValue = recommendation?.recommended?.value || '';
                const recommendedGroupKey = recommendation?.group_key || '';

                starterPickerOptions.forEach((option) => {
                    option.classList.toggle('is-recommended', recommendedValue !== '' && option.getAttribute('data-value') === recommendedValue);
                });

                starterPickerGroups.forEach((group) => {
                    group.classList.toggle('is-recommended-group', recommendedGroupKey !== '' && group.getAttribute('data-group-key') === recommendedGroupKey);
                });
            };

            const syncStarterPickerState = (value, { source = 'manual' } = {}) => {
                if (!starterPickerInput || !starterPickerLabel) {
                    return;
                }

                starterPickerInput.value = value;
                const activeOption = document.querySelector(`[data-starter-picker-option][data-value="${CSS.escape(value)}"]`);
                document.querySelectorAll('[data-starter-picker-option]').forEach((option) => {
                    option.classList.toggle('is-active', option.getAttribute('data-value') === value);
                });
                starterPickerLabel.textContent = activeOption?.querySelector('.starter-picker-option-title')?.textContent || '请选择';
                if (source === 'manual') {
                    starterPickerTouched = true;
                }
                updateStarterPickerRecommendationState();
            };

            const syncStarterPickerRecommendation = ({ force = false } = {}) => {
                const recommendation = starterRecommendationForPrefix();
                const recommendedValue = recommendation?.recommended?.value || '';
                const selectedValue = starterPickerInput?.value || '';
                const selectedGroupKey = selectedStarterGroupKey();
                const recommendedGroupKey = recommendation?.group_key || '';
                const selectionMatchesGroup = recommendedGroupKey !== '' && selectedGroupKey === recommendedGroupKey;

                if (
                    recommendedValue !== ''
                    && (
                        force
                        || !starterPickerTouched
                        || selectedValue === ''
                        || selectedValue === 'blank'
                        || !selectionMatchesGroup
                    )
                ) {
                    syncStarterPickerState(recommendedValue, { source: 'auto' });
                    return;
                }

                updateStarterPickerRecommendationState();
            };

            const scrollStarterPickerToRecommendation = () => {
                const recommendation = starterRecommendationForPrefix();
                const recommendedValue = recommendation?.recommended?.value || '';
                const recommendedGroupKey = recommendation?.group_key || '';
                const recommendedOption = recommendedValue !== ''
                    ? starterPickerModal?.querySelector(`[data-starter-picker-option][data-value="${CSS.escape(recommendedValue)}"]`)
                    : null;
                const recommendedGroup = recommendedGroupKey !== ''
                    ? starterPickerModal?.querySelector(`[data-starter-picker-group][data-group-key="${CSS.escape(recommendedGroupKey)}"]`)
                    : null;
                const target = recommendedOption || recommendedGroup;

                if (!target) {
                    return;
                }

                window.requestAnimationFrame(() => {
                    target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                });
            };

            const openStarterPicker = () => {
                if (!starterPickerModal) {
                    return;
                }

                starterPickerModal.classList.add('is-open');
                syncBodyScroll();
                scrollStarterPickerToRecommendation();
            };

            const closeStarterPicker = () => {
                if (!starterPickerModal) {
                    return;
                }

                starterPickerModal.classList.remove('is-open');
                syncBodyScroll();
            };

            document.querySelector('[data-open-starter-picker]')?.addEventListener('click', openStarterPicker);
            starterPickerModal?.querySelectorAll('[data-close-starter-picker]').forEach((element) => {
                element.addEventListener('click', closeStarterPicker);
            });

            templatePrefixInput?.addEventListener('change', () => {
                syncStarterPickerRecommendation();
            });

            document.querySelectorAll('[data-starter-picker-option]').forEach((option) => {
                option.addEventListener('click', () => {
                    const value = option.getAttribute('data-value') || 'blank';
                    syncStarterPickerState(value, { source: 'manual' });
                    closeStarterPicker();
                });
            });

            syncStarterPickerRecommendation();

            const bindDangerForm = (form, options) => {
                if (!form) {
                    return;
                }

                form.addEventListener('submit', (event) => {
                    if (typeof window.showConfirmDialog === 'function') {
                        event.preventDefault();
                        window.showConfirmDialog({
                            title: options.title,
                            text: options.text,
                            confirmText: options.confirmText,
                            onConfirm: () => form.submit(),
                        });
                        return;
                    }

                    if (!window.confirm(options.text)) {
                        event.preventDefault();
                    }
                });
            };

            bindDangerForm(resetForm, {
                title: '确认恢复平台默认模板？',
                text: '恢复后，当前站点对这个模板的自定义修改会被移除。',
                confirmText: '恢复默认',
            });

            bindDangerForm(deleteForm, {
                title: '确认删除自定义模板？',
                text: '删除后，这个站点新增模板会立即从当前主题中移除。',
                confirmText: '删除模板',
            });

            bindDangerForm(rollbackForm, {
                title: '确认回滚到上一版？',
                text: '回滚后，当前模板会恢复到上一版快照内容。',
                confirmText: '回滚模板',
            });

            document.querySelectorAll('[data-template-snapshot-delete-button]').forEach((button) => {
                const form = button.closest('form');
                if (!form) {
                    return;
                }

                form.addEventListener('submit', (event) => {
                    if (typeof window.showConfirmDialog !== 'function') {
                        return;
                    }

                    event.preventDefault();
                    window.showConfirmDialog({
                        title: '确认删除这个模板快照？',
                        text: '删除后，这条历史快照将无法恢复，请确认是否继续。',
                        confirmText: '删除快照',
                        onConfirm: () => form.submit(),
                    });
                });
            });

            document.querySelectorAll('[data-template-snapshot-favorite-button]').forEach((button) => {
                button.addEventListener('click', () => {
                    button.classList.add('is-active');
                    button.style.transform = 'scale(1.08)';
                    window.setTimeout(() => {
                        button.style.transform = '';
                    }, 180);
                });
            });

            const firstDiffRow = document.querySelector('[data-first-diff]');
            if (firstDiffRow) {
                firstDiffRow.scrollIntoView({ block: 'center' });
            }

            const compareModal = document.querySelector('[data-history-compare-modal]');
            if (compareModal) {
                document.body.style.overflow = 'hidden';
                window.requestAnimationFrame(() => {
                    compareModal.classList.add('is-ready');
                });

                const closeCompareModal = () => {
                    compareModal.classList.remove('is-ready');
                    document.body.style.overflow = '';

                    const cleanUrl = @json(route('admin.themes.snapshots', ['template' => $template]));
                    window.history.replaceState({}, '', cleanUrl);

                    window.setTimeout(() => {
                        compareModal.hidden = true;
                    }, 180);
                };

                compareModal.querySelectorAll('[data-history-compare-close]').forEach((element) => {
                    element.addEventListener('click', closeCompareModal);
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeCompareModal();
                    }
                });
            }
        })();
    </script>
@endpush
