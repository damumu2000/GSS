<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FrontendPageCache
{
    public static function enabled(): bool
    {
        return (bool) config('cms.frontend_page_cache.enabled', false);
    }

    public static function ttl(): int
    {
        return max(0, (int) config('cms.frontend_page_cache.ttl', 300));
    }

    public static function shouldUse(Request $request): bool
    {
        if (! self::enabled() || self::ttl() <= 0 || ! $request->isMethod('GET')) {
            return false;
        }

        if ($request->ajax() || $request->expectsJson()) {
            return false;
        }

        if (auth()->check()) {
            return false;
        }

        $path = trim($request->path(), '/');

        return $path === ''
            || (! str_starts_with($path, 'admin')
                && ! str_starts_with($path, 'login')
                && ! str_starts_with($path, 'logout'));
    }

    public static function key(object $site, Request $request, string $device): string
    {
        return 'frontend-page-cache:'.hash('sha256', implode('|', [
            (int) $site->id,
            self::siteVersion((int) $site->id),
            $device,
            $request->fullUrl(),
        ]));
    }

    public static function get(string $key): ?string
    {
        $html = Cache::get($key);

        return is_string($html) && $html !== '' ? $html : null;
    }

    public static function put(string $key, string $html): void
    {
        Cache::put($key, $html, self::ttl());
    }

    public static function canStoreHtml(string $html): bool
    {
        $html = Str::lower($html);

        return ! str_contains($html, 'name="_token"')
            && ! str_contains($html, "name='_token'")
            && ! str_contains($html, 'csrf-token')
            && ! str_contains($html, 'csrftoken');
    }

    public static function flushSite(int $siteId): void
    {
        if ($siteId <= 0) {
            return;
        }

        Cache::forever(self::siteVersionKey($siteId), self::siteVersion($siteId) + 1);
    }

    protected static function siteVersion(int $siteId): int
    {
        return max(1, (int) Cache::get(self::siteVersionKey($siteId), 1));
    }

    protected static function siteVersionKey(int $siteId): string
    {
        return 'frontend-page-cache:site:'.$siteId.':version';
    }
}
