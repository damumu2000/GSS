<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SiteSecurityStatsBuffer
{
    protected const DAILY_INDEX_KEY = 'site_security_stats:daily:index';

    protected const DAILY_PROCESSING_INDEX_KEY = 'site_security_stats:daily:processing:index';

    protected const DAILY_KEY_PREFIX = 'site_security_stats:daily:';

    protected const IP_INDEX_KEY = 'site_security_stats:ip:index';

    protected const IP_PROCESSING_INDEX_KEY = 'site_security_stats:ip:processing:index';

    protected const IP_KEY_PREFIX = 'site_security_stats:ip:';

    protected const KEY_TTL_SECONDS = 172800;

    /**
     * @var array<string, bool>
     */
    protected static array $columnExistsCache = [];

    /**
     * @var array<string, int>
     */
    protected static array $failureLogBuckets = [];

    public function recordDailyStat(int $siteId, string $statDate, string $column): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        try {
            $redis = $this->redis();
            $key = $this->dateKey($statDate);

            $redis->sadd(self::DAILY_INDEX_KEY, $key);
            $redis->expire(self::DAILY_INDEX_KEY, self::KEY_TTL_SECONDS);
            $redis->hincrby($key, $this->siteField($siteId, 'blocked_total'), 1);

            if ($column !== 'blocked_total') {
                $redis->hincrby($key, $this->siteField($siteId, $column), 1);
            }

            $redis->expire($key, self::KEY_TTL_SECONDS);

            return true;
        } catch (Throwable $exception) {
            $this->logFailure('daily-record', [
                'site_id' => $siteId,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function recordDailyStatDirect(int $siteId, string $statDate, string $column, int $count = 1, bool $incrementTotal = true): void
    {
        $count = max(1, $count);
        $now = now('Asia/Shanghai');

        DB::table('site_security_daily_stats')->insertOrIgnore([
            'site_id' => $siteId,
            'stat_date' => $statDate,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $updates = ['updated_at' => $now];

        if ($incrementTotal) {
            $updates['blocked_total'] = DB::raw('blocked_total + '.$count);
        }

        if ($column !== 'blocked_total' && $this->tableHasColumn('site_security_daily_stats', $column)) {
            $updates[$column] = DB::raw($column.' + '.$count);
        }

        DB::table('site_security_daily_stats')
            ->where('site_id', $siteId)
            ->where('stat_date', $statDate)
            ->update($updates);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function recordIpReputationHit(int $siteId, string $ip, string $ipHash, array $values, bool $isHighRisk, mixed $now): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        try {
            $redis = $this->redis();
            $key = $this->ipKey($siteId, $ipHash);
            $nowValue = $this->dateTimeValue($now);

            $redis->sadd(self::IP_INDEX_KEY, $key);
            $redis->expire(self::IP_INDEX_KEY, self::KEY_TTL_SECONDS);
            $redis->hincrby($key, 'hit_count', 1);

            if ($isHighRisk) {
                $redis->hincrby($key, 'high_risk_count', 1);
            }

            foreach ([
                'site_id' => (string) $siteId,
                'ip_hash' => $ipHash,
                'client_ip' => $ip,
                'last_rule_code' => (string) ($values['last_rule_code'] ?? ''),
                'last_request_path' => (string) ($values['last_request_path'] ?? ''),
                'status' => (string) ($values['status'] ?? 'monitored'),
                'blocked_until' => (string) ($values['blocked_until'] ?? ''),
                'last_seen_at' => $this->dateTimeValue($values['last_seen_at'] ?? $nowValue),
                'updated_at' => $this->dateTimeValue($values['updated_at'] ?? $nowValue),
            ] as $field => $value) {
                $redis->hset($key, $field, $value);
            }

            if (isset($values['region_name'])) {
                $redis->hset($key, 'region_name', (string) $values['region_name']);
            }

            $redis->expire($key, self::KEY_TTL_SECONDS);

            return true;
        } catch (Throwable $exception) {
            $this->logFailure('ip-record', [
                'site_id' => $siteId,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function recordIpReputationHitDirect(
        int $siteId,
        string $ip,
        string $ipHash,
        array $values,
        bool $isHighRisk,
        mixed $now,
        int $hitCount = 1,
        ?int $highRiskCount = null,
    ): void {
        if (! Schema::hasTable('site_security_ip_reputations')) {
            return;
        }

        $hitCount = max(1, $hitCount);
        $highRiskCount = max(0, $highRiskCount ?? ($isHighRisk ? 1 : 0));
        $nowValue = $this->dateTimeValue($now);
        $blockedUntil = trim((string) ($values['blocked_until'] ?? '')) ?: null;
        $existing = DB::table('site_security_ip_reputations')
            ->where('site_id', $siteId)
            ->where('ip_hash', $ipHash)
            ->first(['blocked_until']);
        $existingBlockedUntil = $existing?->blocked_until ? strtotime((string) $existing->blocked_until) : false;
        $incomingBlockedUntil = $blockedUntil ? strtotime($blockedUntil) : false;

        if ($existingBlockedUntil !== false
            && $existingBlockedUntil > now('Asia/Shanghai')->getTimestamp()
            && ($incomingBlockedUntil === false || $existingBlockedUntil > $incomingBlockedUntil)
        ) {
            $blockedUntil = (string) $existing->blocked_until;
            $values['status'] = 'blocked';
        }

        $insert = [
            'site_id' => $siteId,
            'client_ip' => $ip,
            'ip_hash' => $ipHash,
            'hit_count' => 0,
            'high_risk_count' => 0,
            'last_rule_code' => (string) ($values['last_rule_code'] ?? ''),
            'last_request_path' => (string) ($values['last_request_path'] ?? ''),
            'status' => 'monitored',
            'blocked_until' => null,
            'last_seen_at' => $this->dateTimeValue($values['last_seen_at'] ?? $nowValue),
            'created_at' => $nowValue,
            'updated_at' => $nowValue,
        ];

        if ($this->tableHasColumn('site_security_ip_reputations', 'region_name')) {
            $insert['region_name'] = trim((string) ($values['region_name'] ?? '')) ?: null;
        }

        DB::table('site_security_ip_reputations')->insertOrIgnore($insert);

        $updates = [
            'client_ip' => $ip,
            'last_rule_code' => (string) ($values['last_rule_code'] ?? ''),
            'last_request_path' => (string) ($values['last_request_path'] ?? ''),
            'status' => (string) ($values['status'] ?? 'monitored'),
            'blocked_until' => $blockedUntil,
            'last_seen_at' => $this->dateTimeValue($values['last_seen_at'] ?? $nowValue),
            'updated_at' => $this->dateTimeValue($values['updated_at'] ?? $nowValue),
            'hit_count' => DB::raw('hit_count + '.$hitCount),
        ];

        if ($highRiskCount > 0) {
            $updates['high_risk_count'] = DB::raw('high_risk_count + '.$highRiskCount);
        }

        if ($this->tableHasColumn('site_security_ip_reputations', 'region_name')) {
            $regionName = trim((string) ($values['region_name'] ?? ''));

            if ($regionName !== '') {
                $updates['region_name'] = $regionName;
            }
        }

        DB::table('site_security_ip_reputations')
            ->where('site_id', $siteId)
            ->where('ip_hash', $ipHash)
            ->update($updates);
    }

    /**
     * @return array{processed_keys:int,daily_rows:int,ip_rows:int}
     */
    public function flushPending(): array
    {
        $summary = ['processed_keys' => 0, 'daily_rows' => 0, 'ip_rows' => 0];

        if (! $this->enabled()) {
            return $summary;
        }

        try {
            $lock = Cache::store($this->lockStore())->lock('site-security-stats:flush', 600);

            if (! $lock->get()) {
                return $summary;
            }
        } catch (Throwable $exception) {
            $this->logFailure('flush-lock', [
                'message' => $exception->getMessage(),
            ]);

            return $summary;
        }

        try {
            $redis = $this->redis();

            foreach ($this->setMembers(self::DAILY_PROCESSING_INDEX_KEY) as $processingKey) {
                $this->flushProcessingDailyKey($redis, $processingKey, $summary);
            }

            foreach ($this->setMembers(self::IP_PROCESSING_INDEX_KEY) as $processingKey) {
                $this->flushProcessingIpKey($redis, $processingKey, $summary);
            }

            foreach ($this->setMembers(self::DAILY_INDEX_KEY) as $key) {
                $this->moveAndFlushKey($redis, $key, self::DAILY_INDEX_KEY, self::DAILY_PROCESSING_INDEX_KEY, 'daily', $summary);
            }

            foreach ($this->setMembers(self::IP_INDEX_KEY) as $key) {
                $this->moveAndFlushKey($redis, $key, self::IP_INDEX_KEY, self::IP_PROCESSING_INDEX_KEY, 'ip', $summary);
            }

            return $summary;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @param  array{processed_keys:int,daily_rows:int,ip_rows:int}  $summary
     */
    protected function moveAndFlushKey(mixed $redis, string $key, string $indexKey, string $processingIndexKey, string $type, array &$summary): void
    {
        if (! $redis->exists($key)) {
            $redis->srem($indexKey, $key);
            return;
        }

        $processingKey = $key.':processing:'.time().':'.Str::random(8);

        try {
            $redis->rename($key, $processingKey);
        } catch (Throwable) {
            if (! $redis->exists($key)) {
                $redis->srem($indexKey, $key);
            }

            return;
        }

        $redis->sadd($processingIndexKey, $processingKey);
        $redis->expire($processingIndexKey, self::KEY_TTL_SECONDS);

        if ($type === 'daily') {
            $this->flushProcessingDailyKey($redis, $processingKey, $summary);
        } else {
            $this->flushProcessingIpKey($redis, $processingKey, $summary);
        }

        if (! $redis->exists($key)) {
            $redis->srem($indexKey, $key);
        }
    }

    /**
     * @param  array{processed_keys:int,daily_rows:int,ip_rows:int}  $summary
     */
    protected function flushProcessingDailyKey(mixed $redis, string $processingKey, array &$summary): void
    {
        if (! $redis->exists($processingKey)) {
            $redis->srem(self::DAILY_PROCESSING_INDEX_KEY, $processingKey);
            return;
        }

        $statDate = $this->statDateFromKey($processingKey);
        if ($statDate === null) {
            $redis->del($processingKey);
            $redis->srem(self::DAILY_PROCESSING_INDEX_KEY, $processingKey);
            return;
        }

        $payload = $this->normalizeHash($redis->hgetall($processingKey));
        if ($payload === []) {
            $redis->del($processingKey);
            $redis->srem(self::DAILY_PROCESSING_INDEX_KEY, $processingKey);
            return;
        }

        $siteStats = $this->parseDailyPayload($payload);

        if ($siteStats !== []) {
            $existingSiteIds = DB::table('sites')
                ->whereIn('id', array_keys($siteStats))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $siteStats = array_intersect_key($siteStats, array_flip($existingSiteIds));
        }

        DB::transaction(function () use ($statDate, $siteStats, &$summary): void {
            foreach ($siteStats as $siteId => $stats) {
                $total = (int) ($stats['blocked_total'] ?? 0);

                if ($total > 0) {
                    $this->recordDailyStatDirect((int) $siteId, $statDate, 'blocked_total', $total);
                }

                foreach ($stats as $column => $count) {
                    if ($column === 'blocked_total' || $count <= 0) {
                        continue;
                    }

                    $this->recordDailyStatDirect((int) $siteId, $statDate, (string) $column, (int) $count, false);
                }

                $summary['daily_rows']++;
            }
        });

        $redis->del($processingKey);
        $redis->srem(self::DAILY_PROCESSING_INDEX_KEY, $processingKey);
        $summary['processed_keys']++;
    }

    /**
     * @param  array{processed_keys:int,daily_rows:int,ip_rows:int}  $summary
     */
    protected function flushProcessingIpKey(mixed $redis, string $processingKey, array &$summary): void
    {
        if (! $redis->exists($processingKey)) {
            $redis->srem(self::IP_PROCESSING_INDEX_KEY, $processingKey);
            return;
        }

        $payload = $this->normalizeHash($redis->hgetall($processingKey));
        if ($payload === []) {
            $redis->del($processingKey);
            $redis->srem(self::IP_PROCESSING_INDEX_KEY, $processingKey);
            return;
        }

        $siteId = (int) ($payload['site_id'] ?? 0);
        $ipHash = trim((string) ($payload['ip_hash'] ?? ''));
        $clientIp = trim((string) ($payload['client_ip'] ?? ''));
        $hitCount = max(0, (int) ($payload['hit_count'] ?? 0));

        if ($siteId <= 0 || $ipHash === '' || $clientIp === '' || $hitCount <= 0) {
            $redis->del($processingKey);
            $redis->srem(self::IP_PROCESSING_INDEX_KEY, $processingKey);
            return;
        }

        $highRiskCount = max(0, (int) ($payload['high_risk_count'] ?? 0));

        $this->recordIpReputationHitDirect(
            $siteId,
            $clientIp,
            $ipHash,
            [
                'last_rule_code' => (string) ($payload['last_rule_code'] ?? ''),
                'last_request_path' => (string) ($payload['last_request_path'] ?? ''),
                'status' => (string) ($payload['status'] ?? 'monitored'),
                'blocked_until' => trim((string) ($payload['blocked_until'] ?? '')) ?: null,
                'last_seen_at' => (string) ($payload['last_seen_at'] ?? now('Asia/Shanghai')->toDateTimeString()),
                'updated_at' => (string) ($payload['updated_at'] ?? now('Asia/Shanghai')->toDateTimeString()),
                'region_name' => (string) ($payload['region_name'] ?? ''),
            ],
            $highRiskCount > 0,
            (string) ($payload['updated_at'] ?? now('Asia/Shanghai')->toDateTimeString()),
            $hitCount,
            $highRiskCount,
        );

        $redis->del($processingKey);
        $redis->srem(self::IP_PROCESSING_INDEX_KEY, $processingKey);
        $summary['processed_keys']++;
        $summary['ip_rows']++;
    }

    /**
     * @return array<int, string>
     */
    protected function setMembers(string $key): array
    {
        try {
            return array_values(array_filter(array_map('strval', $this->redis()->smembers($key))));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<string, int|string>
     */
    protected function normalizeHash(array $payload): array
    {
        $normalized = [];
        $keys = array_keys($payload);
        $isAssoc = array_keys($keys) !== $keys;

        if ($isAssoc) {
            foreach ($payload as $field => $value) {
                $normalized[(string) $field] = is_numeric($value) ? (int) $value : (string) $value;
            }

            return $normalized;
        }

        for ($i = 0; $i < count($payload); $i += 2) {
            if (! isset($payload[$i])) {
                continue;
            }

            $value = $payload[$i + 1] ?? '';
            $normalized[(string) $payload[$i]] = is_numeric($value) ? (int) $value : (string) $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, int|string>  $payload
     * @return array<int, array<string, int>>
     */
    protected function parseDailyPayload(array $payload): array
    {
        $siteStats = [];

        foreach ($payload as $field => $count) {
            $count = (int) $count;

            if ($count <= 0) {
                continue;
            }

            if (preg_match('/^site:(\d+):(blocked_[a-z_]+)$/', $field, $matches) !== 1) {
                continue;
            }

            $siteId = (int) $matches[1];
            $column = (string) $matches[2];
            $siteStats[$siteId][$column] = ($siteStats[$siteId][$column] ?? 0) + $count;
        }

        return $siteStats;
    }

    protected function enabled(): bool
    {
        return (bool) config('security.stats_buffer.enabled', true);
    }

    protected function redis(): mixed
    {
        return Redis::connection((string) config('security.stats_buffer.redis_connection', 'cache'));
    }

    protected function lockStore(): string
    {
        return (string) config('security.stats_buffer.lock_store', 'security');
    }

    protected function dateKey(string $statDate): string
    {
        return self::DAILY_KEY_PREFIX.$statDate;
    }

    protected function ipKey(int $siteId, string $ipHash): string
    {
        return self::IP_KEY_PREFIX.$siteId.':'.$ipHash;
    }

    protected function siteField(int $siteId, string $column): string
    {
        return 'site:'.$siteId.':'.$column;
    }

    protected function statDateFromKey(string $key): ?string
    {
        if (preg_match('/^'.preg_quote(self::DAILY_KEY_PREFIX, '/').'(\d{4}-\d{2}-\d{2})(?::processing:.+)?$/', $key, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    protected function tableHasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        return static::$columnExistsCache[$key] ??= Schema::hasColumn($table, $column);
    }

    protected function dateTimeValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : now('Asia/Shanghai')->toDateTimeString();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logFailure(string $scope, array $context): void
    {
        $bucket = (int) floor(time() / 60);
        $key = $scope.':'.$bucket;

        if (isset(static::$failureLogBuckets[$key])) {
            return;
        }

        static::$failureLogBuckets[$key] = $bucket;

        if (count(static::$failureLogBuckets) > 100) {
            static::$failureLogBuckets = array_slice(static::$failureLogBuckets, -50, null, true);
        }

        Log::warning('Site security stats buffer failed.', ['scope' => $scope] + $context);
    }
}
