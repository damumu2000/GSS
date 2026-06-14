<?php

namespace App\Support\SystemChecks;

use App\Support\SiteSecurity;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SiteSecurityHealthCheck
{
    public function __construct(
        protected SiteSecurity $siteSecurity,
        protected SystemSettings $systemSettings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $items = [
            $this->protectionItem(),
            $this->runtimeBlockCacheItem(),
            $this->statsBufferItem(),
            $this->storageItem(),
        ];

        return [
            'key' => 'site-security',
            'title' => '安护盾健康',
            'status' => $this->overallStatus($items),
            'summary' => '检查安护盾开关、运行态封禁缓存、统计缓冲和关键记录表是否可用。',
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function protectionItem(): array
    {
        if (! $this->siteSecurity->protectionEnabled()) {
            return [
                'label' => '防护开关',
                'status' => 'warning',
                'value' => '未启用',
                'message' => '当前安护盾主开关未启用。',
                'suggestion' => '商用环境建议启用安护盾，并先使用标准模式观察运行情况。',
                'details' => '',
            ];
        }

        return [
            'label' => '防护开关',
            'status' => 'ok',
            'value' => '运行中',
            'message' => '安护盾主开关已启用。',
            'suggestion' => '',
            'details' => '连续攻击封禁：'.($this->systemSettings->securityMaliciousAutoBlockEnabled() ? '开' : '关'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function runtimeBlockCacheItem(): array
    {
        $store = $this->securityLimiterStore();
        $driver = (string) config("cache.stores.{$store}.driver", $store);

        if ($driver !== 'redis') {
            return [
                'label' => '运行态封禁缓存',
                'status' => $this->productionRiskStatus(),
                'value' => '非 Redis',
                'message' => '安护盾封禁和限流没有使用共享 Redis。',
                'suggestion' => '商用环境请配置 CACHE_LIMITER=security，并确保 cache.stores.security 使用 Redis。',
                'details' => '限流器缓存：'.$store.' / '.$driver,
            ];
        }

        $key = 'site-security-health:runtime-block:'.sha1((string) microtime(true));

        try {
            if ($driver === 'redis') {
                Cache::store($store)->put($key.':redis-check', 'ok', 30);

                if (Cache::store($store)->get($key.':redis-check') !== 'ok') {
                    return [
                        'label' => '运行态封禁缓存',
                        'status' => 'error',
                        'value' => '不可读写',
                        'message' => '安护盾 Redis 读写校验失败。',
                        'suggestion' => '请检查 Redis 服务状态、连接配置、认证信息和访问权限。',
                        'details' => '限流器缓存：'.$store.' / '.$driver,
                    ];
                }

                Cache::store($store)->forget($key.':redis-check');
            }

            RateLimiter::hit($key, 30);
            $available = RateLimiter::tooManyAttempts($key, 1);
            RateLimiter::clear($key);

            if (! $available) {
                return [
                    'label' => '运行态封禁缓存',
                    'status' => 'error',
                    'value' => '不可写',
                    'message' => '运行态封禁缓存写入后无法读取。',
                    'suggestion' => '请检查安护盾 Redis 限流器配置。',
                    'details' => '限流器缓存：'.$store.' / '.$driver,
                ];
            }

            return [
                'label' => '运行态封禁缓存',
                'status' => 'ok',
                'value' => '可读写',
                'message' => '运行态封禁缓存可正常读写。',
                'suggestion' => '',
                'details' => '限流器缓存：'.$store.' / '.$driver,
            ];
        } catch (Throwable $exception) {
            return [
                'label' => '运行态封禁缓存',
                'status' => 'error',
                'value' => '异常',
                'message' => $exception->getMessage(),
                'suggestion' => '请检查 Redis 服务状态，安护盾封禁和限流不能依赖 database/array 降级链路。',
                'details' => '限流器缓存：'.$store.' / '.$driver,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function statsBufferItem(): array
    {
        if (! (bool) config('security.stats_buffer.enabled', true)) {
            return [
                'label' => '统计缓冲',
                'status' => $this->productionRiskStatus(),
                'value' => '未启用',
                'message' => '安护盾统计将回退为请求内数据库直写。',
                'suggestion' => '商用环境建议启用 SECURITY_STATS_BUFFER_ENABLED=true，并通过 Redis 每分钟批量落库。',
                'details' => '',
            ];
        }

        $connection = (string) config('security.stats_buffer.redis_connection', 'cache');
        $key = 'site-security-health:stats-buffer:'.sha1((string) microtime(true));

        try {
            $redis = Redis::connection($connection);
            $redis->setex($key, 30, 'ok');

            if ((string) $redis->get($key) !== 'ok') {
                return [
                    'label' => '统计缓冲',
                    'status' => 'error',
                    'value' => '不可读写',
                    'message' => '安护盾统计 Redis 缓冲读写校验失败。',
                    'suggestion' => '请检查 SECURITY_STATS_REDIS_CONNECTION 指向的 Redis 连接。',
                    'details' => 'Redis 连接：'.$connection,
                ];
            }

            $redis->del($key);

            return [
                'label' => '统计缓冲',
                'status' => 'ok',
                'value' => '运行中',
                'message' => '安护盾统计会先写入 Redis，再由调度任务批量落库。',
                'suggestion' => '',
                'details' => 'Redis 连接：'.$connection.' · 调度命令：cms:flush-site-security-stats',
            ];
        } catch (Throwable $exception) {
            return [
                'label' => '统计缓冲',
                'status' => 'error',
                'value' => '异常',
                'message' => $exception->getMessage(),
                'suggestion' => '请检查 Redis 服务和调度任务；异常期间系统会降级为数据库直写，但高攻击量下数据库压力会升高。',
                'details' => 'Redis 连接：'.$connection,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function storageItem(): array
    {
        $tables = [
            'site_security_events' => ['site_id', 'rule_code', 'request_path', 'ip_hash', 'fingerprint', 'created_at'],
            'site_security_daily_stats' => ['site_id', 'stat_date', 'blocked_total'],
            'site_security_ip_reputations' => ['site_id', 'ip_hash', 'status', 'blocked_until', 'last_seen_at'],
        ];

        $missing = [];

        foreach ($tables as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $missing[] = $table;
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $missing[] = $table.'.'.$column;
                }
            }
        }

        if ($missing !== []) {
            return [
                'label' => '记录表结构',
                'status' => 'error',
                'value' => '不完整',
                'message' => '安护盾关键记录表或字段缺失。',
                'suggestion' => '请执行安全迁移：php artisan migrate --force',
                'details' => implode('，', array_slice($missing, 0, 6)),
            ];
        }

        try {
            DB::table('site_security_events')->limit(1)->count();
            DB::table('site_security_daily_stats')->limit(1)->count();
            DB::table('site_security_ip_reputations')->limit(1)->count();
        } catch (Throwable $exception) {
            return [
                'label' => '记录表结构',
                'status' => 'error',
                'value' => '不可访问',
                'message' => $exception->getMessage(),
                'suggestion' => '请检查数据库连接和表权限。',
                'details' => '',
            ];
        }

        return [
            'label' => '记录表结构',
            'status' => 'ok',
            'value' => '完整',
            'message' => '事件、统计和 IP 画像表结构完整且可访问。',
            'suggestion' => '',
            'details' => '事件表已启用指纹降噪字段。',
        ];
    }

    protected function securityLimiterStore(): string
    {
        $store = trim((string) config('cache.limiter', 'security'));

        return $store !== '' ? $store : 'security';
    }

    protected function productionRiskStatus(): string
    {
        return (string) config('app.env') === 'production' ? 'error' : 'warning';
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function overallStatus(array $items): string
    {
        $priority = ['ok' => 0, 'warning' => 1, 'error' => 2];
        $max = 0;

        foreach ($items as $item) {
            $max = max($max, $priority[(string) ($item['status'] ?? 'ok')] ?? 0);
        }

        return array_search($max, $priority, true) ?: 'ok';
    }
}
