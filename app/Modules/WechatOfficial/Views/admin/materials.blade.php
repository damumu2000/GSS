@extends('layouts.admin')

@section('title', '素材管理 - 微信公众号 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 微信公众号素材管理')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/wechat-official-admin.css') }}">
@endpush

@section('content')
    @php
        $wechatMaterialPageItems = collect($wechatMaterialAttachments->items());
        $wechatMaterialPageCount = (int) $wechatMaterialAttachments->total();
        $wechatMaterialPageSynced = $wechatMaterialPageItems->filter(fn ($item) => (int) ($item->material_record_id ?? 0) > 0)->count();
        $wechatMaterialPageUnsynced = $wechatMaterialPageItems->filter(fn ($item) => (int) ($item->material_record_id ?? 0) === 0)->count();
        $wechatMaterialStatCards = [
            ['label' => '当前页素材', 'value' => $wechatMaterialPageItems->count()],
            ['label' => '已同步', 'value' => $wechatMaterialPageSynced],
            ['label' => '未同步', 'value' => $wechatMaterialPageUnsynced],
            ['label' => '最近记录', 'value' => count($wechatRecentMaterials)],
        ];
    @endphp
    <section class="wechat-official-header">
        <div>
            <h1 class="wechat-official-title">素材管理</h1>
            <div class="wechat-official-desc">直接复用当前站点资源库中的图片附件，同步到公众号素材库。当前阶段只做图片素材，不新增独立上传入口。</div>
        </div>
        <div class="wechat-official-header-meta">
            <span class="wechat-official-meta-tag @if (! $wechatMaterialSyncReady) is-light @endif">{{ $wechatMaterialSyncReady ? '素材同步已就绪' : '请先完善公众号配置' }}</span>
            <span class="wechat-official-meta-tag is-light">共 {{ $wechatMaterialPageCount }} 项</span>
        </div>
    </section>

    @include('wechat_official::admin._nav')

    <section class="wechat-official-grid wechat-official-grid--compact">
        @foreach ($wechatMaterialStatCards as $stat)
            <article class="wechat-official-stat-card">
                <span class="wechat-official-stat-label">{{ $stat['label'] }}</span>
                <strong class="wechat-official-stat-value">{{ $stat['value'] }}</strong>
            </article>
        @endforeach
    </section>

    <section class="wechat-official-panel">
        <div class="wechat-official-panel-head">
            <div>
                <h2 class="wechat-official-panel-title">站点图片附件</h2>
                <div class="wechat-official-panel-desc">只展示当前站点图片附件。同步成功后会保存微信素材 MediaID 和微信返回地址。</div>
            </div>
        </div>

        <form class="wechat-official-article-filters" method="get" action="{{ route('admin.wechat-official.materials') }}">
            <div class="wechat-official-field">
                <label class="wechat-official-label" for="wechat-material-keyword">关键词</label>
                <input id="wechat-material-keyword" class="field" type="text" name="keyword" value="{{ $wechatMaterialKeyword }}">
            </div>
            <div class="wechat-official-field">
                <label class="wechat-official-label" for="wechat-material-sync-status">同步状态</label>
                <select id="wechat-material-sync-status" class="field" name="sync_status">
                    <option value="">全部状态</option>
                    <option value="not_synced" @selected($wechatMaterialSyncStatus === 'not_synced')>未同步</option>
                    <option value="synced" @selected($wechatMaterialSyncStatus === 'synced')>已同步</option>
                </select>
            </div>
            <div class="wechat-official-article-filter-actions">
                <button class="btn btn-primary" type="submit">筛选素材</button>
            </div>
        </form>

        <div class="wechat-official-material-grid">
            @forelse ($wechatMaterialAttachments as $attachment)
                <article class="wechat-official-material-card">
                    <div class="wechat-official-material-preview">
                        <img src="{{ $attachment->url }}" alt="{{ $attachment->origin_name }}">
                    </div>

                    <div class="wechat-official-material-body">
                        <div class="wechat-official-material-head">
                            <h3 class="wechat-official-material-title" title="{{ $attachment->origin_name }}">{{ $attachment->origin_name }}</h3>
                            <span class="wechat-official-chip @if ($attachment->material_record_id) is-success @endif">{{ $attachment->material_record_id ? '已同步' : '未同步' }}</span>
                        </div>
                        <div class="wechat-official-article-meta">
                            <span>{{ strtoupper((string) $attachment->extension) }}</span>
                            @if ($attachment->width && $attachment->height)
                                <span>{{ $attachment->width }}×{{ $attachment->height }}</span>
                            @endif
                            <span>{{ number_format(((int) $attachment->size) / 1024, 1) }} KB</span>
                        </div>

                        @if (trim((string) ($attachment->wechat_media_id ?? '')) !== '')
                            <div class="wechat-official-material-info">
                                <div><strong>MediaID：</strong>{{ $attachment->wechat_media_id }}</div>
                                @if (trim((string) ($attachment->wechat_url ?? '')) !== '')
                                    <div><strong>微信地址：</strong><a href="{{ $attachment->wechat_url }}" target="_blank" rel="noreferrer">查看素材</a></div>
                                @endif
                            </div>
                        @else
                            <div class="wechat-official-material-info is-muted">
                                <div><strong>同步提示：</strong>该图片还未同步到微信公众号素材库。</div>
                            </div>
                        @endif

                        <form method="post" action="{{ route('admin.wechat-official.materials.sync', ['attachment' => $attachment->id]) }}">
                            @csrf
                            <button class="btn btn-primary wechat-official-material-action" type="submit" @disabled(! $wechatMaterialSyncReady)>同步图片素材</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="wechat-official-placeholder">
                    <h2>暂无可同步图片</h2>
                    <p>当前站点资源库里还没有图片附件，可以先上传图片后再到这里同步公众号素材。</p>
                </div>
            @endforelse
        </div>

        {{ $wechatMaterialAttachments->links() }}
    </section>

    <section class="wechat-official-panel">
        <div class="wechat-official-panel-head">
            <div>
                <h2 class="wechat-official-panel-title">最近素材记录</h2>
                <div class="wechat-official-panel-desc">最近同步的公众号图片素材记录，方便快速核对 MediaID。</div>
            </div>
        </div>

        <div class="wechat-official-article-log-list">
            @forelse ($wechatRecentMaterials as $material)
                <div class="wechat-official-article-log-item">
                    <div>
                        <strong>{{ $material->title ?: '未命名图片' }}</strong>
                        <div class="wechat-official-article-log-meta">
                            <span>{{ $material->wechat_media_id ?: '未返回 MediaID' }}</span>
                            <span>{{ $material->synced_at ? \Illuminate\Support\Carbon::parse($material->synced_at)->format('Y-m-d H:i') : '未同步' }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="wechat-official-placeholder">
                    <h2>还没有素材同步记录</h2>
                    <p>图片素材同步成功后，这里会自动显示最近记录。</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection
