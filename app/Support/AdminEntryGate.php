<?php

namespace App\Support;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AdminEntryGate
{
    protected const COOKIE_NAME = 'cms_admin_entry_passed';

    protected const SETTING_KEY = 'security.admin_entry_path';

    protected const SESSION_KEY = 'cms.admin_entry_passed';

    protected const COOKIE_MINUTES = 30;

    public function enabled(): bool
    {
        return filter_var(
            env('CMS_ADMIN_ENTRY_GATE_ENABLED', env('APP_ENV', 'production') === 'production'),
            FILTER_VALIDATE_BOOL,
        );
    }

    public function cookieName(): string
    {
        return self::COOKIE_NAME;
    }

    public function settingKey(): string
    {
        return self::SETTING_KEY;
    }

    public function resolveSite(Request $request): ?object
    {
        $host = mb_strtolower(trim((string) $request->getHost()));

        if ($host !== '') {
            $site = DB::table('site_domains')
                ->join('sites', 'sites.id', '=', 'site_domains.site_id')
                ->whereRaw('LOWER(site_domains.domain) = ?', [$host])
                ->where('site_domains.status', 1)
                ->first([
                    'sites.id',
                    'sites.name',
                    'sites.site_key',
                    'sites.logo',
                    'sites.favicon',
                    'sites.seo_title',
                    'sites.seo_keywords',
                    'sites.seo_description',
                    'sites.status',
                    'sites.expires_at',
                ]);

            if ($site) {
                return $site;
            }
        }

        if (! in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return null;
        }

        $siteKey = trim((string) $request->query('site', ''));

        if ($siteKey !== '') {
            $site = Site::query()
                ->select(['id', 'name', 'site_key', 'logo', 'favicon', 'seo_title', 'seo_keywords', 'seo_description', 'status', 'expires_at'])
                ->where('site_key', $siteKey)
                ->first();

            if ($site) {
                return $site;
            }
        }

        return Site::query()
            ->select(['id', 'name', 'site_key', 'logo', 'favicon', 'seo_title', 'seo_keywords', 'seo_description', 'status', 'expires_at'])
            ->where('status', 1)
            ->orderBy('id')
            ->first();
    }

    public function entryPathForSite(int $siteId): string
    {
        $stored = $this->normalizeEntryPath((string) DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', self::SETTING_KEY)
            ->value('setting_value'));

        if ($stored !== '') {
            return $stored;
        }

        $generated = $this->generateUniqueEntryPath($siteId);

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => self::SETTING_KEY],
            [
                'setting_value' => $generated,
                'autoload' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return $generated;
    }

    public function normalizeEntryPath(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = trim($value, "/ \t\n\r\0\x0B");

        return preg_match('/^[a-z0-9](?:[a-z0-9-]{6,62}[a-z0-9])$/', $value) === 1
            && ! str_contains($value, '--')
                ? $value
                : '';
    }

    public function validateEntryPath(string $value, int $siteId): ?string
    {
        $candidate = $this->cleanEntryPath($value);

        if ($this->isReservedEntryPath($candidate)) {
            return '后台入口路径不能使用系统保留路径或常见扫描路径。';
        }

        $path = $this->normalizeEntryPath($value);

        if ($path === '') {
            return '后台入口路径需为 8-64 位小写字母、数字或短横线，且不能以短横线开头或结尾。';
        }

        if ($this->entryPathExistsOnOtherSite($path, $siteId)) {
            return '后台入口路径已被其他站点使用，请更换后重试。';
        }

        if ($this->conflictsWithSiteFrontendPath($path, $siteId)) {
            return '后台入口路径不能与当前站点前台栏目、模块或资源路径冲突。';
        }

        return null;
    }

    protected function cleanEntryPath(string $value): string
    {
        $value = trim(mb_strtolower($value));

        return trim($value, "/ \t\n\r\0\x0B");
    }

    public function generateEntryPathForSite(int $siteId): string
    {
        return $this->generateUniqueEntryPath($siteId);
    }

    public function issueEntryCookieForSiteId(Request $request, int $siteId): void
    {
        if (! $this->enabled() || $siteId <= 0) {
            return;
        }

        $site = DB::table('sites')
            ->where('id', $siteId)
            ->first([
                'id',
                'name',
                'site_key',
                'status',
                'expires_at',
            ]);

        if (! $site) {
            return;
        }

        $this->issueEntryCookie($request, $site);
    }

