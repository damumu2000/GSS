<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SiteStorageUsage
{
    public static function attachmentCount(int $siteId): int
    {
        return (int) DB::table('attachments')
            ->where('site_id', $siteId)
            ->count();
    }

    /**
     * @return array{count:int, bytes:int}
     */
    protected static function themeAssetStats(int|object|string $site): array
    {
        $themeRoot = Site::root($site).DIRECTORY_SEPARATOR.'theme';

        if (! File::isDirectory($themeRoot)) {
            return ['count' => 0, 'bytes' => 0];
        }

        $files = collect(File::allFiles($themeRoot))
            ->filter(function ($file) use ($themeRoot): bool {
                $relativePath = str_replace('\\', '/', ltrim(str_replace($themeRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR));

                return str_contains('/'.$relativePath, '/assets/');
            })
            ->values();

        return [
            'count' => $files->count(),
            'bytes' => (int) $files->sum(fn ($file): int => (int) $file->getSize()),
        ];
    }

    public static function attachmentBytes(int $siteId): int
    {
        return (int) DB::table('attachments')
            ->where('site_id', $siteId)
            ->sum('size');
    }

    /**
     * @return array{count:int,bytes:int,scanned_at:?\Carbon\Carbon,has_data:bool}
     */
    public static function legacyAttachmentStats(int|object|string $site): array
    {
        return LegacyAttachmentStats::stats($site);
    }

    public static function legacyAttachmentBytes(int|object|string $site): int
    {
        return (int) (static::legacyAttachmentStats($site)['bytes'] ?? 0);
    }

    public static function legacyAttachmentCount(int|object|string $site): int
    {
        return (int) (static::legacyAttachmentStats($site)['count'] ?? 0);
    }

    public static function themeAssetBytes(int|object|string $site): int
    {
        return static::themeAssetStats($site)['bytes'];
    }

    public static function themeAssetCount(int|object|string $site): int
    {
        return static::themeAssetStats($site)['count'];
    }

    public static function totalBytes(int|object|string $site): int
    {
        $siteId = is_object($site) ? (int) ($site->id ?? 0) : (is_numeric($site) ? (int) $site : 0);

        return static::attachmentBytes($siteId) + static::legacyAttachmentBytes($site) + static::themeAssetBytes($site);
    }

    public static function globalLegacyAttachmentBytes(): int
    {
        return (int) DB::table('site_settings')
            ->where('setting_key', 'attachment.legacy_up_bytes')
            ->sum(DB::raw('CAST(setting_value AS UNSIGNED)'));
    }

    public static function globalLegacyAttachmentCount(): int
    {
        return (int) DB::table('site_settings')
            ->where('setting_key', 'attachment.legacy_up_file_count')
            ->sum(DB::raw('CAST(setting_value AS UNSIGNED)'));
    }

    public static function globalThemeAssetBytes(): int
    {
        return (int) DB::table('sites')
            ->get(['id', 'site_key'])
            ->sum(fn (object $site): int => static::themeAssetBytes($site));
    }

    public static function globalThemeAssetCount(): int
    {
        return (int) DB::table('sites')
            ->get(['id', 'site_key'])
            ->sum(fn (object $site): int => static::themeAssetCount($site));
    }

    public static function globalTotalBytes(): int
    {
        return (int) DB::table('attachments')->sum('size') + static::globalLegacyAttachmentBytes() + static::globalThemeAssetBytes();
    }

    public static function storageLimitMb(int $siteId): int
    {
        return max(0, (int) DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'attachment.storage_limit_mb')
            ->value('setting_value'));
    }

    public static function storageLimitBytes(int $siteId): int
    {
        return static::storageLimitMb($siteId) * 1024 * 1024;
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = $bytes / 1024;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return number_format($size, 1).' '.$units[$unitIndex];
    }
}
