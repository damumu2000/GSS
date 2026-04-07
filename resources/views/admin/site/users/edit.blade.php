@extends('layouts.admin')

@section('title', '编辑操作员 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 操作员管理 / 编辑操作员')

@php
    $selectedRoleValue = old('role_id', $selectedRoleId ? 'site:' . $selectedRoleId : '');
    $selectedStatusValue = (string) old('status', (string) $user->status);
    $selectedRoleName = collect($siteRoles)->firstWhere('id', (int) $selectedRoleId)?->name ?? '未分配';
    $statusLabel = $selectedStatusValue === '0' ? '停用' : '启用中';
    $selectedRoleCanManageContent = (bool) collect($siteRoles)->firstWhere('id', (int) $selectedRoleId)?->can_manage_content;
    $selectedManagedChannels = collect($channels)
        ->filter(fn ($channel) => in_array((int) $channel->id, $selectedChannelIds, true))
        ->values();
    $displayName = trim((string) old('name', $user->name)) ?: '未设置姓名';
    $displayUsername = trim((string) old('username', $user->username)) ?: '未设置账号';
    $avatarSeed = trim((string) old('name', $user->name)) ?: trim((string) old('username', $user->username)) ?: '账';
    $avatarInitial = function_exists('mb_substr') ? mb_substr($avatarSeed, 0, 1) : substr($avatarSeed, 0, 1);
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

        .site-user-shell {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow: visible;
        }

        .site-user-body {
            padding: 24px 28px 28px;
        }

        .site-user-layout-grid {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
            gap: 20px;
            align-items: start;
        }

        .site-user-column {
            display: grid;
            gap: 18px;
            min-width: 0;
        }

        .site-user-module {
            border: 1px solid #f0f0f0;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow: visible;
        }

        .site-user-module-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            background: #ffffff;
            border-bottom: 1px solid #f0f0f0;
        }

        .site-user-module-accent {
            width: 4px;
            height: 16px;
            border-radius: 999px;
            background: var(--primary);
            flex-shrink: 0;
        }

        .site-user-module-title {
            color: #262626;
            font-size: 15px;
            line-height: 1.5;
            font-weight: 700;
        }

        .site-user-module-body {
            display: grid;
            gap: 18px;
            padding: 18px;
        }

        .site-user-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .field-group {
            display: grid;
            gap: 6px;
        }

        .field-label {
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 600;
        }

        .field-note {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.7;
        }

        .field-meta {
            min-height: 52px;
            display: grid;
            align-content: start;
            gap: 6px;
        }

        .form-error {
            display: none;
        }

        .field.is-readonly {
            background: #f9fafb;
            color: #6b7280;
            cursor: not-allowed;
        }

        .site-user-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 999px;
            background: var(--tag-bg);
            color: var(--tag-text);
            font-size: 14px;
            line-height: 1.2;
            font-weight: 700;
        }

        .site-user-status-badge.is-offline {
            background: rgba(255, 77, 79, 0.08);
            color: #cf1322;
        }

        .site-user-status-divider {
            border-top: 1px dashed #f0f0f0;
        }

        .site-user-status-meta {
            display: grid;
            gap: 12px;
        }

        .site-user-status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .site-user-status-label {
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.5;
        }

        .site-user-status-value {
            color: #262626;
            font-size: 14px;
            line-height: 1.5;
            font-weight: 700;
            text-align: right;
        }

        .role-choice-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .status-choice-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: center;
        }

        .site-user-status-layout {
            display: grid;
            gap: 16px;
        }

        .site-user-status-module-body {
            display: grid;
            gap: 16px;
        }

        .site-user-status-hero {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) minmax(260px, 0.9fr);
            gap: 18px;
            align-items: center;
            padding: 18px 20px;
            border-radius: 20px;
            border: 1px solid #edf2f7;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, #f8fbff 100%);
            position: relative;
            overflow: hidden;
        }

        .site-user-status-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.58) 42%, transparent 100%),
                repeating-linear-gradient(135deg, rgba(148, 163, 184, 0.05) 0 1px, transparent 1px 20px);
            pointer-events: none;
        }

        .site-user-status-avatar-card {
            position: relative;
            width: 68px;
            height: 68px;
            cursor: pointer;
            border-radius: 22px;
            z-index: 1;
        }

        .site-user-status-avatar {
            width: 68px;
            height: 68px;
            border-radius: 22px;
            background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 14%, #f8fbff 86%) 0%, color-mix(in srgb, var(--primary) 8%, #ffffff 92%) 100%);
            box-shadow:
                inset 0 0 0 1px color-mix(in srgb, var(--primary) 14%, #e5e7eb 86%),
                0 14px 30px rgba(15, 23, 42, 0.05);
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
            position: relative;
            overflow: hidden;
        }

        .site-user-status-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .site-user-status-avatar-fallback {
            position: relative;
            z-index: 1;
        }

        .site-user-status-avatar-card.has-image .site-user-status-avatar-fallback {
            display: none;
        }

        .site-user-status-avatar-actions {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 22px;
            background: rgba(15, 23, 42, 0);
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
            opacity: 0;
            transition: opacity 0.18s ease, background-color 0.18s ease;
            pointer-events: none;
            z-index: 3;
        }

        .site-user-status-avatar-card:hover .site-user-status-avatar-actions {
            opacity: 1;
            background: rgba(15, 23, 42, 0.34);
        }

        .site-user-status-avatar-remove {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 26px;
            height: 26px;
            border: 1px solid rgba(255, 255, 255, 0.72);
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.82);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            cursor: pointer;
            transition: opacity 0.18s ease, transform 0.18s ease, background-color 0.18s ease;
            z-index: 4;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.16);
        }

        .site-user-status-avatar-remove svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .site-user-status-avatar-card.has-image:hover .site-user-status-avatar-remove,
        .site-user-status-avatar-card.has-image .site-user-status-avatar-remove:focus-visible {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }

        .site-user-status-avatar-remove:hover {
            background: rgba(220, 38, 38, 0.92);
        }

        .site-user-status-avatar-note {
            margin-top: 8px;
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.6;
            position: relative;
            z-index: 1;
        }

        @include('admin.site.attachments._attachment_library_styles')

        .site-user-status-copy {
            display: grid;
            gap: 6px;
            min-width: 0;
            position: relative;
            z-index: 1;
        }

        .site-user-status-copy-eyebrow {
            color: #98a2b3;
            font-size: 11px;
            line-height: 1.4;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .site-user-status-copy-name {
            color: #1f2937;
            font-size: 24px;
            line-height: 1.3;
            font-weight: 800;
            word-break: break-word;
        }

        .site-user-status-copy-subline {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            color: #667085;
            font-size: 14px;
            line-height: 1.6;
            font-weight: 600;
        }

        .site-user-status-copy-subline code {
            font: inherit;
            color: #475467;
            background: rgba(255, 255, 255, 0.72);
            padding: 3px 8px;
            border-radius: 999px;
            border: 1px solid #edf2f7;
        }

        .site-user-status-side {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 18px;
            padding-left: 18px;
            border-left: 1px solid rgba(148, 163, 184, 0.16);
            position: relative;
            z-index: 1;
        }

        .site-user-status-side-item {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .site-user-status-side-label {
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.5;
            font-weight: 700;
        }

        .site-user-status-side-value {
            color: #344054;
            font-size: 15px;
            line-height: 1.5;
            font-weight: 700;
            word-break: break-word;
        }

        .site-user-status-main {
            display: grid;
            gap: 12px;
            justify-content: start;
            padding: 0 4px;
        }

        .site-user-status-main .field-meta {
            min-height: 0;
            width: auto;
        }

        .status-choice {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 128px;
            padding: 10px 14px;
            border-radius: 999px;
            background: #f8fafc;
            box-shadow: inset 0 0 0 1px #e5e7eb;
            color: #595959;
            cursor: pointer;
            transition: background 0.18s ease, box-shadow 0.18s ease, color 0.18s ease;
        }

        .status-choice:hover {
            background: var(--primary-soft);
            box-shadow: inset 0 0 0 1px rgba(0, 71, 171, 0.12);
            color: #374151;
        }

        .status-choice.is-error,
        .role-choice.is-error {
            background: rgba(254, 242, 242, 0.9);
            box-shadow: inset 0 0 0 1px rgba(239, 68, 68, 0.34), 0 0 0 3px rgba(239, 68, 68, 0.08);
            color: #b42318;
        }

        .status-choice.is-active {
            background: var(--tag-bg);
            color: var(--tag-text);
            box-shadow: inset 0 0 0 1px var(--primary-border-soft);
        }

        .status-choice input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .status-choice-check {
            width: 16px;
            height: 16px;
            border-radius: 999px;
            background: #ffffff;
            box-shadow: inset 0 0 0 1px #d9d9d9;
            position: relative;
            flex-shrink: 0;
        }

        .status-choice-check::after {
            content: "";
            position: absolute;
            inset: 4px;
            border-radius: 999px;
            background: #ffffff;
            opacity: 0;
        }

        .status-choice-label {
            font-size: 13px;
            font-weight: 600;
            line-height: 1.4;
            text-align: center;
        }

        .status-choice:has(input:checked) {
            background: var(--tag-bg);
            color: var(--tag-text);
            box-shadow: inset 0 0 0 1px var(--primary-border-soft);
        }

        .status-choice input:checked + .status-choice-check,
        .status-choice.is-active .status-choice-check {
            background: var(--primary);
            border-color: var(--primary);
        }

        .status-choice input:checked + .status-choice-check::after,
        .status-choice.is-active .status-choice-check::after {
            opacity: 1;
        }

        .role-choice {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: #f8fafc;
            box-shadow: inset 0 0 0 1px #e5e7eb;
            color: #595959;
            cursor: pointer;
            transition: background 0.18s ease, box-shadow 0.18s ease, color 0.18s ease;
        }

        .role-choice:hover {
            background: var(--primary-soft);
            box-shadow: inset 0 0 0 1px rgba(0, 71, 171, 0.12);
            color: #374151;
        }

        .role-choice input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .role-choice-check {
            width: 16px;
            height: 16px;
            border-radius: 999px;
            background: #ffffff;
            box-shadow: inset 0 0 0 1px #d9d9d9;
            position: relative;
            flex-shrink: 0;
        }

        .role-choice-check::after {
            content: "";
            position: absolute;
            inset: 4px;
            border-radius: 999px;
            background: #ffffff;
            opacity: 0;
        }

        .role-choice-name {
            font-size: 13px;
            line-height: 1.4;
            font-weight: 600;
        }

        .role-choice:has(input:checked) {
            background: var(--tag-bg);
            box-shadow: inset 0 0 0 1px var(--primary-border-soft);
            color: var(--tag-text);
        }

        .channel-panel {
            margin-top: 2px;
            padding: 0;
            border-radius: 0;
            background: transparent;
        }

        .channel-tree-wrap {
            display: grid;
            gap: 2px;
            max-height: 380px;
            padding: 2px 0;
            overflow-y: auto;
        }

        .channel-panel-desc {
            margin-top: 12px;
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.6;
        }

        .channel-placeholder {
            min-height: 380px;
            border: 1px dashed #e5e7eb;
            border-radius: 18px;
            background: linear-gradient(180deg, #fbfcfe 0%, #f8fafc 100%);
            display: grid;
            place-items: center;
            padding: 28px 22px;
        }

        .channel-placeholder[hidden],
        .channel-panel[hidden] {
            display: none !important;
        }

        .channel-placeholder-copy {
            max-width: 320px;
            display: grid;
            gap: 10px;
            justify-items: center;
            text-align: center;
        }

        .channel-placeholder-icon {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            color: #98a2b3;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }

        .channel-placeholder-icon svg {
            width: 24px;
            height: 24px;
            stroke: currentColor;
            stroke-width: 1.9;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .channel-placeholder-title {
            color: #374151;
            font-size: 16px;
            line-height: 1.5;
            font-weight: 700;
        }

        .channel-placeholder-note {
            color: #98a2b3;
            font-size: 13px;
            line-height: 1.8;
        }

        .channel-option {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            min-height: 40px;
            padding: 8px 10px;
            border-radius: 10px;
            background: transparent;
            color: #475467;
            transition: background 0.18s ease, color 0.18s ease;
            cursor: pointer;
        }

        .channel-option:hover {
            background: rgba(15, 23, 42, 0.03);
            color: #374151;
        }

        .channel-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .channel-tree-guides {
            display: inline-flex;
            align-items: stretch;
            align-self: stretch;
            flex: 0 0 auto;
            margin-right: 2px;
        }

        .channel-tree-guide,
        .channel-tree-branch {
            position: relative;
            width: 22px;
            flex: 0 0 22px;
        }

        .channel-tree-guide::before,
        .channel-tree-branch::before {
            content: '';
            position: absolute;
            left: 10px;
            top: -11px;
            bottom: -11px;
            width: 1px;
            background: transparent;
        }

        .channel-tree-guide.is-active::before {
            background: rgba(148, 163, 184, 0.28);
        }

        .channel-tree-branch::before {
            background: rgba(148, 163, 184, 0.28);
        }

        .channel-tree-branch::after {
            content: '';
            position: absolute;
            left: 10px;
            top: 50%;
            width: 12px;
            height: 1px;
            background: rgba(148, 163, 184, 0.28);
            transform: translateY(-50%);
        }

        .channel-tree-branch.is-last::before {
            bottom: 50%;
        }

        .permission-check {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            box-shadow: inset 0 0 0 1.4px #d9e2ec;
            transition: background 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }

        .permission-check::after {
            content: "";
            width: 7px;
            height: 4px;
            border-left: 2px solid transparent;
            border-bottom: 2px solid transparent;
            transform: rotate(-45deg) translateY(-1px);
            opacity: 0;
            transition: border-color 0.18s ease, opacity 0.18s ease;
        }

        .channel-copy {
            min-width: 0;
            flex: 1;
            display: flex;
            align-items: center;
        }

        .channel-name {
            color: inherit;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.45;
        }

        .channel-meta {
            display: none;
        }

        .channel-option:has(input:checked) {
            background: color-mix(in srgb, var(--primary) 6%, #ffffff 94%);
            color: var(--tag-text);
        }

        .channel-option:has(input:checked) .permission-check {
            background: var(--primary);
            box-shadow: inset 0 0 0 1px var(--primary);
        }

        .channel-option:has(input:checked) .permission-check::after {
            opacity: 1;
            border-left-color: #ffffff;
            border-bottom-color: #ffffff;
        }

        .role-choice input:checked + .role-choice-check {
            background: var(--primary);
            box-shadow: inset 0 0 0 1px var(--primary);
        }

        .role-choice input:checked + .role-choice-check::after {
            opacity: 1;
        }

        .site-user-form-footer {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            padding: 18px 28px 24px;
            border-top: 1px solid #f0f0f0;
        }

        .site-user-danger-card {
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .site-user-danger-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            border-bottom: 1px solid #f0f0f0;
        }

        .site-user-danger-accent {
            width: 4px;
            height: 16px;
            border-radius: 999px;
            background: #ef4444;
            flex-shrink: 0;
        }

        .site-user-danger-title {
            color: #262626;
            font-size: 15px;
            line-height: 1.5;
            font-weight: 700;
        }

        .site-user-danger-body {
            display: grid;
            gap: 14px;
            padding: 18px;
        }

        .site-user-danger-note {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.8;
        }

        .site-user-danger-actions {
            display: flex;
            justify-content: flex-end;
        }

        .site-user-danger-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 10px;
            border: 1px solid rgba(239, 68, 68, 0.22);
            background: rgba(255, 255, 255, 0.9);
            color: #dc2626;
            font-size: 13px;
            font-weight: 600;
            line-height: 1;
            transition: background 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease, border-color 0.18s ease;
        }

        .site-user-danger-button:hover {
            background: rgba(254, 226, 226, 0.86);
            border-color: rgba(239, 68, 68, 0.34);
            box-shadow: 0 8px 16px rgba(239, 68, 68, 0.10);
            transform: translateY(-1px);
        }

        .site-user-danger-button svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .site-user-remark-textarea {
            min-height: 220px;
        }

        .tox-tinymce {
            border-radius: 12px !important;
            border: 1px solid #e5e7eb !important;
            box-shadow: none !important;
            overflow: hidden;
        }

        .tox .tox-toolbar__group,
        .tox .tox-edit-area::before {
            border-color: #f0f0f0 !important;
        }

        .tox .tox-edit-area__iframe {
            background: #ffffff;
        }

        @media (max-width: 768px) {
            .page-header {
                margin: -24px -18px 20px;
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
            }

            .site-user-layout-grid,
            .site-user-form-grid,
            .site-user-status-layout {
                grid-template-columns: 1fr;
            }

            .site-user-status-hero {
                grid-template-columns: auto minmax(0, 1fr);
            }

            .site-user-status-side {
                grid-column: 1 / -1;
                padding-left: 0;
                padding-top: 14px;
                border-left: 0;
                border-top: 1px solid rgba(148, 163, 184, 0.16);
            }

            .site-user-body,
            .site-user-form-footer {
                padding-left: 18px;
                padding-right: 18px;
            }

            .site-user-form-footer {
                flex-direction: column;
                align-items: flex-start;
            }

            .site-user-status-hero {
                grid-template-columns: 1fr;
                justify-items: start;
            }

            .site-user-status-side {
                grid-template-columns: 1fr;
                width: 100%;
            }
        }
    </style>
    @include('admin.site._custom_select_styles')
@endpush

@push('scripts')
    <script src="/vendor/tinymce/tinymce.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.status-choice-grid').forEach(function (group) {
                const syncStatusChoices = function () {
                    group.querySelectorAll('.status-choice').forEach(function (choice) {
                        const input = choice.querySelector('input[type="radio"]');
                        choice.classList.toggle('is-active', !!input && input.checked);
                    });
                };

                group.addEventListener('change', syncStatusChoices);
                syncStatusChoices();
            });

            document.querySelectorAll('.role-choice-grid').forEach(function (group) {
                const syncRoleChoices = function () {
                    group.querySelectorAll('.role-choice').forEach(function (choice) {
                        const input = choice.querySelector('input[type="radio"]');
                        choice.classList.toggle('is-active', !!input && input.checked);
                    });
                };

                group.addEventListener('change', syncRoleChoices);
                syncRoleChoices();
            });

            const channelModule = document.querySelector('.js-channel-permission-module');
            const channelTree = channelModule ? channelModule.querySelector('.channel-tree-wrap') : null;
            const channelPlaceholder = channelModule ? channelModule.querySelector('.js-channel-placeholder') : null;
            const channelRealPanel = channelModule ? channelModule.querySelector('.js-channel-real-panel') : null;
            const channelModuleReadonly = channelModule ? channelModule.dataset.readonly === '1' : false;
            const syncChannelDescendants = function (checkbox) {
                if (!channelTree || !checkbox) {
                    return;
                }

                const currentOption = checkbox.closest('.channel-option');
                if (!currentOption) {
                    return;
                }

                const currentDepth = Number(currentOption.dataset.depth || 0);
                let nextOption = currentOption.nextElementSibling;

                while (nextOption && nextOption.classList.contains('channel-option')) {
                    const nextDepth = Number(nextOption.dataset.depth || 0);
                    if (nextDepth <= currentDepth) {
                        break;
                    }

                    const nextCheckbox = nextOption.querySelector('input[name="channel_ids[]"]');
                    if (nextCheckbox && !nextCheckbox.disabled) {
                        nextCheckbox.checked = checkbox.checked;
                    }

                    nextOption = nextOption.nextElementSibling;
                }
            };

            const syncChannelModule = function () {
                if (!channelModule) {
                    return;
                }

                if (channelModuleReadonly) {
                    return;
                }

                const checkedRole = document.querySelector('input[name="role_id"]:checked');
                const canManageContent = !!(checkedRole && checkedRole.closest('.role-choice') && checkedRole.closest('.role-choice').dataset.canManageContent === '1');

                if (channelPlaceholder) {
                    channelPlaceholder.hidden = canManageContent;
                }

                if (channelRealPanel) {
                    channelRealPanel.hidden = ! canManageContent;
                }

                channelModule.querySelectorAll('input[name="channel_ids[]"]').forEach(function (input) {
                    input.disabled = ! canManageContent;
                });
            };

            document.querySelectorAll('.role-choice-grid').forEach(function (group) {
                group.addEventListener('change', syncChannelModule);
            });
            syncChannelModule();

            const syncStatusIdentity = function () {
                const displayName = document.querySelector('[data-status-display-name]');
                const displayUsername = document.querySelector('[data-status-display-username]');
                const displayAvatar = document.querySelector('[data-status-avatar]');
                const nameInput = document.getElementById('name');
                const usernameInput = document.getElementById('username');

                if (!displayName || !displayUsername || !displayAvatar || !nameInput || !usernameInput) {
                    return;
                }

                const nameValue = (nameInput.value || '').trim();
                const usernameValue = (usernameInput.value || '').trim();
                const seed = nameValue || usernameValue || '账';

                displayName.textContent = nameValue || '未设置姓名';
                displayUsername.textContent = usernameValue || '未设置账号';
                displayAvatar.textContent = Array.from(seed)[0] || '账';
            };

            ['name', 'username'].forEach(function (fieldId) {
                const input = document.getElementById(fieldId);
                if (input) {
                    input.addEventListener('input', syncStatusIdentity);
                }
            });
            let cmsAttachments = [];
            const attachmentLibraryWorkspaceAccess = @json($avatarAttachmentWorkspaceAccess);
            const attachmentDeleteUrlTemplate = @json(route('admin.attachments.destroy', ['attachment' => '__ATTACHMENT__']));
            const attachmentUsageUrlTemplate = @json(route('admin.attachments.usages', ['attachment' => '__ATTACHMENT__']));

            @include('admin.site.attachments._attachment_library_script')

            const avatarInput = document.getElementById('avatar');
            const avatarCard = document.querySelector('[data-avatar-trigger]');
            const avatarPreview = document.querySelector('[data-avatar-preview]');
            const avatarNote = document.querySelector('[data-avatar-note]');
            const avatarRemove = document.querySelector('[data-avatar-remove]');

            const renderAvatar = function () {
                if (!avatarInput || !avatarCard || !avatarPreview) {
                    syncStatusIdentity();
                    return;
                }

                const value = avatarInput.value.trim();
                let img = avatarPreview.querySelector('[data-avatar-image]');
                let fallback = avatarPreview.querySelector('[data-status-avatar]');

                if (!fallback) {
                    fallback = document.createElement('span');
                    fallback.className = 'site-user-status-avatar-fallback';
                    fallback.setAttribute('data-status-avatar', '');
                    avatarPreview.appendChild(fallback);
                }

                syncStatusIdentity();

                if (!value) {
                    if (img) {
                        img.remove();
                    }
                    fallback.style.display = 'inline-flex';
                    avatarCard.classList.remove('has-image');
                    if (avatarNote) {
                        avatarNote.textContent = '点击头像，从资源库选择或上传。';
                    }
                    return;
                }

                if (!img) {
                    img = document.createElement('img');
                    img.setAttribute('data-avatar-image', '');
                    img.alt = '头像预览';
                    avatarPreview.prepend(img);
                }

                img.src = value;
                img.onerror = function () {
                    img?.remove();
                    avatarInput.value = '';
                    renderAvatar();
                };
                fallback.style.display = 'none';
                avatarCard.classList.add('has-image');
                if (avatarNote) {
                    avatarNote.textContent = '已设置头像，点击可从资源库更换。';
                }
            };

            avatarCard?.addEventListener('click', function () {
                window.openSiteAttachmentLibrary?.({
                    mode: 'avatar',
                    context: 'avatar',
                    imageOnly: true,
                    onSelect(attachment) {
                        if (!avatarInput) {
                            return;
                        }

                        avatarInput.value = attachment.url || '';
                        renderAvatar();
                    },
                    onClear() {
                        if (!avatarInput) {
                            return;
                        }

                        avatarInput.value = '';
                        renderAvatar();
                    },
                });
            });

            avatarRemove?.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                if (!avatarInput) {
                    return;
                }

                avatarInput.value = '';
                renderAvatar();
            });

            avatarInput?.addEventListener('input', renderAvatar);
            renderAvatar();

            if (channelTree) {
                channelTree.addEventListener('change', function (event) {
                    const checkbox = event.target.closest('input[name="channel_ids[]"]');
                    if (!checkbox) {
                        return;
                    }

                    syncChannelDescendants(checkbox);
                });
            }

            if (window.tinymce) {
                window.tinymce.init({
                    selector: 'textarea.site-user-remark-rich-editor',
                    min_height: 200,
                    height: 240,
                    language: 'zh_CN',
                    language_url: '/vendor/tinymce/langs/zh_CN.js',
                    menubar: false,
                    branding: false,
                    promotion: false,
                    license_key: 'gpl',
                    convert_urls: false,
                    relative_urls: false,
                    plugins: 'autolink link lists code textcolor',
                    toolbar: 'undo redo | bold italic underline forecolor backcolor | bullist numlist | link blockquote | removeformat code',
                    content_style: 'body { font-family: PingFang SC, Microsoft YaHei, sans-serif; font-size: 14px; line-height: 1.8; }',
                    setup(editor) {
                        editor.on('change input undo redo', () => editor.save());
                    }
                });
            }

            const deleteButton = document.querySelector('.js-site-user-delete');
            if (deleteButton) {
                deleteButton.addEventListener('click', function () {
                    const formId = deleteButton.dataset.formId;
                    const form = formId ? document.getElementById(formId) : null;

                    if (!form || typeof window.showConfirmDialog !== 'function') {
                        return;
                    }

                    window.showConfirmDialog({
                        title: '确认删除操作员？',
                        text: '删除后，该操作员账号及其站点绑定关系都会被清除，且操作不可恢复。',
                        confirmText: '确定删除',
                        onConfirm: () => form.submit(),
                    });
                });
            }

            const form = document.getElementById('site-user-edit-form');
            const fields = {
                username: document.getElementById('username'),
                name: document.getElementById('name'),
                email: document.getElementById('email'),
                mobile: document.getElementById('mobile'),
                password: document.getElementById('password'),
                remark: document.getElementById('remark'),
            };

            const normalizeValue = function (field) {
                return String(field?.value || '').trim();
            };

            const clearFieldError = function (field) {
                if (!field) {
                    return;
                }

                field.classList.remove('is-error');
                field.removeAttribute('aria-invalid');
            };

            const setFieldError = function (field) {
                if (!field) {
                    return;
                }

                field.classList.add('is-error');
                field.setAttribute('aria-invalid', 'true');
            };

            const clearChoiceErrors = function (selector) {
                document.querySelectorAll(selector).forEach(function (element) {
                    element.classList.remove('is-error');
                });
            };

            const setChoiceError = function (selector) {
                document.querySelectorAll(selector).forEach(function (element) {
                    element.classList.add('is-error');
                });
            };

            const usernamePattern = /^[A-Za-z][A-Za-z0-9_-]{3,31}$/;
            const mobilePattern = /^[0-9\-+\s()#]{6,50}$/;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            const validators = {
                username(value) {
                    if (value === '') {
                        return '请填写用户名。';
                    }

                    return usernamePattern.test(value)
                        ? ''
                        : '用户名需以字母开头，可使用字母、数字、下划线或短横线，长度 4-32 位。';
                },
                name(value) {
                    if (value === '') {
                        return '请填写姓名。';
                    }

                    if (value.length < 2) {
                        return '姓名至少需要 2 个字符。';
                    }

                    return value.length <= 50 ? '' : '姓名不能超过 50 个字符。';
                },
                email(value) {
                    if (value !== '' && value.length > 255) {
                        return '邮箱长度不能超过 255 个字符。';
                    }

                    return value === '' || emailPattern.test(value) ? '' : '邮箱格式不正确，请重新填写。';
                },
                mobile(value) {
                    return value === '' || mobilePattern.test(value) ? '' : '手机号格式不正确，请输入有效的电话或手机号。';
                },
                password(value) {
                    return value === '' || value.length >= 8 ? '' : '重置密码至少需要 8 位。';
                },
                remark(value) {
                    return value.length <= 10000 ? '' : '备注信息不能超过 10000 个字符。';
                },
            };

            const validateRole = function () {
                const roleInputs = Array.from(document.querySelectorAll('input[name="role_id"]'));

                if (roleInputs.length === 0) {
                    return '';
                }

                return roleInputs.some(function (input) {
                    return input.checked;
                }) ? '' : '请选择操作角色。';
            };

            const validateStatus = function () {
                const statusInputs = Array.from(document.querySelectorAll('input[name="status"]'));

                if (statusInputs.length === 0) {
                    return '';
                }

                return statusInputs.some(function (input) {
                    return input.checked;
                }) ? '' : '请选择账号状态。';
            };

            Object.entries(fields).forEach(function ([key, field]) {
                if (!field) {
                    return;
                }

                const validateCurrentField = function () {
                    const validator = validators[key];
                    const message = validator ? validator(normalizeValue(field)) : '';

                    if (message === '') {
                        clearFieldError(field);
                    }
                };

                field.addEventListener('input', validateCurrentField);
                field.addEventListener('blur', validateCurrentField);
            });

            document.querySelectorAll('input[name="role_id"]').forEach(function (input) {
                input.addEventListener('change', function () {
                    clearChoiceErrors('.role-choice');
                });
            });

            document.querySelectorAll('input[name="status"]').forEach(function (input) {
                input.addEventListener('change', function () {
                    clearChoiceErrors('.status-choice');
                });
            });

            if (form) {
                form.addEventListener('submit', function (event) {
                    const messages = [];
                    let firstInvalid = null;

                    Object.values(fields).forEach(function (field) {
                        clearFieldError(field);
                    });
                    clearChoiceErrors('.role-choice');
                    clearChoiceErrors('.status-choice');

                    Object.entries(fields).forEach(function ([key, field]) {
                        if (!field) {
                            return;
                        }

                        const validator = validators[key];
                        const message = validator ? validator(normalizeValue(field)) : '';

                        if (message !== '') {
                            setFieldError(field);
                            messages.push(message);
                            firstInvalid = firstInvalid || field;
                        }
                    });

                    const roleMessage = validateRole();
                    if (roleMessage !== '') {
                        setChoiceError('.role-choice');
                        messages.push(roleMessage);
                        firstInvalid = firstInvalid || document.querySelector('input[name="role_id"]');
                    }

                    const statusMessage = validateStatus();
                    if (statusMessage !== '') {
                        setChoiceError('.status-choice');
                        messages.push(statusMessage);
                        firstInvalid = firstInvalid || document.querySelector('input[name="status"]');
                    }

                    if (messages.length > 0) {
                        event.preventDefault();
                        showMessage([...new Set(messages)].join('，'), 'error');
                        firstInvalid?.focus();
                        firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            }
        });
    </script>

    @if ($errors->any())
        <script>
            (() => {
                const messages = @json($errors->all());

                if (Array.isArray(messages) && messages.length > 0) {
                    showMessage([...new Set(messages)].join('，'), 'error');
                }
            })();
        </script>
    @endif
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">编辑操作员</h2>
            <div class="page-header-desc">当前站点：{{ $currentSite->name }}。正在维护 {{ $user->name ?: $user->username }} 的账号信息、角色分配和联系方式。</div>
        </div>
        <div class="topbar-right">
            <a class="button secondary" href="{{ route('admin.site-users.index') }}">返回操作员管理</a>
            <button class="button" type="submit" form="site-user-edit-form" data-loading-text="保存中...">保存操作员</button>
        </div>
    </section>

    <section class="site-user-shell">
        <form id="site-user-edit-form" method="POST" action="{{ route('admin.site-users.update', $user->id) }}">
            @csrf

            <div class="site-user-body">
                <div class="site-user-layout-grid">
                    <div class="site-user-column">
                        @if ($isSelfEditing)
                        <div class="site-user-status-hero">
                            <input type="hidden" name="avatar" id="avatar" value="{{ old('avatar', $user->avatar) }}">
                            <div class="site-user-status-avatar-card" data-avatar-trigger>
                                <div class="site-user-status-avatar" data-avatar-preview>
                                    <span class="site-user-status-avatar-fallback" data-status-avatar>{{ $avatarInitial }}</span>
                                </div>
                                <div class="site-user-status-avatar-actions">更换头像</div>
                                <button class="site-user-status-avatar-remove" type="button" data-avatar-remove aria-label="清除头像">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M6 6l12 12M18 6L6 18"></path>
                                    </svg>
                                </button>
                            </div>
                            <div class="site-user-status-copy">
                                <div class="site-user-status-copy-eyebrow">Account Profile</div>
                                <div class="site-user-status-copy-name" data-status-display-name>{{ $displayName }}</div>
                                <div class="site-user-status-copy-subline">
                                    <span>后台操作员账号</span>
                                    <code data-status-display-username>{{ $displayUsername }}</code>
                                </div>
                                <div class="site-user-status-avatar-note" data-avatar-note>点击头像，从资源库选择或上传。</div>
                            </div>

                            <div class="site-user-status-side">
                                <div class="site-user-status-side-item">
                                    <span class="site-user-status-side-label">创建时间</span>
                                    <span class="site-user-status-side-value">{{ optional($user->created_at)->format('Y-m-d H:i') ?: '未记录' }}</span>
                                </div>
                                <div class="site-user-status-side-item">
                                    <span class="site-user-status-side-label">最后登录</span>
                                    <span class="site-user-status-side-value">{{ optional($user->last_login_at)->format('Y-m-d H:i') ?: '暂无记录' }}</span>
                                </div>
                            </div>
                        </div>
                        @elseif ($canManageStatusSelection)
                        <section class="site-user-module">
                            <div class="site-user-module-header">
                                <span class="site-user-module-accent"></span>
                                <div class="site-user-module-title">账号状态</div>
                            </div>
                            <div class="site-user-module-body site-user-status-module-body">
                                <div class="site-user-status-layout">
                                    <div class="site-user-status-hero">
                                        <input type="hidden" name="avatar" id="avatar" value="{{ old('avatar', $user->avatar) }}">
                                        <div class="site-user-status-avatar-card" data-avatar-trigger>
                                            <div class="site-user-status-avatar" data-avatar-preview>
                                                <span class="site-user-status-avatar-fallback" data-status-avatar>{{ $avatarInitial }}</span>
                                            </div>
                                            <div class="site-user-status-avatar-actions">更换头像</div>
                                            <button class="site-user-status-avatar-remove" type="button" data-avatar-remove aria-label="清除头像">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M6 6l12 12M18 6L6 18"></path>
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="site-user-status-copy">
                                            <div class="site-user-status-copy-eyebrow">Account Profile</div>
                                            <div class="site-user-status-copy-name" data-status-display-name>{{ $displayName }}</div>
                                            <div class="site-user-status-copy-subline">
                                                <span>后台操作员账号</span>
                                                <code data-status-display-username>{{ $displayUsername }}</code>
                                            </div>
                                            <div class="site-user-status-avatar-note" data-avatar-note>点击头像，从资源库选择或上传。</div>
                                        </div>

                                        <div class="site-user-status-side">
                                            <div class="site-user-status-side-item">
                                                <span class="site-user-status-side-label">创建时间</span>
                                                <span class="site-user-status-side-value">{{ optional($user->created_at)->format('Y-m-d H:i') ?: '未记录' }}</span>
                                            </div>
                                            <div class="site-user-status-side-item">
                                                <span class="site-user-status-side-label">最后登录</span>
                                                <span class="site-user-status-side-value">{{ optional($user->last_login_at)->format('Y-m-d H:i') ?: '暂无记录' }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="site-user-status-main">
                                        @if ($canManageStatusSelection)
                                        <div class="status-choice-grid">
                                            @foreach (['1' => '启用', '0' => '停用'] as $statusValue => $statusLabel)
                                                <label class="status-choice {{ $selectedStatusValue === (string) $statusValue ? 'is-active' : '' }}">
                                                    <input type="radio" name="status" value="{{ $statusValue }}" @checked($selectedStatusValue === (string) $statusValue)>
                                                    <span class="status-choice-check" aria-hidden="true"></span>
                                                    <span class="status-choice-label">{{ $statusLabel }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <span class="field-meta">
                                            <span class="field-note">停用后，账号将无法继续登录当前后台。</span>
                                            @error('status')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
                                        @else
                                        <div class="site-user-status-meta">
                                            <div class="site-user-status-row">
                                                <span class="site-user-status-label">当前状态</span>
                                                <span class="site-user-status-badge {{ $selectedStatusValue === '0' ? 'is-offline' : '' }}">{{ $statusLabel }}</span>
                                            </div>
                                        </div>
                                        <span class="field-meta">
                                            <span class="field-note">当前账号可自行更换头像，账号状态由具备操作员管理权限的管理员维护。</span>
                                        </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </section>
                        @endif

                        <section class="site-user-module">
                            <div class="site-user-module-header">
                                <span class="site-user-module-accent"></span>
                                <div class="site-user-module-title">基础信息</div>
                            </div>
                            <div class="site-user-module-body">
                                <div class="site-user-form-grid">
                                    <label class="field-group">
                                        <span class="field-label">用户名</span>
                                        <input class="field {{ $isSelfEditing ? 'is-readonly' : '' }} @error('username') is-error @enderror" id="username" type="text" name="username" value="{{ old('username', $user->username) }}" maxlength="32" @if($isSelfEditing) readonly aria-readonly="true" @endif @error('username') aria-invalid="true" @enderror>
                                        <span class="field-meta">
                                            <span class="field-note">{{ $isSelfEditing ? '当前登录账号不能修改自己的用户名。' : '作为后台登录账号使用，建议保持简洁稳定。' }}</span>
                                            @error('username')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">姓名</span>
                                        <input class="field @error('name') is-error @enderror" id="name" type="text" name="name" value="{{ old('name', $user->name) }}" maxlength="50" @error('name') aria-invalid="true" @enderror>
                                        <span class="field-meta">
                                            <span class="field-note">在列表和审计日志中显示的管理员名称。</span>
                                            @error('name')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
                                    </label>
                                </div>

                                <div class="site-user-form-grid">
                                    <label class="field-group">
                                        <span class="field-label">邮箱</span>
                                        <input class="field @error('email') is-error @enderror" id="email" type="text" name="email" value="{{ old('email', $user->email) }}" maxlength="255" @error('email') aria-invalid="true" @enderror>
                                        <span class="field-meta">
                                            <span class="field-note">用于接收通知或找回信息，可留空。</span>
                                            @error('email')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">手机号</span>
                                        <input class="field @error('mobile') is-error @enderror" id="mobile" type="text" name="mobile" value="{{ old('mobile', $user->mobile) }}" @error('mobile') aria-invalid="true" @enderror>
                                        <span class="field-meta">
                                            <span class="field-note">用于展示联系方式或后续短信提醒，可留空。</span>
                                            @error('mobile')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </section>

                        @if ($canManageRoleSelection)
                        <section class="site-user-module">
                            <div class="site-user-module-header">
                                <span class="site-user-module-accent"></span>
                                <div class="site-user-module-title">操作角色</div>
                            </div>
                            <div class="site-user-module-body">
                                <div class="field-group">
                                    <div class="role-choice-grid">
                                        @foreach ($siteRoles as $siteRole)
                                            <label class="role-choice" data-can-manage-content="{{ $siteRole->can_manage_content ? '1' : '0' }}">
                                                <input type="radio" name="role_id" value="site:{{ $siteRole->id }}" @checked((string) $selectedRoleValue === ('site:' . $siteRole->id))>
                                                <span class="role-choice-check" aria-hidden="true"></span>
                                                <span class="role-choice-name">{{ $siteRole->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <span class="field-meta">
                                        <span class="field-note">一个操作员只能绑定一个操作角色。</span>
                                        @error('role_id')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </span>
                                </div>
                            </div>
                        </section>
                        @endif

                        <section class="site-user-module">
                            <div class="site-user-module-header">
                                <span class="site-user-module-accent"></span>
                                <div class="site-user-module-title">安全设置</div>
                            </div>
                            <div class="site-user-module-body">
                                <label class="field-group">
                                    <span class="field-label">重置密码</span>
                                    <input class="field @error('password') is-error @enderror" id="password" type="password" name="password" placeholder="留空则不修改" @error('password') aria-invalid="true" @enderror>
                                    <span class="field-meta">
                                        <span class="field-note">如需重置密码，请填写新的 8 位以上密码。</span>
                                        @error('password')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </span>
                                </label>
                            </div>
                        </section>

                        @if (! $isSelfEditing)
                        <section class="site-user-module">
                            <div class="site-user-module-header">
                                <span class="site-user-module-accent"></span>
                                <div class="site-user-module-title">备注信息</div>
                            </div>
                            <div class="site-user-module-body">
                                <div class="field-group">
                                    <textarea class="field textarea site-user-remark-textarea site-user-remark-rich-editor @error('remark') is-error @enderror" id="remark" name="remark" maxlength="10000" @error('remark') aria-invalid="true" @enderror>{{ old('remark', $user->remark) }}</textarea>
                                    <span class="field-meta">
                                        <span class="field-note">用于记录该操作员的交接说明、职责补充或维护备注，支持精简富文本格式。</span>
                                        @error('remark')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </span>
                                </div>
                            </div>
                        </section>
                        @endif
                    </div>

                    <div class="site-user-column">
                        <section class="site-user-module js-channel-permission-module" data-readonly="{{ $isSelfEditing ? '1' : '0' }}">
                            <div class="site-user-module-header">
                                <span class="site-user-module-accent"></span>
                                <div class="site-user-module-title">可管理栏目</div>
                            </div>
                            <div class="site-user-module-body">
                                <div class="channel-placeholder js-channel-placeholder" @if(($isSelfEditing && $selectedManagedChannels->isNotEmpty()) || (! $isSelfEditing && $selectedRoleCanManageContent)) hidden @endif>
                                    <div class="channel-placeholder-copy">
                                        <span class="channel-placeholder-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24">
                                                <path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h4l2 2H18.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-9Z"></path>
                                            </svg>
                                        </span>
                                        <div class="channel-placeholder-title">暂无可管理栏目</div>
                                        <div class="channel-placeholder-note">
                                            @if ($isSelfEditing)
                                                当前账号未设置按栏目限制的内容管理权限。
                                            @else
                                                选择具备内容管理权限的操作角色后，这里会自动显示对应的栏目权限配置。
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="channel-panel js-channel-real-panel" @if(($isSelfEditing && $selectedManagedChannels->isEmpty()) || (! $isSelfEditing && ! $selectedRoleCanManageContent)) hidden @endif>
                                    <div class="channel-tree-wrap">
                                        @foreach ($isSelfEditing ? $selectedManagedChannels : collect($channels) as $channel)
                                            <label class="channel-option" data-depth="{{ (int) ($channel->tree_depth ?? 0) }}">
                                                <span class="channel-tree-guides" aria-hidden="true">
                                                    @foreach (($channel->tree_ancestors ?? []) as $hasLine)
                                                        <span class="channel-tree-guide {{ $hasLine ? 'is-active' : '' }}"></span>
                                                    @endforeach
                                                    @if ((int) ($channel->tree_depth ?? 0) > 0)
                                                        <span class="channel-tree-branch {{ !empty($channel->tree_is_last) ? 'is-last' : '' }}"></span>
                                                    @endif
                                                </span>
                                                <input
                                                    type="checkbox"
                                                    @if (! $isSelfEditing) name="channel_ids[]" @endif
                                                    value="{{ $channel->id }}"
                                                    @checked(in_array($channel->id, $selectedChannelIds, true))
                                                    @if ($isSelfEditing) disabled @endif
                                                >
                                                <span class="permission-check" aria-hidden="true"></span>
                                                <span class="channel-copy">
                                                    <span class="channel-name">{{ $channel->name }}</span>
                                                    <span class="channel-meta">
                                                        @if (!empty($channel->tree_has_children))
                                                            上级栏目
                                                        @else
                                                            可管理发布
                                                        @endif
                                                    </span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="channel-panel-desc">
                                        @if ($isSelfEditing)
                                            当前账号在这些栏目内具备内容管理权限，此处仅供查看。
                                        @else
                                            默认不勾选可管理添加所有栏目的文章内容。
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </section>

                        @if (! $isSelfEditing)
                        <section class="site-user-danger-card">
                            <div class="site-user-danger-header">
                                <span class="site-user-danger-accent"></span>
                                <div class="site-user-danger-title">删除操作员</div>
                            </div>
                            <div class="site-user-danger-body">
                                <div class="site-user-danger-note">删除后，该操作员账号及其站点绑定关系都会被清除，且操作不可恢复。请确认当前账号不再需要继续保留。</div>
                                <div class="site-user-danger-actions">
                                    <button
                                        class="site-user-danger-button js-site-user-delete"
                                        type="button"
                                        data-form-id="delete-site-user-{{ $user->id }}"
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                        <span>删除操作员</span>
                                    </button>
                                </div>
                            </div>
                        </section>
                        @endif
                    </div>
                </div>
            </div>

        </form>
    </section>

    @include('admin.site.attachments._attachment_library_modal')

    @if (! $isSelfEditing)
        <form id="delete-site-user-{{ $user->id }}" method="POST" action="{{ route('admin.site-users.destroy', $user->id) }}" style="display: none;">
            @csrf
        </form>
    @endif
@endsection
