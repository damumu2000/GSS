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
            $this->trustedProxyItem(),
            $this->writableItem(),
            $this->storageLinkItem(),
        ];

        return [
            'key' => 'runtime',
            'title' => '运行环境检查',
            'status' => $this->overallStatus($items),
            'summary' => '检查当前环境配置、目录写权限和存储链接状态。',
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
    protected function trustedProxyItem(): array
    {
        $trustedProxies = trim((string) config('trustedproxy.proxies', ''));
        $proxyTokens = ','.str_replace(' ', '', $trustedProxies).',';

        if ($trustedProxies === '*'
            || $trustedProxies === '**'
            || str_contains($proxyTokens, ',*,')
            || str_contains($proxyTokens, ',**,')
        ) {
            return [
                'label' => '可信代理',
                'status' => $this->productionRiskStatus(),
                'value' => $trustedProxies,
                'message' => '当前信任全部代理来源的 X-Forwarded 头。',
                'suggestion' => '商用环境建议将 TRUSTED_PROXIES 配置为真实反向代理 IP 或内网 CIDR，并确保应用端口不能被公网直连。',
                'details' => '',
            ];
        }

        if ($trustedProxies === '') {
            return [
                'label' => '可信代理',
                'status' => 'ok',
                'value' => '未配置',
                'message' => '当前不会信任外部提交的 X-Forwarded 头。',
                'suggestion' => '',
                'details' => '如站点经反向代理传递 HTTPS、Host 或客户端 IP，请配置真实代理 IP。',
            ];
        }

        return [
            'label' => '可信代理',
            'status' => 'ok',
            'value' => $trustedProxies,
            'message' => '可信代理已限制为显式来源。',
            'suggestion' => '',
            'details' => '仅这些代理来源的 X-Forwarded 头会被信任。',
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

        if ((string) config('session.driver') === 'file') {
            $targets['storage/framework/sessions'] = (string) config('session.files', storage_path('framework/sessions'));
        }

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
            $details = '';

            if (function_exists('readlink')) {
                $resolved = readlink($linkPath);
                $details = is_string($resolved) ? $resolved : '';
            } else {
                $resolved = realpath($linkPath);
                $details = is_string($resolved) ? $resolved : 'readlink 不可用（可能被 disable_functions 禁用）';
            }

            return [
                'label' => '公开存储链接',
                'status' => 'ok',
                'value' => 'public/storage',
                'message' => '公开存储软链接存在。',
                'suggestion' => '',
                'details' => $details,
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

    protected function productionRiskStatus(): string
    {
        return (string) config('app.env') === 'production' ? 'error' : 'warning';
    }
}
