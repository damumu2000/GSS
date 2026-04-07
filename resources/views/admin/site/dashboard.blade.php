@extends('layouts.admin')

@section('title', '站点工作台 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 站点工作台')

@php
    $statusLabels = [
        'draft' => '草稿',
        'published' => '已发布',
        'pending' => '待审核',
        'offline' => '已下线',
        'approved' => '已通过',
        'rejected' => '已驳回',
    ];
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

        .dashboard-switcher {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dashboard-switcher .custom-select {
            position: relative;
            min-width: 320px;
        }

        .dashboard-switcher .custom-select-native {
            position: absolute;
            inset: 0;
            opacity: 0;
            pointer-events: none;
        }

        .dashboard-switcher .custom-select-trigger {
            width: 100%;
            min-height: 42px;
            padding: 0 40px 0 14px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #ffffff;
            color: #262626;
            font: inherit;
            font-size: 14px;
            line-height: 42px;
            text-align: left;
            cursor: pointer;
            position: relative;
        }

        .dashboard-switcher .custom-select-trigger::after {
            content: "";
            position: absolute;
            right: 14px;
            top: 50%;
            width: 12px;
            height: 12px;
            transform: translateY(-50%);
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12' fill='none'%3E%3Cpath d='M3 4.5L6 7.5L9 4.5' stroke='%2398A2B3' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center/12px 12px no-repeat;
        }

        .dashboard-switcher .custom-select-panel {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            padding: 8px;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            opacity: 0;
            pointer-events: none;
            transform: translateY(-4px) scale(0.98);
            transition: opacity 0.18s ease, transform 0.18s ease;
            z-index: 1500;
        }

        .dashboard-switcher .custom-select.is-open .custom-select-panel {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0) scale(1);
        }

        .dashboard-switcher .custom-select-search .field {
            min-height: 38px;
            height: 38px;
            padding: 0 12px;
            border-radius: 8px;
            box-shadow: none;
        }

        .dashboard-switcher .custom-select-options {
            display: grid;
            gap: 4px;
            max-height: 240px;
            overflow-y: auto;
        }

        .dashboard-switcher .custom-select-option {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: #595959;
            font: inherit;
            font-size: 13px;
            line-height: 1.5;
            text-align: left;
            cursor: pointer;
        }

        .dashboard-switcher .custom-select-option:hover,
        .dashboard-switcher .custom-select-option.is-active {
            background: #f5f7fa;
            color: #374151;
        }

        .dashboard-switcher .custom-select-option.is-hidden {
            display: none;
        }

        .dashboard-switcher .custom-select-check {
            width: 14px;
            height: 14px;
            stroke: #4b5563;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            opacity: 0;
        }

        .dashboard-switcher .custom-select-option.is-active .custom-select-check {
            opacity: 1;
        }

        .dashboard-switcher .custom-select-empty {
            display: none;
            padding: 12px 10px 8px;
            color: #9ca3af;
            font-size: 12px;
            text-align: center;
        }

        .dashboard-switcher .custom-select-empty.is-visible {
            display: block;
        }

        .dashboard-switcher .button {
            min-height: 42px;
            min-width: 160px;
            border-radius: 10px;
            white-space: nowrap;
        }

        .site-banner {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: stretch;
            padding: 22px 24px;
            margin-bottom: 20px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.92));
            border: 1px solid rgba(229, 231, 235, 0.88);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }

        .site-banner-copy {
            display: grid;
            gap: 8px;
        }

        .site-banner-eyebrow {
            color: #8c8c8c;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.04em;
        }

        .site-banner-title {
            margin: 0;
            color: #262626;
            font-size: 24px;
            line-height: 1.35;
            font-weight: 700;
        }

        .site-banner-desc {
            color: #8c8c8c;
            font-size: 14px;
            line-height: 1.75;
        }

        .site-identity-card {
            position: relative;
            overflow: hidden;
            height: 100%;
            padding: 24px;
            border-radius: 20px;
            border: 1px solid rgba(213, 221, 232, 0.95);
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.92) 0, rgba(255, 255, 255, 0) 34%),
                linear-gradient(135deg, rgba(248, 251, 255, 0.98) 0%, rgba(243, 247, 252, 0.94) 100%);
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.06);
        }

        .site-identity-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(120deg, rgba(0, 71, 171, 0.05) 0%, rgba(0, 71, 171, 0) 30%),
                repeating-linear-gradient(
                    -28deg,
                    rgba(148, 163, 184, 0.08) 0,
                    rgba(148, 163, 184, 0.08) 1px,
                    transparent 1px,
                    transparent 18px
                );
            pointer-events: none;
        }

        .site-identity-card::after {
            content: "";
            position: absolute;
            right: -42px;
            bottom: -46px;
            width: 190px;
            height: 190px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(0, 71, 171, 0.12) 0%, rgba(0, 71, 171, 0) 70%);
            pointer-events: none;
        }

        .site-identity-top,
        .site-identity-grid,
        .site-identity-note {
            position: relative;
            z-index: 1;
        }

        .site-identity-top {
            display: grid;
            gap: 8px;
            padding-bottom: 18px;
            border-bottom: 1px solid rgba(226, 232, 240, 0.92);
        }

        .site-identity-eyebrow {
            color: rgba(0, 71, 171, 0.72);
            font-size: 11px;
            line-height: 1;
            letter-spacing: 0.18em;
            font-weight: 700;
            text-transform: uppercase;
        }

        .site-identity-name {
            margin: 0;
            color: #1f2937;
            font-size: 28px;
            line-height: 1.3;
            font-weight: 700;
            word-break: break-word;
        }

        .site-identity-key {
            color: #64748b;
            font-size: 13px;
            line-height: 1.7;
            letter-spacing: 0.02em;
        }

        .site-identity-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 18px;
            padding-top: 18px;
        }

        .site-identity-item {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .site-identity-label {
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.5;
            font-weight: 600;
        }

        .site-identity-value {
            color: #1f2937;
            font-size: 15px;
            line-height: 1.65;
            font-weight: 600;
            word-break: break-word;
        }

        .site-identity-value.is-warning {
            color: #b45309;
        }

        .site-identity-value.is-danger {
            color: #dc2626;
        }

        .site-identity-note {
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid rgba(226, 232, 240, 0.92);
            color: #64748b;
            font-size: 12px;
            line-height: 1.75;
        }

        .site-identity-note strong {
            color: #1f2937;
            font-weight: 700;
        }

        .site-identity-note strong.is-warning {
            color: #b45309;
        }

        .site-identity-note strong.is-danger {
            color: #dc2626;
        }

        @media (max-width: 960px) {
            .site-identity-grid {
                grid-template-columns: 1fr;
            }

            .help-placeholder-grid {
                grid-template-columns: 1fr;
            }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            padding: 18px;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
        }

        .stat-card::after {
            content: "";
            position: absolute;
            right: -12px;
            top: -14px;
            width: 92px;
            height: 92px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.04);
        }

        .stat-card-label {
            color: #8c8c8c;
            font-size: 13px;
        }

        .stat-card-value {
            margin-top: 10px;
            color: #262626;
            font-size: 28px;
            font-weight: 700;
        }

        .stat-card-note {
            margin-top: 6px;
            color: #8c8c8c;
            font-size: 12px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.88fr;
            gap: 18px;
        }

        .panel {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
            padding: 20px;
        }

        .panel + .panel {
            margin-top: 18px;
        }

        .dashboard-top-panel {
            min-height: 476px;
            display: flex;
            flex-direction: column;
        }

        .dashboard-bottom-panel {
            height: 430px;
            display: flex;
            flex-direction: column;
        }

        .panel-title {
            margin: 0;
            font-size: 18px;
            color: #262626;
            font-weight: 700;
        }

        .panel-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .panel-heading-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .panel-heading .button,
        .panel-heading .button.secondary {
            min-height: 36px;
            padding: 0 14px;
            border-radius: 10px;
            white-space: nowrap;
        }

        .dashboard-create-button {
            border: 1px solid var(--line, #f0f0f0) !important;
            background: #fafafa !important;
            color: var(--text, #262626) !important;
            box-shadow: none;
            transition: background 0.22s ease, border-color 0.22s ease, color 0.22s ease, transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease !important;
        }

        .dashboard-action-button:hover,
        .dashboard-action-button:focus-visible {
            transform: translateY(-1px);
            border-color: transparent !important;
            background: linear-gradient(135deg, var(--primary, #0047AB) 0%, color-mix(in srgb, var(--primary, #0047AB) 78%, #ffffff 22%) 100%) !important;
            color: #ffffff !important;
            box-shadow: 0 14px 24px rgba(15, 23, 42, 0.10), 0 6px 14px color-mix(in srgb, var(--primary, #0047AB) 22%, transparent 78%);
            filter: saturate(1.03) brightness(1.02);
        }

        .dashboard-action-button:active {
            transform: translateY(0);
            background: linear-gradient(135deg, var(--primary, #0047AB) 0%, color-mix(in srgb, var(--primary, #0047AB) 82%, #ffffff 18%) 100%) !important;
            color: #ffffff !important;
            box-shadow: 0 8px 16px rgba(15, 23, 42, 0.08), 0 4px 10px color-mix(in srgb, var(--primary, #0047AB) 18%, transparent 82%);
            filter: saturate(0.99) brightness(0.99);
        }

        .panel-subtitle {
            margin-top: 6px;
            color: #8c8c8c;
            font-size: 13px;
            line-height: 1.7;
        }

        .list-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        .list-table th,
        .list-table td {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            text-align: left;
            font-size: 14px;
        }

        .list-table th {
            color: #8c8c8c;
            font-weight: 600;
        }

        .list-table td:last-child,
        .list-table th:last-child {
            text-align: right;
        }

        .list-table td a {
            color: #262626;
            font-weight: 600;
            text-decoration: none;
        }

        .list-table td a:hover {
            color: #111827;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .recent-feed {
            display: grid;
            gap: 0;
            margin-top: 16px;
            flex: 1 1 auto;
            align-content: start;
        }

        .recent-feed-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            gap: 18px;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid #f0f0f0;
            transition: transform 0.18s ease, color 0.18s ease;
        }

        .recent-feed-item:hover {
            transform: translateX(2px);
        }

        .recent-feed-main {
            min-width: 0;
            display: grid;
            gap: 8px;
        }

        .recent-feed-title {
            min-width: 0;
            color: #1f2937;
            font-size: 15px;
            line-height: 1.75;
            font-weight: 400;
            text-decoration: none;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .recent-feed-title:hover {
            color: var(--primary, #0047AB);
            text-decoration: none;
        }

        .recent-feed-item:hover .recent-feed-title {
            color: var(--primary, #0047AB);
        }

        .recent-feed-time {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 54px;
            padding: 0 2px;
            color: #98a2b3;
            font-size: 15px;
            line-height: 1;
            font-weight: 600;
            white-space: nowrap;
        }

        .recent-feed-status {
            justify-self: end;
            white-space: nowrap;
        }

        .recent-feed-empty {
            margin-top: 16px;
            padding: 32px 18px;
            border-radius: 14px;
            background: #fafafa;
            color: #8c8c8c;
            font-size: 14px;
            text-align: center;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 0 10px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 12px;
            font-weight: 600;
            line-height: 24px;
        }

        .status-badge.published {
            background: #f6ffed;
            color: #389e0d;
        }

        .status-badge.pending {
            background: #fff7e6;
            color: #d48806;
        }

        .status-badge.draft,
        .status-badge.offline {
            background: #f5f5f5;
            color: #595959;
        }

        .help-placeholder {
            position: relative;
            overflow: hidden;
            flex: 1 1 auto;
            display: grid;
            gap: 16px;
            align-content: start;
            padding: 22px;
            border-radius: 18px;
            border: 1px solid #edf2f7;
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.96) 0%, rgba(255, 255, 255, 0) 38%),
                linear-gradient(180deg, #fcfdff 0%, #f8fafc 100%);
        }

        .help-placeholder::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(135deg, rgba(15, 23, 42, 0.02) 0%, transparent 34%),
                repeating-linear-gradient(
                    -35deg,
                    rgba(148, 163, 184, 0.06) 0,
                    rgba(148, 163, 184, 0.06) 1px,
                    transparent 1px,
                    transparent 20px
                );
            pointer-events: none;
        }

        .help-placeholder-head,
        .help-placeholder-grid,
        .help-placeholder-note {
            position: relative;
            z-index: 1;
        }

        .help-placeholder-head {
            display: grid;
            gap: 10px;
        }

        .help-placeholder-kicker {
            color: #98a2b3;
            font-size: 11px;
            line-height: 1;
            letter-spacing: 0.16em;
            font-weight: 700;
            text-transform: uppercase;
        }

        .help-placeholder-title {
            margin: 0;
            color: #1f2937;
            font-size: 24px;
            line-height: 1.4;
            font-weight: 700;
        }

        .help-placeholder-desc {
            color: #667085;
            font-size: 14px;
            line-height: 1.8;
        }

        .help-placeholder-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .help-placeholder-item {
            display: grid;
            gap: 6px;
            padding: 14px 16px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(226, 232, 240, 0.9);
        }

        .help-placeholder-item strong {
            color: #344054;
            font-size: 14px;
            line-height: 1.6;
            font-weight: 700;
        }

        .help-placeholder-item span {
            color: #8b94a7;
            font-size: 12px;
            line-height: 1.7;
        }

        .notice-list {
            display: grid;
            gap: 12px;
            margin-top: 14px;
            flex: 1 1 auto;
            align-content: start;
        }

        .notice-item {
            display: grid;
            gap: 6px;
            padding: 14px 16px;
            border-radius: 12px;
            background: #fafafa;
            border: 1px solid rgba(226, 232, 240, 0.88);
            transition: transform 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease;
        }

        .notice-item:hover {
            transform: translateY(-1px);
            background: #f5f7fb;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.05);
        }

        .notice-item-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .notice-item-title {
            color: #262626;
            font-size: 14px;
            font-weight: 400;
            min-width: 0;
        }

        .notice-item-title-text {
            min-width: 0;
            display: inline;
            word-break: break-word;
        }

        .notice-item-title-flags {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: 8px;
            vertical-align: middle;
        }

        .notice-item-title-flag {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            padding: 0 8px;
            border-radius: 999px;
            background: rgba(0, 71, 171, 0.08);
            color: var(--primary, #0047AB);
            font-size: 12px;
            line-height: 1;
            font-weight: 700;
        }

        .notice-item-title-flag.is-top {
            background: rgba(245, 158, 11, 0.12);
            color: #b45309;
        }

        .notice-item-title-flag.is-recommend {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
        }

        .notice-item-title a {
            color: inherit;
            text-decoration: none;
        }

        .notice-item-title a:hover {
            color: var(--primary, #0047AB);
            text-decoration: none;
        }

        .notice-item:hover .notice-item-title a {
            color: var(--primary, #0047AB);
        }

        .notice-item-date {
            color: #8c8c8c;
            font-size: 12px;
        }

        .notice-item-summary {
            color: #8b94a7;
            font-size: 12px;
            line-height: 1.75;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .notice-modal[hidden] {
            display: none;
        }

        .notice-modal {
            position: fixed;
            inset: 0;
            z-index: 2800;
        }

        .notice-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.48);
            backdrop-filter: blur(8px) saturate(110%);
            opacity: 0;
            transition: opacity 0.24s ease;
        }

        .notice-modal.is-open .notice-modal-backdrop {
            opacity: 1;
        }

        .notice-modal-shell {
            position: relative;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .notice-modal-panel {
            position: relative;
            width: min(860px, calc(100vw - 40px));
            max-height: calc(100vh - 48px);
            overflow: hidden;
            border-radius: 30px;
            border: 1px solid rgba(220, 229, 239, 0.95);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.99) 0%, rgba(248, 250, 252, 0.98) 100%);
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.22), inset 0 1px 0 rgba(255, 255, 255, 0.75);
            transform: scale(0.92) translateY(18px);
            opacity: 0;
            transition: transform 0.28s cubic-bezier(.2,.8,.2,1), opacity 0.22s ease;
        }

        .notice-modal-scroll {
            max-height: calc(100vh - 48px);
            overflow: auto;
            padding-right: 14px;
            scrollbar-width: auto;
            scrollbar-color: rgba(148, 163, 184, 0.96) transparent;
        }

        .notice-modal-scroll::-webkit-scrollbar {
            width: 16px;
        }

        .notice-modal-scroll::-webkit-scrollbar-track {
            margin: 44px 8px 44px 0;
            border: 4px solid transparent;
            border-radius: 999px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.78) 0%, rgba(241, 245, 249, 0.92) 100%);
            background-clip: padding-box;
            box-shadow:
                inset 0 1px 2px rgba(255, 255, 255, 0.72),
                inset 0 -1px 3px rgba(148, 163, 184, 0.12),
                inset 0 0 0 1px rgba(191, 219, 254, 0.4);
        }

        .notice-modal-scroll::-webkit-scrollbar-thumb {
            border: 4px solid transparent;
            border-radius: 999px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.34) 0%, rgba(255, 255, 255, 0) 30%),
                linear-gradient(180deg, rgba(203, 213, 225, 0.98) 0%, rgba(148, 163, 184, 0.98) 100%);
            background-clip: padding-box;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.42),
                0 4px 12px rgba(15, 23, 42, 0.10);
        }

        .notice-modal-scroll::-webkit-scrollbar-thumb:hover {
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.32) 0%, rgba(255, 255, 255, 0) 24%),
                linear-gradient(180deg, rgba(148, 163, 184, 0.98) 0%, rgba(100, 116, 139, 1) 100%);
            background-clip: padding-box;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.45),
                0 6px 14px rgba(15, 23, 42, 0.14);
        }

        .notice-modal-scroll::-webkit-scrollbar-corner {
            background: transparent;
        }

        .notice-modal.is-open .notice-modal-panel {
            transform: scale(1) translateY(0);
            opacity: 1;
        }

        .notice-modal-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 30px;
            background:
                linear-gradient(135deg, rgba(0, 71, 171, 0.05) 0%, rgba(0, 71, 171, 0) 42%),
                repeating-linear-gradient(135deg, rgba(148, 163, 184, 0.06) 0, rgba(148, 163, 184, 0.06) 1px, transparent 1px, transparent 22px);
            pointer-events: none;
        }

        .notice-modal-inner {
            position: relative;
            z-index: 1;
            padding: 30px 32px 34px;
        }

        .notice-modal-topbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 22px;
        }

        .notice-modal-kicker {
            color: rgba(0, 71, 171, 0.78);
            font-size: 11px;
            line-height: 1;
            letter-spacing: 0.16em;
            font-weight: 700;
            text-transform: uppercase;
        }

        .notice-modal-title {
            margin: 12px 0 0;
            color: #1f2937;
            font-size: clamp(26px, 4vw, 34px);
            line-height: 1.35;
            font-weight: 800;
        }

        .notice-modal-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-top: 12px;
            color: #8b94a7;
            font-size: 13px;
            line-height: 1.7;
            font-weight: 600;
        }

        .notice-modal-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 28px;
            padding: 0 12px;
            border-radius: 999px;
            background: rgba(0, 71, 171, 0.08);
            color: var(--primary, #0047AB);
            font-size: 12px;
            font-weight: 700;
        }

        .notice-modal-close {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            border: 1px solid rgba(213, 221, 232, 0.95);
            background: rgba(255, 255, 255, 0.92);
            color: #667085;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease;
        }

        .notice-modal-close:hover {
            background: #f8fafc;
            color: #1f2937;
            transform: translateY(-1px);
        }

        .notice-modal-close svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 1.8;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .notice-modal-frame {
            padding: 22px 24px;
            border-radius: 24px;
            border: 1px solid rgba(220, 229, 239, 0.95);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(250, 252, 255, 0.95) 100%);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
        }

        .notice-modal-summary {
            color: #526071;
            font-size: 15px;
            line-height: 1.9;
            margin-bottom: 18px;
        }

        .notice-modal-content {
            color: #1f2937;
            font-size: 15px;
            line-height: 1.95;
        }

        .notice-modal-content p {
            margin: 0 0 16px;
        }

        .notice-modal-content p:last-child {
            margin-bottom: 0;
        }

        .notice-modal-content :first-child {
            margin-top: 0;
        }

        .notice-modal-content :last-child {
            margin-bottom: 0;
        }

        .notice-modal-content img {
            max-width: 100%;
            height: auto;
            border-radius: 18px;
            display: block;
            margin: 24px auto;
        }

        .notice-modal-content figure {
            margin: 24px 0;
        }

        .notice-modal-content figure img {
            margin: 0 auto;
        }

        .notice-modal-content p > img,
        .notice-modal-content p > a > img {
            margin: 24px auto;
        }

        .notice-modal-content p + img,
        .notice-modal-content img + p,
        .notice-modal-content p + figure,
        .notice-modal-content figure + p,
        .notice-modal-content p > img + br,
        .notice-modal-content p > a + br {
            margin-top: 24px;
        }

        .notice-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 22px;
        }

        .notice-modal-actions .button.secondary {
            min-height: 40px;
            border-radius: 12px;
        }

        .notice-link {
            margin-top: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #4b5563;
            font-size: 13px;
            font-weight: 600;
        }

        .quick-links {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }

        .quick-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #fafafa;
            color: #262626;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .quick-link:hover {
            background: #f4f4f5;
        }

        .quick-link-note {
            color: #8c8c8c;
            font-size: 12px;
            font-weight: 500;
        }

        .site-profile-list {
            display: grid;
            gap: 12px;
            margin-top: 14px;
        }

        .site-profile-item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #fafafa;
        }

        .site-profile-label {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.6;
        }

        .site-profile-value {
            color: #262626;
            font-size: 13px;
            line-height: 1.7;
            font-weight: 600;
            text-align: right;
            word-break: break-all;
        }

        .site-profile-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }

        .site-profile-actions .button,
        .site-profile-actions .button.secondary {
            min-height: 38px;
            border-radius: 10px;
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div>
            <h2 class="page-header-title">站点工作台</h2>
            <div class="page-header-desc">{{ $dashboardGreeting }}</div>
        </div>

        @if ($showSiteSwitcher)
            <form method="POST" action="{{ route('admin.site-context.update') }}" class="dashboard-switcher">
                @csrf
                <div class="custom-select" data-select>
                    <select class="custom-select-native" id="site_id" name="site_id">
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected($site->id === $currentSite->id)>{{ $site->name }}（{{ $site->site_key }}）</option>
                        @endforeach
                    </select>
                    <button class="custom-select-trigger" type="button" data-select-trigger>{{ $currentSite->name }}（{{ $currentSite->site_key }}）</button>
                    <div class="custom-select-panel" data-select-panel>
                        <div class="custom-select-search">
                            <input class="field" type="text" placeholder="搜索站点名称或标识" data-select-search>
                        </div>
                        <div class="custom-select-options" data-select-options>
                            @foreach ($sites as $site)
                                <button class="custom-select-option {{ $site->id === $currentSite->id ? 'is-active' : '' }}" type="button" data-select-option data-value="{{ $site->id }}" data-label="{{ $site->name }}（{{ $site->site_key }}）">
                                    <span>{{ $site->name }}（{{ $site->site_key }}）</span>
                                    <svg class="custom-select-check" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5 6.5 11.5 12.5 4.5"/></svg>
                                </button>
                            @endforeach
                        </div>
                        <div class="custom-select-empty" data-select-empty>没有匹配的站点。</div>
                    </div>
                </div>
                <button class="button" type="submit">切换站点主控</button>
            </form>
        @endif
    </section>

    <section class="stats-grid">
        <article class="stat-card">
            <div class="stat-card-label">栏目数量</div>
            <div class="stat-card-value">{{ $stats['channel_count'] }}</div>
        </article>
        <article class="stat-card">
            <div class="stat-card-label">内容数量</div>
            <div class="stat-card-value">{{ $stats['content_count'] }}</div>
        </article>
        <article class="stat-card">
            <div class="stat-card-label">附件数量</div>
            <div class="stat-card-value">{{ $stats['attachment_count'] }}</div>
        </article>
        <article class="stat-card">
            <div class="stat-card-label">待处理内容</div>
            <div class="stat-card-value">{{ $stats['pending_count'] }}</div>
            <div class="stat-card-note">草稿 {{ $stats['draft_count'] }} 篇，待审核 {{ $stats['review_count'] }} 篇</div>
        </article>
    </section>

    <section class="dashboard-grid">
        <div>
            <section class="panel dashboard-top-panel">
                <div class="panel-heading">
                    <h3 class="panel-title">近期文章</h3>
                    <div class="panel-heading-actions">
                        <a class="button dashboard-create-button dashboard-action-button" href="{{ route('admin.articles.create') }}">新建文章</a>
                        <a class="button dashboard-create-button dashboard-action-button" href="{{ route('admin.articles.index', ['status' => 'draft', 'mine' => 'drafts']) }}">我的草稿</a>
                    </div>
                </div>
                @if ($recentContents->isNotEmpty())
                    <div class="recent-feed">
                        @foreach ($recentContents as $content)
                            <article class="recent-feed-item" @if ($canManageContent) onclick="window.location.href='{{ $content->type === 'page' ? route('admin.pages.edit', $content->id) : route('admin.articles.edit', $content->id) }}'" style="cursor:pointer;" @endif>
                                <div class="recent-feed-main">
                                    @if ($canManageContent)
                                        <a class="recent-feed-title" href="{{ $content->type === 'page' ? route('admin.pages.edit', $content->id) : route('admin.articles.edit', $content->id) }}" onclick="event.stopPropagation()">
                                            {{ $content->title }}
                                        </a>
                                    @else
                                        <div class="recent-feed-title">{{ $content->title }}</div>
                                    @endif
                                </div>
                                <span class="status-badge recent-feed-status {{ $content->status }}">{{ $statusLabels[$content->status] ?? $content->status }}</span>
                                <div class="recent-feed-time">
                                    {{ $content->updated_at ? \Illuminate\Support\Carbon::parse($content->updated_at)->format('m-d') : '--' }}
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="recent-feed-empty">当前站点暂无内容记录。</div>
                @endif
            </section>

            <section class="panel dashboard-bottom-panel">
                <div class="help-placeholder">
                    <div class="help-placeholder-head">
                        <div class="help-placeholder-kicker">Help Center</div>
                        <h3 class="help-placeholder-title">系统使用帮助功能（预留）</h3>
                        <div class="help-placeholder-desc">这里会逐步补充新手引导、常见问题、权限说明和内容操作指引，帮助操作员更快上手并减少来回查找。</div>
                    </div>
                    <div class="help-placeholder-grid">
                        <div class="help-placeholder-item">
                            <strong>新手引导</strong>
                            <span>引导完成栏目、文章、附件和模板的基础操作。</span>
                        </div>
                        <div class="help-placeholder-item">
                            <strong>常见问题</strong>
                            <span>集中整理发布、审核、回收站和附件相关问题。</span>
                        </div>
                        <div class="help-placeholder-item">
                            <strong>权限说明</strong>
                            <span>帮助理解当前角色可操作范围与协作边界。</span>
                        </div>
                        <div class="help-placeholder-item">
                            <strong>快捷入口</strong>
                            <span>后续可接入视频教程、图文说明和搜索帮助。</span>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div>
            <section class="panel dashboard-top-panel">
                <h3 class="panel-title">官闪闪公告栏</h3>
                <div class="notice-list">
                    @forelse ($platformNotices as $notice)
                        @php
                            $noticeTitleStyle = [];

                            if (! empty($notice->title_color)) {
                                $noticeTitleStyle[] = 'color: '.$notice->title_color;
                            }

                            if (! empty($notice->title_bold)) {
                                $noticeTitleStyle[] = 'font-weight: 700';
                            }

                            if (! empty($notice->title_italic)) {
                                $noticeTitleStyle[] = 'font-style: italic';
                            }
                        @endphp
                        <article
                            class="notice-item"
                            style="cursor:pointer;"
                            data-notice-trigger
                            data-notice-title="{{ $notice->title }}"
                            data-notice-date="{{ $notice->published_at ? \Illuminate\Support\Carbon::parse($notice->published_at)->format('Y-m-d') : '待发布' }}"
                            data-notice-link="{{ route('site.article', ['id' => $notice->id, 'site' => $platformNoticeSiteKey]) }}"
                            data-notice-summary="{{ trim((string) ($notice->summary ?? '')) }}"
                            data-notice-content-id="platform-notice-content-{{ $notice->id }}"
                        >
                            <div class="notice-item-top">
                                <div class="notice-item-title">
                                    <span class="notice-item-title-text" style="{{ implode('; ', $noticeTitleStyle) }}">{{ $notice->title }}</span>
                                    @if (! empty($notice->is_top) || ! empty($notice->is_recommend))
                                        <span class="notice-item-title-flags">
                                            @if (! empty($notice->is_top))
                                                <span class="notice-item-title-flag is-top">顶</span>
                                            @endif
                                            @if (! empty($notice->is_recommend))
                                                <span class="notice-item-title-flag is-recommend">精</span>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                                <div class="notice-item-date">{{ $notice->published_at ? \Illuminate\Support\Carbon::parse($notice->published_at)->format('Y-m-d') : '待发布' }}</div>
                            </div>
                            @if (trim((string) ($notice->summary ?? '')) !== '')
                                <div class="notice-item-summary">{{ $notice->summary }}</div>
                            @endif
                            <template id="platform-notice-content-{{ $notice->id }}">
                                {!! (string) ($notice->content ?? '') !!}
                            </template>
                        </article>
                    @empty
                        <article class="notice-item">
                            <div class="notice-item-title">当前暂无官闪闪公告。</div>
                        </article>
                    @endforelse
                </div>
            </section>

            <section class="panel dashboard-bottom-panel">
                @php
                    $expiryClass = $daysUntilExpiry !== null && $daysUntilExpiry < 0
                        ? 'is-danger'
                        : (($daysUntilExpiry !== null && $daysUntilExpiry <= 30) ? 'is-warning' : '');
                    $expiryText = $expiresAt ? $expiresAt->format('Y-m-d') : '未设置';
                @endphp
                <div class="site-identity-card">
                    <div class="site-identity-top">
                        <div class="site-identity-eyebrow">Site Credential</div>
                        <h3 class="site-identity-name">{{ $currentSite->name }}</h3>
                        <div class="site-identity-key">站点标识 · {{ $currentSite->site_key }}</div>
                    </div>
                    <div class="site-identity-grid">
                        <div class="site-identity-item">
                            <div class="site-identity-label">当前角色</div>
                            <div class="site-identity-value">{{ count($roleNames) > 0 ? implode('、', $roleNames) : '未分配' }}</div>
                        </div>
                        <div class="site-identity-item">
                            <div class="site-identity-label">站点状态</div>
                            <div class="site-identity-value">{{ (int) $currentSite->status === 1 ? '开启中' : '已关闭' }}</div>
                        </div>
                        <div class="site-identity-item">
                            <div class="site-identity-label">主域名</div>
                            <div class="site-identity-value">{{ $primaryDomain ?: '待绑定' }}</div>
                        </div>
                        <div class="site-identity-item">
                            <div class="site-identity-label">绑定站点</div>
                            <div class="site-identity-value">{{ $boundSiteCount }} 个</div>
                        </div>
                        <div class="site-identity-item">
                            <div class="site-identity-label">到期时间</div>
                            <div class="site-identity-value {{ $expiryClass }}">{{ $expiryText }}</div>
                        </div>
                        <div class="site-identity-item">
                            <div class="site-identity-label">备案号</div>
                            <div class="site-identity-value">{{ $filingNumber ?: '未填写' }}</div>
                        </div>
                    </div>
                    <div class="site-identity-note">
                        @if ($daysUntilExpiry === null)
                            <span>站点尚未设置到期时间，请联系平台管理员补充站点有效期。</span>
                        @elseif ($daysUntilExpiry < 0)
                            <span>当前站点已<strong class="is-danger">过期 {{ abs($daysUntilExpiry) }} 天</strong>，请尽快联系平台管理员续期。</span>
                        @elseif ($daysUntilExpiry <= 30)
                            <span>当前站点距离到期还有<strong class="is-warning">{{ $daysUntilExpiry }} 天</strong>，建议提前完成续期安排。</span>
                        @else
                            <span>当前站点有效期正常，距离到期还有<strong>{{ $daysUntilExpiry }} 天</strong>。</span>
                        @endif
                    </div>
                </div>
            </section>

        </div>
    </section>

    <div class="notice-modal" id="platform-notice-modal" hidden>
        <div class="notice-modal-backdrop" data-notice-close></div>
        <div class="notice-modal-shell" data-notice-shell>
            <div class="notice-modal-panel" role="dialog" aria-modal="true" aria-labelledby="platform-notice-modal-title">
                <div class="notice-modal-scroll">
                    <div class="notice-modal-inner">
                        <div class="notice-modal-topbar">
                        <div>
                            <div class="notice-modal-kicker">Guanshanshan Notice</div>
                            <h3 class="notice-modal-title" id="platform-notice-modal-title">官闪闪公告栏</h3>
                            <div class="notice-modal-meta">
                                <span class="notice-modal-chip">官闪闪公告栏</span>
                                <span id="platform-notice-modal-date">--</span>
                                </div>
                            </div>
                            <button class="notice-modal-close" type="button" data-notice-close aria-label="关闭公告弹窗">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M6 6l12 12"></path>
                                    <path d="M18 6 6 18"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="notice-modal-frame">
                            <div class="notice-modal-summary" id="platform-notice-modal-summary" hidden></div>
                            <div class="notice-modal-content" id="platform-notice-modal-content">暂无公告内容。</div>
                        </div>
                        <div class="notice-modal-actions">
                            <a class="button secondary" id="platform-notice-modal-link" href="#" target="_blank" rel="noopener">前台查看全文</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            document.querySelectorAll('[data-select]').forEach((container) => {
                const trigger = container.querySelector('[data-select-trigger]');
                const panel = container.querySelector('[data-select-panel]');
                const options = Array.from(container.querySelectorAll('[data-select-option]'));
                const nativeSelect = container.querySelector('.custom-select-native');
                const searchInput = container.querySelector('[data-select-search]');
                const emptyState = container.querySelector('[data-select-empty]');

                if (!trigger || !panel || !nativeSelect) {
                    return;
                }

                const close = () => container.classList.remove('is-open');

                trigger.addEventListener('click', () => {
                    const open = !container.classList.contains('is-open');
                    document.querySelectorAll('[data-select].is-open').forEach((item) => item.classList.remove('is-open'));
                    container.classList.toggle('is-open', open);
                    if (open && searchInput) {
                        searchInput.value = '';
                        options.forEach((option) => option.classList.remove('is-hidden'));
                        emptyState?.classList.remove('is-visible');
                        setTimeout(() => searchInput.focus(), 30);
                    }
                });

                options.forEach((option) => {
                    option.addEventListener('click', () => {
                        const value = option.dataset.value ?? '';
                        const label = option.dataset.label ?? option.textContent?.trim() ?? '';
                        nativeSelect.value = value;
                        trigger.textContent = label;
                        options.forEach((item) => item.classList.toggle('is-active', item === option));
                        close();
                    });
                });

                searchInput?.addEventListener('input', () => {
                    const keyword = searchInput.value.trim().toLowerCase();
                    let visible = 0;

                    options.forEach((option) => {
                        const label = (option.dataset.label ?? option.textContent ?? '').toLowerCase();
                        const matched = keyword === '' || label.includes(keyword);
                        option.classList.toggle('is-hidden', !matched);
                        if (matched) visible += 1;
                    });

                    emptyState?.classList.toggle('is-visible', visible === 0);
                });

                document.addEventListener('click', (event) => {
                    if (!container.contains(event.target)) {
                        close();
                    }
                });
            });

            const noticeModal = document.getElementById('platform-notice-modal');
            const noticeModalTitle = document.getElementById('platform-notice-modal-title');
            const noticeModalDate = document.getElementById('platform-notice-modal-date');
            const noticeModalSummary = document.getElementById('platform-notice-modal-summary');
            const noticeModalContent = document.getElementById('platform-notice-modal-content');
            const noticeModalLink = document.getElementById('platform-notice-modal-link');
            let previousBodyOverflow = '';

            const closeNoticeModal = () => {
                if (!noticeModal || noticeModal.hidden) {
                    return;
                }

                noticeModal.classList.remove('is-open');
                window.setTimeout(() => {
                    noticeModal.hidden = true;
                    document.body.style.overflow = previousBodyOverflow;
                }, 220);
            };

            const openNoticeModal = (payload) => {
                if (!noticeModal || !noticeModalTitle || !noticeModalDate || !noticeModalSummary || !noticeModalContent || !noticeModalLink) {
                    return;
                }

                noticeModalTitle.textContent = payload.title || '平台公告';
                noticeModalDate.textContent = payload.date || '--';

                if (payload.summary) {
                    noticeModalSummary.hidden = false;
                    noticeModalSummary.textContent = payload.summary;
                } else {
                    noticeModalSummary.hidden = true;
                    noticeModalSummary.textContent = '';
                }

                noticeModalContent.innerHTML = payload.contentHtml && payload.contentHtml.trim() !== ''
                    ? payload.contentHtml
                    : '<p>暂无公告内容。</p>';
                noticeModalLink.href = payload.link || '#';
                noticeModal.hidden = false;
                previousBodyOverflow = document.body.style.overflow || '';
                document.body.style.overflow = 'hidden';
                window.requestAnimationFrame(() => {
                    noticeModal.classList.add('is-open');
                });
            };

            document.querySelectorAll('[data-notice-trigger]').forEach((item) => {
                item.addEventListener('click', () => {
                    const templateId = item.getAttribute('data-notice-content-id');
                    const contentTemplate = templateId ? document.getElementById(templateId) : null;

                    openNoticeModal({
                        title: item.getAttribute('data-notice-title') || '官闪闪公告栏',
                        date: item.getAttribute('data-notice-date') || '--',
                        link: item.getAttribute('data-notice-link') || '#',
                        summary: item.getAttribute('data-notice-summary') || '',
                        contentHtml: contentTemplate ? contentTemplate.innerHTML.trim() : '',
                    });
                });
            });

            noticeModal?.querySelectorAll('[data-notice-close]').forEach((element) => {
                element.addEventListener('click', closeNoticeModal);
            });

            noticeModal?.querySelector('[data-notice-shell]')?.addEventListener('click', (event) => {
                if (event.target === event.currentTarget) {
                    closeNoticeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeNoticeModal();
                }
            });
        })();
    </script>
@endpush
