<?php

namespace App\Http\Controllers;

use App\Support\EmbeddedContentRenderer;
use App\Support\Site as SitePath;
use App\Support\ThemeTags;
use App\Support\ThemeTemplateEngine;
use App\Support\ThemeTemplateException;
use App\Support\ThemeTemplateLocator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SiteController extends Controller
{
    /**
     * Render the default site homepage with the active theme.
     */
    public function show(Request $request): Response
    {
        $site = $this->resolvedSite($request);
        if (! $site) {
            return $this->renderDomainUnboundPage($request);
        }
        $this->recordSiteVisit((int) $site->id, 'home');

        $settings = $this->siteSettings($site->id);
        $themeCode = $this->frontendThemeCode($site->id);
        if ($themeCode === null) {
            return $this->renderMissingThemePage($site);
        }
        $channels = $this->siteNavChannels($site->id);
        $tags = (new ThemeTags($site, $settings, $channels))->withContext('home', null, 'home');
        $navItems = $tags->nav()->values();

        return $this->renderTheme($site, $themeCode, 'home', [
            'site' => $this->sitePayload($site, $settings),
            'settings' => $settings,
            'tags' => $tags,
            'navItems' => $navItems,
            'primaryChannel' => $navItems->first(),
            'activeChannelSlug' => null,
            'page' => $this->pageMeta($site, $settings),
            'meta' => $this->metaPayload($site),
        ]);
    }

    /**
     * Render a channel list page.
     */
    public function channel(Request $request, string $slug): Response
    {
        $site = $this->resolvedSite($request);
        if (! $site) {
            return $this->renderDomainUnboundPage($request);
        }
        $this->recordSiteVisit((int) $site->id, 'channel');
        $settings = $this->siteSettings($site->id);
        $themeCode = $this->frontendThemeCode($site->id);
        if ($themeCode === null) {
            return $this->renderMissingThemePage($site);
        }
        $channels = $this->siteNavChannels($site->id);
        $tags = new ThemeTags($site, $settings, $channels);

        $channel = DB::table('channels')
            ->where('site_id', $site->id)
            ->where('slug', $slug)
            ->where('status', 1)
            ->first();

        abort_unless($channel, 404);

        if ($channel->type === 'link') {
            abort_unless(! empty($channel->link_url), 404);

            return redirect()->away($channel->link_url);
        }

        if ($channel->type === 'page') {
            $page = DB::table('contents')
                ->where('site_id', $site->id)
                ->where('type', 'page')
                ->where('status', 'published')
                ->whereNull('deleted_at')
                ->whereExists(function ($query) use ($channel): void {
                    $query->selectRaw('1')
                        ->from('content_channels')
                        ->whereColumn('content_channels.content_id', 'contents.id')
                        ->where('content_channels.channel_id', $channel->id);
                })
                ->orderByDesc('updated_at')
                ->first();

            abort_unless($page, 404);
            $pageTemplate = $page->template_name ?: ($channel->detail_template ?: 'page');
            $tags->withContext('page', (int) $channel->id, $pageTemplate);
            $navItems = $tags->nav()->values();

            return $this->renderTheme($site, $themeCode, $pageTemplate, [
                'site' => $this->sitePayload($site, $settings),
                'settings' => $settings,
                'tags' => $tags,
                'navItems' => $navItems,
                'activeChannelSlug' => $channel->slug,
                'channel' => $this->channelPayload($channel),
                'page' => $this->pagePayload($page, $site, $channel),
                'meta' => $this->metaPayload(
                    $site,
                    trim((string) ($page->seo_title ?? '')) !== '' ? (string) $page->seo_title : ((string) $page->title.' - '.$site->name),
                    (string) ($page->seo_keywords ?? ''),
                    trim((string) ($page->seo_description ?? '')) !== '' ? (string) $page->seo_description : (string) ($page->summary ?? '')
                ),
            ]);
        }

        $channelIds = $this->channelAndDescendantLeafIds($site->id, (int) $channel->id);
        $listTemplate = $channel->list_template ?: 'list';
        $tags->withContext('channel', (int) $channel->id, $listTemplate);
        $navItems = $tags->nav()->values();

        $items = DB::table('contents')
            ->where('site_id', $site->id)
            ->where('type', 'article')
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->whereExists(function ($query) use ($channelIds): void {
                $query->selectRaw('1')
                ->from('content_channels')
                ->whereColumn('content_channels.content_id', 'contents.id')
                ->whereIn('content_channels.channel_id', $channelIds);
            })
            ->orderByDesc('is_top')
            ->orderByDesc('sort')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn ($item) => $this->articleListPayload($item, $site, $channel));

        return $this->renderTheme($site, $themeCode, $listTemplate, [
            'site' => $this->sitePayload($site, $settings),
            'settings' => $settings,
            'tags' => $tags,
            'navItems' => $navItems,
            'activeChannelSlug' => $channel->slug,
            'page' => $this->pageMeta($site, $settings, $channel->name.' - '.$site->name),
            'meta' => $this->metaPayload($site, $channel->name.' - '.$site->name),
            'channel' => $this->channelPayload($channel),
            'items' => $items,
        ]);
    }

    /**
     * Render a published article detail page.
     */
    public function article(Request $request, string $id): Response
    {
        $site = $this->resolvedSite($request);
        if (! $site) {
            return $this->renderDomainUnboundPage($request);
        }
        $settings = $this->siteSettings($site->id);
        $themeCode = $this->frontendThemeCode($site->id);
        if ($themeCode === null) {
            return $this->renderMissingThemePage($site);
        }
        $channels = $this->siteNavChannels($site->id);
        $tags = new ThemeTags($site, $settings, $channels);

        $article = DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->where('contents.site_id', $site->id)
            ->where('contents.id', $id)
            ->where('contents.type', 'article')
            ->where('contents.status', 'published')
            ->whereNull('contents.deleted_at')
            ->first([
                'contents.*',
                'channels.name as channel_name',
                'channels.slug as channel_slug',
            ]);

        abort_unless($article, 404);
        $this->recordSiteVisit((int) $site->id, 'article', (int) $article->id);
        $detailTemplate = $this->articleTemplate($site->id, (int) $article->id, (string) ($article->channel_slug ?? ''));
        $tags->withContext('detail', $article->channel_id ? (int) $article->channel_id : null, $detailTemplate);
        $navItems = $tags->nav()->values();

        $attachments = DB::table('attachment_relations')
            ->join('attachments', 'attachments.id', '=', 'attachment_relations.attachment_id')
            ->where('attachments.site_id', $site->id)
            ->where('attachment_relations.relation_type', 'content')
            ->where('attachment_relations.relation_id', $article->id)
            ->orderBy('attachments.id')
            ->get([
                'attachments.id',
                'attachments.origin_name',
                'attachments.extension',
                'attachments.size',
                'attachments.url',
            ])
            ->map(fn ($attachment) => [
                'id' => $attachment->id,
                'origin_name' => $attachment->origin_name,
                'extension' => $attachment->extension ?: '',
                'extension_upper' => strtoupper($attachment->extension ?: '-'),
                'size' => $attachment->size,
                'size_kb' => number_format(($attachment->size ?? 0) / 1024, 1),
                'url' => $attachment->url,
            ]);

        return $this->renderTheme($site, $themeCode, $detailTemplate, [
            'site' => $this->sitePayload($site, $settings),
            'settings' => $settings,
            'tags' => $tags,
            'navItems' => $navItems,
            'activeChannelSlug' => $article->channel_slug,
            'page' => $this->pageMeta($site, $settings, $article->title.' - '.$site->name),
            'meta' => $this->metaPayload(
                $site,
                trim((string) ($article->seo_title ?? '')) !== '' ? (string) $article->seo_title : ((string) $article->title.' - '.$site->name),
                (string) ($article->seo_keywords ?? ''),
                trim((string) ($article->seo_description ?? '')) !== '' ? (string) $article->seo_description : (string) ($article->summary ?? '')
            ),
            'article' => $this->articlePayload($article, $site),
            'attachments' => $attachments,
            'previousArticle' => $tags->previous($article),
            'nextArticle' => $tags->next($article),
            'relatedArticles' => $tags->related($article),
        ]);
    }

    /**
     * Render a page detail page.
     */
    public function page(Request $request, string $id): Response
    {
        $site = $this->resolvedSite($request);
        if (! $site) {
            return $this->renderDomainUnboundPage($request);
        }
        $this->recordSiteVisit((int) $site->id, 'page');
        $settings = $this->siteSettings($site->id);
        $themeCode = $this->frontendThemeCode($site->id);
        if ($themeCode === null) {
            return $this->renderMissingThemePage($site);
        }
        $channels = $this->siteNavChannels($site->id);
        $tags = new ThemeTags($site, $settings, $channels);

        $page = DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->where('contents.site_id', $site->id)
            ->where('contents.id', $id)
            ->where('contents.type', 'page')
            ->where('contents.status', 'published')
            ->whereNull('contents.deleted_at')
            ->first([
                'contents.*',
                'channels.name as channel_name',
                'channels.slug as channel_slug',
            ]);

        abort_unless($page, 404);
        $pageTemplate = $this->pageTemplate($site->id, $page->id);
        $tags->withContext('page', $page->channel_id ? (int) $page->channel_id : null, $pageTemplate);
        $navItems = $tags->nav()->values();

        $channel = (object) ['name' => $page->channel_name ?: '单页面', 'slug' => $page->channel_slug];

        return $this->renderTheme($site, $themeCode, $pageTemplate, [
            'site' => $this->sitePayload($site, $settings),
            'settings' => $settings,
            'tags' => $tags,
            'navItems' => $navItems,
            'activeChannelSlug' => $page->channel_slug,
            'page' => $this->pagePayload($page, $site, $channel),
            'meta' => $this->metaPayload(
                $site,
                trim((string) ($page->seo_title ?? '')) !== '' ? (string) $page->seo_title : ((string) $page->title.' - '.$site->name),
                (string) ($page->seo_keywords ?? ''),
                trim((string) ($page->seo_description ?? '')) !== '' ? (string) $page->seo_description : (string) ($page->summary ?? '')
            ),
            'channel' => $this->channelPayload($channel),
        ]);
    }

    public function previewArticle(Request $request, string $content): Response
    {
        $accessibleSiteIds = $this->siteIdsWithPermission($request->user()->id, 'content.manage');
        abort_if($accessibleSiteIds === [], 404);

        $article = DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->whereIn('contents.site_id', $accessibleSiteIds)
            ->where('contents.id', $content)
            ->where('contents.type', 'article')
            ->whereNull('contents.deleted_at');

        $contentRecord = $article->first([
            'contents.*',
            'channels.name as channel_name',
            'channels.slug as channel_slug',
        ]);

        abort_unless($contentRecord, 404);

        $scopedArticle = DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->where('contents.site_id', (int) $contentRecord->site_id)
            ->where('contents.id', $content)
            ->where('contents.type', 'article')
            ->whereNull('contents.deleted_at');
        $this->applySiteContentVisibilityScope($scopedArticle, $request->user()->id, (int) $contentRecord->site_id);

        $articleRecord = $scopedArticle->first([
            'contents.*',
            'channels.name as channel_name',
            'channels.slug as channel_slug',
        ]);

        abort_unless($articleRecord, 404);

        $site = DB::table('sites')->where('id', (int) $articleRecord->site_id)->first();
        abort_unless($site, 404);

        $settings = $this->siteSettings($site->id);
        $themeCode = $this->frontendThemeCode($site->id);
        if ($themeCode === null) {
            return $this->renderMissingThemePage($site, true);
        }
        $channels = $this->siteNavChannels($site->id);
        $tags = new ThemeTags($site, $settings, $channels);
        $detailTemplate = $this->articleTemplate($site->id, (int) $articleRecord->id, (string) ($articleRecord->channel_slug ?? ''));
        $tags->withContext('detail', $articleRecord->channel_id ? (int) $articleRecord->channel_id : null, $detailTemplate);
        $navItems = $tags->nav()->values();

        $attachments = DB::table('attachment_relations')
            ->join('attachments', 'attachments.id', '=', 'attachment_relations.attachment_id')
            ->where('attachments.site_id', $site->id)
            ->where('attachment_relations.relation_type', 'content')
            ->where('attachment_relations.relation_id', $articleRecord->id)
            ->orderBy('attachments.id')
            ->get([
                'attachments.id',
                'attachments.origin_name',
                'attachments.extension',
                'attachments.size',
                'attachments.url',
            ])
            ->map(fn ($attachment) => [
                'id' => $attachment->id,
                'origin_name' => $attachment->origin_name,
                'extension' => $attachment->extension ?: '',
                'extension_upper' => strtoupper($attachment->extension ?: '-'),
                'size' => $attachment->size,
                'size_kb' => number_format(($attachment->size ?? 0) / 1024, 1),
                'url' => $attachment->url,
            ]);

        return $this->renderTheme($site, $themeCode, $detailTemplate, [
            'site' => $this->sitePayload($site, $settings),
            'settings' => $settings,
            'tags' => $tags,
            'navItems' => $navItems,
            'activeChannelSlug' => $articleRecord->channel_slug,
            'page' => $this->pageMeta($site, $settings, '[预览] '.$articleRecord->title.' - '.$site->name),
            'meta' => $this->metaPayload(
                $site,
                '[预览] '.(trim((string) ($articleRecord->seo_title ?? '')) !== '' ? (string) $articleRecord->seo_title : ((string) $articleRecord->title.' - '.$site->name)),
                (string) ($articleRecord->seo_keywords ?? ''),
                trim((string) ($articleRecord->seo_description ?? '')) !== '' ? (string) $articleRecord->seo_description : (string) ($articleRecord->summary ?? '')
            ),
            'article' => $this->articlePayload($articleRecord, $site),
            'attachments' => $attachments,
            'previousArticle' => $tags->previous($articleRecord),
            'nextArticle' => $tags->next($articleRecord),
            'relatedArticles' => $tags->related($articleRecord),
        ]);
    }

    public function previewPage(Request $request, string $content): Response
    {
        $accessibleSiteIds = $this->siteIdsWithPermission($request->user()->id, 'content.manage');
        abort_if($accessibleSiteIds === [], 404);

        $page = DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->whereIn('contents.site_id', $accessibleSiteIds)
            ->where('contents.id', $content)
            ->where('contents.type', 'page')
            ->whereNull('contents.deleted_at');

        $contentRecord = $page->first([
            'contents.*',
            'channels.name as channel_name',
            'channels.slug as channel_slug',
        ]);

        abort_unless($contentRecord, 404);

        $scopedPage = DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->where('contents.site_id', (int) $contentRecord->site_id)
            ->where('contents.id', $content)
            ->where('contents.type', 'page')
            ->whereNull('contents.deleted_at');
        $this->applySiteContentVisibilityScope($scopedPage, $request->user()->id, (int) $contentRecord->site_id);

        $pageRecord = $scopedPage->first([
            'contents.*',
            'channels.name as channel_name',
            'channels.slug as channel_slug',
        ]);

        abort_unless($pageRecord, 404);

        $site = DB::table('sites')->where('id', (int) $pageRecord->site_id)->first();
        abort_unless($site, 404);

        $settings = $this->siteSettings($site->id);
        $themeCode = $this->frontendThemeCode($site->id);
        if ($themeCode === null) {
            return $this->renderMissingThemePage($site, true);
        }
        $channels = $this->siteNavChannels($site->id);
        $tags = new ThemeTags($site, $settings, $channels);
        $pageTemplate = $this->pageTemplate($site->id, (int) $pageRecord->id);
        $tags->withContext('page', $pageRecord->channel_id ? (int) $pageRecord->channel_id : null, $pageTemplate);
        $navItems = $tags->nav()->values();
        $channel = (object) ['name' => $pageRecord->channel_name ?: '单页面', 'slug' => $pageRecord->channel_slug];

        return $this->renderTheme($site, $themeCode, $pageTemplate, [
            'site' => $this->sitePayload($site, $settings),
            'settings' => $settings,
            'tags' => $tags,
            'navItems' => $navItems,
            'activeChannelSlug' => $pageRecord->channel_slug,
            'page' => $this->pagePayload($pageRecord, $site, $channel),
            'meta' => $this->metaPayload(
                $site,
                '[预览] '.(trim((string) ($pageRecord->seo_title ?? '')) !== '' ? (string) $pageRecord->seo_title : ((string) $pageRecord->title.' - '.$site->name)),
                (string) ($pageRecord->seo_keywords ?? ''),
                trim((string) ($pageRecord->seo_description ?? '')) !== '' ? (string) $pageRecord->seo_description : (string) ($pageRecord->summary ?? '')
            ),
            'channel' => $this->channelPayload($channel),
        ]);
    }

    public function themeAsset(Request $request, string $theme, string $path): Response
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]+$/', $theme) === 1, 404);

        $siteKey = trim((string) $request->query('site', ''));
        abort_unless($siteKey !== '', 404);

        $site = DB::table('sites')
            ->where('site_key', $siteKey)
            ->first(['id', 'site_key', 'active_site_template_id']);

        abort_unless($site, 404);
        abort_unless($this->frontendThemeCode((int) $site->id) === $theme, 404);

        $normalizedPath = ThemeTemplateLocator::normalizeAssetPath($path);

        abort_unless($normalizedPath !== null, 404);

        $resolved = ThemeTemplateLocator::resolveAssetPath($site->site_key, $theme, $normalizedPath);
        abort_unless($resolved !== null && File::exists($resolved), 404);

        $etag = sha1($resolved.'|'.File::lastModified($resolved).'|'.File::size($resolved));
        $response = response(File::get($resolved), 200, [
            'Content-Type' => $this->themeAssetMimeType($resolved),
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => '"'.$etag.'"',
            'Last-Modified' => gmdate('D, d M Y H:i:s', File::lastModified($resolved)).' GMT',
        ]);

        if ($request->headers->get('If-None-Match') === '"'.$etag.'"') {
            $response->setStatusCode(304);
            $response->setContent(null);
        }

        return $response;
    }

    protected function themeAssetMimeType(string $path): string
    {
        return match (strtolower((string) pathinfo($path, PATHINFO_EXTENSION))) {
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            default => File::mimeType($path) ?: 'application/octet-stream',
        };
    }

    /**
     * Resolve the site ids where the user has a specific site permission.
     *
     * @return array<int, int>
     */
    protected function siteIdsWithPermission(int $userId, string $permissionCode): array
    {
        if ($this->isPlatformAdmin($userId)) {
            return DB::table('sites')
                ->pluck('id')
                ->map(fn ($siteId) => (int) $siteId)
                ->values()
                ->all();
        }

        return $this->boundSites($userId)
            ->pluck('id')
            ->map(fn ($siteId) => (int) $siteId)
            ->filter(fn (int $siteId): bool => in_array($permissionCode, $this->sitePermissionCodes($userId, $siteId), true))
            ->values()
            ->all();
    }

    protected function defaultSite(): ?object
    {
        return DB::table('sites')->orderBy('id')->first();
    }

    protected function resolvedSite(Request $request): ?object
    {
        $host = mb_strtolower(trim((string) $request->getHost()));

        if ($host !== '') {
            $site = DB::table('site_domains')
                ->join('sites', 'sites.id', '=', 'site_domains.site_id')
                ->whereRaw('LOWER(site_domains.domain) = ?', [$host])
                ->where('site_domains.status', 1)
                ->where('sites.status', 1)
                ->first(['sites.*']);

            if ($site) {
                return $site;
            }
        }

        if (! in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return null;
        }

        $siteKey = trim((string) $request->query('site', ''));

        if ($siteKey !== '') {
            $site = DB::table('sites')
                ->where('site_key', $siteKey)
                ->where('status', 1)
                ->first();

            if ($site) {
                return $site;
            }
        }

        return DB::table('sites')
            ->where('status', 1)
            ->orderBy('id')
            ->first();
    }

    protected function renderDomainUnboundPage(Request $request): Response
    {
        return response()->view('site.domain-unbound', [
            'host' => mb_strtolower(trim((string) $request->getHost())),
        ]);
    }

    protected function siteSettings(int $siteId)
    {
        return DB::table('site_settings')
            ->where('site_id', $siteId)
            ->pluck('setting_value', 'setting_key');
    }

    protected function siteNavChannels(int $siteId)
    {
        return DB::table('channels')
            ->where('site_id', $siteId)
            ->where('is_nav', 1)
            ->where('status', 1)
            ->orderBy('sort')
            ->orderBy('id')
            ->limit(8)
            ->get(['id', 'name', 'slug', 'type', 'link_url', 'link_target']);
    }

    protected function themeCode(int $siteId): string
    {
        return $this->siteThemeCode($siteId);
    }

    protected function frontendThemeCode(int $siteId): ?string
    {
        $themeCode = $this->siteThemeCode($siteId);

        return $themeCode !== '' ? $themeCode : null;
    }

    protected function renderMissingThemePage(object $site, bool $isPreview = false): Response
    {
        return response()->view('site.theme-missing', [
            'site' => $site,
            'isPreview' => $isPreview,
        ], 503);
    }

    protected function renderTheme(object $site, string $themeCode, string $template, array $payload): Response
    {
        abort_unless(file_exists(ThemeTemplateLocator::resolvePath($site->site_key, $themeCode, $template)), 404);

        $payload['csrfToken'] = csrf_token();
        $payload['current'] = $this->themeCurrentPayload($payload);
        $engine = new ThemeTemplateEngine(SitePath::key($site), $themeCode, $payload['tags']);

        try {
            $html = $engine->render($template, $payload);

            return response($this->injectSharedFrontendAssets($html));
        } catch (ThemeTemplateException $exception) {
            Log::error('Theme template render failed.', [
                'site_id' => $site->id,
                'site_key' => $site->site_key,
                'template_key' => $themeCode,
                'template' => $template,
                'message' => $exception->getMessage(),
            ]);

            return response()->view('site.theme-error', [
                'site' => $site,
                'template' => $template,
                'message' => $exception->getMessage(),
            ], 503);
        }
    }

    protected function injectSharedFrontendAssets(string $html): string
    {
        $assetTag = sprintf('<link rel="stylesheet" href="%s">', asset('css/site-content-render.css'));

        if (str_contains($html, $assetTag)) {
            return $html;
        }

        if (stripos($html, '</head>') !== false) {
            return preg_replace('/<\/head>/i', $assetTag."\n</head>", $html, 1) ?? $html;
        }

        return $assetTag."\n".$html;
    }

    protected function channelDetailTemplate(int $siteId, string $channelSlug): string
    {
        return DB::table('channels')
            ->where('site_id', $siteId)
            ->where('slug', $channelSlug)
            ->value('detail_template') ?: 'detail';
    }

    protected function articleTemplate(int $siteId, int $articleId, string $channelSlug = ''): string
    {
        $template = DB::table('contents')
            ->where('site_id', $siteId)
            ->where('id', $articleId)
            ->value('template_name');

        if (is_string($template) && trim($template) !== '') {
            return trim($template);
        }

        return $channelSlug !== '' ? $this->channelDetailTemplate($siteId, $channelSlug) : 'detail';
    }

    protected function pageTemplate(int $siteId, int $pageId): string
    {
        return DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->where('contents.site_id', $siteId)
            ->where('contents.id', $pageId)
            ->value(DB::raw("COALESCE(NULLIF(contents.template_name, ''), NULLIF(channels.detail_template, ''), 'page')")) ?: 'page';
    }

    /**
     * @return array<int, int>
     */
    protected function channelAndDescendantLeafIds(int $siteId, int $channelId): array
    {
        $channels = DB::table('channels')
            ->where('site_id', $siteId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'parent_id']);

        $childrenByParent = $channels->groupBy(fn (object $channel): int => (int) ($channel->parent_id ?? 0));
        $leafIds = [];

        $walk = function (int $parentId) use (&$walk, $childrenByParent, &$leafIds): void {
            $children = $childrenByParent->get($parentId, collect())->values();

            if ($children->isEmpty()) {
                $leafIds[] = $parentId;
                return;
            }

            foreach ($children as $child) {
                $walk((int) $child->id);
            }
        };

        $walk($channelId);

        return array_values(array_unique(array_map('intval', $leafIds)));
    }

    protected function sitePayload(object $site, $settings): array
    {
        return [
            'id' => $site->id,
            'name' => $site->name,
            'site_key' => $site->site_key,
            'logo' => $site->logo,
            'favicon' => $site->favicon,
            'contact_phone' => $site->contact_phone,
            'contact_email' => $site->contact_email,
            'address' => $site->address,
            'seo_title' => $site->seo_title,
            'seo_keywords' => $site->seo_keywords,
            'seo_description' => $site->seo_description,
            'remark' => $site->remark,
            'filing_number' => $settings->get('site.filing_number', ''),
            'icp_no' => $settings->get('site.filing_number', ''),
        ];
    }

    protected function pageMeta(object $site, $settings, ?string $title = null): array
    {
        return [
            'title' => $title ?: ($site->seo_title ?: $site->name),
        ];
    }

    protected function metaPayload(
        object $site,
        ?string $title = null,
        ?string $keywords = null,
        ?string $description = null
    ): array {
        $siteName = trim((string) ($site->name ?? ''));
        $siteSeoTitle = trim((string) ($site->seo_title ?? ''));
        $siteSeoKeywords = trim((string) ($site->seo_keywords ?? ''));
        $siteSeoDescription = trim((string) ($site->seo_description ?? ''));

        $resolvedTitle = trim((string) ($title ?? ''));
        if ($resolvedTitle === '') {
            $resolvedTitle = $siteSeoTitle !== '' ? $siteSeoTitle : $siteName;
        }

        $resolvedKeywords = trim((string) ($keywords ?? ''));
        if ($resolvedKeywords === '') {
            $resolvedKeywords = $siteSeoKeywords;
        }

        $resolvedDescription = trim((string) ($description ?? ''));
        if ($resolvedDescription === '') {
            $resolvedDescription = $siteSeoDescription !== '' ? $siteSeoDescription : $siteName;
        }

        return [
            'title' => $resolvedTitle,
            'keywords' => $resolvedKeywords,
            'description' => $resolvedDescription,
        ];
    }

    protected function themeCurrentPayload(array $payload): array
    {
        $tags = $payload['tags'] ?? null;
        $base = is_object($tags) && method_exists($tags, 'current')
            ? $tags->current()
            : [];

        $content = $payload['article'] ?? $payload['page'] ?? null;

        $base['content'] = is_array($content)
            ? [
                'id' => $content['id'] ?? null,
                'title' => $content['title'] ?? '',
                'view_count' => $content['view_count'] ?? 0,
                'url' => $content['url'] ?? '',
                'channel_id' => $content['channel_id'] ?? null,
                'type' => $content['type'] ?? (isset($payload['article']) ? 'article' : (isset($payload['page']) ? 'page' : '')),
            ]
            : [
                'id' => null,
                'title' => '',
                'view_count' => 0,
                'url' => '',
                'channel_id' => null,
                'type' => '',
            ];

        return $base;
    }

    protected function channelPayload(object $channel): array
    {
        return [
            'id' => $channel->id ?? null,
            'name' => $channel->name ?? '',
            'slug' => $channel->slug ?? '',
            'type' => $channel->type ?? 'list',
            'link_url' => $channel->link_url ?? '',
            'link_target' => $channel->link_target ?? '_self',
        ];
    }

    protected function articleListPayload(object $item, object $site, object $channel): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'title_color' => $item->title_color ?? '',
            'title_bold' => (bool) ($item->title_bold ?? false),
            'title_italic' => (bool) ($item->title_italic ?? false),
            'is_recommend' => (bool) ($item->is_recommend ?? false),
            'summary' => $item->summary,
            'published_at' => $item->published_at,
            'channel_name' => $channel->name,
            'channel_slug' => $channel->slug,
            'url' => route('site.article', ['id' => $item->id] + $this->frontendRouteParameters($site)),
        ];
    }

    protected function articlePayload(object $article, object $site): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'view_count' => (int) ($article->view_count ?? 0),
            'title_color' => $article->title_color ?? '',
            'title_bold' => (bool) ($article->title_bold ?? false),
            'title_italic' => (bool) ($article->title_italic ?? false),
            'is_recommend' => (bool) ($article->is_recommend ?? false),
            'summary' => $article->summary,
            'content_html' => EmbeddedContentRenderer::render($article->content ?: ''),
            'channel_id' => $article->channel_id !== null ? (int) $article->channel_id : null,
            'published_at' => $article->published_at,
            'author' => $article->author ?: '本站编辑',
            'channel_name' => $article->channel_name ?: '新闻资讯',
            'channel_slug' => $article->channel_slug,
            'type' => 'article',
            'url' => route('site.article', ['id' => $article->id] + $this->frontendRouteParameters($site)),
        ];
    }

    protected function pagePayload(object $page, object $site, object $channel): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'view_count' => (int) ($page->view_count ?? 0),
            'title_color' => $page->title_color ?? '',
            'title_bold' => (bool) ($page->title_bold ?? false),
            'title_italic' => (bool) ($page->title_italic ?? false),
            'summary' => $page->summary,
            'content_html' => EmbeddedContentRenderer::render($page->content ?: ''),
            'channel_id' => $page->channel_id !== null ? (int) $page->channel_id : null,
            'published_at' => $page->published_at ?? null,
            'channel_name' => $channel->name ?? '单页面',
            'channel_slug' => $page->channel_slug ?? ($channel->slug ?? null),
            'type' => 'page',
            'url' => route('site.page', ['id' => $page->id] + $this->frontendRouteParameters($site)),
        ];
    }

    protected function frontendRouteParameters(object $site): array
    {
        $host = mb_strtolower(trim((string) request()->getHost()));

        if (in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return ['site' => $site->site_key];
        }

        return [];
    }

    protected function recordSiteVisit(int $siteId, string $type, ?int $contentId = null): void
    {
        $statDate = now('Asia/Shanghai')->toDateString();
        $now = now();

        DB::table('site_visit_daily_stats')->insertOrIgnore([
            'site_id' => $siteId,
            'stat_date' => $statDate,
            'page_views' => 0,
            'article_views' => 0,
            'channel_views' => 0,
            'home_views' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $updates = [
            'page_views' => DB::raw('page_views + 1'),
            'updated_at' => $now,
        ];

        if ($type === 'article') {
            $updates['article_views'] = DB::raw('article_views + 1');
        }

        if ($type === 'channel') {
            $updates['channel_views'] = DB::raw('channel_views + 1');
        }

        if ($type === 'home') {
            $updates['home_views'] = DB::raw('home_views + 1');
        }

        DB::table('site_visit_daily_stats')
            ->where('site_id', $siteId)
            ->where('stat_date', $statDate)
            ->update($updates);

        if ($type === 'article' && $contentId !== null) {
            DB::table('contents')
                ->where('site_id', $siteId)
                ->where('id', $contentId)
                ->increment('view_count');
        }
    }
}
