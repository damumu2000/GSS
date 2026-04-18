@php
    $adminBrandSettings = app(\App\Support\SystemSettings::class)->formDefaults();
    $sessionStatus = session('status');
    $sessionErrors = session('errors');
    $sessionErrorMessage = null;
    if ($sessionErrors instanceof \Illuminate\Support\ViewErrorBag) {
        $sessionErrorMessage = $sessionErrors->first();
    } elseif ($sessionErrors instanceof \Illuminate\Support\MessageBag) {
        $sessionErrorMessage = $sessionErrors->first();
    }
    $flashMessage = is_string($sessionStatus) && trim($sessionStatus) !== '' ? $sessionStatus : $sessionErrorMessage;
    $flashType = $flashMessage === $sessionErrorMessage
        ? 'error'
        : (is_string($flashMessage) && preg_match('/(失败|错误|不能|无权|禁止|驳回|不支持|请输入|请先|不能为空|未填写|必填|缺少)/u', $flashMessage) ? 'error' : 'success');
    $themeChoices = [
        'geek-blue',
        'mint-fresh',
        'sun-amber',
        'aurora-purple',
        'coral-rose',
        'ice-cyan',
        'candy-magenta',
        'sunset-orange',
        'lime-glow',
    ];
    $cookieTheme = (string) request()->cookie('admin_theme', '');
    $activeTheme = in_array($cookieTheme, $themeChoices, true) ? $cookieTheme : 'mint-fresh';
@endphp
<!DOCTYPE html>
<html lang="zh-CN" class="admin-theme--{{ $activeTheme }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $adminBrandSettings['system_name'])</title>
    @if (! empty($adminBrandSettings['admin_favicon']))
        <link rel="icon" href="{{ $adminBrandSettings['admin_favicon'] }}">
    @endif
    @stack('styles')
        <link rel="stylesheet" href="/css/admin-layout.css">

</head>
<body
    @if ($flashMessage)
        data-admin-status-message="{{ $flashMessage }}"
        data-admin-status-type="{{ $flashType }}"
    @endif
>
@php
    $authUser = auth()->user();
    $adminLayout = app(\App\Support\Admin\AdminLayoutData::class)->build(
        request(),
        $authUser,
        $currentSite ?? null,
        $sites ?? [],
        (bool) ($showSiteSwitcher ?? false),
    );
    [
        'currentSite' => $currentSite,
        'sites' => $sites,
        'showSiteSwitcher' => $showSiteSwitcher,
        'displayName' => $displayName,
        'headerRoleLabel' => $headerRoleLabel,
        'profileRoute' => $profileRoute,
        'activeAdminArea' => $activeAdminArea,
        'menuGroups' => $menuGroups,
    ] = $adminLayout;

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
        'guestbook' => '<svg viewBox="0 0 24 24"><path d="M7 8.5h10"/><path d="M7 12h6"/><path d="M8 17H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-7l-4 3z"/></svg>',
        'payroll' => '<svg viewBox="0 0 24 24"><path d="M5 4h11l3 3v13H5z"/><path d="M8 11h8"/><path d="M8 15h5"/><path d="M15 4v4h4"/><path d="M8 8h3"/></svg>',
        'database' => '<svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="7" ry="3"/><path d="M5 5v6c0 1.66 3.13 3 7 3s7-1.34 7-3V5"/><path d="M5 11v6c0 1.66 3.13 3 7 3s7-1.34 7-3v-6"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24"><path d="M12 3 5 6v6c0 5 3.2 8.6 7 10 3.8-1.4 7-5 7-10V6z"/><path d="m9.5 12 1.7 1.7 3.3-3.4"/></svg>',
        'setting' => '<svg viewBox="0 0 24 24"><path d="M12 15.5A3.5 3.5 0 1 0 12 8.5a3.5 3.5 0 0 0 0 7z"/><path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a2 2 0 1 1-4 0v-.2a1 1 0 0 0-.6-.9 1 1 0 0 0-1.1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a2 2 0 1 1 0-4h.2a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1 1 0 0 0 1.1.2 1 1 0 0 0 .6-.9V4a2 2 0 1 1 4 0v.2a1 1 0 0 0 .6.9 1 1 0 0 0 1.1-.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1 1 0 0 0-.2 1.1 1 1 0 0 0 .9.6H20a2 2 0 1 1 0 4h-.2a1 1 0 0 0-.9.6z"/></svg>',
        'log' => '<svg viewBox="0 0 24 24"><path d="M12 8v5l3 2"/><path d="M20 12a8 8 0 1 1-2.34-5.66"/><path d="M20 4v5h-5"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>',
        'tag' => '<svg viewBox="0 0 24 24"><path d="M20.59 13.41 12 22l-9-9V4h9z"/><path d="M7.5 8.5h.01"/></svg>',
        'chevron-down' => '<svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>',
        'site-switch' => '<svg viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h10"/><path d="m17 15 3 3-3 3"/><path d="M20 18h-7"/></svg>',
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

