<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SiteFrontendUrl
{
    /**
     * @var array<int, string|null>
     */
    protected static array $primaryDomains = [];

    public static function homeUrl(object $site): string
    {
        return static::urlForSitePath($site, route('site.home', absolute: false));
    }

    public static function channelUrl(object $site, string $slug): string
    {
        return static::urlForSitePath($site, route('site.channel', ['slug' => $slug], absolute: false));
    }

    public static function articleUrl(object $site, int|string $articleId): string
    {
        return static::urlForSitePath($site, route('site.article', ['id' => $articleId], absolute: false));
    }

    public static function contentPreviewUrl(object $site, string $type, int|string $contentId): string
    {
        $normalizedType = $type === 'page' ? 'page' : 'article';

        if (! static::canUseCurrentHostForSite($site)) {
            return '';
        }

        $route = $normalizedType === 'page'
            ? route('admin.content-preview.page', ['content' => $contentId], absolute: false)
            : route('admin.content-preview.article', ['content' => $contentId], absolute: false);

        return static::urlForCurrentHostPath($site, $route);
    }

    public static function guestbookUrl(object $site): string
    {
        return static::urlForSitePath($site, route('site.guestbook.index', absolute: false));
    }

    public static function absolutizeSiteAssetUrl(object $site, string $url): string
    {
        $url = trim($url);

        if ($url === '' || preg_match('#^(?:https?:)?//#i', $url) === 1 || ! str_starts_with($url, '/')) {
            return $url;
        }

        return static::urlForSitePath($site, $url);
    }

    protected static function urlForSitePath(object $site, string $path): string
    {
        $host = mb_strtolower(trim((string) request()->getHost()));

        if (in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return static::appendSiteQuery($path, (string) $site->site_key);
        }

        $domain = static::primaryDomain((int) $site->id);

        if ($domain !== null) {
            $scheme = request()->getScheme();

            return rtrim($scheme.'://'.$domain, '/').$path;
        }

        return static::appendSiteQuery($path, (string) $site->site_key);
    }

    protected static function appendSiteQuery(string $path, string $siteKey): string
    {
        $separator = str_contains($path, '?') ? '&' : '?';

        return $path.$separator.'site='.$siteKey;
    }

    protected static function urlForCurrentHostPath(object $site, string $path): string
    {
        $host = mb_strtolower(trim((string) request()->getHost()));

        if (in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return static::appendSiteQuery($path, (string) $site->site_key);
        }

        return rtrim(request()->getSchemeAndHttpHost(), '/').$path;
    }

    public static function primaryDomain(int $siteId): ?string
    {
        if (array_key_exists($siteId, static::$primaryDomains)) {
            return static::$primaryDomains[$siteId];
        }

        $domain = DB::table('site_domains')
            ->where('site_id', $siteId)
            ->where('status', 1)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->value('domain');

        $domain = is_string($domain) && trim($domain) !== ''
            ? trim($domain)
            : null;

        static::$primaryDomains[$siteId] = $domain;

        return $domain;
    }

    protected static function canUseCurrentHostForSite(object $site): bool
    {
        $host = mb_strtolower(trim((string) request()->getHost()));

        if (in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return true;
        }

        return DB::table('site_domains')
            ->where('site_id', (int) $site->id)
            ->where('status', 1)
            ->whereRaw('LOWER(domain) = ?', [$host])
            ->exists();
    }
}
