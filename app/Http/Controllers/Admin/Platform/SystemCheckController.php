<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use App\Support\SystemChecks\DatabaseHealthCheck;
use App\Support\SystemChecks\DeployHealthCheck;
use App\Support\SystemChecks\RuntimeHealthCheck;
use App\Support\SystemChecks\StaticVendorHealthCheck;
use App\Support\SystemChecks\StaticVendorManager;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class SystemCheckController extends Controller
{
    public function __construct(
        protected DatabaseHealthCheck $databaseHealthCheck,
        protected RuntimeHealthCheck $runtimeHealthCheck,
        protected DeployHealthCheck $deployHealthCheck,
        protected StaticVendorHealthCheck $staticVendorHealthCheck,
        protected StaticVendorManager $staticVendorManager,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorizePlatform($request, 'system.setting.manage');
        $currentSite = $this->currentSite($request);

        $groups = [
            $this->databaseHealthCheck->inspect(),
            $this->runtimeHealthCheck->inspect(),
            $this->deployHealthCheck->inspect(),
            $this->staticVendorHealthCheck->inspect(),
        ];

        $counts = [
            'ok' => 0,
            'warning' => 0,
            'error' => 0,
        ];

        foreach ($groups as $group) {
            $counts[$group['status']]++;
        }

        return view('admin.platform.system-checks.index', [
            'currentSite' => $currentSite,
            'groups' => $groups,
            'counts' => $counts,
            'overallStatus' => $this->overallStatus($groups),
            'checkedAt' => now(),
        ]);
    }

    public function upgradeStaticVendor(Request $request, string $asset): RedirectResponse
    {
        $this->authorizePlatform($request, 'system.setting.manage');

        try {
            $result = $this->staticVendorManager->upgrade($asset);

            $message = ! empty($result['updated'])
                ? sprintf('%s 已升级到 %s。', strtoupper((string) $result['package']), (string) $result['version'])
                : sprintf('%s 当前已经是最新版本。', strtoupper((string) $result['package']));

            return redirect()
                ->route('admin.platform.system-checks.index')
                ->with('status', $message);
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.platform.system-checks.index')
                ->with('status', '升级失败：'.$exception->getMessage());
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     */
    protected function overallStatus(array $groups): string
    {
        $priority = ['ok' => 0, 'warning' => 1, 'error' => 2];
        $max = 0;

        foreach ($groups as $group) {
            $max = max($max, $priority[$group['status']] ?? 0);
        }

        return array_search($max, $priority, true) ?: 'ok';
    }
}
