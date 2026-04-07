<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Support\PromoAttachmentRelationSync;
use App\Support\PromoItemExpiryManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PromoItemController extends Controller
{
    /**
     * Display a listing of promo items for the given position.
     */
    public function index(Request $request, int $position): View
    {
        [$currentSite, $positionRecord] = $this->resolvePositionContext($request, $position);
        (new PromoItemExpiryManager())->deactivateExpiredItems($currentSite->id);

        $items = DB::table('promo_items')
            ->join('attachments', 'attachments.id', '=', 'promo_items.attachment_id')
            ->where('promo_items.position_id', $positionRecord->id)
            ->orderBy('promo_items.sort')
            ->orderByDesc('promo_items.id')
            ->get([
                'promo_items.*',
                'attachments.origin_name as attachment_name',
                'attachments.url as attachment_url',
                'attachments.extension as attachment_extension',
            ])
            ->map(fn ($item) => $this->decorateItemState($item));

        return view('admin.site.promos.items.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'position' => $positionRecord,
            'items' => $items,
            'itemSheets' => $items->mapWithKeys(fn ($item) => [$item->id => $this->serializeItem($item)])->all(),
            'displayModes' => config('cms.promo_display_modes'),
            'attachmentLibraryWorkspaceAccess' => $this->canAccessAttachmentWorkspace((int) $request->user()->id, (int) $currentSite->id),
            'promoIndexQuery' => $this->promoIndexQuery($request),
        ]);
    }

    /**
     * Store a newly created promo item.
     */
    public function store(Request $request, int $position): RedirectResponse
    {
        [$currentSite, $positionRecord] = $this->resolvePositionContext($request, $position);

        $existingItemCount = (int) DB::table('promo_items')
            ->where('position_id', $positionRecord->id)
            ->count();

        if ($existingItemCount >= (int) $positionRecord->max_items) {
            return redirect()
                ->route('admin.promos.items.index', ['position' => $positionRecord->id] + $this->promoIndexQuery($request))
                ->withErrors(['promo_item' => '当前图宣位已达到最大图宣数量限制，请先删除或停用其他图宣内容。']);
        }

        $validated = $this->validateItem($request, $currentSite->id, $positionRecord);
        $validated['sort'] = $this->nextSortValue($positionRecord->id);

        $itemId = DB::table('promo_items')->insertGetId($this->itemPayload($validated, $currentSite->id, $positionRecord->id));

        (new PromoAttachmentRelationSync())->syncForPromoItem($currentSite->id, $itemId);

        $this->logOperation(
            'site',
            'promo',
            'create_item',
            $currentSite->id,
            $request->user()->id,
            'promo_item',
            $itemId,
            ['position' => $positionRecord->code, 'attachment_id' => (int) $validated['attachment_id']],
            $request,
        );

        return redirect()
            ->route('admin.promos.items.index', ['position' => $positionRecord->id] + $this->promoIndexQuery($request))
            ->with('status', '图宣内容已创建。');
    }

    public function quickStore(Request $request, int $position): JsonResponse
    {
        [$currentSite, $positionRecord] = $this->resolvePositionContext($request, $position);

        $existingItemCount = (int) DB::table('promo_items')
            ->where('position_id', $positionRecord->id)
            ->count();

        if ($existingItemCount >= (int) $positionRecord->max_items) {
            return response()->json([
                'message' => '当前图宣位已达到最大图宣数量限制，请先删除或停用其他图宣内容。',
            ], 422);
        }

        $validated = $this->validateItem($request, $currentSite->id, $positionRecord);
        $validated['sort'] = $this->nextSortValue($positionRecord->id);

        $itemId = DB::table('promo_items')->insertGetId($this->itemPayload($validated, $currentSite->id, $positionRecord->id));
        (new PromoAttachmentRelationSync())->syncForPromoItem($currentSite->id, $itemId);

        $itemRecord = $this->findItem($currentSite->id, $positionRecord->id, $itemId);

        $this->logOperation(
            'site',
            'promo',
            'create_item',
            $currentSite->id,
            $request->user()->id,
            'promo_item',
            $itemId,
            ['position' => $positionRecord->code, 'attachment_id' => (int) $validated['attachment_id']],
            $request,
        );

        return response()->json([
            'message' => '图宣内容已创建。',
            'item' => $this->serializeItem($itemRecord),
        ]);
    }

    /**
     * Update the specified promo item.
     */
    public function update(Request $request, int $position, int $item): RedirectResponse
    {
        [$currentSite, $positionRecord] = $this->resolvePositionContext($request, $position);

        $itemRecord = $this->findItem($currentSite->id, $positionRecord->id, $item);
        abort_unless($itemRecord, 404);

        $validated = $this->validateItem($request, $currentSite->id, $positionRecord);
        $validated['sort'] = (int) $itemRecord->sort;

        DB::table('promo_items')
            ->where('id', $itemRecord->id)
            ->update($this->itemPayload($validated, $currentSite->id, $positionRecord->id, false));

        (new PromoAttachmentRelationSync())->syncForPromoItem($currentSite->id, $itemRecord->id);

        $this->logOperation(
            'site',
            'promo',
            'update_item',
            $currentSite->id,
            $request->user()->id,
            'promo_item',
            $itemRecord->id,
            ['position' => $positionRecord->code, 'attachment_id' => (int) $validated['attachment_id']],
            $request,
        );

        return redirect()
            ->route('admin.promos.items.index', ['position' => $positionRecord->id] + $this->promoIndexQuery($request))
            ->with('status', '图宣内容已更新。');
    }

    public function quickUpdate(Request $request, int $position, int $item): JsonResponse
    {
        [$currentSite, $positionRecord] = $this->resolvePositionContext($request, $position);

        $itemRecord = $this->findItem($currentSite->id, $positionRecord->id, $item);
        abort_unless($itemRecord, 404);

        $validated = $this->validateItem($request, $currentSite->id, $positionRecord);
        $validated['sort'] = (int) $itemRecord->sort;

        DB::table('promo_items')
            ->where('id', $itemRecord->id)
            ->update($this->itemPayload($validated, $currentSite->id, $positionRecord->id, false));

        (new PromoAttachmentRelationSync())->syncForPromoItem($currentSite->id, $itemRecord->id);
        $updatedRecord = $this->findItem($currentSite->id, $positionRecord->id, $itemRecord->id);

        $this->logOperation(
            'site',
            'promo',
            'update_item',
            $currentSite->id,
            $request->user()->id,
            'promo_item',
            $itemRecord->id,
            ['position' => $positionRecord->code, 'attachment_id' => (int) $validated['attachment_id']],
            $request,
        );

        return response()->json([
            'message' => '图宣内容已更新。',
            'item' => $this->serializeItem($updatedRecord),
        ]);
    }

    public function replaceImage(Request $request, int $position, int $item): JsonResponse
    {
        [$currentSite, $positionRecord] = $this->resolvePositionContext($request, $position);

        $itemRecord = $this->findItem($currentSite->id, $positionRecord->id, $item);
        abort_unless($itemRecord, 404);

        $validated = $request->validate([
            'attachment_id' => [
                'required',
                'integer',
                Rule::exists('attachments', 'id')->where(function ($query) use ($currentSite): void {
                    $query->where('site_id', $currentSite->id)
                        ->whereIn('extension', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                }),
            ],
        ], [], [
            'attachment_id' => '图宣图片',
        ]);

        DB::table('promo_items')
            ->where('id', $itemRecord->id)
            ->update([
                'attachment_id' => (int) $validated['attachment_id'],
                'updated_at' => now(),
            ]);

        (new PromoAttachmentRelationSync())->syncForPromoItem($currentSite->id, $itemRecord->id);
        $updatedRecord = $this->findItem($currentSite->id, $positionRecord->id, $itemRecord->id);

        $this->logOperation(
            'site',
            'promo',
            'replace_item_image',
            $currentSite->id,
            $request->user()->id,
            'promo_item',
            $itemRecord->id,
            ['position' => $positionRecord->code, 'attachment_id' => (int) $validated['attachment_id']],
            $request,
        );

        return response()->json([
            'message' => '图宣图片已更新。',
            'item' => $this->serializeItem($updatedRecord),
        ]);
    }

    /**
     * Delete the specified promo item.
     */
    public function destroy(Request $request, int $position, int $item): RedirectResponse
    {
        [$currentSite, $positionRecord] = $this->resolvePositionContext($request, $position);

        $itemRecord = $this->findItem($currentSite->id, $positionRecord->id, $item);
        abort_unless($itemRecord, 404);

        (new PromoAttachmentRelationSync())->clearForPromoItem($currentSite->id, $itemRecord->id);
        DB::table('promo_items')->where('id', $itemRecord->id)->delete();

        $this->logOperation(
            'site',
            'promo',
            'delete_item',
            $currentSite->id,
            $request->user()->id,
            'promo_item',
            $itemRecord->id,
            ['position' => $positionRecord->code, 'attachment_id' => (int) $itemRecord->attachment_id],
            $request,
        );

        return redirect()
            ->route('admin.promos.items.index', ['position' => $positionRecord->id] + $this->promoIndexQuery($request))
            ->with('status', '图宣内容已删除。');
    }

    /**
     * Toggle the active status for the specified promo item.
     */
    public function toggle(Request $request, int $position, int $item): RedirectResponse
    {
        [$currentSite, $positionRecord] = $this->resolvePositionContext($request, $position);

        $itemRecord = $this->findItem($currentSite->id, $positionRecord->id, $item);
        abort_unless($itemRecord, 404);

        $nextStatus = (int) $itemRecord->status === 1 ? 0 : 1;

        DB::table('promo_items')
            ->where('id', $itemRecord->id)
            ->update([
                'status' => $nextStatus,
                'updated_at' => now(),
            ]);

        $this->logOperation(
            'site',
            'promo',
            $nextStatus === 1 ? 'enable_item' : 'disable_item',
            $currentSite->id,
            $request->user()->id,
            'promo_item',
            $itemRecord->id,
            ['position' => $positionRecord->code, 'status' => $nextStatus],
            $request,
        );

        return redirect()
            ->route('admin.promos.items.index', ['position' => $positionRecord->id] + $this->promoIndexQuery($request))
            ->with('status', $nextStatus === 1 ? '图宣内容已启用。' : '图宣内容已停用。');
    }

    /**
     * Move the specified promo item up or down within its position.
     */
    public function move(Request $request, int $position, int $item): RedirectResponse
    {
        [$currentSite, $positionRecord] = $this->resolvePositionContext($request, $position);

        $itemRecord = $this->findItem($currentSite->id, $positionRecord->id, $item);
        abort_unless($itemRecord, 404);

        $direction = (string) $request->input('direction', 'up');
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        $orderedIds = DB::table('promo_items')
            ->where('site_id', $currentSite->id)
            ->where('position_id', $positionRecord->id)
            ->orderBy('sort')
            ->orderByDesc('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $currentIndex = array_search((int) $itemRecord->id, $orderedIds, true);
        $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;

        if ($currentIndex === false || ! array_key_exists($targetIndex, $orderedIds)) {
            return redirect()
                ->route('admin.promos.items.index', ['position' => $positionRecord->id] + $this->promoIndexQuery($request))
                ->with('status', $direction === 'up' ? '已经是第一项。' : '已经是最后一项。');
        }

        [$orderedIds[$currentIndex], $orderedIds[$targetIndex]] = [$orderedIds[$targetIndex], $orderedIds[$currentIndex]];

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $orderedId) {
                DB::table('promo_items')
                    ->where('id', $orderedId)
                    ->update([
                        'sort' => ($index + 1) * 10,
                        'updated_at' => now(),
                    ]);
            }
        });

        $this->logOperation(
            'site',
            'promo',
            'move_item',
            $currentSite->id,
            $request->user()->id,
            'promo_item',
            $itemRecord->id,
            ['position' => $positionRecord->code, 'direction' => $direction],
            $request,
        );

        return redirect()
            ->route('admin.promos.items.index', ['position' => $positionRecord->id] + $this->promoIndexQuery($request))
            ->with('status', $direction === 'up' ? '图宣内容已上移。' : '图宣内容已下移。');
    }

    /**
     * Persist a visible-list reorder operation for promo items.
     */
    public function reorder(Request $request, int $position): JsonResponse
    {
        [$currentSite, $positionRecord] = $this->resolvePositionContext($request, $position);

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $orderedIds = array_map('intval', $validated['ordered_ids']);

        $existingIds = DB::table('promo_items')
            ->where('site_id', $currentSite->id)
            ->where('position_id', $positionRecord->id)
            ->whereIn('id', $orderedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        sort($existingIds);
        $normalizedOrderedIds = $orderedIds;
        sort($normalizedOrderedIds);

        if ($existingIds !== $normalizedOrderedIds) {
            return response()->json([
                'message' => '排序保存失败，图宣内容列表已发生变化，请刷新页面后重试。',
            ], 422);
        }

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $itemId) {
                DB::table('promo_items')
                    ->where('id', $itemId)
                    ->update([
                        'sort' => ($index + 1) * 10,
                        'updated_at' => now(),
                    ]);
            }
        });

        $this->logOperation(
            'site',
            'promo',
            'reorder_items',
            $currentSite->id,
            $request->user()->id,
            'promo_position',
            $positionRecord->id,
            [
                'position' => $positionRecord->code,
                'ordered_ids' => $orderedIds,
            ],
            $request,
        );

        return response()->json([
            'message' => '图宣内容排序已保存。',
        ]);
    }

    /**
     * Duplicate the specified promo item within the same position.
     */
    public function duplicate(Request $request, int $position, int $item): RedirectResponse
    {
        [$currentSite, $positionRecord] = $this->resolvePositionContext($request, $position);

        $itemRecord = $this->findItem($currentSite->id, $positionRecord->id, $item);
        abort_unless($itemRecord, 404);

        $existingItemCount = (int) DB::table('promo_items')
            ->where('position_id', $positionRecord->id)
            ->count();

        if ($existingItemCount >= (int) $positionRecord->max_items) {
            return redirect()
                ->route('admin.promos.items.index', ['position' => $positionRecord->id] + $this->promoIndexQuery($request))
                ->withErrors(['promo_item' => '当前图宣位已达到最大图宣数量限制，请先删除或停用其他图宣内容后再复制。']);
        }

        $nextSort = (int) DB::table('promo_items')
            ->where('position_id', $positionRecord->id)
            ->max('sort');

        $duplicateTitle = $this->nullableTrim((string) ($itemRecord->title ?? ''));
        if ($duplicateTitle !== null) {
            $duplicateTitle = mb_strimwidth($duplicateTitle.' · 副本', 0, 80, '', 'UTF-8');
        }

        $newItemId = DB::table('promo_items')->insertGetId([
            'site_id' => $currentSite->id,
            'position_id' => $positionRecord->id,
            'attachment_id' => (int) $itemRecord->attachment_id,
            'title' => $duplicateTitle,
            'subtitle' => $this->nullableTrim($itemRecord->subtitle),
            'link_url' => $this->nullableTrim($itemRecord->link_url),
            'link_target' => (string) $itemRecord->link_target,
            'sort' => $nextSort > 0 ? $nextSort + 10 : 10,
            'status' => (int) $itemRecord->status,
            'start_at' => $itemRecord->start_at,
            'end_at' => $itemRecord->end_at,
            'display_payload' => $itemRecord->display_payload,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new PromoAttachmentRelationSync())->syncForPromoItem($currentSite->id, $newItemId);

        $this->logOperation(
            'site',
            'promo',
            'duplicate_item',
            $currentSite->id,
            $request->user()->id,
            'promo_item',
            $newItemId,
            [
                'position' => $positionRecord->code,
                'source_item_id' => $itemRecord->id,
                'attachment_id' => (int) $itemRecord->attachment_id,
            ],
            $request,
        );

        return redirect()
            ->route('admin.promos.items.index', ['position' => $positionRecord->id] + $this->promoIndexQuery($request))
            ->with('status', '图宣内容已复制。');
    }

    protected function resolvePositionContext(Request $request, int $position): array
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'promo.manage');

        $positionRecord = DB::table('promo_positions')
            ->leftJoin('channels', 'channels.id', '=', 'promo_positions.channel_id')
            ->where('promo_positions.site_id', $currentSite->id)
            ->where('promo_positions.id', $position)
            ->first([
                'promo_positions.*',
                'channels.name as channel_name',
            ]);

        abort_unless($positionRecord, 404);

        return [$currentSite, $positionRecord];
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

    protected function validateItem(Request $request, int $siteId, object $position): array
    {
        $validator = Validator::make($request->all(), [
            'attachment_id' => [
                'required',
                'integer',
                Rule::exists('attachments', 'id')->where(function ($query) use ($siteId): void {
                    $query->where('site_id', $siteId)
                        ->whereIn('extension', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                }),
            ],
            'title' => ['nullable', 'string', 'max:80'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            'link_url' => ['nullable', 'string', 'max:2048'],
            'link_target' => ['required', Rule::in(['_self', '_blank'])],
            'status' => ['required', Rule::in(['0', '1', 0, 1])],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'floating_position' => ['nullable', 'string', 'max:40'],
            'floating_offset_x' => ['nullable', 'integer', 'min:-2000', 'max:2000'],
            'floating_offset_y' => ['nullable', 'integer', 'min:-2000', 'max:2000'],
            'floating_width' => ['nullable', 'integer', 'min:40', 'max:1200'],
            'floating_height' => ['nullable', 'integer', 'min:40', 'max:1200'],
            'floating_z_index' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'floating_animation' => ['nullable', Rule::in(['none', 'float', 'pulse', 'sway'])],
            'floating_show_on' => ['nullable', Rule::in(['all', 'pc', 'mobile'])],
            'floating_closable' => ['nullable', Rule::in(['0', '1'])],
            'floating_remember_close' => ['nullable', Rule::in(['0', '1'])],
            'floating_close_expire_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ], [], [
            'attachment_id' => '图宣图片',
            'title' => '标题',
            'subtitle' => '副标题',
            'link_url' => '跳转地址',
            'link_target' => '跳转方式',
            'status' => '状态',
            'start_at' => '开始时间',
            'end_at' => '结束时间',
        ]);

        $validator->after(function ($validator) use ($position): void {
            $data = $validator->getData();
            $linkUrl = trim((string) ($data['link_url'] ?? ''));

            if ($linkUrl !== '') {
                if (str_starts_with($linkUrl, '//')) {
                    $validator->errors()->add('link_url', '跳转地址格式不正确，仅支持站内相对路径或完整网址。');
                } elseif (str_starts_with($linkUrl, '/')) {
                    if (! preg_match('#^/(?!/).*$#', $linkUrl)) {
                        $validator->errors()->add('link_url', '跳转地址格式不正确，仅支持站内相对路径或完整网址。');
                    }
                } elseif (! filter_var($linkUrl, FILTER_VALIDATE_URL)) {
                    $validator->errors()->add('link_url', '跳转地址格式不正确，仅支持站内相对路径或完整网址。');
                }
            }

            if ((string) $position->display_mode !== 'floating') {
                return;
            }

            $width = $data['floating_width'] ?? null;
            $showOn = (string) ($data['floating_show_on'] ?? 'all');

            if ($showOn === 'mobile' && $width !== null && (int) $width > 420) {
                $validator->errors()->add('floating_width', '移动端漂浮图宽度建议不超过 420 像素。');
            }
        });

        $validated = $validator->validate();

        if ((string) $position->display_mode !== 'floating') {
            foreach ([
                'floating_position',
                'floating_offset_x',
                'floating_offset_y',
                'floating_width',
                'floating_height',
                'floating_z_index',
                'floating_animation',
                'floating_show_on',
                'floating_closable',
                'floating_remember_close',
                'floating_close_expire_hours',
            ] as $field) {
                unset($validated[$field]);
            }
        }

        return $validated;
    }

    protected function itemPayload(array $validated, int $siteId, int $positionId, bool $withCreatedAt = true): array
    {
        $displayPayload = null;
        $resolvedStatus = (int) $validated['status'];
        $resolvedEndAt = $validated['end_at'] ?? null;

        // If an already-expired end time is submitted, persist it as disabled immediately.
        if ($resolvedStatus === 1 && !empty($resolvedEndAt)) {
            $endAt = Carbon::parse((string) $resolvedEndAt);

            if ($endAt->isPast()) {
                $resolvedStatus = 0;
            }
        }

        if (array_key_exists('floating_position', $validated)) {
            $displayPayload = [
                'position' => $validated['floating_position'] ?: 'right-bottom',
                'offset_x' => isset($validated['floating_offset_x']) ? (int) $validated['floating_offset_x'] : 24,
                'offset_y' => isset($validated['floating_offset_y']) ? (int) $validated['floating_offset_y'] : 24,
                'width' => isset($validated['floating_width']) ? (int) $validated['floating_width'] : 180,
                'height' => isset($validated['floating_height']) ? (int) $validated['floating_height'] : null,
                'z_index' => isset($validated['floating_z_index']) ? (int) $validated['floating_z_index'] : 120,
                'animation' => $validated['floating_animation'] ?: 'float',
                'show_on' => $validated['floating_show_on'] ?: 'all',
                'closable' => (string) ($validated['floating_closable'] ?? '1') === '1',
                'remember_close' => (string) ($validated['floating_remember_close'] ?? '1') === '1',
                'close_expire_hours' => isset($validated['floating_close_expire_hours']) ? (int) $validated['floating_close_expire_hours'] : 24,
            ];
        }

        $payload = [
            'site_id' => $siteId,
            'position_id' => $positionId,
            'attachment_id' => (int) $validated['attachment_id'],
            'title' => $this->nullableTrim($validated['title'] ?? null),
            'subtitle' => $this->nullableTrim($validated['subtitle'] ?? null),
            'link_url' => $this->nullableTrim($validated['link_url'] ?? null),
            'link_target' => (string) $validated['link_target'],
            'sort' => (int) $validated['sort'],
            'status' => $resolvedStatus,
            'start_at' => $validated['start_at'] ?? null,
            'end_at' => $resolvedEndAt,
            'display_payload' => $displayPayload ? json_encode($displayPayload, JSON_UNESCAPED_UNICODE) : null,
            'updated_at' => now(),
        ];

        if ($withCreatedAt) {
            $payload['created_at'] = now();
        }

        return $payload;
    }

    protected function findItem(int $siteId, int $positionId, int $itemId): ?object
    {
        return DB::table('promo_items')
            ->join('attachments', 'attachments.id', '=', 'promo_items.attachment_id')
            ->where('promo_items.site_id', $siteId)
            ->where('promo_items.position_id', $positionId)
            ->where('promo_items.id', $itemId)
            ->first([
                'promo_items.*',
                'attachments.origin_name as attachment_name',
                'attachments.url as attachment_url',
            ]);
    }

    protected function nextSortValue(int $positionId): int
    {
        $maxSort = (int) DB::table('promo_items')
            ->where('position_id', $positionId)
            ->max('sort');

        return $maxSort > 0 ? $maxSort + 10 : 10;
    }

    protected function serializeItem(?object $item): array
    {
        $item = $item ? $this->decorateItemState($item) : null;
        $displayPayload = [];

        if (!empty($item?->display_payload)) {
            $decodedPayload = json_decode((string) $item->display_payload, true);
            $displayPayload = is_array($decodedPayload) ? $decodedPayload : [];
        }

        return [
            'id' => (int) ($item->id ?? 0),
            'attachment_id' => (int) ($item->attachment_id ?? 0),
            'attachment_name' => (string) ($item->attachment_name ?? ''),
            'attachment_url' => (string) ($item->attachment_url ?? ''),
            'title' => (string) ($item->title ?? ''),
            'subtitle' => (string) ($item->subtitle ?? ''),
            'link_url' => (string) ($item->link_url ?? ''),
            'link_target' => (string) ($item->link_target ?? '_self'),
            'sort' => (int) ($item->sort ?? 0),
            'status' => (int) ($item->status ?? 1),
            'effective_status' => (string) ($item->effective_status ?? 'disabled'),
            'effective_status_label' => (string) ($item->effective_status_label ?? '已停用'),
            'effective_status_tone' => (string) ($item->effective_status_tone ?? 'muted'),
            'start_at' => $item->start_at ? (string) $item->start_at : '',
            'end_at' => $item->end_at ? (string) $item->end_at : '',
            'display_payload' => $displayPayload,
        ];
    }

    protected function decorateItemState(object $item): object
    {
        $now = now();
        $startAt = !empty($item->start_at) ? Carbon::parse((string) $item->start_at) : null;
        $endAt = !empty($item->end_at) ? Carbon::parse((string) $item->end_at) : null;

        $effectiveStatus = 'active';
        $effectiveLabel = '启用中';
        $effectiveTone = 'active';

        if ((int) ($item->status ?? 0) !== 1) {
            $effectiveStatus = 'disabled';
            $effectiveLabel = '已停用';
            $effectiveTone = 'muted';
        } elseif ($startAt && $startAt->isFuture()) {
            $effectiveStatus = 'scheduled';
            $effectiveLabel = '未开始';
            $effectiveTone = 'warning';
        } elseif ($endAt && $endAt->isPast()) {
            $effectiveStatus = 'expired';
            $effectiveLabel = '已过期';
            $effectiveTone = 'danger';
        }

        $item->effective_status = $effectiveStatus;
        $item->effective_status_label = $effectiveLabel;
        $item->effective_status_tone = $effectiveTone;

        return $item;
    }

    protected function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
