<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttachmentUsageTracker
{
    /**
     * Remove duplicate attachment relation rows and keep the earliest record.
     */
    public function deduplicateRelations(): int
    {
        $hasUsageSlot = Schema::hasColumn('attachment_relations', 'usage_slot');

        $selectColumns = [
            'attachment_id',
            'relation_type',
            'relation_id',
            DB::raw('MIN(id) AS keep_id'),
            DB::raw('COUNT(*) AS duplicate_count'),
        ];

        $groupByColumns = ['attachment_id', 'relation_type', 'relation_id'];

        if ($hasUsageSlot) {
            $selectColumns[] = 'usage_slot';
            $groupByColumns[] = 'usage_slot';
        }

        $duplicateGroups = DB::table('attachment_relations')
            ->select(...$selectColumns)
            ->groupBy(...$groupByColumns)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $deleted = 0;

        foreach ($duplicateGroups as $group) {
            $deleted += DB::table('attachment_relations')
                ->where('attachment_id', $group->attachment_id)
                ->where('relation_type', $group->relation_type)
                ->where('relation_id', $group->relation_id)
                ->when(
                    $hasUsageSlot,
                    fn ($query) => $query->where('usage_slot', $group->usage_slot),
                )
                ->where('id', '!=', $group->keep_id)
                ->delete();
        }

        return $deleted;
    }

    /**
     * Rebuild usage stats for all attachments or a single site.
     *
     * @return array{attachments:int, referenced:int}
     */
    public function rebuildAll(?int $siteId = null): array
    {
        $this->deduplicateRelations();

        $query = DB::table('attachments')->select('id');

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        $attachmentIds = $query->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->rebuildForAttachmentIds($attachmentIds, $siteId);

        $referenced = DB::table('attachments')
            ->when($siteId !== null, fn ($builder) => $builder->where('site_id', $siteId))
            ->where('usage_count', '>', 0)
            ->count();

        return [
            'attachments' => count($attachmentIds),
            'referenced' => (int) $referenced,
        ];
    }

    /**
     * Rebuild usage stats for the given attachment ids.
     *
     * @param  array<int, int|string>  $attachmentIds
     */
    public function rebuildForAttachmentIds(array $attachmentIds, ?int $siteId = null): void
    {
        $attachmentIds = collect($attachmentIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($attachmentIds === []) {
            return;
        }

        $attachmentsQuery = DB::table('attachments')
            ->whereIn('id', $attachmentIds);

        if ($siteId !== null) {
            $attachmentsQuery->where('site_id', $siteId);
        }

        $existingAttachmentIds = $attachmentsQuery
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($existingAttachmentIds === []) {
            return;
        }

        DB::table('attachments')
            ->whereIn('id', $existingAttachmentIds)
            ->update([
                'usage_count' => 0,
                'last_used_at' => null,
            ]);

        $usageRows = DB::table('attachment_relations')
            ->join('attachments', 'attachments.id', '=', 'attachment_relations.attachment_id')
            ->whereIn('attachment_relations.attachment_id', $existingAttachmentIds)
            ->when($siteId !== null, fn ($query) => $query->where('attachments.site_id', $siteId))
            ->groupBy('attachment_relations.attachment_id')
            ->get([
                'attachment_relations.attachment_id',
                DB::raw('COUNT(*) AS usage_count'),
                DB::raw('MAX(attachment_relations.updated_at) AS last_used_at'),
            ]);

        foreach ($usageRows as $row) {
            DB::table('attachments')
                ->where('id', $row->attachment_id)
                ->update([
                    'usage_count' => (int) $row->usage_count,
                    'last_used_at' => $row->last_used_at,
                ]);
        }
    }
}
