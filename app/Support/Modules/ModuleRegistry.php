<?php

namespace App\Support\Modules;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleRegistry
{
    public function rootPath(): string
    {
        return app_path('Modules');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function manifests(): Collection
    {
        $rootPath = $this->rootPath();

        if (! File::isDirectory($rootPath)) {
            return collect();
        }

        return collect(File::directories($rootPath))
            ->map(fn (string $modulePath) => $this->readManifest($modulePath))
            ->filter()
            ->sortBy([
                ['sort', 'asc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $code): ?array
    {
        return $this->manifests()->firstWhere('code', $code);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readManifest(string $modulePath): ?array
    {
        $manifestPath = $modulePath.'/module.json';
        $directoryName = basename($modulePath);
        $fallbackCode = 'invalid_manifest_'.Str::snake($directoryName);

        if (! File::exists($manifestPath)) {
            return $this->invalidManifest(
                modulePath: $modulePath,
                manifestPath: $manifestPath,
                fallbackCode: $fallbackCode,
                fallbackName: Str::headline($directoryName),
                reason: '模块目录存在，但 module.json 缺失。',
            );
        }

        $decoded = json_decode((string) File::get($manifestPath), true);

        if (! is_array($decoded)) {
            return $this->invalidManifest(
                modulePath: $modulePath,
                manifestPath: $manifestPath,
                fallbackCode: $fallbackCode,
                fallbackName: Str::headline($directoryName),
                reason: 'module.json 解析失败，请检查 JSON 格式是否正确。',
            );
        }

        $code = trim((string) ($decoded['code'] ?? $directoryName));

        if ($code === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $code)) {
            return $this->invalidManifest(
                modulePath: $modulePath,
                manifestPath: $manifestPath,
                fallbackCode: $fallbackCode,
                fallbackName: trim((string) ($decoded['name'] ?? Str::headline($directoryName))) ?: Str::headline($directoryName),
                reason: 'module.json 中的模块标识不合法，仅支持小写字母、数字和下划线，且需以字母开头。',
            );
        }

        $scope = in_array(($decoded['scope'] ?? 'site'), ['site', 'platform'], true)
            ? (string) $decoded['scope']
            : 'site';

        return [
            'name' => trim((string) ($decoded['name'] ?? Str::headline($code))) ?: Str::headline($code),
            'code' => $code,
            'version' => trim((string) ($decoded['version'] ?? '1.0.0')) ?: '1.0.0',
            'scope' => $scope,
            'author' => trim((string) ($decoded['author'] ?? '')),
            'description' => trim((string) ($decoded['description'] ?? '')),
            'default_enabled' => (bool) ($decoded['default_enabled'] ?? false),
            'sort' => max(0, (int) ($decoded['sort'] ?? 0)),
            'platform_entry_route' => $this->normalizeNullableString($decoded['platform_entry_route'] ?? null),
            'site_entry_route' => $this->normalizeNullableString($decoded['site_entry_route'] ?? null),
            'settings' => $this->normalizeStringList($decoded['settings'] ?? []),
            'permissions' => $this->normalizeStringList($decoded['permissions'] ?? []),
            'tables' => $this->normalizeStringList($decoded['tables'] ?? []),
            'notes' => $this->normalizeStringList($decoded['notes'] ?? []),
            'path' => $modulePath,
            'manifest_path' => $manifestPath,
            'invalid_manifest' => false,
            'manifest_error' => null,
            'files' => collect(File::allFiles($modulePath))
                ->map(function ($file) use ($modulePath): string {
                    return str_replace($modulePath.'/', '', $file->getPathname());
                })
                ->sort()
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    protected function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    protected function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    protected function invalidManifest(
        string $modulePath,
        string $manifestPath,
        string $fallbackCode,
        string $fallbackName,
        string $reason,
    ): array {
        return [
            'name' => $fallbackName,
            'code' => $fallbackCode,
            'version' => '异常',
            'scope' => 'site',
            'author' => '',
            'description' => '当前模块配置异常，请修复 module.json 后再使用。',
            'default_enabled' => false,
            'sort' => 999999,
            'platform_entry_route' => null,
            'site_entry_route' => null,
            'settings' => [],
            'permissions' => [],
            'tables' => [],
            'notes' => [$reason],
            'path' => $modulePath,
            'manifest_path' => $manifestPath,
            'invalid_manifest' => true,
            'manifest_error' => $reason,
            'files' => collect(File::allFiles($modulePath))
                ->map(function ($file) use ($modulePath): string {
                    return str_replace($modulePath.'/', '', $file->getPathname());
                })
                ->sort()
                ->values()
                ->all(),
        ];
    }
}