    public function issueEntryCookie(Request $request, object $site): void
    {
        $siteId = (int) $site->id;
        $entryPath = $this->entryPathForSite($siteId);
        $expiresAt = now()->addMinutes(self::COOKIE_MINUTES)->timestamp;
        $payload = [
            'site_id' => $siteId,
            'path_hash' => hash('sha256', $entryPath),
            'expires_at' => $expiresAt,
        ];

        $request->session()->put(self::SESSION_KEY, $payload);

        Cookie::queue(cookie(
            self::COOKIE_NAME,
            json_encode($payload, JSON_UNESCAPED_SLASHES),
            self::COOKIE_MINUTES,
            '/',
            null,
            $request->isSecure() || (bool) config('session.secure', false),
            true,
            false,
            'lax',
        ));
    }

    public function allowsLogin(Request $request, ?object $site = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        $site = $site ?: $this->resolveSite($request);

        if (! $site) {
            return false;
        }

        $payload = $this->entryPayload($request);

        if ($payload === null || (int) ($payload['site_id'] ?? 0) !== (int) $site->id) {
            return false;
        }

        if ((int) ($payload['expires_at'] ?? 0) < now()->timestamp) {
            return false;
        }

        return hash_equals(
            (string) ($payload['path_hash'] ?? ''),
            hash('sha256', $this->entryPathForSite((int) $site->id)),
        );
    }

    public function entryMatches(Request $request, string $entryPath): ?object
    {
        $path = $this->normalizeEntryPath($entryPath);

        if ($path === '') {
            return null;
        }

        $site = $this->resolveSite($request);

        if (! $site) {
            return null;
        }

        if (! hash_equals($this->entryPathForSite((int) $site->id), $path)) {
            return null;
        }

        return $site;
    }

    public function hitFailedEntryAttempt(Request $request, string $entryPath): void
    {
        RateLimiter::hit($this->entryAttemptKey($request, $entryPath), 600);
    }

    public function tooManyEntryAttempts(Request $request, string $entryPath): bool
    {
        return RateLimiter::tooManyAttempts($this->entryAttemptKey($request, $entryPath), 20);
    }

    protected function entryPayload(Request $request): ?array
    {
        $sessionPayload = $request->session()->get(self::SESSION_KEY);

        if (is_array($sessionPayload)) {
            return $sessionPayload;
        }

        return $this->cookiePayload($request);
    }

    protected function cookiePayload(Request $request): ?array
    {
        $raw = $request->cookie(self::COOKIE_NAME);

        if (! is_string($raw) || $raw === '' || strlen($raw) > 1000) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function entryAttemptKey(Request $request, string $entryPath): string
    {
        return 'admin-entry-gate:'.sha1(
            mb_strtolower((string) $request->getHost())
            .'|'.($request->ip() ?: 'guest')
            .'|'.mb_strtolower($entryPath),
        );
    }

    protected function generateUniqueEntryPath(int $siteId): string
    {
        do {
            $candidate = 'console-'.Str::lower(Str::random(10));
        } while (
            $this->validateEntryPath($candidate, $siteId) !== null
            || DB::table('site_settings')
                ->where('setting_key', self::SETTING_KEY)
                ->where('setting_value', $candidate)
                ->exists()
        );

        return $candidate;
    }

    protected function isReservedEntryPath(string $path): bool
    {
        $reserved = [
            'admin',
            'api',
            'article',
            'assets',
            'atts',
            'cat',
            'css',
            'favicon.ico',
            'guestbook',
            'images',
            'js',
            'login',
            'logout',
            'page',
            'payroll',
            'phpmyadmin',
            'pma',
            'pub',
            'public',
            'site-media',
            'storage',
            'theme-assets',
            'up',
            'vendor',
            'wp-admin',
            'wp-login',
        ];

        return in_array($path, $reserved, true);
    }

    protected function entryPathExistsOnOtherSite(string $path, int $siteId): bool
    {
        return DB::table('site_settings')
            ->where('setting_key', self::SETTING_KEY)
            ->where('setting_value', $path)
            ->where('site_id', '!=', $siteId)
            ->exists();
    }

    protected function conflictsWithSiteFrontendPath(string $path, int $siteId): bool
    {
        return DB::table('channels')
            ->where('site_id', $siteId)
            ->where('slug', $path)
            ->exists();
    }
}
