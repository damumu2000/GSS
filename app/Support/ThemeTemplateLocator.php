<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ThemeTemplateLocator
{
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

    public static function overridePath(int|object|string $site, string $themeCode, string $template): string
    {
        return static::overrideRoot($site, $themeCode).DIRECTORY_SEPARATOR.$template.'.tpl';
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
            'top' => '公共头部模板',
            'foot' => '公共尾部模板',
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
}
