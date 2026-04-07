<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ page.title }}</title>
    <meta name="keywords" content="{{ page.meta_keywords }}">
    <meta name="description" content="{{ page.meta_description }}">
    <style>
        :root {
            --bg: #f5f8f6;
            --panel: #ffffff;
            --line: #e5ece8;
            --text: #1f2d2a;
            --muted: #70807b;
            --primary: #1d6a5e;
            --primary-deep: #154b43;
            --primary-soft: rgba(29, 106, 94, 0.08);
            --shadow: 0 18px 40px rgba(18, 55, 46, 0.06);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            color: var(--text);
            font-family: "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
            background:
                radial-gradient(circle at top left, rgba(214, 234, 223, 0.55), transparent 24%),
                linear-gradient(180deg, #fcfefd 0%, var(--bg) 44%, #f7faf8 100%);
        }
        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; display: block; }
        .container { width: min(1180px, calc(100% - 40px)); margin: 0 auto; }
        .site-header {
            position: sticky;
            top: 0;
            z-index: 30;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(29, 106, 94, 0.08);
        }
        .site-header-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 18px 0;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }
        .brand-logo {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            overflow: hidden;
            background: linear-gradient(145deg, #eef7f2, #ddece4);
            border: 1px solid rgba(29, 106, 94, 0.12);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.78);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-deep);
            font-size: 18px;
            font-weight: 700;
            flex-shrink: 0;
        }
        .brand-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .brand-title {
            display: block;
            font-size: 28px;
            line-height: 1.2;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .brand-subtitle {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            font-size: 14px;
        }
        .site-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }
        .site-nav a {
            display: inline-flex;
            align-items: center;
            min-height: 40px;
            padding: 0 16px;
            border-radius: 999px;
            color: var(--muted);
            transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
        }
        .site-nav a:hover,
        .site-nav a.is-active {
            background: rgba(255, 255, 255, 0.78);
            color: var(--primary-deep);
            box-shadow: inset 0 0 0 1px rgba(29, 106, 94, 0.1);
        }
        .theme-main {
            padding: 32px 0 48px;
        }
        .theme-floating-promo {
            position: fixed;
            display: block;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 18px 40px rgba(18, 55, 46, 0.16);
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(29, 106, 94, 0.1);
            backdrop-filter: blur(10px);
            opacity: 0;
            transform: translateY(12px) scale(0.96);
            transition: opacity 0.28s ease, transform 0.28s ease, box-shadow 0.18s ease;
            will-change: transform, opacity;
        }
        .theme-floating-promo.is-ready {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        .theme-floating-promo:hover {
            box-shadow: 0 22px 50px rgba(18, 55, 46, 0.2);
        }
        .theme-floating-promo img {
            width: 100%;
            height: auto;
            display: block;
        }
        .theme-floating-promo-close {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 28px;
            height: 28px;
            border: 0;
            border-radius: 999px;
            background: rgba(20, 35, 31, 0.58);
            color: #fff;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
        }
        .theme-floating-promo-close:hover {
            background: rgba(20, 35, 31, 0.74);
        }
        .theme-floating-promo.is-show-on-pc {}
        .theme-floating-promo.is-show-on-mobile {}
        .theme-floating-promo.is-anim-float {
            animation: themeFloatingFloat 3.4s ease-in-out infinite;
        }
        .theme-floating-promo.is-anim-pulse {
            animation: themeFloatingPulse 2.8s ease-in-out infinite;
        }
        .theme-floating-promo.is-anim-sway {
            animation: themeFloatingSway 4.2s ease-in-out infinite;
            transform-origin: center top;
        }
        .panel {
            background: var(--panel);
            border: 1px solid rgba(29, 106, 94, 0.08);
            border-radius: 28px;
            box-shadow: var(--shadow);
        }
        .site-footer {
            padding: 0 0 42px;
        }
        .site-footer-card {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            padding: 22px 24px;
            border-radius: 26px;
            background: rgba(21, 75, 67, 0.96);
            color: rgba(245, 251, 248, 0.92);
            box-shadow: 0 18px 44px rgba(17, 58, 49, 0.18);
        }
        .site-footer-copy {
            font-size: 15px;
            line-height: 1.8;
        }
        .site-footer-meta {
            text-align: right;
            color: rgba(245, 251, 248, 0.7);
            font-size: 13px;
            line-height: 1.8;
        }
        @media (max-width: 960px) {
            .site-header-inner,
            .site-footer-card {
                flex-direction: column;
                align-items: flex-start;
            }
            .site-nav {
                justify-content: flex-start;
            }
            .theme-floating-promo.is-show-on-pc {
                display: none !important;
            }
        }
        @media (max-width: 640px) {
            .brand-title { font-size: 24px; }
            .container { width: min(100% - 24px, 1180px); }
            .theme-main { padding-top: 22px; }
            .theme-floating-promo {
                max-width: min(220px, calc(100vw - 24px));
                border-radius: 18px;
            }
            .theme-floating-promo.is-show-on-pc {
                display: none !important;
            }
        }
        @media (min-width: 641px) {
            .theme-floating-promo.is-show-on-mobile {
                display: none !important;
            }
        }
        @keyframes themeFloatingFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        @keyframes themeFloatingPulse {
            0%, 100% { box-shadow: 0 18px 40px rgba(18, 55, 46, 0.16); }
            50% { box-shadow: 0 22px 50px rgba(18, 55, 46, 0.24); }
        }
        @keyframes themeFloatingSway {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(1.4deg); }
            75% { transform: rotate(-1.4deg); }
        }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .theme-floating-promo,
            .theme-floating-promo.is-anim-float,
            .theme-floating-promo.is-anim-pulse,
            .theme-floating-promo.is-anim-sway {
                animation: none !important;
                transition: none !important;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container site-header-inner">
            <a class="brand" href="/site-preview?site={{ site.site_key }}">
                <span class="brand-logo">
                    {% if site.logo %}
                        <img src="{{ site.logo }}" alt="{{ site.name }}">
                    {% else %}
                        校
                    {% endif %}
                </span>
                <span>
                    <span class="brand-title">{{ site.name }}</span>
                    <span class="brand-subtitle">{% if site.address %}{{ site.address }}{% else %}面向学校官网建设的内容展示模板{% endif %}</span>
                </span>
            </a>

            <nav class="site-nav">
                {% for navItem in navItems %}
                    {% if activeChannelSlug == navItem.slug %}
                        <a class="is-active" href="{{ navItem.url }}"{% if navItem.target == '_blank' %} target="_blank" rel="noopener noreferrer"{% endif %}>{{ navItem.name }}</a>
                    {% else %}
                        <a href="{{ navItem.url }}"{% if navItem.target == '_blank' %} target="_blank" rel="noopener noreferrer"{% endif %}>{{ navItem.name }}</a>
                    {% endif %}
                {% endfor %}
            </nav>
        </div>
    </header>

    <main class="theme-main">
