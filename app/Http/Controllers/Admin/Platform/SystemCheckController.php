<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use App\Support\SystemChecks\DatabaseHealthCheck;
use App\Support\SystemChecks\DeployHealthCheck;
use App\Support\SystemChecks\RuntimeHealthCheck;
use App\Support\SystemChecks\StaticVendorHealthCheck;
use App\Support\SystemChecks\StaticVendorManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class SystemCheckController extends Controller
{
    protected const CACHE_ACTIONS = [
        'view' => [
            'command' => 'view:clear',
            'label' => '视图缓存',
            'description' => '适合模板、Blade 页面、提示文案更新后使用。',
            'success' => '视图缓存已清理。',
        ],
        'config' => [
            'command' => 'config:clear',
            'label' => '配置缓存',
            'description' => '适合环境变量或系统配置变更后使用。',
            'success' => '配置缓存已清理。',
        ],
        'route' => [
            'command' => 'route:clear',
            'label' => '路由缓存',
            'description' => '适合新增、调整后台或前台路由后使用。',
            'success' => '路由缓存已清理。',
        ],
        'app' => [
            'command' => 'cache:clear',
            'label' => '应用缓存',
            'description' => '清理业务缓存键，不影响会话和队列数据。',
            'success' => '应用缓存已清理。',
        ],
    ];

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
        $this->authorizePlatform($request, 'system.check.view');
        $currentSite = $this->currentSite($request);

        $groups = [
            $this->staticVendorHealthCheck->inspect(),
            $this->databaseHealthCheck->inspect(),
            $this->runtimeHealthCheck->inspect(),
            $this->deployHealthCheck->inspect(),
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
            'cacheActions' => self::CACHE_ACTIONS,
            'activeTab' => in_array((string) $request->query('tab'), ['cache', 'base'], true) ? (string) $request->query('tab') : 'base',
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

    public function clearCache(Request $request, string $action): RedirectResponse
    {
        $this->authorizePlatform($request, 'system.setting.manage');

        if (! $this->isSuperAdmin((int) $request->user()->id)) {
            return redirect()
                ->route('admin.platform.system-checks.index')
                ->with('status', '只有总管理员可以执行缓存清理。');
        }

        $definition = self::CACHE_ACTIONS[$action] ?? null;

        if (! is_array($definition)) {
            return redirect()
                ->route('admin.platform.system-checks.index')
                ->with('status', '不支持的缓存清理操作。');
        }

        $lock = Cache::lock('system-checks:cache-action:'.$action, 30);

        if (! $lock->get()) {
            return redirect()
                ->route('admin.platform.system-checks.index')
                ->with('status', $definition['label'].'正在处理中，请稍后再试。');
        }

        try {
            Artisan::call($definition['command']);
            $output = trim((string) Artisan::output());

            $this->logOperation(
                'platform',
                'system_check',
                'clear_cache_'.$action,
                null,
                (int) $request->user()->id,
                'cache_action',
                null,
                [
                    'action' => $action,
                    'label' => $definition['label'],
                    'command' => $definition['command'],
                    'output' => $output === '' ? null : mb_substr($output, 0, 500),
                ],
                $request,
            );

            return redirect()
                ->route('admin.platform.system-checks.index')
                ->with('status', $definition['success']);
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.platform.system-checks.index')
                ->with('status', $definition['label'].'清理失败：'.$exception->getMessage());
        } finally {
            optional($lock)->release();
        }
    }

    public function clearAllCache(Request $request): RedirectResponse
    {
        $this->authorizePlatform($request, 'system.setting.manage');

        if (! $this->isSuperAdmin((int) $request->user()->id)) {
            return redirect()
                ->route('admin.platform.system-checks.index', ['tab' => 'cache'])
                ->with('status', '只有总管理员可以执行缓存清理。');
        }

        $lock = Cache::lock('system-checks:cache-action:all', 45);

        if (! $lock->get()) {
            return redirect()
                ->route('admin.platform.system-checks.index', ['tab' => 'cache'])
                ->with('status', '一键清除正在处理中，请稍后再试。');
        }

        try {
            Artisan::call('optimize:clear');
            $output = trim((string) Artisan::output());

            $this->logOperation(
                'platform',
                'system_check',
                'clear_cache_all',
                null,
                (int) $request->user()->id,
                'cache_action',
                null,
                [
                    'action' => 'all',
                    'label' => '一键清除',
                    'command' => 'optimize:clear',
                    'output' => $output === '' ? null : mb_substr($output, 0, 500),
                ],
                $request,
            );

            return redirect()
                ->route('admin.platform.system-checks.index', ['tab' => 'cache'])
                ->with('status', '已完成一键清除。');
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.platform.system-checks.index', ['tab' => 'cache'])
                ->with('status', '一键清除失败：'.$exception->getMessage());
        } finally {
            optional($lock)->release();
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
