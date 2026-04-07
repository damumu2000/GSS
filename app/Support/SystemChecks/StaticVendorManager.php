<?php

namespace App\Support\SystemChecks;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class StaticVendorManager
{
    public function manifestPath(): string
    {
        return (string) config('cms.static_vendor_manifest_path', public_path('vendor/vendor-assets.json'));
    }

    public function rootPath(): string
    {
        return (string) config('cms.static_vendor_root_path', base_path());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function manifest(): array
    {
        $manifestPath = $this->manifestPath();

        if (! is_file($manifestPath)) {
            return [];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        return is_array($manifest) ? $manifest : [];
    }

    public function latestVersion(string $package): ?string
    {
        return Cache::remember(
            'system-checks:static-vendor:'.$package,
            (int) config('cms.static_vendor_version_cache_seconds', 1800),
            function () use ($package): ?string {
                $response = Http::acceptJson()
                    ->timeout(5)
                    ->withHeaders(['User-Agent' => 'GSS-system-check'])
                    ->get("https://registry.npmjs.org/{$package}/latest");

                if (! $response->successful()) {
                    return null;
                }

                return $response->json('version');
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function upgrade(string $assetKey): array
    {
        $manifest = $this->manifest();
        $asset = $manifest[$assetKey] ?? null;

        if (! is_array($asset)) {
            throw new RuntimeException('未找到对应的静态资源清单项。');
        }

        $package = (string) ($asset['package'] ?? $assetKey);
        $currentVersion = (string) ($asset['version'] ?? '');
        $latestVersion = $this->latestVersion($package);

        if (! is_string($latestVersion) || $latestVersion === '') {
            throw new RuntimeException('暂时无法获取上游最新版本信息。');
        }

        if ($latestVersion === $currentVersion) {
            return [
                'package' => $package,
                'version' => $currentVersion,
                'updated' => false,
            ];
        }

        $downloadUrl = $this->upgradeSourceUrl($asset, $currentVersion, $latestVersion);
        $response = Http::timeout(15)
            ->withHeaders(['User-Agent' => 'GSS-system-check'])
            ->get($downloadUrl);

        if (! $response->successful()) {
            throw new RuntimeException('下载升级文件失败。');
        }

        $relativeFile = (string) ($asset['file'] ?? '');
        $targetPath = $this->resolveAssetPath($relativeFile);
        $targetDir = dirname($targetPath);

        if (! is_dir($targetDir) && ! mkdir($targetDir, 0775, true) && ! is_dir($targetDir)) {
            throw new RuntimeException('无法创建静态资源目录。');
        }

        $tempPath = $targetPath.'.tmp';
        file_put_contents($tempPath, $response->body());
        rename($tempPath, $targetPath);

        $asset['version'] = $latestVersion;
        $asset['source'] = $downloadUrl;
        $asset['sha256'] = strtolower(hash_file('sha256', $targetPath));
        $manifest[$assetKey] = $asset;

        $this->writeManifest($manifest);
        Cache::forget('system-checks:static-vendor:'.$package);

        return [
            'package' => $package,
            'version' => $latestVersion,
            'updated' => true,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $manifest
     */
    protected function writeManifest(array $manifest): void
    {
        $manifestPath = $this->manifestPath();
        $dir = dirname($manifestPath);

        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('无法创建静态资源清单目录。');
        }

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($json)) {
            throw new RuntimeException('静态资源清单写入失败。');
        }

        file_put_contents($manifestPath, $json."\n");
    }

    protected function resolveAssetPath(string $relativeFile): string
    {
        $root = rtrim($this->rootPath(), DIRECTORY_SEPARATOR);
        $relative = ltrim($relativeFile, '/');

        if ($relative === '' || Str::contains($relative, ['..\\', '../'])) {
            throw new RuntimeException('静态资源文件路径不合法。');
        }

        return $root.DIRECTORY_SEPARATOR.$relative;
    }

    /**
     * @param  array<string, mixed>  $asset
     */
    protected function upgradeSourceUrl(array $asset, string $currentVersion, string $latestVersion): string
    {
        $source = (string) ($asset['source'] ?? '');

        if ($source === '') {
            throw new RuntimeException('缺少静态资源来源地址。');
        }

        $escapedVersion = preg_quote($currentVersion, '/');
        $upgraded = preg_replace('/@'.$escapedVersion.'\//', '@'.$latestVersion.'/', $source, 1);

        if (! is_string($upgraded) || $upgraded === $source) {
            throw new RuntimeException('当前静态资源来源地址不支持自动升级。');
        }

        if (! str_starts_with($upgraded, 'https://cdn.jsdelivr.net/npm/')) {
            throw new RuntimeException('仅支持升级受信任的 jsDelivr npm 静态资源。');
        }

        return $upgraded;
    }
}
