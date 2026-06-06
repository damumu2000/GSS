<?php

namespace App\Support;

use App\Support\SystemChecks\DatabaseHealthCheck;
use App\Support\SystemChecks\DeployHealthCheck;
use App\Support\SystemChecks\MailQueueHealthCheck;
use App\Support\SystemChecks\PerformanceCacheHealthCheck;
use App\Support\SystemChecks\RuntimeHealthCheck;
use App\Support\SystemChecks\SchedulerHealthCheck;
use App\Support\SystemChecks\SiteSecurityHealthCheck;
use App\Support\SystemChecks\StaticVendorHealthCheck;
use App\Support\SystemChecks\StaticVendorManager;

class PlatformSystemStatus
{
    public function __construct(
        protected SystemSettings $systemSettings,
        protected DatabaseHealthCheck $databaseHealthCheck,
        protected DeployHealthCheck $deployHealthCheck,
        protected MailQueueHealthCheck $mailQueueHealthCheck,
        protected PerformanceCacheHealthCheck $performanceCacheHealthCheck,
        protected RuntimeHealthCheck $runtimeHealthCheck,
        protected SchedulerHealthCheck $schedulerHealthCheck,
        protected SiteSecurityHealthCheck $siteSecurityHealthCheck,
        protected StaticVendorHealthCheck $staticVendorHealthCheck,
        protected StaticVendorManager $staticVendorManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardSummary(): array
    {
        $items = [
            $this->mailQueueStatusItem(),
            $this->laravelVersionStatusItem(),
            $this->jqueryVersionStatusItem(),
            $this->opcacheStatusItem(),
            $this->redisCacheStatusItem(),
            $this->imageProcessingStatusItem(),
        ];
        $systemCheckStatus = $this->systemCheckStatus();

        return [
            'checked_at' => now('Asia/Shanghai')->format('Y-m-d H:i:s'),
            'overall_status' => $this->maxStatus([$this->overallStatus($items), $systemCheckStatus]),
            'items' => $items,
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $items
     */
    protected function overallStatus(array $items): string
    {
        $statuses = [];

        foreach ($items as $item) {
            if (($item['status_class'] ?? '') === 'draft') {
                $statuses[] = 'error';
                continue;
            }

            if (($item['status_class'] ?? '') === 'pending') {
                $statuses[] = 'warning';
                continue;
            }

            $statuses[] = 'ok';
        }

        return $this->maxStatus($statuses);
    }

    protected function systemCheckStatus(): string
    {
        $groups = [
            $this->staticVendorHealthCheck->inspect(),
            $this->mailQueueHealthCheck->inspect(),
            $this->performanceCacheHealthCheck->inspect(),
            $this->schedulerHealthCheck->inspect(),
            $this->siteSecurityHealthCheck->inspect(),
            $this->databaseHealthCheck->inspect(),
            $this->runtimeHealthCheck->inspect(),
            $this->deployHealthCheck->inspect(),
        ];

        return $this->maxStatus(array_map(
            static fn (array $group): string => (string) ($group['status'] ?? 'ok'),
            $groups
        ));
    }

    /**
     * @param  array<int, string>  $statuses
     */
    protected function maxStatus(array $statuses): string
    {
        $priority = ['ok' => 0, 'warning' => 1, 'error' => 2];
        $max = 0;

        foreach ($statuses as $status) {
            $max = max($max, $priority[$status] ?? 0);
        }

        return array_search($max, $priority, true) ?: 'ok';
    }

    /**
     * @return array<string, string>
     */
    protected function mailQueueStatusItem(): array
    {
        $item = $this->mailQueueHealthCheck->inspect()['items'][0] ?? [];
        $state = str_contains((string) ($item['value'] ?? ''), '/')
            ? trim((string) explode('/', (string) $item['value'], 2)[1])
            : '未运行';
        $statusClass = match (($item['status'] ?? 'warning')) {
            'ok' => 'published',
            'warning' => 'draft',
            default => 'pending',
        };

        return [
            'title' => '邮件队列服务',
            'state' => $state,
            'status_class' => $statusClass,
            'meta' => (string) ($item['details'] ?? ''),
            'detail' => (string) ($item['message'] ?? ''),
            'action_url' => route('admin.platform.settings.index', ['tab' => 'mail']),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function laravelVersionStatusItem(): array
    {
        $currentVersion = $this->currentLaravelVersion();
        $latestVersion = $this->latestLaravelVersion();

        if ($latestVersion === '') {
            return [
                'title' => 'Laravel 版本',
                'state' => '检测失败',
                'status_class' => 'draft',
                'meta' => '当前 v'.$currentVersion,
                'detail' => '当前无法获取最新 Laravel 稳定版信息，已使用缓存优先策略避免频繁请求。',
            ];
        }

        $upgradeNeeded = version_compare($currentVersion, $latestVersion, '<');

        return [
            'title' => 'Laravel 版本',
            'state' => $upgradeNeeded ? '可升级' : '已最新',
            'status_class' => $upgradeNeeded ? 'pending' : 'published',
            'meta' => '当前 v'.$currentVersion."\n".'最新 v'.$latestVersion,
            'detail' => $upgradeNeeded
                ? '当前框架版本低于最新稳定版，建议在测试通过后安排升级。'
                : '当前框架版本已是最新稳定版。',
            'action_url' => route('admin.platform.system-checks.index'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function imageProcessingStatusItem(): array
    {
        $resizeEnabled = $this->systemSettings->attachmentImageAutoResizeEnabled();
        $compressEnabled = $this->systemSettings->attachmentImageAutoCompressEnabled();

        $state = match (true) {
            $resizeEnabled && $compressEnabled => '已开启',
            $resizeEnabled || $compressEnabled => '部分开启',
            default => '未开启',
        };

        $statusClass = match ($state) {
            '已开启' => 'published',
            '部分开启' => 'pending',
            default => 'draft',
        };

        return [
            'title' => '图片处理',
            'state' => $state,
            'status_class' => $statusClass,
            'meta' => '自动缩小：'.($resizeEnabled ? '开' : '关')."\n".'自动压缩：'.($compressEnabled ? '开' : '关'),
            'detail' => '用于控制上传图片时是否自动缩小到限制尺寸，以及是否执行压缩处理。',
            'action_url' => route('admin.platform.settings.index', ['tab' => 'upload']),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function jqueryVersionStatusItem(): array
    {
        $manifest = $this->staticVendorManager->manifest();
        $asset = $manifest['jquery'] ?? null;

        if (! is_array($asset)) {
            return [
                'title' => 'jQuery 公共库',
                'state' => '未登记',
                'status_class' => 'draft',
                'meta' => '未找到 /pub/jquery.min.js 静态资源清单',
                'detail' => '请检查 public/vendor/vendor-assets.json 中是否已登记 jquery 项。',
                'action_url' => route('admin.platform.system-checks.index'),
            ];
        }

        $currentVersion = (string) ($asset['version'] ?? '');
        $latestVersion = (string) ($this->staticVendorManager->latestVersion('jquery') ?? '');
        $file = (string) ($asset['file'] ?? '');
        $fullPath = base_path(ltrim($file, '/'));
        $expectedSha = strtolower((string) ($asset['sha256'] ?? ''));
        $actualSha = is_file($fullPath) ? strtolower(hash_file('sha256', $fullPath)) : '';

        if (! is_file($fullPath)) {
            return [
                'title' => 'jQuery 公共库',
                'state' => '异常',
                'status_class' => 'draft',
                'meta' => $this->versionMeta($currentVersion, $latestVersion),
                'detail' => '公共脚本文件不存在，请重新同步或恢复 public/pub/jquery.min.js。',
                'action_url' => route('admin.platform.system-checks.index'),
            ];
        }

        if ($expectedSha !== '' && $actualSha !== $expectedSha) {
            return [
                'title' => 'jQuery 公共库',
                'state' => '异常',
                'status_class' => 'draft',
                'meta' => $this->versionMeta($currentVersion, $latestVersion),
                'detail' => '公共脚本文件校验和与清单不一致，请确认是否被手动替换。',
                'action_url' => route('admin.platform.system-checks.index'),
            ];
        }

        if ($latestVersion === '') {
            return [
                'title' => 'jQuery 公共库',
                'state' => '检测失败',
                'status_class' => 'draft',
                'meta' => $this->versionMeta($currentVersion, ''),
                'detail' => '当前无法获取 jQuery 最新版本信息，已使用缓存优先策略避免频繁请求。',
                'action_url' => route('admin.platform.system-checks.index'),
            ];
        }

        $upgradeNeeded = $currentVersion !== '' && version_compare($currentVersion, $latestVersion, '<');

        return [
            'title' => 'jQuery 公共库',
            'state' => $upgradeNeeded ? '可升级' : '已最新',
            'status_class' => $upgradeNeeded ? 'pending' : 'published',
            'meta' => $this->versionMeta($currentVersion, $latestVersion),
            'detail' => $upgradeNeeded
                ? '当前 jQuery 公共库低于最新版本，建议在测试通过后升级。'
                : '当前 jQuery 公共库已是最新版本。',
            'action_url' => route('admin.platform.system-checks.index'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function redisCacheStatusItem(): array
    {
        $item = collect($this->performanceCacheHealthCheck->inspect()['items'])
            ->firstWhere('label', 'Redis 应用缓存') ?? [];
        $frontendPageCacheEnabled = FrontendPageCache::enabled() && FrontendPageCache::ttl() > 0;

        return [
            'title' => 'Redis 应用缓存',
            'state' => (string) ($item['value'] ?? '检查失败'),
            'status_class' => match ($item['status'] ?? 'error') {
                'ok' => 'published',
                'warning' => 'pending',
                default => 'draft',
            },
            'meta' => 'Redis：'.(($item['status'] ?? 'error') === 'ok' ? '运行' : '关闭')
                ."\n".'整页缓存：'.($frontendPageCacheEnabled ? '开启' : '关闭'),
            'detail' => (string) ($item['message'] ?? '当前无法获取 Redis 应用缓存状态。'),
            'action_url' => route('admin.platform.system-checks.index'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function opcacheStatusItem(): array
    {
        $enabled = filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOL);
        $cliEnabled = filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOL);

        if (! extension_loaded('Zend OPcache')) {
            return [
                'title' => 'OPcache',
                'state' => '未安装',
                'status_class' => 'draft',
                'meta' => 'PHP 扩展未加载',
                'detail' => '当前 PHP 未加载 Zend OPcache 扩展。',
                'action_url' => route('admin.platform.system-checks.index'),
            ];
        }

        if (! $enabled) {
            return [
                'title' => 'OPcache',
                'state' => '未启用',
                'status_class' => 'draft',
                'meta' => 'Web：关 · CLI：'.($cliEnabled ? '开' : '关'),
                'detail' => 'OPcache 未对 Web 请求启用，PHP 文件每次请求仍需重新解析。',
                'action_url' => route('admin.platform.system-checks.index'),
            ];
        }

        $status = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;

        if (! is_array($status)) {
            return [
                'title' => 'OPcache',
                'state' => '已启用',
                'status_class' => 'published',
                'meta' => 'Web：开 · CLI：'.($cliEnabled ? '开' : '关'),
                'detail' => 'OPcache 已启用，但当前无法读取运行统计。',
                'action_url' => route('admin.platform.system-checks.index'),
            ];
        }

        $statistics = is_array($status['opcache_statistics'] ?? null) ? $status['opcache_statistics'] : [];
        $memory = is_array($status['memory_usage'] ?? null) ? $status['memory_usage'] : [];
        $hitRate = round((float) ($statistics['opcache_hit_rate'] ?? 0), 1);
        $usedMemory = (int) ($memory['used_memory'] ?? 0);
        $freeMemory = (int) ($memory['free_memory'] ?? 0);
        $totalMemory = max(1, $usedMemory + $freeMemory + (int) ($memory['wasted_memory'] ?? 0));
        $usedRatio = round(($usedMemory / $totalMemory) * 100, 1);
        $scripts = (int) ($statistics['num_cached_scripts'] ?? 0);
        $state = $hitRate >= 90 ? '正常' : ($hitRate > 0 ? '预热中' : '已启用');

        return [
            'title' => 'OPcache',
            'state' => $state,
            'status_class' => $hitRate >= 90 ? 'published' : 'pending',
            'meta' => '命中率：'.$hitRate.'%'."\n".'内存：'.$usedRatio.'% · 脚本：'.$scripts,
            'detail' => 'OPcache 正在缓存 PHP 编译结果，用于减少 PHP 文件解析开销。',
            'action_url' => route('admin.platform.system-checks.index'),
        ];
    }

    protected function versionMeta(string $currentVersion, string $latestVersion): string
    {
        $current = $currentVersion !== '' ? '当前 v'.$currentVersion : '当前未知';

        if ($latestVersion === '') {
            return $current;
        }

        return $current."\n".'最新 v'.$latestVersion;
    }

    protected function currentLaravelVersion(): string
    {
        $version = app()->version();

        if (preg_match('/(\d+\.\d+\.\d+)/', $version, $matches) === 1) {
            return $matches[1];
        }

        return ltrim($version, 'v');
    }

    protected function latestLaravelVersion(): string
    {
        return ltrim((string) ($this->staticVendorHealthCheck->latestLaravelVersionCached() ?? ''), 'v');
    }
}
