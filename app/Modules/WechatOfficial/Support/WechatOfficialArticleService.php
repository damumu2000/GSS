<?php

namespace App\Modules\WechatOfficial\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WechatOfficialArticleService
{
    public function paginatedPublishedArticles(int $siteId, string $keyword = '', string $pushStatus = ''): LengthAwarePaginator
    {
        $latestPushIds = DB::table('module_wechat_official_article_pushes')
            ->selectRaw('MAX(id) as id, content_id')
            ->where('site_id', $siteId)
            ->groupBy('content_id');
        $coverAttachmentRelations = DB::table('attachment_relations')
            ->select('relation_id', 'attachment_id')
            ->where('relation_type', 'content')
            ->where('usage_slot', 'cover_image');
        $latestMaterialIds = DB::table('module_wechat_official_materials')
            ->selectRaw('MAX(id) as id, attachment_id')
            ->where('site_id', $siteId)
            ->whereNotNull('attachment_id')
            ->groupBy('attachment_id');

        return DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->leftJoinSub($latestPushIds, 'latest_push_ids', function ($join): void {
                $join->on('latest_push_ids.content_id', '=', 'contents.id');
            })
            ->leftJoinSub($coverAttachmentRelations, 'cover_relations', function ($join): void {
                $join->on('cover_relations.relation_id', '=', 'contents.id');
            })
            ->leftJoinSub($latestMaterialIds, 'latest_material_ids', function ($join): void {
                $join->on('latest_material_ids.attachment_id', '=', 'cover_relations.attachment_id');
            })
            ->leftJoin('module_wechat_official_article_pushes as pushes', 'pushes.id', '=', 'latest_push_ids.id')
            ->leftJoin('module_wechat_official_materials as cover_materials', 'cover_materials.id', '=', 'latest_material_ids.id')
            ->where('contents.site_id', $siteId)
            ->where('contents.type', 'article')
            ->where('contents.status', 'published')
            ->whereNull('contents.deleted_at')
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery->where('contents.title', 'like', '%'.$keyword.'%')
                        ->orWhere('contents.summary', 'like', '%'.$keyword.'%');
                });
            })
            ->when($pushStatus !== '', function ($query) use ($pushStatus): void {
                if ($pushStatus === 'not_pushed') {
                    $query->whereNull('pushes.id');

                    return;
                }

                $query->where('pushes.status', $pushStatus);
            })
            ->orderByDesc('contents.published_at')
            ->orderByDesc('contents.id')
            ->paginate(10, [
                'contents.id',
                'contents.title',
                'contents.summary',
                'contents.content',
                'contents.author',
                'contents.cover_image',
                'contents.published_at',
                'contents.updated_at',
                'channels.name as channel_name',
                'cover_relations.attachment_id as cover_attachment_id',
                'cover_materials.wechat_media_id as auto_thumb_media_id',
                'cover_materials.synced_at as auto_thumb_synced_at',
                'pushes.id as push_id',
                'pushes.status as push_status',
                'pushes.draft_media_id',
                'pushes.publish_id',
                'pushes.error_message',
                'pushes.updated_at as push_updated_at',
            ])
            ->withQueryString();
    }

    public function recommendedThumbMediaId(int $siteId, int $contentId): string
    {
        $coverAttachmentId = DB::table('attachment_relations')
            ->where('relation_type', 'content')
            ->where('relation_id', $contentId)
            ->where('usage_slot', 'cover_image')
            ->value('attachment_id');

        if (! $coverAttachmentId) {
            return '';
        }

        return trim((string) DB::table('module_wechat_official_materials')
            ->where('site_id', $siteId)
            ->where('attachment_id', (int) $coverAttachmentId)
            ->orderByDesc('id')
            ->value('wechat_media_id'));
    }

    /**
     * @return array<int, object>
     */
    public function recentPushes(int $siteId, int $limit = 8): array
    {
        return DB::table('module_wechat_official_article_pushes')
            ->where('site_id', $siteId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'content_id',
                'title',
                'status',
                'draft_media_id',
                'publish_id',
                'error_message',
                'published_at',
                'updated_at',
            ])
            ->all();
    }

    /**
     * @return array{content: object, draft: array<string, mixed>}
     */
    public function buildDraftPayload(int $siteId, int $contentId, string $thumbMediaId, ?string $sourceUrl = null): array
    {
        $content = DB::table('contents')
            ->where('site_id', $siteId)
            ->where('type', 'article')
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->where('id', $contentId)
            ->first([
                'id',
                'title',
                'summary',
                'content',
                'author',
                'cover_image',
                'published_at',
            ]);

        if (! $content) {
            throw new RuntimeException('文章不存在或暂未发布，不能同步到公众号草稿。');
        }

        $baseUrl = $this->siteBaseUrl($siteId);
        $resolvedSourceUrl = trim((string) $sourceUrl);
        if ($resolvedSourceUrl === '') {
            $siteKey = trim((string) DB::table('sites')->where('id', $siteId)->value('site_key'));
            $resolvedSourceUrl = route('site.article', ['id' => $contentId, 'site' => $siteKey]);
        }

        $sanitizedContent = $this->sanitizeHtml((string) ($content->content ?? ''), $baseUrl);
        if (trim(strip_tags($sanitizedContent)) === '') {
            $fallbackText = trim((string) ($content->summary ?? '')) !== ''
                ? trim((string) $content->summary)
                : trim((string) $content->title);
            $sanitizedContent = '<p>'.e($fallbackText).'</p>';
        }

        $digest = trim((string) ($content->summary ?? ''));
        if ($digest === '') {
            $digest = Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($sanitizedContent)) ?? ''), 120, '');
        }

        return [
            'content' => $content,
            'draft' => [
                'title' => trim((string) $content->title),
                'author' => trim((string) ($content->author ?? '')),
                'digest' => $digest,
                'content' => $sanitizedContent,
                'content_source_url' => $resolvedSourceUrl,
                'thumb_media_id' => trim($thumbMediaId),
                'show_cover_pic' => 1,
                'need_open_comment' => 0,
                'only_fans_can_comment' => 0,
            ],
        ];
    }

    protected function siteBaseUrl(int $siteId): string
    {
        $domain = trim((string) DB::table('site_domains')
            ->where('site_id', $siteId)
            ->where('status', 1)
            ->orderByDesc('is_primary')
            ->orderBy('domain')
            ->value('domain'));

        if ($domain !== '') {
            return 'https://'.$domain;
        }

        $siteKey = trim((string) DB::table('sites')->where('id', $siteId)->value('site_key'));

        return rtrim(route('site.home', ['site' => $siteKey]), '/');
    }

    protected function sanitizeHtml(string $html, string $baseUrl): string
    {
        $sanitized = trim($html);
        if ($sanitized === '') {
            return '';
        }

        $sanitized = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('#\s+on[a-z]+\s*=\s*([\'"]).*?\1#is', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace_callback(
            '/\b(href|src)=([\'"])(\/(?!\/)[^\'"]*)\2/i',
            static fn (array $matches): string => sprintf('%s=%s%s%s', $matches[1], $matches[2], rtrim($baseUrl, '/').$matches[3], $matches[2]),
            $sanitized
        ) ?? $sanitized;

        return $sanitized;
    }
}
