<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class ThemeTemplateScaffold
{
    /**
     * Copy system default template files into a site template directory.
     */
    public static function copyDefaultFiles(string $targetRoot): void
    {
        File::ensureDirectoryExists($targetRoot);

        foreach (static::defaultFiles() as $relativePath) {
            $sourcePath = static::sourceRoot().DIRECTORY_SEPARATOR.$relativePath;
            $targetPath = $targetRoot.DIRECTORY_SEPARATOR.$relativePath;

            if (File::exists($targetPath)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($targetPath));
            File::copy($sourcePath, $targetPath);
        }
    }

    public static function sourceRoot(): string
    {
        return storage_path('app/theme_templates');
    }

    /**
     * @return array<int, string>
     */
    protected static function defaultFiles(): array
    {
        $sourceRoot = static::sourceRoot();

        if (! File::isDirectory($sourceRoot)) {
            return [];
        }

        return collect(File::allFiles($sourceRoot))
            ->map(fn ($file): string => ltrim(str_replace($sourceRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR))
            ->reject(fn (string $relativePath): bool => static::shouldSkip($relativePath))
            ->sort()
            ->values()
            ->all();
    }

    protected static function shouldSkip(string $relativePath): bool
    {
        $segments = preg_split('#[\\\\/]#', $relativePath) ?: [];

        foreach ($segments as $segment) {
            if ($segment !== '' && str_starts_with($segment, '.')) {
                return true;
            }
        }

        return false;
    }
}
