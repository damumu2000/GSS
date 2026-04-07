@php
    $adminBrandSettings = app(\App\Support\SystemSettings::class)->formDefaults();
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $adminBrandSettings['system_name'])</title>
    @if (! empty($adminBrandSettings['admin_favicon']))
        <link rel="icon" href="{{ $adminBrandSettings['admin_favicon'] }}">
    @endif
    @stack('styles')
    <style>
        :root {
            --bg: #f5f7fa;
            --panel: #ffffff;
            --panel-soft: #fafafa;
            --surface-hover: #fafafa;
            --text: #262626;
            --muted: #8c8c8c;
            --line: #f0f0f0;
            --line-strong: #e8e8e8;
            --color-primary: #0050b3;
            --color-primary-light: #e6f7ff;
            --color-text-main: #262626;
            --group-title-color: rgba(0, 80, 179, 0.5);
            --primary: var(--color-primary);
            --primary-bg: var(--color-primary-light);
            --primary-soft: rgba(0, 80, 179, 0.06);
            --primary-soft-strong: rgba(0, 80, 179, 0.12);
            --primary-dark: #003a8c;
            --primary-border-soft: rgba(0, 80, 179, 0.28);
            --tag-bg: rgba(0, 80, 179, 0.08);
            --tag-text: #0050b3;
            --danger: #ff4d4f;
            --danger-soft: #fff1f0;
            --success-soft: #f6ffed;
            --sidebar-bg: #ffffff;
            --sidebar-text: #595959;
            --sidebar-muted: #8c8c8c;
            --shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
            --sidebar-width: 220px;
            --header-height: 64px;
            --control-height: 38px;
            --control-radius: 8px;
        }

        * { box-sizing: border-box; }

        input[type="checkbox"] {
            accent-color: var(--primary);
        }

        input[type="checkbox"]:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-soft-strong);
            border-radius: 4px;
        }

        body {
            margin: 0;
            font-family: "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
            color: var(--text);
            background: var(--bg);
        }

        a { color: inherit; text-decoration: none; }

        .shell {
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: var(--sidebar-width);
            padding: 20px 14px 18px;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            border-right: 1px solid #f0f0f0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            scrollbar-width: none;
        }

        .sidebar::-webkit-scrollbar {
            width: 0;
            height: 0;
        }

        .brand {
            padding: 14px 8px 16px 10px;
            margin-bottom: 12px;
        }

        .brand-logo {
            display: inline-flex;
            align-items: center;
            max-width: 100%;
        }

        .brand-logo img {
            display: block;
            width: auto;
            max-width: 168px;
            max-height: 36px;
            object-fit: contain;
        }

        .brand h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            line-height: 1.35;
            color: #262626;
        }

        .sidebar-nav {
            flex: 1;
        }

        .menu-title {
            height: 32px;
            margin: 24px 0 8px;
            padding: 0 14px 0 16px;
            color: #595959;
            font-size: 12px;
            line-height: 32px;
            font-weight: 600;
            letter-spacing: 0.01em;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }

        .menu-link {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            height: 40px;
            padding: 0 16px;
            margin-bottom: 2px;
            border-radius: 8px;
            color: #595959;
            font-size: 13px;
            line-height: 40px;
            transition: background 0.18s ease, color 0.18s ease;
        }

        .menu-link-label {
            min-width: 0;
            display: inline-flex;
            align-items: center;
        }

        .menu-link-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 0;
            height: auto;
            padding: 0;
            margin-left: -8px;
            background: transparent;
            color: #ef4444;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            transform: translateY(-5px);
            pointer-events: none;
        }

        .menu-link.active .menu-link-badge {
            color: #ef4444;
        }

        .menu-link-badge.is-title {
            color: currentColor;
        }

        .menu-link.active .menu-link-badge.is-title {
            color: var(--primary);
        }

        .menu-link::before {
            content: "";
            position: absolute;
            left: 0;
            top: 50%;
            width: 3px;
            height: 16px;
            border-radius: 2px;
            background: var(--primary);
            opacity: 0;
            transform: translateY(-50%);
            transition: opacity 0.18s ease;
        }

        .menu-link:hover {
            background: #fafafa;
            color: #595959;
        }

        .menu-link.active {
            background: transparent;
            color: var(--primary);
            font-weight: 600;
        }

        .menu-link.active::before {
            opacity: 1;
        }

        .menu-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            color: #8c8c8c;
            flex-shrink: 0;
        }

        .menu-link.active .menu-icon,
        .menu-link.active .menu-icon svg {
            color: var(--primary);
        }

        .menu-icon svg,
        .button-icon svg,
        .user-tag-icon svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .sidebar-bottom {
            margin-top: 28px;
            padding: 18px 12px 12px;
            border-top: 1px solid #f0f0f0;
        }

        .sidebar-user {
            color: #8c8c8c;
            font-size: 12px;
            line-height: 1.8;
        }

        .sidebar-user strong {
            display: block;
            color: #262626;
            font-size: 13px;
            font-weight: 600;
        }

        .workspace {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .app-header {
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            height: var(--header-height);
            z-index: 1000;
            background: #ffffff;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 0 28px;
        }

        .breadcrumb {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .header-user-menu {
            position: relative;
            display: inline-flex;
            flex-shrink: 0;
        }

        .header-user-trigger {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 40px;
            padding: 4px 12px 4px 8px;
            border: 1px solid transparent;
            border-radius: 14px;
            background: #ffffff;
            color: var(--text);
            cursor: pointer;
            transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }

        .header-user-trigger:hover,
        .header-user-menu.is-open .header-user-trigger {
            border-color: var(--primary-border-soft);
            background: #fbfdff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            transform: translateY(-1px);
        }

        .header-user-trigger:focus-visible {
            outline: none;
            border-color: var(--primary-border-soft);
            box-shadow: 0 0 0 3px var(--primary-soft-strong);
        }

        .header-user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary) 0%, color-mix(in srgb, var(--primary) 78%, #ffffff 22%) 100%);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.22);
        }

        .header-user-avatar svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.9;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .header-user-copy {
            display: grid;
            gap: 2px;
            text-align: left;
            min-width: 0;
        }

        .header-user-name {
            color: var(--text);
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
        }

        .header-user-role {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.2;
            white-space: nowrap;
        }

        .header-user-caret {
            width: 14px;
            height: 14px;
            color: #98a2b3;
            flex-shrink: 0;
            transition: transform 0.18s ease, color 0.18s ease;
        }

        .header-user-menu.is-open .header-user-caret {
            color: var(--primary);
            transform: rotate(180deg);
        }

        .header-user-caret svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.9;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .header-user-panel {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 220px;
            padding: 10px;
            border: 1px solid rgba(229, 231, 235, 0.9);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            opacity: 0;
            pointer-events: none;
            transform: translateY(-4px);
            transition: opacity 0.18s ease, transform 0.18s ease;
            z-index: 1450;
            backdrop-filter: blur(10px);
        }

        .header-user-menu.is-open .header-user-panel {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }

        .header-user-panel-head {
            padding: 10px 12px 12px;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 8px;
        }

        .header-user-panel-name {
            color: #111827;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.4;
        }

        .header-user-panel-meta {
            margin-top: 4px;
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.5;
        }

        .header-user-panel-list {
            display: grid;
            gap: 4px;
        }

        .header-user-panel-link,
        .header-user-panel-button {
            width: 100%;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 42px;
            padding: 0 12px;
            border: 0;
            border-radius: 12px;
            background: transparent;
            color: #374151;
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            text-align: left;
            cursor: pointer;
            transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease;
        }

        .header-user-panel-link:hover,
        .header-user-panel-button:hover {
            background: #f8fafc;
            color: var(--primary);
            transform: translateX(1px);
        }

        .header-user-panel-button.is-danger:hover {
            background: rgba(255, 77, 79, 0.08);
            color: #d92d20;
        }

        .header-user-panel-link svg,
        .header-user-panel-button svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.9;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
        }

        .theme-switcher {
            position: relative;
            display: inline-flex;
            flex-shrink: 0;
        }

        .theme-switcher-trigger {
            width: 36px;
            height: 36px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #ffffff;
            color: var(--muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: border-color 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .theme-switcher-trigger:hover,
        .theme-switcher.is-open .theme-switcher-trigger {
            border-color: var(--primary-border-soft);
            background: var(--primary-bg);
            color: var(--primary);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
        }

        .theme-switcher-panel {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 440px;
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: none;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            opacity: 0;
            pointer-events: none;
            transform: translateY(-4px);
            transition: opacity 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 1400;
        }

        .theme-switcher.is-open .theme-switcher-panel {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }

        .theme-switcher-title {
            margin: 0 0 20px;
            color: #4b5563;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .theme-switcher-title::before {
            content: "";
            width: 3px;
            height: 14px;
            border-radius: 999px;
            background: var(--primary);
            flex-shrink: 0;
        }

        .theme-switcher-options {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .theme-switcher-item {
            display: block;
        }

        .theme-swatch {
            width: 100%;
            padding: 0;
            border: 0;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.92);
            box-shadow: none;
            outline: 1px solid #f3f4f6;
            cursor: pointer;
            overflow: hidden;
            transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), outline-color 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .theme-swatch:hover,
        .theme-swatch.is-active {
            transform: translateY(-4px);
            box-shadow:
                0 16px 28px -10px var(--swatch-glow, rgba(59, 130, 246, 0.28)),
                0 10px 22px -12px var(--swatch-glow, rgba(59, 130, 246, 0.22));
            outline-color: #ebeef2;
        }

        .theme-swatch-preview {
            position: relative;
            height: 78px;
            background:
                linear-gradient(135deg,
                    color-mix(in srgb, var(--swatch-color) 90%, #ffffff 10%) 0%,
                    var(--swatch-color) 52%,
                    color-mix(in srgb, var(--swatch-color) 90%, #000000 10%) 100%);
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            padding: 10px;
            border-radius: 12px 12px 0 0;
            box-shadow:
                inset 0 0 0 1px rgba(255, 255, 255, 0.18),
                inset 0 1px 0 rgba(255, 255, 255, 0.24),
                inset 0 -18px 28px rgba(255, 255, 255, 0.08);
        }

        .theme-swatch-preview::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.28) 0%, rgba(255, 255, 255, 0.08) 34%, transparent 66%),
                linear-gradient(135deg, rgba(255, 255, 255, 0.16) 0%, transparent 38%, rgba(255, 255, 255, 0.06) 100%);
            pointer-events: none;
        }

        .theme-swatch-icon {
            width: 18px;
            height: 18px;
            color: rgba(255, 255, 255, 0.92);
        }

        .theme-swatch-icon svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.9;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .theme-swatch-name {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 14px 12px 14px;
            color: #595959;
            font-size: 12px;
            line-height: 1.45;
            font-weight: 700;
            text-align: left;
        }

        .theme-swatch-label {
            min-width: 0;
        }

        .theme-swatch-status {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.92);
            box-shadow: none;
            opacity: 0;
            flex-shrink: 0;
            transform: scale(0.85);
            transition: opacity 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .theme-swatch.is-active .theme-swatch-status {
            opacity: 1;
            transform: scale(1);
            background: #52c41a;
            box-shadow:
                0 0 0 0 rgba(82, 196, 26, 0.24),
                0 0 16px rgba(82, 196, 26, 0.24);
            animation: theme-status-pulse 1.8s ease-out infinite;
        }

        @keyframes theme-status-pulse {
            0% { box-shadow: 0 0 0 0 rgba(82, 196, 26, 0.28), 0 0 16px rgba(82, 196, 26, 0.24); }
            70% { box-shadow: 0 0 0 8px rgba(82, 196, 26, 0), 0 0 18px rgba(82, 196, 26, 0.12); }
            100% { box-shadow: 0 0 0 0 rgba(82, 196, 26, 0), 0 0 16px rgba(82, 196, 26, 0.18); }
        }

        .button-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .content {
            padding: calc(var(--header-height) + 28px) 28px 36px;
            min-width: 0;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .topbar h2 {
            margin: 0;
            font-size: 30px;
            line-height: 1.25;
            color: var(--text);
        }

        .topbar-subtitle,
        .muted,
        .helper-text {
            color: var(--muted);
        }

        .topbar-subtitle {
            margin-top: 8px;
            font-size: 14px;
            line-height: 1.8;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .page-eyebrow {
            color: var(--primary-dark);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .card,
        .panel {
            background: var(--panel);
            border-radius: 12px;
            box-shadow: var(--shadow);
        }

        .card {
            padding: 18px;
        }

        .card-label {
            font-size: 13px;
            color: var(--muted);
        }

        .card-value {
            margin-top: 10px;
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
        }

        .panel {
            padding: 20px;
        }

        .panel + .panel {
            margin-top: 16px;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 16px;
        }

        .panel-title {
            margin: 0;
            font-size: 18px;
            line-height: 1.35;
            color: var(--text);
        }

        .panel-subtitle {
            margin-top: 4px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 12px;
            font-weight: 600;
        }

        .table-wrap {
            overflow-x: auto;
            border-radius: 12px;
            background: #ffffff;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--line);
            font-size: 14px;
            vertical-align: middle;
        }

        .table th {
            color: var(--muted);
            font-weight: 600;
            background: #fafafa;
            white-space: nowrap;
        }

        .table tbody tr:hover {
            background: #fcfefe;
        }

        .table-admin tbody tr:last-child td {
            border-bottom: 0;
        }

        .table-check {
            width: 44px;
            text-align: center;
        }

        .table-actions {
            width: 140px;
        }

        .cell-title {
            font-weight: 600;
            line-height: 1.5;
            color: var(--text);
        }

        .pill {
            display: inline-flex;
            padding: 4px 10px;
            border-radius: 4px;
            background: #f5f7fa;
            color: #4b5563;
            font-size: 12px;
            font-weight: 600;
        }

        .stack {
            display: grid;
            gap: 16px;
        }

        .split {
            display: grid;
            grid-template-columns: 1.25fr 0.95fr;
            gap: 18px;
        }

        .split-form {
            grid-template-columns: 1fr 1fr;
        }

        .theme-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .theme-card .button[disabled] {
            opacity: 0.72;
            cursor: default;
        }

        .theme-preview {
            border-radius: 20px;
            overflow: hidden;
            background: linear-gradient(180deg, #f3fffc 0%, #e6faf7 100%);
        }

        .theme-preview-bar {
            height: 22px;
            background: #0a8f8f;
        }

        .theme-preview-body {
            padding: 18px;
        }

        .theme-preview-hero {
            height: 90px;
            border-radius: 16px;
            background: linear-gradient(135deg, #ffffff 0%, #dbf7f3 100%);
        }

        .theme-preview-columns {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 14px;
        }

        .theme-preview-columns span {
            display: block;
            height: 70px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.82);
        }

        .field,
        textarea,
        select,
        input[type="text"],
        input[type="password"] {
            width: 100%;
            min-height: var(--control-height);
            padding: 8px 12px;
            border: 1px solid var(--line-strong);
            border-radius: var(--control-radius);
            background: #fff;
            font: inherit;
            font-size: 14px;
            line-height: 1.5;
            color: inherit;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .field.is-error,
        textarea.is-error,
        select.is-error,
        input[type="text"].is-error,
        input[type="password"].is-error {
            border-color: #ff4d4f;
            box-shadow: 0 0 0 3px rgba(255, 77, 79, 0.12);
        }

        .field:focus,
        textarea:focus,
        select:focus,
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--primary-border-soft);
            box-shadow: 0 0 0 3px var(--primary-soft-strong);
        }

        .textarea {
            min-height: 96px;
            resize: vertical;
        }

        .textarea-lg {
            min-height: 220px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: var(--control-height);
            padding: 8px 14px;
            border: 1px solid transparent;
            border-radius: var(--control-radius);
            background: var(--primary);
            color: #fff;
            font: inherit;
            font-size: 14px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.22s ease, border-color 0.22s ease, color 0.22s ease, filter 0.22s ease, transform 0.22s ease;
        }

        .button:hover {
            background: var(--primary-dark);
            filter: saturate(1.03);
            transform: translateY(-1px);
        }

        .button:active {
            filter: saturate(0.98);
            transform: translateY(0);
        }

        .button.is-loading,
        .button[disabled] {
            opacity: 0.78;
            cursor: default;
        }

        .button.secondary {
            background: #fafafa;
            color: var(--text);
            border: 1px solid var(--line);
        }

        .button.secondary:hover {
            background: #eceff3;
            color: var(--text);
        }

        .button.neutral-action,
        .button.neutral-action:visited {
            background: #f9fafb;
            border-color: #e5e7eb;
            color: #6b7280;
            font-size: 13px;
            box-shadow: none;
            transition: all 0.3s ease-in-out;
        }

        .button.neutral-action:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(0, 71, 171, 0.18);
        }

        .button.neutral-action:active {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            color: #ffffff;
            transform: translateY(0);
            box-shadow: 0 6px 16px rgba(0, 71, 171, 0.16);
        }

        .badge-soft {
            display: inline-flex;
            align-items: center;
            min-height: 22px;
            padding: 0 10px;
            border-radius: 999px;
            background: var(--tag-bg);
            color: var(--tag-text);
            font-size: 12px;
            line-height: 22px;
            font-weight: 600;
        }

        .badge-soft.muted {
            background: #f5f7fa;
            color: #8c8c8c;
        }

        .button.ghost {
            background: transparent;
            color: var(--muted);
            border-color: transparent;
        }

        .button.ghost:hover {
            background: rgba(0, 0, 0, 0.03);
            color: var(--text);
        }

        .button-spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 999px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-top-color: #ffffff;
            animation: button-spin 0.7s linear infinite;
        }

        .button.secondary .button-spinner {
            border-color: var(--primary-soft-strong);
            border-top-color: var(--primary-dark);
        }

        @keyframes button-spin {
            to { transform: rotate(360deg); }
        }

        .button.danger {
            background: var(--danger);
        }

        .button-link {
            padding: 0;
            border: 0;
            background: transparent;
            color: var(--primary-dark);
            cursor: pointer;
            font: inherit;
        }

        .button-link.danger {
            color: var(--danger);
        }

        .icon-button {
            width: 32px;
            height: 32px;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: var(--muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: color 0.18s ease, background 0.18s ease;
        }

        .icon-button:hover {
            background: rgba(0, 0, 0, 0.03);
            color: var(--text);
        }

        .icon-button.danger:hover {
            color: #ff7875;
            background: rgba(255, 77, 79, 0.08);
        }

        .icon-button svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .action-menu {
            position: relative;
            display: inline-flex;
        }

        .action-menu-panel {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 160px;
            padding: 8px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
            opacity: 0;
            pointer-events: none;
            transform: translateY(-4px);
            transition: opacity 0.16s ease, transform 0.16s ease;
            z-index: 1200;
        }

        .action-menu.is-open .action-menu-panel {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }

        .action-menu-item {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 10px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: var(--text);
            font: inherit;
            font-size: 13px;
            line-height: 1.4;
            text-align: left;
            cursor: pointer;
        }

        .action-menu-item:hover {
            background: var(--primary-soft-strong);
            color: var(--primary-dark);
        }

        .action-menu-item.danger {
            color: #cf1322;
        }

        .action-menu-item.danger:hover {
            background: rgba(255, 77, 79, 0.08);
            color: #ff4d4f;
        }

        .action-menu-item svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
        }

        .field-sm {
            min-width: 160px;
            width: auto;
        }

        .inline-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .filters-card {
            padding: 16px;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: var(--shadow);
        }

        .filter-actions {
            display: flex;
            align-items: flex-end;
            gap: 8px;
        }

        .notice {
            display: none;
        }

        .toast {
            position: fixed;
            top: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(-14px);
            z-index: 9999;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-width: 280px;
            max-width: min(520px, calc(100vw - 32px));
            padding: 12px 24px;
            border-radius: 8px;
            background: #ffffff;
            color: var(--text);
            border: 1px solid rgba(82, 196, 26, 0.16);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.28s ease, transform 0.28s ease;
        }

        .toast.is-error {
            border-color: rgba(239, 68, 68, 0.22);
            background: linear-gradient(180deg, #fffafa 0%, #fff4f4 100%);
            box-shadow: 0 10px 28px rgba(239, 68, 68, 0.12);
        }

        .toast.is-visible {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        .toast-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 999px;
            background: #52c41a;
            color: #ffffff;
            flex-shrink: 0;
        }

        .toast.is-error .toast-icon {
            background: #ef4444;
        }

        .toast.is-error .toast-text {
            color: #7f1d1d;
        }

        .toast-icon svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .toast-text {
            font-size: 14px;
            line-height: 1.6;
            color: #262626;
            white-space: pre-line;
        }

        .global-tooltip {
            position: fixed;
            z-index: 9998;
            padding: 7px 10px;
            border-radius: 8px;
            background: rgba(38, 38, 38, 0.92);
            color: #ffffff;
            font-size: 12px;
            line-height: 1;
            white-space: nowrap;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18);
            opacity: 0;
            pointer-events: none;
            transform: translateY(4px);
            transition: opacity 0.16s ease, transform 0.16s ease;
        }

        .global-tooltip.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        .confirm-modal {
            position: fixed;
            inset: 0;
            z-index: 6000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }

        .confirm-modal.is-visible {
            opacity: 1;
            pointer-events: auto;
        }

        .confirm-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.32);
            backdrop-filter: blur(4px);
        }

        .confirm-modal-dialog {
            position: relative;
            width: min(460px, calc(100vw - 32px));
            padding: 24px;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 22px 40px rgba(15, 23, 42, 0.18);
        }

        .confirm-modal-icon {
            width: 44px;
            height: 44px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff7e6;
            color: #faad14;
        }

        .confirm-modal-icon svg {
            width: 22px;
            height: 22px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.9;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .confirm-modal-title {
            margin: 16px 0 0;
            color: #262626;
            font-size: 18px;
            line-height: 1.45;
            font-weight: 700;
        }

        .confirm-modal-text {
            margin-top: 10px;
            color: #8c8c8c;
            font-size: 14px;
            line-height: 1.75;
        }

        .confirm-modal-actions {
            margin-top: 24px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .empty {
            padding: 32px 20px;
            color: var(--muted);
            text-align: center;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: var(--shadow);
        }

        .form-error {
            margin-top: 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #ff4d4f;
            font-size: 12px;
            line-height: 1.5;
        }

        .form-error::before {
            content: "!";
            width: 14px;
            height: 14px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 77, 79, 0.12);
            color: #ff4d4f;
            font-size: 10px;
            font-weight: 700;
            line-height: 1;
            flex-shrink: 0;
        }

        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
        }

        .action-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .action-row-compact {
            gap: 12px;
            white-space: nowrap;
        }

        .action-row form {
            margin: 0;
        }

        .code-area {
            min-height: 540px;
            font-family: "SFMono-Regular", "Menlo", "Consolas", monospace;
            font-size: 13px;
            line-height: 1.7;
            white-space: pre;
        }

        .check-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .checkbox-card {
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 12px 14px;
            border-radius: 12px;
            background: #fafafa;
        }

        .pagination {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }

        .pagination a,
        .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            padding: 8px 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
            color: var(--text);
            font-size: 14px;
        }

        .pagination .active span {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        .table-toolbar {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 14px;
        }

        .table-toolbar-left,
        .table-toolbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .toolbar-label,
        .toolbar-meta {
            color: var(--muted);
            font-size: 13px;
        }

        .toolbar-label {
            font-weight: 600;
        }

        .form-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            gap: 20px;
        }

        .form-section {
            padding: 18px;
            border-radius: 12px;
            background: #fff;
            box-shadow: var(--shadow);
        }

        .form-section-title {
            margin-bottom: 14px;
            font-size: 15px;
            font-weight: 700;
        }

        .helper-text {
            font-size: 13px;
            line-height: 1.7;
        }

        .form-submit-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding-top: 4px;
        }

        .danger-zone {
            margin-top: 16px;
            padding: 16px 18px;
            border-radius: 12px;
            background: var(--danger-soft);
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
        }

        .danger-zone-title {
            color: #cf1322;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        @media (max-width: 1080px) {
            .card-grid,
            .split,
            .theme-grid,
            .check-grid,
            .filters,
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 860px) {
            .sidebar {
                position: static;
                width: auto;
            }

            .workspace {
                margin-left: 0;
            }

            .app-header {
                position: static;
                left: auto;
            }

            .content {
                padding-top: 24px;
            }

            .card-grid,
            .split,
            .split-form,
            .theme-grid,
            .check-grid,
            .filters,
            .form-grid {
                grid-template-columns: 1fr;
            }

            .topbar,
            .table-toolbar,
            .form-submit-bar,
            .danger-zone,
            .app-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
@php
    $authUser = auth()->user();
    $isSuperAdmin = false;
    $isPlatformAdmin = false;
    $platformPermissionCodes = [];
    $sitePermissionCodes = [];
    $currentSite = $currentSite ?? null;
    $displayName = $authUser->real_name ?? $authUser->name ?? $authUser->username ?? '管理员';
    $boundSitesCount = 0;
    $headerRoleLabel = '管理员';

    if ($authUser) {
        $isSuperAdmin = (int) $authUser->id === (int) config('cms.super_admin_user_id', 1);

        $isPlatformAdmin = $isSuperAdmin || \Illuminate\Support\Facades\DB::table('platform_user_roles')
            ->join('platform_roles', 'platform_roles.id', '=', 'platform_user_roles.role_id')
            ->where('platform_user_roles.user_id', $authUser->id)
            ->exists();

        $platformPermissionCodes = $isSuperAdmin
            ? \Illuminate\Support\Facades\DB::table('platform_permissions')->pluck('code')->all()
            : \Illuminate\Support\Facades\DB::table('platform_user_roles')
                ->join('platform_roles', 'platform_roles.id', '=', 'platform_user_roles.role_id')
                ->join('platform_role_permissions', 'platform_role_permissions.role_id', '=', 'platform_roles.id')
                ->join('platform_permissions', 'platform_permissions.id', '=', 'platform_role_permissions.permission_id')
                ->where('platform_user_roles.user_id', $authUser->id)
                ->distinct()
                ->pluck('platform_permissions.code')
                ->all();

        $boundSitesCount = \Illuminate\Support\Facades\DB::table('site_user_roles')
            ->where('user_id', $authUser->id)
            ->distinct('site_id')
            ->count('site_id');

        if ($isSuperAdmin) {
            $headerRoleLabel = '总管理员';
        } elseif ($isPlatformAdmin) {
            $headerRoleLabel = (string) (\Illuminate\Support\Facades\DB::table('platform_user_roles')
                ->join('platform_roles', 'platform_roles.id', '=', 'platform_user_roles.role_id')
                ->where('platform_user_roles.user_id', $authUser->id)
                ->value('platform_roles.name') ?: '平台管理员');
        } elseif (! empty($currentSite?->id)) {
            $siteRoleNames = \Illuminate\Support\Facades\DB::table('site_user_roles')
                ->join('site_roles', 'site_roles.id', '=', 'site_user_roles.role_id')
                ->where('site_user_roles.user_id', $authUser->id)
                ->where('site_user_roles.site_id', $currentSite->id)
                ->orderBy('site_roles.id')
                ->pluck('site_roles.name')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($siteRoleNames !== []) {
                $headerRoleLabel = implode('、', $siteRoleNames);
            } elseif ($boundSitesCount > 0) {
                $headerRoleLabel = '操作员';
            }
        } elseif ($boundSitesCount > 0) {
            $headerRoleLabel = '操作员';
        }

        $sitePermissionCodes = ($isPlatformAdmin || ! empty($currentSite?->id))
            ? ($isPlatformAdmin
                ? \Illuminate\Support\Facades\DB::table('site_permissions')->pluck('code')->all()
                : \Illuminate\Support\Facades\DB::table('site_user_roles')
                    ->join('site_roles', 'site_roles.id', '=', 'site_user_roles.role_id')
                    ->join('site_role_permissions', function ($join) use ($currentSite): void {
                        $join->on('site_role_permissions.role_id', '=', 'site_roles.id')
                            ->where('site_role_permissions.site_id', '=', $currentSite->id ?? 0);
                    })
                    ->join('site_permissions', 'site_permissions.id', '=', 'site_role_permissions.permission_id')
                    ->where('site_user_roles.user_id', $authUser->id)
                    ->where('site_user_roles.site_id', $currentSite->id ?? 0)
                    ->where(function ($query) use ($currentSite): void {
                        $query->whereNull('site_roles.site_id')
                            ->orWhere('site_roles.site_id', $currentSite->id ?? 0);
                    })
                    ->distinct()
                    ->pluck('site_permissions.code')
                    ->all())
            : [];
    }

    $profileRoute = $authUser
        ? ($isPlatformAdmin
            ? route('admin.platform.users.edit', $authUser->id)
            : route('admin.site-users.edit', $authUser->id))
        : '#';

    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24"><path d="M4 13h6V4H4z"/><path d="M14 20h6v-9h-6z"/><path d="M14 10h6V4h-6z"/><path d="M4 20h6v-3H4z"/></svg>',
        'site' => '<svg viewBox="0 0 24 24"><path d="M3 10.5 12 4l9 6.5"/><path d="M5 9.5V20h14V9.5"/><path d="M9 20v-6h6v6"/></svg>',
        'theme' => '<svg viewBox="0 0 24 24"><path d="M4 6h16v12H4z"/><path d="M8 10h8"/><path d="M8 14h5"/></svg>',
        'user' => '<svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><path d="M9.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/><path d="M20 8v6"/><path d="M23 11h-6"/></svg>',
        'channel' => '<svg viewBox="0 0 24 24"><path d="M4 6h7v5H4z"/><path d="M13 6h7v5h-7z"/><path d="M4 13h7v5H4z"/><path d="M13 13h7v5h-7z"/></svg>',
        'promo' => '<svg viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M7 7v10"/><path d="M17 7v10"/><path d="M7 17h10"/><path d="m14 17 3 3"/><path d="m17 17-3 3"/></svg>',
        'page' => '<svg viewBox="0 0 24 24"><path d="M6 4h9l3 3v13H6z"/><path d="M9 8h3"/><path d="M9 12h6"/><path d="M9 16h6"/></svg>',
        'article' => '<svg viewBox="0 0 24 24"><path d="M5 6h14"/><path d="M5 12h14"/><path d="M5 18h9"/></svg>',
        'recycle' => '<svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>',
        'attachment' => '<svg viewBox="0 0 24 24"><path d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 1 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.82-2.82l8.49-8.48"/></svg>',
        'module' => '<svg viewBox="0 0 24 24"><path d="M4 7h7v7H4z"/><path d="M13 7h7v7h-7z"/><path d="M4 16h7v4H4z"/><path d="M13 16h7v4h-7z"/></svg>',
        'database' => '<svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="7" ry="3"/><path d="M5 5v6c0 1.66 3.13 3 7 3s7-1.34 7-3V5"/><path d="M5 11v6c0 1.66 3.13 3 7 3s7-1.34 7-3v-6"/></svg>',
        'setting' => '<svg viewBox="0 0 24 24"><path d="M12 15.5A3.5 3.5 0 1 0 12 8.5a3.5 3.5 0 0 0 0 7z"/><path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a2 2 0 1 1-4 0v-.2a1 1 0 0 0-.6-.9 1 1 0 0 0-1.1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a2 2 0 1 1 0-4h.2a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1 1 0 0 0 1.1.2 1 1 0 0 0 .6-.9V4a2 2 0 1 1 4 0v.2a1 1 0 0 0 .6.9 1 1 0 0 0 1.1-.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1 1 0 0 0-.2 1.1 1 1 0 0 0 .9.6H20a2 2 0 1 1 0 4h-.2a1 1 0 0 0-.9.6z"/></svg>',
        'log' => '<svg viewBox="0 0 24 24"><path d="M12 8v5l3 2"/><path d="M20 12a8 8 0 1 1-2.34-5.66"/><path d="M20 4v5h-5"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>',
        'tag' => '<svg viewBox="0 0 24 24"><path d="M20.59 13.41 12 22l-9-9V4h9z"/><path d="M7.5 8.5h.01"/></svg>',
        'chevron-down' => '<svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>',
        'profile-card' => '<svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2Z"/><path d="M8.5 10a3.5 3.5 0 1 0 7 0 3.5 3.5 0 0 0-7 0Z"/><path d="M7 18a5 5 0 0 1 10 0"/></svg>',
        'palette' => '<svg viewBox="0 0 24 24"><path d="M12 3a9 9 0 0 0 0 18h1.2a1.8 1.8 0 0 0 .4-3.56l-.64-.16a1.54 1.54 0 0 1 1.04-2.9H16a5 5 0 0 0 0-10z"/><path d="M7.5 10.5h.01"/><path d="M12 7.5h.01"/><path d="M16.5 10.5h.01"/><path d="M8.5 15.5h.01"/></svg>',
        'theme-chip' => '<svg viewBox="0 0 24 24"><path d="M4 12h16"/><path d="M12 4v16"/><path d="M5.5 5.5 18.5 18.5"/><path d="M18.5 5.5 5.5 18.5"/></svg>',
        'theme-leaf' => '<svg viewBox="0 0 24 24"><path d="M6 18c6 0 12-5 12-12-7 0-12 6-12 12Z"/><path d="M6 18c0-5 4-9 9-9"/></svg>',
        'theme-sun' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v3"/><path d="M12 19v3"/><path d="m4.93 4.93 2.12 2.12"/><path d="m16.95 16.95 2.12 2.12"/><path d="M2 12h3"/><path d="M19 12h3"/></svg>',
        'theme-star' => '<svg viewBox="0 0 24 24"><path d="m12 3 2.7 5.47L21 9.38l-4.5 4.38 1.06 6.24L12 17.27 6.44 20l1.06-6.24L3 9.38l6.3-.91z"/></svg>',
        'theme-heart' => '<svg viewBox="0 0 24 24"><path d="m12 20-7-7a4.5 4.5 0 0 1 6.36-6.36L12 7.27l.64-.63A4.5 4.5 0 1 1 19 13z"/></svg>',
        'theme-snow' => '<svg viewBox="0 0 24 24"><path d="M12 2v20"/><path d="m4.93 6 14.14 12"/><path d="m19.07 6-14.14 12"/><path d="M2 12h20"/></svg>',
        'theme-spark' => '<svg viewBox="0 0 24 24"><path d="m12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/></svg>',
    ];

    $isPlatformArea = request()->is('admin/platform*') || request()->is('admin/dashboard') || request()->is('admin/logs');
    $isSiteArea = request()->is('admin/site*') || request()->is('admin/site-dashboard');
    $activeAdminArea = $isPlatformArea ? 'platform' : 'site';
    $articleRejectedCount = ($currentSite && in_array('content.manage', $sitePermissionCodes ?? [], true))
        ? (int) \Illuminate\Support\Facades\DB::table('contents')
            ->where('site_id', $currentSite->id)
            ->where('type', 'article')
            ->where('status', 'rejected')
            ->whereNull('deleted_at')
            ->count()
        : 0;
    $articlePendingCount = ($currentSite && in_array('content.audit', $sitePermissionCodes ?? [], true))
        ? (int) \Illuminate\Support\Facades\DB::table('contents')
            ->where('site_id', $currentSite->id)
            ->where('type', 'article')
            ->where('status', 'pending')
            ->whereNull('deleted_at')
            ->count()
        : 0;
    $articleReviewEnabled = $currentSite
        ? (\Illuminate\Support\Facades\DB::table('site_settings')
            ->where('site_id', $currentSite->id)
            ->where('setting_key', 'content.article_requires_review')
            ->value('setting_value') === '1')
        : false;
    $guestbookMenuName = $currentSite
        ? (string) (\Illuminate\Support\Facades\DB::table('site_settings')
            ->where('site_id', $currentSite->id)
            ->where('setting_key', 'module.guestbook.name')
            ->value('setting_value') ?: '')
        : '';
    $recycleCount = ($currentSite && in_array('content.manage', $sitePermissionCodes ?? [], true))
        ? (int) \Illuminate\Support\Facades\DB::table('contents')
            ->where('site_id', $currentSite->id)
            ->whereNotNull('deleted_at')
            ->count()
        : 0;
    $moduleManager = app(\App\Support\Modules\ModuleManager::class);
    $boundSiteModules = $currentSite
        ? $moduleManager->boundSiteModules((int) $currentSite->id)
            ->filter(function (array $module) use ($sitePermissionCodes, $isPlatformAdmin): bool {
                if ($isPlatformAdmin) {
                    return true;
                }

                $entryPermission = $module['entry_permission'] ?? null;

                return ! is_string($entryPermission) || $entryPermission === '' || in_array($entryPermission, $sitePermissionCodes, true);
            })
            ->values()
        : collect();
    $guestbookPendingCount = ($currentSite && $boundSiteModules->contains(fn (array $module): bool => ($module['code'] ?? null) === 'guestbook'))
        ? (int) \Illuminate\Support\Facades\DB::table('module_guestbook_messages')
            ->where('site_id', $currentSite->id)
            ->where('status', 'pending')
            ->count()
        : 0;

    $siteMenuGroups = [
        [
            'title' => '内容管理',
            'items' => array_values(array_filter([
                ['label' => '文章管理', 'route' => 'admin.articles.index', 'active' => request()->routeIs('admin.articles.*'), 'icon' => 'article', 'badge' => $articleRejectedCount, 'badge_class' => '', 'show' => in_array('content.manage', $sitePermissionCodes, true)],
                ['label' => '文章审核', 'route' => 'admin.article-reviews.index', 'active' => request()->routeIs('admin.article-reviews.*'), 'icon' => 'tag', 'badge' => $articlePendingCount, 'badge_class' => '', 'show' => in_array('content.audit', $sitePermissionCodes, true) && $articleReviewEnabled],
                ['label' => '单页面管理', 'route' => 'admin.pages.index', 'active' => request()->routeIs('admin.pages.*'), 'icon' => 'page', 'show' => in_array('content.manage', $sitePermissionCodes, true)],
                ['label' => '资源库管理', 'route' => 'admin.attachments.index', 'active' => request()->routeIs('admin.attachments.*'), 'icon' => 'attachment', 'show' => in_array('attachment.manage', $sitePermissionCodes, true) || in_array('content.manage', $sitePermissionCodes, true)],
                ['label' => '回收站', 'route' => 'admin.recycle-bin.index', 'active' => request()->routeIs('admin.recycle-bin.*'), 'icon' => 'recycle', 'badge' => $recycleCount, 'badge_class' => 'is-title', 'show' => in_array('content.manage', $sitePermissionCodes, true)],
            ], fn ($item) => $item['show'])),
        ],
        [
            'title' => '功能模块',
            'items' => array_values(array_filter(
                $boundSiteModules->map(function (array $module) use ($guestbookMenuName, $guestbookPendingCount): array {
                    $entryRoute = is_string($module['site_entry_route'] ?? null) && ($module['site_entry_route'] ?? '') !== ''
                        ? (string) $module['site_entry_route']
                        : 'admin.site-modules.show';
                    $routeParams = $entryRoute === 'admin.site-modules.show'
                        ? ['module' => $module['code']]
                        : [];
                    $activePattern = $entryRoute === 'admin.site-modules.show'
                        ? 'admin.site-modules.show'
                        : preg_replace('/\.[^.]+$/', '.*', $entryRoute);

                    return [
                        'label' => $module['code'] === 'guestbook' && $guestbookMenuName !== '' ? $guestbookMenuName : $module['name'],
                        'route' => $entryRoute,
                        'route_params' => $routeParams,
                        'active' => request()->routeIs($activePattern) || (request()->routeIs('admin.site-modules.show') && request()->route('module') === $module['code']),
                        'icon' => 'module',
                        'badge' => ($module['code'] ?? null) === 'guestbook' ? $guestbookPendingCount : 0,
                        'badge_class' => ($module['code'] ?? null) === 'guestbook' ? 'is-title' : '',
                        'show' => true,
                    ];
                })->all(),
                fn ($item) => $item['show']
            )),
        ],
        [
            'title' => '站点配置',
            'items' => array_values(array_filter([
                ['label' => '站点工作台', 'route' => 'admin.site-dashboard', 'active' => request()->routeIs('admin.site-dashboard'), 'icon' => 'dashboard', 'show' => $currentSite !== null],
                ['label' => '站点设置', 'route' => 'admin.settings.index', 'active' => request()->routeIs('admin.settings.*'), 'icon' => 'setting', 'show' => in_array('setting.manage', $sitePermissionCodes, true)],
                ['label' => '栏目管理', 'route' => 'admin.channels.index', 'active' => request()->routeIs('admin.channels.*'), 'icon' => 'channel', 'show' => in_array('channel.manage', $sitePermissionCodes, true)],
                ['label' => '图宣管理', 'route' => 'admin.promos.index', 'active' => request()->routeIs('admin.promos.*'), 'icon' => 'promo', 'show' => in_array('promo.manage', $sitePermissionCodes, true)],
                ['label' => '模板管理', 'route' => 'admin.themes.index', 'active' => request()->routeIs('admin.themes.*'), 'icon' => 'theme', 'show' => in_array('theme.use', $sitePermissionCodes, true) || in_array('theme.edit', $sitePermissionCodes, true)],
                ['label' => '操作员管理', 'route' => 'admin.site-users.index', 'active' => request()->routeIs('admin.site-users.*'), 'icon' => 'user', 'show' => in_array('site.user.manage', $sitePermissionCodes, true)],
                ['label' => '操作角色管理', 'route' => 'admin.site-roles.index', 'active' => request()->routeIs('admin.site-roles.*'), 'icon' => 'setting', 'show' => in_array('site.user.manage', $sitePermissionCodes, true)],
                ['label' => '站点日志', 'route' => 'admin.site-logs.index', 'active' => request()->routeIs('admin.site-logs.*'), 'icon' => 'log', 'show' => in_array('log.view', $sitePermissionCodes, true)],
            ], fn ($item) => $item['show'])),
        ],
    ];

    $platformMenuGroups = [
        [
            'title' => '业务管理',
            'items' => $isPlatformAdmin ? array_values(array_filter([
                ['label' => '站点管理', 'route' => 'admin.platform.sites.index', 'active' => request()->routeIs('admin.platform.sites.*'), 'icon' => 'site', 'show' => in_array('site.manage', $platformPermissionCodes, true)],
                ['label' => '主题市场', 'route' => 'admin.platform.themes.index', 'active' => request()->routeIs('admin.platform.themes.*'), 'icon' => 'theme', 'show' => in_array('theme.market.manage', $platformPermissionCodes, true)],
                ['label' => '模块管理', 'route' => 'admin.platform.modules.index', 'active' => request()->routeIs('admin.platform.modules.*'), 'icon' => 'module', 'show' => in_array('module.manage', $platformPermissionCodes, true)],
            ], fn ($item) => $item['show'])) : [],
        ],
        [
            'title' => '平台配置',
            'items' => $isPlatformAdmin ? array_values(array_filter([
                ['label' => '平台工作台', 'route' => 'admin.dashboard', 'active' => request()->routeIs('admin.dashboard'), 'icon' => 'dashboard', 'show' => true],
                ['label' => '平台管理员', 'route' => 'admin.platform.users.index', 'active' => request()->routeIs('admin.platform.users.*'), 'icon' => 'user', 'show' => in_array('platform.user.manage', $platformPermissionCodes, true)],
                ['label' => '平台角色管理', 'route' => 'admin.platform.roles.index', 'active' => request()->routeIs('admin.platform.roles.*'), 'icon' => 'setting', 'show' => in_array('platform.role.manage', $platformPermissionCodes, true)],
                ['label' => '数据库管理', 'route' => 'admin.platform.database.index', 'active' => request()->routeIs('admin.platform.database.*'), 'icon' => 'database', 'show' => in_array('database.manage', $platformPermissionCodes, true)],
                [
                    'label' => '系统设置',
                    'route' => 'admin.platform.settings.index',
                    'active' => request()->routeIs('admin.platform.settings.*'),
                    'icon' => 'setting',
                    'show' => in_array('system.setting.manage', $platformPermissionCodes, true),
                ],
                ['label' => '操作日志', 'route' => 'admin.logs.index', 'active' => request()->routeIs('admin.logs.*'), 'icon' => 'log', 'show' => in_array('platform.log.view', $platformPermissionCodes, true)],
            ], fn ($item) => $item['show'])) : [],
        ],
    ];

    if ($activeAdminArea === 'platform' && $isPlatformAdmin && $currentSite) {
        $platformMenuGroups[] = [
            'title' => '站点视角',
            'items' => [[
                'label' => '进入站点工作台',
                'route' => 'admin.site-dashboard',
                'active' => false,
                'icon' => 'dashboard',
                'show' => true,
            ]],
        ];
    }

    if ($activeAdminArea === 'site' && $isPlatformAdmin) {
        $siteMenuGroups[] = [
            'title' => '平台视角',
            'items' => [[
                'label' => '进入平台工作台',
                'route' => 'admin.dashboard',
                'active' => false,
                'icon' => 'dashboard',
                'show' => true,
            ]],
        ];
    }

    $menuGroups = $activeAdminArea === 'platform' ? $platformMenuGroups : $siteMenuGroups;
