<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class ContentAttachmentRelationSync
{
    /**
     * @return array<int, int>
     */
    public function extractAttachmentIds(int $siteId, string $contentHtml, string $coverImage = ''): array
    {
        return collect($this->buildRelationRows($siteId, $contentHtml, $coverImage, 0))
            ->pluck('attachment_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Sync attachment relations for a content entry and refresh usage stats.
     *
     * @return array<int, int>
     */
    public function syncForContent(int $siteId, int $contentId): array
    {
        $previousAttachmentIds = DB::table('attachment_relations')
            ->join('attachments', 'attachments.id', '=', 'attachment_relations.attachment_id')
            ->where('attachments.site_id', $siteId)
            ->where('attachment_relations.relation_type', 'content')
            ->where('attachment_relations.relation_id', $contentId)
            ->distinct()
            ->pluck('attachment_relations.attachment_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $content = DB::table('contents')
            ->where('site_id', $siteId)
            ->where('id', $contentId)
            ->first(['content', 'cover_image']);

        $relationRows = $this->buildRelationRows(
            $siteId,
            (string) ($content->content ?? ''),
            (string) ($content->cover_image ?? ''),
            $contentId,
        );

        DB::table('attachment_relations')
            ->where('relation_type', 'content')
            ->where('relation_id', $contentId)
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
     * @return array<int, array<string, mixed>>
     */
    protected function buildRelationRows(int $siteId, string $contentHtml, string $coverImage, int $contentId): array
    {
        $rows = [];

        foreach ($this->extractAttachmentIdsFromUrls($siteId, [$coverImage]) as $attachmentId) {
            $rows[$attachmentId.':cover_image'] = $this->makeRelationRow($attachmentId, $contentId, 'cover_image');
        }

        foreach ($this->extractAttachmentIdsFromAttribute($siteId, $contentHtml, 'src') as $attachmentId) {
            $rows[$attachmentId.':body_image'] = $this->makeRelationRow($attachmentId, $contentId, 'body_image');
        }

        foreach ($this->extractAttachmentIdsFromAttribute($siteId, $contentHtml, 'poster') as $attachmentId) {
            $rows[$attachmentId.':body_image'] = $this->makeRelationRow($attachmentId, $contentId, 'body_image');
        }

        foreach ($this->extractAttachmentIdsFromSrcset($siteId, $contentHtml) as $attachmentId) {
            $rows[$attachmentId.':body_image'] = $this->makeRelationRow($attachmentId, $contentId, 'body_image');
        }

        foreach ($this->extractAttachmentIdsFromAttribute($siteId, $contentHtml, 'href') as $attachmentId) {
            $rows[$attachmentId.':body_link'] = $this->makeRelationRow($attachmentId, $contentId, 'body_link');
        }

        foreach ($this->extractAttachmentIdsFromCssUrls($siteId, $contentHtml) as $attachmentId) {
            $rows[$attachmentId.':body_image'] ??= $this->makeRelationRow($attachmentId, $contentId, 'body_image');
        }

        return array_values($rows);
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeRelationRow(int $attachmentId, int $contentId, string $usageSlot): array
    {
        return [
            'attachment_id' => $attachmentId,
            'relation_type' => 'content',
            'relation_id' => $contentId,
            'usage_slot' => $usageSlot,
            'created_at' => now(),
            'updated_at' => now(),
        ];
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
     * @return array<int, int>
     */
    protected function extractAttachmentIdsFromSrcset(int $siteId, string $content): array
    {
        if ($content === '') {
            return [];
        }

        preg_match_all('/srcset\s*=\s*["\']([^"\']+)["\']/i', $content, $matches);

        $urls = collect($matches[1] ?? [])
            ->flatMap(function (string $srcset): array {
                return collect(explode(',', $srcset))
                    ->map(fn (string $item): string => trim((string) preg_split('/\s+/', trim($item))[0] ?? ''))
                    ->filter()
                    ->values()
                    ->all();
            })
            ->values()
            ->all();

        return $this->extractAttachmentIdsFromUrls($siteId, $urls);
    }

    /**
     * @return array<int, int>
     */
    protected function extractAttachmentIdsFromCssUrls(int $siteId, string $content): array
    {
        if ($content === '') {
            return [];
        }

        preg_match_all('/url\((["\']?)([^)"\']+)\1\)/i', $content, $matches);

        return $this->extractAttachmentIdsFromUrls($siteId, $matches[2] ?? []);
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

                if ($url === '' || str_starts_with($url, 'data:') || str_starts_with($url, '#')) {
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
