<?php

namespace App\Support\SystemChecks;

use Illuminate\Support\Facades\File;

class RuntimeHealthCheck
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $items = [
            $this->appConfigItem(),
            $this->writableItem(),
            $this->storageLinkItem(),
            $this->logHealthItem(),
        ];

        return [
            'key' => 'runtime',
            'title' => '运行环境检查',
            'status' => $this->overallStatus($items),
            'summary' => '检查当前环境配置、目录写权限、存储链接和最近日志状态。',
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function appConfigItem(): array
    {
        $appEnv = (string) config('app.env');
        $appDebug = (bool) config('app.debug');

        if ($appEnv === 'production' && ! $appDebug) {
            return [
                'label' => '应用环境',
                'status' => 'ok',
                'value' => 'production / debug=false',
                'message' => '生产环境配置正常。',
                'suggestion' => '',
                'details' => '',
            ];
        }

        return [
            'label' => '应用环境',
            'status' => $appDebug ? 'error' : 'warning',
            'value' => sprintf('%s / debug=%s', $appEnv, $appDebug ? 'true' : 'false'),
            'message' => $appDebug
                ? '检测到 APP_DEBUG 仍然开启。'
                : '当前环境不是 production。',
            'suggestion' => '商用环境建议使用 APP_ENV=production 且 APP_DEBUG=false。',
            'details' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function writableItem(): array
    {
        $targets = [
            'storage' => storage_path(),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        $blocked = [];

        foreach ($targets as $label => $path) {
            if (! File::isDirectory($path) || ! is_writable($path)) {
                $blocked[] = $label;
            }
        }

        if ($blocked === []) {
            return [
                'label' => '目录写权限',
                'status' => 'ok',
                'value' => 'storage / bootstrap/cache',
                'message' => 'Laravel 运行目录可写。',
                'suggestion' => '',
                'details' => '',
            ];
        }

        return [
            'label' => '目录写权限',
            'status' => 'error',
            'value' => implode('，', $blocked),
            'message' => '检测到 Laravel 运行目录不可写。',
            'suggestion' => '请修正 Web 服务用户对 storage 和 bootstrap/cache 的写权限。',
            'details' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function storageLinkItem(): array
    {
        $linkPath = public_path('storage');

        if (is_link($linkPath)) {
            return [
                'label' => '公开存储链接',
                'status' => 'ok',
                'value' => 'public/storage',
                'message' => '公开存储软链接存在。',
                'suggestion' => '',
                'details' => (string) readlink($linkPath),
            ];
        }

        return [
            'label' => '公开存储链接',
            'status' => 'warning',
            'value' => '缺失',
            'message' => 'public/storage 软链接不存在。',
            'suggestion' => '请执行：php artisan storage:link',
            'details' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function logHealthItem(): array
    {
        $logPath = storage_path('logs/laravel.log');

        if (! is_file($logPath)) {
            return [
                'label' => '最近日志',
                'status' => 'ok',
                'value' => '暂无 laravel.log',
                'message' => '未发现主日志文件，当前没有可识别的异常日志。',
                'suggestion' => '',
                'details' => '',
            ];
        }

        $sample = $this->tailSample($logPath, 262144);
        $hasCritical = str_contains($sample, 'CRITICAL') || str_contains($sample, 'EMERGENCY');
        $hasError = str_contains($sample, 'ERROR');

        if (! $hasCritical && ! $hasError) {
            return [
                'label' => '最近日志',
                'status' => 'ok',
                'value' => '未发现高等级错误',
                'message' => '最近日志中未检测到 ERROR / CRITICAL。',
                'suggestion' => '',
                'details' => '',
            ];
        }

        return [
            'label' => '最近日志',
            'status' => $hasCritical ? 'error' : 'warning',
            'value' => $hasCritical ? '发现 CRITICAL' : '发现 ERROR',
            'message' => '最近日志里存在高等级异常，请尽快排查。',
            'suggestion' => '请检查 storage/logs/laravel.log 的最新记录。',
            'details' => '',
        ];
    }

    protected function tailSample(string $path, int $bytes): string
    {
        $size = filesize($path);

        if ($size === false || $size <= 0) {
            return '';
        }

        $handle = fopen($path, 'rb');

        if (! $handle) {
            return '';
        }

        $offset = max(0, $size - $bytes);
        fseek($handle, $offset);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function overallStatus(array $items): string
    {
        $priority = ['ok' => 0, 'warning' => 1, 'error' => 2];
        $max = 0;

        foreach ($items as $item) {
            $max = max($max, $priority[$item['status']] ?? 0);
        }

        return array_search($max, $priority, true) ?: 'ok';
    }
}
