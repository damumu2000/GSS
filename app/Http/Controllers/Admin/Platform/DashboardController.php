<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! $this->isPlatformAdmin($request->user()->id)) {
            return redirect()->route('admin.site-dashboard');
        }

        $sites = $this->adminSites($request->user()->id);
        $currentSite = $this->currentSite($request);
        $currentSiteId = $currentSite->id;
        $stats = [
            'site_count' => $sites->count(),
            'channel_count' => $currentSiteId
                ? DB::table('channels')->where('site_id', $currentSiteId)->count()
                : 0,
            'content_count' => $currentSiteId
                ? DB::table('contents')->where('site_id', $currentSiteId)->count()
                : 0,
            'attachment_count' => $currentSiteId
                ? DB::table('attachments')->where('site_id', $currentSiteId)->count()
                : 0,
        ];

        $recentContents = $currentSiteId
            ? DB::table('contents')
                ->where('site_id', $currentSiteId)
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get(['id', 'title', 'type', 'status', 'updated_at'])
            : collect();

        $platformNotices = $this->platformNoticeItems(6);
        $platformNoticeSiteKey = $this->platformSiteKey();

        return view('admin.platform.dashboard', [
            'sites' => $sites,
            'currentSite' => $currentSite,
            'stats' => $stats,
            'recentContents' => $recentContents,
            'platformNotices' => $platformNotices,
            'platformNoticeSiteKey' => $platformNoticeSiteKey,
            'showPlatformNoticeLink' => $platformNoticeSiteKey !== '',
        ]);
    }
}
