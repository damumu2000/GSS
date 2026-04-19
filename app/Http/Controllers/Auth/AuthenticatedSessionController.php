<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Support\DatabaseHealth;
use App\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Str;

class AuthenticatedSessionController extends Controller
{
    protected const LOGIN_CAPTCHA_TRIGGER_ATTEMPTS = 2;

    protected const LOGIN_LOCK_ATTEMPTS = 10;

    protected const LOGIN_DECAY_SECONDS = 300;

    protected const CAPTCHA_LENGTH = 4;

    public function __construct(
        protected SystemSettings $systemSettings,
    ) {
    }

    /**
     * Display the login screen.
     */
    public function create(Request $request): View|RedirectResponse|Response
    {
        if (Auth::check()) {
            return redirect()->route($this->defaultAdminRoute(Auth::id()));
        }

        $this->ensureLoginDeviceId($request);
        $this->clearExpiredStoredLockoutState($request);

        $databaseHealth = app(DatabaseHealth::class);
        $loginSiteBrand = $this->resolveLoginSite($request);

        if (! $loginSiteBrand) {
            return $this->renderDomainUnboundPage($request);
        }

        return view('auth.login', [
            'databaseHealthWarning' => $databaseHealth->hasPendingMigrations()
                ? $databaseHealth->warningMessage()
                : null,
            'adminDisabledMessage' => $this->systemSettings->adminEnabled()
                ? null
                : $this->systemSettings->adminDisabledMessage(),
            'loginSiteBrand' => $loginSiteBrand,
            'loginCaptchaRequired' => $this->loginCaptchaIsRequired($request),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse|Response
    {
        $loginSite = $this->resolveLoginSite($request);

        if (! $loginSite) {
            return $this->renderDomainUnboundPage($request);
        }

        $databaseHealth = app(DatabaseHealth::class);

        if ($databaseHealth->hasPendingMigrations()) {
            $this->throwLoginValidationException($request, [
                'username' => $databaseHealth->warningMessage(),
            ]);
        }

        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'captcha' => ['nullable', 'string'],
        ], [], [
            'username' => '用户名',
            'password' => '密码',
            'captcha' => '验证码',
        ]);

        $this->ensureLoginDeviceId($request);
        $loginThrottleKey = $this->loginThrottleKey($request, (string) $credentials['username']);
        $captchaTriggerKey = $this->loginCaptchaTriggerKey($request);
        $loginIsLocked = $this->syncLoginLockoutState($request, $loginThrottleKey);

        $captchaIsRequired = $this->loginCaptchaIsRequired($request, $captchaTriggerKey);

        if ($captchaIsRequired) {
            $submittedCaptcha = $this->normalizeCaptchaValue($request->input('captcha'));
            $expectedCaptcha = $this->normalizeCaptchaValue($request->session()->get($this->loginCaptchaSessionKey()));

            if (strlen($submittedCaptcha) !== self::CAPTCHA_LENGTH || $expectedCaptcha === '' || $submittedCaptcha !== $expectedCaptcha) {
                if ($loginIsLocked) {
                    $this->throwLoginValidationException($request, [
                        'username' => $this->loginLockoutMessage(RateLimiter::availableIn($loginThrottleKey)),
                    ]);
                }

                RateLimiter::hit($captchaTriggerKey, self::LOGIN_DECAY_SECONDS);
                RateLimiter::hit($loginThrottleKey, self::LOGIN_DECAY_SECONDS);
                $this->recordLoginCaptchaFailure($request);

                if ($this->syncLoginLockoutState($request, $loginThrottleKey)) {
                    $this->markLoginCaptchaAsRequired($request);

                    $this->throwLoginValidationException($request, [
                        'username' => $this->loginLockoutMessage(RateLimiter::availableIn($loginThrottleKey)),
                    ]);
                }

                $this->throwLoginValidationException($request, [
                    'captcha' => $this->loginFailureMessage(
                        '验证码输入错误，请从新输入。',
                        $this->remainingLoginAttempts($loginThrottleKey),
                    ),
                ]);
            }
        }

        if ($loginIsLocked || $this->syncLoginLockoutState($request, $loginThrottleKey)) {
            $this->markLoginCaptchaAsRequired($request);

            $this->throwLoginValidationException($request, [
                'username' => $this->loginLockoutMessage(RateLimiter::availableIn($loginThrottleKey)),
            ]);
        }

        $authCredentials = [
            'username' => (string) $credentials['username'],
            'password' => (string) $credentials['password'],
        ];

        if (! Auth::attempt($authCredentials, $request->boolean('remember'))) {
            RateLimiter::hit($captchaTriggerKey, self::LOGIN_DECAY_SECONDS);
            RateLimiter::hit($loginThrottleKey, self::LOGIN_DECAY_SECONDS);
            $this->recordLoginCaptchaFailure($request);

            if ($this->syncLoginLockoutState($request, $loginThrottleKey)) {
                $this->markLoginCaptchaAsRequired($request);

                $this->throwLoginValidationException($request, [
                    'username' => $this->loginLockoutMessage(RateLimiter::availableIn($loginThrottleKey)),
                ]);
            }

            $this->throwLoginValidationException($request, [
                'username' => $this->loginFailureMessage(
                    '用户名或密码不正确。',
                    $this->remainingLoginAttempts($loginThrottleKey),
                ),
            ]);
        }

        RateLimiter::clear($loginThrottleKey);
        RateLimiter::clear($captchaTriggerKey);
        $request->session()->forget($this->loginCaptchaRequiredSessionKey());
        $request->session()->forget($this->loginCaptchaFailureCountSessionKey());
        $request->session()->forget($this->loginCaptchaSessionKey());
        $request->session()->forget($this->loginLockoutUntilSessionKey());
        $request->session()->forget($this->loginLockoutKeySessionKey());

        $userId = (int) $request->user()->id;
        if (! $this->systemSettings->adminEnabled() && ! $this->isSuperAdmin($userId)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->throwLoginValidationException($request, [
                'username' => $this->systemSettings->adminDisabledMessage(),
            ]);
        }

        $isPlatformAdmin = $this->isPlatformAdmin($userId);

        if ((int) $request->user()->status !== 1 && ! $isPlatformAdmin) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->throwLoginValidationException($request, [
                'username' => '账号已停用，如有疑问请联系站点管理员。',
            ]);
        }

        $request->session()->regenerate();

        $boundSites = $isPlatformAdmin ? collect() : $this->boundSites($userId);

        if (! $isPlatformAdmin && $boundSites->isEmpty()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->throwLoginValidationException($request, [
                'username' => '当前账号尚未分配站点，请联系平台管理员。',
            ]);
        }

        if (! $isPlatformAdmin) {
            if ($loginSite && ! $boundSites->contains(fn ($site) => (int) $site->id === (int) $loginSite->id)) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $this->throwLoginValidationException($request, [
                    'username' => '当前账号无权登录该站点，请使用对应站点域名登录。',
                ]);
            }

            $currentSiteId = (int) $request->session()->get('current_site_id', 0);
            $loginSiteId = (int) ($loginSite->id ?? 0);

            if ($loginSiteId > 0 && $boundSites->contains(fn ($site) => (int) $site->id === $loginSiteId)) {
                $request->session()->put('current_site_id', $loginSiteId);
            } elseif (! $boundSites->contains(fn ($site) => (int) $site->id === $currentSiteId)) {
                $request->session()->put('current_site_id', (int) $boundSites->first()->id);
            }
        }

