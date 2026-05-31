<?php

namespace App\Support\SystemChecks;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SchedulerHealthCheck
{
    public const HEARTBEAT_CACHE_KEY = 'system:scheduler:last_heartbeat';

    public function heartbeat(): void
    {
        Cache::store('database')->put(
            self::HEARTBEAT_CACHE_KEY,
            now('Asia/Shanghai')->toDateTimeString(),
            now()->addMinutes(15)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $lastHeartbeat = (string) (Cache::store('database')->get(self::HEARTBEAT_CACHE_KEY) ?? '');
        $lastSeenAt = $lastHeartbeat !== '' ? Carbon::parse($lastHeartbeat, 'Asia/Shanghai') : null;
        $ageSeconds = $lastSeenAt ? $lastSeenAt->diffInSeconds(now('Asia/Shanghai')) : null;

        $status = match (true) {
            $ageSeconds !== null && $ageSeconds <= 180 => 'ok',
            $ageSeconds !== null && $ageSeconds <= 600 => 'warning',
            default => 'error',
        };

        $value = $lastSeenAt
            ? $lastSeenAt->format('Y-m-d H:i:s')
            : '未检测到';

        $message = match ($status) {
            'ok' => 'Laravel Scheduler 最近有心跳，自动任务调度正常。',
            'warning' => 'Laravel Scheduler 心跳有延迟，请关注服务器调度是否稳定。',
            default => '当前未检测到最近可用的 Laravel Scheduler 心跳。',
        };

        return [
            'key' => 'scheduler',
            'title' => '自动任务调度检查',
            'status' => $status,
            'summary' => '检查 Laravel Scheduler 是否正在被服务器定时触发。',
            'items' => [[
                'label' => 'Laravel Scheduler',
                'status' => $status,
                'value' => $value,
                'message' => $message,
                'suggestion' => $status === 'ok'
                    ? ''
                    : '请确认服务器已配置：* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1',
                'details' => implode(' · ', [
                    '关键任务：到期图宣停用',
                    '访问统计批量落库：每 60 分钟',
                ]),
            ]],
        ];
    }
}
