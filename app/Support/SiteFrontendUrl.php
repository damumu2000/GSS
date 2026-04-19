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
        $host = mb_strtolower(trim((string) request()->getHost()));

        if (in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return route('site.home', ['site' => $site->site_key]);
        }

        $domain = static::primaryDomain((int) $site->id);

        if ($domain !== null) {
            $scheme = request()->getScheme();

            return rtrim($scheme.'://'.$domain, '/').route('site.home', absolute: false);
        }

        return route('site.home', ['site' => $site->site_key]);
    }

    protected static function primaryDomain(int $siteId): ?string
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
}
