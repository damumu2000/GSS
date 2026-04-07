<?php

namespace App\Support\SystemChecks;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class StaticVendorHealthCheck
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $manifestPath = public_path('vendor/vendor-assets.json');

        if (! is_file($manifestPath)) {
            $items = [[
                'label' => '静态资源清单',
                'status' => 'warning',
                'value' => '缺失',
                'message' => '未找到 vendor-assets.json。',
                'suggestion' => '建议为本地第三方静态资源建立版本清单。',
                'details' => '',
            ]];

            return [
                'key' => 'static-vendors',
                'title' => '静态资源与安全检查',
                'status' => 'warning',
                'summary' => '检查本地第三方前端静态资源的存在性、校验和与版本更新。',
                'items' => $items,
            ];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $items = [];

        foreach (is_array($manifest) ? $manifest : [] as $name => $asset) {
            $items[] = $this->inspectAsset((string) $name, is_array($asset) ? $asset : []);
        }

        return [
            'key' => 'static-vendors',
            'title' => '静态资源与安全检查',
            'status' => $this->overallStatus($items),
            'summary' => '检查本地第三方前端静态资源的存在性、校验和与版本更新。',
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $asset
     * @return array<string, mixed>
     */
    protected function inspectAsset(string $name, array $asset): array
    {
        $package = (string) ($asset['package'] ?? $name);
        $version = (string) ($asset['version'] ?? '');
        $file = (string) ($asset['file'] ?? '');
        $expectedSha = strtolower((string) ($asset['sha256'] ?? ''));
        $fullPath = base_path(ltrim($file, '/'));

        if (! is_file($fullPath)) {
            return [
                'label' => strtoupper($name),
                'status' => 'error',
                'value' => '本地文件缺失',
                'message' => '固定版本文件不存在。',
                'suggestion' => '请重新同步或恢复本地静态资源文件。',
                'details' => $file,
            ];
        }

        $actualSha = strtolower(hash_file('sha256', $fullPath));
        if ($expectedSha !== '' && $actualSha !== $expectedSha) {
            return [
                'label' => strtoupper($name),
                'status' => 'error',
                'value' => $version !== '' ? $version : '未知版本',
                'message' => '文件校验和与仓库记录不一致。',
                'suggestion' => '请确认本地文件是否被替换，并重新校准清单。',
                'details' => "sha256: {$actualSha}",
            ];
        }

        $latestVersion = Cache::remember(
            'system-checks:static-vendor:'.$package,
            now()->addMinutes(30),
            fn (): ?string => $this->resolveLatestVersion($package)
        );

        if (is_string($latestVersion) && $latestVersion !== '' && $latestVersion !== $version) {
            return [
                'label' => strtoupper($name),
                'status' => 'warning',
                'value' => sprintf('%s -> %s', $version, $latestVersion),
                'message' => '检测到上游有更新版本可用。',
                'suggestion' => '如准备升级，请先在测试环境验证兼容性后再替换。',
                'details' => "sha256: {$actualSha}",
            ];
        }

        return [
            'label' => strtoupper($name),
            'status' => 'ok',
            'value' => $version !== '' ? $version : '已固定',
            'message' => '本地静态资源文件正常，校验通过。',
            'suggestion' => '',
            'details' => is_string($latestVersion) && $latestVersion !== '' ? "latest: {$latestVersion}" : '',
        ];
    }

    protected function resolveLatestVersion(string $package): ?string
    {
        $response = Http::acceptJson()
            ->timeout(5)
            ->withHeaders(['User-Agent' => 'GSS-system-check'])
            ->get("https://registry.npmjs.org/{$package}/latest");

        if (! $response->successful()) {
            return null;
        }

        return $response->json('version');
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
