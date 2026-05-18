<?php

namespace App\Support\SystemChecks;

use App\Support\PlatformMailSettings;

class MailQueueHealthCheck
{
    public function __construct(
        protected PlatformMailSettings $platformMailSettings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $diagnostics = $this->platformMailSettings->diagnostics();

        $state = match (true) {
            ! $diagnostics['requires_worker'] => '当前模式不需要',
            $diagnostics['worker_active'] => '运行中',
            default => '未运行',
        };

        return [
            'key' => 'mail-queue',
            'title' => '邮件与队列检查',
            'status' => $diagnostics['status'],
            'summary' => '检查邮件服务依赖的队列连接、worker 活跃状态、待执行任务和失败任务。',
            'items' => [[
                'label' => '邮件服务 / 队列执行状态',
                'status' => $diagnostics['status'],
                'value' => strtoupper((string) $diagnostics['queue_connection']).' / '.$state,
                'message' => $diagnostics['message'],
                'suggestion' => $diagnostics['suggestion'],
                'details' => implode(' · ', array_filter([
                    '待执行 '.(string) ($diagnostics['pending_jobs'] ?? '—'),
                    '失败 '.(string) ($diagnostics['failed_jobs'] ?? '—'),
                    $diagnostics['last_seen_at'] !== '' ? '最近心跳 '.$diagnostics['last_seen_at'] : '',
                ])),
            ]],
        ];
    }
}
