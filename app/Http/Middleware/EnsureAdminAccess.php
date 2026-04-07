<?php

namespace App\Http\Middleware;

use App\Support\DatabaseHealth;
use App\Support\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    public function __construct(
        protected SystemSettings $systemSettings,
    ) {
    }

    /**
     * Ensure only platform admins or bound site operators can enter the admin area.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $databaseHealth = app(DatabaseHealth::class);

        if ($databaseHealth->hasPendingMigrations()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['username' => $databaseHealth->warningMessage()]);
        }

        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $this->systemSettings->adminEnabled() && (int) $user->id !== (int) config('cms.super_admin_user_id', 1)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['username' => $this->systemSettings->adminDisabledMessage()]);
        }

        $isPlatformAdmin = DB::table('platform_user_roles')
            ->join('platform_roles', 'platform_roles.id', '=', 'platform_user_roles.role_id')
            ->where('platform_user_roles.user_id', $user->id)
            ->exists();

        $currentStatus = (int) DB::table('users')
            ->where('id', $user->id)
            ->value('status');

        if (! $isPlatformAdmin && $currentStatus !== 1) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['username' => '账号已停用，如有疑问请联系站点管理员。']);
        }

        $sites = $isPlatformAdmin
            ? DB::table('sites')
                ->orderByRaw('CASE WHEN id = 1 THEN 0 ELSE 1 END')
                ->orderBy('name')
                ->get(['id'])
            : DB::table('sites')
                ->join('site_user_roles', 'site_user_roles.site_id', '=', 'sites.id')
                ->where('site_user_roles.user_id', $user->id)
                ->groupBy('sites.id', 'sites.name')
                ->orderBy('sites.name')
                ->get(['sites.id', 'sites.name']);

        if (! $isPlatformAdmin && $sites->isEmpty()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['username' => '当前账号尚未分配站点，请联系平台管理员。']);
        }

        $siteId = (int) $request->session()->get('current_site_id', 0);

        if ($sites->isNotEmpty() && ! $sites->contains(fn ($site) => (int) $site->id === $siteId)) {
            $request->session()->put('current_site_id', (int) $sites->first()->id);
        }

        return $next($request);
    }
}
