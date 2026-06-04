<?php

use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsurePlatformOnly;
use App\Http\Middleware\EnsureSiteModuleAdminActive;
use App\Http\Middleware\EnsureSiteNotExpired;
use App\Http\Middleware\MinifyHtmlResponse;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SiteSecurityGuard;
use App\Support\AdminEntryGate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['admin_theme']);
        $middleware->redirectUsersTo(fn () => route('admin.entry'));
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
            'admin.access' => EnsureAdminAccess::class,
            'platform.only' => EnsurePlatformOnly::class,
            'html.minify' => MinifyHtmlResponse::class,
            'site.not_expired' => EnsureSiteNotExpired::class,
            'site.security' => SiteSecurityGuard::class,
            'security.headers' => SecurityHeaders::class,
            'module.admin.active' => EnsureSiteModuleAdminActive::class,
        ]);

        $middleware->append(SecurityHeaders::class);

        $middleware->appendToGroup('web', [
            EnsureSiteNotExpired::class,
            SiteSecurityGuard::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($response->getStatusCode() !== 419 || ! $exception->getPrevious() instanceof TokenMismatchException) {
                return $response;
            }

            if (! $request->is('login') && ! $request->is('login/captcha/check')) {
                return $response;
            }

            if (! app(AdminEntryGate::class)->allowsLogin($request)) {
                return response()->view('errors.404', [], 404);
            }

            $message = '登录令牌已过期，请刷新页面后重试。';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => $message,
                ], 419);
            }

            return redirect()
                ->route('login')
                ->withErrors([
                    'username' => $message,
                ])
                ->withInput($request->only(['username', 'remember', 'service_agreement']));
        });

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
