<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()?->id;

        if (! $userId) {
            abort(403, '当前账号没有平台权限。');
        }

        $isPlatformAdmin = DB::table('platform_user_roles')
            ->join('platform_roles', 'platform_roles.id', '=', 'platform_user_roles.role_id')
            ->where('platform_user_roles.user_id', $userId)
            ->exists();

        abort_unless($isPlatformAdmin, 403, '当前账号没有平台权限。');

        return $next($request);
    }
}
