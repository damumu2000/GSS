<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ThemeTemplateLocator
{
    /**
     * @var list<string>
     */
    protected const EDITOR_EXTENSIONS = ['tpl', 'css', 'js'];

    /**
     * @var list<string>
     */
    protected const ASSET_EXTENSIONS = ['css', 'js', 'json', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'woff', 'woff2'];

    public static function defaultRoot(string $themeCode): string
    {
        return storage_path("app/theme_templates/{$themeCode}");
    }

    public static function overrideRoot(int|object|string $site, string $themeCode): string
    {
        return Site::themeOverrideRoot($site, $themeCode);
    }

    public static function defaultPath(string $themeCode, string $template): string
    {
        return static::defaultRoot($themeCode).DIRECTORY_SEPARATOR.$template.'.tpl';
    }

    public static function defaultEditorFilePath(string $themeCode, string $file): string
    {
        $identifier = static::normalizeEditorFileIdentifier($file);

        return static::defaultRoot($themeCode).DIRECTORY_SEPARATOR.static::editorFilename($identifier);
    }

    public static function overridePath(int|object|string $site, string $themeCode, string $template): string
    {
        return static::overrideRoot($site, $themeCode).DIRECTORY_SEPARATOR.$template.'.tpl';
    }

    public static function overrideEditorFilePath(int|object|string $site, string $themeCode, string $file): string
    {
        $identifier = static::normalizeEditorFileIdentifier($file);

        return static::overrideRoot($site, $themeCode).DIRECTORY_SEPARATOR.static::editorFilename($identifier);
    }

    public static function resolvePath(int|object|string $site, string $themeCode, string $template): string
    {
        $override = static::overridePath($site, $themeCode, $template);

        if (File::exists($override)) {
            return $override;
        }

        return static::defaultPath($themeCode, $template);
    }

    public static function existingOverridePath(int|object|string $site, string $themeCode, string $template): ?string
    {
        $override = static::overridePath($site, $themeCode, $template);

        return File::exists($override) ? $override : null;
    }

    public static function existingEditorOverridePath(int|object|string $site, string $themeCode, string $file): ?string
    {
        $override = static::overrideEditorFilePath($site, $themeCode, $file);

        return File::exists($override) ? $override : null;
    }

    public static function availableTemplates(string $themeCode): Collection
    {
        $root = static::defaultRoot($themeCode);

        if (! File::isDirectory($root)) {
            return collect();
        }

        return collect(File::files($root))
            ->filter(fn ($file): bool => $file->getExtension() === 'tpl')
            ->map(function ($file): array {
                $template = str_replace('.tpl', '', $file->getFilename());

                return [
                    'file' => $template,
                    'label' => static::labelFor($template),
                ];
            })
            ->sortBy('file')
            ->values();
    }

    public static function availableEditorFiles(string $themeCode): Collection
    {
        $root = static::defaultRoot($themeCode);

        if (! File::isDirectory($root)) {
            return collect();
        }

        return collect(File::files($root))
            ->filter(fn ($file): bool => in_array($file->getExtension(), self::EDITOR_EXTENSIONS, true))
            ->map(fn ($file): array => static::editorFilePayload($file->getFilename()))
            ->sortBy('sort_key')
            ->values();
    }

    public static function availableTemplatesForSite(int|object|string $site, string $themeCode): Collection
    {
        $siteId = is_object($site) ? (int) ($site->id ?? 0) : (is_numeric($site) ? (int) $site : 0);
        $titleMap = $siteId > 0
            ? DB::table('site_theme_template_meta')
                ->where('site_id', $siteId)
                ->where('theme_code', $themeCode)
                ->pluck('title', 'template_name')
            : collect();

        $defaultTemplates = static::availableTemplates($themeCode)
            ->mapWithKeys(fn (array $template): array => [
                $template['file'] => [
                    'file' => $template['file'],
                    'base_label' => $template['label'],
                    'label' => static::displayLabel(
                        $template['label'],
                        $titleMap->get($template['file'])
                    ),
                    'source' => 'default',
                    'has_default' => true,
                    'has_override' => false,
                ],
            ]);

        $overrideRoot = static::overrideRoot($site, $themeCode);

        if (File::isDirectory($overrideRoot)) {
            collect(File::files($overrideRoot))
                ->filter(fn ($file): bool => $file->getExtension() === 'tpl')
                ->each(function ($file) use ($defaultTemplates, $titleMap): void {
                    $template = str_replace('.tpl', '', $file->getFilename());
                    $existing = $defaultTemplates->get($template);

                    $defaultTemplates->put($template, [
                        'file' => $template,
                        'label' => static::displayLabel(
                            $existing['base_label'] ?? static::labelFor($template),
                            $titleMap->get($template)
                        ),
                        'source' => $existing ? 'override' : 'custom',
                        'base_label' => $existing['base_label'] ?? static::labelFor($template),
                        'has_default' => (bool) ($existing['has_default'] ?? false),
                        'has_override' => true,
                    ]);
                });
        }

        return $defaultTemplates
            ->sortBy('file')
            ->values();
    }

    public static function availableEditorFilesForSite(int|object|string $site, string $themeCode): Collection
    {
        $siteId = is_object($site) ? (int) ($site->id ?? 0) : (is_numeric($site) ? (int) $site : 0);
        $titleMap = $siteId > 0
            ? DB::table('site_theme_template_meta')
                ->where('site_id', $siteId)
                ->where('theme_code', $themeCode)
                ->pluck('title', 'template_name')
            : collect();

        $defaultFiles = static::availableEditorFiles($themeCode)
            ->mapWithKeys(fn (array $file): array => [
                $file['key'] => [
                    ...$file,
                    'label' => static::displayLabel($file['base_label'], $titleMap->get($file['key'])),
                    'source' => 'default',
                    'has_default' => true,
                    'has_override' => false,
                ],
            ]);

        $overrideRoot = static::overrideRoot($site, $themeCode);

        if (File::isDirectory($overrideRoot)) {
            collect(File::files($overrideRoot))
                ->filter(fn ($file): bool => in_array($file->getExtension(), self::EDITOR_EXTENSIONS, true))
                ->each(function ($file) use ($defaultFiles, $titleMap): void {
                    $payload = static::editorFilePayload($file->getFilename());
                    $existing = $defaultFiles->get($payload['key']);

                    $defaultFiles->put($payload['key'], [
                        ...$payload,
                        'label' => static::displayLabel(
                            $existing['base_label'] ?? $payload['base_label'],
                            $titleMap->get($payload['key'])
                        ),
                        'source' => $existing ? 'override' : 'custom',
                        'base_label' => $existing['base_label'] ?? $payload['base_label'],
                        'has_default' => (bool) ($existing['has_default'] ?? false),
                        'has_override' => true,
                    ]);
                });
        }

        return $defaultFiles
            ->sortBy('sort_key')
            ->values();
    }

    public static function resolveAssetPath(int|object|string|null $site, string $themeCode, string $path): ?string
    {
        $path = static::normalizeAssetPath($path);

        if ($path === null) {
            return null;
        }

        if ($site !== null && $site !== '') {
            $override = static::overrideRoot($site, $themeCode).DIRECTORY_SEPARATOR.$path;
            if (File::exists($override) && File::isFile($override)) {
                return $override;
            }
        }

        $default = static::defaultRoot($themeCode).DIRECTORY_SEPARATOR.$path;

        return File::exists($default) && File::isFile($default) ? $default : null;
    }

    public static function assetVersion(int|object|string|null $site, string $themeCode, string $path): ?int
    {
        $resolved = static::resolveAssetPath($site, $themeCode, $path);

        return $resolved ? File::lastModified($resolved) : null;
    }

    public static function normalizeEditorFileIdentifier(string $file): string
    {
        $file = trim(str_replace('\\', '/', $file));

        if ($file === '' || str_contains($file, '/')) {
            throw new \InvalidArgumentException('模板文件标识不合法。');
        }

        if (preg_match('/^[A-Za-z0-9_-]+$/', $file) === 1) {
            return $file;
        }

        if (preg_match('/^[A-Za-z0-9_-]+\.(tpl|css|js|json)$/', $file) === 1) {
            return $file;
        }

        throw new \InvalidArgumentException('模板文件标识不合法。');
    }

    public static function editorFilename(string $file): string
    {
        $identifier = static::normalizeEditorFileIdentifier($file);

        return str_contains($identifier, '.') ? $identifier : $identifier.'.tpl';
    }

    public static function editorExtension(string $file): string
    {
        $filename = static::editorFilename($file);

        return (string) pathinfo($filename, PATHINFO_EXTENSION);
    }

    public static function editorStem(string $file): string
    {
        $filename = static::editorFilename($file);

        return (string) pathinfo($filename, PATHINFO_FILENAME);
    }

    public static function normalizeAssetPath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '.')) {
            return null;
        }

        if (preg_match('#^[A-Za-z0-9/_\.-]+$#', $path) !== 1) {
            return null;
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, self::ASSET_EXTENSIONS, true)) {
            return null;
        }

        return $path;
    }

    protected static function displayLabel(string $baseLabel, ?string $customTitle): string
    {
        $customTitle = trim((string) $customTitle);

        if ($customTitle === '') {
            return $baseLabel;
        }

        return $baseLabel.'_'.$customTitle;
    }

    public static function labelFor(string $template): string
    {
        return match ($template) {
            'head' => '页面头信息模板',
            'top' => '页面顶部结构模板',
            'foot' => '页面底部结构模板',
            'home' => '首页模板',
            'list' => '列表模板',
            'list-grid' => '网格列表模板',
            'detail' => '详情模板',
            'detail-focus' => '聚焦详情模板',
            'page' => '单页模板',
            'page-clean' => '简洁单页模板',
            default => static::customLabelFor($template),
        };
    }

    protected static function customLabelFor(string $template): string
    {
        if (str_starts_with($template, 'list-')) {
            return '自定义列表模板（'.substr($template, 5).'）';
        }

        if (str_starts_with($template, 'detail-')) {
            return '自定义详情模板（'.substr($template, 7).'）';
        }

        if (str_starts_with($template, 'page-')) {
            return '自定义单页模板（'.substr($template, 5).'）';
        }

        return '自定义模板（'.$template.'）';
    }

    protected static function editorFilePayload(string $filename): array
    {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $stem = (string) pathinfo($filename, PATHINFO_FILENAME);
        $key = $extension === 'tpl' ? $stem : $filename;

        return [
            'key' => $key,
            'file' => $filename,
            'stem' => $stem,
            'extension' => $extension,
            'base_label' => static::editorLabelFor($filename),
            'sort_key' => static::editorSortKey($filename),
        ];
    }

    protected static function editorLabelFor(string $filename): string
    {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $stem = (string) pathinfo($filename, PATHINFO_FILENAME);

        if ($extension === 'tpl') {
            return static::labelFor($stem);
        }

        return match ($filename) {
            'theme.css' => '主题全局样式',
            'home.css' => '首页样式',
            'home.js' => '首页脚本',
            'list.css' => '列表样式',
            'list.js' => '列表脚本',
            'detail.css' => '详情样式',
            'detail.js' => '详情脚本',
            'page.css' => '单页样式',
            'page.js' => '单页脚本',
            default => ($extension === 'css' ? '样式文件' : '脚本文件').'（'.$stem.'）',
        };
    }

    protected static function editorSortKey(string $filename): string
    {
        return match ($filename) {
            'top.tpl' => '00-top.tpl',
            'head.tpl' => '00-head.tpl',
            'theme.css' => '01-theme.css',
            'home.tpl' => '10-home.tpl',
            'home.css' => '11-home.css',
            'home.js' => '12-home.js',
            'list.tpl' => '20-list.tpl',
            'list.css' => '21-list.css',
            'list.js' => '22-list.js',
            'detail.tpl' => '30-detail.tpl',
            'detail.css' => '31-detail.css',
            'detail.js' => '32-detail.js',
            'page.tpl' => '40-page.tpl',
            'page.css' => '41-page.css',
            'page.js' => '42-page.js',
            'foot.tpl' => '90-foot.tpl',
            default => '50-'.$filename,
        };
    }
}
