<?php

namespace App\Support;

use App\Support\SystemChecks\MailQueueHealthCheck;
use App\Support\SystemChecks\StaticVendorHealthCheck;

class PlatformSystemStatus
{
    public function __construct(
        protected SystemSettings $systemSettings,
        protected MailQueueHealthCheck $mailQueueHealthCheck,
        protected StaticVendorHealthCheck $staticVendorHealthCheck,
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
            'title' => '邮件服务 / 队列执行状态',
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
