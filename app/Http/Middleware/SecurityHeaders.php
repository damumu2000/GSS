<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
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
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "media-src 'self' blob:",
            "manifest-src 'self'",
            $isDebugExceptionPage ? "style-src 'self' 'unsafe-inline'" : "style-src 'self'",
            $isDebugExceptionPage ? "script-src 'self' 'unsafe-inline'" : "script-src 'self'",
            "connect-src 'self'",
            "frame-src 'self' https://player.bilibili.com",
        ]));

        $hstsEnabled = filter_var(
            (string) env('SECURITY_HEADERS_HSTS_APP', app()->environment('production', 'staging', 'testing')),
            FILTER_VALIDATE_BOOL
        );

        if ($request->isSecure() && $hstsEnabled) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
