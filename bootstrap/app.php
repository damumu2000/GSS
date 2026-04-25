<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['admin_theme']);
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*'),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        $middleware->alias([
            'admin.access' => \App\Http\Middleware\EnsureAdminAccess::class,
            'platform.only' => \App\Http\Middleware\EnsurePlatformOnly::class,
            'html.minify' => \App\Http\Middleware\MinifyHtmlResponse::class,
            'site.not_expired' => \App\Http\Middleware\EnsureSiteNotExpired::class,
            'site.security' => \App\Http\Middleware\SiteSecurityGuard::class,
            'security.headers' => \App\Http\Middleware\SecurityHeaders::class,
            'module.admin.active' => \App\Http\Middleware\EnsureSiteModuleAdminActive::class,
        ]);

        $middleware->appendToGroup('web', [
            \App\Http\Middleware\EnsureSiteNotExpired::class,
            \App\Http\Middleware\SiteSecurityGuard::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            $message = '本次上传内容过大，请减少单次上传数量或压缩文件后重试。';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => $message,
                ], 413);
            }

            return redirect()->back()->withErrors([
                'file' => $message,
            ]);
        });
    })->create();
