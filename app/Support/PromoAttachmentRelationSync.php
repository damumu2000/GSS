<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class PromoAttachmentRelationSync
{
    /**
     * Sync attachment relations for a promo item and refresh usage stats.
     *
     * @return array<int, int>
     */
    public function syncForPromoItem(int $siteId, int $promoItemId): array
    {
        $previousAttachmentIds = $this->existingAttachmentIds($siteId, $promoItemId);

        $promoItem = DB::table('promo_items')
            ->where('site_id', $siteId)
            ->where('id', $promoItemId)
            ->first(['attachment_id']);

        DB::table('attachment_relations')
            ->where('relation_type', 'promo_item')
            ->where('relation_id', $promoItemId)
            ->delete();

        $relationRows = [];

        if ($promoItem && (int) ($promoItem->attachment_id ?? 0) > 0) {
            $relationRows[] = [
                'attachment_id' => (int) $promoItem->attachment_id,
                'relation_type' => 'promo_item',
                'relation_id' => $promoItemId,
                'usage_slot' => 'promo_image',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($relationRows !== []) {
            DB::table('attachment_relations')->insert($relationRows);
        }

        $affectedAttachmentIds = collect($relationRows)
            ->pluck('attachment_id')
            ->map(fn ($id) => (int) $id)
            ->merge($previousAttachmentIds)
            ->unique()
            ->values()
            ->all();

        if ($affectedAttachmentIds !== []) {
            (new AttachmentUsageTracker())->rebuildForAttachmentIds($affectedAttachmentIds, $siteId);
        }

        return $affectedAttachmentIds;
    }

    /**
     * Clear attachment relations for a promo item and refresh usage stats.
     *
     * @return array<int, int>
     */
    public function clearForPromoItem(int $siteId, int $promoItemId): array
    {
        $previousAttachmentIds = $this->existingAttachmentIds($siteId, $promoItemId);

        DB::table('attachment_relations')
            ->where('relation_type', 'promo_item')
            ->where('relation_id', $promoItemId)
            ->delete();

        if ($previousAttachmentIds !== []) {
            (new AttachmentUsageTracker())->rebuildForAttachmentIds($previousAttachmentIds, $siteId);
        }

        return $previousAttachmentIds;
    }

    /**
     * @return array<int, int>
     */
    protected function existingAttachmentIds(int $siteId, int $promoItemId): array
    {
        return DB::table('attachment_relations')
            ->join('attachments', 'attachments.id', '=', 'attachment_relations.attachment_id')
            ->where('attachments.site_id', $siteId)
            ->where('attachment_relations.relation_type', 'promo_item')
            ->where('attachment_relations.relation_id', $promoItemId)
            ->distinct()
            ->pluck('attachment_relations.attachment_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
