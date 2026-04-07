<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\DatabaseHealth;
use App\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
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

        return view('auth.login', [
            'databaseHealthWarning' => $databaseHealth->hasPendingMigrations()
                ? $databaseHealth->warningMessage()
                : null,
            'adminDisabledMessage' => $this->systemSettings->adminEnabled()
                ? null
                : $this->systemSettings->adminDisabledMessage(),
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

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'username' => '用户名或密码不正确。',
            ]);
        }

        $userId = (int) $request->user()->id;

        if (! $this->systemSettings->adminEnabled() && ! $this->isSuperAdmin($userId)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'username' => $this->systemSettings->adminDisabledMessage(),
            ]);
        }

        if ((int) $request->user()->status !== 1 && ! $this->isPlatformAdmin($userId)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'username' => '账号已停用，如有疑问请联系站点管理员。',
            ]);
        }

        $request->session()->regenerate();

        if (! $this->isPlatformAdmin($userId) && $this->boundSites($userId)->isEmpty()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'username' => '当前账号尚未分配站点，请联系平台管理员。',
            ]);
        }

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
}
