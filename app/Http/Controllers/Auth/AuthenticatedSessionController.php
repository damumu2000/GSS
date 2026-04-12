<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Support\DatabaseHealth;
use App\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    protected const LOGIN_MAX_ATTEMPTS = 5;

    protected const LOGIN_DECAY_SECONDS = 300;

    public function __construct(
        protected SystemSettings $systemSettings,
    ) {
    }

    /**
     * Display the login screen.
     */
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route($this->defaultAdminRoute(Auth::id()));
        }

        $databaseHealth = app(DatabaseHealth::class);
        $loginSiteBrand = Site::query()
            ->select(['id', 'name', 'site_key', 'logo', 'favicon', 'seo_title', 'seo_keywords', 'seo_description'])
            ->where('site_key', 'site')
            ->first();

        return view('auth.login', [
            'databaseHealthWarning' => $databaseHealth->hasPendingMigrations()
                ? $databaseHealth->warningMessage()
                : null,
            'adminDisabledMessage' => $this->systemSettings->adminEnabled()
                ? null
                : $this->systemSettings->adminDisabledMessage(),
            'loginSiteBrand' => $loginSiteBrand,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $databaseHealth = app(DatabaseHealth::class);

        if ($databaseHealth->hasPendingMigrations()) {
            throw ValidationException::withMessages([
                'username' => $databaseHealth->warningMessage(),
            ]);
        }

        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [], [
            'username' => '用户名',
            'password' => '密码',
        ]);

        $loginThrottleKey = $this->loginThrottleKey($request, (string) $credentials['username']);

        if (RateLimiter::tooManyAttempts($loginThrottleKey, self::LOGIN_MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'username' => '登录尝试过于频繁，请稍后再试。',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($loginThrottleKey, self::LOGIN_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'username' => '用户名或密码不正确。',
            ]);
        }

        RateLimiter::clear($loginThrottleKey);

        $userId = (int) $request->user()->id;

        if (! $this->systemSettings->adminEnabled() && ! $this->isSuperAdmin($userId)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'username' => $this->systemSettings->adminDisabledMessage(),
            ]);
        }

        $isPlatformAdmin = $this->isPlatformAdmin($userId);

        if ((int) $request->user()->status !== 1 && ! $isPlatformAdmin) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'username' => '账号已停用，如有疑问请联系站点管理员。',
            ]);
        }

        $request->session()->regenerate();

        $boundSites = $isPlatformAdmin ? collect() : $this->boundSites($userId);

        if (! $isPlatformAdmin && $boundSites->isEmpty()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'username' => '当前账号尚未分配站点，请联系平台管理员。',
            ]);
        }

        if (! $isPlatformAdmin) {
            $currentSiteId = (int) $request->session()->get('current_site_id', 0);

            if (! $boundSites->contains(fn ($site) => (int) $site->id === $currentSiteId)) {
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

    protected function loginThrottleKey(Request $request, string $username): string
    {
        return 'auth-login:'.sha1(mb_strtolower(trim($username)).'|'.($request->ip() ?: 'guest'));
    }
}
