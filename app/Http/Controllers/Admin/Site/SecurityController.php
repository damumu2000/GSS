<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Support\SiteSecurity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SecurityController extends Controller
{
    public function __construct(
        protected SiteSecurity $siteSecurity,
    ) {
    }

    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'security.view');
        $siteId = (int) $currentSite->id;
        $eventFilter = in_array((string) $request->query('security_event_filter', 'all'), ['all', 'critical', 'high', 'medium'], true)
            ? (string) $request->query('security_event_filter', 'all')
            : 'all';
        $activeModal = in_array((string) $request->query('security_modal', ''), ['events', 'ips'], true)
            ? (string) $request->query('security_modal', '')
            : '';

        return view('admin.site.security.index', [
            'currentSite' => $currentSite,
            'sites' => $this->adminSites(),
            'showSiteSwitcher' => $this->shouldShowSiteSwitcher($request->user()->id),
            'security' => $this->siteSecurity->sitePagePayload($siteId),
            'canManageIpPolicy' => in_array('security.manage', $this->sitePermissionCodes((int) $request->user()->id, $siteId), true),
            'securityEventFilter' => $eventFilter,
            'securityEventsPaginator' => $this->siteSecurity->siteEventsModalPaginator($siteId, $eventFilter, (int) $request->query('security_event_page', 1)),
            'securityIpsPaginator' => $this->siteSecurity->siteSuspiciousIpsModalPaginator($siteId, (int) $request->query('security_ip_page', 1)),
            'activeSecurityModal' => $activeModal,
        ]);
    }

    public function ipDetail(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'security.view');

        $validated = $request->validate([
            'client_ip' => ['required', 'ip'],
        ]);

        $detail = $this->siteSecurity->siteIpDetailPayload($siteId, trim((string) $validated['client_ip']));

        abort_if($detail === null, 404);

        return view('admin.site.security.ip-detail', [
            'currentSite' => $currentSite,
            'sites' => $this->adminSites(),
            'showSiteSwitcher' => $this->shouldShowSiteSwitcher($request->user()->id),
            'detail' => $detail,
        ]);
    }

    public function storeIpPolicy(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;

        $this->authorizeSite($request, $siteId, 'security.manage');

        $validated = $request->validate([
            'client_ip' => ['required', 'ip'],
            'action' => ['required', Rule::in($this->siteSecurity->siteIpPolicyActions())],
        ]);

        $ip = trim((string) $validated['client_ip']);
        $action = (string) $validated['action'];
        $this->siteSecurity->applySiteIpPolicy($siteId, $ip, $action, (int) $request->user()->id);

        $this->logOperation(
            'site',
            'security',
            $this->siteSecurity->siteIpPolicyAuditAction($action),
            $siteId,
            (int) $request->user()->id,
            'site_security_ip',
            null,
            [
                'client_ip' => $ip,
                'action' => $action,
            ],
            $request,
        );

        return redirect()
            ->route('admin.security.index')
            ->with('status', $this->siteSecurity->siteIpPolicyStatusMessage($action));
    }

    public function deleteEvent(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'security.manage');

        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'min:1'],
            'security_event_filter' => ['nullable', Rule::in(['all', 'critical', 'high', 'medium'])],
            'security_event_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $deleted = $this->siteSecurity->deleteSiteSecurityEventRecord($siteId, (int) $validated['event_id']);

        if ($deleted) {
            $this->logOperation(
                'site',
                'security',
                'security_delete_event_record',
                $siteId,
                (int) $request->user()->id,
                'site_security_event',
                (int) $validated['event_id'],
                ['event_id' => (int) $validated['event_id']],
                $request,
            );
        }

        return redirect()
            ->route('admin.security.index', [
                'security_modal' => 'events',
                'security_event_filter' => (string) ($validated['security_event_filter'] ?? 'all'),
                'security_event_page' => (int) ($validated['security_event_page'] ?? 1),
            ])
            ->with('status', $deleted ? '已删除该条拦截记录，并同步清理对应自动封禁状态。' : '未找到要删除的拦截记录。');
    }

    public function clearEvents(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'security.manage');

        $validated = $request->validate([
            'security_event_filter' => ['nullable', Rule::in(['all', 'critical', 'high', 'medium'])],
        ]);

        $filter = (string) ($validated['security_event_filter'] ?? 'all');
        $result = $this->siteSecurity->clearSiteSecurityEventRecords($siteId, $filter);

        $this->logOperation(
            'site',
            'security',
            'security_clear_event_records',
            $siteId,
            (int) $request->user()->id,
            'site_security_event',
            null,
            $result + ['risk_filter' => $filter],
            $request,
        );

        return redirect()
            ->route('admin.security.index', [
                'security_modal' => 'events',
                'security_event_filter' => $filter,
                'security_event_page' => 1,
            ])
            ->with('status', $result['deleted_events'] > 0
                ? sprintf('已清除 %d 条拦截记录，影响 %d 个 IP。', (int) $result['deleted_events'], (int) $result['affected_ips'])
                : '当前筛选条件下没有可清除的拦截记录。');
    }

    public function deleteSuspiciousIp(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'security.manage');

        $validated = $request->validate([
            'client_ip' => ['required', 'ip'],
            'security_ip_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $clientIp = trim((string) $validated['client_ip']);
        $deleted = $this->siteSecurity->deleteSiteSuspiciousIpRecord($siteId, $clientIp);

        if ($deleted) {
            $this->logOperation(
                'site',
                'security',
                'security_delete_ip_record',
                $siteId,
                (int) $request->user()->id,
                'site_security_ip',
                null,
                ['client_ip' => $clientIp],
                $request,
            );
        }

        return redirect()
            ->route('admin.security.index', [
                'security_modal' => 'ips',
                'security_ip_page' => (int) ($validated['security_ip_page'] ?? 1),
            ])
            ->with('status', $deleted ? '已清除该 IP 的自动记录，并解除对应自动临时限制。' : '未找到要清除的 IP 记录。');
    }

    public function clearSuspiciousIps(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'security.manage');

        $result = $this->siteSecurity->clearSiteSuspiciousIpRecords($siteId);

        $this->logOperation(
            'site',
            'security',
            'security_clear_ip_records',
            $siteId,
            (int) $request->user()->id,
            'site_security_ip',
            null,
            $result,
            $request,
        );

        return redirect()
            ->route('admin.security.index', [
                'security_modal' => 'ips',
                'security_ip_page' => 1,
            ])
            ->with('status', $result['deleted_ips'] > 0
                ? sprintf('已清除 %d 个可疑 IP 画像，并删除 %d 条相关拦截记录。', (int) $result['deleted_ips'], (int) $result['deleted_events'])
                : '当前没有可清除的可疑 IP 画像。');
    }
}
