<?php

namespace App\Http\Middleware;

use App\Support\SiteSecurity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function __construct(
        protected SiteSecurity $siteSecurity,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($response = $this->blockedBadMethodResponse($request)) {
            return $this->applyHeaders($request, $response);
        }

        /** @var Response $response */
        $response = $next($request);

        return $this->applyHeaders($request, $response);
    }

    protected function blockedBadMethodResponse(Request $request): ?Response
    {
        $site = $this->siteSecurity->resolveSite($request);

        if (! $site) {
            return null;
        }

        $rule = $this->siteSecurity->inspectBadMethod($request);

        if ($rule === null) {
            return null;
        }

        $this->siteSecurity->recordBlocked($site, $rule, $request);

        if (! $this->siteSecurity->shouldBlock($rule)) {
            return null;
        }

        return response()->view('errors.security-blocked', [
            'blockedRule' => $rule,
            'blockedPath' => '/'.trim((string) $request->path(), '/'),
        ], 403);
    }

    protected function applyHeaders(Request $request, Response $response): Response
    {
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('X-Generator');

        $contentType = (string) $response->headers->get('Content-Type', '');
        $isDebugExceptionPage = config('app.debug')
            && $response->getStatusCode() >= 500
            && str_contains(strtolower($contentType), 'text/html');

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "img-src 'self' https: data: blob:",
            "font-src 'self' data:",
            "media-src 'self' https: blob:",
            "manifest-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            $isDebugExceptionPage ? "script-src 'self' 'unsafe-inline'" : "script-src 'self'",
            "connect-src 'self'",
            "frame-src 'self' https://player.bilibili.com https://www.bilibili.com",
        ]));

        $hstsEnabled = filter_var((string) env('SECURITY_HEADERS_HSTS_APP', false), FILTER_VALIDATE_BOOL);

        if ($request->isSecure() && $hstsEnabled) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
