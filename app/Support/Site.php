<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class Site
{
    /**
     * @var array<string, int>
     */
    protected static array $siteIdsByKey = [];

    public static function key(int|object|string $site): string
    {
        if (is_string($site)) {
            return trim($site);
        }

        if (is_object($site) && isset($site->site_key)) {
            return trim((string) $site->site_key);
        }

        if (is_object($site) && isset($site->id)) {
            return static::resolveKeyById((int) $site->id);
        }

        if (is_int($site)) {
            return static::resolveKeyById($site);
        }

        throw new InvalidArgumentException('无法解析站点标识。');
    }

    public static function rootRelative(int|object|string $site): string
    {
        return 'web/'.static::key($site);
    }

    public static function root(int|object|string $site): string
    {
        return storage_path('app/'.static::rootRelative($site));
    }

    public static function mediaRelative(int|object|string $site, string $suffix = ''): string
    {
        return static::joinSegments(static::rootRelative($site), 'media', $suffix);
    }

    public static function brandMediaRelative(int|object|string $site, string $suffix = ''): string
    {
        return static::joinSegments(static::rootRelative($site), 'media/brand', $suffix);
    }

    public static function attachmentRelative(int|object|string $site, string $suffix = ''): string
    {
        return static::joinSegments(static::rootRelative($site), 'media/attachments', $suffix);
    }

    public static function mediaUrl(int|object|string $site, string $suffix = ''): string
    {
        return url('/site-media/'.static::key($site).'/'.ltrim($suffix, '/'));
    }

    public static function attachmentUrl(int|object|string $site, string $suffix = ''): string
    {
        $siteKey = static::key($site);
        $path = static::attachmentPublicPath($suffix);
        $url = url($path);

        if (static::shouldAppendLocalSiteQuery($url)) {
            return static::appendUrlQuery($url, ['site' => $siteKey]);
        }

        $siteId = static::resolveId($site);
        $domain = $siteId > 0 ? SiteFrontendUrl::primaryDomain($siteId) : null;

        if (is_string($domain) && $domain !== '') {
            return rtrim(request()->getScheme().'://'.$domain, '/').$path;
        }

        return url('/site-media/'.$siteKey.'/attachments/'.ltrim($suffix, '/'));
    }

    public static function attachmentPublicPath(string $suffix = ''): string
    {
        return '/atts/'.ltrim($suffix, '/');
    }

    public static function storedAttachmentUrlForPath(string $path): string
    {
        $normalized = trim($path, '/');

        if (preg_match('#^web/[^/]+/media/attachments/(.+)$#', $normalized, $matches)) {
            return static::attachmentPublicPath($matches[1]);
        }

        return url('/'.$normalized);
    }

    public static function urlForStoredPath(string $path): string
    {
        $normalized = trim($path, '/');

        if (preg_match('#^web/([^/]+)/media/attachments/(.+)$#', $normalized, $matches)) {
            return static::attachmentUrl($matches[1], $matches[2]);
        }

        if (preg_match('#^web/([^/]+)/media/(.+)$#', $normalized, $matches)) {
            return url('/site-media/'.$matches[1].'/'.$matches[2]);
        }

        return url('/'.$normalized);
    }

    public static function versionedMediaUrl(?string $url): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return $url;
        }

        $absolutePath = null;

        if (preg_match('#^/site-media/([^/]+)/(.+)$#', $path, $matches) === 1) {
            $absolutePath = static::mediaAbsolutePath($matches[1], $matches[2]);
        } elseif (preg_match('#^/atts/(.+)$#', $path, $matches) === 1) {
            $siteKey = trim((string) parse_url($url, PHP_URL_QUERY));
            parse_str($siteKey, $query);
            $resolvedSiteKey = trim((string) ($query['site'] ?? ''));
            if ($resolvedSiteKey !== '') {
                $absolutePath = static::mediaAbsolutePath($resolvedSiteKey, 'attachments/'.$matches[1]);
            }
        }

        if (! is_string($absolutePath) || ! is_file($absolutePath)) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'v='.filemtime($absolutePath);
    }

    public static function mediaAbsolutePath(int|object|string $site, string $suffix = ''): string
    {
        return storage_path('app/'.static::mediaRelative($site, $suffix));
    }

    public static function mediaRelativeFromPath(string $path): ?string
    {
        $normalized = trim($path, '/');

        if (preg_match('#^web/[^/]+/media/(.+)$#', $normalized, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function themeOverrideRoot(int|object|string $site, string $themeCode): string
    {
        return static::root($site).DIRECTORY_SEPARATOR.'theme'.DIRECTORY_SEPARATOR.$themeCode;
    }

    public static function siteTemplateRoot(int|object|string $site, string $templateKey): string
    {
        return static::themeOverrideRoot($site, $templateKey);
    }

    protected static function resolveKeyById(int $siteId): string
    {
        $siteKey = DB::table('sites')->where('id', $siteId)->value('site_key');

        if (! is_string($siteKey) || trim($siteKey) === '') {
            throw new InvalidArgumentException('当前站点缺少有效站点标识。');
        }

        return trim($siteKey);
    }

    protected static function resolveId(int|object|string $site): int
    {
        if (is_int($site)) {
            return $site;
        }

        if (is_object($site) && isset($site->id)) {
            return (int) $site->id;
        }

        if (is_string($site) && trim($site) !== '') {
            $siteKey = trim($site);

            if (! array_key_exists($siteKey, static::$siteIdsByKey)) {
                static::$siteIdsByKey[$siteKey] = (int) DB::table('sites')
                    ->where('site_key', $siteKey)
                    ->value('id');
            }

            return static::$siteIdsByKey[$siteKey];
        }

        return 0;
    }

    protected static function joinSegments(string ...$segments): string
    {
        return collect($segments)
            ->map(fn (string $segment) => trim($segment, '/'))
            ->filter(fn (string $segment) => $segment !== '')
            ->implode('/');
    }

    protected static function shouldAppendLocalSiteQuery(string $url): bool
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, ['127.0.0.1', 'localhost'], true);
    }

    protected static function appendUrlQuery(string $url, array $query): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($query);
    }
}
