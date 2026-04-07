<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class UserAttachmentRelationSync
{
    /**
     * Sync avatar attachment relations for a user and refresh usage stats.
     *
     * @return array<int, int>
     */
    public function syncForUser(int $siteId, int $userId): array
    {
        $previousAttachmentIds = $this->existingAttachmentIds($siteId, $userId);

        $avatar = DB::table('users')
            ->where('id', $userId)
            ->value('avatar');

        DB::table('attachment_relations')
            ->where('relation_type', 'user')
            ->where('relation_id', $userId)
            ->delete();

        $relationRows = [];

        foreach ($this->extractAttachmentIdsFromUrls($siteId, [(string) $avatar]) as $attachmentId) {
            $relationRows[] = [
                'attachment_id' => $attachmentId,
                'relation_type' => 'user',
                'relation_id' => $userId,
                'usage_slot' => 'avatar',
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
     * Clear avatar attachment relations for a user and refresh usage stats.
     *
     * @return array<int, int>
     */
    public function clearForUser(int $siteId, int $userId): array
    {
        $previousAttachmentIds = $this->existingAttachmentIds($siteId, $userId);

        DB::table('attachment_relations')
            ->where('relation_type', 'user')
            ->where('relation_id', $userId)
            ->delete();

        if ($previousAttachmentIds !== []) {
            (new AttachmentUsageTracker())->rebuildForAttachmentIds($previousAttachmentIds, $siteId);
        }

        return $previousAttachmentIds;
    }

    /**
     * @return array<int, int>
     */
    protected function existingAttachmentIds(int $siteId, int $userId): array
    {
        return DB::table('attachment_relations')
            ->join('attachments', 'attachments.id', '=', 'attachment_relations.attachment_id')
            ->where('attachments.site_id', $siteId)
            ->where('attachment_relations.relation_type', 'user')
            ->where('attachment_relations.relation_id', $userId)
            ->distinct()
            ->pluck('attachment_relations.attachment_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<int, int>
     */
    protected function extractAttachmentIdsFromUrls(int $siteId, array $urls): array
    {
        $urls = array_values(array_filter($urls, fn ($url) => trim((string) $url) !== ''));

        if ($urls === []) {
            return [];
        }

        $normalizedUrls = collect($urls)
            ->flatMap(function (string $url): array {
                $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));

                if ($url === '') {
                    return [];
                }

                if (str_starts_with($url, '//')) {
                    $url = 'http:'.$url;
                }

                $candidates = [$url];

                if (str_starts_with($url, '/')) {
                    $candidates[] = url($url);
                }

                $parsedPath = parse_url($url, PHP_URL_PATH);

                if (is_string($parsedPath) && $parsedPath !== '') {
                    $candidates[] = $parsedPath;
                }

                return $candidates;
            })
            ->map(fn ($url) => trim((string) $url))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($normalizedUrls === []) {
            return [];
        }

        return DB::table('attachments')
            ->where('site_id', $siteId)
            ->where(function ($query) use ($normalizedUrls): void {
                $query->whereIn('url', $normalizedUrls)
                    ->orWhereIn(DB::raw("CONCAT('/', path)"), $normalizedUrls);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
