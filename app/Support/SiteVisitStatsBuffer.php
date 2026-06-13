<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class SiteVisitStatsBuffer
{
    protected const INDEX_KEY = 'site_visit_stats:index';

    protected const PROCESSING_INDEX_KEY = 'site_visit_stats:processing:index';

    protected const KEY_PREFIX = 'site_visit_stats:';

    protected const KEY_TTL_SECONDS = 172800;

    protected const REDIS_CONNECTION = 'default';

    public function record(int $siteId, string $type, ?int $contentId = null): bool
    {
        try {
            $redis = Redis::connection(self::REDIS_CONNECTION);
            $statDate = now('Asia/Shanghai')->toDateString();
            $key = $this->dateKey($statDate);

            $redis->sadd(self::INDEX_KEY, $key);
            $redis->expire(self::INDEX_KEY, self::KEY_TTL_SECONDS);
            $redis->hincrby($key, $this->siteField($siteId, 'page_views'), 1);

            if ($type === 'article') {
                $redis->hincrby($key, $this->siteField($siteId, 'article_views'), 1);

                if ($contentId !== null) {
                    $redis->hincrby($key, $this->contentField($contentId), 1);
                }
            }

            if ($type === 'channel') {
                $redis->hincrby($key, $this->siteField($siteId, 'channel_views'), 1);
            }

            if ($type === 'home') {
                $redis->hincrby($key, $this->siteField($siteId, 'home_views'), 1);
            }

            $redis->expire($key, self::KEY_TTL_SECONDS);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Site visit stats redis buffer failed.', [
                'site_id' => $siteId,
                'type' => $type,
                'content_id' => $contentId,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array{processed_keys:int,site_rows:int,content_rows:int}
     */
    public function flushPending(): array
    {
        try {
            $lock = Cache::store('redis')->lock('site-visit-stats:flush', 600);

            if (! $lock->get()) {
                return ['processed_keys' => 0, 'site_rows' => 0, 'content_rows' => 0];
            }
        } catch (Throwable $exception) {
            Log::warning('Site visit stats flush lock failed.', [
                'message' => $exception->getMessage(),
            ]);

            return ['processed_keys' => 0, 'site_rows' => 0, 'content_rows' => 0];
        }

        try {
            $redis = Redis::connection(self::REDIS_CONNECTION);
            $summary = ['processed_keys' => 0, 'site_rows' => 0, 'content_rows' => 0];

            foreach ($this->setMembers(self::PROCESSING_INDEX_KEY) as $processingKey) {
                $this->flushProcessingKey($redis, $processingKey, $summary);
            }

            foreach ($this->setMembers(self::INDEX_KEY) as $key) {
                if (! $redis->exists($key)) {
                    $redis->srem(self::INDEX_KEY, $key);
                    continue;
                }

                $processingKey = $key.':processing:'.time().':'.Str::random(8);
                try {
                    $redis->rename($key, $processingKey);
                } catch (Throwable) {
                    if (! $redis->exists($key)) {
                        $redis->srem(self::INDEX_KEY, $key);
                    }

                    continue;
                }

                $redis->sadd(self::PROCESSING_INDEX_KEY, $processingKey);
                $redis->expire(self::PROCESSING_INDEX_KEY, self::KEY_TTL_SECONDS);

                $this->flushProcessingKey($redis, $processingKey, $summary);

                if (! $redis->exists($key)) {
                    $redis->srem(self::INDEX_KEY, $key);
                }
            }

            return $summary;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @param  array{processed_keys:int,site_rows:int,content_rows:int}  $summary
     */
    protected function flushProcessingKey(mixed $redis, string $processingKey, array &$summary): void
    {
        if (! $redis->exists($processingKey)) {
            $redis->srem(self::PROCESSING_INDEX_KEY, $processingKey);
            return;
        }

        $statDate = $this->statDateFromKey($processingKey);
        if ($statDate === null) {
            $redis->del($processingKey);
            $redis->srem(self::PROCESSING_INDEX_KEY, $processingKey);
            return;
        }

        $payload = $this->normalizeHash($redis->hgetall($processingKey));
        if ($payload === []) {
            $redis->del($processingKey);
            $redis->srem(self::PROCESSING_INDEX_KEY, $processingKey);
            return;
        }

        [$siteStats, $contentStats] = $this->parsePayload($payload);

        if ($siteStats !== []) {
            $existingSiteIds = DB::table('sites')
                ->whereIn('id', array_keys($siteStats))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $siteStats = array_intersect_key($siteStats, array_flip($existingSiteIds));
        }

        DB::transaction(function () use ($statDate, $siteStats, $contentStats, &$summary): void {
            $now = now();

            foreach ($siteStats as $siteId => $stats) {
                DB::table('site_visit_daily_stats')->insertOrIgnore([
                    'site_id' => $siteId,
                    'stat_date' => $statDate,
                    'page_views' => 0,
                    'article_views' => 0,
                    'channel_views' => 0,
                    'home_views' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('site_visit_daily_stats')
                    ->where('site_id', $siteId)
                    ->where('stat_date', $statDate)
                    ->update([
                        'page_views' => DB::raw('page_views + '.(int) ($stats['page_views'] ?? 0)),
                        'article_views' => DB::raw('article_views + '.(int) ($stats['article_views'] ?? 0)),
                        'channel_views' => DB::raw('channel_views + '.(int) ($stats['channel_views'] ?? 0)),
                        'home_views' => DB::raw('home_views + '.(int) ($stats['home_views'] ?? 0)),
                        'updated_at' => $now,
                    ]);

                $summary['site_rows']++;
            }

            foreach ($contentStats as $contentId => $views) {
                DB::table('contents')
                    ->where('id', $contentId)
                    ->increment('view_count', $views);

                $summary['content_rows']++;
            }
        });

        $redis->del($processingKey);
        $redis->srem(self::PROCESSING_INDEX_KEY, $processingKey);
        $summary['processed_keys']++;
    }

    /**
     * @return array<int, string>
     */
    protected function setMembers(string $key): array
    {
        try {
            return array_values(array_filter(array_map('strval', Redis::connection(self::REDIS_CONNECTION)->smembers($key))));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<string, int>
     */
    protected function normalizeHash(array $payload): array
    {
        $normalized = [];
        $keys = array_keys($payload);
        $isAssoc = array_keys($keys) !== $keys;

        if ($isAssoc) {
            foreach ($payload as $field => $value) {
                $normalized[(string) $field] = (int) $value;
            }

            return $normalized;
        }

        for ($i = 0; $i < count($payload); $i += 2) {
            if (! isset($payload[$i])) {
                continue;
            }

            $normalized[(string) $payload[$i]] = (int) ($payload[$i + 1] ?? 0);
        }

        return $normalized;
    }

    /**
     * @param  array<string, int>  $payload
     * @return array{0: array<int, array<string, int>>, 1: array<int, int>}
     */
    protected function parsePayload(array $payload): array
    {
        $siteStats = [];
        $contentStats = [];

        foreach ($payload as $field => $count) {
            if ($count <= 0) {
                continue;
            }

            if (preg_match('/^site:(\d+):(page_views|article_views|channel_views|home_views)$/', $field, $matches) === 1) {
                $siteId = (int) $matches[1];
                $column = (string) $matches[2];
                $siteStats[$siteId][$column] = ($siteStats[$siteId][$column] ?? 0) + $count;
                continue;
            }

            if (preg_match('/^content:(\d+):view_count$/', $field, $matches) === 1) {
                $contentId = (int) $matches[1];
                $contentStats[$contentId] = ($contentStats[$contentId] ?? 0) + $count;
            }
        }

        return [$siteStats, $contentStats];
    }

    protected function dateKey(string $statDate): string
    {
        return self::KEY_PREFIX.$statDate;
    }

    protected function siteField(int $siteId, string $column): string
    {
        return 'site:'.$siteId.':'.$column;
    }

    protected function contentField(int $contentId): string
    {
        return 'content:'.$contentId.':view_count';
    }

    protected function statDateFromKey(string $key): ?string
    {
        if (preg_match('/^'.preg_quote(self::KEY_PREFIX, '/').'(\d{4}-\d{2}-\d{2})(?::processing:.+)?$/', $key, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
