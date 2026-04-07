<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $pageScope = trim((string) $request->query('page_scope', ''));
        $displayMode = trim((string) $request->query('display_mode', ''));
        $status = trim((string) $request->query('status', ''));

        $positions = DB::table('promo_positions')
            ->leftJoin('channels', 'channels.id', '=', 'promo_positions.channel_id')
            ->leftJoin('promo_items', 'promo_items.position_id', '=', 'promo_positions.id')
            ->where('promo_positions.site_id', $currentSite->id)
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery->where('promo_positions.name', 'like', '%'.$keyword.'%')
                        ->orWhere('promo_positions.code', 'like', '%'.$keyword.'%');
                });
            })
            ->when($pageScope !== '', fn ($query) => $query->where('promo_positions.page_scope', $pageScope))
            ->when($displayMode !== '', fn ($query) => $query->where('promo_positions.display_mode', $displayMode))
            ->when($status !== '', fn ($query) => $query->where('promo_positions.status', (int) $status))
            ->groupBy(
                'promo_positions.id',
                'promo_positions.site_id',
                'promo_positions.channel_id',
                'promo_positions.code',
                'promo_positions.name',
                'promo_positions.page_scope',
                'promo_positions.display_mode',
                'promo_positions.template_name',
                'promo_positions.allow_multiple',
                'promo_positions.max_items',
                'promo_positions.status',
                'promo_positions.remark',
                'promo_positions.created_at',
                'promo_positions.updated_at',
                'channels.name'
            )
            ->orderByDesc('promo_positions.id')
            ->paginate(9, [
                'promo_positions.*',
                'channels.name as channel_name',
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
                'attachments.origin_name as attachment_name',
            ])
            ->groupBy('position_id');

        $positions->getCollection()->transform(function ($position) use ($previewItems) {
            $items = $previewItems->get($position->id) ?? collect();
            $effectiveItems = $items->filter(fn ($item) => $this->isPromoItemEffectivelyActive($item))->values();
            $firstEffectiveItem = $effectiveItems->first();
            $firstItem = $items->first();

            $position->enabled_item_count = $effectiveItems->count();
            $position->preview_image_url = $firstEffectiveItem->attachment_url ?? null;
            $position->preview_title = $firstEffectiveItem ? ($firstEffectiveItem->title ?: ($firstEffectiveItem->attachment_name ?? null)) : null;
            $position->preview_subtitle = $firstEffectiveItem->subtitle ?? null;
            $position->preview_link_url = $firstEffectiveItem->link_url ?? null;
            $position->preview_status = isset($firstEffectiveItem->status) ? (int) $firstEffectiveItem->status : null;
            $position->preview_items = $effectiveItems
                ->take(6)
                ->map(fn ($item) => [
                    'image_url' => $item->attachment_url,
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
            'selectedPageScope' => $pageScope,
            'selectedDisplayMode' => $displayMode,
            'selectedStatus' => $status,
            'pageScopes' => config('cms.promo_page_scopes'),
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
            'channels' => $this->channelOptions($currentSite->id),
            'pageScopes' => config('cms.promo_page_scopes'),
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
            'channels' => $this->channelOptions($currentSite->id),
            'pageScopes' => config('cms.promo_page_scopes'),
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
        $validated['code'] = (string) $positionRecord->code;
        $this->ensurePositionItemLimit($positionRecord->id, (int) $validated['max_items']);

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

        return redirect()
            ->route('admin.promos.index', $this->promoIndexQuery($request))
            ->with('status', $nextStatus === 1 ? '图宣位已启用。' : '图宣位已停用。');
    }

    protected function promoIndexQuery(Request $request): array
    {
        return collect([
            'keyword' => $request->query('keyword', $request->input('keyword')),
            'page_scope' => $request->query('page_scope', $request->input('page_scope')),
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
        $pageScopes = array_keys(config('cms.promo_page_scopes', []));
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'page_scope' => ['required', Rule::in($pageScopes)],
            'display_mode' => ['required', Rule::in($displayModes)],
            'channel_id' => [
                'nullable',
                'integer',
                Rule::exists('channels', 'id')->where(fn ($query) => $query->where('site_id', $siteId)),
            ],
            'template_name' => ['nullable', 'string', 'max:50'],
            'max_items' => ['required', 'integer', 'min:1', 'max:20'],
            'status' => ['required', Rule::in(['0', '1', 0, 1])],
            'remark' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'name' => '图宣位名称',
            'page_scope' => '页面范围',
            'display_mode' => '展示模式',
            'channel_id' => '所属栏目',
            'template_name' => '模板名称',
            'max_items' => '最大图宣数',
            'status' => '状态',
            'remark' => '备注',
        ]);

        $validated = $validator->validate();
        $validated['code'] = $positionId === null
            ? $this->generateUniquePositionCode(
                $siteId,
                (string) $validated['page_scope'],
                (string) $validated['display_mode'],
                (string) $validated['name'],
                null
            )
            : null;

        $validated['max_items'] = match ($validated['display_mode']) {
            'single' => 1,
            'floating' => min((int) $validated['max_items'], 2),
            'carousel' => min((int) $validated['max_items'], 10),
            default => (int) $validated['max_items'],
        };

        $validated['allow_multiple'] = $validated['max_items'] > 1;

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
            'max_items' => "当前图宣位下已有 {$itemCount} 条图宣内容，不能将最大图宣数保存为 {$maxItems}。请先删除多余图宣内容后再保存。",
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
            'channel_id' => $validated['channel_id'] ?: null,
            'code' => trim((string) $validated['code']),
            'name' => trim((string) $validated['name']),
            'page_scope' => (string) $validated['page_scope'],
            'display_mode' => (string) $validated['display_mode'],
            'template_name' => $validated['template_name'] !== null && trim((string) $validated['template_name']) !== ''
                ? trim((string) $validated['template_name'])
                : null,
            'scope_hash' => $this->buildScopeHash(
                (string) $validated['page_scope'],
                $validated['channel_id'] ?: null,
                $validated['template_name'] !== null && trim((string) $validated['template_name']) !== ''
                    ? trim((string) $validated['template_name'])
                    : null
            ),
            'allow_multiple' => (bool) $validated['allow_multiple'],
            'max_items' => (int) $validated['max_items'],
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
            'page_scope' => 'global',
            'display_mode' => 'single',
            'channel_id' => null,
            'template_name' => '',
            'max_items' => 1,
            'status' => 1,
            'remark' => '',
        ];
    }

    protected function buildScopeHash(string $pageScope, ?int $channelId = null, ?string $templateName = null): string
    {
        return sha1(sprintf(
            '%s|%s|%s',
            trim($pageScope) !== '' ? trim($pageScope) : 'global',
            $channelId !== null ? (string) $channelId : 'site',
            $templateName !== null && trim($templateName) !== '' ? trim($templateName) : 'default'
        ));
    }

    protected function generatePositionCode(string $pageScope, string $displayMode): string
    {
        $scope = trim($pageScope) !== '' ? trim($pageScope) : 'global';
        $mode = trim($displayMode) !== '' ? trim($displayMode) : 'single';

        return match ($mode) {
            'carousel' => $scope.'.carousel',
            'floating' => $scope.'.floating',
            default => $scope.'.hero',
        };
    }

    protected function generateUniquePositionCode(
        int $siteId,
        string $pageScope,
        string $displayMode,
        string $name,
        ?int $positionId = null
    ): string {
        $baseCode = $this->generatePositionCode($pageScope, $displayMode);
        $nameToken = $this->buildPositionNameToken($name);
        $candidate = $nameToken !== '' ? $baseCode.'.'.$nameToken : $baseCode.'.slot';

        if (! $this->positionCodeExists($siteId, $candidate, $positionId)) {
            return $candidate;
        }

        $suffix = 2;

        while ($this->positionCodeExists($siteId, $candidate.'_'.$suffix, $positionId)) {
            $suffix++;
        }

        return $candidate.'_'.$suffix;
    }

    protected function buildPositionNameToken(string $name): string
    {
        $trimmedName = trim($name);

        if ($trimmedName === '') {
            return 'slot';
        }

        $asciiSlug = Str::slug($trimmedName, '_');

        if ($asciiSlug !== '') {
            return Str::limit($asciiSlug, 24, '');
        }

        return 'slot_'.substr(sha1($trimmedName), 0, 8);
    }

    protected function positionCodeExists(int $siteId, string $code, ?int $positionId = null): bool
    {
        return DB::table('promo_positions')
            ->where('site_id', $siteId)
            ->where('code', $code)
            ->when($positionId !== null, fn ($query) => $query->where('id', '!=', $positionId))
            ->exists();
    }

    protected function findPosition(int $siteId, int $positionId): ?object
    {
        return DB::table('promo_positions')
            ->where('site_id', $siteId)
            ->where('id', $positionId)
            ->first();
    }

    protected function channelOptions(int $siteId)
    {
        $channels = DB::table('channels')
            ->where('site_id', $siteId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'name', 'parent_id']);

        $childrenByParent = $channels->groupBy(fn (object $channel): int => (int) ($channel->parent_id ?? 0));

        $walk = function (int $parentId, int $depth = 0, array $ancestorLines = []) use (&$walk, $childrenByParent): array {
            $items = $childrenByParent->get($parentId, collect())->values();
            $flattened = [];

            foreach ($items as $index => $channel) {
                $isLast = $index === $items->count() - 1;
                $channel->tree_depth = $depth;
                $channel->tree_is_last = $isLast;
                $channel->tree_ancestors = $ancestorLines;
                $channel->tree_has_children = $childrenByParent->has((int) $channel->id);
                $flattened[] = $channel;

                $nextAncestorLines = $ancestorLines;
                $nextAncestorLines[] = ! $isLast;

                foreach ($walk((int) $channel->id, $depth + 1, $nextAncestorLines) as $child) {
                    $flattened[] = $child;
                }
            }

            return $flattened;
        };

        return collect($walk(0));
    }
}
