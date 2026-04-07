<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use App\Support\SystemChecks\DatabaseHealthCheck;
use App\Support\SystemChecks\DeployHealthCheck;
use App\Support\SystemChecks\RuntimeHealthCheck;
use App\Support\SystemChecks\StaticVendorHealthCheck;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemCheckController extends Controller
{
    public function __construct(
        protected DatabaseHealthCheck $databaseHealthCheck,
        protected RuntimeHealthCheck $runtimeHealthCheck,
        protected DeployHealthCheck $deployHealthCheck,
        protected StaticVendorHealthCheck $staticVendorHealthCheck,
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
