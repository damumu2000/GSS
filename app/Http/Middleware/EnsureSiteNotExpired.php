<?php

namespace App\Http\Middleware;

use App\Support\SiteBackendAccess;
use App\Support\SiteExpiration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteNotExpired
{
    public function __construct(
        protected SiteBackendAccess $siteBackendAccess,
        protected SiteExpiration $siteExpiration,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        if (! $this->isAdminPath($request)) {
            return $this->handleFrontend($request, $next);
        }

        if (! $request->user()) {
            return $next($request);
        }

        $siteId = (int) $request->session()->get('current_site_id', 0);
        if ($siteId <= 0) {
            return $next($request);
        }

        $site = DB::table('sites')->where('id', $siteId)->first();
        if (! $site) {
            return $next($request);
        }

        if ($this->isPlatformAdmin((int) $request->user()->id)) {
            return $next($request);
        }

        $siteAccess = $this->siteBackendAccess->status($site);
        if (! $siteAccess['allowed']) {
            return response()->view('admin.site.expired', [
                'site' => $site,
                'title' => '站点后台功能已限制',
                'message' => $siteAccess['message'],
            ], 403);
        }

        return $next($request);
    }

    protected function isPlatformAdmin(int $userId): bool
    {
        return DB::table('platform_user_roles')
            ->where('user_id', $userId)
            ->exists();
    }

    protected function shouldBypass(Request $request): bool
    {
        $path = trim((string) $request->path(), '/');

        foreach (['login', 'logout', 'up'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        if (str_starts_with($path, 'admin') && ! $this->isAdminPath($request)) {
            return true;
        }

        return in_array($path, ['admin/site-context', 'admin/site/expired'], true);
    }

    protected function isAdminPath(Request $request): bool
    {
        $path = trim((string) $request->path(), '/');

        return str_starts_with($path, 'admin/site') || str_starts_with($path, 'admin/content-preview');
    }

    protected function handleFrontend(Request $request, Closure $next): Response
    {
        $site = $this->resolveFrontendSite($request);

        if (! $site) {
            return $next($request);
        }

        if ((int) ($site->status ?? 1) !== 1) {
            return response()->view('site.closed', [
                'site' => $site,
            ], 503);
        }

        $expiration = $this->siteExpiration->status($site);

        if (! ($expiration['frontend_blocked'] ?? false)) {
            return $next($request);
        }

        return response()->view('site.expired', [
            'site' => $site,
            'expiration' => $expiration,
        ], 503);
    }

    protected function resolveFrontendSite(Request $request): ?object
    {
        $host = mb_strtolower(trim((string) $request->getHost()));

        if ($host !== '') {
            $site = DB::table('site_domains')
                ->join('sites', 'sites.id', '=', 'site_domains.site_id')
                ->whereRaw('LOWER(site_domains.domain) = ?', [$host])
                ->where('site_domains.status', 1)
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
                ->first();

            if ($site) {
                return $site;
            }
        }

        $site = DB::table('sites')
            ->where('status', 1)
            ->orderBy('id')
            ->first();

        if ($site) {
            return $site;
        }

        return DB::table('sites')
            ->orderBy('id')
            ->first();
    }
}
