@extends('layouts.admin')

@section('title', '菜单管理 - 微信公众号 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 微信公众号菜单管理')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/wechat-official-admin.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/wechat-official-admin.js') }}" defer></script>
@endpush

@section('content')
    @php
        $wechatCreateMenuMode = request()->query('mode') === 'create' || old('mode') === 'create';
        $wechatMenuTypeOptions = [
            'click' => '发送消息',
            'view' => '跳转网页',
            'media_id' => '下发素材',
        ];
        $wechatRequestedLevel = (int) old('level', request()->integer('level', 1));
        $wechatRequestedParentId = (int) old('parent_id', request()->integer('parent_id', 0));

        $wechatMenuFlatItems = collect($wechatMenuGroups)
            ->flatMap(function (array $group) {
                return collect([$group['parent']])->concat($group['children'])->values();
            })
            ->values();

        $wechatSelectedMenuId = (int) request()->integer('menu');
        $wechatSelectedMenu = $wechatCreateMenuMode ? null : ($wechatMenuFlatItems->firstWhere('id', $wechatSelectedMenuId) ?? $wechatMenuFlatItems->first());
        $wechatSelectedMenuLevel = $wechatSelectedMenu ? (int) $wechatSelectedMenu->level : 0;
        $wechatSelectedParent = null;
        $wechatSelectedParentId = 0;

        if ($wechatSelectedMenu) {
            if ($wechatSelectedMenuLevel === 2) {
                $wechatSelectedParentId = (int) $wechatSelectedMenu->parent_id;
                $wechatSelectedParent = $wechatMenuFlatItems->firstWhere('id', $wechatSelectedParentId);
            } else {
                $wechatSelectedParentId = (int) $wechatSelectedMenu->id;
                $wechatSelectedParent = $wechatSelectedMenu;
            }
        } elseif ($wechatCreateMenuMode && $wechatRequestedLevel === 2 && $wechatRequestedParentId > 0) {
            $wechatSelectedParentId = $wechatRequestedParentId;
            $wechatSelectedParent = $wechatMenuFlatItems->firstWhere('id', $wechatSelectedParentId);
        }

        $wechatSelectedGroup = null;
        $wechatSelectedChildren = collect();
        if ($wechatSelectedParentId > 0) {
            $wechatSelectedGroup = collect($wechatMenuGroups)->first(fn ($group) => (int) ($group['parent']->id ?? 0) === $wechatSelectedParentId);
            $wechatSelectedChildren = $wechatSelectedGroup['children'] ?? collect();
        }

        $wechatCanCreateTopMenu = $wechatTopMenus->count() < 3;
        $wechatCanCreateSubMenu = $wechatSelectedParent && $wechatSelectedChildren instanceof \Illuminate\Support\Collection && $wechatSelectedChildren->count() < 5;
        $wechatRequestedParentMenu = $wechatTopMenus->firstWhere('id', $wechatRequestedParentId);
    @endphp

    <section class="wechat-official-header">
        <div>
            <h1 class="wechat-official-title">菜单管理</h1>
            <div class="wechat-official-desc">先在系统里维护公众号菜单结构，再按配置一键同步到微信。左侧按层级树管理菜单，右侧直接编辑当前选中项。</div>
        </div>
        <div class="page-header-actions wechat-official-menu-header-actions">
            <form method="POST" action="{{ route('admin.wechat-official.menus.pull') }}" onsubmit="return window.confirm('将使用公众号当前菜单覆盖当前页面菜单，本地未同步改动会被替换，确认继续吗？');">
                @csrf
                <button class="button button-secondary" type="submit">从公众号拉取</button>
            </form>
            <form method="POST" action="{{ route('admin.wechat-official.menus.sync') }}">
                @csrf
                <button class="button" type="submit">同步到公众号</button>
            </form>
            <a class="button button-secondary" href="{{ route('admin.wechat-official.menus', ['mode' => 'create']) }}">新增菜单</a>
        </div>
    </section>

    @include('wechat_official::admin._nav')

    <section class="wechat-official-menu-notice">
        <span class="wechat-official-menu-notice-item">先在当前页面编辑本地菜单</span>
        <span class="wechat-official-menu-notice-divider">/</span>
        <span class="wechat-official-menu-notice-item">“同步到公众号”会把本地菜单发布到微信</span>
        <span class="wechat-official-menu-notice-divider">/</span>
        <span class="wechat-official-menu-notice-item">“从公众号拉取”会用微信当前菜单覆盖本地菜单</span>
    </section>

    <section class="wechat-official-menu-workspace">
        <article class="wechat-official-panel wechat-official-menu-phone-card">
            <div class="wechat-official-menu-phone">
                <div class="wechat-official-menu-phone-topbar">
                    <span>1:21</span>
                    <span class="wechat-official-menu-phone-status">
                        <i></i><i></i><i></i>
                    </span>
                </div>

                <div class="wechat-official-menu-phone-screen">
                    <div class="wechat-official-menu-phone-navline">
                        <span class="wechat-official-menu-phone-back">‹</span>
                        <span class="wechat-official-menu-phone-title">{{ $wechatOfficialName }}</span>
                        <span class="wechat-official-menu-phone-user"></span>
                    </div>

                </div>

                <div class="wechat-official-menu-phone-bar">
                    <div class="wechat-official-menu-phone-icon-slot">
                        <span class="wechat-official-menu-phone-icon" aria-hidden="true">
                            <i></i><i></i><i></i>
                            <b></b><b></b><b></b>
                        </span>
                    </div>
                    @foreach ($wechatTopMenus as $topMenu)
                        <div class="wechat-official-menu-phone-tab-wrap">
                            @if ($wechatSelectedParentId === (int) $topMenu->id)
                                <div class="wechat-official-menu-phone-floating">
                                    @if ($wechatSelectedChildren instanceof \Illuminate\Support\Collection && $wechatSelectedChildren->isNotEmpty() || $wechatCanCreateSubMenu)
                                        <div class="wechat-official-menu-phone-popover">
                                            @foreach ($wechatSelectedChildren as $child)
                                                <a class="wechat-official-menu-phone-popover-item @if ((int) $wechatSelectedMenuId === (int) $child->id) is-active @endif" href="{{ route('admin.wechat-official.menus', ['menu' => $child->id]) }}">
                                                    {{ $child->name }}
                                                </a>
                                            @endforeach
                                            @if ($wechatCanCreateSubMenu)
                                                <a class="wechat-official-menu-phone-popover-item wechat-official-menu-phone-popover-item--add" href="{{ route('admin.wechat-official.menus', ['mode' => 'create', 'level' => 2, 'parent_id' => $wechatSelectedParentId]) }}">
                                                    + 添加
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <a class="wechat-official-menu-phone-tab @if ($wechatSelectedParentId === (int) $topMenu->id) is-active @endif" href="{{ route('admin.wechat-official.menus', ['menu' => $topMenu->id]) }}">
                                {{ $topMenu->name }}
                            </a>
                        </div>
                    @endforeach

                    @if ($wechatCanCreateTopMenu)
                        <div class="wechat-official-menu-phone-tab-wrap wechat-official-menu-phone-tab-wrap--placeholder">
                            <div class="wechat-official-menu-phone-floating">
                                <a class="wechat-official-menu-phone-add wechat-official-menu-phone-popover-item wechat-official-menu-phone-popover-item--add" href="{{ route('admin.wechat-official.menus', ['mode' => 'create', 'level' => 1]) }}">
                                    <span>+</span>
                                    <span>添加</span>
                                </a>
                            </div>
                            <span class="wechat-official-menu-phone-tab wechat-official-menu-phone-tab--placeholder" aria-hidden="true"></span>
                        </div>
                    @endif

                    @for ($wechatEmptyIndex = $wechatTopMenus->count() + ($wechatCanCreateTopMenu ? 1 : 0); $wechatEmptyIndex < 3; $wechatEmptyIndex++)
                        <div class="wechat-official-menu-phone-tab-wrap wechat-official-menu-phone-tab-wrap--placeholder">
                            <span class="wechat-official-menu-phone-tab wechat-official-menu-phone-tab--placeholder" aria-hidden="true"></span>
                        </div>
                    @endfor
                </div>
            </div>
        </article>

        <article class="wechat-official-panel wechat-official-menu-editor-card">
            <div class="wechat-official-panel-head wechat-official-menu-editor-head">
                <div>
                    <h2 class="wechat-official-panel-title">菜单信息</h2>
                    <div class="wechat-official-panel-desc">
                        @if ($wechatCreateMenuMode)
                            在这里新增菜单，保存后会立即出现在左侧菜单区。
                        @else
                            点击左侧菜单后，右侧直接修改并保存，完成后可同步到公众号。
                        @endif
                    </div>
                </div>
                <div class="wechat-official-inline-tags">
                    <span class="wechat-official-inline-tag {{ $wechatMenuSyncReady ? 'is-success wechat-official-inline-tag--wide' : 'is-warning' }}">
                        {{ $wechatMenuSyncReady ? '已具备同步条件' : '请先完善 AppID 与 AppSecret' }}
                    </span>
                </div>
            </div>

            @if ($wechatCreateMenuMode)
                <form method="POST" action="{{ route('admin.wechat-official.menus.store') }}" class="wechat-official-menu-editor-form wechat-official-menu-create-form" data-wechat-menu-form>
                    @csrf
                    <input type="hidden" name="mode" value="create">
                    <input type="hidden" name="sort" value="{{ old('sort', 0) }}">

                    <div class="wechat-official-menu-editor-summary">
                        <span class="wechat-official-menu-editor-summary-tag">{{ $wechatRequestedLevel === 2 ? '准备新建二级菜单' : '准备新建一级菜单' }}</span>
                        <div class="wechat-official-menu-editor-summary-text">
                            @if ($wechatRequestedLevel === 2 && $wechatRequestedParentMenu)
                                当前将添加到“{{ $wechatRequestedParentMenu->name }}”下，保存后会立即出现在左侧菜单区。
                            @elseif ($wechatRequestedLevel === 2)
                                当前准备新建二级菜单，请先选择所属一级菜单。
                            @else
                                当前将新增一级菜单，保存后会立即出现在左侧菜单区。
                            @endif
                        </div>
                    </div>

                    <div class="wechat-official-menu-form-rows">
                        <div class="wechat-official-menu-form-row">
                            <label class="wechat-official-menu-form-label">名称</label>
                            <div class="wechat-official-menu-form-control">
                                <input class="input" type="text" name="name" value="{{ old('name') }}">
                                <div class="wechat-official-menu-form-hint">建议控制在 4 个汉字或 8 个英文字符内。</div>
                                @error('name')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="wechat-official-menu-form-row">
                            <label class="wechat-official-menu-form-label">菜单层级</label>
                            <div class="wechat-official-menu-form-control">
                                <select class="input" name="level" data-wechat-menu-level>
                                    <option value="1" @selected($wechatRequestedLevel === 1)>一级菜单</option>
                                    <option value="2" @selected($wechatRequestedLevel === 2)>二级菜单</option>
                                </select>
                                @error('level')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="wechat-official-menu-form-row" data-wechat-menu-parent-wrap @if($wechatRequestedLevel !== 2) hidden @endif>
                            <label class="wechat-official-menu-form-label">所属菜单</label>
                            <div class="wechat-official-menu-form-control">
                                <select class="input" name="parent_id">
                                    <option value="">请选择一级菜单</option>
                                    @foreach ($wechatTopMenus as $topMenu)
                                        <option value="{{ $topMenu->id }}" @selected($wechatRequestedParentId === (int) $topMenu->id)>{{ $topMenu->name }}</option>
                                    @endforeach
                                </select>
                                @error('parent_id')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="wechat-official-menu-form-row">
                            <label class="wechat-official-menu-form-label">消息类型</label>
                            <div class="wechat-official-menu-form-control">
                                <div class="wechat-official-menu-type-options">
                                    @foreach ($wechatMenuTypeOptions as $typeKey => $typeLabel)
                                        <label class="wechat-official-menu-type-option">
                                            <input type="radio" name="type" value="{{ $typeKey }}" data-wechat-menu-type @checked(old('type', 'view') === $typeKey)>
                                            <span>{{ $typeLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('type')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="wechat-official-menu-form-row" data-wechat-menu-value-wrap="url">
                            <label class="wechat-official-menu-form-label">网页链接</label>
                            <div class="wechat-official-menu-form-control">
                                <input class="input" type="url" name="url" value="{{ old('url') }}" placeholder="https://example.com/path">
                                @error('url')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="wechat-official-menu-form-row" data-wechat-menu-value-wrap="key" @if(old('type', 'view') !== 'click') hidden @endif>
                            <label class="wechat-official-menu-form-label">事件 KEY</label>
                            <div class="wechat-official-menu-form-control">
                                <input class="input" type="text" name="key" value="{{ old('key') }}" placeholder="MENU_KEY_EXAMPLE">
                                @error('key')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="wechat-official-menu-form-row" data-wechat-menu-value-wrap="media_id" @if(old('type', 'view') !== 'media_id') hidden @endif>
                            <label class="wechat-official-menu-form-label">素材 MediaID</label>
                            <div class="wechat-official-menu-form-control">
                                <input class="input" type="text" name="media_id" value="{{ old('media_id') }}" placeholder="请输入微信素材 MediaID">
                                @error('media_id')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="wechat-official-menu-editor-footer">
                        <div></div>
                        <div class="wechat-official-menu-editor-actions">
                            <button class="button" type="submit">保存菜单</button>
                        </div>
                    </div>
                </form>
            @elseif ($wechatSelectedMenu)
                <form method="POST" action="{{ route('admin.wechat-official.menus.update', $wechatSelectedMenu->id) }}" class="wechat-official-menu-editor-form" data-wechat-menu-form>
                    @csrf
                    <input type="hidden" name="level" value="{{ $wechatSelectedMenuLevel }}">
                    <input type="hidden" name="sort" value="{{ old('sort', $wechatSelectedMenu->sort) }}">
                    @if ($wechatSelectedMenuLevel === 2)
                        <input type="hidden" name="parent_id" value="{{ (int) $wechatSelectedMenu->parent_id }}">
                    @endif

                    <div class="wechat-official-menu-editor-summary">
                        <span class="wechat-official-menu-editor-summary-tag">{{ $wechatSelectedMenuLevel === 2 ? '当前编辑二级菜单' : '当前编辑一级菜单' }}</span>
                        <div class="wechat-official-menu-editor-summary-text">
                            @if ($wechatSelectedMenuLevel === 2 && $wechatSelectedParent)
                                当前菜单挂在“{{ $wechatSelectedParent->name }}”下，修改后保存即可继续同步到公众号。
                            @else
                                当前菜单属于一级菜单，修改后保存即可继续同步到公众号。
                            @endif
                        </div>
                    </div>

                    <div class="wechat-official-menu-form-rows">
                        <div class="wechat-official-menu-form-row">
                            <label class="wechat-official-menu-form-label">名称</label>
                            <div class="wechat-official-menu-form-control">
                                <input class="input" type="text" name="name" value="{{ old('name', $wechatSelectedMenu->name) }}">
                                <div class="wechat-official-menu-form-hint">
                                    @if ($wechatSelectedMenuLevel === 2 && $wechatSelectedParent)
                                        当前挂在“{{ $wechatSelectedParent->name }}”下。
                                    @else
                                        当前为一级菜单。
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="wechat-official-menu-form-row">
                            <label class="wechat-official-menu-form-label">消息类型</label>
                            <div class="wechat-official-menu-form-control">
                                <div class="wechat-official-menu-type-options">
                                    @foreach ($wechatMenuTypeOptions as $typeKey => $typeLabel)
                                        <label class="wechat-official-menu-type-option">
                                            <input type="radio" name="type" value="{{ $typeKey }}" data-wechat-menu-type @checked(old('type', $wechatSelectedMenu->type) === $typeKey)>
                                            <span>{{ $typeLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="wechat-official-menu-form-row" data-wechat-menu-value-wrap="url" @if(old('type', $wechatSelectedMenu->type) !== 'view') hidden @endif>
                            <label class="wechat-official-menu-form-label">网页链接</label>
                            <div class="wechat-official-menu-form-control">
                                <input class="input" type="url" name="url" value="{{ old('url', $wechatSelectedMenu->url) }}">
                            </div>
                        </div>

                        <div class="wechat-official-menu-form-row" data-wechat-menu-value-wrap="key" @if(old('type', $wechatSelectedMenu->type) !== 'click') hidden @endif>
                            <label class="wechat-official-menu-form-label">事件 KEY</label>
                            <div class="wechat-official-menu-form-control">
                                <input class="input" type="text" name="key" value="{{ old('key', $wechatSelectedMenu->key) }}">
                            </div>
                        </div>

                        <div class="wechat-official-menu-form-row" data-wechat-menu-value-wrap="media_id" @if(old('type', $wechatSelectedMenu->type) !== 'media_id') hidden @endif>
                            <label class="wechat-official-menu-form-label">素材 MediaID</label>
                            <div class="wechat-official-menu-form-control">
                                <input class="input" type="text" name="media_id" value="{{ old('media_id', $wechatSelectedMenu->media_id) }}">
                            </div>
                        </div>
                    </div>

                    <div class="wechat-official-menu-editor-footer">
                        <div>
                            <button class="button secondary wechat-official-menu-delete-button" type="submit" form="wechat-official-menu-delete-form" data-confirm-submit data-confirm-text="{{ $wechatSelectedMenuLevel === 1 ? '确认删除该一级菜单吗？其下所有二级菜单也会一起删除。' : '确认删除这个二级菜单吗？' }}">删除菜单</button>
                        </div>
                        <div class="wechat-official-menu-editor-actions">
                            <a class="button button-secondary" href="{{ route('admin.wechat-official.menus', ['mode' => 'create']) }}">新增菜单</a>
                            <button class="button" type="submit">保存菜单</button>
                        </div>
                    </div>
                </form>
                <form id="wechat-official-menu-delete-form" method="POST" action="{{ route('admin.wechat-official.menus.destroy', $wechatSelectedMenu->id) }}" data-confirm-submit data-confirm-text="{{ $wechatSelectedMenuLevel === 1 ? '确认删除该一级菜单吗？其下所有二级菜单也会一起删除。' : '确认删除这个二级菜单吗？' }}">
                    @csrf
                </form>
            @else
                <div class="wechat-official-placeholder">
                    <h2>当前没有可编辑菜单</h2>
                    <p>先在左侧上方新增一个菜单，或者直接新建一级菜单后再回来编辑。</p>
                </div>
            @endif
        </article>
    </section>
@endsection
