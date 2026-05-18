<?php

namespace App\Support\SystemChecks;

use App\Support\FrontendPageCache;
use App\Support\SiteStorageUsage;
use Illuminate\Support\Facades\Cache;

class PerformanceCacheHealthCheck
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $items = [
            $this->opcacheItem(),
            $this->frontendPageCacheItem(),
        ];

        return [
            'key' => 'performance-cache',
            'title' => '性能缓存检查',
            'status' => $this->overallStatus($items),
            'summary' => '检查 OPcache 与前台整页缓存的启用状态、写入能力和关键运行指标。',
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function opcacheItem(): array
    {
        if (! extension_loaded('Zend OPcache')) {
            return [
                'label' => 'OPcache',
                'status' => 'error',
                'value' => '未安装',
                'message' => '当前 PHP 未加载 Zend OPcache 扩展。',
                'suggestion' => '商用环境建议启用 OPcache，以降低 PHP 文件解析开销。',
                'details' => '',
            ];
        }

        $enabled = $this->iniBool('opcache.enable');
        $cliEnabled = $this->iniBool('opcache.enable_cli');

        if (! $enabled) {
            return [
                'label' => 'OPcache',
                'status' => 'error',
                'value' => '未启用',
                'message' => 'OPcache 未对 Web 请求启用。',
                'suggestion' => '请在 PHP 配置中开启 opcache.enable=1，并重载 PHP-FPM。',
                'details' => 'Web：关 · CLI：'.($cliEnabled ? '开' : '关'),
            ];
        }

        $status = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;

        if (! is_array($status)) {
            return [
                'label' => 'OPcache',
                'status' => 'warning',
                'value' => '已启用',
                'message' => 'OPcache 已启用，但当前无法读取运行统计。',
                'suggestion' => '如需观察命中率和内存占用，请确认 opcache_get_status 可用。',
                'details' => 'Web：开 · CLI：'.($cliEnabled ? '开' : '关'),
            ];
        }

        $statistics = is_array($status['opcache_statistics'] ?? null) ? $status['opcache_statistics'] : [];
        $memory = is_array($status['memory_usage'] ?? null) ? $status['memory_usage'] : [];
        $hitRate = round((float) ($statistics['opcache_hit_rate'] ?? 0), 1);
        $usedMemory = (int) ($memory['used_memory'] ?? 0);
        $freeMemory = (int) ($memory['free_memory'] ?? 0);
        $wastedMemory = (int) ($memory['wasted_memory'] ?? 0);
        $totalMemory = max(1, $usedMemory + $freeMemory + $wastedMemory);
        $usedRatio = round(($usedMemory / $totalMemory) * 100, 1);
        $scripts = (int) ($statistics['num_cached_scripts'] ?? 0);
        $statusLevel = $hitRate >= 90 || $scripts === 0 ? 'ok' : 'warning';

        return [
            'label' => 'OPcache',
            'status' => $statusLevel,
            'value' => $hitRate > 0 ? '命中率 '.$hitRate.'%' : '已启用',
            'message' => $statusLevel === 'ok'
                ? 'OPcache 运行状态正常。'
                : 'OPcache 已启用，但命中率偏低，可能刚重载或仍在预热。',
            'suggestion' => $statusLevel === 'ok' ? '' : '观察一段时间后再判断，若长期偏低请检查配置。',
            'details' => '内存 '.SiteStorageUsage::formatBytes($usedMemory).' / '.SiteStorageUsage::formatBytes($totalMemory).'（'.$usedRatio.'%） · 脚本 '.$scripts.' · CLI：'.($cliEnabled ? '开' : '关'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function frontendPageCacheItem(): array
    {
        $enabled = FrontendPageCache::enabled();
        $ttl = FrontendPageCache::ttl();
        $store = (string) config('cache.default', 'file');
        $driver = (string) config("cache.stores.{$store}.driver", $store);

        if (! $enabled || $ttl <= 0) {
            return [
                'label' => '前台整页缓存',
                'status' => 'warning',
                'value' => '未启用',
                'message' => '当前不会缓存前台页面。',
                'suggestion' => '如需提升前台访问速度，请配置 FRONTEND_PAGE_CACHE_ENABLED=true 且 TTL 大于 0。',
                'details' => '缓存驱动：'.$driver.' · TTL：'.$ttl.' 秒',
            ];
        }

        try {
            Cache::put('frontend-page-cache:system-check', 'ok', 30);
            $writable = Cache::get('frontend-page-cache:system-check') === 'ok';
        } catch (\Throwable $exception) {
            $writable = false;
            $message = $exception->getMessage();
        }

        if (! $writable) {
            $path = $driver === 'file'
                ? (string) config("cache.stores.{$store}.path", storage_path('framework/cache/data'))
                : '';

            return [
                'label' => '前台整页缓存',
                'status' => 'error',
                'value' => '写入失败',
                'message' => '整页缓存依赖的缓存存储不可写。',
                'suggestion' => $driver === 'file'
                    ? '请检查 storage/framework/cache/data 目录是否存在，并确认 Web 服务用户有写权限。'
                    : '请检查当前缓存服务连接和权限。',
                'details' => $path !== '' ? $path : ($message ?? ''),
            ];
        }

        return [
            'label' => '前台整页缓存',
            'status' => 'ok',
            'value' => '已启用',
            'message' => '前台整页缓存可正常写入。',
            'suggestion' => '',
            'details' => '缓存驱动：'.$driver.' · TTL：'.$ttl.' 秒',
        ];
    }

    protected function iniBool(string $key): bool
    {
        return filter_var(ini_get($key), FILTER_VALIDATE_BOOL);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function overallStatus(array $items): string
    {
        $priority = ['ok' => 0, 'warning' => 1, 'error' => 2];
        $max = 0;

        foreach ($items as $item) {
            $max = max($max, $priority[$item['status'] ?? 'ok'] ?? 0);
        }

        return array_search($max, $priority, true) ?: 'ok';
    }
}
