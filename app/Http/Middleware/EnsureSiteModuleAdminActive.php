<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteModuleAdminActive
{
    public function handle(Request $request, Closure $next, string $moduleCode): Response
    {
        $siteId = (int) $request->session()->get('current_site_id', 0);

        if ($siteId > 0 && trim($moduleCode) !== '') {
            $binding = DB::table('site_module_bindings')
                ->join('modules', 'modules.id', '=', 'site_module_bindings.module_id')
                ->where('site_module_bindings.site_id', $siteId)
                ->where('modules.code', trim($moduleCode))
                ->first(['site_module_bindings.is_paused']);

            if ($binding && (bool) ($binding->is_paused ?? false)) {
                return redirect()
                    ->route('admin.site-dashboard')
                    ->withErrors(['module' => '该模块已停用，请联系客服处理。']);
            }
        }

        return $next($request);
    }
}