@endphp
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <a class="brand-logo" href="{{ route('admin.entry') }}" aria-label="后台首页">
                <img src="{{ $adminBrandSettings['admin_logo'] }}" alt="{{ $adminBrandSettings['system_name'] }}">
            </a>
        </div>

        <nav class="sidebar-nav">
            @foreach ($menuGroups as $group)
                @if (! empty($group['items']))
                    <div class="menu-group">
                        <div class="menu-title">{{ $group['title'] }}</div>
                        @foreach ($group['items'] as $item)
                            <a class="menu-link {{ $item['active'] ? 'active' : '' }}" href="{{ isset($item['route_params']) ? route($item['route'], $item['route_params']) : route($item['route']) }}">
                                <span class="menu-icon">{!! $icons[$item['icon']] ?? '' !!}</span>
                                <span class="menu-link-label">{{ $item['label'] }}</span>
                                @if (! empty($item['badge']))
                                    <span class="menu-link-badge {{ $item['badge_class'] ?? '' }}">+{{ (int) $item['badge'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </nav>

        <div class="sidebar-bottom">
            <div class="sidebar-user">
                <strong>{{ $activeAdminArea === 'platform' ? '平台管理端' : '站点管理端' }}</strong>
                @if ($activeAdminArea === 'site' && ! empty($currentSite?->site_key))
                    <div>{{ '站点标识：' . $currentSite->site_key }}</div>
                @endif
                <div>{{ '系统版本：' . ($adminBrandSettings['system_version'] ?? '1.0.0') }}</div>
            </div>
        </div>
    </aside>

    <div class="workspace">
        <header class="app-header">
            @php
                $breadcrumb = trim((string) $__env->yieldContent('breadcrumb', ''));
                $breadcrumb = preg_replace('/^后台管理\s*\/\s*/u', '', $breadcrumb) ?? $breadcrumb;
                $breadcrumb = $breadcrumb === '后台管理' ? '' : $breadcrumb;
            @endphp
            <div class="breadcrumb">{{ $breadcrumb }}</div>
            <div class="header-user">
                <div class="theme-switcher" data-theme-switcher>
                    <button class="theme-switcher-trigger" type="button" data-theme-trigger data-tooltip="界面风格">
                        <span class="button-icon">{!! $icons['palette'] !!}</span>
                    </button>
                    <div class="theme-switcher-panel">
                        <p class="theme-switcher-title">界面风格</p>
                        <div class="theme-switcher-divider"></div>
                        <div class="theme-switcher-options">
                            <div class="theme-switcher-item">
                                <button class="theme-swatch" type="button" style="--swatch-color: #10B981; --swatch-glow: rgba(16, 185, 129, 0.2);" data-theme-choice="mint-fresh">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-leaf'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">薄荷夏日</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                            <div class="theme-switcher-item">
                                <button class="theme-swatch" type="button" style="--swatch-color: #0047AB; --swatch-glow: rgba(0, 71, 171, 0.2);" data-theme-choice="geek-blue">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-chip'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">纯净宝蓝</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                            <div class="theme-switcher-item">
                                <button class="theme-swatch" type="button" style="--swatch-color: #F59E0B; --swatch-glow: rgba(245, 158, 11, 0.2);" data-theme-choice="sun-amber">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-sun'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">初生暖阳</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                            <div class="theme-switcher-item">
                                <button class="theme-swatch" type="button" style="--swatch-color: #8B5CF6; --swatch-glow: rgba(139, 92, 246, 0.2);" data-theme-choice="aurora-purple">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-star'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">幻影霓虹</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                            <div class="theme-switcher-item">
                                <button class="theme-swatch" type="button" style="--swatch-color: #F43F5E; --swatch-glow: rgba(244, 63, 94, 0.2);" data-theme-choice="coral-rose">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-heart'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">元气珊瑚</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                            <div class="theme-switcher-item">
                                <button class="theme-swatch" type="button" style="--swatch-color: #06B6D4; --swatch-glow: rgba(6, 182, 212, 0.2);" data-theme-choice="ice-cyan">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-snow'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">冰晶之域</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                            <div class="theme-switcher-item">
                                <button class="theme-swatch" type="button" style="--swatch-color: #D946EF; --swatch-glow: rgba(217, 70, 239, 0.2);" data-theme-choice="candy-magenta">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-spark'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">糖果粉紫</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                            <div class="theme-switcher-item">
                                <button class="theme-swatch" type="button" style="--swatch-color: #F97316; --swatch-glow: rgba(249, 115, 22, 0.2);" data-theme-choice="sunset-orange">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-sun'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">燃情落日</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                            <div class="theme-switcher-item">
                                <button class="theme-swatch" type="button" style="--swatch-color: #2F7D32; --swatch-glow: rgba(47, 125, 50, 0.22);" data-theme-choice="lime-glow">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-leaf'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">春意盎然</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="header-user-menu" data-user-menu>
                    <button class="header-user-trigger" type="button" data-user-menu-trigger aria-haspopup="menu" aria-expanded="false">
                        <span class="header-user-avatar">{!! $icons['profile-card'] !!}</span>
                        <span class="header-user-copy">
                            <span class="header-user-name">{{ $displayName }}</span>
                            <span class="header-user-role">{{ $headerRoleLabel }}</span>
                        </span>
                        <span class="header-user-caret">{!! $icons['chevron-down'] !!}</span>
                    </button>
                    <div class="header-user-panel" role="menu">
                        <div class="header-user-panel-head">
                            <div class="header-user-panel-name">{{ $displayName }}</div>
                            <div class="header-user-panel-meta">{{ '@' . ($authUser->username ?? 'admin') }}</div>
                        </div>
                        <div class="header-user-panel-list">
                            <a class="header-user-panel-link" href="{{ $profileRoute }}" role="menuitem">
                                <span class="button-icon">{!! $icons['profile-card'] !!}</span>
                                <span>个人信息</span>
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="header-user-panel-button is-danger" type="submit" role="menuitem">
                                    <span class="button-icon">{!! $icons['logout'] !!}</span>
                                    <span>退出登录</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="content">
            @yield('content')
        </main>
    </div>
</div>
<div class="confirm-modal js-confirm-modal" aria-hidden="true">
    <div class="confirm-modal-backdrop js-confirm-cancel"></div>
    <div class="confirm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="global-confirm-title">
        <div class="confirm-modal-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.72 3h16.92a2 2 0 0 0 1.72-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
        </div>
        <h3 class="confirm-modal-title" id="global-confirm-title">确认继续此操作？</h3>
        <div class="confirm-modal-text js-confirm-text">该操作将立即生效，请确认是否继续。</div>
        <div class="confirm-modal-actions">
            <button class="button secondary js-confirm-cancel" type="button">取消</button>
            <button class="button danger js-confirm-accept" type="button">确定</button>
        </div>
    </div>
</div>
</body>
<script>
    (() => {
        const STORAGE_KEY = 'school-cms-admin-theme';
        const root = document.documentElement;
        const hexToRgba = (hex, alpha) => {
            const normalized = hex.replace('#', '');
            const safeHex = normalized.length === 3
                ? normalized.split('').map((char) => char + char).join('')
                : normalized;
            const value = Number.parseInt(safeHex, 16);
            const r = (value >> 16) & 255;
            const g = (value >> 8) & 255;
            const b = value & 255;
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        };

        const themes = {
            'geek-blue': {
                '--color-primary': '#0047AB',
                '--color-primary-light': '#F0F6FF',
                '--color-text-main': '#1F2937',
            },
            'mint-fresh': {
                '--color-primary': '#10B981',
                '--color-primary-light': '#ECFDF5',
                '--color-text-main': '#262626',
            },
            'sun-amber': {
                '--color-primary': '#F59E0B',
                '--color-primary-light': '#FFFBEB',
                '--color-text-main': '#262626',
            },
            'aurora-purple': {
                '--color-primary': '#8B5CF6',
                '--color-primary-light': '#F5F3FF',
                '--color-text-main': '#262626',
            },
            'coral-rose': {
                '--color-primary': '#F43F5E',
                '--color-primary-light': '#FFF1F2',
                '--color-text-main': '#262626',
            },
            'ice-cyan': {
                '--color-primary': '#06B6D4',
                '--color-primary-light': '#ECFEFF',
                '--color-text-main': '#262626',
            },
            'candy-magenta': {
                '--color-primary': '#D946EF',
                '--color-primary-light': '#FDF4FF',
                '--color-text-main': '#262626',
            },
            'sunset-orange': {
                '--color-primary': '#F97316',
                '--color-primary-light': '#FFF7ED',
                '--color-text-main': '#262626',
            },
            'lime-glow': {
                '--color-primary': '#2F7D32',
                '--color-primary-light': '#EEF7EE',
                '--color-text-main': '#262626',
            },
        };

        const applyTheme = (themeKey) => {
            const baseTheme = themes[themeKey] || themes['mint-fresh'];
            const primary = baseTheme['--color-primary'];
            const primaryLight = baseTheme['--color-primary-light'];
            const themeValues = {
                ...baseTheme,
                '--primary': primary,
                '--primary-dark': primary,
                '--primary-bg': primaryLight,
                '--primary-soft': hexToRgba(primary, 0.06),
                '--primary-soft-strong': hexToRgba(primary, 0.12),
                '--primary-border-soft': hexToRgba(primary, 0.22),
                '--tag-bg': hexToRgba(primary, 0.08),
                '--tag-text': primary,
                '--group-title-color': hexToRgba(primary, 0.5),
            };

            Object.entries(themeValues).forEach(([key, value]) => {
                root.style.setProperty(key, value);
            });

            document.querySelectorAll('[data-theme-choice]').forEach((button) => {
                button.classList.toggle('is-active', button.dataset.themeChoice === themeKey);
            });

            window.localStorage.setItem(STORAGE_KEY, themeKey);
        };

        const currentTheme = window.localStorage.getItem(STORAGE_KEY) || 'mint-fresh';
        applyTheme(currentTheme);

        const switcher = document.querySelector('[data-theme-switcher]');
        if (switcher) {
            const trigger = switcher.querySelector('[data-theme-trigger]');
            trigger?.addEventListener('click', (event) => {
                event.stopPropagation();
                switcher.classList.toggle('is-open');
            });

            switcher.querySelectorAll('[data-theme-choice]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                applyTheme(button.dataset.themeChoice || 'mint-fresh');
                });
            });
        }

        const userMenu = document.querySelector('[data-user-menu]');
        const userMenuTrigger = userMenu?.querySelector('[data-user-menu-trigger]');
        const setUserMenuOpen = (isOpen) => {
            if (!userMenu || !userMenuTrigger) {
                return;
            }

            userMenu.classList.toggle('is-open', isOpen);
            userMenuTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        };

        userMenuTrigger?.addEventListener('click', (event) => {
            event.stopPropagation();
            setUserMenuOpen(!userMenu?.classList.contains('is-open'));
        });

        document.addEventListener('click', (event) => {
            if (switcher && !switcher.contains(event.target)) {
                switcher.classList.remove('is-open');
            }

            if (userMenu && !userMenu.contains(event.target)) {
                setUserMenuOpen(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                switcher?.classList.remove('is-open');
                setUserMenuOpen(false);
            }
        });
    })();

    function showMessage(message, type = 'success') {
        if (!message) {
            return;
        }

        document.querySelectorAll('.toast').forEach((item) => item.remove());

        const toast = document.createElement('div');
        const normalizedType = type === 'error' ? 'error' : 'success';
        toast.className = `toast${normalizedType === 'error' ? ' is-error' : ''}`;
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.innerHTML = `
            <span class="toast-icon">
                ${normalizedType === 'error'
                    ? '<svg viewBox="0 0 24 24"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>'
                    : '<svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>'}
            </span>
            <span class="toast-text"></span>
        `;
        toast.querySelector('.toast-text').textContent = message;
        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.add('is-visible');
        });

        window.setTimeout(() => {
            toast.classList.remove('is-visible');
            window.setTimeout(() => {
                toast.remove();
            }, 240);
        }, 3000);
    }

    function inferMessageType(message) {
        if (typeof message !== 'string') {
            return 'success';
        }

        return /(失败|错误|不能|无权|禁止|驳回|不支持|请输入|请先|不能为空|未填写|必填|缺少)/.test(message) ? 'error' : 'success';
    }

    (() => {
        const tooltip = document.createElement('div');
        tooltip.className = 'global-tooltip';
        document.body.appendChild(tooltip);

        let activeTarget = null;

        const hideTooltip = () => {
            tooltip.classList.remove('is-visible');
            activeTarget = null;
        };

        const positionTooltip = (target) => {
            if (!target) {
                return;
            }

            const label = target.dataset.tooltip;
            if (!label) {
                hideTooltip();
                return;
            }

            tooltip.textContent = label;
            tooltip.style.left = '0px';
            tooltip.style.top = '0px';

            const rect = target.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();
            const gap = 10;
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;

            let left = rect.left + rect.width / 2 - tooltipRect.width / 2;
            let top = rect.top - tooltipRect.height - gap;

            if (top < 12) {
                top = rect.bottom + gap;
            }

            left = Math.max(12, Math.min(left, viewportWidth - tooltipRect.width - 12));
            top = Math.max(12, Math.min(top, viewportHeight - tooltipRect.height - 12));

            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${top}px`;
        };

        document.addEventListener('mouseover', (event) => {
            const target = event.target.closest('[data-tooltip]');
            if (!target) {
                hideTooltip();
                return;
            }

            activeTarget = target;
            positionTooltip(target);
            tooltip.classList.add('is-visible');
        });

        document.addEventListener('mouseout', (event) => {
            if (!activeTarget) {
                return;
            }

            const relatedTarget = event.relatedTarget;
            if (relatedTarget && activeTarget.contains(relatedTarget)) {
                return;
            }

            if (event.target.closest('[data-tooltip]') === activeTarget) {
                hideTooltip();
            }
        });

        document.addEventListener('focusin', (event) => {
            const target = event.target.closest('[data-tooltip]');
            if (!target) {
                return;
            }

            activeTarget = target;
            positionTooltip(target);
            tooltip.classList.add('is-visible');
        });

        document.addEventListener('focusout', (event) => {
            if (event.target.closest('[data-tooltip]')) {
                hideTooltip();
            }
        });

        window.addEventListener('scroll', () => {
            if (activeTarget) {
                positionTooltip(activeTarget);
            }
        }, true);

        window.addEventListener('resize', () => {
            if (activeTarget) {
                positionTooltip(activeTarget);
            }
        });
    })();

    (() => {
        document.querySelectorAll('[data-menu-trigger]').forEach((trigger) => {
            const menu = trigger.closest('.action-menu');
            if (!menu) {
                return;
            }

            trigger.addEventListener('click', (event) => {
                event.stopPropagation();

                document.querySelectorAll('.action-menu.is-open').forEach((item) => {
                    if (item !== menu) {
                        item.classList.remove('is-open');
                    }
                });

                menu.classList.toggle('is-open');
            });
        });

        document.addEventListener('click', (event) => {
            document.querySelectorAll('.action-menu.is-open').forEach((menu) => {
                if (!menu.contains(event.target)) {
                    menu.classList.remove('is-open');
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                document.querySelectorAll('.action-menu.is-open').forEach((menu) => {
                    menu.classList.remove('is-open');
                });
            }
        });
    })();

    (() => {
        const modal = document.querySelector('.js-confirm-modal');
        if (!modal) {
            return;
        }

        const titleElement = modal.querySelector('.confirm-modal-title');
        const textElement = modal.querySelector('.js-confirm-text');
        const acceptButton = modal.querySelector('.js-confirm-accept');
        const cancelButtons = modal.querySelectorAll('.js-confirm-cancel');
        let onConfirm = null;

        const closeModal = () => {
            modal.classList.remove('is-visible');
            modal.setAttribute('aria-hidden', 'true');
            onConfirm = null;
            acceptButton.disabled = false;
            acceptButton.textContent = '确定';
        };

        window.closeConfirmDialog = closeModal;

        window.showConfirmDialog = ({
            title = '确认继续此操作？',
            text = '该操作将立即生效，请确认是否继续。',
            confirmText = '确定',
            onConfirm: confirmHandler = null,
        } = {}) => {
            titleElement.textContent = title;
            textElement.textContent = text;
            acceptButton.textContent = confirmText;
            acceptButton.disabled = false;
            onConfirm = typeof confirmHandler === 'function' ? confirmHandler : null;
            modal.classList.add('is-visible');
            modal.setAttribute('aria-hidden', 'false');
        };

        cancelButtons.forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-visible')) {
                closeModal();
            }
        });

        acceptButton.addEventListener('click', async () => {
            if (!onConfirm) {
                closeModal();
                return;
            }

            acceptButton.disabled = true;
            try {
                const result = onConfirm();

                if (result && typeof result.then === 'function') {
                    await result;
                }

                closeModal();
            } catch (error) {
                acceptButton.disabled = false;
            }
        });
    })();

    const resetLoadingButtons = () => {
        document.querySelectorAll('button[data-loading-text]').forEach((button) => {
            if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
            }

            button.classList.remove('is-loading');
            button.disabled = false;
            delete button.dataset.loadingApplied;
        });
    };

    resetLoadingButtons();
    window.resetLoadingButtons = resetLoadingButtons;
    window.addEventListener('pageshow', resetLoadingButtons);

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const linkedButtons = form.id
                ? Array.from(document.querySelectorAll(`button[form="${form.id}"][type="submit"]`))
                : [];

            const submitButtons = [...form.querySelectorAll('button[type="submit"]'), ...linkedButtons]
                .filter((button) => button.dataset.loadingText);

            submitButtons.forEach((button) => {
                if (button.dataset.loadingApplied === 'true') {
                    return;
                }

                button.dataset.loadingApplied = 'true';
                button.dataset.originalHtml = button.innerHTML;
                button.classList.add('is-loading');
                button.disabled = true;
                button.innerHTML = `<span class="button-spinner" aria-hidden="true"></span><span>${button.dataset.loadingText}</span>`;
            });

            window.setTimeout(() => {
                if (event.defaultPrevented) {
                    resetLoadingButtons();
                }
            }, 0);
        });
    });

    document.addEventListener('input', (event) => {
        const field = event.target.closest('input, textarea, select');
        if (!field || !field.classList.contains('is-error')) {
            return;
        }

        field.classList.remove('is-error');
        field.removeAttribute('aria-invalid');

        const container = field.closest('.field-group, label, .form-section, .role-create-body, .stack');
        const error = container?.querySelector('.form-error');
        if (error) {
            error.remove();
        }
    });

    @if (session('status'))
        showMessage(@json(session('status')), inferMessageType(@json(session('status'))));
    @endif
</script>
@stack('scripts')
</html>
