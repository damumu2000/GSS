<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use App\Support\SiteSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SecurityOverviewController extends Controller
{
    public function __construct(
        protected SiteSecurity $siteSecurity,
    ) {
    }

    public function __invoke(Request $request): View
    {
        $this->authorizePlatform($request, 'system.setting.manage');

        return view('admin.platform.security.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $this->currentSite($request),
            'overview' => $this->siteSecurity->platformOverviewPayload(),
        ]);
    }

    public function ipDetail(Request $request): View
    {
        $this->authorizePlatform($request, 'system.setting.manage');

        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'client_ip' => ['required', 'ip'],
        ]);

        $siteId = (int) $validated['site_id'];
        $detail = $this->siteSecurity->siteIpDetailPayload($siteId, trim((string) $validated['client_ip']));
        $site = DB::table('sites')->where('id', $siteId)->first(['id', 'name', 'site_key']);

        abort_if($detail === null || ! $site, 404);

        return view('admin.platform.security.ip-detail', [
            'sites' => $this->adminSites(),
            'currentSite' => $this->currentSite($request),
            'detail' => $detail,
            'targetSite' => $site,
        ]);
    }

    public function storeIpPolicy(Request $request): RedirectResponse
    {
        $this->authorizePlatform($request, 'system.setting.manage');

        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'client_ip' => ['required', 'ip'],
            'action' => ['required', Rule::in($this->siteSecurity->siteIpPolicyActions())],
        ]);

        $siteId = (int) $validated['site_id'];
        $ip = trim((string) $validated['client_ip']);
        $action = (string) $validated['action'];
        $site = DB::table('sites')->where('id', $siteId)->first(['id', 'name', 'site_key']);

        if (! $site) {
            abort(404);
        }
        $this->siteSecurity->applySiteIpPolicy($siteId, $ip, $action, (int) $request->user()->id);

        $this->logOperation(
            'platform',
            'security',
            $this->siteSecurity->siteIpPolicyAuditAction($action),
            null,
            (int) $request->user()->id,
            'site_security_ip',
            null,
            [
                'site_name' => (string) $site->name,
                'site_key' => (string) $site->site_key,
                'client_ip' => $ip,
                'action' => $action,
            ],
            $request,
        );

        return redirect()
            ->route('admin.platform.security.ip-detail', ['site_id' => $siteId, 'client_ip' => $ip])
            ->with('status', $this->siteSecurity->siteIpPolicyStatusMessage($action));
    }
}
