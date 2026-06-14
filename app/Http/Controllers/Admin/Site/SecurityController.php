<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Support\SiteSecurity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
        $eventSort = in_array((string) $request->query('security_event_sort', 'latest'), ['latest', 'risk'], true)
            ? (string) $request->query('security_event_sort', 'latest')
            : 'latest';
        $ipSort = in_array((string) $request->query('security_ip_sort', 'latest'), ['latest', 'risk'], true)
            ? (string) $request->query('security_ip_sort', 'latest')
            : 'latest';

        return view('admin.site.security.index', [
            'currentSite' => $currentSite,
            'sites' => $this->adminSites(),
            'showSiteSwitcher' => $this->shouldShowSiteSwitcher($request->user()->id),
            'security' => $this->siteSecurity->sitePagePayload($siteId),
            'canManageIpPolicy' => in_array('security.manage', $this->sitePermissionCodes((int) $request->user()->id, $siteId), true),
            'securityEventFilter' => $eventFilter,
            'securityEventSort' => $eventSort,
            'securityIpSort' => $ipSort,
            'securityEventsPaginator' => $this->siteSecurity->siteEventsModalPaginator($siteId, $eventFilter, (int) $request->query('security_event_page', 1), 20, $eventSort),
            'securityIpsPaginator' => $this->siteSecurity->siteSuspiciousIpsModalPaginator($siteId, (int) $request->query('security_ip_page', 1), 15, $ipSort),
            'securityIpPolicies' => $this->siteSecurity->siteIpPolicyLists($siteId),
            'securityIpPolicyLimit' => SiteSecurity::SITE_IP_POLICY_LIST_LIMIT,
        ]);
    }

    public function ipDetail(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'security.view');

        $validated = $request->validate([
            'client_ip' => ['required', 'ip'],
        ], $this->ipValidationMessages());

        $detail = $this->siteSecurity->siteIpDetailPayload($siteId, trim((string) $validated['client_ip']));

        abort_if($detail === null, 404);

        return view('admin.site.security.ip-detail', [
            'currentSite' => $currentSite,
            'sites' => $this->adminSites(),
            'showSiteSwitcher' => $this->shouldShowSiteSwitcher($request->user()->id),
            'detail' => $detail,
        ]);
    }

    public function storeIpPolicy(Request $request): RedirectResponse|JsonResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;

        $this->authorizeSite($request, $siteId, 'security.manage');

        $validated = $request->validate([
            'client_ip' => [
                Rule::requiredIf(fn (): bool => ! in_array((string) $request->input('action'), ['clear_allow', 'clear_block'], true)),
                'nullable',
                'ip',
            ],
            'action' => ['required', Rule::in($this->siteSecurity->siteIpPolicyActions(true))],
        ], $this->ipValidationMessages());

        $ip = trim((string) ($validated['client_ip'] ?? ''));
        $action = (string) $validated['action'];
        $this->siteSecurity->applySiteIpPolicy($siteId, $ip, $action, (int) $request->user()->id);
        $modal = in_array((string) $request->input('security_modal'), ['ips', 'policies'], true)
            ? (string) $request->input('security_modal')
            : (in_array((string) $request->header('X-Requested-Modal'), ['ips', 'policies'], true)
                ? (string) $request->header('X-Requested-Modal')
                : null);
        $redirectParams = [];

        if ($modal !== null) {
            $redirectParams['security_modal'] = $modal;

            if ($modal === 'ips') {
                $redirectParams['security_ip_page'] = max(1, (int) $request->input('security_ip_page', 1));
                $redirectParams['security_ip_sort'] = in_array((string) $request->input('security_ip_sort', 'latest'), ['latest', 'risk'], true)
                    ? (string) $request->input('security_ip_sort', 'latest')
                    : 'latest';
            }
        }

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

        $message = $this->siteSecurity->siteIpPolicyStatusMessage($action);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'modal' => $modal,
                'redirect' => route('admin.security.index', $redirectParams),
            ]);
        }

        return redirect()
            ->route('admin.security.index', $redirectParams)
            ->with('status', $message);
    }

    public function deleteEvent(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'security.manage');

        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'min:1'],
            'security_event_filter' => ['nullable', Rule::in(['all', 'critical', 'high', 'medium'])],
            'security_event_sort' => ['nullable', Rule::in(['latest', 'risk'])],
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
                'security_event_sort' => (string) ($validated['security_event_sort'] ?? 'latest'),
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
            'security_event_sort' => ['nullable', Rule::in(['latest', 'risk'])],
        ]);

        $filter = (string) ($validated['security_event_filter'] ?? 'all');
        $sort = (string) ($validated['security_event_sort'] ?? 'latest');
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
                'security_event_sort' => $sort,
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
            'security_ip_sort' => ['nullable', Rule::in(['latest', 'risk'])],
        ], $this->ipValidationMessages());

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
                'security_ip_sort' => (string) ($validated['security_ip_sort'] ?? 'latest'),
                'security_ip_page' => (int) ($validated['security_ip_page'] ?? 1),
            ])
            ->with('status', $deleted ? '已清除该 IP 的自动记录，并解除对应自动临时限制。' : '未找到要清除的 IP 记录。');
    }

    public function clearSuspiciousIps(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'security.manage');
        $sort = in_array((string) $request->input('security_ip_sort', 'latest'), ['latest', 'risk'], true)
            ? (string) $request->input('security_ip_sort', 'latest')
            : 'latest';

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
                'security_ip_sort' => $sort,
                'security_ip_page' => 1,
            ])
            ->with('status', $result['deleted_ips'] > 0
                ? sprintf('已清除 %d 个可疑 IP 画像，并删除 %d 条相关拦截记录。', (int) $result['deleted_ips'], (int) $result['deleted_events'])
                : '当前没有可清除的可疑 IP 画像。');
    }

    /**
     * @return array<string, string>
     */
    protected function ipValidationMessages(): array
    {
        return [
            'client_ip.required' => '请输入 IP 地址。',
            'client_ip.ip' => '请输入正确的 IP 地址。',
            'action.required' => '请选择要执行的操作。',
            'action.in' => '当前操作无效，请刷新页面后重试。',
            'security_ip_page.integer' => '分页参数无效，请刷新页面后重试。',
            'security_ip_page.min' => '分页参数无效，请刷新页面后重试。',
        ];
    }
}
