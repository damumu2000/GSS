@php
    $tabs = [
        ['key' => 'settings', 'label' => '公众号配置', 'route' => route('admin.wechat-official.settings')],
        ['key' => 'menus', 'label' => '菜单管理', 'route' => route('admin.wechat-official.menus')],
        ['key' => 'articles', 'label' => '文章推送', 'route' => route('admin.wechat-official.articles')],
        ['key' => 'materials', 'label' => '素材管理', 'route' => route('admin.wechat-official.materials')],
        ['key' => 'logs', 'label' => '接口日志', 'route' => route('admin.wechat-official.logs')],
    ];
@endphp

<nav class="wechat-official-nav" aria-label="微信公众号模块导航">
    @foreach ($tabs as $tab)
        @php
            $tabDisabled = ($tab['key'] ?? '') !== 'settings' && empty($wechatOfficialModuleEnabled);
        @endphp
        @if ($tabDisabled)
            <span class="wechat-official-nav-link is-disabled">{{ $tab['label'] }}</span>
        @else
            <a class="wechat-official-nav-link @if (($activeWechatOfficialTab ?? '') === $tab['key']) is-active @endif" href="{{ $tab['route'] }}">{{ $tab['label'] }}</a>
        @endif
    @endforeach
</nav>
