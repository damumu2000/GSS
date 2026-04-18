<?php

namespace App\Http\Middleware;

use App\Support\SiteSecurity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SiteSecurityGuard
{
    public function __construct(
        protected SiteSecurity $siteSecurity,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        $site = $this->siteSecurity->resolveSite($request);

        if (! $site) {
            return $next($request);
        }

        $rule = $this->siteSecurity->inspect($request, $site);

        if ($rule !== null) {
            $this->siteSecurity->recordBlocked($site, $rule, $request);

            return response()->view('errors.security-blocked', [
                'blockedRule' => $rule,
                'blockedPath' => '/'.trim((string) $request->path(), '/'),
            ], 403);
        }

        return $next($request);
    }

    protected function shouldBypass(Request $request): bool
    {
        $path = trim((string) $request->path(), '/');

        if ($path === '' || $path === 'up') {
            return false;
        }

        foreach (['admin', 'logout'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
