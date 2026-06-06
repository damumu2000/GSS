<?php

namespace App\Support\SystemChecks;

use App\Support\SiteSecurity;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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
            $this->storageItem(),
        ];

        return [
            'key' => 'site-security',
            'title' => '安护盾健康',
            'status' => $this->overallStatus($items),
            'summary' => '检查安护盾开关、运行态封禁缓存和关键记录表是否可用。',
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
        $key = 'site-security-health:runtime-block:'.sha1((string) microtime(true));

        try {
            RateLimiter::hit($key, 30);
            $available = RateLimiter::tooManyAttempts($key, 1);
            RateLimiter::clear($key);

            if (! $available) {
                return [
                    'label' => '运行态封禁缓存',
                    'status' => 'error',
                    'value' => '不可写',
                    'message' => '运行态封禁缓存写入后无法读取。',
                    'suggestion' => '请检查默认缓存链路，优先确认 Redis 或后备缓存是否可写。',
                    'details' => '缓存驱动：'.$this->cacheDriverLabel(),
                ];
            }

            return [
                'label' => '运行态封禁缓存',
                'status' => 'ok',
                'value' => '可读写',
                'message' => '运行态封禁缓存可正常读写。',
                'suggestion' => '',
                'details' => '缓存驱动：'.$this->cacheDriverLabel(),
            ];
        } catch (Throwable $exception) {
            return [
                'label' => '运行态封禁缓存',
                'status' => 'error',
                'value' => '异常',
                'message' => $exception->getMessage(),
                'suggestion' => '请检查 Redis、database 缓存表和缓存目录权限，避免攻击流量下无法快速拦截。',
                'details' => '缓存驱动：'.$this->cacheDriverLabel(),
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

    protected function cacheDriverLabel(): string
    {
        $store = (string) config('cache.default', 'default');
        $driver = (string) config("cache.stores.{$store}.driver", $store);

        if ($driver !== 'failover') {
            return $driver;
        }

        $stores = array_values(array_filter((array) config("cache.stores.{$store}.stores", []), static fn ($value): bool => is_string($value) && $value !== ''));

        return implode(' -> ', $stores).'（故障切换）';
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
