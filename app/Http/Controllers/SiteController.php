<?php

namespace App\Http\Controllers;

use App\Support\EmbeddedContentRenderer;
use App\Support\FrontendContent;
use App\Support\FrontendPageCache;
use App\Support\FrontendDevice;
use App\Support\Site as SitePath;
use App\Support\SiteBackendAccess;
use App\Support\ThemeTags;
use App\Support\ThemeTemplateEngine;
use App\Support\ThemeTemplateException;
use App\Support\ThemeTemplateLocator;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SiteController extends Controller
{
    protected const THEME_ASSET_BASE_CACHE_TTL_SECONDS = 86400;

    /**
     * Render the default site homepage with the active theme.
     */
    public function show(Request $request): Response
    {
        $site = $this->resolvedSite($request);
        if (! $site) {
            return $this->renderDomainUnboundPage($request);
        }
        if ($disabled = $this->renderWhenFrontendDisabled($site)) {
            return $disabled;
        }
        if ($cached = $this->cachedFrontendPageResponse($request, $site)) {
            return $cached;
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
        if ($disabled = $this->renderWhenFrontendDisabled($site)) {
            return $disabled;
        }
        if ($cached = $this->cachedFrontendPageResponse($request, $site)) {
            return $cached;
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
            abort_unless($this->isSafeFrontendExternalUrl((string) $channel->link_url), 404);

            return redirect()->away($channel->link_url);
        }

        if ($channel->type === 'page') {
            $page = FrontendContent::visibleQuery((int) $site->id, 'page')
                ->whereExists(function ($query) use ($channel): void {
                    $query->selectRaw('1')
                        ->from('content_channels')
                        ->whereColumn('content_channels.content_id', 'contents.id')
                        ->where('content_channels.channel_id', $channel->id);
                })
                ->orderByDesc('sort')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
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
        $showAll = in_array(strtolower(trim((string) $request->query('all', '0'))), ['1', 'true', 'yes'], true);
        $pageTitle = $showAll ? '全部内容' : (string) $channel->name;

        $items = FrontendContent::visibleQuery((int) $site->id, 'article')
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
            'page' => $this->pageMeta($site, $settings, $pageTitle.' - '.$site->name),
            'meta' => $this->metaPayload($site, $pageTitle.' - '.$site->name),
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
        if ($disabled = $this->renderWhenFrontendDisabled($site)) {
            return $disabled;
        }
        if ($cached = $this->cachedFrontendPageResponse($request, $site)) {
            return $cached;
        }
        $settings = $this->siteSettings($site->id);
        $themeCode = $this->frontendThemeCode($site->id);
        if ($themeCode === null) {
            return $this->renderMissingThemePage($site);
        }
        $channels = $this->siteNavChannels($site->id);
        $tags = new ThemeTags($site, $settings, $channels);

        $article = FrontendContent::visibleQuery((int) $site->id, 'article')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->where('contents.id', $id)
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
                'attachments.path',
            ])
            ->map(fn ($attachment) => [
                'id' => $attachment->id,
                'origin_name' => $attachment->origin_name,
                'extension' => $attachment->extension ?: '',
                'extension_upper' => strtoupper($attachment->extension ?: '-'),
                'size' => $attachment->size,
                'size_kb' => number_format(($attachment->size ?? 0) / 1024, 1),
                'url' => trim((string) ($attachment->path ?? '')) !== ''
                    ? SitePath::urlForStoredPath((string) $attachment->path)
                    : $attachment->url,
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
        if ($disabled = $this->renderWhenFrontendDisabled($site)) {
            return $disabled;
        }
        if ($cached = $this->cachedFrontendPageResponse($request, $site)) {
            return $cached;
        }
        $this->recordSiteVisit((int) $site->id, 'page');
        $settings = $this->siteSettings($site->id);
        $themeCode = $this->frontendThemeCode($site->id);
        if ($themeCode === null) {
            return $this->renderMissingThemePage($site);
        }
        $channels = $this->siteNavChannels($site->id);
        $tags = new ThemeTags($site, $settings, $channels);

        $page = FrontendContent::visibleQuery((int) $site->id, 'page')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->where('contents.id', $id)
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
        $article = DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->where('contents.id', $content)
            ->where('contents.type', 'article')
            ->whereNull('contents.deleted_at');

        $contentRecord = $article->first([
            'contents.*',
            'channels.name as channel_name',
            'channels.slug as channel_slug',
        ]);

        abort_unless($contentRecord, 404);
        $this->authorizeSite($request, (int) $contentRecord->site_id, 'content.manage');

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
                'attachments.path',
            ])
            ->map(fn ($attachment) => [
                'id' => $attachment->id,
                'origin_name' => $attachment->origin_name,
                'extension' => $attachment->extension ?: '',
                'extension_upper' => strtoupper($attachment->extension ?: '-'),
                'size' => $attachment->size,
                'size_kb' => number_format(($attachment->size ?? 0) / 1024, 1),
                'url' => trim((string) ($attachment->path ?? '')) !== ''
                    ? SitePath::urlForStoredPath((string) $attachment->path)
                    : $attachment->url,
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
        $page = DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->where('contents.id', $content)
            ->where('contents.type', 'page')
            ->whereNull('contents.deleted_at');

        $contentRecord = $page->first([
            'contents.*',
            'channels.name as channel_name',
            'channels.slug as channel_slug',
        ]);

        abort_unless($contentRecord, 404);
        $this->authorizeSite($request, (int) $contentRecord->site_id, 'content.manage');

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

    public function themeAsset(Request $request, string $theme, string $path): SymfonyResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]+$/', $theme) === 1, 404);

        $assetBase = $this->resolveThemeAssetBase($request);
        abort_unless(is_string($assetBase) && $assetBase !== '', 404);

        $normalizedPath = ThemeTemplateLocator::normalizeAssetPath($path);
        abort_unless($normalizedPath !== null, 404);
        abort_unless(! $this->themeAssetPathHasHiddenSegments($normalizedPath), 404);

        $themeRoot = storage_path('app/'.$assetBase.DIRECTORY_SEPARATOR.$theme);
        $resolvedThemeRoot = realpath($themeRoot);
        abort_unless(is_string($resolvedThemeRoot) && File::isDirectory($resolvedThemeRoot), 404);

        $resolved = realpath($resolvedThemeRoot.DIRECTORY_SEPARATOR.$normalizedPath);
        abort_unless(is_string($resolved) && File::isFile($resolved), 404);
        abort_unless($this->themeAssetPathWithinRoot($resolvedThemeRoot, $resolved), 404);

        $etag = sha1($resolved.'|'.File::lastModified($resolved).'|'.File::size($resolved));
        $response = response()->file($resolved, [
            'Content-Type' => $this->themeAssetMimeType($resolved),
            'Cache-Control' => 'public, max-age=3600',
        ]);
        $response->setPublic();
        $response->setMaxAge(3600);
        $response->setEtag($etag);
        $response->setLastModified((new DateTimeImmutable())->setTimestamp(File::lastModified($resolved)));
        $response->isNotModified($request);

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

    protected function resolveThemeAssetBase(Request $request): ?string
    {
        $host = mb_strtolower(trim((string) $request->getHost()));

        if ($host !== '' && ! in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $cacheKey = $this->themeAssetBaseCacheKey($host);
            $cachedBase = Cache::get($cacheKey);

            if (is_string($cachedBase) && $cachedBase !== '') {
                return $cachedBase;
            }

            $siteKey = DB::table('site_domains')
                ->join('sites', 'sites.id', '=', 'site_domains.site_id')
                ->whereRaw('LOWER(site_domains.domain) = ?', [$host])
                ->where('site_domains.status', 1)
                ->where('sites.status', 1)
                ->value('sites.site_key');

            if (! is_string($siteKey) || trim($siteKey) === '') {
                return null;
            }

            $assetBase = SitePath::rootRelative(trim($siteKey)).'/theme';
            Cache::put($cacheKey, $assetBase, now()->addSeconds(self::THEME_ASSET_BASE_CACHE_TTL_SECONDS));

            return $assetBase;
        }

        if (! in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return null;
        }

        $siteKey = trim((string) $request->query('site', ''));
        if ($siteKey === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9\\-]*$/', $siteKey) !== 1) {
            return null;
        }

        $cacheKey = $this->themeAssetBaseCacheKey('local', $siteKey);
        $cachedBase = Cache::get($cacheKey);

        if (is_string($cachedBase) && $cachedBase !== '') {
            return $cachedBase;
        }

        $resolvedSiteKey = DB::table('sites')
            ->where('site_key', $siteKey)
            ->where('status', 1)
            ->value('site_key');

        if (! is_string($resolvedSiteKey) || trim($resolvedSiteKey) === '') {
            return null;
        }

        $assetBase = SitePath::rootRelative(trim($resolvedSiteKey)).'/theme';
        Cache::put($cacheKey, $assetBase, now()->addSeconds(self::THEME_ASSET_BASE_CACHE_TTL_SECONDS));

        return $assetBase;
    }

    protected function themeAssetBaseCacheKey(string $host, ?string $siteKey = null): string
    {
        $host = mb_strtolower(trim($host));
        $suffix = $siteKey !== null && trim($siteKey) !== '' ? ':'.mb_strtolower(trim($siteKey)) : '';

        return 'theme-asset-base:'.$host.$suffix;
    }

    protected function themeAssetPathHasHiddenSegments(string $path): bool
    {
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || str_starts_with($segment, '.')) {
                return true;
            }
        }

        return false;
    }

    protected function themeAssetPathWithinRoot(string $root, string $path): bool
    {
        $normalizedRoot = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $normalizedPath = rtrim($path, DIRECTORY_SEPARATOR);

        return str_starts_with($normalizedPath, $normalizedRoot);
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

        $siteBackendAccess = app(SiteBackendAccess::class);

        return $this->boundSites($userId)
            ->filter(fn (object $site): bool => in_array($permissionCode, $this->sitePermissionCodes($userId, (int) $site->id), true)
                && $siteBackendAccess->status($site)['allowed'])
            ->pluck('id')
            ->map(fn ($siteId) => (int) $siteId)
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

    protected function renderWhenFrontendDisabled(object $site): ?Response
    {
        $enabled = DB::table('site_settings')
            ->where('site_id', (int) $site->id)
            ->where('setting_key', 'site.frontend_enabled')
            ->value('setting_value');

        if ($enabled === null || $enabled === '1') {
            return null;
        }

        return response()->view('site.site-closed', [
            'site' => $site,
        ], 503);
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

    protected function cachedFrontendPageResponse(Request $request, object $site): ?Response
    {
        if (! FrontendPageCache::shouldUse($request)) {
            return null;
        }

        $cacheKey = FrontendPageCache::key($site, $request, FrontendDevice::mode($request));
        $cachedHtml = FrontendPageCache::get($cacheKey);

        if ($cachedHtml === null) {
            return null;
        }

        return response($cachedHtml)->header('X-Frontend-Page-Cache', 'HIT');
    }

    protected function renderTheme(object $site, string $themeCode, string $template, array $payload): Response
    {
        $request = request();
        $device = FrontendDevice::mode($request);
        $template = $this->resolveResponsiveTemplate($site, $themeCode, $template, $request);

        abort_unless(file_exists(ThemeTemplateLocator::resolvePath($site->site_key, $themeCode, $template)), 404);

        $payload['csrfToken'] = csrf_token();
        $payload['device'] = $device;

        if (($payload['tags'] ?? null) instanceof ThemeTags) {
            $payload['tags']->withTemplateName($template);
        }

        $payload['current'] = $this->themeCurrentPayload($payload);
        $engine = new ThemeTemplateEngine(SitePath::key($site), $themeCode, $payload['tags']);
        $cacheKey = FrontendPageCache::shouldUse($request)
            ? FrontendPageCache::key($site, $request, $device)
            : null;

        if ($cacheKey !== null && ($cachedHtml = FrontendPageCache::get($cacheKey)) !== null) {
            return response($cachedHtml)->header('X-Frontend-Page-Cache', 'HIT');
        }

        try {
            $html = $this->injectSharedFrontendAssets($engine->render($template, $payload), $template);

            if ($cacheKey !== null && FrontendPageCache::canStoreHtml($html)) {
                FrontendPageCache::put($cacheKey, $html);
            }

            return response($html)->header('X-Frontend-Page-Cache', $cacheKey !== null ? 'MISS' : 'BYPASS');
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

    protected function injectSharedFrontendAssets(string $html, string $template): string
    {
        if (in_array($template, ['home', 'm-home'], true)) {
            return $html;
        }

        $assetTag = sprintf('<link rel="stylesheet" href="%s">', asset('css/site-content-render.css'));

        if (str_contains($html, $assetTag)) {
            return $html;
        }

        if (stripos($html, '</head>') !== false) {
            return preg_replace('/<\/head>/i', $assetTag."\n</head>", $html, 1) ?? $html;
        }

        return $assetTag."\n".$html;
    }

    protected function resolveResponsiveTemplate(object $site, string $themeCode, string $template, Request $request): string
    {
        $template = trim($template) !== '' ? trim($template) : 'home';

        if (FrontendDevice::mode($request) !== 'mobile' || str_starts_with($template, 'm-')) {
            return $template;
        }

        $mobileTemplate = 'm-'.$template;

        if ($this->siteThemeTemplateExists($site, $themeCode, $mobileTemplate)) {
            return $mobileTemplate;
        }

        $defaultMobileTemplate = $this->defaultMobileTemplateFor($template);

        if ($defaultMobileTemplate !== null && $this->siteThemeTemplateExists($site, $themeCode, $defaultMobileTemplate)) {
            return $defaultMobileTemplate;
        }

        return $template;
    }

    protected function defaultMobileTemplateFor(string $template): ?string
    {
        return match (true) {
            $template === 'home' => 'm-home',
            $template === 'list', str_starts_with($template, 'list-') => 'm-list',
            $template === 'detail', str_starts_with($template, 'detail-') => 'm-detail',
            $template === 'page', str_starts_with($template, 'page-') => 'm-page',
            default => null,
        };
    }

    protected function siteThemeTemplateExists(object $site, string $themeCode, string $template): bool
    {
        return file_exists(ThemeTemplateLocator::resolvePath($site->site_key, $themeCode, $template));
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

        if (is_string($template) && trim($template) !== '' && trim($template) !== 'detail') {
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
            'logo' => SitePath::versionedMediaUrl($site->logo),
            'favicon' => SitePath::versionedMediaUrl($site->favicon) ?: 'data:,',
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
        $device = (string) ($payload['device'] ?? 'pc');

        $base['page'] = is_array($base['page'] ?? null) ? $base['page'] : [];
        $base['page']['device'] = $device;
        $base['page']['is_mobile'] = $device === 'mobile';

        $content = $payload['article'] ?? $payload['page'] ?? null;

        $base['content'] = is_array($content)
            ? [
                'id' => $content['id'] ?? null,
                'title' => $content['title'] ?? '',
                'view_count' => $content['view_count'] ?? 0,
                'url' => $content['url'] ?? '',
                'channel_id' => $content['channel_id'] ?? null,
                'type' => $content['type'] ?? (isset($payload['article']) ? 'article' : (isset($payload['page']) ? 'page' : '')),
                'summary' => $content['summary'] ?? '',
                'content_html' => $content['content_html'] ?? new HtmlString(''),
                'cover_image' => $content['cover_image'] ?? '',
                'author' => $content['author'] ?? '',
                'source' => $content['source'] ?? '',
                'published_at' => $content['published_at'] ?? null,
                'updated_at' => $content['updated_at'] ?? null,
                'channel_name' => $content['channel_name'] ?? '',
                'channel_slug' => $content['channel_slug'] ?? '',
                'title_color' => $content['title_color'] ?? '',
                'title_bold' => (bool) ($content['title_bold'] ?? false),
                'title_italic' => (bool) ($content['title_italic'] ?? false),
                'is_top' => (bool) ($content['is_top'] ?? false),
                'is_recommend' => (bool) ($content['is_recommend'] ?? false),
            ]
            : [
                'id' => null,
                'title' => '',
                'view_count' => 0,
                'url' => '',
                'channel_id' => null,
                'type' => '',
                'summary' => '',
                'content_html' => new HtmlString(''),
                'cover_image' => '',
                'author' => '',
                'source' => '',
                'published_at' => null,
                'updated_at' => null,
                'channel_name' => '',
                'channel_slug' => '',
                'title_color' => '',
                'title_bold' => false,
                'title_italic' => false,
                'is_top' => false,
                'is_recommend' => false,
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
            'is_top' => (bool) ($article->is_top ?? false),
            'is_recommend' => (bool) ($article->is_recommend ?? false),
            'summary' => $article->summary,
            'content_html' => new HtmlString(EmbeddedContentRenderer::render($article->content ?: '')),
            'cover_image' => $article->cover_image ?? '',
            'channel_id' => $article->channel_id !== null ? (int) $article->channel_id : null,
            'published_at' => $article->published_at,
            'updated_at' => $article->updated_at ?? null,
            'author' => $article->author ?: '本站编辑',
            'source' => $article->source ?? '',
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
            'content_html' => new HtmlString(EmbeddedContentRenderer::render($page->content ?: '')),
            'cover_image' => $page->cover_image ?? '',
            'channel_id' => $page->channel_id !== null ? (int) $page->channel_id : null,
            'published_at' => $page->published_at ?? null,
            'updated_at' => $page->updated_at ?? null,
            'author' => $page->author ?: '',
            'source' => $page->source ?? '',
            'channel_name' => $channel->name ?? '单页面',
            'channel_slug' => $page->channel_slug ?? ($channel->slug ?? null),
            'type' => 'page',
            'url' => route('site.page', ['id' => $page->id] + $this->frontendRouteParameters($site)),
        ];
    }

    protected function frontendRouteParameters(object $site): array
    {
        $host = mb_strtolower(trim((string) request()->getHost()));
        $parameters = [];

        if (in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $parameters['site'] = $site->site_key;
        }

        $forcedDevice = FrontendDevice::forced(request());
        if ($forcedDevice !== null) {
            $parameters['device'] = $forcedDevice;
        }

        return $parameters;
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

    protected function isSafeFrontendExternalUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || Str::startsWith($url, '//')) {
            return false;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
