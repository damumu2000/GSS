<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SiteBackendAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiteContextController extends Controller
{
    public function __construct(
        protected SiteBackendAccess $siteBackendAccess,
    ) {
    }

    /**
     * Switch the active site context for the current admin session.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
        ], [], [
            'site_id' => '站点',
        ]);

        $site = $this->adminSites($request->user()->id)
            ->firstWhere('id', (int) $validated['site_id']);

        if ($site) {
            $userId = (int) $request->user()->id;
            $isPlatformAdmin = $this->isPlatformAdmin($userId);
            $siteRecord = DB::table('sites')->where('id', (int) $site->id)->first();

            if (! $isPlatformAdmin && $siteRecord) {
                $siteAccess = $this->siteBackendAccess->status($siteRecord);

                if (! $siteAccess['allowed']) {
                    return back()->with('status', $siteAccess['message']);
                }
            }

            $request->session()->put('current_site_id', $site->id);

            $this->logOperation(
                $isPlatformAdmin ? 'platform' : 'site',
                'auth',
                'switch_site_context',
                ! $isPlatformAdmin ? (int) $site->id : null,
                $userId,
                'site',
                (int) $site->id,
                [
                    'site_name' => (string) $site->name,
                    'site_key' => (string) ($site->site_key ?? ''),
                ],
                $request,
            );
        } else {
            return back()->with('status', '当前账号不能进入所选站点。');
        }

        return back()->with('status', '当前站点已切换。');
    }
}
