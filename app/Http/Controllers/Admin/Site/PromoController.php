<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Support\Site as SitePath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PromoController extends Controller
{
    /**
     * Display a listing of promo positions for the current site.
     */
    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'promo.manage');
        $promoIndexQuery = $this->promoIndexQuery($request);

        $keyword = trim((string) $request->query('keyword', ''));
        $displayMode = trim((string) $request->query('display_mode', ''));
        $status = trim((string) $request->query('status', ''));

        $positions = DB::table('promo_positions')
            ->leftJoin('promo_items', 'promo_items.position_id', '=', 'promo_positions.id')
            ->where('promo_positions.site_id', $currentSite->id)
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery->where('promo_positions.name', 'like', '%'.$keyword.'%')
                        ->orWhere('promo_positions.code', 'like', '%'.$keyword.'%');
                });
            })
            ->when($displayMode !== '', fn ($query) => $query->where('promo_positions.display_mode', $displayMode))
            ->when($status !== '', fn ($query) => $query->where('promo_positions.status', (int) $status))
            ->groupBy(
                'promo_positions.id',
                'promo_positions.site_id',
                'promo_positions.code',
                'promo_positions.name',
                'promo_positions.display_mode',
                'promo_positions.status',
                'promo_positions.remark',
                'promo_positions.created_at',
                'promo_positions.updated_at'
            )
            ->orderByDesc('promo_positions.id')
            ->paginate(9, [
                'promo_positions.*',
                DB::raw('COUNT(promo_items.id) as item_count'),
            ])
            ->withQueryString();

        $positionIds = $positions->getCollection()->pluck('id');

        $previewItems = DB::table('promo_items')
            ->join('attachments', 'attachments.id', '=', 'promo_items.attachment_id')
            ->where('promo_items.site_id', $currentSite->id)
            ->whereIn('promo_items.position_id', $positionIds)
            ->orderBy('promo_items.position_id')
            ->orderBy('promo_items.sort')
            ->orderByDesc('promo_items.id')
            ->get([
                'promo_items.position_id',
                'promo_items.title',
                'promo_items.subtitle',
                'promo_items.link_url',
                'promo_items.status',
                'promo_items.start_at',
                'promo_items.end_at',
                'attachments.url as attachment_url',
                'attachments.path as attachment_path',
                'attachments.created_at as attachment_created_at',
                'attachments.updated_at as attachment_updated_at',
                'attachments.origin_name as attachment_name',
            ])
            ->groupBy('position_id');

        $positions->getCollection()->transform(function ($position) use ($previewItems) {
            $items = $previewItems->get($position->id) ?? collect();
            $effectiveItems = $items->filter(fn ($item) => $this->isPromoItemEffectivelyActive($item))->values();
            $firstEffectiveItem = $effectiveItems->first();
            $firstItem = $items->first();

            $position->enabled_item_count = $effectiveItems->count();
            $position->preview_image_url = $firstEffectiveItem ? $this->promoAttachmentDisplayUrl($firstEffectiveItem) : null;
            $position->preview_title = $firstEffectiveItem ? ($firstEffectiveItem->title ?: ($firstEffectiveItem->attachment_name ?? null)) : null;
            $position->preview_subtitle = $firstEffectiveItem->subtitle ?? null;
            $position->preview_link_url = $firstEffectiveItem->link_url ?? null;
            $position->preview_status = isset($firstEffectiveItem->status) ? (int) $firstEffectiveItem->status : null;
            $position->preview_items = $effectiveItems
                ->take(6)
                ->map(fn ($item) => [
                    'image_url' => $this->promoAttachmentDisplayUrl($item),
                    'title' => $item->title ?: ($item->attachment_name ?? ''),
                ])
                ->values();
            $position->content_state_label = $firstEffectiveItem
                ? '已生效'
                : ($firstItem ? '待生效' : '待配置');
            $position->content_summary = $firstEffectiveItem
                ? ($position->preview_title ?: '图宣内容已配置')
                : ($firstItem ? '已配置图宣内容，但当前没有处于生效时间内的内容。' : '还没有挂载图宣内容，可先添加图片内容。');

            return $position;
        });

        return view('admin.site.promos.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'positions' => $positions,
            'keyword' => $keyword,
            'selectedDisplayMode' => $displayMode,
            'selectedStatus' => $status,
            'displayModes' => config('cms.promo_display_modes'),
            'promoIndexQuery' => $promoIndexQuery,
        ]);
    }

    /**
     * Display the create form for a promo position.
     */
    public function create(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'promo.manage');

        return view('admin.site.promos.edit', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'position' => $this->emptyPosition(),
            'displayModes' => config('cms.promo_display_modes'),
            'isCreate' => true,
            'promoIndexQuery' => $this->promoIndexQuery($request),
        ]);
    }

    /**
     * Store a newly created promo position.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'promo.manage');

        $validated = $this->validatePosition($request, $currentSite->id);
        $positionId = DB::table('promo_positions')->insertGetId($this->payloadFromValidated($validated, $currentSite->id));

        $this->logOperation(
            'site',
            'promo',
            'create',
            $currentSite->id,
            $request->user()->id,
            'promo_position',
            $positionId,
            ['name' => $validated['name'], 'code' => $validated['code']],
            $request,
        );

        $this->flushFrontendPageCache($currentSite);

        return redirect()
            ->route('admin.promos.index', $this->promoIndexQuery($request))
            ->with('status', '图宣位已创建。');
    }

    /**
     * Display the edit form for a promo position.
     */
    public function edit(Request $request, int $position): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'promo.manage');

        $positionRecord = $this->findPosition($currentSite->id, $position);
        abort_unless($positionRecord, 404);

        return view('admin.site.promos.edit', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'position' => $positionRecord,
            'displayModes' => config('cms.promo_display_modes'),
            'isCreate' => false,
            'promoIndexQuery' => $this->promoIndexQuery($request),
        ]);
    }

    /**
     * Update the specified promo position.
     */
    public function update(Request $request, int $position): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'promo.manage');

        $positionRecord = $this->findPosition($currentSite->id, $position);
        abort_unless($positionRecord, 404);

        $validated = $this->validatePosition($request, $currentSite->id, $positionRecord->id);
        $this->ensurePositionItemLimit($positionRecord->id, $this->maxItemsForMode((string) $validated['display_mode']));

        DB::table('promo_positions')
            ->where('id', $positionRecord->id)
            ->update($this->payloadFromValidated($validated, $currentSite->id, false));

        $this->logOperation(
            'site',
            'promo',
            'update',
            $currentSite->id,
            $request->user()->id,
            'promo_position',
            $positionRecord->id,
            ['name' => $validated['name'], 'code' => $validated['code']],
            $request,
        );

        $this->flushFrontendPageCache($currentSite);

        return redirect()
            ->route('admin.promos.index', $this->promoIndexQuery($request))
            ->with('status', '图宣位已更新。');
    }

    /**
     * Delete the specified promo position when it has no items.
     */
    public function destroy(Request $request, int $position): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'promo.manage');

        $positionRecord = $this->findPosition($currentSite->id, $position);
        abort_unless($positionRecord, 404);

        $itemCount = (int) DB::table('promo_items')
            ->where('position_id', $positionRecord->id)
            ->count();

        if ($itemCount > 0) {
            return redirect()
                ->route('admin.promos.index', $this->promoIndexQuery($request))
                ->withErrors(['promo' => '该图宣位下仍有图宣内容，请先清空图宣内容后再删除。']);
        }

        DB::table('promo_positions')->where('id', $positionRecord->id)->delete();

        $this->logOperation(
            'site',
            'promo',
            'delete',
            $currentSite->id,
            $request->user()->id,
            'promo_position',
            $positionRecord->id,
            ['name' => $positionRecord->name, 'code' => $positionRecord->code],
            $request,
        );

        $this->flushFrontendPageCache($currentSite);

        return redirect()
            ->route('admin.promos.index', $this->promoIndexQuery($request))
            ->with('status', '图宣位已删除。');
    }

    /**
     * Toggle the active status for the specified promo position.
     */
    public function toggle(Request $request, int $position): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'promo.manage');

        $positionRecord = $this->findPosition($currentSite->id, $position);
        abort_unless($positionRecord, 404);

        $nextStatus = (int) $positionRecord->status === 1 ? 0 : 1;

        DB::table('promo_positions')
            ->where('id', $positionRecord->id)
            ->update([
                'status' => $nextStatus,
                'updated_at' => now(),
            ]);

        $this->logOperation(
            'site',
            'promo',
            $nextStatus === 1 ? 'enable' : 'disable',
            $currentSite->id,
            $request->user()->id,
            'promo_position',
            $positionRecord->id,
            ['name' => $positionRecord->name, 'code' => $positionRecord->code, 'status' => $nextStatus],
            $request,
        );

        $this->flushFrontendPageCache($currentSite);

        return redirect()
            ->route('admin.promos.index', $this->promoIndexQuery($request))
            ->with('status', $nextStatus === 1 ? '图宣位已启用。' : '图宣位已停用。');
    }

    protected function promoIndexQuery(Request $request): array
    {
        return collect([
            'keyword' => $request->query('keyword', $request->input('keyword')),
            'display_mode' => $request->query('display_mode'),
            'status' => $request->query('status', $request->input('status')),
            'page' => $request->query('page', $request->input('page')),
        ])
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();
    }

    protected function validatePosition(Request $request, int $siteId, ?int $positionId = null): array
    {
        $displayModes = array_keys(config('cms.promo_display_modes', []));
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'code' => [
                'required',
                'string',
                'min:2',
                'max:80',
                'regex:/^[a-z][a-z0-9_\\-]*$/',
                Rule::unique('promo_positions', 'code')
                    ->where(fn ($query) => $query->where('site_id', $siteId))
                    ->ignore($positionId),
            ],
            'display_mode' => ['required', Rule::in($displayModes)],
            'status' => ['required', Rule::in(['0', '1', 0, 1])],
            'remark' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'name' => '图宣位名称',
            'code' => '图宣位编码',
            'display_mode' => '展示模式',
            'status' => '状态',
            'remark' => '备注',
        ]);

        $validated = $validator->validate();
        $validated['code'] = trim((string) $validated['code']);

        return $validated;
    }

    protected function ensurePositionItemLimit(int $positionId, int $maxItems): void
    {
        $itemCount = (int) DB::table('promo_items')
            ->where('position_id', $positionId)
            ->count();

        if ($itemCount <= $maxItems) {
            return;
        }

        throw ValidationException::withMessages([
            'display_mode' => "当前图宣位下已有 {$itemCount} 条图宣内容，不能切换为最多 {$maxItems} 条的类型。请先删除多余图宣内容后再保存。",
        ]);
    }

    protected function isPromoItemEffectivelyActive(object $item): bool
    {
        if ((int) ($item->status ?? 0) !== 1) {
            return false;
        }

        $now = now();
        $startAt = !empty($item->start_at) ? Carbon::parse((string) $item->start_at) : null;
        $endAt = !empty($item->end_at) ? Carbon::parse((string) $item->end_at) : null;

        if ($startAt && $startAt->isFuture()) {
            return false;
        }

        if ($endAt && $endAt->isPast()) {
            return false;
        }

        return true;
    }

    protected function payloadFromValidated(array $validated, int $siteId, bool $withCreatedAt = true): array
    {
        $payload = [
            'site_id' => $siteId,
            'code' => trim((string) $validated['code']),
            'name' => trim((string) $validated['name']),
            'display_mode' => (string) $validated['display_mode'],
            'status' => (int) $validated['status'],
            'remark' => $validated['remark'] !== null && trim((string) $validated['remark']) !== ''
                ? trim((string) $validated['remark'])
                : null,
            'updated_at' => now(),
        ];

        if ($withCreatedAt) {
            $payload['created_at'] = now();
        }

        return $payload;
    }

    protected function emptyPosition(): object
    {
        return (object) [
            'id' => null,
            'name' => '',
            'code' => '',
            'display_mode' => 'single',
            'status' => 1,
            'remark' => '',
        ];
    }

    protected function findPosition(int $siteId, int $positionId): ?object
    {
        return DB::table('promo_positions')
            ->where('site_id', $siteId)
            ->where('id', $positionId)
            ->first();
    }

    protected function promoAttachmentDisplayUrl(object $item): string
    {
        $path = trim((string) ($item->attachment_path ?? ''));
        $url = $path !== ''
            ? SitePath::urlForStoredPath($path)
            : trim((string) ($item->attachment_url ?? ''));

        $cacheVersion = null;
        if (! empty($item->attachment_updated_at)) {
            $cacheVersion = Carbon::parse((string) $item->attachment_updated_at)->timestamp;
        } elseif (! empty($item->attachment_created_at)) {
            $cacheVersion = Carbon::parse((string) $item->attachment_created_at)->timestamp;
        }

        if ($url === '' || $cacheVersion === null || $cacheVersion <= 0) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'v='.$cacheVersion;
    }

    protected function maxItemsForMode(string $displayMode): int
    {
        return $displayMode === 'multi' ? 20 : 1;
    }
}
