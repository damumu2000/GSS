<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AttachmentRelationWriter
{
    /**
     * @param  array<int, array<string, mixed>>  $relationRows
     * @param  array<int, int>  $previousAttachmentIds
     * @return array<int, int>
     */
    public function sync(string $relationType, int $relationId, array $relationRows, array $previousAttachmentIds, int $siteId): array
    {
        if ($this->relationRowsMatch($relationType, $relationId, $relationRows)) {
            return collect($previousAttachmentIds)
                ->merge($this->attachmentIdsFromRows($relationRows))
                ->unique()
                ->values()
                ->all();
        }

        DB::table('attachment_relations')
            ->where('relation_type', $relationType)
            ->where('relation_id', $relationId)
            ->delete();

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
     * @param  array<int, array<string, mixed>>  $relationRows
     */
    protected function relationRowsMatch(string $relationType, int $relationId, array $relationRows): bool
    {
        $existingKeys = DB::table('attachment_relations')
            ->where('relation_type', $relationType)
            ->where('relation_id', $relationId)
            ->get(['attachment_id', 'relation_type', 'relation_id', 'usage_slot'])
            ->map(fn ($row): string => $this->relationKey([
                'attachment_id' => $row->attachment_id,
                'relation_type' => $row->relation_type,
                'relation_id' => $row->relation_id,
                'usage_slot' => $row->usage_slot,
            ]))
            ->sort()
            ->values()
            ->all();

        $targetKeys = collect($relationRows)
            ->map(fn (array $row): string => $this->relationKey($row))
            ->sort()
            ->values()
            ->all();

        return $existingKeys === $targetKeys;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function relationKey(array $row): string
    {
        return implode(':', [
            (int) ($row['attachment_id'] ?? 0),
            (string) ($row['relation_type'] ?? ''),
            (int) ($row['relation_id'] ?? 0),
            (string) ($row['usage_slot'] ?? ''),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $relationRows
     * @return array<int, int>
     */
    protected function attachmentIdsFromRows(array $relationRows): array
    {
        return collect($relationRows)
            ->pluck('attachment_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
