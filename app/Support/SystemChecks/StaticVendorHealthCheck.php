<?php

namespace App\Support\SystemChecks;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class StaticVendorHealthCheck
{
    protected const LARAVEL_LATEST_CACHE_KEY = 'system-checks:laravel:latest';

    public function __construct(
        protected StaticVendorManager $manager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $manifestPath = $this->manager->manifestPath();
        $items = [
            $this->laravelVersionItem(),
        ];

        if (! is_file($manifestPath)) {
            $items[] = [
                'label' => '静态资源清单',
                'status' => 'warning',
                'value' => '缺失',
                'message' => '未找到 vendor-assets.json。',
                'suggestion' => '建议为本地第三方静态资源建立版本清单。',
                'details' => '',
            ];

            return [
                'key' => 'static-vendors',
                'title' => '静态资源与安全检查',
                'status' => 'warning',
                'summary' => '检查 Laravel 框架版本与本地第三方 JS 资源库的存在性、校验和及版本更新。',
                'items' => $items,
            ];
        }

        $manifest = $this->manager->manifest();

        foreach ($manifest as $name => $asset) {
            $items[] = $this->inspectAsset((string) $name, is_array($asset) ? $asset : []);
        }

        return [
            'key' => 'static-vendors',
            'title' => '静态资源与安全检查',
            'status' => $this->overallStatus($items),
            'summary' => '检查 Laravel 框架版本与本地第三方 JS 资源库的存在性、校验和及版本更新。',
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
        $label = strtoupper($name) === 'SORTABLEJS' ? 'SORTABLEJS（项目拖拽排序组件）' : strtoupper($name);
        $version = (string) ($asset['version'] ?? '');
        $file = (string) ($asset['file'] ?? '');
        $expectedSha = strtolower((string) ($asset['sha256'] ?? ''));
        $fullPath = rtrim($this->manager->rootPath(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($file, '/');

        if (! is_file($fullPath)) {
            return [
                'label' => $label,
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
                'label' => $label,
                'status' => 'error',
                'value' => $version !== '' ? $version : '未知版本',
                'message' => '文件校验和与仓库记录不一致。',
                'suggestion' => '请确认本地文件是否被替换，并重新校准清单。',
                'details' => "sha256: {$actualSha}",
            ];
        }

        try {
            $latestVersion = $this->manager->latestVersion($package);
        } catch (\Throwable) {
            $latestVersion = null;
        }

        if (is_string($latestVersion) && $latestVersion !== '' && $latestVersion !== $version) {
            return [
                'label' => $label,
                'status' => 'warning',
                'value' => sprintf('%s -> %s', $version, $latestVersion),
                'message' => '检测到上游有更新版本可用。',
                'suggestion' => '如准备升级，请先在测试环境验证兼容性后再替换。',
                'details' => "sha256: {$actualSha}",
                'action_url' => route('admin.platform.system-checks.static-vendors.upgrade', ['asset' => $name]),
                'action_label' => '一键升级',
            ];
        }

        return [
            'label' => $label,
            'status' => 'ok',
            'value' => $version !== '' ? $version : '已固定',
            'message' => '本地静态资源文件正常，校验通过。',
            'suggestion' => '',
            'details' => is_string($latestVersion) && $latestVersion !== '' ? "latest: {$latestVersion}" : '',
            ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function laravelVersionItem(): array
    {
        $currentVersion = $this->packageVersion('laravel/framework');
        try {
            $latestVersion = $this->latestLaravelVersion();
        } catch (\Throwable) {
            $latestVersion = null;
        }

        if ($currentVersion === null) {
            return [
                'label' => 'Laravel 版本',
                'status' => 'warning',
                'value' => '无法识别',
                'message' => '未能从 composer.lock 读取当前 Laravel 版本。',
                'suggestion' => '请确认 composer.lock 存在且格式正确。',
                'details' => '',
            ];
        }

        if ($latestVersion === null) {
            return [
                'label' => 'Laravel 版本',
                'status' => 'warning',
                'value' => $currentVersion,
                'message' => '当前版本已识别，但暂时无法获取最新版本信息。',
                'suggestion' => '请稍后重试或检查服务器网络访问。',
                'details' => '',
            ];
        }

        $currentNormalized = ltrim($currentVersion, 'v');
        $latestNormalized = ltrim($latestVersion, 'v');

        if (version_compare($currentNormalized, $latestNormalized, '>=')) {
            return [
                'label' => 'Laravel 版本',
                'status' => 'ok',
                'value' => $currentVersion,
                'message' => '当前已是最新版本。',
                'suggestion' => '',
                'details' => 'latest: '.$latestVersion,
            ];
        }

        return [
            'label' => 'Laravel 版本',
            'status' => 'warning',
            'value' => $currentVersion.' -> '.$latestVersion,
            'message' => '检测到 Laravel 存在可升级版本。',
            'suggestion' => '建议先在测试环境验证后再升级。',
            'details' => 'latest: '.$latestVersion,
        ];
    }

    protected function latestLaravelVersion(): ?string
    {
        return Cache::remember(self::LARAVEL_LATEST_CACHE_KEY, 1800, function (): ?string {
            try {
                $response = Http::acceptJson()
                    ->connectTimeout(3)
                    ->timeout(5)
                    ->withHeaders(['User-Agent' => 'GSS-system-check'])
                    ->get('https://repo.packagist.org/p2/laravel/framework.json');
            } catch (\Throwable) {
                return null;
            }

            if (! $response->successful()) {
                return null;
            }

            $packages = $response->json('packages.laravel/framework');
            if (! is_array($packages)) {
                return null;
            }

            $latest = null;
            foreach ($packages as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $version = (string) ($row['version'] ?? '');
                if (! preg_match('/^v?\d+\.\d+\.\d+$/', $version)) {
                    continue;
                }

                if ($latest === null || version_compare(ltrim($version, 'v'), ltrim($latest, 'v'), '>')) {
                    $latest = $version;
                }
            }

            return $latest;
        });
    }

    protected function packageVersion(string $packageName): ?string
    {
        $manifestPath = base_path('composer.lock');
        if (! is_file($manifestPath)) {
            return null;
        }

        $raw = file_get_contents($manifestPath);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $json = json_decode($raw, true);
        if (! is_array($json)) {
            return null;
        }

        $groups = [];
        if (isset($json['packages']) && is_array($json['packages'])) {
            $groups[] = $json['packages'];
        }
        if (isset($json['packages-dev']) && is_array($json['packages-dev'])) {
            $groups[] = $json['packages-dev'];
        }

        foreach ($groups as $group) {
            foreach ($group as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if ((string) ($row['name'] ?? '') !== $packageName) {
                    continue;
                }

                $version = (string) ($row['version'] ?? '');
                return $version !== '' ? $version : null;
            }
        }

        return null;
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