@endphp
<div class="shell">
    <aside class="sidebar" data-admin-sidebar>
        <div class="brand">
            <a class="brand-logo" href="{{ route('admin.entry') }}" aria-label="后台首页">
                <img src="{{ $adminBrandSettings['admin_logo'] }}" alt="{{ $adminBrandSettings['system_name'] }}">
            </a>
        </div>

        <div class="sidebar-scroll-wrap">
            <div class="sidebar-scroll-fade is-top" data-sidebar-fade-top aria-hidden="true"></div>
            <nav class="sidebar-nav" data-admin-sidebar-scroll>
                @foreach ($menuGroups as $group)
                    @if (! empty($group['items']))
                        <div class="menu-group">
                            <div class="menu-title">{{ $group['title'] }}</div>
                            @foreach ($group['items'] as $item)
                                <a class="menu-link {{ $item['active'] ? 'active' : '' }}" href="{{ isset($item['route_params']) ? route($item['route'], $item['route_params']) : route($item['route']) }}">
                                    <span class="menu-icon">{!! $icons[$item['icon']] ?? '' !!}</span>
                                    @if (! empty($item['prefix_badge']))
                                        <span class="menu-link-prefix-badge">{{ $item['prefix_badge'] }}</span>
                                    @endif
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
            <div class="sidebar-scroll-indicator" aria-hidden="true">
                <span class="sidebar-scroll-thumb" data-sidebar-scroll-thumb></span>
            </div>
            <div class="sidebar-scroll-fade is-bottom" data-sidebar-fade-bottom aria-hidden="true"></div>
        </div>

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
                @if ($showSiteSwitcher)
                    <div class="site-context-switcher" data-site-context-switcher>
                        <form class="site-context-switcher-form" method="POST" action="{{ route('admin.site-context.update') }}">
                            @csrf
                            <input type="hidden" name="site_id" value="{{ (int) ($currentSite->id ?? 0) }}" data-site-context-input>
                            <button class="site-context-switcher-trigger" type="button" data-site-context-trigger data-tooltip="切换站点" aria-haspopup="listbox" aria-expanded="false">
                                <span class="button-icon">{!! $icons['site-switch'] !!}</span>
                                <span class="site-context-switcher-copy">
                                    <span class="site-context-switcher-name">{{ $currentSite->name ?? '切换站点' }}</span>
                                </span>
                                <span class="site-context-switcher-caret">{!! $icons['chevron-down'] !!}</span>
                            </button>
                            <div class="site-context-switcher-panel" data-site-context-panel>
                                <div class="site-context-switcher-search-wrap">
                                    <input class="site-context-switcher-search" type="search" placeholder="搜索站点名称" data-site-context-search>
                                </div>
                                <div class="site-context-switcher-list" role="listbox" data-site-context-list>
                                    @foreach ($sites as $siteOption)
                                        <button
                                            class="site-context-switcher-option @if ((int) $siteOption->id === (int) ($currentSite->id ?? 0)) is-active @endif"
                                            type="button"
                                            data-site-context-option
                                            data-site-id="{{ (int) $siteOption->id }}"
                                            data-site-name="{{ $siteOption->name }}"
                                            data-site-key="{{ $siteOption->site_key }}"
                                            role="option"
                                            aria-selected="{{ (int) $siteOption->id === (int) ($currentSite->id ?? 0) ? 'true' : 'false' }}"
                                        >
                                            <span class="site-context-switcher-option-name">{{ $siteOption->name }}</span>
                                            <span class="site-context-switcher-option-meta">{{ '站点标识：' . $siteOption->site_key }}</span>
                                        </button>
                                    @endforeach
                                    <div class="site-context-switcher-empty" data-site-context-empty hidden>没有找到匹配的站点。</div>
                                </div>
                            </div>
                        </form>
                    </div>
                @endif
                <div class="theme-switcher" data-theme-switcher>
                    <button class="theme-switcher-trigger" type="button" data-theme-trigger data-tooltip="界面风格">
                        <span class="button-icon">{!! $icons['palette'] !!}</span>
                    </button>
                    <div class="theme-switcher-panel">
                        <p class="theme-switcher-title">界面风格</p>
                        <div class="theme-switcher-divider"></div>
                        <div class="theme-switcher-options">
                            <div class="theme-switcher-item">
                                <button class="theme-swatch theme-swatch--mint-fresh" type="button" data-theme-choice="mint-fresh">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-leaf'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">薄荷夏日</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                            <div class="theme-switcher-item">
                                <button class="theme-swatch theme-swatch--geek-blue" type="button" data-theme-choice="geek-blue">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-chip'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">纯净宝蓝</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                            <div class="theme-switcher-item">
                                <button class="theme-swatch theme-swatch--sun-amber" type="button" data-theme-choice="sun-amber">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-sun'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">初生暖阳</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                            <div class="theme-switcher-item">
                                <button class="theme-swatch theme-swatch--aurora-purple" type="button" data-theme-choice="aurora-purple">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-star'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">幻影霓虹</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                            <div class="theme-switcher-item">
                                <button class="theme-swatch theme-swatch--coral-rose" type="button" data-theme-choice="coral-rose">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-heart'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">元气珊瑚</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                            <div class="theme-switcher-item">
                                <button class="theme-swatch theme-swatch--ice-cyan" type="button" data-theme-choice="ice-cyan">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-snow'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">冰晶之域</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                            <div class="theme-switcher-item">
                                <button class="theme-swatch theme-swatch--candy-magenta" type="button" data-theme-choice="candy-magenta">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-spark'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">糖果粉紫</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                            <div class="theme-switcher-item">
                                <button class="theme-swatch theme-swatch--sunset-orange" type="button" data-theme-choice="sunset-orange">
                                    <span class="theme-swatch-preview">
                                        <span class="theme-swatch-icon">{!! $icons['theme-sun'] !!}</span>
                                    </span>
                                    <span class="theme-swatch-name"><span class="theme-swatch-label">燃情落日</span><span class="theme-swatch-status" aria-hidden="true"></span></span>
                                </button>
                            </div>
                            <div class="theme-switcher-item">
                                <button class="theme-swatch theme-swatch--lime-glow" type="button" data-theme-choice="lime-glow">
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
<script src="/js/toast-config.js"></script>
<script src="/js/admin-layout.js"></script>
@stack('scripts')
</html>
