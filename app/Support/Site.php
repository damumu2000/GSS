<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class Site
{
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

    public static function urlForStoredPath(string $path): string
    {
        $normalized = trim($path, '/');

        if (preg_match('#^web/([^/]+)/media/(.+)$#', $normalized, $matches)) {
            return url('/site-media/'.$matches[1].'/'.$matches[2]);
        }

        return url('/'.$normalized);
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

    protected static function joinSegments(string ...$segments): string
    {
        return collect($segments)
            ->map(fn (string $segment) => trim($segment, '/'))
            ->filter(fn (string $segment) => $segment !== '')
            ->implode('/');
    }
}
