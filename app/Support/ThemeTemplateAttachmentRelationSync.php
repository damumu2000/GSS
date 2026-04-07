<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class ThemeTemplateAttachmentRelationSync
{
    /**
     * Extract attachment ids referenced by a template source.
     *
     * @return array<int, int>
     */
    public function extractAttachmentIds(int $siteId, string $templateSource): array
    {
        return collect($this->buildRelationRows($siteId, 0, $templateSource))
            ->pluck('attachment_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Sync attachment relations for a theme template and refresh usage stats.
     *
     * @return array<int, int>
     */
    public function syncForTemplate(int $siteId, string $themeCode, string $template, string $templateSource): array
    {
        $templateMetaId = $this->templateMetaId($siteId, $themeCode, $template);

        if ($templateMetaId <= 0) {
            return [];
        }

        $previousAttachmentIds = $this->existingAttachmentIds($siteId, $templateMetaId);
        $relationRows = $this->buildRelationRows($siteId, $templateMetaId, $templateSource);

        DB::table('attachment_relations')
            ->where('relation_type', 'theme_template')
            ->where('relation_id', $templateMetaId)
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
     * Clear all attachment relations for a theme template.
     *
     * @return array<int, int>
     */
    public function clearForTemplate(int $siteId, string $themeCode, string $template): array
    {
        $templateMetaId = $this->templateMetaId($siteId, $themeCode, $template);

        if ($templateMetaId <= 0) {
            return [];
        }

        $previousAttachmentIds = $this->existingAttachmentIds($siteId, $templateMetaId);

        DB::table('attachment_relations')
            ->where('relation_type', 'theme_template')
            ->where('relation_id', $templateMetaId)
            ->delete();

        if ($previousAttachmentIds !== []) {
            (new AttachmentUsageTracker())->rebuildForAttachmentIds($previousAttachmentIds, $siteId);
        }

        return $previousAttachmentIds;
    }

    protected function templateMetaId(int $siteId, string $themeCode, string $template): int
    {
        return (int) DB::table('site_theme_template_meta')
            ->where('site_id', $siteId)
            ->where('theme_code', $themeCode)
            ->where('template_name', $template)
            ->value('id');
    }

    /**
     * @return array<int, int>
     */
    protected function existingAttachmentIds(int $siteId, int $templateMetaId): array
    {
        return DB::table('attachment_relations')
            ->join('attachments', 'attachments.id', '=', 'attachment_relations.attachment_id')
            ->where('attachments.site_id', $siteId)
            ->where('attachment_relations.relation_type', 'theme_template')
            ->where('attachment_relations.relation_id', $templateMetaId)
            ->distinct()
            ->pluck('attachment_relations.attachment_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildRelationRows(int $siteId, int $templateMetaId, string $templateSource): array
    {
        $rows = [];

        foreach ($this->extractAttachmentIdsFromAttribute($siteId, $templateSource, 'src') as $attachmentId) {
            $rows[$attachmentId] = $this->makeRelationRow($attachmentId, $templateMetaId, 'template_image');
        }

        foreach ($this->extractAttachmentIdsFromAttribute($siteId, $templateSource, 'poster') as $attachmentId) {
            $rows[$attachmentId] = $this->makeRelationRow($attachmentId, $templateMetaId, 'template_image');
        }

        foreach ($this->extractAttachmentIdsFromSrcset($siteId, $templateSource) as $attachmentId) {
            $rows[$attachmentId] = $this->makeRelationRow($attachmentId, $templateMetaId, 'template_image');
        }

        foreach ($this->extractAttachmentIdsFromAttribute($siteId, $templateSource, 'href') as $attachmentId) {
            $rows[$attachmentId] ??= $this->makeRelationRow($attachmentId, $templateMetaId, 'template_link');
        }

        foreach ($this->extractAttachmentIdsFromCssUrls($siteId, $templateSource) as $attachmentId) {
            $rows[$attachmentId] ??= $this->makeRelationRow($attachmentId, $templateMetaId, 'template_asset');
        }

        foreach ($this->extractAttachmentIdsFromPlainPaths($siteId, $templateSource) as $attachmentId) {
            $rows[$attachmentId] ??= $this->makeRelationRow($attachmentId, $templateMetaId, 'template_asset');
        }

        return array_values($rows);
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeRelationRow(int $attachmentId, int $templateMetaId, string $usageSlot): array
    {
        return [
            'attachment_id' => $attachmentId,
            'relation_type' => 'theme_template',
            'relation_id' => $templateMetaId,
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
     * @return array<int, int>
     */
    protected function extractAttachmentIdsFromPlainPaths(int $siteId, string $content): array
    {
        if ($content === '') {
            return [];
        }

        preg_match_all('/(?:(?:https?:)?\/\/[^\\s"\')<>]+)?(\/site-media\/[^\\s"\')<>]+)/i', $content, $matches);

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
                    ->orWhereIn('path', array_map(fn ($url) => ltrim((string) $url, '/'), $normalizedUrls))
                    ->orWhereIn(DB::raw("CONCAT('/', path)"), $normalizedUrls);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
