@extends('layouts.admin')

@section('title', '安护盾 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 安护盾')

@push('styles')
    <style>
        .page-header {
            display: flex;
            justify-content: flex-start;
            gap: 24px;
            align-items: center;
            padding: 24px 32px;
            margin: -28px -28px 24px;
            background:
                radial-gradient(circle at top right, rgba(0, 35, 102, 0.06), transparent 34%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.92) 0%, rgba(248, 251, 255, 0.88) 100%);
            border-bottom: 1px solid #f0f0f0;
            backdrop-filter: blur(18px);
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
            max-width: 900px;
        }

        .security-shell {
            display: grid;
            gap: 20px;
        }

        .security-metrics {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .security-card {
            padding: 22px 24px;
            border: 1px solid #e7edf4;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.82);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(18px);
        }

        .security-card-label {
            color: #667085;
            font-size: 13px;
            font-weight: 700;
        }

        .security-card-value {
            margin-top: 12px;
            color: #111827;
            font-size: 42px;
            line-height: 1;
            font-weight: 800;
        }

        .security-card-note {
            margin-top: 12px;
            color: #98a2b3;
            font-size: 13px;
            line-height: 1.7;
        }

        .security-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }

        .security-card-icon {
            width: 20px;
            height: 20px;
            color: #94a3b8;
            flex-shrink: 0;
        }

        .security-card-icon svg {
            width: 100%;
            height: 100%;
            display: block;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.9;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .security-card.is-status {
            padding: 14px 16px;
        }

        .security-status-showcase {
            position: relative;
            min-height: 0;
            height: 100%;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            overflow: hidden;
            padding-top: 6px;
        }

        .security-status-showcase.is-disabled .security-status-core-glow {
            opacity: 0.32;
            animation: none;
            filter: blur(12px) saturate(0.6);
        }

        .security-status-showcase.is-disabled .security-status-ring {
            animation: none;
            background: conic-gradient(from 0deg, rgba(148, 163, 184, 0.04) 0deg, rgba(148, 163, 184, 0.16) 180deg, rgba(203, 213, 225, 0.08) 360deg);
        }

        .security-status-showcase.is-disabled .security-status-particle {
            display: none;
        }

        .security-status-showcase.is-disabled .security-status-shield {
            color: #94a3b8;
            animation: none;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.28),
                0 12px 22px rgba(148, 163, 184, 0.08);
        }

        .security-status-showcase.is-disabled .security-status-shield::before {
            display: none;
        }

        .security-status-state {
            position: absolute;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 5;
            padding: 4px 9px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.42);
            border: 1px solid rgba(255, 255, 255, 0.52);
            color: rgba(71, 85, 105, 0.86);
            font-size: 10px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: 0.04em;
            white-space: nowrap;
            backdrop-filter: blur(12px);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.34),
                0 8px 18px rgba(15, 23, 42, 0.03);
        }

        .security-status-visual {
            position: relative;
            width: 112px;
            height: 112px;
            z-index: 1;
        }

        .security-status-core-glow {
            position: absolute;
            inset: 16px;
            border-radius: 999px;
            background:
                radial-gradient(circle, rgba(84, 178, 255, 0.22) 0%, rgba(84, 178, 255, 0.12) 34%, rgba(84, 178, 255, 0.02) 68%, transparent 100%),
                radial-gradient(circle at 65% 35%, rgba(111, 223, 210, 0.18) 0%, transparent 54%);
            filter: blur(16px);
            animation: shieldGlowPulse 4.8s ease-in-out infinite;
        }

        .security-status-ring {
            position: absolute;
            inset: 6px;
            border-radius: 999px;
            background: conic-gradient(from 0deg, rgba(68, 199, 191, 0) 0deg, rgba(68, 199, 191, 0.18) 95deg, rgba(0, 35, 102, 0.12) 175deg, rgba(29, 102, 255, 0.2) 255deg, rgba(68, 199, 191, 0) 360deg);
            animation: shieldOrbit 18s linear infinite;
            filter: blur(0.2px);
        }

        .security-status-ring::after {
            content: "";
            position: absolute;
            inset: 13px;
            border-radius: 999px;
            background:
                radial-gradient(circle at 30% 26%, rgba(255, 255, 255, 0.48) 0%, rgba(255, 255, 255, 0.16) 30%, rgba(255, 255, 255, 0.03) 62%, transparent 100%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.2) 0%, rgba(234, 244, 255, 0.1) 100%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.34),
                0 24px 44px rgba(0, 35, 102, 0.08);
            backdrop-filter: blur(20px);
        }

        .security-status-ring::before {
            content: "";
            position: absolute;
            inset: 26px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.4) 0%, rgba(226, 239, 255, 0.14) 48%, rgba(226, 239, 255, 0.02) 78%, transparent 100%);
            z-index: 1;
        }

        .security-status-particle {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 7px;
            height: 7px;
            margin: -3.5px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.94) 0%, rgba(79, 209, 197, 0.9) 32%, rgba(29, 102, 255, 0.88) 72%, rgba(29, 102, 255, 0) 100%);
            box-shadow: 0 0 10px rgba(79, 209, 197, 0.32);
            z-index: 4;
            transform-origin: 0 0;
        }

        .security-status-particle.is-one {
            animation: shieldParticleOne 8.4s linear infinite;
        }

        .security-status-particle.is-two {
            width: 6px;
            height: 6px;
            margin: -3px;
            animation: shieldParticleTwo 6.8s linear infinite;
            animation-delay: -2.1s;
        }

        .security-status-particle.is-three {
            width: 5px;
            height: 5px;
            margin: -2.5px;
            animation: shieldParticleThree 9.2s linear infinite;
            animation-delay: -4.2s;
        }

        .security-status-shield {
            position: absolute;
            inset: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            overflow: hidden;
            color: #2f7fff;
            background:
                radial-gradient(circle at 32% 24%, rgba(255, 255, 255, 0.5) 0%, rgba(255, 255, 255, 0.12) 32%, rgba(255, 255, 255, 0.04) 52%, transparent 72%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.2) 0%, rgba(231, 243, 255, 0.08) 100%);
            border: 1px solid rgba(255, 255, 255, 0.34);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.3),
                0 16px 28px rgba(0, 35, 102, 0.08);
            backdrop-filter: blur(20px);
            animation: shieldHeartbeat 4.2s ease-in-out infinite;
            z-index: 3;
        }

        .security-status-shield::before {
            content: "";
            position: absolute;
            inset: -12% 34% -12% -52%;
            background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.02) 22%, rgba(255, 255, 255, 0.72) 50%, rgba(255, 255, 255, 0.06) 66%, transparent 100%);
            transform: translateX(-165%) skewX(-10deg);
            animation: shieldSweep 3s cubic-bezier(0.22, 1, 0.36, 1) infinite;
        }

        .security-status-shield svg {
            position: relative;
            z-index: 2;
            width: 27px;
            height: 27px;
            display: block;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
            filter: drop-shadow(0 10px 18px rgba(47, 127, 255, 0.18));
        }

        .security-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.8fr);
            gap: 20px;
        }

        .security-panel {
            padding: 22px 24px;
            border: 1px solid #e7edf4;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.82);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
            backdrop-filter: blur(18px);
        }

        .security-panel-title {
            margin: 0;
            color: #111827;
            font-size: 22px;
            line-height: 1.3;
            font-weight: 800;
        }

        .security-panel-desc {
            margin-top: 8px;
            color: #98a2b3;
            font-size: 13px;
            line-height: 1.7;
        }

        .security-trend {
            width: 100%;
            margin-top: 18px;
        }

        .security-trend-chart {
            position: relative;
            width: 100%;
            height: 238px;
            border-radius: 24px;
            overflow: hidden;
            background:
                linear-gradient(180deg, rgba(0, 35, 102, 0.03) 0%, rgba(255, 255, 255, 0) 36%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.86) 0%, rgba(245, 248, 252, 0.72) 100%);
            border: 1px solid rgba(230, 236, 244, 0.92);
        }

        .security-trend-headline {
            position: absolute;
            left: 18px;
            top: 16px;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.86);
            border: 1px solid rgba(229, 236, 244, 0.9);
            backdrop-filter: blur(10px);
            color: #60708d;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
        }

        .security-trend-headline-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: linear-gradient(135deg, #002366 0%, #3f86ff 100%);
            box-shadow: 0 0 0 4px rgba(63, 134, 255, 0.08);
        }

        .security-trend-focus {
            position: absolute;
            right: 18px;
            top: 16px;
            z-index: 2;
            min-width: 124px;
            padding: 10px 14px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(229, 236, 244, 0.92);
            backdrop-filter: blur(10px);
            text-align: right;
        }

        .security-trend-focus-label {
            color: #7c8ca8;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.3;
        }

        .security-trend-focus-value {
            margin-top: 4px;
            color: #002366;
            font-size: 22px;
            line-height: 1;
            font-weight: 800;
        }

        .security-trend-svg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
        }

        .security-trend-gridline {
            stroke: rgba(148, 163, 184, 0.16);
            stroke-width: 1;
        }

        .security-trend-baseline {
            stroke: rgba(148, 163, 184, 0.34);
            stroke-width: 1.5;
            stroke-dasharray: 5 7;
        }

        .security-trend-area {
            fill: url(#securityTrendAreaFill);
            opacity: 0;
            animation: trendFadeIn 0.8s ease forwards 0.18s;
        }

        .security-trend-line {
            fill: none;
            stroke: #002366;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            path-length: 100;
            stroke-dasharray: 100;
            stroke-dashoffset: 100;
            animation: trendDraw 1.5s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        .security-trend-point {
            fill: #ffffff;
            stroke: #002366;
            stroke-width: 2.2;
            filter: drop-shadow(0 4px 12px rgba(0, 35, 102, 0.12));
        }

        .security-trend-zero-dot {
            fill: rgba(148, 163, 184, 0.72);
        }

        .security-trend-hotspot {
            fill: transparent;
            pointer-events: all;
        }

        .security-trend-axis {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 14px;
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            padding: 0 18px;
            pointer-events: none;
        }

        .security-trend-label {
            color: #94a3b8;
            font-size: 11px;
            line-height: 1;
            font-weight: 700;
            text-align: center;
        }

        .security-trend-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .security-trend-stat {
            display: grid;
            gap: 6px;
            padding: 14px 16px;
            border-radius: 18px;
            background: linear-gradient(180deg, rgba(248, 251, 255, 0.96) 0%, rgba(244, 248, 253, 0.92) 100%);
            border: 1px solid rgba(227, 235, 244, 0.96);
        }

        .security-trend-stat-label {
            color: #8b9bb4;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 700;
        }

        .security-trend-stat-value {
            color: #0f172a;
            font-size: 24px;
            line-height: 1;
            font-weight: 800;
        }

        .security-trend-stat-note {
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
            font-weight: 600;
        }

        .security-region {
            display: grid;
            gap: 14px;
            margin-top: 16px;
            padding: 18px;
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(248, 251, 255, 0.96) 0%, rgba(244, 248, 253, 0.9) 100%);
            border: 1px solid rgba(227, 235, 244, 0.96);
        }

        .security-region-heading {
            margin: 0;
            color: #344054;
            font-size: 14px;
            font-weight: 800;
        }

        .security-region-sub {
            margin-top: 6px;
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.6;
            font-weight: 600;
        }

        .security-region-list-items {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
        }

        .security-region-item {
            display: grid;
            gap: 8px;
            padding: 14px 16px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(231, 237, 244, 0.96);
        }

        .security-region-item-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .security-region-item-name {
            color: #344054;
            font-size: 14px;
            font-weight: 800;
        }

        .security-region-item-meta {
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
        }

        .security-region-track {
            position: relative;
            height: 8px;
            border-radius: 999px;
            overflow: hidden;
            background: rgba(226, 232, 240, 0.72);
        }

        .security-region-bar {
            position: absolute;
            inset: 0 auto 0 0;
            border-radius: 999px;
            background: linear-gradient(90deg, rgba(0, 35, 102, 0.28) 0%, rgba(63, 134, 255, 0.78) 100%);
        }

        .security-types {
            display: grid;
            gap: 12px;
            margin-top: 22px;
            max-height: 566px;
            overflow-y: auto;
            padding-right: 6px;
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.55) transparent;
        }

        .security-types::-webkit-scrollbar {
            width: 8px;
        }

        .security-types::-webkit-scrollbar-track {
            background: transparent;
        }

        .security-types::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.5);
            border-radius: 999px;
        }

        .security-types::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 163, 184, 0.72);
        }

        .security-type-item {
            display: grid;
            gap: 16px;
            padding: 12px 14px;
            border-radius: 16px;
            background: #f8fbff;
            border: 1px solid #edf2f7;
        }

        .security-type-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .security-type-name {
            color: #344054;
            font-size: 14px;
            font-weight: 700;
        }

        .security-type-value {
            color: var(--primary);
            font-size: 18px;
            font-weight: 800;
        }

        .security-type-track {
            position: relative;
            height: 10px;
            border-radius: 999px;
            overflow: hidden;
            background: #eaf1f7;
        }

        .security-type-bar {
            position: absolute;
            inset: 0 auto 0 0;
            border-radius: 999px;
            background: linear-gradient(90deg, rgba(0, 80, 179, 0.2), rgba(0, 80, 179, 0.58));
        }

        .security-type-meta {
            color: #98a2b3;
            font-size: 12px;
            font-weight: 700;
        }

        .security-events {
            display: grid;
            gap: 12px;
            margin-top: 22px;
        }

        .security-event {
            display: grid;
            gap: 10px;
            padding: 16px 18px;
            border-radius: 18px;
            background: #ffffff;
            border: 1px solid #edf2f7;
        }

        .security-event-top {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
        }

        .security-event-rule {
            color: #111827;
            font-size: 15px;
            font-weight: 700;
        }

        .security-event-time {
            color: #98a2b3;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .security-event-path {
            color: #667085;
            font-size: 13px;
            line-height: 1.7;
            word-break: break-all;
        }

        .security-event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .security-event-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #f8fbff;
            border: 1px solid #edf2f7;
            color: #667085;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
        }

        .security-event-chip.is-risk-high {
            background: rgba(254, 242, 242, 0.92);
            border-color: rgba(254, 202, 202, 0.95);
            color: #b91c1c;
        }

        .security-event-chip.is-risk-medium {
            background: rgba(255, 247, 237, 0.96);
            border-color: rgba(254, 215, 170, 0.95);
            color: #c2410c;
        }

        .security-event-chip.is-ip {
            background: #f8fafc;
            color: #475467;
        }

        .security-empty {
            padding: 24px 18px;
            border-radius: 18px;
            background: #f8fbff;
            border: 1px dashed #d7e3f1;
            color: #98a2b3;
            font-size: 13px;
            line-height: 1.8;
            text-align: center;
        }

        @media (max-width: 1180px) {
            .page-header {
                align-items: flex-start;
            }

            .security-metrics {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .security-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .page-header {
                flex-direction: column;
            }

            .security-metrics {
                grid-template-columns: 1fr;
            }

            .security-trend-stats {
                grid-template-columns: 1fr;
            }

            .security-region {
                grid-template-columns: 1fr;
            }

            .security-region-list-items {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .security-status-showcase {
                min-height: 0;
                padding-top: 4px;
            }

            .security-status-state {
                top: 8px;
                font-size: 9px;
                padding: 4px 8px;
            }

            .security-status-visual {
                width: 96px;
                height: 96px;
            }

            .security-status-shield {
                inset: 19px;
            }

            .security-status-shield svg {
                width: 22px;
                height: 22px;
            }

            .security-region-list-items {
                grid-template-columns: 1fr;
            }
        }

        @keyframes shieldOrbit {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes shieldSweep {
            0%, 18% {
                transform: translateX(-145%) skewX(-18deg);
            }
            48%, 100% {
                transform: translateX(180%) skewX(-18deg);
            }
        }

        @keyframes shieldHeartbeat {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        @keyframes shieldGlowPulse {
            0%, 100% {
                opacity: 0.74;
                transform: scale(0.98);
            }
            50% {
                opacity: 1;
                transform: scale(1.04);
            }
        }

        @keyframes shieldParticleOne {
            from {
                transform: rotate(0deg) translateX(45px) rotate(0deg);
            }
            to {
                transform: rotate(360deg) translateX(45px) rotate(-360deg);
            }
        }

        @keyframes shieldParticleTwo {
            from {
                transform: rotate(210deg) translateX(36px) rotate(-210deg);
            }
            to {
                transform: rotate(570deg) translateX(36px) rotate(-570deg);
            }
        }

        @keyframes shieldParticleThree {
            from {
                transform: rotate(120deg) translateX(52px) rotate(-120deg);
            }
            to {
                transform: rotate(480deg) translateX(52px) rotate(-480deg);
            }
        }

        @keyframes trendDraw {
            to {
                stroke-dashoffset: 0;
            }
        }

        @keyframes trendFadeIn {
            to {
                opacity: 1;
            }
        }
    </style>
@endpush

@section('content')
    <section class="page-header">
        <div class="page-header-main">
            <h2 class="page-header-title">安护盾</h2>
            <div class="page-header-desc">构建于硬核防火墙之后的 WAF 纵深防御体系，作为 Web 应用的第二道核心屏障，精准拦截各类应用层威胁。</div>
        </div>
    </section>

    <div class="security-shell">
        <section class="security-metrics">
            <article class="security-card">
                <div class="security-card-top">
                    <div class="security-card-label">今日拦截攻击</div>
                    <div class="security-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 3 5.5 6v5.2c0 4.1 2.5 7.7 6.5 9.8 4-2.1 6.5-5.7 6.5-9.8V6L12 3Z"></path>
                            <path d="m9.5 12 1.8 1.8 3.4-3.6"></path>
                        </svg>
                    </div>
                </div>
                <div class="security-card-value">{{ number_format($security['today_blocked']) }}</div>
                <div class="security-card-note">今天已经拦下的异常请求。</div>
            </article>
            <article class="security-card">
                <div class="security-card-top">
                    <div class="security-card-label">累计拦截次数</div>
                    <div class="security-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 3 5.5 6v5.2c0 4.1 2.5 7.7 6.5 9.8 4-2.1 6.5-5.7 6.5-9.8V6L12 3Z"></path>
                            <path d="M12 9v3.8"></path>
                            <path d="M12 16h.01"></path>
                        </svg>
                    </div>
                </div>
                <div class="security-card-value">{{ number_format($security['total_blocked']) }}</div>
                <div class="security-card-note">当前站点累计拦截次数。</div>
            </article>
            <article class="security-card">
                <div class="security-card-top">
                    <div class="security-card-label">近 7 天最高峰值</div>
                    <div class="security-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 18V6"></path>
                            <path d="M4 18h16"></path>
                            <path d="m7 14 3-3 3 2 4-5"></path>
                        </svg>
                    </div>
                </div>
                <div class="security-card-value">{{ number_format($security['peak_blocked']) }}</div>
                <div class="security-card-note">最近 7 天单日最高拦截值。</div>
            </article>
            <article class="security-card is-status">
                <div class="security-status-showcase{{ ($security['status_tone'] ?? 'running') === 'disabled' ? ' is-disabled' : '' }}">
                    <div class="security-status-visual" aria-hidden="true">
                        <div class="security-status-core-glow"></div>
                        <div class="security-status-ring"></div>
                        <span class="security-status-particle is-one"></span>
                        <span class="security-status-particle is-two"></span>
                        <span class="security-status-particle is-three"></span>
                        <div class="security-status-shield">
                            <svg viewBox="0 0 24 24">
                                <path d="M12 3 5.5 6v5.2c0 4.1 2.5 7.7 6.5 9.8 4-2.1 6.5-5.7 6.5-9.8V6L12 3Z"></path>
                                <path d="m9.5 12 1.8 1.8 3.4-3.6"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="security-status-state">{{ $security['status_label'] }}</div>
                </div>
            </article>
        </section>

        <section class="security-grid">
            <article class="security-panel">
                <h3 class="security-panel-title">近 7 天拦截趋势</h3>
                @php
                    $trendItems = collect($security['trend'])->values();
                    $trendTotal = (int) $trendItems->sum('value');
                    $todayValue = (int) ($trendItems->last()['value'] ?? 0);
                    $yesterdayValue = (int) ($trendItems->slice(-2, 1)->first()['value'] ?? 0);
                    $delta = $todayValue - $yesterdayValue;
                    $leadType = collect($security['types'])->sortByDesc('value')->first();
                    $regionItems = collect($security['regions'] ?? []);
                    $chartWidth = 760;
                    $chartHeight = 238;
                    $chartPaddingX = 22;
                    $chartTop = 70;
                    $chartBottom = 50;
                    $chartInnerWidth = $chartWidth - ($chartPaddingX * 2);
                    $chartInnerHeight = $chartHeight - $chartTop - $chartBottom;
                    $pointCount = max(1, $trendItems->count());
                    $peakValue = max(1, (int) $trendItems->max('value'));
                    $quietDays = $trendItems->where('value', 0)->count();
                    $stepX = $pointCount > 1 ? $chartInnerWidth / ($pointCount - 1) : 0;
                    $points = [];

                    foreach ($trendItems as $index => $item) {
                        $x = $chartPaddingX + ($stepX * $index);
                        $y = $chartTop + $chartInnerHeight - (($item['value'] / $peakValue) * $chartInnerHeight);
                        $points[] = [
                            'x' => round($x, 2),
                            'y' => round($y, 2),
                            'label' => $item['label'],
                            'value' => (int) $item['value'],
                        ];
                    }

                    $linePath = '';
                    if (count($points) === 1) {
                        $linePath = 'M ' . $points[0]['x'] . ' ' . $points[0]['y'];
                    } elseif (count($points) > 1) {
                        $linePath = 'M ' . $points[0]['x'] . ' ' . $points[0]['y'];
                        for ($i = 0; $i < count($points) - 1; $i++) {
                            $current = $points[$i];
                            $next = $points[$i + 1];
                            $controlX = round(($current['x'] + $next['x']) / 2, 2);
                            $linePath .= ' Q ' . $current['x'] . ' ' . $current['y'] . ' ' . $controlX . ' ' . round(($current['y'] + $next['y']) / 2, 2);
                        }
                        $lastIndex = count($points) - 1;
                        $prev = $points[$lastIndex - 1];
                        $last = $points[$lastIndex];
                        $linePath .= ' Q ' . $prev['x'] . ' ' . $prev['y'] . ' ' . $last['x'] . ' ' . $last['y'];
                    }

                    $baselineY = $chartTop + $chartInnerHeight;
                    $areaPath = $linePath !== ''
                        ? $linePath . ' L ' . $points[array_key_last($points)]['x'] . ' ' . $baselineY . ' L ' . $points[0]['x'] . ' ' . $baselineY . ' Z'
                        : '';
                @endphp
                <div class="security-trend">
                    <div class="security-trend-chart">
                        <div class="security-trend-headline">
                            <span class="security-trend-headline-dot"></span>
                            <span>{{ $quietDays > 0 ? ('近 7 天有 ' . $quietDays . ' 天处于静默拦截') : '近 7 天每天都有拦截记录' }}</span>
                        </div>
                        <div class="security-trend-focus">
                            <div class="security-trend-focus-label">当前最高点</div>
                            <div class="security-trend-focus-value">{{ number_format($security['peak_blocked']) }}</div>
                        </div>
                        <svg class="security-trend-svg" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="securityTrendAreaFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#002366" stop-opacity="0.4"></stop>
                                    <stop offset="100%" stop-color="#002366" stop-opacity="0"></stop>
                                </linearGradient>
                            </defs>
                            <line class="security-trend-gridline" x1="{{ $chartPaddingX }}" y1="{{ $chartTop + 26 }}" x2="{{ $chartWidth - $chartPaddingX }}" y2="{{ $chartTop + 26 }}"></line>
                            <line class="security-trend-gridline" x1="{{ $chartPaddingX }}" y1="{{ $chartTop + 78 }}" x2="{{ $chartWidth - $chartPaddingX }}" y2="{{ $chartTop + 78 }}"></line>
                            <line class="security-trend-baseline" x1="{{ $chartPaddingX }}" y1="{{ $baselineY }}" x2="{{ $chartWidth - $chartPaddingX }}" y2="{{ $baselineY }}"></line>
                            @if ($areaPath !== '')
                                <path class="security-trend-area" d="{{ $areaPath }}"></path>
                            @endif
                            @if ($linePath !== '')
                                <path class="security-trend-line" d="{{ $linePath }}"></path>
                            @endif
                            @foreach ($points as $index => $point)
                                @php
                                    $hotspotX = $point['x'] - (($index === 0 || $index === count($points) - 1) ? 28 : 44);
                                    $hotspotWidth = $index === 0 || $index === count($points) - 1 ? 56 : 88;
                                @endphp
                                <rect class="security-trend-hotspot" x="{{ $hotspotX }}" y="{{ $chartTop }}" width="{{ $hotspotWidth }}" height="{{ $chartInnerHeight + 16 }}" data-tooltip="{{ $point['label'] }} · {{ number_format($point['value']) }} 次"></rect>
                                @if ($point['value'] > 0)
                                    <circle class="security-trend-point" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5.4"></circle>
                                @else
                                    <circle class="security-trend-zero-dot" cx="{{ $point['x'] }}" cy="{{ $baselineY }}" r="3.1"></circle>
                                @endif
                            @endforeach
                        </svg>
                        <div class="security-trend-axis">
                            @foreach ($trendItems as $item)
                                <div class="security-trend-label">{{ $item['label'] }}</div>
                            @endforeach
                        </div>
                    </div>
                    <div class="security-trend-stats">
                        <div class="security-trend-stat">
                            <div class="security-trend-stat-label">近 7 天累计</div>
                            <div class="security-trend-stat-value">{{ number_format($trendTotal) }}</div>
                            <div class="security-trend-stat-note">最近一周命中的总拦截次数</div>
                        </div>
                        <div class="security-trend-stat">
                            <div class="security-trend-stat-label">单日峰值</div>
                            <div class="security-trend-stat-value">{{ number_format($security['peak_blocked']) }}</div>
                            <div class="security-trend-stat-note">近 7 天单日最高拦截值</div>
                        </div>
                        <div class="security-trend-stat">
                            <div class="security-trend-stat-label">今日较昨日</div>
                            <div class="security-trend-stat-value">{{ $delta > 0 ? '+' : '' }}{{ number_format($delta) }}</div>
                            <div class="security-trend-stat-note">{{ $leadType ? ('主要类型：' . $leadType['label']) : '当前还没有主要拦截类型' }}</div>
                        </div>
                    </div>
                    <div class="security-region">
                        <h4 class="security-region-heading">攻击区域</h4>
                        <div class="security-region-sub">近 7 天命中的拦截记录里，主要攻击来源区域如下。</div>
                        <div class="security-region-list-items">
                            @forelse ($regionItems as $region)
                                <div class="security-region-item">
                                    <div class="security-region-item-top">
                                        <div class="security-region-item-name">{{ $region['label'] }}</div>
                                        <div class="security-region-item-meta">{{ number_format($region['value']) }} 次 · {{ $region['ratio'] }}%</div>
                                    </div>
                                    <div class="security-region-track">
                                        <div class="security-region-bar" style="width: {{ $region['ratio'] }}%;"></div>
                                    </div>
                                </div>
                            @empty
                                <div class="security-empty">当前还没有足够的来源区域数据。</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </article>

            <article class="security-panel">
                <h3 class="security-panel-title">拦截类型分布</h3>
                <div class="security-panel-desc">看看近 7 天主要拦下了哪些异常请求。</div>
                <div class="security-types">
                    @forelse ($security['types'] as $item)
                        <div class="security-type-item">
                            <div class="security-type-top">
                                <div class="security-type-name">{{ $item['label'] }}</div>
                                <div class="security-type-value">{{ number_format($item['value']) }}</div>
                            </div>
                            <div class="security-type-track">
                                <div class="security-type-bar" style="width: {{ $item['ratio'] }}%;"></div>
                            </div>
                            <div class="security-type-meta">占比 {{ $item['ratio'] }}%</div>
                        </div>
                    @empty
                        <div class="security-empty">当前还没有拦截记录。功能已经就绪，后续有命中时会在这里显示。</div>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="security-panel">
            <h3 class="security-panel-title">最近拦截记录</h3>
            <div class="security-panel-desc">展示最近命中的拦截记录、访问 IP、处置方式和防护类型。</div>
            <div class="security-events">
                @forelse ($security['events'] as $event)
                    <article class="security-event">
                        <div class="security-event-top">
                            <div class="security-event-rule">{{ $event['rule_name'] }}</div>
                            <div class="security-event-time">{{ $event['created_at_label'] }}</div>
                        </div>
                        <div class="security-event-meta">
                            <span class="security-event-chip">{{ $event['category_label'] }}</span>
                            <span class="security-event-chip {{ $event['risk_label'] === '高危' ? 'is-risk-high' : 'is-risk-medium' }}">{{ $event['risk_label'] }}</span>
                            <span class="security-event-chip">{{ $event['action_label'] }}</span>
                            <span class="security-event-chip is-ip">IP {{ $event['client_ip'] }}</span>
                        </div>
                        <div class="security-event-path">{{ $event['request_method'] }} · {{ $event['request_path'] }}</div>
                    </article>
                @empty
                    <div class="security-empty">当前还没有最近拦截记录。站点端会在命中拦截后自动更新，不需要手动操作。</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
