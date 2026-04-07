<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiteContextController extends Controller
{
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
            $request->session()->put('current_site_id', $site->id);
        } else {
            return back()->with('status', '当前账号不能进入所选站点。');
        }

        return back()->with('status', '当前站点已切换。');
    }
}
