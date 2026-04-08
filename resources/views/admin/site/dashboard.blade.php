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

        .page-header {
            display: flex;
            justify-content: flex-start;
            gap: 20px;
            align-items: flex-start;
            padding: 24px 32px;
            margin: -28px -28px 24px;
            background: #ffffff;
            border-bottom: 1px solid #f0f0f0;
        }

        .page-header-main {
            min-width: 0;
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

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr;
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

        .notice-list {
            display: grid;
            gap: 12px;
            margin-top: 14px;
            flex: 1 1 auto;
            align-content: start;
        }

        .notice-item {
            display: grid;
            gap: 8px;
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
            display: grid;
            gap: 8px;
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
            line-height: 1.5;
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
            position: relative;
            display: flex;
            justify-content: center;
            margin-bottom: 22px;
            padding-top: 4px;
        }

        .notice-modal-heading {
            width: 100%;
            text-align: center;
        }

        .notice-modal-title {
            margin: 0;
            color: #1f2937;
            font-size: clamp(22px, 3.2vw, 28px);
            line-height: 1.4;
            font-weight: 800;
        }

        .notice-modal-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: center;
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
            position: absolute;
            top: 0;
            right: 0;
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

        .insights-content {
            margin-top: 0;
        }

        .insights-hero-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .insight-hero-card {
            position: relative;
            overflow: hidden;
            min-height: 160px;
            padding: 18px 18px 16px;
            border-radius: 20px;
            border: 1px solid rgba(221, 229, 239, 0.96);
            background: rgba(255, 255, 255, 0.86);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }

        .insight-hero-card.is-security,
        .insight-hero-card.is-security-total {
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.94) 0%, rgba(248, 251, 255, 0.96) 100%);
            border-color: rgba(208, 221, 237, 0.96);
        }

        .insight-hero-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            position: relative;
            z-index: 1;
        }

        .insight-hero-top-right {
            display: inline-flex;
            align-items: center;
            flex-shrink: 0;
        }

        .insight-hero-icon {
            width: 20px;
            height: 20px;
            color: #94a3b8;
            flex-shrink: 0;
        }

        .insight-hero-icon svg {
            width: 100%;
            height: 100%;
            display: block;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.9;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .insight-hero-card.is-visits .insight-hero-icon,
        .insight-hero-card.is-trend .insight-hero-icon {
            color: #8b94a7;
        }

        .insight-hero-card.is-security-total .insight-hero-icon {
            color: #475569;
        }

        .insight-hero-label {
            color: #667085;
            font-size: 13px;
            line-height: 1.6;
            font-weight: 700;
        }

        .insight-hero-value {
            margin-top: 14px;
            color: #111827;
            font-size: clamp(30px, 4vw, 40px);
            line-height: 1;
            font-weight: 900;
            letter-spacing: -0.03em;
            position: relative;
            z-index: 1;
        }

        .insight-hero-note {
            margin-top: 12px;
            color: #667085;
            font-size: 13px;
            line-height: 1.7;
            position: relative;
            z-index: 1;
        }

        .insights-board {
            display: grid;
            grid-template-columns: 1.28fr 1fr 0.92fr;
            gap: 14px;
            align-items: start;
        }

        .insight-board-card {
            display: flex;
            flex-direction: column;
            gap: 14px;
            min-width: 0;
            min-height: 100%;
            padding: 18px;
            border-radius: 22px;
            border: 1px solid rgba(221, 229, 239, 0.96);
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.04);
        }

        .insight-board-card.is-trend-card {
            grid-column: 1;
            grid-row: 1;
            min-height: 312px;
        }

        .insight-board-card.is-article-rank-card {
            grid-column: 2;
            grid-row: 1;
        }

        .insight-board-card.is-assets-card {
            grid-column: 3;
            grid-row: 2;
        }

        .insight-board-card.is-author-card {
            grid-column: 3;
            grid-row: 1;
        }

        .insight-board-card.is-notice-card {
            grid-column: 2;
            grid-row: 2;
        }

        .insight-board-card .panel-heading {
            align-items: flex-start;
        }

        .insight-board-card .panel-heading-actions {
            flex-wrap: wrap;
        }

        .insight-board-card-title {
            margin: 0;
            color: #111827;
            font-size: 16px;
            line-height: 1.5;
            font-weight: 800;
        }

        .insight-board-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .insight-board-card-tag {
            flex-shrink: 0;
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 700;
            white-space: nowrap;
        }

        .insight-board-card-subtitle {
            color: #8b94a7;
            font-size: 12px;
            line-height: 1.7;
        }

        .insight-trend {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 8px;
            align-items: end;
            min-height: 176px;
        }

        .insight-trend-item {
            display: grid;
            gap: 8px;
            justify-items: center;
            padding: 8px 4px 4px;
            border-radius: 18px;
            transition: background-color 0.18s ease, transform 0.18s ease;
            cursor: default;
        }

        .insight-trend-item:hover {
            background: rgba(242, 246, 244, 0.95);
            transform: translateY(-2px);
        }

        .insight-trend-bar {
            width: 72%;
            max-width: 58px;
            min-height: 16px;
            border-radius: 999px;
            background: linear-gradient(180deg, color-mix(in srgb, var(--primary) 82%, white 18%) 0%, color-mix(in srgb, var(--primary) 34%, white 66%) 100%);
            box-shadow: 0 6px 14px color-mix(in srgb, var(--primary) 18%, transparent);
            transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
        }

        .insight-trend-item:hover .insight-trend-bar {
            transform: translateY(-2px);
            box-shadow: 0 10px 18px color-mix(in srgb, var(--primary) 22%, transparent);
            filter: saturate(1.06);
        }

        .insight-trend-label {
            color: #9aa4b2;
            font-size: 11px;
            line-height: 1;
            font-weight: 700;
        }

        .insight-rank-list {
            display: grid;
            gap: 12px;
        }

        .insight-rank-item {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 10px 12px;
            border-radius: 16px;
            transition: background-color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
        }

        .insight-rank-item:hover {
            background: rgba(248, 250, 249, 0.98);
            transform: translateY(-1px);
            box-shadow: inset 0 0 0 1px rgba(229, 235, 231, 0.92);
        }

        .insight-rank-no {
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary, #0047AB);
            font-size: 12px;
            font-weight: 800;
        }

        .insight-rank-main {
            min-width: 0;
            display: grid;
            gap: 6px;
        }

        .insight-rank-title {
            color: #1f2937;
            font-size: 14px;
            line-height: 1.6;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .insight-rank-subtitle {
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.6;
        }

        .insight-rank-bar-track {
            height: 7px;
            border-radius: 999px;
            background: var(--primary-soft);
            overflow: hidden;
        }

        .insight-rank-bar {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, color-mix(in srgb, var(--primary) 78%, white 22%) 0%, color-mix(in srgb, var(--primary) 42%, white 58%) 100%);
            transition: filter 0.18s ease, box-shadow 0.18s ease;
        }

        .insight-rank-item:hover .insight-rank-bar {
            filter: saturate(1.04);
            box-shadow: 0 0 0 1px var(--primary-soft-strong);
        }

        .insight-rank-value {
            color: #344054;
            font-size: 13px;
            line-height: 1.4;
            font-weight: 800;
            white-space: nowrap;
        }

        .insight-resource-layout {
            display: grid;
            place-items: center;
            flex: 1 1 auto;
            min-height: 364px;
            padding: 12px 0 8px;
        }

        .insight-ring-shell {
            display: grid;
            place-items: center;
            width: 100%;
            gap: 22px;
            min-height: 0;
        }

        .insight-ring-stage {
            position: relative;
            display: grid;
            place-items: center;
            width: 194px;
            height: 194px;
        }

        .insight-ring {
            --used-ratio: 0;
            position: relative;
            width: 194px;
            height: 194px;
            border-radius: 50%;
            background:
                radial-gradient(circle at center, #ffffff 0 66px, transparent 67px),
                conic-gradient(
                    #22c55e 0 calc(var(--used-ratio) * 1%),
                    #f59e0b calc(var(--used-ratio) * 1%) 100%
                );
            box-shadow:
                inset 0 0 0 12px rgba(255, 255, 255, 0.84),
                0 22px 42px rgba(15, 23, 42, 0.08);
            transition: transform 0.22s ease, filter 0.22s ease, box-shadow 0.22s ease;
        }

        .insight-ring-shell:hover .insight-ring {
            transform: scale(1.03);
            filter: saturate(1.04);
            box-shadow:
                inset 0 0 0 12px rgba(255, 255, 255, 0.88),
                0 28px 48px rgba(15, 23, 42, 0.12);
        }

        .insight-ring-center {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 126px;
            height: 126px;
            display: grid;
            place-items: center;
            text-align: center;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.97);
            box-shadow: inset 0 0 0 1px rgba(226, 232, 240, 0.85), 0 8px 20px rgba(15, 23, 42, 0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .insight-ring-value {
            color: #111827;
            font-size: 34px;
            line-height: 1;
            font-weight: 900;
        }

        .insight-ring-label {
            margin-top: 6px;
            color: #8b94a7;
            font-size: 12px;
            line-height: 1.5;
            font-weight: 700;
        }

        .insight-ring-shell[data-active-segment="used"] .insight-ring-center,
        .insight-ring-shell[data-active-segment="unused"] .insight-ring-center {
            transform: translate(-50%, -50%) scale(1.03);
            box-shadow: inset 0 0 0 1px rgba(203, 213, 225, 0.88), 0 10px 24px rgba(15, 23, 42, 0.08);
        }

        .insight-ring-hit {
            position: absolute;
            top: 50%;
            width: 134px;
            height: 158px;
            border-radius: 14px;
            transform: translateY(-50%);
            cursor: pointer;
        }

        .insight-ring-hit.is-used {
            right: -48px;
            border-radius: 0 120px 120px 0;
        }

        .insight-ring-hit.is-unused {
            left: -48px;
            border-radius: 120px 0 0 120px;
        }

        .insight-ring-legend {
            width: min(100%, 320px);
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .insight-resource-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .insight-resource-capacity {
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.6;
            text-align: right;
            font-weight: 600;
            white-space: nowrap;
        }

        .insight-ring-legend-card {
            display: grid;
            gap: 8px;
            min-height: 86px;
            padding: 14px 14px 12px;
            border-radius: 18px;
            border: 1px solid rgba(226, 232, 240, 0.92);
            background: rgba(248, 250, 252, 0.96);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
        }

        .insight-ring-legend-card:hover,
        .insight-ring-legend-card:focus-visible,
        .insight-ring-shell[data-active-segment="used"] .insight-ring-legend-card.is-used,
        .insight-ring-shell[data-active-segment="unused"] .insight-ring-legend-card.is-unused {
            transform: translateY(-2px);
            box-shadow: 0 18px 30px rgba(15, 23, 42, 0.08);
        }

        .insight-ring-legend-card.is-used:hover,
        .insight-ring-legend-card.is-used:focus-visible,
        .insight-ring-shell[data-active-segment="used"] .insight-ring-legend-card.is-used {
            border-color: rgba(34, 197, 94, 0.32);
            background: rgba(240, 253, 244, 0.96);
        }

        .insight-ring-legend-card.is-unused:hover,
        .insight-ring-legend-card.is-unused:focus-visible,
        .insight-ring-shell[data-active-segment="unused"] .insight-ring-legend-card.is-unused {
            border-color: rgba(245, 158, 11, 0.3);
            background: rgba(255, 247, 237, 0.98);
        }

        .insight-ring-hover-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: var(--ring-dot, #94a3b8);
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--ring-dot, #94a3b8) 18%, transparent 82%);
        }

        .insight-ring-legend-top {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #667085;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 700;
        }

        .insight-ring-legend-value {
            color: #111827;
            font-size: 24px;
            line-height: 1;
            font-weight: 900;
            letter-spacing: -0.03em;
        }

        .insight-ring-legend-note {
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.5;
            font-weight: 700;
        }

        .insight-ring-hover-value {
            color: #111827;
            font-size: 15px;
            font-weight: 900;
        }

        @media (max-width: 1180px) {
            .insights-hero-grid,
            .insights-board {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .insight-board-card.is-trend-card,
            .insight-board-card.is-article-rank-card,
            .insight-board-card.is-assets-card,
            .insight-board-card.is-author-card,
            .insight-board-card.is-notice-card {
                grid-column: auto;
                grid-row: auto;
            }
        }

        @media (max-width: 860px) {
            .insights-hero-grid,
            .insights-board {
                grid-template-columns: 1fr;
                display: grid;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $today = \Illuminate\Support\Carbon::now('Asia/Shanghai');
        $solarDateLabel = $today->translatedFormat('Y年n月j日');
        $lunarMonthNumber = null;
        $lunarDayNumber = null;
        $lunarLabel = null;

        if (class_exists(\IntlDateFormatter::class)) {
            try {
                $lunarMonthFormatter = new \IntlDateFormatter('zh_CN@calendar=chinese', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'Asia/Shanghai', \IntlDateFormatter::TRADITIONAL, 'M');
                $lunarDayFormatter = new \IntlDateFormatter('zh_CN@calendar=chinese', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'Asia/Shanghai', \IntlDateFormatter::TRADITIONAL, 'd');
                $lunarMonthNumber = (int) $lunarMonthFormatter->format($today);
                $lunarDayNumber = (int) $lunarDayFormatter->format($today);
            } catch (\Throwable $exception) {
                $lunarMonthNumber = null;
                $lunarDayNumber = null;
            }
        }

        if ($lunarMonthNumber && $lunarDayNumber) {
            $lunarMonths = [1 => '正月', 2 => '二月', 3 => '三月', 4 => '四月', 5 => '五月', 6 => '六月', 7 => '七月', 8 => '八月', 9 => '九月', 10 => '十月', 11 => '冬月', 12 => '腊月'];
            $lunarDays = [
                1 => '初一', 2 => '初二', 3 => '初三', 4 => '初四', 5 => '初五', 6 => '初六', 7 => '初七', 8 => '初八', 9 => '初九', 10 => '初十',
                11 => '十一', 12 => '十二', 13 => '十三', 14 => '十四', 15 => '十五', 16 => '十六', 17 => '十七', 18 => '十八', 19 => '十九', 20 => '二十',
                21 => '廿一', 22 => '廿二', 23 => '廿三', 24 => '廿四', 25 => '廿五', 26 => '廿六', 27 => '廿七', 28 => '廿八', 29 => '廿九', 30 => '三十',
            ];
            $lunarLabel = '农历' . ($lunarMonths[$lunarMonthNumber] ?? ($lunarMonthNumber . '月')) . ($lunarDays[$lunarDayNumber] ?? ($lunarDayNumber . '日'));
        }

        $hour = (int) $today->format('G');
        $timeGreeting = match (true) {
            $hour < 6 => '凌晨好',
            $hour < 9 => '早上好',
            $hour < 12 => '上午好',
            $hour < 14 => '中午好',
            $hour < 19 => '下午好',
            default => '晚上好',
        };

        $greetings = [
            '记得照顾好自己，别让今天过得太匆忙。',
            '慢一点也没关系，先让自己舒服一点。',
            '天气和心情都值得被认真对待，愿你今天顺顺的。',
            '别太赶，按自己的节奏来就很好。',
            '忙的时候也记得喝口水，歇一歇。',
            '今天也希望你心里松一点，事情顺一点。',
            '先照顾好自己的状态，其他事情都会慢慢跟上。',
            '愿你今天遇到的人和事，都温和一点。',
            '累了就停一下，不必一直绷着。',
            '希望今天的你，做事顺手，心里也轻松。',
            '别给自己太多压力，慢慢来一样很好。',
            '愿你今天有一点好消息，也有一点小轻松。',
        ];
        $operatorName = auth()->user()?->real_name ?? auth()->user()?->name ?? auth()->user()?->username ?? '管理员';
        $operatorId = (int) (auth()->user()?->id ?? 0);
        $greetingIndex = (($today->dayOfYear ?? 1) + (int) ($currentSite->id ?? 0) + $operatorId) % count($greetings);
        $headerGreeting = $greetings[$greetingIndex];
        $headerDateLine = '今天是：' . $solarDateLabel . ($lunarLabel ? '，' . $lunarLabel . '。' : '。');
        $headerGreetingLine = $timeGreeting . '，' . $operatorName . '，' . $headerGreeting;
    @endphp

    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">站点工作台</h2>
            <div class="page-header-desc">{{ $headerDateLine }} {{ $headerGreetingLine }}</div>
        </div>
    </section>

    <section class="insights-content">
        <div class="insights-hero-grid">
            @foreach ($insights['hero'] as $metric)
                <article class="insight-hero-card is-{{ $metric['accent'] }}">
                    <div class="insight-hero-top">
                        <div class="insight-hero-label">{{ $metric['label'] }}</div>
                        <div class="insight-hero-top-right">
                            <div class="insight-hero-icon" aria-hidden="true">
                                @if ($metric['accent'] === 'visits')
                                    <svg viewBox="0 0 24 24">
                                        <circle cx="12" cy="8" r="3.2"></circle>
                                        <path d="M5.5 19.2c0-3.3 2.9-5.5 6.5-5.5s6.5 2.2 6.5 5.5"></path>
                                    </svg>
                                @elseif ($metric['accent'] === 'trend')
                                    <svg viewBox="0 0 24 24">
                                        <path d="M4 18V6"></path>
                                        <path d="M4 18h16"></path>
                                        <path d="m7 14 3-3 3 2 4-5"></path>
                                    </svg>
                                @elseif ($metric['accent'] === 'security')
                                    <svg viewBox="0 0 24 24">
                                        <path d="M12 3 5.5 6v5.2c0 4.1 2.5 7.7 6.5 9.8 4-2.1 6.5-5.7 6.5-9.8V6L12 3Z"></path>
                                        <path d="m9.5 12 1.8 1.8 3.4-3.6"></path>
                                    </svg>
                                @else
                                    <svg viewBox="0 0 24 24">
                                        <path d="M12 3 5.5 6v5.2c0 4.1 2.5 7.7 6.5 9.8 4-2.1 6.5-5.7 6.5-9.8V6L12 3Z"></path>
                                        <path d="M12 9v3.8"></path>
                                        <path d="M12 16h.01"></path>
                                    </svg>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="insight-hero-value">{{ $metric['value'] }}</div>
                    <div class="insight-hero-note">{{ $metric['note'] }}</div>
                </article>
            @endforeach
        </div>

        <div class="insights-board">
            <article class="insight-board-card is-trend-card">
                <div>
                    <h3 class="insight-board-card-title">近 7 天访问趋势</h3>
                </div>
                <div class="insight-trend">
                    @foreach ($insights['trend'] as $trendItem)
                        <div class="insight-trend-item" data-tooltip="{{ $trendItem['label'] }} · {{ number_format($trendItem['value']) }} PV">
                            <div class="insight-trend-bar" style="height: {{ $trendItem['height'] }}px;"></div>
                            <div class="insight-trend-label">{{ $trendItem['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="insight-board-card is-article-rank-card">
                <div class="insight-board-card-head">
                    <h3 class="insight-board-card-title">文章访问排行</h3>
                    <span class="insight-board-card-tag">30天内</span>
                </div>
                <div class="insight-rank-list">
                    @forelse ($insights['top_articles'] as $index => $article)
                        <div class="insight-rank-item">
                            <div class="insight-rank-no">{{ $index + 1 }}</div>
                            <div class="insight-rank-main">
                                <div class="insight-rank-title">{{ $article['title'] }}</div>
                                <div class="insight-rank-subtitle">{{ $article['channel_name'] }}</div>
                                <div class="insight-rank-bar-track">
                                    <div class="insight-rank-bar" style="width: {{ $article['bar_width'] }}%;"></div>
                                </div>
                            </div>
                            <div class="insight-rank-value">{{ number_format($article['view_count']) }}</div>
                        </div>
                    @empty
                        <div class="recent-feed-empty">当前站点还没有文章访问数据。</div>
                    @endforelse
                </div>
            </article>

            <article class="insight-board-card is-author-card">
                <div class="insight-board-card-head">
                    <h3 class="insight-board-card-title">作者发布排行</h3>
                    <span class="insight-board-card-tag">本年度</span>
                </div>
                <div class="insight-rank-list">
                    @forelse ($insights['top_authors'] as $index => $author)
                        <div class="insight-rank-item">
                            <div class="insight-rank-no">{{ $index + 1 }}</div>
                            <div class="insight-rank-main">
                                <div class="insight-rank-title">{{ $author['name'] }}</div>
                                <div class="insight-rank-subtitle">已发布 {{ $author['published_count'] }} 篇</div>
                                <div class="insight-rank-bar-track">
                                    <div class="insight-rank-bar" style="width: {{ $author['bar_width'] }}%;"></div>
                                </div>
                            </div>
                            <div class="insight-rank-value">{{ $author['total_count'] }} 篇</div>
                        </div>
                    @empty
                        <div class="recent-feed-empty">近 30 天还没有新的发布记录。</div>
                    @endforelse
                </div>
            </article>

            <article class="insight-board-card is-recent-card">
                <div class="panel-heading">
                    <h3 class="insight-board-card-title">近期文章</h3>
                    <div class="panel-heading-actions">
                        <a class="button dashboard-create-button dashboard-action-button" href="{{ route('admin.articles.create') }}">新建文章</a>
                        <a class="button dashboard-create-button dashboard-action-button" href="{{ route('admin.articles.index', ['status' => 'draft', 'mine' => 'drafts']) }}">我的草稿</a>
                    </div>
                </div>
                @if ($recentContents->isNotEmpty())
                    <div class="recent-feed">
                        @foreach ($recentContents as $content)
                            @php
                                $recentContentTitle = (string) $content->title;
                                $recentContentTitleDisplay = mb_strlen($recentContentTitle, 'UTF-8') > 15
                                    ? mb_substr($recentContentTitle, 0, 15, 'UTF-8') . '...'
                                    : $recentContentTitle;
                            @endphp
                            <article class="recent-feed-item" @if ($canManageContent) onclick="window.location.href='{{ $content->type === 'page' ? route('admin.pages.edit', $content->id) : route('admin.articles.edit', $content->id) }}'" style="cursor:pointer;" @endif>
                                <div class="recent-feed-main">
                                    @if ($canManageContent)
                                        <a class="recent-feed-title" href="{{ $content->type === 'page' ? route('admin.pages.edit', $content->id) : route('admin.articles.edit', $content->id) }}" onclick="event.stopPropagation()" data-tooltip="{{ $recentContentTitle }}">
                                            {{ $recentContentTitleDisplay }}
                                        </a>
                                    @else
                                        <div class="recent-feed-title" data-tooltip="{{ $recentContentTitle }}">{{ $recentContentTitleDisplay }}</div>
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
            </article>

            <article class="insight-board-card is-assets-card">
                <div class="insight-resource-head">
                    <h3 class="insight-board-card-title">资源使用情况</h3>
                    <div class="insight-resource-capacity">已用 {{ $insights['assets']['used_size_label'] }} / {{ $insights['assets']['storage_limit_label'] }}</div>
                </div>
                <div class="insight-resource-layout">
                    <div
                        class="insight-ring-shell"
                        data-insight-ring
                        data-default-value="{{ number_format($insights['assets']['unused']) }}"
                        data-default-label="未引用资源"
                        data-default-detail="总资源 {{ number_format($insights['assets']['total']) }}"
                    >
                        <div class="insight-ring-stage">
                            <div class="insight-ring" style="--used-ratio: {{ $insights['assets']['used_ratio'] }};"></div>
                            <div class="insight-ring-center">
                                <div>
                                    <div class="insight-ring-value" data-insight-ring-value>{{ number_format($insights['assets']['unused']) }}</div>
                                    <div class="insight-ring-label" data-insight-ring-label>未引用资源</div>
                                </div>
                            </div>
                            <div
                                class="insight-ring-hit is-used"
                                data-insight-segment
                                data-segment="used"
                                data-value="{{ number_format($insights['assets']['used']) }}"
                                data-label="已引用资源"
                                data-detail="占比 {{ $insights['assets']['used_ratio'] }}%"
                            ></div>
                            <div
                                class="insight-ring-hit is-unused"
                                data-insight-segment
                                data-segment="unused"
                                data-value="{{ number_format($insights['assets']['unused']) }}"
                                data-label="未引用资源"
                                data-detail="占比 {{ $insights['assets']['unused_ratio'] }}%"
                            ></div>
                        </div>
                        <div class="insight-ring-legend">
                            <div
                                class="insight-ring-legend-card is-used"
                                tabindex="0"
                                data-insight-segment
                                data-segment="used"
                                data-value="{{ number_format($insights['assets']['used']) }}"
                                data-label="已引用资源"
                                data-detail="占比 {{ $insights['assets']['used_ratio'] }}%"
                            >
                                <div class="insight-ring-legend-top">
                                    <span class="insight-ring-hover-dot" style="--ring-dot:#22c55e;"></span>
                                    <span>已引用</span>
                                </div>
                                <div class="insight-ring-legend-value">{{ number_format($insights['assets']['used']) }}</div>
                                <div class="insight-ring-legend-note">占比 {{ $insights['assets']['used_ratio'] }}%</div>
                            </div>
                            <div
                                class="insight-ring-legend-card is-unused"
                                tabindex="0"
                                data-insight-segment
                                data-segment="unused"
                                data-value="{{ number_format($insights['assets']['unused']) }}"
                                data-label="未引用资源"
                                data-detail="占比 {{ $insights['assets']['unused_ratio'] }}%"
                            >
                                <div class="insight-ring-legend-top">
                                    <span class="insight-ring-hover-dot" style="--ring-dot:#f59e0b;"></span>
                                    <span>未引用</span>
                                </div>
                                <div class="insight-ring-legend-value">{{ number_format($insights['assets']['unused']) }}</div>
                                <div class="insight-ring-legend-note">占比 {{ $insights['assets']['unused_ratio'] }}%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </article>

            <article class="insight-board-card is-notice-card">
                <div class="insight-board-card-head">
                    <h3 class="insight-board-card-title">官闪闪公告栏</h3>
                    @if ($showPlatformNoticeLink)
                        <a class="insight-board-card-tag" href="{{ route('site.channel', ['slug' => 'platform-notices', 'site' => $platformNoticeSiteKey]) }}" target="_blank">更多</a>
                    @endif
                </div>
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
                                    @php
                                        $noticeTitleText = (string) $notice->title;
                                        $noticeTitleDisplay = mb_strlen($noticeTitleText, 'UTF-8') > 30
                                            ? mb_substr($noticeTitleText, 0, 30, 'UTF-8') . '...'
                                            : $noticeTitleText;
                                    @endphp
                                    <span class="notice-item-title-text" style="{{ implode('; ', $noticeTitleStyle) }}" data-tooltip="{{ $noticeTitleText }}">{{ $noticeTitleDisplay }}</span>
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
            </article>
        </div>
    </section>

    <div class="notice-modal" id="platform-notice-modal" hidden>
        <div class="notice-modal-backdrop" data-notice-close></div>
        <div class="notice-modal-shell" data-notice-shell>
            <div class="notice-modal-panel" role="dialog" aria-modal="true" aria-labelledby="platform-notice-modal-title">
                <div class="notice-modal-scroll">
                    <div class="notice-modal-inner">
                        <div class="notice-modal-topbar">
                        <div class="notice-modal-heading">
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

            document.querySelectorAll('[data-insight-ring]').forEach((ringShell) => {
                const valueNode = ringShell.querySelector('[data-insight-ring-value]');
                const labelNode = ringShell.querySelector('[data-insight-ring-label]');
                const defaultState = {
                    value: ringShell.dataset.defaultValue || '--',
                    label: ringShell.dataset.defaultLabel || '',
                    segment: 'default',
                };

                if (!valueNode || !labelNode) {
                    return;
                }

                const applyState = (state) => {
                    valueNode.textContent = state.value;
                    labelNode.textContent = state.label;
                    ringShell.dataset.activeSegment = state.segment;
                };

                applyState(defaultState);

                ringShell.querySelectorAll('[data-insight-segment]').forEach((segment) => {
                    const segmentState = {
                        value: segment.dataset.value || '--',
                        label: segment.dataset.label || '',
                        segment: segment.dataset.segment || 'default',
                    };

                    segment.addEventListener('mouseenter', () => applyState(segmentState));
                    segment.addEventListener('focus', () => applyState(segmentState));
                    segment.addEventListener('mouseleave', () => applyState(defaultState));
                    segment.addEventListener('blur', () => applyState(defaultState));
                });
            });
        })();
    </script>
@endpush