        DB::table('users')
            ->where('id', $userId)
            ->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
                'updated_at' => now(),
            ]);

        $loginSiteId = $isPlatformAdmin
            ? null
            : (int) $request->session()->get('current_site_id', (int) ($boundSites->first()->id ?? 0));

        $this->logOperation(
            $isPlatformAdmin ? 'platform' : 'site',
            'auth',
            'login',
            $loginSiteId > 0 ? $loginSiteId : null,
            $userId,
            'user',
            $userId,
            ['username' => (string) $request->user()->username],
            $request,
        );

        return redirect()->intended(route($this->defaultAdminRoute($userId)));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function captcha(Request $request): Response
    {
        if (! $this->resolveLoginSite($request)) {
            return response('', 404);
        }

        $code = $this->generateCaptchaCode();
        $request->session()->put($this->loginCaptchaSessionKey(), $code);

        $svg = $this->renderCaptchaSvg($code);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function captchaCheck(Request $request): JsonResponse
    {
        if (! $this->resolveLoginSite($request)) {
            return response()->json([
                'valid' => false,
            ], 404);
        }

        $payload = $request->validate([
            'captcha' => ['required', 'string'],
        ], [], [
            'captcha' => '验证码',
        ]);

        $submittedCaptcha = $this->normalizeCaptchaValue($payload['captcha']);
        $expectedCaptcha = $this->normalizeCaptchaValue($request->session()->get($this->loginCaptchaSessionKey()));
        $isValid = strlen($submittedCaptcha) === self::CAPTCHA_LENGTH
            && $expectedCaptcha !== ''
            && $submittedCaptcha === $expectedCaptcha;

        return response()->json([
            'valid' => $isValid,
        ]);
    }

    protected function resolveLoginSite(Request $request): ?object
    {
        $host = mb_strtolower(trim((string) $request->getHost()));

        if ($host !== '') {
            $site = DB::table('site_domains')
                ->join('sites', 'sites.id', '=', 'site_domains.site_id')
                ->whereRaw('LOWER(site_domains.domain) = ?', [$host])
                ->where('site_domains.status', 1)
                ->where('sites.status', 1)
                ->first([
                    'sites.id',
                    'sites.name',
                    'sites.site_key',
                    'sites.logo',
                    'sites.favicon',
                    'sites.seo_title',
                    'sites.seo_keywords',
                    'sites.seo_description',
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
                ->select(['id', 'name', 'site_key', 'logo', 'favicon', 'seo_title', 'seo_keywords', 'seo_description'])
                ->where('site_key', $siteKey)
                ->where('status', 1)
                ->first();

            if ($site) {
                return $site;
            }
        }

        return Site::query()
            ->select(['id', 'name', 'site_key', 'logo', 'favicon', 'seo_title', 'seo_keywords', 'seo_description'])
            ->where('status', 1)
            ->orderBy('id')
            ->first();
    }

    protected function renderDomainUnboundPage(Request $request): Response
    {
        return response()->view('site.domain-unbound', [
            'host' => mb_strtolower(trim((string) $request->getHost())),
        ]);
    }

    protected function loginThrottleKey(Request $request, string $username): string
    {
        return 'auth-login:'.sha1(
            $this->ensureLoginDeviceId($request)
            .'|'.($request->ip() ?: 'guest')
            .'|'.mb_strtolower(trim($username))
        );
    }

    protected function loginCaptchaSessionKey(): string
    {
        return 'auth.login.captcha';
    }

    protected function loginCaptchaRequiredSessionKey(): string
    {
        return 'auth.login.captcha.required';
    }

    protected function loginCaptchaFailureCountSessionKey(): string
    {
        return 'auth.login.captcha.failures';
    }

    protected function loginDeviceSessionKey(): string
    {
        return 'auth.login.device';
    }

    protected function loginDeviceCookieName(): string
    {
        return 'auth_login_device';
    }

    protected function loginLockoutUntilSessionKey(): string
    {
        return 'auth.login.locked_until';
    }

    protected function loginLockoutKeySessionKey(): string
    {
        return 'auth.login.locked_key';
    }

    protected function loginCaptchaIsRequired(Request $request, ?string $captchaTriggerKey = null): bool
    {
        if (! empty($request->session()->get($this->loginCaptchaRequiredSessionKey()))) {
            return true;
        }

        if ((int) $request->session()->get($this->loginCaptchaFailureCountSessionKey(), 0) >= self::LOGIN_CAPTCHA_TRIGGER_ATTEMPTS) {
            return true;
        }

        if ($captchaTriggerKey !== null) {
            return RateLimiter::attempts($captchaTriggerKey) >= self::LOGIN_CAPTCHA_TRIGGER_ATTEMPTS;
        }

        return RateLimiter::attempts($this->loginCaptchaTriggerKey($request)) >= self::LOGIN_CAPTCHA_TRIGGER_ATTEMPTS;
    }

    protected function ensureLoginDeviceId(Request $request): string
    {
        $cookieDeviceId = $this->normalizeLoginDeviceId($request->cookie($this->loginDeviceCookieName()));
        $sessionDeviceId = $this->normalizeLoginDeviceId($request->session()->get($this->loginDeviceSessionKey()));

        $deviceId = $cookieDeviceId !== '' ? $cookieDeviceId : $sessionDeviceId;

        if ($deviceId === '') {
            $deviceId = Str::lower(Str::random(40));
        }

        if ($sessionDeviceId !== $deviceId) {
            $request->session()->put($this->loginDeviceSessionKey(), $deviceId);
        }

        if ($cookieDeviceId !== $deviceId) {
            $this->queueLoginDeviceCookie($request, $deviceId);
        }

        return $deviceId;
    }

    protected function normalizeLoginDeviceId(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $deviceId = trim((string) $value);

        if ($deviceId === '' || strlen($deviceId) > 120) {
            return '';
        }

        return $deviceId;
    }

    protected function queueLoginDeviceCookie(Request $request, string $deviceId): void
    {
        Cookie::queue(cookie()->forever(
            $this->loginDeviceCookieName(),
            $deviceId,
            '/',
            null,
            $request->isSecure() || (bool) config('session.secure', false),
            true,
            false,
            'lax',
        ));
    }

    protected function loginCaptchaTriggerKey(Request $request): string
    {
        return 'auth-login-captcha-trigger:'.sha1($this->ensureLoginDeviceId($request).'|'.($request->ip() ?: 'guest'));
    }

    protected function clearExpiredStoredLockoutState(Request $request): void
    {
        $lockedUntil = (int) $request->session()->get($this->loginLockoutUntilSessionKey(), 0);
        $lockedKey = (string) $request->session()->get($this->loginLockoutKeySessionKey(), '');

        if ($lockedUntil > 0 && now()->timestamp >= $lockedUntil) {
            if ($lockedKey !== '') {
                RateLimiter::clear($lockedKey);
            }

            $request->session()->forget($this->loginLockoutUntilSessionKey());
            $request->session()->forget($this->loginLockoutKeySessionKey());
        }
    }

    protected function syncLoginLockoutState(Request $request, string $loginThrottleKey): bool
    {
        $this->clearExpiredStoredLockoutState($request);

        $lockedUntil = (int) $request->session()->get($this->loginLockoutUntilSessionKey(), 0);
        $lockedKey = (string) $request->session()->get($this->loginLockoutKeySessionKey(), '');

        if (RateLimiter::tooManyAttempts($loginThrottleKey, self::LOGIN_LOCK_ATTEMPTS)) {
            $availableSeconds = RateLimiter::availableIn($loginThrottleKey);
            $request->session()->put($this->loginLockoutUntilSessionKey(), now()->addSeconds($availableSeconds)->timestamp);
            $request->session()->put($this->loginLockoutKeySessionKey(), $loginThrottleKey);

            return true;
        }

        if ($lockedUntil > 0 && $lockedKey === $loginThrottleKey) {
            $request->session()->forget($this->loginLockoutUntilSessionKey());
            $request->session()->forget($this->loginLockoutKeySessionKey());
        }

        return false;
    }

    protected function normalizeCaptchaValue(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return strtoupper(trim((string) $value));
    }

    protected function recordLoginCaptchaFailure(Request $request): void
    {
        $failures = (int) $request->session()->get($this->loginCaptchaFailureCountSessionKey(), 0);
        $failures++;
        $request->session()->put($this->loginCaptchaFailureCountSessionKey(), $failures);

        if ($failures >= self::LOGIN_CAPTCHA_TRIGGER_ATTEMPTS) {
            $this->markLoginCaptchaAsRequired($request);
        }
    }

    protected function markLoginCaptchaAsRequired(Request $request): void
    {
        $request->session()->put($this->loginCaptchaRequiredSessionKey(), true);
        $request->session()->put($this->loginCaptchaFailureCountSessionKey(), max(
            (int) $request->session()->get($this->loginCaptchaFailureCountSessionKey(), 0),
            self::LOGIN_CAPTCHA_TRIGGER_ATTEMPTS,
        ));
    }

    protected function loginLockoutMessage(int $availableSeconds): string
    {
        $availableSeconds = max(1, $availableSeconds);
        $minutes = (int) ceil($availableSeconds / 60);

        return sprintf('登录尝试过于频繁，请稍后再试。剩余 %d 分钟后可再试。', $minutes);
    }

    protected function remainingLoginAttempts(string $loginThrottleKey): int
    {
        return max(0, self::LOGIN_LOCK_ATTEMPTS - RateLimiter::attempts($loginThrottleKey));
    }

    protected function loginFailureMessage(string $message, int $remainingAttempts): string
    {
        if ($remainingAttempts > 0) {
            return sprintf('%s 再输错 %d 次后将限制登录5分钟。', $message, $remainingAttempts);
        }

        return '输入错误次数过多，限制登录5分钟。';
    }

    protected function generateCaptchaCode(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $characters = [];

        for ($i = 0; $i < self::CAPTCHA_LENGTH; $i++) {
            $characters[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return implode('', $characters);
    }

    protected function renderCaptchaSvg(string $code): string
    {
        $width = 132;
        $height = 48;
        $innerWidth = $width - 1;
        $innerHeight = $height - 1;
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $noise = [];

        for ($i = 0; $i < 6; $i++) {
            $noise[] = sprintf(
                '<circle cx="%d" cy="%d" r="%d" fill="#94A3B8" fill-opacity="0.24" />',
                random_int(10, $width - 10),
                random_int(10, $height - 10),
                random_int(1, 2),
            );
        }

        for ($i = 0; $i < 4; $i++) {
            $x1 = random_int(0, $width - 20);
            $y1 = random_int(0, $height - 8);
            $x2 = min($width - 1, $x1 + random_int(16, 42));
            $y2 = min($height - 1, $y1 + random_int(-6, 6));

            $noise[] = sprintf(
                '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#94A3B8" stroke-opacity="0.22" stroke-width="1" />',
                $x1,
                $y1,
                $x2,
                $y2,
            );
        }

        $noiseMarkup = implode('', $noise);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" fill="none" role="img" aria-label="验证码">
    <rect x="0" y="0" width="{$width}" height="{$height}" rx="14" fill="#F8FAFC"/>
    <rect x="0.5" y="0.5" width="{$innerWidth}" height="{$innerHeight}" rx="13.5" fill="none" stroke="#E2E8F0"/>
    {$noiseMarkup}
    <text x="66" y="31" text-anchor="middle" fill="#1E293B" font-family="PingFang SC, Microsoft YaHei, Arial, sans-serif" font-size="20" font-weight="700" letter-spacing="4">{$safeCode}</text>
</svg>
SVG;
    }

    /**
     * @param  array<string, string>  $messages
     */
    protected function throwLoginValidationException(Request $request, array $messages): never
    {
        $errorBag = new MessageBag($messages);
        $response = redirect()
            ->route('login')
            ->withErrors($errorBag)
            ->withInput($request->only(['username', 'captcha', 'remember']));

        throw new ValidationException(
            validator()->make([], []),
            $response,
        );
    }
}
