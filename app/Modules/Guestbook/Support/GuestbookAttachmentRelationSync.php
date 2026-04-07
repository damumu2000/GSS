<?php

namespace App\Modules\Guestbook\Support;

use App\Support\AttachmentUsageTracker;
use Illuminate\Support\Facades\DB;

class GuestbookAttachmentRelationSync
{
    /**
     * Sync attachment relations for guestbook settings and refresh usage stats.
     *
     * @return array<int, int>
     */
    public function syncForSite(int $siteId): array
    {
        $previousAttachmentIds = $this->existingAttachmentIds($siteId);
        $relationRows = $this->buildRelationRows($siteId);

        DB::table('attachment_relations')
            ->where('relation_type', 'guestbook_setting')
            ->where('relation_id', $siteId)
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
     * @return array<int, int>
     */
    protected function existingAttachmentIds(int $siteId): array
    {
        return DB::table('attachment_relations')
            ->join('attachments', 'attachments.id', '=', 'attachment_relations.attachment_id')
            ->where('attachments.site_id', $siteId)
            ->where('attachment_relations.relation_type', 'guestbook_setting')
            ->where('attachment_relations.relation_id', $siteId)
            ->distinct()
            ->pluck('attachment_relations.attachment_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildRelationRows(int $siteId): array
    {
        $notice = (string) DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'module.guestbook.notice')
            ->value('setting_value');
        $noticeImage = (string) DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'module.guestbook.notice_image')
            ->value('setting_value');

        $rows = [];

        foreach ($this->extractAttachmentIdsFromUrls($siteId, [$noticeImage]) as $attachmentId) {
            $rows[$attachmentId.':notice_image'] = [
                'attachment_id' => $attachmentId,
                'relation_type' => 'guestbook_setting',
                'relation_id' => $siteId,
                'usage_slot' => 'notice_image',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ($this->extractAttachmentIdsFromAttribute($siteId, $notice, 'href') as $attachmentId) {
            $rows[$attachmentId.':notice_link'] = [
                'attachment_id' => $attachmentId,
                'relation_type' => 'guestbook_setting',
                'relation_id' => $siteId,
                'usage_slot' => 'notice_link',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return array_values($rows);
    }

    /**
     * @return array<int, int>
     */
    protected function extractAttachmentIdsFromAttribute(int $siteId, string $content, string $attribute): array
    {
        if ($content === '') {
            return [];
        }

        preg_match_all('/'.preg_quote($attribute, '/').'\s*=\s*["\']([^"\']+)["\']/i', $content, $matches);

        return $this->extractAttachmentIdsFromUrls($siteId, $matches[1] ?? []);
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
