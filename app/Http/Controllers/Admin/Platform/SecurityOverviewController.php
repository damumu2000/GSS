<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use App\Support\PlatformSecuritySettings;
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
        protected PlatformSecuritySettings $platformSecuritySettings,
    ) {
    }

    public function __invoke(Request $request): View
    {
        $this->authorizePlatform($request, 'system.setting.manage');

        return view('admin.platform.security.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $this->currentSite($request),
            'overview' => $this->siteSecurity->platformOverviewPayload(),
            'securitySettings' => $this->platformSecuritySettings->formDefaults(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizePlatform($request, 'system.setting.manage');

        $settings = $this->platformSecuritySettings->validateAndStore($request, (int) $request->user()->id);

        $this->logOperation(
            'platform',
            'security',
            'update',
            null,
            (int) $request->user()->id,
            'system_setting',
            null,
            [
                'site_protection_enabled' => $settings['security.site_protection_enabled'],
                'malicious_auto_block_enabled' => $settings['security.malicious_auto_block_enabled'],
                'malicious_auto_block_threshold' => $settings['security.malicious_auto_block_threshold'],
            ],
            $request,
        );

        return redirect()
            ->route('admin.platform.security.index')
            ->with('status', '安护盾设置已更新。');
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
