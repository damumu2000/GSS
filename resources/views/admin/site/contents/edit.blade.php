@extends('layouts.admin')

@section('title', ($isCreate ? '新建' : '编辑') . $typeLabel . ' - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / ' . $typeLabel . '管理 / ' . ($isCreate ? '新建' . $typeLabel : '编辑' . $typeLabel))

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

        .content-editor-shell {
            display: grid;
            gap: 24px;
        }

        .content-editor-floating-actions {
            position: fixed;
            right: 28px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 80;
            display: none;
        }

        .content-editor-floating-actions.is-visible {
            display: block;
        }

        .content-editor-floating-actions .button,
        .content-editor-floating-actions .button.secondary {
            position: relative;
            width: 34px;
            height: 34px;
            min-height: 34px;
            padding: 0;
            border-radius: 999px;
            display: inline-flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 0;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #fff;
            border: 0;
            background: linear-gradient(135deg, var(--color-primary, #0047AB) 0%, color-mix(in srgb, var(--color-primary, #0047AB) 78%, #7fb1ff 22%) 100%);
            box-shadow: 0 12px 24px rgba(0, 71, 171, 0.16), 0 6px 16px rgba(15, 23, 42, 0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease, opacity 0.2s ease;
        }

        .content-editor-floating-actions .button:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 28px rgba(0, 71, 171, 0.2), 0 8px 18px rgba(15, 23, 42, 0.1);
            filter: saturate(1.04) brightness(1.02);
        }

        .content-editor-floating-actions .button:active {
            transform: translateY(0);
            box-shadow: 0 8px 18px rgba(0, 71, 171, 0.14), 0 5px 12px rgba(15, 23, 42, 0.08);
            filter: saturate(0.98) brightness(0.98);
        }

        .content-editor-floating-actions .button::before {
            content: '';
            width: 18px;
            height: 18px;
            flex: 0 0 18px;
            background: currentColor;
            opacity: 0.95;
            -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z'/%3E%3Cpath d='M17 21v-8H7v8'/%3E%3Cpath d='M7 3v5h8'/%3E%3C/svg%3E") center / contain no-repeat;
            mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z'/%3E%3Cpath d='M17 21v-8H7v8'/%3E%3Cpath d='M7 3v5h8'/%3E%3C/svg%3E") center / contain no-repeat;
            transform: none;
        }

        .content-editor-floating-actions .button span {
            display: none;
        }

        .content-editor-floating-actions .button::after {
            content: attr(data-tip);
            position: absolute;
            left: 50%;
            bottom: calc(100% + 10px);
            transform: translateX(-50%) translateY(6px);
            padding: 6px 10px;
            border-radius: 10px;
            background: rgba(17, 24, 39, 0.94);
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            line-height: 1;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            box-shadow: 0 12px 22px rgba(15, 23, 42, 0.16);
            transition: opacity 0.18s ease, transform 0.18s ease;
        }

        .content-editor-floating-actions .button:hover::after,
        .content-editor-floating-actions .button:focus-visible::after {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        .content-editor-main {
            display: grid;
            gap: 20px;
        }

        .content-editor-panel {
            border: 1px solid #eef1f4;
            border-radius: 24px;
            background: #fff;
            padding: 24px;
            box-shadow: 0 12px 36px rgba(15, 23, 42, 0.04);
        }

        .content-editor-panel.primary {
            padding: 28px;
        }

        .content-review-alert {
            display: grid;
            gap: 14px;
            padding: 16px 18px;
            border: 1px solid rgba(239, 68, 68, 0.16);
            border-radius: 18px;
            background: linear-gradient(180deg, #fffafa 0%, #fff5f5 100%);
            margin-bottom: 18px;
        }

        .content-review-alert-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #b42318;
            font-size: 15px;
            font-weight: 700;
        }

        .content-review-alert-icon {
            width: 20px;
            height: 20px;
            border-radius: 999px;
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .content-review-alert-icon svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            stroke-width: 1.8;
            fill: none;
        }

        .content-review-alert-grid {
            display: grid;
            gap: 14px;
        }

        .content-review-alert-item {
            display: grid;
            gap: 4px;
        }

        .content-review-alert-label {
            color: #b07b7b;
            font-size: 12px;
            font-weight: 600;
        }

        .content-review-alert-value {
            color: #5b5561;
            font-size: 13px;
            line-height: 1.7;
        }

        .content-review-alert-meta {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px 16px;
            padding-top: 2px;
        }

        .content-review-alert-meta-item {
            display: grid;
            gap: 2px;
            min-width: 0;
        }

        .content-review-alert-meta-item .content-review-alert-value {
            font-size: 12px;
            line-height: 1.5;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .content-review-note {
            display: grid;
            gap: 10px;
            padding: 14px 18px;
            border: 1px solid rgba(0, 71, 171, 0.1);
            border-radius: 18px;
            background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
            margin-bottom: 18px;
        }

        .content-review-note-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #355070;
            font-size: 14px;
            font-weight: 700;
        }

        .content-review-note-icon {
            width: 20px;
            height: 20px;
            border-radius: 999px;
            background: rgba(0, 71, 171, 0.08);
            color: var(--primary, #0047AB);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .content-review-note-icon svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            stroke-width: 1.8;
            fill: none;
        }

        .content-review-note-body {
            color: #667085;
            font-size: 13px;
            line-height: 1.8;
        }

        .content-review-history {
            display: grid;
            gap: 14px;
            margin-bottom: 18px;
        }

        .content-review-history-title {
            color: #1f2937;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.5;
        }

        .content-review-history-list {
            display: grid;
            gap: 12px;
        }

        .content-review-history-item {
            display: grid;
            gap: 8px;
            padding: 14px 16px;
            border-radius: 16px;
            background: #ffffff;
            border: 1px solid #edf2f7;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
        }

        .content-review-history-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .content-review-history-action {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 0 10px;
            border-radius: 999px;
            background: #eef2f7;
            color: #475467;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .content-review-history-action.is-submitted {
            background: rgba(245, 158, 11, 0.12);
            color: #b45309;
        }

        .content-review-history-action.is-approved {
            background: rgba(16, 185, 129, 0.12);
            color: #047857;
        }

        .content-review-history-action.is-rejected {
            background: rgba(239, 68, 68, 0.1);
            color: #c2410c;
        }

        .content-review-history-meta {
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.7;
        }

        .content-review-history-reason {
            color: #475467;
            font-size: 13px;
            line-height: 1.7;
        }

        @media (max-width: 960px) {
            .content-review-alert-meta {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .content-review-alert-meta {
                grid-template-columns: 1fr;
            }
        }

        .content-editor-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 18px;
        }

        .content-editor-title {
            margin: 0;
            color: #1f2937;
            font-size: 22px;
            font-weight: 700;
            line-height: 1.4;
        }

        .content-editor-subtitle {
            margin-top: 6px;
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.7;
        }

        .content-side-switcher {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .content-side-button {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 112px;
            min-height: 40px;
            padding: 0 14px;
            border: 0;
            border-radius: 999px;
            background: #f8fafc;
            box-shadow: inset 0 0 0 1px #e5e7eb;
            color: #595959;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.18s ease, box-shadow 0.18s ease, color 0.18s ease;
        }

        .content-side-button:hover {
            background: var(--primary-soft);
            box-shadow: inset 0 0 0 1px rgba(0, 71, 171, 0.12);
            color: #374151;
        }

        .content-side-button.is-active {
            background: var(--tag-bg);
            color: var(--tag-text);
            box-shadow: inset 0 0 0 1px var(--primary-border-soft);
        }

        .content-main-stack {
            display: grid;
            gap: 18px;
        }

        .content-main-stack.is-hidden {
            display: none;
        }

        .content-field-group {
            display: grid;
            gap: 14px;
        }

        .content-main-top {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 22px;
            align-items: start;
            width: 100%;
        }

        .content-cover-card {
            display: grid;
            gap: 10px;
            width: min(280px, 100%);
            padding: 14px;
            border: 1px solid #eef1f4;
            border-radius: 22px;
            background: #fbfcfe;
            cursor: pointer;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
            justify-self: end;
        }

        .content-cover-card:hover {
            border-color: rgba(0, 71, 171, 0.18);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            transform: translateY(-1px);
        }

        .content-cover-preview {
            position: relative;
            min-height: 188px;
            border-radius: 18px;
            overflow: hidden;
            background: linear-gradient(135deg, #eef5ff 0%, #dce9fb 100%);
            border: 1px solid #e4ebf5;
        }

        .content-cover-preview img {
            width: 100%;
            height: 188px;
            object-fit: cover;
            display: block;
        }

        .content-cover-actions {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease, background-color 0.2s ease;
        }

        .content-cover-card.has-cover:hover .content-cover-actions {
            background: rgba(15, 23, 42, 0.36);
            opacity: 1;
            pointer-events: auto;
        }

        .content-cover-remove {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            padding: 0 16px;
            border: 1px solid rgba(255, 255, 255, 0.42);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
        }

        .content-cover-remove:hover {
            background: rgba(255, 255, 255, 0.28);
            border-color: rgba(255, 255, 255, 0.56);
            transform: translateY(-1px);
        }

        .content-cover-remove svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        .content-cover-placeholder {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: flex-end;
            padding: 18px;
            color: #445066;
            font-size: 20px;
            font-weight: 700;
            line-height: 1.5;
            background: linear-gradient(180deg, rgba(255,255,255,0.2) 0%, rgba(230,239,252,0.76) 100%);
        }

        .content-cover-meta {
            display: grid;
        }

        .content-cover-tip {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.7;
        }

        .content-cover-input {
            display: none;
        }

        .content-meta-stack {
            display: grid;
            gap: 12px;
            align-content: start;
            width: 100%;
            min-width: 0;
        }

        .content-main-row {
            --content-main-control-width: 760px;
            --content-title-toolbar-width: 132px;
            --content-title-field-width: calc(var(--content-main-control-width) - var(--content-title-toolbar-width) - 12px);
            display: grid;
            grid-template-columns: 56px minmax(0, 1fr);
            gap: 14px;
            align-items: center;
        }

        .content-main-row.has-article-flags {
            --content-title-toolbar-width: 202px;
        }

        .content-main-label {
            color: #6b7280;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.6;
            padding-top: 0;
        }

        .content-main-field {
            max-width: var(--content-main-control-width);
        }

        .content-title-stack {
            display: grid;
            gap: 12px;
            max-width: var(--content-main-control-width);
        }

        .content-channel-select {
            position: relative;
            width: 100%;
            max-width: var(--content-title-field-width);
        }

        .content-channel-trigger {
            width: 100%;
            min-height: 42px;
            padding: 0 40px 0 14px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            color: #374151;
            font: inherit;
            font-size: 14px;
            font-weight: 500;
            line-height: 42px;
            text-align: left;
            cursor: pointer;
            position: relative;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }

        .content-channel-trigger::after {
            content: '';
            position: absolute;
            right: 14px;
            top: 50%;
            width: 12px;
            height: 12px;
            transform: translateY(-50%);
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12' fill='none'%3E%3Cpath d='M3 4.5L6 7.5L9 4.5' stroke='%2398A2B3' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center/12px 12px no-repeat;
            transition: transform 0.18s ease;
        }

        .content-channel-select.is-open .content-channel-trigger::after {
            transform: translateY(-50%) rotate(180deg);
        }

        .content-channel-trigger:hover {
            border-color: #d1d5db;
            background: #fcfcfd;
        }

        .content-channel-select.is-open .content-channel-trigger,
        .content-channel-trigger:focus-visible {
            outline: none;
            border-color: rgba(0, 71, 171, 0.26);
            box-shadow: 0 0 0 3px rgba(0, 71, 171, 0.10);
        }

        .content-channel-select.is-error .content-channel-trigger {
            border-color: #ff4d4f;
            box-shadow: 0 0 0 3px rgba(255, 77, 79, 0.12);
        }

        .content-channel-panel {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            padding: 8px;
            border: 1px solid #eef2f7;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.10);
            opacity: 0;
            pointer-events: none;
            transform: translateY(-4px);
            transition: opacity 0.18s ease, transform 0.18s ease;
            z-index: 120;
        }

        .content-channel-select.is-open .content-channel-panel {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }

        .content-channel-search {
            position: relative;
            margin-bottom: 8px;
        }

        .content-channel-search-input {
            width: 100%;
            min-height: 38px;
            padding: 8px 40px 8px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fff;
            color: #374151;
            font: inherit;
            font-size: 13px;
            line-height: 1.5;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .content-channel-search-input:focus {
            outline: none;
            border-color: rgba(0, 71, 171, 0.26);
            box-shadow: 0 0 0 3px rgba(0, 71, 171, 0.10);
        }

        .content-channel-search-clear {
            position: absolute;
            top: 50%;
            right: 10px;
            width: 22px;
            height: 22px;
            margin: 0;
            padding: 0;
            border: 0;
            border-radius: 999px;
            background: transparent;
            color: #98a2b3;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transform: translateY(-50%);
            transition: background 0.18s ease, color 0.18s ease, opacity 0.18s ease;
            opacity: 0;
            pointer-events: none;
        }

        .content-channel-search.has-value .content-channel-search-clear {
            opacity: 1;
            pointer-events: auto;
        }

        .content-channel-search-clear:hover {
            background: #f3f4f6;
            color: #4b5563;
        }

        .content-channel-search-clear svg {
            width: 12px;
            height: 12px;
            stroke: currentColor;
            stroke-width: 2.1;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .content-channel-list {
            display: grid;
            gap: 0;
            max-height: 320px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .content-channel-locked-note {
            max-width: var(--content-title-field-width);
            margin-top: 10px;
            padding: 12px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #fafbfc;
            color: #667085;
            font-size: 12px;
            line-height: 1.8;
        }

        .content-channel-locked-title {
            color: #475467;
            font-weight: 700;
        }

        .content-channel-locked-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .content-channel-locked-tag {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 0 10px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            color: #4b5563;
            font-size: 12px;
            font-weight: 600;
            line-height: 1;
        }

        .content-channel-option {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 42px;
            padding: 9px 12px;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.16s ease;
        }

        .content-channel-option.is-hidden {
            display: none;
        }

        .content-channel-option:hover {
            background: #f8fbff;
        }

        .content-channel-option.is-active {
            background: #f8fbff;
        }

        button.content-channel-option {
            width: 100%;
            border: 0;
            background: transparent;
            font: inherit;
            text-align: left;
            box-shadow: none;
            appearance: none;
            -webkit-appearance: none;
        }

        button.content-channel-option:focus-visible {
            outline: none;
        }

        .content-channel-guides {
            display: inline-flex;
            align-items: stretch;
            align-self: stretch;
            flex: 0 0 auto;
            margin-right: 2px;
        }

        .content-channel-guide,
        .content-channel-branch {
            position: relative;
            width: 24px;
            flex: 0 0 24px;
        }

        .content-channel-guide::before,
        .content-channel-branch::before {
            content: '';
            position: absolute;
            left: 11px;
            top: -9px;
            bottom: -9px;
            width: 1px;
            background: transparent;
        }

        .content-channel-guide.is-active::before {
            background: rgba(0, 71, 171, 0.08);
        }

        .content-channel-option[data-depth="1"] .content-channel-guide:first-child::before {
            background: transparent;
        }

        .content-channel-guides > .content-channel-guide:first-child::before {
            background: transparent !important;
        }

        .content-channel-branch::before {
            background: rgba(0, 71, 171, 0.08);
        }

        .content-channel-branch::after {
            content: '';
            position: absolute;
            left: 11px;
            top: 50%;
            width: 14px;
            height: 1px;
            background: rgba(0, 71, 171, 0.08);
            transform: translateY(-50%);
        }

        .content-channel-branch.is-last::before {
            bottom: 50%;
        }

        .content-channel-checkbox {
            width: 16px;
            height: 16px;
            margin: 0;
            flex: 0 0 auto;
        }

        .content-channel-checkbox-spacer {
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
        }

        .content-channel-option.is-disabled {
            cursor: default;
        }

        .content-channel-option.is-disabled .content-channel-checkbox {
            cursor: not-allowed;
        }

        .content-channel-option.is-disabled .content-channel-text {
            color: #94a3b8;
        }

        .content-channel-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            color: #94a3b8;
            flex: 0 0 auto;
        }

        .content-channel-icon.is-folder {
            color: var(--color-primary, #0047AB);
        }

        .content-channel-icon svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 1.85;
            fill: none;
        }

        .content-channel-text {
            min-width: 0;
            color: #334155;
            font-size: 14px;
            font-weight: 500;
            line-height: 1.5;
        }

        .content-channel-single-input {
            position: absolute;
            inset: 0;
            opacity: 0;
            pointer-events: none;
        }

        .content-title-input-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .content-title-toolbar {
            position: relative;
            display: inline-flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 8px;
            justify-content: start;
            flex-shrink: 0;
        }

        .content-title-meta {
            display: grid;
            gap: 12px;
        }

        .content-title-meta-field {
            display: grid;
            gap: 8px;
            max-width: 420px;
        }

        .content-title-meta-field.time-field {
            max-width: 220px;
        }

        .content-static-display {
            color: #6b7280;
            font-size: 15px;
            font-weight: 600;
            line-height: 1.7;
        }

        .content-datetime-field {
            position: relative;
        }

        .content-datetime-input {
            padding-right: 64px;
            cursor: pointer;
        }

        .content-datetime-input::-webkit-calendar-picker-indicator {
            opacity: 0;
            cursor: pointer;
        }

        .content-datetime-trigger {
            position: absolute;
            top: 50%;
            right: 10px;
            padding: 0;
            border: 0;
            background: transparent;
            color: #98a2b3;
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            line-height: 1;
            cursor: pointer;
            transform: translateY(-50%);
            transition: color 0.18s ease;
        }

        .content-datetime-trigger:hover,
        .content-datetime-field:hover .content-datetime-trigger {
            color: var(--primary, #0047AB);
        }

        .content-status-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .content-status-option {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 128px;
            min-height: 40px;
            padding: 10px 14px;
            border: 0;
            border-radius: 999px;
            background: #f9fafb;
            box-shadow: inset 0 0 0 1px #e5e7eb;
            color: #595959;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.18s ease, box-shadow 0.18s ease, color 0.18s ease;
        }

        .content-status-option:hover {
            background: var(--primary-soft);
            box-shadow: inset 0 0 0 1px rgba(0, 71, 171, 0.12);
            color: #374151;
        }

        .content-status-option::before {
            content: '';
            width: 16px;
            height: 16px;
            border-radius: 999px;
            background: #ffffff;
            box-shadow: inset 0 0 0 1px #d9d9d9;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .content-status-option.is-active {
            background: var(--tag-bg);
            color: var(--tag-text);
            box-shadow: inset 0 0 0 1px var(--primary-border-soft);
        }

        .content-status-option.is-active::before {
            background: var(--primary, #0047AB);
            box-shadow: inset 0 0 0 2px #fff;
        }

        .content-status-option.is-disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .content-status-option input {
            display: none;
        }

        .content-body-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }

        .content-body-header .content-main-label {
            padding-top: 0;
        }

        .content-body-preview-button {
            min-width: 96px;
            min-height: 36px;
            padding: 0 14px;
            border-radius: 999px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .content-style-button {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            color: #4b5563;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 0 10px;
        }

        .content-style-button::after,
        .content-color-button::after {
            content: attr(data-tip);
            position: absolute;
            left: 50%;
            bottom: calc(100% + 8px);
            transform: translateX(-50%) translateY(4px);
            opacity: 0;
            pointer-events: none;
            white-space: nowrap;
            padding: 6px 8px;
            border-radius: 8px;
            background: rgba(17, 24, 39, 0.92);
            color: #fff;
            font-size: 12px;
            font-weight: 500;
            line-height: 1;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18);
            transition: all 0.18s ease;
            z-index: 24;
        }

        .content-style-button:hover::after,
        .content-color-button:hover::after {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        .content-style-button:hover {
            border-color: rgba(0, 71, 171, 0.22);
            background: rgba(0, 71, 171, 0.06);
            color: var(--primary, #0047AB);
        }

        .content-style-button.is-active {
            border-color: var(--primary, #0047AB);
            background: var(--primary, #0047AB);
            color: #ffffff;
        }

        .content-style-button.italic {
            font-style: italic;
        }

        .content-color-button {
            width: 34px;
            height: 34px;
            padding: 0;
        }

        .content-color-control {
            position: relative;
            display: inline-flex;
            flex: 0 0 auto;
        }

        .content-color-button span {
            display: inline-flex;
            width: 14px;
            height: 14px;
            border-radius: 999px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: conic-gradient(
                from 180deg,
                #dc2626 0deg,
                #d97706 60deg,
                #059669 120deg,
                #2563eb 180deg,
                #7c3aed 240deg,
                #db2777 300deg,
                #dc2626 360deg
            );
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.55);
        }

        .content-color-button.has-selection {
            border-color: var(--swatch-color, var(--primary, #0047AB));
            background: var(--swatch-color, var(--primary, #0047AB));
            color: #ffffff;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18), 0 0 0 1px rgba(15, 23, 42, 0.04);
        }

        .content-color-button.has-selection span {
            display: inline-flex;
            width: 16px;
            height: 4px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.96);
            border: 0;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.12);
            transform: rotate(-28deg);
        }

        .content-color-picker {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            z-index: 20;
            display: none;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            width: 168px;
            padding: 12px;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            background: #fff;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.1);
        }

        .content-color-picker.is-open {
            display: grid;
        }

        .content-color-swatch {
            width: 28px;
            height: 28px;
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            position: relative;
            box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .content-color-swatch::after {
            content: attr(data-tip);
            position: absolute;
            left: 50%;
            bottom: calc(100% + 8px);
            transform: translateX(-50%) translateY(4px);
            opacity: 0;
            pointer-events: none;
            white-space: nowrap;
            padding: 6px 8px;
            border-radius: 8px;
            background: rgba(17, 24, 39, 0.92);
            color: #fff;
            font-size: 12px;
            font-weight: 500;
            line-height: 1;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18);
            transition: all 0.18s ease;
            z-index: 24;
        }

        .content-color-swatch:hover::after {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        .content-color-swatch:hover {
            transform: scale(1.06);
            box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.12), 0 6px 16px rgba(15, 23, 42, 0.1);
        }

        .content-color-swatch.is-active {
            box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.92), 0 0 0 1px rgba(15, 23, 42, 0.15);
        }

        .content-color-swatch.reset {
            background: #ffffff;
            box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.12);
        }

        .content-color-swatch.reset.is-active {
            box-shadow: inset 0 0 0 2px rgba(15, 23, 42, 0.14);
        }

        .content-color-swatch.reset i {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }

        .content-color-swatch.reset i::before,
        .content-color-swatch.reset i::after {
            content: '';
            position: absolute;
            left: 50%;
            top: 50%;
            width: 16px;
            height: 2px;
            border-radius: 999px;
            background: #9ca3af;
        }

        .content-color-swatch.reset i::before {
            transform: translate(-50%, -50%) rotate(45deg);
        }

        .content-color-swatch.reset i::after {
            transform: translate(-50%, -50%) rotate(-45deg);
        }

        .content-title-color-input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .content-title-input {
            height: 46px;
            font-size: 16px;
            font-weight: 400;
            border-radius: 16px;
            flex: 0 1 var(--content-title-field-width);
            width: min(100%, var(--content-title-field-width));
            max-width: var(--content-title-field-width);
            min-width: 0;
            transition: color 0.2s ease, font-weight 0.2s ease, font-style 0.2s ease;
        }

        .content-editor-body {
            margin-top: 16px;
        }

        .article-typesetting-toast {
            position: fixed;
            left: 50%;
            top: 43%;
            transform: translate(-50%, calc(-50% + 16px)) scale(0.98);
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 360px;
            max-width: min(520px, calc(100vw - 32px));
            padding: 18px 20px;
            border-radius: 24px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(246, 250, 255, 0.95));
            color: #0f172a;
            box-shadow:
                0 34px 84px rgba(15, 23, 42, 0.2),
                0 14px 36px rgba(59, 130, 246, 0.12),
                0 2px 6px rgba(15, 23, 42, 0.06);
            border: 1px solid rgba(147, 197, 253, 0.96);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.22s ease, transform 0.22s ease;
            z-index: 9999;
            backdrop-filter: blur(18px);
            overflow: hidden;
        }

        .article-typesetting-toast.is-visible {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }

        .article-typesetting-toast::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top left, rgba(59, 130, 246, 0.16), transparent 38%),
                radial-gradient(circle at bottom right, rgba(14, 165, 233, 0.1), transparent 34%);
            pointer-events: none;
        }

        .article-typesetting-toast::after {
            content: '';
            position: absolute;
            inset: 1px;
            border-radius: 23px;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.84),
                inset 0 0 0 1px rgba(255, 255, 255, 0.36);
            pointer-events: none;
        }

        .article-typesetting-toast__icon {
            position: relative;
            flex: 0 0 auto;
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(219, 234, 254, 0.98), rgba(191, 219, 254, 0.94));
            box-shadow:
                inset 0 0 0 1px rgba(96, 165, 250, 0.18),
                0 10px 18px rgba(59, 130, 246, 0.12);
            color: #0b4fb3;
        }

        .article-typesetting-toast__icon svg {
            width: 22px;
            height: 22px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .article-typesetting-toast__body {
            position: relative;
            min-width: 0;
        }

        .article-typesetting-toast__title {
            display: block;
            font-size: 18px;
            font-weight: 700;
            line-height: 1.35;
            letter-spacing: 0.01em;
            color: #0f172a;
        }

        .article-typesetting-toast__text {
            display: block;
            margin-top: 4px;
            color: #55657d;
            font-size: 14px;
            line-height: 1.6;
        }

        @media (max-width: 640px) {
            .article-typesetting-toast {
                left: 50%;
                top: 43%;
                min-width: 0;
                max-width: calc(100vw - 24px);
                padding: 16px;
                gap: 12px;
                border-radius: 20px;
            }

            .article-typesetting-toast__icon {
                width: 42px;
                height: 42px;
                border-radius: 12px;
            }

            .article-typesetting-toast__title {
                font-size: 15px;
            }
        }

        .content-editor-body .tox-tinymce {
            min-height: 620px !important;
            border-radius: 20px !important;
            border: 1px solid #e5e7eb !important;
            box-shadow: none !important;
        }

        .content-editor-body.is-error .tox-tinymce {
            border-color: #ff4d4f !important;
            box-shadow: 0 0 0 3px rgba(255, 77, 79, 0.12) !important;
        }

        .content-editor-body .tox-editor-header {
            border-bottom: 0 !important;
            box-shadow: none !important;
        }

        .content-editor-body .tox:not(.tox-tinymce-inline) .tox-editor-header,
        .content-editor-body .tox:not(.tox-tinymce-inline).tox-tinymce--toolbar-sticky-on .tox-editor-header {
            box-shadow: none !important;
            padding-bottom: 0 !important;
        }

        .content-editor-body .tox-toolbar-overlord {
            border-bottom: 0 !important;
            box-shadow: none !important;
        }

        .content-editor-body .tox .tox-toolbar__primary {
            flex-wrap: wrap !important;
            row-gap: 6px;
            border-bottom: 0 !important;
            background-image: none !important;
            background-repeat: no-repeat !important;
            background-size: 0 0 !important;
            background-position: center 39px !important;
        }

        .content-editor-body .tox .tox-toolbar,
        .content-editor-body .tox .tox-toolbar__overflow,
        .content-editor-body .tox .tox-toolbar__primary {
            background-color: #fff !important;
        }

        .content-editor-body .tox-editor-header .tox-toolbar-overlord > .tox-toolbar__primary:first-of-type {
            border-bottom: 0 !important;
            padding-bottom: 0 !important;
            margin-bottom: 0 !important;
        }

        .content-editor-body .tox .tox-toolbar__group {
            flex-wrap: wrap !important;
        }

        .content-editor-body .tox-menubar {
            align-items: center;
            gap: 6px;
        }

        .content-editor-body .tox-tbtn[title="插入哔哩哔哩视频"],
        .content-editor-body .tox-tbtn[aria-label="插入哔哩哔哩视频"],
        .content-editor-body .tox-tbtn[title="一键排版"],
        .content-editor-body .tox-tbtn[aria-label="一键排版"],
        .content-editor-body .tox-tbtn[title="插入表情"],
        .content-editor-body .tox-tbtn[aria-label="插入表情"],
        .content-editor-body .tox-tbtn[title="打开资源库"],
        .content-editor-body .tox-tbtn[aria-label="打开资源库"],
        .content-editor-body .tox-tbtn[title="全屏编辑"],
        .content-editor-body .tox-tbtn[aria-label="全屏编辑"] {
            padding: 0 10px !important;
            min-height: 34px !important;
            border-radius: 10px !important;
            background: transparent !important;
            color: #4b5563 !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            box-shadow: none !important;
            transition: color 0.2s ease, background-color 0.2s ease, transform 0.2s ease !important;
        }

        .content-editor-body .tox-tbtn[title="插入哔哩哔哩视频"] *,
        .content-editor-body .tox-tbtn[aria-label="插入哔哩哔哩视频"] *,
        .content-editor-body .tox-tbtn[title="一键排版"] *,
        .content-editor-body .tox-tbtn[aria-label="一键排版"] *,
        .content-editor-body .tox-tbtn[title="插入表情"] *,
        .content-editor-body .tox-tbtn[aria-label="插入表情"] *,
        .content-editor-body .tox-tbtn[title="打开资源库"] *,
        .content-editor-body .tox-tbtn[aria-label="打开资源库"] *,
        .content-editor-body .tox-tbtn[title="全屏编辑"] *,
        .content-editor-body .tox-tbtn[aria-label="全屏编辑"] * {
            cursor: pointer !important;
        }

        .content-editor-body .tox-tbtn[title="插入哔哩哔哩视频"],
        .content-editor-body .tox-tbtn[aria-label="插入哔哩哔哩视频"] {
            position: relative;
            margin-left: 18px !important;
        }

        .content-editor-body .tox-tbtn[title="引用"],
        .content-editor-body .tox-tbtn[aria-label="引用"] {
            position: relative;
            margin-left: 18px !important;
        }

        .content-editor-body .tox-tbtn[title="插入表情"],
        .content-editor-body .tox-tbtn[aria-label="插入表情"] {
            margin-left: 0 !important;
        }

        .content-editor-body .tox-tbtn[title="一键排版"],
        .content-editor-body .tox-tbtn[aria-label="一键排版"] {
            margin-left: 0 !important;
        }

        .content-editor-body .tox-tbtn[title="全屏编辑"],
        .content-editor-body .tox-tbtn[aria-label="全屏编辑"] {
            margin-left: 0 !important;
        }

        .content-editor-body .tox-tbtn[title="插入哔哩哔哩视频"]::after,
        .content-editor-body .tox-tbtn[aria-label="插入哔哩哔哩视频"]::after {
            content: '|';
            position: absolute;
            left: -12px;
            top: 50%;
            transform: translateY(-50%);
            color: #d1d5db;
            font-size: 16px;
            font-weight: 400;
            pointer-events: none;
        }

        .content-editor-body .tox-tbtn[title="引用"]::after,
        .content-editor-body .tox-tbtn[aria-label="引用"]::after {
            content: '|';
            position: absolute;
            left: -12px;
            top: 50%;
            transform: translateY(-50%);
            color: #d1d5db;
            font-size: 16px;
            font-weight: 400;
            pointer-events: none;
        }

        .content-editor-body .tox-tbtn[title="插入哔哩哔哩视频"]::before,
        .content-editor-body .tox-tbtn[aria-label="插入哔哩哔哩视频"]::before,
        .content-editor-body .tox-tbtn[title="一键排版"]::before,
        .content-editor-body .tox-tbtn[aria-label="一键排版"]::before,
        .content-editor-body .tox-tbtn[title="插入表情"]::before,
        .content-editor-body .tox-tbtn[aria-label="插入表情"]::before,
        .content-editor-body .tox-tbtn[title="打开资源库"]::before,
        .content-editor-body .tox-tbtn[aria-label="打开资源库"]::before,
        .content-editor-body .tox-tbtn[title="全屏编辑"]::before,
        .content-editor-body .tox-tbtn[aria-label="全屏编辑"]::before {
            content: '';
            display: inline-block;
            width: 14px;
            height: 14px;
            margin-right: 5px;
            background-color: currentColor;
            opacity: 0.9;
            vertical-align: -2px;
        }

        .content-editor-body .tox-tbtn[title="插入哔哩哔哩视频"]::before,
        .content-editor-body .tox-tbtn[aria-label="插入哔哩哔哩视频"]::before {
            -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m22 8-6 4 6 4V8Z'/%3E%3Crect x='2' y='6' width='14' height='12' rx='2' ry='2'/%3E%3C/svg%3E") center / contain no-repeat;
            mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m22 8-6 4 6 4V8Z'/%3E%3Crect x='2' y='6' width='14' height='12' rx='2' ry='2'/%3E%3C/svg%3E") center / contain no-repeat;
        }

        .content-editor-body .tox-tbtn[title="一键排版"]::before,
        .content-editor-body .tox-tbtn[aria-label="一键排版"]::before {
            -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m12 3 1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z'/%3E%3Cpath d='M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z'/%3E%3C/svg%3E") center / contain no-repeat;
            mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m12 3 1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z'/%3E%3Cpath d='M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z'/%3E%3C/svg%3E") center / contain no-repeat;
        }

        .content-editor-body .tox-tbtn[title="插入表情"]::before,
        .content-editor-body .tox-tbtn[aria-label="插入表情"]::before {
            -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cpath d='M8 15s1.5 2 4 2 4-2 4-2'/%3E%3Cline x1='9' x2='9.01' y1='9' y2='9'/%3E%3Cline x1='15' x2='15.01' y1='9' y2='9'/%3E%3C/svg%3E") center / contain no-repeat;
            mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cpath d='M8 15s1.5 2 4 2 4-2 4-2'/%3E%3Cline x1='9' x2='9.01' y1='9' y2='9'/%3E%3Cline x1='15' x2='15.01' y1='9' y2='9'/%3E%3C/svg%3E") center / contain no-repeat;
        }

        .content-editor-body .tox-tbtn[title="打开资源库"]::before,
        .content-editor-body .tox-tbtn[aria-label="打开资源库"]::before {
            -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 7.5A1.5 1.5 0 0 1 4.5 6h4l1.5 1.5H19.5A1.5 1.5 0 0 1 21 9v8.5A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5v-10Z'/%3E%3Cpath d='M3 10h18'/%3E%3C/svg%3E") center / contain no-repeat;
            mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 7.5A1.5 1.5 0 0 1 4.5 6h4l1.5 1.5H19.5A1.5 1.5 0 0 1 21 9v8.5A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5v-10Z'/%3E%3Cpath d='M3 10h18'/%3E%3C/svg%3E") center / contain no-repeat;
        }

        .content-editor-body .tox-tbtn[title="打开资源库"],
        .content-editor-body .tox-tbtn[aria-label="打开资源库"] {
            margin-left: 0 !important;
            color: #4b5563 !important;
            font-weight: 600 !important;
            background: transparent !important;
        }

        .content-editor-body .tox-tbtn[title="一键排版"],
        .content-editor-body .tox-tbtn[aria-label="一键排版"],
        .content-editor-body .tox-tbtn[title="打开资源库"],
        .content-editor-body .tox-tbtn[aria-label="打开资源库"] {
            padding-left: 8px !important;
            padding-right: 8px !important;
        }

        .content-editor-body .tox-tbtn[title="全屏编辑"]::before,
        .content-editor-body .tox-tbtn[aria-label="全屏编辑"]::before {
            -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M8 3H5a2 2 0 0 0-2 2v3'/%3E%3Cpath d='M16 3h3a2 2 0 0 1 2 2v3'/%3E%3Cpath d='M8 21H5a2 2 0 0 1-2-2v-3'/%3E%3Cpath d='M16 21h3a2 2 0 0 0 2-2v-3'/%3E%3C/svg%3E") center / contain no-repeat;
            mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M8 3H5a2 2 0 0 0-2 2v3'/%3E%3Cpath d='M16 3h3a2 2 0 0 1 2 2v3'/%3E%3Cpath d='M8 21H5a2 2 0 0 1-2-2v-3'/%3E%3Cpath d='M16 21h3a2 2 0 0 0 2-2v-3'/%3E%3C/svg%3E") center / contain no-repeat;
        }

        .content-editor-body .tox-tbtn[title="插入哔哩哔哩视频"]:hover,
        .content-editor-body .tox-tbtn[aria-label="插入哔哩哔哩视频"]:hover,
        .content-editor-body .tox-tbtn[title="一键排版"]:hover,
        .content-editor-body .tox-tbtn[aria-label="一键排版"]:hover,
        .content-editor-body .tox-tbtn[title="插入表情"]:hover,
        .content-editor-body .tox-tbtn[aria-label="插入表情"]:hover,
        .content-editor-body .tox-tbtn[title="打开资源库"]:hover,
        .content-editor-body .tox-tbtn[aria-label="打开资源库"]:hover,
        .content-editor-body .tox-tbtn[title="全屏编辑"]:hover,
        .content-editor-body .tox-tbtn[aria-label="全屏编辑"]:hover {
            background: rgba(240, 246, 255, 0.96) !important;
            color: var(--color-primary, #0047AB) !important;
            transform: translateY(-1px);
        }

        .content-editor-body .tox-tbtn[title="插入哔哩哔哩视频"]:active,
        .content-editor-body .tox-tbtn[aria-label="插入哔哩哔哩视频"]:active,
        .content-editor-body .tox-tbtn[title="一键排版"]:active,
        .content-editor-body .tox-tbtn[aria-label="一键排版"]:active,
        .content-editor-body .tox-tbtn[title="插入表情"]:active,
        .content-editor-body .tox-tbtn[aria-label="插入表情"]:active,
        .content-editor-body .tox-tbtn[title="打开资源库"]:active,
        .content-editor-body .tox-tbtn[aria-label="打开资源库"]:active,
        .content-editor-body .tox-tbtn[title="全屏编辑"]:active,
        .content-editor-body .tox-tbtn[aria-label="全屏编辑"]:active {
            transform: translateY(0);
        }

        .content-editor-body .tox-tbtn[title="插入哔哩哔哩视频"] .tox-tbtn__select-label,
        .content-editor-body .tox-tbtn[aria-label="插入哔哩哔哩视频"] .tox-tbtn__select-label,
        .content-editor-body .tox-tbtn[title="插入哔哩哔哩视频"] .tox-tbtn__status-label,
        .content-editor-body .tox-tbtn[aria-label="插入哔哩哔哩视频"] .tox-tbtn__status-label,
        .content-editor-body .tox-tbtn[title="一键排版"] .tox-tbtn__select-label,
        .content-editor-body .tox-tbtn[aria-label="一键排版"] .tox-tbtn__select-label,
        .content-editor-body .tox-tbtn[title="一键排版"] .tox-tbtn__status-label,
        .content-editor-body .tox-tbtn[aria-label="一键排版"] .tox-tbtn__status-label,
        .content-editor-body .tox-tbtn[title="清除格式"] .tox-tbtn__select-label,
        .content-editor-body .tox-tbtn[aria-label="清除格式"] .tox-tbtn__select-label,
        .content-editor-body .tox-tbtn[title="清除格式"] .tox-tbtn__status-label,
        .content-editor-body .tox-tbtn[aria-label="清除格式"] .tox-tbtn__status-label,
        .content-editor-body .tox-tbtn[title="插入表情"] .tox-tbtn__select-label,
        .content-editor-body .tox-tbtn[aria-label="插入表情"] .tox-tbtn__select-label,
        .content-editor-body .tox-tbtn[title="插入表情"] .tox-tbtn__status-label,
        .content-editor-body .tox-tbtn[aria-label="插入表情"] .tox-tbtn__status-label,
        .content-editor-body .tox-tbtn[title="打开资源库"] .tox-tbtn__select-label,
        .content-editor-body .tox-tbtn[aria-label="打开资源库"] .tox-tbtn__select-label,
        .content-editor-body .tox-tbtn[title="打开资源库"] .tox-tbtn__status-label,
        .content-editor-body .tox-tbtn[aria-label="打开资源库"] .tox-tbtn__status-label,
        .content-editor-body .tox-tbtn[title="全屏编辑"] .tox-tbtn__select-label,
        .content-editor-body .tox-tbtn[aria-label="全屏编辑"] .tox-tbtn__select-label,
        .content-editor-body .tox-tbtn[title="全屏编辑"] .tox-tbtn__status-label,
        .content-editor-body .tox-tbtn[aria-label="全屏编辑"] .tox-tbtn__status-label {
            font-weight: inherit;
            letter-spacing: 0.01em;
        }

        .content-editor-body .tox-tbtn[title="清除格式"] .tox-tbtn__select-label,
        .content-editor-body .tox-tbtn[aria-label="清除格式"] .tox-tbtn__select-label,
        .content-editor-body .tox-tbtn[title="清除格式"] .tox-tbtn__status-label,
        .content-editor-body .tox-tbtn[aria-label="清除格式"] .tox-tbtn__status-label {
            font-size: 15px !important;
            font-weight: 800 !important;
            letter-spacing: -0.03em;
        }

        .content-editor-body .tox .tox-tbtn[aria-label="撤销"],
        .content-editor-body .tox .tox-tbtn[aria-label="重做"],
        .content-editor-body .tox .tox-tbtn[aria-label="减少缩进"],
        .content-editor-body .tox .tox-tbtn[aria-label="增加缩进"],
        .content-editor-body .tox .tox-tbtn[aria-label="代码示例"],
        .content-editor-body .tox .tox-tbtn[aria-label="显示块"],
        .content-editor-body .tox .tox-tbtn[aria-label="文字颜色"],
        .content-editor-body .tox .tox-tbtn[aria-label="背景颜色"] {
            min-width: 56px !important;
            padding-inline: 10px !important;
        }

        .content-editor-body .tox .tox-tbtn--select[title*="字体大小"],
        .content-editor-body .tox .tox-tbtn--select[aria-label*="字体大小"],
        .content-editor-body .tox .tox-tbtn--select[title*="Font size"],
        .content-editor-body .tox .tox-tbtn--select[aria-label*="Font size"] {
            min-width: 72px !important;
            width: 72px !important;
            max-width: 72px !important;
            padding-inline: 6px !important;
        }

        .content-editor-body .tox .tox-tbtn--select[title*="字体大小"] .tox-tbtn__select-label,
        .content-editor-body .tox .tox-tbtn--select[aria-label*="字体大小"] .tox-tbtn__select-label,
        .content-editor-body .tox .tox-tbtn--select[title*="Font size"] .tox-tbtn__select-label,
        .content-editor-body .tox .tox-tbtn--select[aria-label*="Font size"] .tox-tbtn__select-label {
            min-width: 0 !important;
            max-width: 38px !important;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .content-editor-body .tox .tox-edit-area::before {
            border: 0 !important;
        }

        .content-editor-body .tox .tox-edit-area__iframe,
        .content-editor-body .tox .tox-edit-area {
            box-shadow: none !important;
        }

        .content-editor-body .tox .tox-editor-container,
        .content-editor-body .tox .tox-sidebar-wrap,
        .content-editor-body .tox .tox-edit-area {
            border-top: 0 !important;
            box-shadow: none !important;
        }

        .content-editor-panels {
            display: none;
            gap: 18px;
            margin-bottom: 18px;
        }

        .content-editor-panels.is-active {
            display: grid;
        }

        .content-editor-pane {
            display: none;
            border: 1px solid #eef1f4;
            border-radius: 20px;
            background: #fbfcfe;
            padding: 18px;
        }

        .content-editor-pane[data-editor-pane="reviews"] {
            background: transparent;
            border: 0;
            padding: 0;
        }

        .content-editor-pane.is-active {
            display: grid;
            gap: 16px;
        }

        .content-pane-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .content-pane-card {
            border: 1px solid #eef1f4;
            border-radius: 20px;
            background: #fbfcfe;
            padding: 18px;
        }

        .content-pane-card-title {
            margin: 0 0 10px;
            color: #374151;
            font-size: 14px;
            font-weight: 700;
        }

        .content-pane-card .helper-text {
            margin-top: 10px;
        }

        .content-pane-card.is-plain {
            border: 0;
            border-radius: 0;
            background: transparent;
            padding: 0;
        }


        .content-editor-attachment-shortcuts {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .content-editor-attachment-shortcuts .checkbox-card {
            display: grid;
            gap: 10px;
            border-radius: 18px;
            background: #fbfcfe;
        }

        @media (max-width: 720px) {
            .page-header {
                margin: -24px -18px 20px;
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
            }

            .content-editor-floating-actions {
                right: 16px;
                top: auto;
                bottom: 20px;
            }
        }

        @media (max-width: 960px) {
            .content-editor-heading {
                flex-direction: column;
                align-items: stretch;
            }

            .content-side-switcher {
                justify-content: flex-start;
            }

            .content-pane-grid,
            .content-editor-attachment-shortcuts {
                grid-template-columns: 1fr;
            }

            .content-main-top {
                grid-template-columns: 1fr;
            }

            .content-cover-card {
                width: 100%;
                justify-self: stretch;
            }

            .content-main-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .content-main-label {
                padding-top: 0;
            }

            .content-main-field {
                max-width: none;
            }

            .content-channel-select {
                max-width: none;
            }

            .content-title-input {
                width: 100%;
                max-width: none;
                flex: 1 1 auto;
            }

            .content-title-input-row {
                flex-wrap: wrap;
                align-items: stretch;
            }

            .content-title-toolbar {
                flex-wrap: wrap;
            }

            .content-body-header {
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .content-body-preview-button {
                min-width: 0;
            }

            .content-title-meta-field {
                max-width: none;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $initialEditorStatus = in_array(old('status', $content->status), ['published', 'pending', 'rejected'], true) ? 'published' : 'draft';
        $initialPublishActionLabel = ($type === 'article' && $articleRequiresReview)
            ? '提交审核'
            : ($isCreate ? '正式发布' : '保存修改');
        $initialSaveActionLabel = ($type === 'article')
            ? ($initialEditorStatus === 'draft' ? '保存草稿' : $initialPublishActionLabel)
            : ($isCreate ? '创建' . $typeLabel : '保存修改');
        $returnTo = (string) request()->query('return_to', old('return_to', ''));
        $backToListUrl = ($returnTo !== '' && str_starts_with($returnTo, url('/')))
            ? $returnTo
            : ($type === 'page' ? route('admin.pages.index') : route('admin.articles.index'));
        $previewUrl = ! $isCreate
            ? ($type === 'page'
                ? route('admin.content-preview.page', ['content' => $content->id])
                : route('admin.content-preview.article', ['content' => $content->id]))
            : '';
    @endphp

    <section class="page-header">
        <div>
            <h2 class="page-header-title">{{ $isCreate ? '新建' : '编辑' }}{{ $typeLabel }}</h2>
            <div class="page-header-desc">当前站点：{{ $currentSite->name }}。{{ $isCreate ? '建议先完成基础信息，再补正文与附件。' : '建议优先修改正文与状态，再处理附件和删除操作。' }}</div>
        </div>
        <div class="topbar-right">
            <a class="button secondary" href="{{ $backToListUrl }}">返回列表</a>
            <button
                class="button"
                type="submit"
                form="content-editor-form"
                data-page-save-button
                data-label-draft="{{ $type === 'article' ? '保存草稿' : ($isCreate ? '创建' . $typeLabel : '保存修改') }}"
                data-label-published="{{ $type === 'article' ? $initialPublishActionLabel : ($isCreate ? '创建' . $typeLabel : '保存修改') }}"
            >
                <span data-page-save-label>{{ $initialSaveActionLabel }}</span>
            </button>
        </div>
    </section>

    <div class="content-editor-floating-actions">
        <button
            class="button"
            type="submit"
            form="content-editor-form"
            data-tip="{{ $initialSaveActionLabel }}"
            aria-label="{{ $initialSaveActionLabel }}"
            data-floating-save-button
        ><span>@if($isCreate)创<br>建@else保<br>存@endif</span></button>
    </div>

    <section class="content-editor-shell">
        <form id="content-editor-form" method="POST" action="{{ $isCreate ? ($type === 'page' ? route('admin.pages.store') : route('admin.articles.store')) : ($type === 'page' ? route('admin.pages.update', $content->id) : route('admin.articles.update', $content->id)) }}" class="stack" novalidate>
            @csrf
            <input type="hidden" name="return_to" value="{{ $returnTo }}">

            <div class="content-editor-main">
                <div class="content-editor-panel primary">
                    <div class="content-editor-heading">
                        <div>
                            <h3 class="content-editor-title">{{ $isCreate ? '开始撰写' : '编辑内容' }}</h3>
                        </div>
                        <div class="content-side-switcher" data-editor-switcher>
                            <button class="content-side-button is-active" type="button" data-pane-target="main">正文内容</button>
                            <button class="content-side-button" type="button" data-pane-target="basic">基础参数</button>
                            @if (! $isCreate && $type === 'article' && $articleRequiresReview && $reviewHistory->isNotEmpty())
                                <button class="content-side-button" type="button" data-pane-target="reviews">审核记录</button>
                            @endif
                        </div>
                    </div>

                    @if (! $isCreate && $type === 'article' && $content->status === 'rejected' && $latestRejectedReview)
                        <section class="content-review-alert">
                            <div class="content-review-alert-title">
                                <span class="content-review-alert-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                                </span>
                                最近一次审核已驳回
                            </div>
                            <div class="content-review-alert-grid">
                                <div class="content-review-alert-item" style="grid-column: 1 / -1;">
                                    <span class="content-review-alert-label">驳回原因</span>
                                    <div class="content-review-alert-value">{{ $latestRejectedReview->reason ?: '未填写驳回原因。' }}</div>
                                </div>
                                <div class="content-review-alert-meta">
                                    <div class="content-review-alert-meta-item">
                                        <span class="content-review-alert-label">审核人</span>
                                        <div class="content-review-alert-value">{{ $latestRejectedReview->reviewer_name ?: '未记录' }}</div>
                                    </div>
                                    <div class="content-review-alert-meta-item">
                                        <span class="content-review-alert-label">联系电话</span>
                                        <div class="content-review-alert-value">{{ $latestRejectedReview->reviewer_phone ?: '未记录' }}</div>
                                    </div>
                                    <div class="content-review-alert-meta-item">
                                        <span class="content-review-alert-label">驳回时间</span>
                                        <div class="content-review-alert-value">{{ \Illuminate\Support\Carbon::parse($latestRejectedReview->created_at)->format('Y-m-d H:i') }}</div>
                                    </div>
                                    <div class="content-review-alert-meta-item">
                                        <span class="content-review-alert-label">驳回次数</span>
                                        <div class="content-review-alert-value">{{ $rejectCount }} 次</div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    @endif

                        <div class="content-editor-panels" data-editor-panels>
                            @php
                                $publishedAtValue = old('published_at');
                                if ($publishedAtValue === null) {
                                    $publishedAtSource = $content->published_at
                                        ?: (($content->status ?? null) === 'rejected' ? ($latestRejectedReview->created_at ?? $latestReviewRecord->created_at ?? null) : ($latestReviewRecord->created_at ?? null));

                                    if (! empty($publishedAtSource)) {
                                        $publishedAtValue = \Illuminate\Support\Carbon::parse($publishedAtSource)->format('Y-m-d\TH:i');
                                    }
                                }
                            @endphp

                            <section class="content-editor-pane" data-editor-pane="basic">
                                <div class="content-pane-grid">
                                    <div class="content-pane-card is-plain">
                                        <h4 class="content-pane-card-title">摘要</h4>
                                        <textarea class="field textarea" id="summary" name="summary">{{ old('summary', $content->summary) }}</textarea>
                                    </div>
                                </div>
                                <div class="content-pane-grid">
                                    <div class="content-pane-card is-plain">
                                        <h4 class="content-pane-card-title">来源</h4>
                                        <input class="field" id="source" type="text" name="source" value="{{ old('source', $content->source ?: $currentSite->name) }}">
                                    </div>
                                </div>
                                <div class="content-pane-grid">
                                    <div class="content-pane-card is-plain">
                                        <h4 class="content-pane-card-title">发布人</h4>
                                        <div class="content-static-display">{{ $publisherName }}</div>
                                    </div>
                                </div>
                            </section>

                            @if (! $isCreate && $type === 'article' && $articleRequiresReview && $reviewHistory->isNotEmpty())
                                <section class="content-editor-pane" data-editor-pane="reviews">
                                    <section class="content-review-history" style="margin-bottom: 0;">
                                        <div class="content-review-history-title">最近审核记录</div>
                                        <div class="content-review-history-list">
                                            @foreach ($reviewHistory as $reviewItem)
                                                @php
                                                    $reviewActionMap = [
                                                        'submitted' => '提交审核',
                                                        'approved' => '审核通过',
                                                        'rejected' => '驳回内容',
                                                    ];
                                                @endphp
                                                <article class="content-review-history-item">
                                                    <div class="content-review-history-top">
                                                        <span class="content-review-history-action is-{{ $reviewItem->action }}">
                                                            {{ $reviewActionMap[$reviewItem->action] ?? '审核记录' }}
                                                        </span>
                                                        <span class="content-review-history-meta">
                                                            {{ $reviewItem->reviewer_name ?: '未记录审核人' }}
                                                            @if ($reviewItem->reviewer_phone)
                                                                · {{ $reviewItem->reviewer_phone }}
                                                            @endif
                                                            · {{ \Illuminate\Support\Carbon::parse($reviewItem->created_at)->format('Y-m-d H:i') }}
                                                        </span>
                                                    </div>
                                                    @if ($reviewItem->action === 'rejected')
                                                        <div class="content-review-history-reason">驳回原因：{{ $reviewItem->reason ?: '未填写驳回原因。' }}</div>
                                                    @elseif ($reviewItem->action === 'submitted')
                                                        <div class="content-review-history-reason">已提交审核，等待审核人处理后才会正式上线。</div>
                                                    @else
                                                        <div class="content-review-history-reason">审核通过后文章已进入正式发布状态。</div>
                                                    @endif
                                                </article>
                                            @endforeach
                                        </div>
                                    </section>
                                </section>
                            @endif

                        </div>

                    <div class="content-main-stack" data-editor-main>
                        <div class="content-field-group">
                            <div class="content-main-top">
                                <div class="content-meta-stack">
                                    <div class="content-main-row">
                                        <label class="content-main-label" for="channel_id_main">栏目</label>
                                        @if ($type === 'article')
                                            <div class="content-channel-select @error('channel_ids') is-error @enderror @error('channel_ids.*') is-error @enderror" data-content-channel-select>
                                                <button class="content-channel-trigger" type="button" data-content-channel-trigger aria-haspopup="dialog" aria-expanded="false">请选择栏目</button>
                                                <div class="content-channel-panel" data-content-channel-panel>
                                                    <div class="content-channel-search" data-channel-search>
                                                        <input class="content-channel-search-input" type="text" placeholder="搜索栏目名称" data-channel-search-input>
                                                        <button class="content-channel-search-clear" type="button" data-channel-search-clear aria-label="清空搜索">
                                                            <svg viewBox="0 0 16 16" aria-hidden="true">
                                                                <path d="M4 4l8 8"></path>
                                                                <path d="M12 4 4 12"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <div class="content-channel-list">
                                                        @foreach ($channels as $channel)
                                                            <label
                                                                class="content-channel-option {{ !empty($channel->is_selectable) ? '' : 'is-disabled' }}"
                                                                data-depth="{{ (int) ($channel->tree_depth ?? 0) }}"
                                                                style="--channel-depth: {{ (int) ($channel->tree_depth ?? 0) }};"
                                                                data-channel-option
                                                                data-channel-keyword="{{ \Illuminate\Support\Str::lower($channel->name) }}"
                                                            >
                                                                @if (!empty($channel->is_selectable))
                                                                    <input
                                                                        class="content-channel-checkbox"
                                                                        type="checkbox"
                                                                        name="channel_ids[]"
                                                                        value="{{ $channel->id }}"
                                                                        data-channel-checkbox
                                                                        data-channel-name="{{ $channel->name }}"
                                                                        @checked(in_array((int) $channel->id, $selectedChannelIds ?? [], true))
                                                                    >
                                                                @else
                                                                    <span class="content-channel-checkbox-spacer" aria-hidden="true"></span>
                                                                @endif
                                                                <span class="content-channel-guides" aria-hidden="true">
                                                                    @foreach (($channel->tree_ancestors ?? []) as $hasLine)
                                                                        <span class="content-channel-guide {{ $hasLine ? 'is-active' : '' }}"></span>
                                                                    @endforeach
                                                                    @if ((int) ($channel->tree_depth ?? 0) > 0)
                                                                        <span class="content-channel-branch {{ !empty($channel->tree_is_last) ? 'is-last' : '' }}"></span>
                                                                    @endif
                                                                </span>
                                                                <span class="content-channel-icon {{ !empty($channel->tree_has_children) || (int) ($channel->tree_depth ?? 0) === 0 ? 'is-folder' : '' }}" aria-hidden="true">
                                                                    @if (!empty($channel->tree_has_children) || (int) ($channel->tree_depth ?? 0) === 0)
                                                                        <svg viewBox="0 0 24 24"><path d="M3 7.5A1.5 1.5 0 0 1 4.5 6h4.086a1.5 1.5 0 0 1 1.06.44l1.414 1.414A1.5 1.5 0 0 0 12.121 8.5H19.5A1.5 1.5 0 0 1 21 10v7.5A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5z"/></svg>
                                                                    @else
                                                                        <svg viewBox="0 0 24 24"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z"/><path d="M14 2v5h5"/><path d="M9 13h6"/><path d="M9 17h4"/></svg>
                                                                    @endif
                                                                </span>
                                                                <span class="content-channel-text">{{ $channel->name }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="field-meta">
                                                @error('channel_ids')
                                                    <span class="form-error">{{ $message }}</span>
                                                @enderror
                                                @error('channel_ids.*')
                                                    <span class="form-error">{{ $message }}</span>
                                                @enderror
                                            </span>
                                            @if(($lockedSelectedChannels ?? collect())->isNotEmpty())
                                                <div class="content-channel-locked-note">
                                                    <div class="content-channel-locked-title">以下栏目已关联，但当前账号无权调整，本次保存会自动保留：</div>
                                                    <div class="content-channel-locked-tags">
                                                        @foreach($lockedSelectedChannels as $lockedChannel)
                                                            <span class="content-channel-locked-tag">{{ $lockedChannel->name }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        @else
                                            <div class="content-channel-select @error('channel_ids') is-error @enderror @error('channel_ids.*') is-error @enderror" data-content-channel-select>
                                                <button class="content-channel-trigger" type="button" data-content-channel-trigger aria-haspopup="dialog" aria-expanded="false">请选择栏目</button>
                                                <div class="content-channel-panel" data-content-channel-panel>
                                                    <div class="content-channel-search" data-channel-search>
                                                        <input class="content-channel-search-input" type="text" placeholder="搜索栏目名称" data-channel-search-input>
                                                        <button class="content-channel-search-clear" type="button" data-channel-search-clear aria-label="清空搜索">
                                                            <svg viewBox="0 0 16 16" aria-hidden="true">
                                                                <path d="M4 4l8 8"></path>
                                                                <path d="M12 4 4 12"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <div class="content-channel-list">
                                                        @foreach ($channels as $channel)
                                                            <label
                                                                class="content-channel-option {{ !empty($channel->is_selectable) ? '' : 'is-disabled' }}"
                                                                data-depth="{{ (int) ($channel->tree_depth ?? 0) }}"
                                                                style="--channel-depth: {{ (int) ($channel->tree_depth ?? 0) }};"
                                                                data-channel-option
                                                                data-channel-keyword="{{ \Illuminate\Support\Str::lower($channel->name) }}"
                                                            >
                                                                @if (!empty($channel->is_selectable))
                                                                    <input
                                                                        class="content-channel-checkbox"
                                                                        type="checkbox"
                                                                        name="channel_ids[]"
                                                                        value="{{ $channel->id }}"
                                                                        data-channel-checkbox
                                                                        data-channel-name="{{ $channel->name }}"
                                                                        @checked(in_array((int) $channel->id, $selectedChannelIds ?? [], true))
                                                                    >
                                                                @else
                                                                    <span class="content-channel-checkbox-spacer" aria-hidden="true"></span>
                                                                @endif
                                                                <span class="content-channel-guides" aria-hidden="true">
                                                                    @foreach (($channel->tree_ancestors ?? []) as $hasLine)
                                                                        <span class="content-channel-guide {{ $hasLine ? 'is-active' : '' }}"></span>
                                                                    @endforeach
                                                                    @if ((int) ($channel->tree_depth ?? 0) > 0)
                                                                        <span class="content-channel-branch {{ !empty($channel->tree_is_last) ? 'is-last' : '' }}"></span>
                                                                    @endif
                                                                </span>
                                                                <span class="content-channel-icon {{ !empty($channel->tree_has_children) || (int) ($channel->tree_depth ?? 0) === 0 ? 'is-folder' : '' }}" aria-hidden="true">
                                                                    @if (!empty($channel->tree_has_children) || (int) ($channel->tree_depth ?? 0) === 0)
                                                                        <svg viewBox="0 0 24 24"><path d="M3 7.5A1.5 1.5 0 0 1 4.5 6h4.086a1.5 1.5 0 0 1 1.06.44l1.414 1.414A1.5 1.5 0 0 0 12.121 8.5H19.5A1.5 1.5 0 0 1 21 10v7.5A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5z"/></svg>
                                                                    @else
                                                                        <svg viewBox="0 0 24 24"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z"/><path d="M14 2v5h5"/><path d="M9 13h6"/><path d="M9 17h4"/></svg>
                                                                    @endif
                                                                </span>
                                                                <span class="content-channel-text">{{ $channel->name }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="field-meta">
                                                @error('channel_ids')
                                                    <span class="form-error">{{ $message }}</span>
                                                @enderror
                                                @error('channel_ids.*')
                                                    <span class="form-error">{{ $message }}</span>
                                                @enderror
                                            </span>
                                            @if(($lockedSelectedChannels ?? collect())->isNotEmpty())
                                                <div class="content-channel-locked-note">
                                                    <div class="content-channel-locked-title">以下栏目已关联，但当前账号无权调整，本次保存会自动保留：</div>
                                                    <div class="content-channel-locked-tags">
                                                        @foreach($lockedSelectedChannels as $lockedChannel)
                                                            <span class="content-channel-locked-tag">{{ $lockedChannel->name }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        @endif
                                    </div>

                                    <div class="content-main-row @if($type === 'article') has-article-flags @endif">
                                        <label class="content-main-label" for="title">标题</label>
                                        <div class="content-title-stack">
                                            @php
                                                $currentEditorStatus = old('status', $content->status);
                                                $editorStatus = in_array($currentEditorStatus, ['published', 'pending', 'rejected'], true) ? 'published' : 'draft';
                                                $publishOptionLabel = ($type === 'article' && $articleRequiresReview)
                                                    ? '提交审核'
                                                    : '正式发布';
                                            @endphp
                                            <div class="content-title-input-row">
                                                <input class="field content-title-input content-main-field @error('title') is-error @enderror" id="title" type="text" name="title" value="{{ old('title', $content->title) }}" required>
                                                <div class="content-title-toolbar" data-title-toolbar>
                                                    <input id="title_color" class="content-title-color-input" type="hidden" name="title_color" value="{{ old('title_color', $content->title_color ?: '') }}">
                                                    <div class="content-color-control">
                                                        <button class="content-style-button content-color-button @if(old('title_color', $content->title_color ?: '')) has-selection @endif" type="button" data-color-trigger data-tip="标题颜色">
                                                            <span data-color-preview></span>
                                                        </button>
                                                        <div class="content-color-picker" data-color-picker>
                                                            @foreach ([
                                                                ['value' => '#0047AB', 'label' => '宝蓝'],
                                                                ['value' => '#2563EB', 'label' => '亮蓝'],
                                                                ['value' => '#7C3AED', 'label' => '紫罗兰'],
                                                                ['value' => '#DB2777', 'label' => '玫红'],
                                                                ['value' => '#059669', 'label' => '松绿'],
                                                                ['value' => '#D97706', 'label' => '琥珀'],
                                                                ['value' => '#DC2626', 'label' => '朱红'],
                                                            ] as $colorOption)
                                                                <button
                                                                    class="content-color-swatch @if(strtolower((string) old('title_color', $content->title_color ?: '')) === strtolower($colorOption['value'])) is-active @endif"
                                                                    type="button"
                                                                    data-color-swatch
                                                                    data-color="{{ $colorOption['value'] }}"
                                                                    data-tip="{{ $colorOption['label'] }}"
                                                                    style="background: {{ $colorOption['value'] }}"
                                                                ></button>
                                                            @endforeach
                                                            <button class="content-color-swatch reset @if(old('title_color', $content->title_color ?: '') === '') is-active @endif" type="button" data-color-reset data-tip="默认色"><i></i></button>
                                                        </div>
                                                    </div>
                                                    <input type="hidden" name="title_bold" value="0">
                                                    <label class="content-style-button @if(old('title_bold', $content->title_bold)) is-active @endif" data-style-toggle data-style-label="加粗" data-tip="标题加粗">
                                                        <input id="title_bold" type="checkbox" name="title_bold" value="1" @checked(old('title_bold', $content->title_bold)) hidden>
                                                        B
                                                    </label>
                                                    <input type="hidden" name="title_italic" value="0">
                                                    <label class="content-style-button italic @if(old('title_italic', $content->title_italic)) is-active @endif" data-style-toggle data-style-label="斜体" data-tip="标题斜体">
                                                        <input id="title_italic" type="checkbox" name="title_italic" value="1" @checked(old('title_italic', $content->title_italic)) hidden>
                                                        I
                                                    </label>
                                                    @if ($type === 'article')
                                                        <input type="hidden" name="is_top" value="0">
                                                        <label class="content-style-button @if(old('is_top', $content->is_top)) is-active @endif" data-style-toggle data-style-label="置顶" data-tip="栏目置顶">
                                                            <input id="is_top" type="checkbox" name="is_top" value="1" @checked(old('is_top', $content->is_top)) hidden>
                                                            顶
                                                        </label>
                                                        <input type="hidden" name="is_recommend" value="0">
                                                        <label class="content-style-button @if(old('is_recommend', $content->is_recommend)) is-active @endif" data-style-toggle data-style-label="精华" data-tip="标题精华标识">
                                                            <input id="is_recommend" type="checkbox" name="is_recommend" value="1" @checked(old('is_recommend', $content->is_recommend)) hidden>
                                                            精
                                                        </label>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="content-main-row">
                                        <label class="content-main-label" for="published_at">时间</label>
                                        <div class="content-title-meta-field time-field">
                                            <div class="content-datetime-field">
                                                <input class="field content-datetime-input" id="published_at" type="datetime-local" name="published_at" value="{{ $publishedAtValue }}">
                                                <button class="content-datetime-trigger" type="button" data-datetime-trigger aria-label="选择时间">选择</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="content-main-row">
                                        <div class="content-main-label">状态</div>
                                        <div class="content-title-meta-field">
                                            <div class="content-status-options">
                                                <label class="content-status-option @if($editorStatus === 'draft') is-active @endif">
                                                    <input type="radio" name="status" value="draft" @checked($editorStatus === 'draft')>
                                                    草稿
                                                </label>
                                                @php
                                                    $canRequestPublish = $canPublish || ($type === 'article' && $articleRequiresReview);
                                                @endphp
                                                <label class="content-status-option @if($editorStatus === 'published') is-active @endif @if(! $canRequestPublish) is-disabled @endif">
                                                    <input type="radio" name="status" value="published" @checked($editorStatus === 'published') @disabled(! $canRequestPublish)>
                                                    {{ $publishOptionLabel }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="content-cover-card">
                                    <input class="field content-cover-input" id="cover_image" type="text" name="cover_image" value="{{ old('cover_image', $content->cover_image) }}" data-cover-input>
                                    <div class="content-cover-preview" data-cover-preview data-open-cover-library>
                                        @if (! empty(old('cover_image', $content->cover_image)))
                                            <img src="{{ old('cover_image', $content->cover_image) }}" alt="封面图预览" data-cover-image>
                                        @else
                                            <div class="content-cover-placeholder" data-cover-placeholder>{{ $typeLabel }}封面</div>
                                        @endif
                                        <div class="content-cover-actions">
                                            <button class="content-cover-remove" type="button" data-cover-remove>
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M3 6h18"></path>
                                                    <path d="M8 6V4h8v2"></path>
                                                    <path d="M19 6l-1 14H6L5 6"></path>
                                                    <path d="M10 11v6"></path>
                                                    <path d="M14 11v6"></path>
                                                </svg>
                                                删除封面
                                            </button>
                                        </div>
                                    </div>
                                    <div class="content-cover-meta" data-open-cover-library>
                                        <div class="content-cover-tip">幻灯片或文章图片展示需要上传封面图</div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="content-body-header">
                                    <label class="content-main-label" for="content">正文</label>
                                    @if (! $isCreate)
                                        <a class="button secondary neutral-action content-body-preview-button" href="{{ $previewUrl }}" target="_blank" rel="noreferrer">前台预览</a>
                                    @endif
                                </div>
                                <div class="content-editor-body @error('content') is-error @enderror">
                                    <textarea class="field textarea textarea-lg rich-editor" id="content" name="content">{{ old('content', $content->content) }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </form>
    </section>

    @include('admin.site.attachments._attachment_library_modal')

    <div id="emoji-picker-modal" class="emoji-picker-modal" hidden>
        <div class="emoji-picker-backdrop" data-close-emoji-picker></div>
        <div class="emoji-picker-panel" role="dialog" aria-modal="true" aria-labelledby="emoji-picker-title">
            <div class="emoji-picker-header">
                <div>
                    <h3 id="emoji-picker-title">表情面板</h3>
                    <div class="muted">选择表情后会直接插入正文，最近使用会自动保留。</div>
                </div>
                <button class="button secondary" type="button" data-close-emoji-picker>关闭</button>
            </div>
            <div class="emoji-picker-toolbar">
                <input id="emoji-picker-search" class="field" type="text" placeholder="搜索表情名称">
                <div id="emoji-picker-categories" class="emoji-picker-categories"></div>
            </div>
            <div id="emoji-picker-grid" class="emoji-picker-grid"></div>
        </div>
    </div>

    <div id="video-embed-modal" class="video-embed-modal" hidden>
        <div class="video-embed-backdrop" data-close-video-embed></div>
        <div class="video-embed-panel" role="dialog" aria-modal="true" aria-labelledby="video-embed-title">
            <div class="video-embed-header">
                <div>
                    <h3 id="video-embed-title">插入视频</h3>
                    <div class="muted">粘贴哔哩哔哩视频网页地址，系统会自动解析为可播放视频。</div>
                </div>
                <button class="button secondary" type="button" data-close-video-embed>关闭</button>
            </div>
            <div class="video-embed-grid">
                <div class="video-embed-field video-embed-field-wide">
                    <label for="video-embed-url">哔哩哔哩地址</label>
                    <input id="video-embed-url" class="field" type="text" placeholder="例如：https://www.bilibili.com/video/BV1xx411c7mD/">
                </div>
                <div class="video-embed-field">
                    <label for="video-embed-width">宽度</label>
                    <input id="video-embed-width" class="field" type="text" value="90%" placeholder="90%">
                </div>
                <div class="video-embed-field">
                    <label for="video-embed-height">高度</label>
                    <input id="video-embed-height" class="field" type="text" value="450" placeholder="450">
                </div>
                <div class="video-embed-field">
                    <label for="video-embed-align">对齐方式</label>
                    <div class="site-select" data-site-select>
                        <select id="video-embed-align" class="field site-select-native">
                            <option value="center" selected>居中</option>
                            <option value="left">居左</option>
                            <option value="right">居右</option>
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-expanded="false">居中</button>
                        <div class="site-select-panel" data-select-panel></div>
                    </div>
                </div>
            </div>
            <div id="video-embed-error" class="video-embed-error" hidden></div>
            <div class="action-row video-embed-actions">
                <button id="video-embed-confirm" class="button" type="button">插入视频</button>
            </div>
        </div>
    </div>

@endsection

@push('styles')
    <style>
        @include('admin.site.attachments._attachment_library_styles')
        .emoji-picker-modal[hidden] { display: none; }
        .emoji-picker-modal { position: fixed; inset: 0; z-index: 2450; }
        .emoji-picker-backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.48); backdrop-filter: blur(5px); }
        .emoji-picker-panel {
            position: relative;
            width: min(760px, calc(100% - 32px));
            max-height: calc(100vh - 64px);
            margin: 32px auto;
            padding: 24px;
            overflow: auto;
            border-radius: 28px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            border: 1px solid #e5e7eb;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
        }
        .emoji-picker-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 18px;
        }
        .emoji-picker-header h3 { margin: 0 0 6px; font-size: 24px; color: #111827; }
        .emoji-picker-toolbar { display: grid; gap: 14px; margin-bottom: 18px; }
        .emoji-picker-categories { display: flex; flex-wrap: wrap; gap: 10px; }
        .emoji-picker-category {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .emoji-picker-category:hover {
            border-color: rgba(0, 71, 171, 0.16);
            background: rgba(240, 246, 255, 0.92);
            color: var(--color-primary, #0047AB);
        }
        .emoji-picker-category.is-active {
            border-color: transparent;
            background: var(--color-primary, #0047AB);
            color: #fff;
        }
        .emoji-picker-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(78px, 1fr));
            gap: 12px;
        }
        .emoji-picker-item {
            display: grid;
            justify-items: center;
            gap: 8px;
            min-height: 96px;
            padding: 12px 8px;
            border-radius: 18px;
            border: 1px solid #eef2f7;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            cursor: pointer;
            transition: transform 0.18s ease, border-color 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease;
        }
        .emoji-picker-item:hover {
            transform: translateY(-2px);
            border-color: rgba(0, 71, 171, 0.18);
            background: rgba(240, 246, 255, 0.92);
            box-shadow: 0 10px 22px rgba(0, 71, 171, 0.08);
        }
        .emoji-picker-glyph { font-size: 32px; line-height: 1; }
        .emoji-picker-name {
            max-width: 100%;
            color: #64748b;
            font-size: 11px;
            line-height: 1.35;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .emoji-picker-empty {
            grid-column: 1 / -1;
            padding: 36px 12px;
            border-radius: 20px;
            border: 1px dashed #dbe2ea;
            background: #f8fafc;
            color: #64748b;
            text-align: center;
            font-size: 14px;
            line-height: 1.7;
        }
        .video-embed-modal[hidden] { display: none; }
        .video-embed-modal { position: fixed; inset: 0; z-index: 2470; }
        .video-embed-backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.42); backdrop-filter: blur(4px); }
        .video-embed-panel {
            position: relative;
            width: min(640px, calc(100% - 32px));
            margin: 56px auto;
            padding: 24px;
            border-radius: 26px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            border: 1px solid #e5e7eb;
            box-shadow: 0 24px 64px rgba(15, 23, 42, 0.18);
        }
        .video-embed-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 18px;
        }
        .video-embed-header h3 {
            margin: 0 0 6px;
            font-size: 24px;
            color: #111827;
        }
        .video-embed-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 140px 140px;
            gap: 14px;
        }
        .video-embed-field { display: grid; gap: 8px; }
        .video-embed-field-wide { grid-column: 1 / -1; }
        .video-embed-error {
            margin-top: 14px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(239, 68, 68, 0.18);
            background: rgba(254, 242, 242, 0.9);
            color: #dc2626;
            font-size: 13px;
            line-height: 1.6;
        }
        .video-embed-actions {
            margin-top: 16px;
            justify-content: flex-end;
        }
        @media (max-width: 920px) {
            .attachment-library-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 680px) {
            .attachment-library-panel { margin: 16px auto; padding: 18px; width: calc(100% - 20px); }
            .attachment-library-toolbar { grid-template-columns: 1fr; }
            .attachment-library-grid { grid-template-columns: 1fr; }
            .attachment-library-pagination,
            .attachment-library-pagination nav,
            .attachment-library-pagination .pagination-shell {
                justify-content: center;
            }
            .emoji-picker-panel { margin: 16px auto; padding: 18px; width: calc(100% - 20px); }
            .video-embed-panel { margin: 16px auto; padding: 18px; width: calc(100% - 20px); }
            .video-embed-grid { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@push('scripts')
    @include('admin.site._custom_select_scripts')
@endpush

@push('scripts')
    <script src="/vendor/tinymce/tinymce.min.js"></script>
    <script>
        document.querySelectorAll('[data-editor-switcher]').forEach((switcher) => {
            const buttons = Array.from(switcher.querySelectorAll('[data-pane-target]'));
            const panes = Array.from(document.querySelectorAll('[data-editor-pane]'));
            const panelWrapper = document.querySelector('[data-editor-panels]');
            const mainStack = document.querySelector('[data-editor-main]');
            let activeTarget = 'main';

            const activatePane = (target) => {
                activeTarget = target;

                buttons.forEach((button) => {
                    button.classList.toggle('is-active', button.getAttribute('data-pane-target') === target);
                });

                panes.forEach((pane) => {
                    pane.classList.toggle('is-active', pane.getAttribute('data-editor-pane') === target);
                });

                const showSecondaryPane = Boolean(target && target !== 'main');
                panelWrapper?.classList.toggle('is-active', showSecondaryPane);
                mainStack?.classList.toggle('is-hidden', showSecondaryPane);
            };

            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    const target = button.getAttribute('data-pane-target');
                    activatePane(activeTarget === target ? 'main' : target);
                });
            });

            activatePane('main');
        });

        (() => {
            const input = document.getElementById('cover_image');
            const preview = document.querySelector('[data-cover-preview]');
            const card = document.querySelector('.content-cover-card');
            const removeButton = document.querySelector('[data-cover-remove]');

            if (! input || ! preview || ! card) {
                return;
            }

            const ensureImage = () => {
                let img = preview.querySelector('[data-cover-image]');
                if (! img) {
                    img = document.createElement('img');
                    img.setAttribute('data-cover-image', '');
                    img.alt = '封面图预览';
                    preview.appendChild(img);
                }
                return img;
            };

            const ensurePlaceholder = () => {
                let placeholder = preview.querySelector('[data-cover-placeholder]');
                if (! placeholder) {
                    placeholder = document.createElement('div');
                    placeholder.setAttribute('data-cover-placeholder', '');
                    placeholder.className = 'content-cover-placeholder';
                    placeholder.textContent = '{{ $typeLabel }}封面';
                    preview.appendChild(placeholder);
                }
                return placeholder;
            };

            const renderCover = () => {
                const value = input.value.trim();
                const img = preview.querySelector('[data-cover-image]');
                const placeholder = ensurePlaceholder();

                if (! value) {
                    if (img) img.remove();
                    placeholder.style.display = 'flex';
                    card.classList.remove('has-cover');
                    return;
                }

                const image = ensureImage();
                image.src = value;
                image.onerror = () => {
                    image.remove();
                    ensurePlaceholder().style.display = 'flex';
                    card.classList.remove('has-cover');
                };
                placeholder.style.display = 'none';
                card.classList.add('has-cover');
            };

            input.addEventListener('input', renderCover);
            renderCover();

            removeButton?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                input.value = '';
                renderCover();
            });
        })();

        (() => {
            const floatingActions = document.querySelector('.content-editor-floating-actions');
            const pageSaveButton = document.querySelector('[data-page-save-button]');

            if (! floatingActions || ! pageSaveButton || !('IntersectionObserver' in window)) {
                return;
            }

            const observer = new IntersectionObserver(([entry]) => {
                floatingActions.classList.toggle('is-visible', entry.intersectionRatio < 0.88);
            }, {
                root: null,
                rootMargin: '-50px 0px 0px 0px',
                threshold: [0, 0.25, 0.55, 0.8, 0.88, 1],
            });

            observer.observe(pageSaveButton);
        })();

        document.querySelectorAll('[data-style-toggle]').forEach((toggle) => {
            const input = toggle.querySelector('input[type="checkbox"]');
            if (! input) {
                return;
            }

            const sync = () => toggle.classList.toggle('is-active', input.checked);
            input.addEventListener('change', sync);
            sync();
        });

        document.querySelectorAll('.content-status-options').forEach((group) => {
            const options = Array.from(group.querySelectorAll('.content-status-option'));
            const pageSaveButton = document.querySelector('[data-page-save-button]');
            const pageSaveLabel = document.querySelector('[data-page-save-label]');
            const floatingSaveButton = document.querySelector('[data-floating-save-button]');

            const sync = () => {
                options.forEach((option) => {
                    const input = option.querySelector('input[type="radio"]');
                    option.classList.toggle('is-active', Boolean(input?.checked));
                });

                const checkedInput = group.querySelector('input[type="radio"]:checked');
                if (! checkedInput || ! pageSaveButton || ! pageSaveLabel) {
                    return;
                }

                const nextLabel = checkedInput.value === 'published'
                    ? pageSaveButton.dataset.labelPublished
                    : pageSaveButton.dataset.labelDraft;

                if (! nextLabel) {
                    return;
                }

                pageSaveLabel.textContent = nextLabel;

                if (floatingSaveButton) {
                    floatingSaveButton.setAttribute('data-tip', nextLabel);
                    floatingSaveButton.setAttribute('aria-label', nextLabel);
                }
            };

            options.forEach((option) => {
                const input = option.querySelector('input[type="radio"]');
                if (! input) {
                    return;
                }

                option.addEventListener('click', () => {
                    if (input.disabled) {
                        return;
                    }
                    input.checked = true;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    sync();
                });

                input.addEventListener('change', sync);
            });

            sync();
        });

        (() => {
            const toolbar = document.querySelector('[data-title-toolbar]');
            const trigger = document.querySelector('[data-color-trigger]');
            const picker = document.querySelector('[data-color-picker]');
            const preview = document.querySelector('[data-color-preview]');
            const input = document.getElementById('title_color');
            const titleInput = document.getElementById('title');
            const boldInput = document.getElementById('title_bold');
            const italicInput = document.getElementById('title_italic');
            const reset = document.querySelector('[data-color-reset]');

            if (! toolbar || ! trigger || ! picker || ! preview || ! input) {
                return;
            }

            const syncTitlePreview = () => {
                if (! titleInput) {
                    return;
                }

                titleInput.style.color = input.value || '';
                titleInput.style.fontWeight = boldInput?.checked ? '700' : '400';
                titleInput.style.fontStyle = italicInput?.checked ? 'italic' : 'normal';
            };

            const sync = () => {
                document.querySelectorAll('[data-color-swatch]').forEach((swatch) => {
                    const swatchColor = (swatch.dataset.color || '').toLowerCase();
                    swatch.classList.toggle('is-active', swatchColor === input.value.toLowerCase());
                });
                reset?.classList.toggle('is-active', input.value === '');
                trigger.classList.toggle('has-selection', input.value !== '');
                if (input.value) {
                    preview.style.setProperty('--swatch-color', input.value);
                    trigger.style.setProperty('--swatch-color', input.value);
                } else {
                    trigger.style.removeProperty('--swatch-color');
                }
                syncTitlePreview();
            };

            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                picker.classList.toggle('is-open');
            });

            document.querySelectorAll('[data-color-swatch]').forEach((swatch) => {
                swatch.addEventListener('click', () => {
                    input.value = swatch.dataset.color;
                    picker.classList.remove('is-open');
                    sync();
                });
            });

            reset?.addEventListener('click', () => {
                input.value = '';
                picker.classList.remove('is-open');
                sync();
            });

            boldInput?.addEventListener('change', syncTitlePreview);
            italicInput?.addEventListener('change', syncTitlePreview);

            document.addEventListener('click', (event) => {
                if (! toolbar.contains(event.target)) {
                    picker.classList.remove('is-open');
                }
            });

            sync();
        })();

        (() => {
            const form = document.getElementById('content-editor-form');
            const titleInput = document.getElementById('title');
            const contentTextarea = document.getElementById('content');
            const contentEditorBody = document.querySelector('.content-editor-body');

            if (!form || !titleInput || !contentTextarea || !contentEditorBody) {
                return;
            }

            const hasMeaningfulContent = (html) => {
                const raw = String(html || '').trim();

                if (raw === '') {
                    return false;
                }

                if (/<(img|video|iframe|embed|object|audio|table|blockquote|pre|ul|ol)\b/i.test(raw)) {
                    return true;
                }

                const temp = document.createElement('div');
                temp.innerHTML = raw;
                const text = (temp.textContent || temp.innerText || '').replace(/[\u00A0\u200B-\u200D\uFEFF\s]+/g, '');

                return text !== '';
            };

            const clearTitleError = () => {
                titleInput.classList.remove('is-error');
                titleInput.removeAttribute('aria-invalid');
            };

            const clearContentError = () => {
                contentTextarea.classList.remove('is-error');
                contentTextarea.removeAttribute('aria-invalid');
                contentEditorBody.classList.remove('is-error');
            };

            const validateForm = () => {
                tinymce.triggerSave();

                let isValid = true;
                let firstInvalid = null;

                clearTitleError();
                clearContentError();

                if (titleInput.value.trim() === '') {
                    titleInput.classList.add('is-error');
                    titleInput.setAttribute('aria-invalid', 'true');
                    firstInvalid = firstInvalid || titleInput;
                    isValid = false;
                }

                if (!hasMeaningfulContent(contentTextarea.value)) {
                    contentTextarea.classList.add('is-error');
                    contentTextarea.setAttribute('aria-invalid', 'true');
                    contentEditorBody.classList.add('is-error');
                    firstInvalid = firstInvalid || contentTextarea;
                    isValid = false;
                }

                if (!isValid) {
                    const messages = [];
                    if (titleInput.classList.contains('is-error')) {
                        messages.push('请输入标题');
                    }
                    if (contentEditorBody.classList.contains('is-error')) {
                        messages.push('请输入正文内容');
                    }
                    showMessage(messages.join('，') + '。', 'error');
                    firstInvalid?.focus();
                    firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                return isValid;
            };

            form.addEventListener('submit', (event) => {
                if (!validateForm()) {
                    event.preventDefault();
                }
            });

            titleInput.addEventListener('input', clearTitleError);

            document.addEventListener('tinymce-editor-ready', (event) => {
                if (event.detail?.id !== 'content') {
                    return;
                }

                const editor = tinymce.get('content');
                editor?.on('input change undo redo SetContent', clearContentError);
            });
        })();

        @if ($errors->has('title') || $errors->has('content'))
            (() => {
                const messages = [];
                @if ($errors->has('title'))
                    messages.push(@json($errors->first('title')));
                @endif
                @if ($errors->has('content'))
                    messages.push(@json($errors->first('content')));
                @endif
                if (messages.length > 0) {
                    showMessage(messages.join('，'), 'error');
                }
            })();
        @endif

        let cmsAttachments = [];
        const emojiPickerCatalog = {
            recent: { label: '最近使用', items: [] },
            smile: {
                label: '常用笑脸',
                items: [
                    { emoji: '😀', name: '开心' }, { emoji: '😁', name: '大笑' }, { emoji: '😂', name: '笑哭' }, { emoji: '😉', name: '眨眼' },
                    { emoji: '😊', name: '微笑' }, { emoji: '🙂', name: '轻笑' }, { emoji: '😄', name: '欢笑' }, { emoji: '😆', name: '超开心' },
                    { emoji: '😌', name: '放松' }, { emoji: '😍', name: '喜欢' }, { emoji: '🤩', name: '惊喜' }, { emoji: '🥳', name: '庆祝' },
                    { emoji: '😎', name: '酷' }, { emoji: '🥰', name: '甜蜜' }, { emoji: '😇', name: '祝福' }, { emoji: '🤗', name: '拥抱' },
                    { emoji: '🤭', name: '偷笑' }, { emoji: '🤔', name: '思考' }, { emoji: '😴', name: '睡觉' }, { emoji: '🥹', name: '感动' },
                    { emoji: '😭', name: '大哭' }, { emoji: '😡', name: '生气' }, { emoji: '😮', name: '惊讶' }, { emoji: '🫶', name: '比心' },
                    { emoji: '😺', name: '猫咪开心' }, { emoji: '🤍', name: '白心' }, { emoji: '💖', name: '粉心' }, { emoji: '🩵', name: '浅蓝心' }
                ]
            },
            gesture: {
                label: '互动手势',
                items: [
                    { emoji: '👍', name: '点赞' }, { emoji: '👎', name: '点踩' }, { emoji: '👏', name: '鼓掌' }, { emoji: '🙌', name: '欢呼' },
                    { emoji: '👋', name: '招手' }, { emoji: '🤝', name: '握手' }, { emoji: '🙏', name: '感谢' }, { emoji: '✌️', name: '胜利' },
                    { emoji: '🤞', name: '好运' }, { emoji: '👌', name: '可以' }, { emoji: '👉', name: '指向' }, { emoji: '👈', name: '返回' },
                    { emoji: '👇', name: '下看' }, { emoji: '☝️', name: '提醒' }, { emoji: '💪', name: '加油' }, { emoji: '🫡', name: '致意' }
                ]
            },
            life: {
                label: '生活氛围',
                items: [
                    { emoji: '🎉', name: '礼花' }, { emoji: '🎈', name: '气球' }, { emoji: '🎁', name: '礼物' }, { emoji: '🎵', name: '音乐' },
                    { emoji: '📚', name: '书本' }, { emoji: '🧡', name: '橙心' }, { emoji: '☕', name: '咖啡' }, { emoji: '🍎', name: '苹果' },
                    { emoji: '🍉', name: '西瓜' }, { emoji: '🏆', name: '奖杯' }, { emoji: '🎓', name: '学位帽' }, { emoji: '🖼️', name: '图片' }
                ]
            },
            nature: {
                label: '自然氛围',
                items: [
                    { emoji: '🌱', name: '幼苗' }, { emoji: '🌿', name: '绿叶' }, { emoji: '🍃', name: '微风' }, { emoji: '🌸', name: '花朵' },
                    { emoji: '🌷', name: '郁金香' }, { emoji: '🌻', name: '向日葵' }, { emoji: '🍀', name: '四叶草' }, { emoji: '🌈', name: '彩虹' },
                    { emoji: '☀️', name: '太阳' }, { emoji: '🌤️', name: '晴朗' }, { emoji: '⛅', name: '多云' }, { emoji: '🌧️', name: '小雨' },
                    { emoji: '❄️', name: '雪花' }, { emoji: '🌙', name: '月亮' }, { emoji: '⭐', name: '星星' }, { emoji: '✨', name: '闪光' },
                    { emoji: '🌊', name: '海浪' }, { emoji: '⛰️', name: '山峰' }, { emoji: '🌾', name: '麦穗' }, { emoji: '🍁', name: '枫叶' }
                ]
            },
            notice: {
                label: '提示强调',
                items: [
                    { emoji: '📢', name: '公告' }, { emoji: '📣', name: '通知' }, { emoji: '📌', name: '置顶' }, { emoji: '🔥', name: '热门' },
                    { emoji: '🎯', name: '重点' }, { emoji: '✅', name: '完成' }, { emoji: '⚠️', name: '提醒' }, { emoji: '❗', name: '强调' },
                    { emoji: '❓', name: '疑问' }, { emoji: '💡', name: '灵感' }, { emoji: '🆕', name: '全新' }, { emoji: '📎', name: '附件' },
                    { emoji: '🔔', name: '铃铛' }, { emoji: '📝', name: '记录' }, { emoji: '📍', name: '定位' }, { emoji: '🏷️', name: '标签' },
                    { emoji: '📅', name: '日程' }, { emoji: '📤', name: '发布' }, { emoji: '🛠️', name: '维护' }, { emoji: '🔒', name: '安全' }
                ]
            }
        };
        const attachmentLibraryWorkspaceAccess = @json($attachmentLibraryWorkspaceAccess);
        const attachmentDeleteUrlTemplate = @json(route('admin.attachments.destroy', ['attachment' => '__ATTACHMENT__']));
        const attachmentUsageUrlTemplate = @json(route('admin.attachments.usages', ['attachment' => '__ATTACHMENT__']));
        const bilibiliVideoResolveUrl = @json(route('admin.articles.resolve-bilibili'));
        let emojiPickerEditorId = null;
        let emojiPickerCategory = 'smile';
        let emojiPickerSearch = '';

        @include('admin.site.attachments._attachment_library_script')

        function emojiRecentKey() {
            return 'cms_recent_emojis';
        }

        function loadRecentEmojis() {
            try {
                return JSON.parse(window.localStorage.getItem(emojiRecentKey()) || '[]');
            } catch (error) {
                return [];
            }
        }

        function saveRecentEmoji(entry) {
            const recent = loadRecentEmojis().filter((item) => item.emoji !== entry.emoji);
            recent.unshift(entry);
            window.localStorage.setItem(emojiRecentKey(), JSON.stringify(recent.slice(0, 12)));
        }

        function getEmojiItems() {
            const category = emojiPickerCatalog[emojiPickerCategory] || emojiPickerCatalog.smile;
            const search = emojiPickerSearch.trim().toLowerCase();

            if (! search) {
                return category.items;
            }

            return Object.values(emojiPickerCatalog)
                .flatMap((group) => group.items)
                .filter((item, index, list) => list.findIndex((candidate) => candidate.emoji === item.emoji) === index)
                .filter((item) => `${item.emoji}${item.name}`.toLowerCase().includes(search));
        }

        function renderEmojiPicker() {
            const categoryContainer = document.getElementById('emoji-picker-categories');
            const grid = document.getElementById('emoji-picker-grid');
            const search = document.getElementById('emoji-picker-search');

            if (! categoryContainer || ! grid) {
                return;
            }

            if (search && search.value !== emojiPickerSearch) {
                search.value = emojiPickerSearch;
            }

            categoryContainer.innerHTML = Object.entries(emojiPickerCatalog)
                .filter(([key, group]) => key !== 'recent' || group.items.length)
                .map(([key, group]) => `
                    <button class="emoji-picker-category ${emojiPickerCategory === key ? 'is-active' : ''}" type="button" data-emoji-category="${key}">
                        ${group.label}
                    </button>
                `)
                .join('');

            const items = getEmojiItems();
            grid.innerHTML = items.length
                ? items.map((item) => `
                    <button class="emoji-picker-item" type="button" data-emoji="${item.emoji}" data-emoji-name="${item.name}">
                        <span class="emoji-picker-glyph">${item.emoji}</span>
                        <span class="emoji-picker-name">${item.name}</span>
                    </button>
                `).join('')
                : '<div class="emoji-picker-empty">没有找到匹配的表情，换个关键词试试。</div>';

            categoryContainer.querySelectorAll('[data-emoji-category]').forEach((button) => {
                button.addEventListener('click', () => {
                    emojiPickerCategory = button.dataset.emojiCategory || 'smile';
                    renderEmojiPicker();
                });
            });

            grid.querySelectorAll('[data-emoji]').forEach((button) => {
                button.addEventListener('click', () => {
                    const emoji = button.dataset.emoji || '';
                    const name = button.dataset.emojiName || '';
                    const editor = emojiPickerEditorId ? tinymce.get(emojiPickerEditorId) : null;

                    if (! editor || ! emoji) {
                        return;
                    }

                    editor.insertContent(emoji);
                    editor.save();
                    saveRecentEmoji({ emoji, name });
                    closeEmojiPicker();
                });
            });
        }

        function openEmojiPicker(editorId) {
            const modal = document.getElementById('emoji-picker-modal');
            const search = document.getElementById('emoji-picker-search');

            if (! modal) {
                return;
            }

            emojiPickerEditorId = editorId;
            emojiPickerCatalog.recent.items = loadRecentEmojis();
            emojiPickerCategory = emojiPickerCatalog.recent.items.length ? 'recent' : 'smile';
            emojiPickerSearch = '';
            modal.hidden = false;
            renderEmojiPicker();
            window.requestAnimationFrame(() => search?.focus());
        }

        function closeEmojiPicker() {
            const modal = document.getElementById('emoji-picker-modal');
            if (modal) {
                modal.hidden = true;
            }
        }

        function initializeEmojiPicker() {
            const modal = document.getElementById('emoji-picker-modal');
            const search = document.getElementById('emoji-picker-search');

            if (! modal || ! search) {
                return;
            }

            modal.querySelectorAll('[data-close-emoji-picker]').forEach((button) => {
                button.addEventListener('click', closeEmojiPicker);
            });

            search.addEventListener('input', () => {
                emojiPickerSearch = search.value;
                renderEmojiPicker();
            });
        }

        initializeEmojiPicker();

        let videoEmbedEditorId = 'content';
        let videoEmbedEditingNode = null;

        function setVideoEmbedError(message = '') {
            const error = document.getElementById('video-embed-error');

            if (! error) {
                return;
            }

            if (message) {
                error.textContent = message;
                error.hidden = false;
                return;
            }

            error.textContent = '';
            error.hidden = true;
        }

        function closeVideoEmbed() {
            const modal = document.getElementById('video-embed-modal');
            if (modal) {
                modal.hidden = true;
            }
            videoEmbedEditingNode = null;
            setVideoEmbedError('');
        }

        function syncSiteSelectValue(selectElement, value) {
            if (! selectElement) {
                return;
            }

            selectElement.value = value;
            Array.from(selectElement.options).forEach((option) => {
                option.selected = option.value === value;
            });

            const root = selectElement.closest('[data-site-select]');
            const trigger = root?.querySelector('[data-select-trigger]');
            const panel = root?.querySelector('[data-select-panel]');

            if (trigger) {
                const selectedOption = Array.from(selectElement.options).find((option) => option.value === value);
                trigger.textContent = selectedOption?.textContent || '';
            }

            if (panel) {
                panel.querySelectorAll('.site-select-option').forEach((optionButton) => {
                    optionButton.classList.toggle('is-active', optionButton.dataset.value === value);
                });
            }
        }

        function findVideoEmbedNode(node) {
            if (! node) {
                return null;
            }

            if (node.nodeType === Node.ELEMENT_NODE && node.matches?.('.bilibili-video-embed[data-bilibili-video="1"]')) {
                return node;
            }

            return node.closest?.('.bilibili-video-embed[data-bilibili-video="1"]') || null;
        }

        function clearSelectedVideoEmbed(editor) {
            const body = editor?.getBody?.();
            if (! body) {
                return;
            }

            body.querySelectorAll('.bilibili-video-embed.is-selected').forEach((node) => {
                node.classList.remove('is-selected');
            });
        }

        function selectVideoEmbedNode(editor, node) {
            if (! editor || ! node) {
                return;
            }

            clearSelectedVideoEmbed(editor);
            node.classList.add('is-selected');
            editor.selection.select(node);
            editor.focus();
        }

        function buildBilibiliEmbedHtml(resolved, width, height, align) {
            const alignLabel = ({
                left: '居左',
                center: '居中',
                right: '居右',
            })[align] || '居中';

            return `
                <div class="bilibili-video-embed mceNonEditable" data-bilibili-video="1" data-aid="${resolved.aid}" data-bvid="${resolved.bvid}" data-cid="${resolved.cid}" data-p="${resolved.page}" data-width="${width}" data-height="${height}" data-align="${align}">
                    <div class="bilibili-video-embed__title">哔哩哔哩视频</div>
                    <div class="bilibili-video-embed__meta">${resolved.bvid} · ${width} × ${height} · ${alignLabel}</div>
                </div>
            `;
        }

        function openVideoEmbed(editorId = 'content', existingNode = null) {
            const modal = document.getElementById('video-embed-modal');
            const urlInput = document.getElementById('video-embed-url');
            const widthInput = document.getElementById('video-embed-width');
            const heightInput = document.getElementById('video-embed-height');
            const alignInput = document.getElementById('video-embed-align');
            const title = document.getElementById('video-embed-title');
            const confirmButton = document.getElementById('video-embed-confirm');

            if (! modal) {
                return;
            }

            videoEmbedEditorId = editorId;
            videoEmbedEditingNode = existingNode;
            setVideoEmbedError('');

            if (existingNode) {
                if (urlInput) {
                    urlInput.value = `https://www.bilibili.com/video/${existingNode.getAttribute('data-bvid') || ''}/`;
                }
                if (widthInput) {
                    widthInput.value = (existingNode.getAttribute('data-width') || '80%').trim();
                }
                if (heightInput) {
                    heightInput.value = (existingNode.getAttribute('data-height') || '450px').replace(/px$/i, '').trim();
                }
                syncSiteSelectValue(alignInput, (existingNode.getAttribute('data-align') || 'center').trim() || 'center');
                if (title) {
                    title.textContent = '编辑视频';
                }
                if (confirmButton) {
                    confirmButton.textContent = '保存视频';
                }
            } else {
                if (urlInput) {
                    urlInput.value = '';
                }
                if (widthInput) {
                    widthInput.value = '90%';
                }
                if (heightInput) {
                    heightInput.value = '500';
                }
                syncSiteSelectValue(alignInput, 'center');
                if (title) {
                    title.textContent = '插入视频';
                }
                if (confirmButton) {
                    confirmButton.textContent = '插入视频';
                }
            }

            if (urlInput && ! existingNode) {
                urlInput.value = '';
            }
            modal.hidden = false;
            window.requestAnimationFrame(() => urlInput?.focus());
        }

        function normalizeVideoWidth(value) {
            const raw = String(value || '').trim();

            if (! raw) {
                return '90%';
            }

            if (/^\d+$/.test(raw)) {
                return `${raw}px`;
            }

            if (/^\d+(px|%|vw|rem|em)$/i.test(raw)) {
                return raw;
            }

            throw new Error('视频宽度只支持数字、px、%、vw、rem、em。');
        }

        function normalizeVideoHeight(value) {
            const raw = String(value || '').trim();

            if (! raw) {
                return '500px';
            }

            if (/^\d+$/.test(raw)) {
                return `${raw}px`;
            }

            if (/^\d+(px|vh|rem|em)$/i.test(raw)) {
                return raw;
            }

            throw new Error('视频高度只支持数字、px、vh、rem、em。');
        }

        async function resolveBilibiliVideoUrl(rawUrl) {
            const urlText = String(rawUrl || '').trim();

            if (! urlText) {
                throw new Error('请先输入哔哩哔哩视频地址。');
            }

            const response = await fetch(bilibiliVideoResolveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ url: urlText }),
            });

            const json = await response.json().catch(() => ({}));

            if (! response.ok || ! json.embed_url || !json.aid || !json.bvid || !json.cid) {
                throw new Error(json.message || '视频解析失败，请确认哔哩哔哩地址可访问。');
            }

            return json;
        }

        async function insertBilibiliVideo() {
            const editor = videoEmbedEditorId ? tinymce.get(videoEmbedEditorId) : null;
            const urlInput = document.getElementById('video-embed-url');
            const widthInput = document.getElementById('video-embed-width');
            const heightInput = document.getElementById('video-embed-height');
            const alignInput = document.getElementById('video-embed-align');

            if (! editor || ! urlInput || ! widthInput || ! heightInput || ! alignInput) {
                return;
            }

            try {
                const resolved = await resolveBilibiliVideoUrl(urlInput.value);
                const width = normalizeVideoWidth(widthInput.value);
                const height = normalizeVideoHeight(heightInput.value);
                const align = alignInput.value || 'center';
                const embedHtml = buildBilibiliEmbedHtml(resolved, width, height, align);

                if (videoEmbedEditingNode) {
                    editor.dom.setOuterHTML(videoEmbedEditingNode, embedHtml);
                    const nodes = editor.getBody().querySelectorAll('.bilibili-video-embed[data-bilibili-video="1"]');
                    const latestNode = nodes[nodes.length - 1];
                    if (latestNode) {
                        selectVideoEmbedNode(editor, latestNode);
                    }
                } else {
                    editor.insertContent(embedHtml);
                }
                editor.save();
                editor.focus();
                closeVideoEmbed();
            } catch (error) {
                setVideoEmbedError(error instanceof Error ? error.message : '视频插入失败，请检查链接格式。');
            }
        }

        function initializeVideoEmbed() {
            const modal = document.getElementById('video-embed-modal');
            const confirmButton = document.getElementById('video-embed-confirm');
            const urlInput = document.getElementById('video-embed-url');

            if (! modal || ! confirmButton) {
                return;
            }

            modal.querySelectorAll('[data-close-video-embed]').forEach((button) => {
                button.addEventListener('click', closeVideoEmbed);
            });

            confirmButton.addEventListener('click', insertBilibiliVideo);
            urlInput?.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    insertBilibiliVideo();
                }
            });
        }

        initializeVideoEmbed();

        (() => {
            document.querySelectorAll('[data-content-channel-select]').forEach((selectRoot) => {
                const trigger = selectRoot.querySelector('[data-content-channel-trigger]');
                const panel = selectRoot.querySelector('[data-content-channel-panel]');
                const searchWrap = selectRoot.querySelector('[data-channel-search]');
                const searchInput = selectRoot.querySelector('[data-channel-search-input]');
                const clearButton = selectRoot.querySelector('[data-channel-search-clear]');
                const options = Array.from(selectRoot.querySelectorAll('[data-channel-option]'));
                const checkboxes = Array.from(selectRoot.querySelectorAll('[data-channel-checkbox]'));

                if (!trigger || !panel || checkboxes.length === 0) {
                    return;
                }

                const updateSummary = () => {
                    const selected = checkboxes.filter((checkbox) => checkbox.checked);

                    if (selected.length === 0) {
                        trigger.textContent = '请选择栏目';
                        return;
                    }

                    const previewNames = selected
                        .slice(0, 5)
                        .map((checkbox) => checkbox.dataset.channelName || '')
                        .filter(Boolean);

                    if (selected.length <= 5) {
                        trigger.textContent = previewNames.join('、');
                        return;
                    }

                    trigger.textContent = `${previewNames.join('、')} 等${selected.length}个栏目`;
                };

                const closePanel = () => {
                    selectRoot.classList.remove('is-open');
                    trigger.setAttribute('aria-expanded', 'false');
                };

                const filterOptions = () => {
                    const keyword = String(searchInput?.value || '').trim().toLowerCase();
                    const visibleOptions = new Set();

                    searchWrap?.classList.toggle('has-value', keyword !== '');

                    if (keyword === '') {
                        options.forEach((option) => {
                            option.classList.remove('is-hidden');
                        });
                        return;
                    }

                    options.forEach((option, index) => {
                        const haystack = [
                            String(option.dataset.channelKeyword || ''),
                            String(option.textContent || ''),
                        ].join(' ').toLowerCase();

                        if (!haystack.includes(keyword)) {
                            return;
                        }

                        visibleOptions.add(option);

                        const currentDepth = Number(option.dataset.depth || '0');
                        if (currentDepth <= 0) {
                            return;
                        }

                        for (let cursor = index - 1, depth = currentDepth; cursor >= 0 && depth > 0; cursor -= 1) {
                            const candidate = options[cursor];
                            const candidateDepth = Number(candidate.dataset.depth || '0');

                            if (candidateDepth === depth - 1) {
                                visibleOptions.add(candidate);
                                depth = candidateDepth;
                            }
                        }
                    });

                    options.forEach((option) => {
                        option.classList.toggle('is-hidden', !visibleOptions.has(option));
                    });
                };

                trigger.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const nextState = !selectRoot.classList.contains('is-open');

                    document.querySelectorAll('[data-content-channel-select].is-open').forEach((openRoot) => {
                        openRoot.classList.remove('is-open');
                        openRoot.querySelector('[data-content-channel-trigger]')?.setAttribute('aria-expanded', 'false');
                    });

                    if (nextState) {
                        selectRoot.classList.add('is-open');
                        trigger.setAttribute('aria-expanded', 'true');
                        searchInput?.focus();
                    }
                });

                checkboxes.forEach((checkbox) => {
                    checkbox.addEventListener('change', updateSummary);
                });

                searchInput?.addEventListener('input', filterOptions);
                searchInput?.addEventListener('change', filterOptions);
                searchInput?.addEventListener('keyup', filterOptions);
                clearButton?.addEventListener('click', () => {
                    if (!searchInput) {
                        return;
                    }

                    searchInput.value = '';
                    filterOptions();
                    searchInput.focus();
                });

                document.addEventListener('click', (event) => {
                    if (!selectRoot.contains(event.target)) {
                        closePanel();
                    }
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && selectRoot.classList.contains('is-open')) {
                        closePanel();
                    }
                });

                filterOptions();
                updateSummary();
            });
        })();

        (() => {
            document.querySelectorAll('[data-datetime-trigger]').forEach((trigger) => {
                const wrapper = trigger.closest('.content-datetime-field');
                const input = wrapper?.querySelector('.content-datetime-input');

                if (!input) {
                    return;
                }

                const openPicker = () => {
                    if (typeof input.showPicker === 'function') {
                        input.showPicker();
                        return;
                    }

                    input.focus();
                    input.click();
                };

                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    openPicker();
                });

                wrapper?.addEventListener('click', (event) => {
                    if (event.target === input || event.target === trigger) {
                        return;
                    }

                    openPicker();
                });
            });
        })();

        function attachResourceLibraryMenubarButton(editor) {
            return editor;
        }

        function looksLikeArticleFooterDate(text) {
            return /^(\d{4})[年\-\/\.](\d{1,2})[月\-\/\.](\d{1,2})日?$/.test(text);
        }

        function looksLikeArticleFooterMeta(text) {
            if (text.length < 4 || text.length > 36) {
                return false;
            }

            return /(图、文|图文|编辑|记者|来源|摄影|撰稿|审核)/.test(text);
        }

        function splitMixedMediaParagraphs(root) {
            const mediaSelector = 'img, table, iframe, video, figure, .bilibili-video-embed';

            root.querySelectorAll('p').forEach((node) => {
                const mediaNodes = Array.from(node.querySelectorAll(':scope > img, :scope > table, :scope > iframe, :scope > video, :scope > figure, :scope > .bilibili-video-embed'));
                const text = (node.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();

                if (mediaNodes.length === 0 || text === '') {
                    return;
                }

                mediaNodes.forEach((mediaNode) => {
                    const wrapper = document.createElement('p');
                    wrapper.appendChild(mediaNode.cloneNode(true));
                    node.parentNode?.insertBefore(wrapper, node.nextSibling);
                    mediaNode.remove();
                });
            });

            root.querySelectorAll('p').forEach((node) => {
                if ((node.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim() === '' && ! node.querySelector(mediaSelector)) {
                    node.remove();
                }
            });
        }

        function normalizeArticleFooter(root) {
            const paragraphs = Array.from(root.querySelectorAll('p')).filter((node) => {
                return ! node.querySelector('img, table, iframe, video, figure, .bilibili-video-embed');
            });

            const tailParagraphs = paragraphs.slice(-4);

            tailParagraphs.forEach((node, index) => {
                const text = (node.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();

                if (! text) {
                    return;
                }

                const isDateLine = looksLikeArticleFooterDate(text);
                const isMetaLine = looksLikeArticleFooterMeta(text);

                if (! isDateLine && ! isMetaLine) {
                    return;
                }

                node.style.textIndent = '0';
                node.style.textAlign = 'right';
                node.style.color = isDateLine ? '#64748b' : '#475569';
                node.style.fontSize = isDateLine ? '13px' : '14px';
                node.style.lineHeight = '1.8';
                node.style.letterSpacing = '0.01em';
                node.style.margin = index === 0 ? '1.8em 0 0.35em auto' : '0.35em 0 0 auto';
                node.style.maxWidth = '100%';
            });
        }

        function collapseMediaSpacing(root) {
            root.querySelectorAll('p').forEach((node) => {
                const hasMediaContent = Boolean(node.querySelector('img, table, iframe, video, figure, .bilibili-video-embed'));

                if (! hasMediaContent) {
                    return;
                }

                const previous = node.previousElementSibling;

                if (! previous || previous.tagName?.toLowerCase() !== 'p') {
                    return;
                }

                const previousText = (previous.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
                const previousHasMedia = Boolean(previous.querySelector('img, table, iframe, video, figure, .bilibili-video-embed'));

                if (! previousHasMedia && previousText === '') {
                    previous.remove();
                    return;
                }

                if (! previousHasMedia && previousText !== '') {
                    previous.style.marginBottom = '0.55em';
                }
            });
        }

        function tightenMediaTopSpacing(root) {
            root.querySelectorAll('p').forEach((node) => {
                const mediaNode = node.querySelector(':scope > img, :scope > figure, :scope > .bilibili-video-embed, :scope > table, :scope > iframe, :scope > video');

                if (! mediaNode) {
                    return;
                }

                const previous = node.previousElementSibling;
                const previousTag = previous?.tagName?.toLowerCase() || '';
                const previousText = previous ? (previous.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim() : '';
                const previousHasMedia = previous ? Boolean(previous.querySelector('img, table, iframe, video, figure, .bilibili-video-embed')) : false;

                if (previous && previousTag === 'p' && previousText !== '' && ! previousHasMedia) {
                    if (mediaNode.matches('img')) {
                        mediaNode.style.marginTop = '1em';
                    }

                    if (mediaNode.matches('figure')) {
                        mediaNode.style.marginTop = '1em';
                    }

                    if (mediaNode.matches('.bilibili-video-embed')) {
                        mediaNode.style.marginTop = '0.9em';
                    }
                }
            });
        }

        function normalizeLeadingParagraphIndent(root) {
            const paragraphs = Array.from(root.querySelectorAll('p')).filter((node) => {
                const text = (node.textContent || '').replace(/\u00a0/g, ' ').trim();
                const hasMediaContent = Boolean(node.querySelector('img, table, iframe, video, figure, .bilibili-video-embed'));

                return text !== '' && ! hasMediaContent;
            });

            const firstParagraph = paragraphs[0];

            if (! firstParagraph) {
                return;
            }

            firstParagraph.style.textIndent = '2em';
        }

        function normalizeArticleTypography(root) {
            splitMixedMediaParagraphs(root);

            root.querySelectorAll('p, li, td, th, figcaption, blockquote').forEach((node) => {
                node.style.fontSize = '14px';
                node.style.lineHeight = '1.95';
            });

            root.querySelectorAll('p').forEach((node) => {
                const text = (node.textContent || '').replace(/\u00a0/g, ' ').trim();
                const hasMediaContent = Boolean(node.querySelector('img, table, iframe, video, figure, .bilibili-video-embed'));

                node.style.margin = '0 0 1em';

                if (hasMediaContent) {
                    Array.from(node.childNodes).forEach((child) => {
                        if (child.nodeType === Node.TEXT_NODE && (child.textContent || '').replace(/\u00a0/g, ' ').trim() === '') {
                            child.remove();
                        }
                    });

                    node.style.textIndent = '0';
                    node.style.display = 'flow-root';
                    node.style.margin = '0';
                    node.style.padding = '18px 0 22px';
                    node.style.fontSize = '0';
                    node.style.lineHeight = '0';
                    node.style.letterSpacing = '0';
                    return;
                }

                if (text === '') {
                    node.remove();
                    return;
                }

                node.style.textIndent = '2em';
                node.style.color = '#1f2937';
                node.style.letterSpacing = '0.01em';
            });

            root.querySelectorAll('h1, h2, h3, h4').forEach((node) => {
                const tag = node.tagName.toLowerCase();
                const fontSizes = {
                    h1: '24px',
                    h2: '20px',
                    h3: '17px',
                    h4: '15px',
                };

                node.style.fontSize = fontSizes[tag] || '17px';
                node.style.lineHeight = '1.65';
                node.style.fontWeight = '700';
                node.style.margin = '1.6em 0 0.8em';
                node.style.color = '#111827';
                node.style.letterSpacing = '0.01em';
            });

            root.querySelectorAll('ul, ol').forEach((node) => {
                node.style.margin = '0 0 1.1em 1.35em';
                node.style.padding = '0';
            });

            root.querySelectorAll('li').forEach((node) => {
                node.style.marginBottom = '0.45em';
                node.style.color = '#1f2937';
            });

            root.querySelectorAll('blockquote').forEach((node) => {
                node.style.margin = '1.2em 0';
                node.style.padding = '0.9em 1.1em';
                node.style.borderLeft = '4px solid #93c5fd';
                node.style.background = '#f8fbff';
                node.style.color = '#334155';
                node.style.textIndent = '0';
                node.style.borderRadius = '0 14px 14px 0';
            });

            root.querySelectorAll('table').forEach((node) => {
                node.style.width = '100%';
                node.style.borderCollapse = 'collapse';
                node.style.margin = '1.2em 0';
                node.style.overflow = 'hidden';
            });

            root.querySelectorAll('table td, table th').forEach((node) => {
                node.style.border = '1px solid #e5e7eb';
                node.style.padding = '10px 12px';
                node.style.textIndent = '0';
            });

            root.querySelectorAll('table th').forEach((node) => {
                node.style.background = '#f8fafc';
                node.style.fontWeight = '700';
                node.style.color = '#0f172a';
            });

            root.querySelectorAll('img').forEach((node) => {
                const hasExplicitWidth = Boolean((node.style.width || '').trim()) || node.hasAttribute('width');

                node.style.display = 'block';
                if (!hasExplicitWidth) {
                    node.style.width = '80%';
                }
                node.style.maxWidth = '100%';
                node.style.height = 'auto';
                node.style.margin = '0 auto';
                node.style.borderRadius = '14px';
            });

            root.querySelectorAll('figure').forEach((node) => {
                node.style.display = 'flow-root';
                node.style.margin = '0';
                node.style.padding = '18px 0 22px';
                node.style.textAlign = 'center';
            });

            root.querySelectorAll('figcaption').forEach((node) => {
                node.style.marginTop = '0.7em';
                node.style.color = '#64748b';
                node.style.textIndent = '0';
            });

            root.querySelectorAll('span').forEach((node) => {
                const style = node.getAttribute('style') || '';
                const normalizedStyle = style
                    .replace(/font-size\s*:\s*[^;]+;?/gi, '')
                    .replace(/line-height\s*:\s*[^;]+;?/gi, '')
                    .trim();

                if (normalizedStyle === '') {
                    node.removeAttribute('style');
                } else {
                    node.setAttribute('style', normalizedStyle);
                }
            });

            normalizeArticleFooter(root);
            collapseMediaSpacing(root);
            normalizeLeadingParagraphIndent(root);
            tightenMediaTopSpacing(root);
        }

        function applySmartTypesetting(editor) {
            const rawHtml = editor.getContent({ format: 'html' }).trim();

            if (rawHtml === '') {
                editor.notificationManager.open({
                    text: '请先输入文章内容，再使用一键排版。',
                    type: 'warning',
                    timeout: 2200,
                });
                return;
            }

            const parser = new DOMParser();
            const documentFragment = parser.parseFromString(`<div data-typesetting-root>${rawHtml}</div>`, 'text/html');
            const root = documentFragment.body.querySelector('[data-typesetting-root]');

            if (! root) {
                return;
            }

            normalizeArticleTypography(root);

            editor.undoManager.transact(() => {
                editor.setContent(root.innerHTML);
                editor.save();
            });

            showArticleTypesettingToast();
        }

        function showArticleTypesettingToast() {
            document.querySelectorAll('.article-typesetting-toast').forEach((node) => node.remove());

            const toast = document.createElement('div');
            toast.className = 'article-typesetting-toast';
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            toast.innerHTML = `
                <span class="article-typesetting-toast__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="m12 3 1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z"></path>
                        <path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z"></path>
                    </svg>
                </span>
                <span class="article-typesetting-toast__body">
                    <strong class="article-typesetting-toast__title">排版已优化完成</strong>
                    <span class="article-typesetting-toast__text">正文已统一为 14px，段落、列表和表格也一起整理好了。</span>
                </span>
            `;

            document.body.appendChild(toast);
            requestAnimationFrame(() => toast.classList.add('is-visible'));

            window.setTimeout(() => {
                toast.classList.remove('is-visible');
                window.setTimeout(() => toast.remove(), 220);
            }, 2800);
        }

        function clearEditorFormatting(editor) {
            const body = editor?.getBody?.();
            const embeds = body
                ? Array.from(body.querySelectorAll('.bilibili-video-embed[data-bilibili-video="1"]'))
                : [];

            if (embeds.length === 0) {
                editor.execCommand('RemoveFormat');
                editor.save();
                return;
            }

            const placeholders = embeds.map((node, index) => {
                const token = `__CMS_BILIBILI_EMBED_${Date.now()}_${index}__`;
                const textNode = editor.getDoc().createTextNode(token);

                node.replaceWith(textNode);

                return {
                    token,
                    html: node.outerHTML,
                };
            });

            editor.execCommand('RemoveFormat');

            let restoredHtml = editor.getContent({ format: 'html' });
            placeholders.forEach(({ token, html }) => {
                restoredHtml = restoredHtml.replace(token, html);
            });

            const parser = new DOMParser();
            const documentFragment = parser.parseFromString(`<div data-clear-format-root>${restoredHtml}</div>`, 'text/html');
            const root = documentFragment.body.querySelector('[data-clear-format-root]');

            const isEmptyParagraph = (element) => {
                if (!element || element.tagName?.toLowerCase() !== 'p') {
                    return false;
                }

                return (element.innerHTML || '')
                    .replace(/&nbsp;/gi, ' ')
                    .replace(/<br\s*\/?>/gi, ' ')
                    .replace(/\s+/g, '')
                    === '';
            };

            root?.querySelectorAll('.bilibili-video-embed[data-bilibili-video="1"]').forEach((node) => {
                while (isEmptyParagraph(node.previousElementSibling)) {
                    node.previousElementSibling.remove();
                }

                while (isEmptyParagraph(node.nextElementSibling)) {
                    node.nextElementSibling.remove();
                }
            });

            restoredHtml = root?.innerHTML || restoredHtml;

            editor.undoManager.transact(() => {
                editor.setContent(restoredHtml);
                editor.save();
            });
        }

        tinymce.init({
            selector: 'textarea.rich-editor',
            height: 520,
            language: 'zh_CN',
            language_url: '/vendor/tinymce/langs/zh_CN.js',
            toolbar_mode: 'wrap',
            menubar: false,
            branding: false,
            promotion: false,
            license_key: 'gpl',
            entity_encoding: 'raw',
            convert_urls: false,
            relative_urls: false,
            images_upload_url: '{{ route('admin.attachments.image-upload') }}',
            automatic_uploads: true,
            images_reuse_filename: false,
            plugins: 'autolink anchor code codesample fullscreen image link lists media noneditable searchreplace table visualblocks wordcount',
            noneditable_class: 'mceNonEditable',
            content_style: 'body { font-family: PingFang SC, Microsoft YaHei, sans-serif; font-size: 15px; line-height: 1.85; } .bilibili-video-embed { width: fit-content; max-width: 100%; margin: 20px auto; padding: 16px 18px; border: 1px solid #e5e7eb; border-radius: 16px; background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%); color: #334155; text-align: center; cursor: pointer; transition: box-shadow .18s ease, border-color .18s ease, transform .18s ease; } .bilibili-video-embed:hover { border-color: #94a3b8; box-shadow: 0 0 0 2px rgba(0, 71, 171, 0.08); } .bilibili-video-embed.is-selected { border-color: #0047AB; box-shadow: 0 0 0 2px rgba(0, 71, 171, 0.18); } .bilibili-video-embed__title { font-size: 14px; font-weight: 700; color: #1e3a8a; } .bilibili-video-embed__meta { margin-top: 6px; font-size: 12px; color: #64748b; }',
            font_family_formats: '默认字体=PingFang SC,Microsoft YaHei,sans-serif;宋体=SimSun,STSong,serif;黑体=SimHei,Heiti SC,sans-serif;楷体=KaiTi,Kaiti SC,serif;仿宋=FangSong,STFangsong,serif;Arial=Arial,Helvetica,sans-serif;Times New Roman=Times New Roman,Times,serif;Courier New=Courier New,Courier,monospace',
            setup(editor) {
                editor.ui.registry.addButton('linkCn', { icon: 'link', tooltip: '插入链接', onAction: () => editor.execCommand('mceLink') });
                editor.ui.registry.addButton('mediaCn', { text: '媒体', tooltip: '插入媒体', onAction: () => editor.execCommand('mceMedia') });
                editor.ui.registry.addButton('schoolVideoEmbed', {
                    text: '视频',
                    tooltip: '插入哔哩哔哩视频',
                    onAction: () => openVideoEmbed(editor.id),
                });
                editor.ui.registry.addButton('quoteCn', { icon: 'quote', tooltip: '引用', onAction: () => editor.execCommand('mceBlockQuote') });
                editor.ui.registry.addButton('codeSampleCn', { icon: 'sourcecode', tooltip: '代码演示', onAction: () => editor.execCommand('Codesample') });
                editor.ui.registry.addButton('codeCn', { icon: 'code-sample', tooltip: '内容源码', onAction: () => editor.execCommand('mceCodeEditor') });
                editor.ui.registry.addButton('clearCn', { text: '清', tooltip: '清除格式', onAction: () => clearEditorFormatting(editor) });
                editor.ui.registry.addButton('smartArticleFormat', {
                    text: '排版',
                    tooltip: '一键排版',
                    onAction: () => applySmartTypesetting(editor),
                });
                editor.ui.registry.addToggleButton('visualBlocksCn', {
                    text: '显示块',
                    tooltip: '显示块',
                    onAction: () => editor.execCommand('mceVisualBlocks'),
                    onSetup: (api) => editor.formatter.formatChanged('visualblocks', (state) => api.setActive(state)),
                });
                editor.ui.registry.addIcon('school-library', '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M4 6.25A2.25 2.25 0 0 1 6.25 4h3.43l1.6 1.75h6.47A2.25 2.25 0 0 1 20 8v8.75A2.25 2.25 0 0 1 17.75 19h-11.5A2.25 2.25 0 0 1 4 16.75V6.25Zm2.25-.75a.75.75 0 0 0-.75.75v.5h12.5V8a.75.75 0 0 0-.75-.75h-6.88l-1.6-1.75H6.25Zm11.25 3H5.75v8.25c0 .41.34.75.75.75h10.99a.75.75 0 0 0 .75-.75V8.5ZM8.2 15.6l1.95-2.28 1.45 1.62 2.28-2.72L16.8 15.6H8.2Z"/></svg>');
                editor.ui.registry.addButton('schoolResourceLibrary', {
                    icon: 'school-library',
                    text: '资源库',
                    tooltip: '打开资源库',
                    onAction: () => window.openSiteAttachmentLibrary?.({
                        editorId: editor.id,
                        mode: 'editor',
                        context: 'content',
                    }),
                });
                editor.ui.registry.addButton('schoolEmojiPicker', {
                    text: '表情',
                    tooltip: '插入表情',
                    onAction: () => openEmojiPicker(editor.id),
                });
                editor.ui.registry.addButton('schoolFullscreen', {
                    text: '全屏',
                    tooltip: '全屏编辑',
                    onAction: () => editor.execCommand('mceFullScreen'),
                });
                editor.on('init', () => {
                    attachResourceLibraryMenubarButton(editor);
                    window.setTimeout(() => attachResourceLibraryMenubarButton(editor), 120);
                    window.setTimeout(() => attachResourceLibraryMenubarButton(editor), 320);
                    document.dispatchEvent(new CustomEvent('tinymce-editor-ready', { detail: { id: editor.id } }));
                });
                editor.on('click', (event) => {
                    const node = findVideoEmbedNode(event.target);

                    if (node) {
                        selectVideoEmbedNode(editor, node);
                        return;
                    }

                    clearSelectedVideoEmbed(editor);
                });
                editor.on('contextmenu', (event) => {
                    const node = findVideoEmbedNode(event.target);

                    if (! node) {
                        return;
                    }

                    event.preventDefault();
                    selectVideoEmbedNode(editor, node);
                    openVideoEmbed(editor.id, node);
                });
                editor.on('keydown', (event) => {
                    const node = findVideoEmbedNode(editor.selection.getNode());

                    if (! node || ! ['Backspace', 'Delete'].includes(event.key)) {
                        return;
                    }

                    event.preventDefault();
                    node.remove();
                    clearSelectedVideoEmbed(editor);
                    editor.save();
                });
                editor.on('change input undo redo', () => editor.save());
            },
            toolbar: 'undo redo fontfamily fontsize | bold italic underline forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent table visualblocks quoteCn linkCn codeSampleCn codeCn clearCn schoolVideoEmbed schoolEmojiPicker smartArticleFormat schoolResourceLibrary schoolFullscreen',
            images_upload_handler: (blobInfo, progress) => new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '{{ route('admin.attachments.image-upload') }}');
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) {
                        progress(e.loaded / e.total * 100);
                    }
                };

                xhr.onload = () => {
                    if (xhr.status < 200 || xhr.status >= 300) {
                        reject(`图片上传失败（${xhr.status}）`);
                        return;
                    }

                    const json = JSON.parse(xhr.responseText || '{}');

                    if (!json.location) {
                        reject('图片上传失败，返回数据不完整');
                        return;
                    }

                    resolve(json.location);
                };

                xhr.onerror = () => reject('图片上传失败');

                const formData = new FormData();
                formData.append('file', blobInfo.blob(), blobInfo.filename());
                xhr.send(formData);
            })
        });
    </script>
@endpush
