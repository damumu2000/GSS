<?php

namespace App\Support;

use App\Support\SystemChecks\MailQueueHealthCheck;
use App\Support\SystemChecks\StaticVendorHealthCheck;
use App\Support\SystemChecks\StaticVendorManager;
use Illuminate\Support\Facades\Cache;

class PlatformSystemStatus
{
    public function __construct(
        protected SystemSettings $systemSettings,
        protected MailQueueHealthCheck $mailQueueHealthCheck,
        protected StaticVendorHealthCheck $staticVendorHealthCheck,
        protected StaticVendorManager $staticVendorManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardSummary(): array
    {
        return [
            'checked_at' => now('Asia/Shanghai')->format('Y-m-d H:i:s'),
            'items' => [
                $this->mailQueueStatusItem(),
                $this->laravelVersionStatusItem(),
                $this->jqueryVersionStatusItem(),
                $this->opcacheStatusItem(),
                $this->frontendPageCacheStatusItem(),
                $this->imageProcessingStatusItem(),
            ],
        ];
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
            'title' => '邮件服务',
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
            'meta' => '当前 v'.$currentVersion.' · 最新 v'.$latestVersion,
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
            'title' => '上传图片自动处理',
            'state' => $state,
            'status_class' => $statusClass,
            'meta' => '自动缩小：'.($resizeEnabled ? '开' : '关').' · 自动压缩：'.($compressEnabled ? '开' : '关'),
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
    protected function frontendPageCacheStatusItem(): array
    {
        $enabled = FrontendPageCache::enabled();
        $ttl = FrontendPageCache::ttl();
        $store = (string) config('cache.default', 'file');
        $driver = (string) config("cache.stores.{$store}.driver", $store);

        if (! $enabled || $ttl <= 0) {
            return [
                'title' => '前台整页缓存',
                'state' => '未启用',
                'status_class' => 'draft',
                'meta' => '缓存驱动：'.$driver.' · TTL：'.$ttl.' 秒',
                'detail' => '当前不会缓存前台页面。需要启用时请配置 FRONTEND_PAGE_CACHE_ENABLED=true。',
                'action_url' => route('admin.platform.system-checks.index'),
            ];
        }

        try {
            Cache::put('frontend-page-cache:health-check', 'ok', 30);
            $writable = Cache::get('frontend-page-cache:health-check') === 'ok';
        } catch (\Throwable $exception) {
            $writable = false;
            $message = $exception->getMessage();
        }

        $fileCachePath = $driver === 'file'
            ? (string) config("cache.stores.{$store}.path", storage_path('framework/cache/data'))
            : '';

        if (! $writable) {
            return [
                'title' => '前台整页缓存',
                'state' => '异常',
                'status_class' => 'draft',
                'meta' => '缓存驱动：'.$driver.' · TTL：'.$ttl.' 秒',
                'detail' => $driver === 'file'
                    ? '缓存写入失败，请检查目录权限：'.$fileCachePath
                    : '缓存写入失败：'.($message ?? '请检查缓存服务配置。'),
                'action_url' => route('admin.platform.system-checks.index'),
            ];
        }

        return [
            'title' => '前台整页缓存',
            'state' => '正常',
            'status_class' => 'published',
            'meta' => '缓存驱动：'.$driver.' · TTL：'.$ttl.' 秒',
            'detail' => $driver === 'file'
                ? '缓存目录可写，前台公开 GET 页面可正常写入整页缓存。'
                : '缓存服务可写，前台公开 GET 页面可正常写入整页缓存。',
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
            'meta' => '命中率：'.$hitRate.'% · 内存：'.$usedRatio.'% · 脚本：'.$scripts,
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

        return $current.' · 最新 v'.$latestVersion;
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
