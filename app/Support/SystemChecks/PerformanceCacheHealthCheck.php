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
            $this->redisCacheItem(),
            $this->frontendPageCacheItem(),
        ];

        return [
            'key' => 'performance-cache',
            'title' => '性能缓存检查',
            'status' => $this->overallStatus($items),
            'summary' => '检查 OPcache、Redis 应用缓存与前台整页缓存的运行状态。',
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
    protected function redisCacheItem(): array
    {
        [$store, $driver, $driverLabel] = $this->cacheStoreSummary();
        $usesRedis = $driver === 'redis'
            || ($driver === 'failover' && in_array('redis', (array) config("cache.stores.{$store}.stores", []), true));

        if (! $usesRedis) {
            return [
                'label' => 'Redis 应用缓存',
                'status' => 'warning',
                'value' => '未接入',
                'message' => '当前默认应用缓存链路未使用 Redis。',
                'suggestion' => '如需以 Redis 承载应用缓存，请将 CACHE_STORE 配置为包含 redis 的缓存链路。',
                'details' => '当前缓存驱动：'.$driverLabel,
            ];
        }

        $message = $this->redisFailureMessage($store, $driver);

        if ($message !== null) {
            $fallbackFailure = $driver === 'failover' ? $this->defaultCacheFailureMessage() : null;

            if ($fallbackFailure !== null) {
                return [
                    'label' => 'Redis 应用缓存',
                    'status' => 'error',
                    'value' => '不可用',
                    'message' => 'Redis 与后备缓存均无法正常读写。',
                    'suggestion' => '请检查 Redis 服务状态，以及 database 后备缓存的表结构、连接和权限。',
                    'details' => $message.'；后备缓存：'.$fallbackFailure,
                ];
            }

            return [
                'label' => 'Redis 应用缓存',
                'status' => $driver === 'failover' ? 'warning' : 'error',
                'value' => $driver === 'failover' ? '已降级' : '不可用',
                'message' => $driver === 'failover'
                    ? 'Redis 不可用，应用缓存当前将由后备存储接管。'
                    : 'Redis 应用缓存读写失败。',
                'suggestion' => '请检查 Redis 服务状态、连接配置、认证信息和访问权限。',
                'details' => $message,
            ];
        }

        return [
            'label' => 'Redis 应用缓存',
            'status' => 'ok',
            'value' => '运行中',
            'message' => 'Redis 应用缓存连接和读写正常。',
            'suggestion' => '',
            'details' => '默认缓存链路：'.$driverLabel,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function frontendPageCacheItem(): array
    {
        $enabled = FrontendPageCache::enabled();
        $ttl = FrontendPageCache::ttl();

        if (! $enabled || $ttl <= 0) {
            return [
                'label' => '前台整页缓存',
                'status' => 'warning',
                'value' => '未启用',
                'message' => '当前不会缓存前台页面。',
                'suggestion' => '如需提升前台访问速度，请配置 FRONTEND_PAGE_CACHE_ENABLED=true 且 TTL 大于 0。',
                'details' => 'TTL：'.$ttl.' 秒',
            ];
        }

        return [
            'label' => '前台整页缓存',
            'status' => 'ok',
            'value' => '已启用',
            'message' => '符合条件的前台公开页面将使用整页缓存。',
            'suggestion' => '',
            'details' => 'TTL：'.$ttl.' 秒 · 存储状态见“Redis 应用缓存”',
        ];
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    protected function cacheStoreSummary(): array
    {
        $store = (string) config('cache.default', 'failover');
        $driver = (string) config("cache.stores.{$store}.driver", $store);

        if ($driver !== 'failover') {
            return [$store, $driver, $driver];
        }

        $stores = array_values(array_filter((array) config("cache.stores.{$store}.stores", []), fn ($value): bool => is_string($value) && $value !== ''));
        $labels = [];

        foreach ($stores as $fallbackStore) {
            $labels[] = (string) config("cache.stores.{$fallbackStore}.driver", $fallbackStore);
        }

        $label = implode(' -> ', $labels);

        return [$store, $driver, $label !== '' ? $label.'（故障切换）' : 'failover（故障切换）'];
    }

    protected function redisFailureMessage(string $store, string $driver): ?string
    {
        $usesRedis = $driver === 'redis'
            || ($driver === 'failover' && in_array('redis', (array) config("cache.stores.{$store}.stores", []), true));

        if (! $usesRedis) {
            return null;
        }

        try {
            Cache::store('redis')->put('frontend-page-cache:redis-system-check', 'ok', 30);

            if (Cache::store('redis')->get('frontend-page-cache:redis-system-check') !== 'ok') {
                return 'Redis 读写校验失败。';
            }

            Cache::store('redis')->forget('frontend-page-cache:redis-system-check');

            return null;
        } catch (\Throwable $exception) {
            return $exception->getMessage();
        }
    }

    protected function defaultCacheFailureMessage(): ?string
    {
        try {
            Cache::put('application-cache:fallback-system-check', 'ok', 30);

            if (Cache::get('application-cache:fallback-system-check') !== 'ok') {
                return '后备缓存读写校验失败。';
            }

            Cache::forget('application-cache:fallback-system-check');

            return null;
        } catch (\Throwable $exception) {
            return $exception->getMessage();
        }
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
