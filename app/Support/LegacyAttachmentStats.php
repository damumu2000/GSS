<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LegacyAttachmentStats
{
    protected const COUNT_KEY = 'attachment.legacy_up_file_count';
    protected const BYTES_KEY = 'attachment.legacy_up_bytes';
    protected const SCANNED_AT_KEY = 'attachment.legacy_up_scanned_at';

    /**
     * @return array{count:int,bytes:int,scanned_at:?Carbon,has_data:bool,directory:?string}
     */
    public static function stats(int|object|string $site): array
    {
        $siteRecord = static::siteRecord($site);

        if (! $siteRecord) {
            return [
                'count' => 0,
                'bytes' => 0,
                'scanned_at' => null,
                'has_data' => false,
                'directory' => null,
            ];
        }

        $settings = DB::table('site_settings')
            ->where('site_id', $siteRecord->id)
            ->whereIn('setting_key', [static::COUNT_KEY, static::BYTES_KEY, static::SCANNED_AT_KEY])
            ->pluck('setting_value', 'setting_key');

        if (! $settings->has(static::SCANNED_AT_KEY)) {
            return static::refresh($siteRecord, 0);
        }

        $count = max(0, (int) ($settings[static::COUNT_KEY] ?? 0));
        $bytes = max(0, (int) ($settings[static::BYTES_KEY] ?? 0));
        $scannedAtValue = trim((string) ($settings[static::SCANNED_AT_KEY] ?? ''));

        return [
            'count' => $count,
            'bytes' => $bytes,
            'scanned_at' => $scannedAtValue !== '' ? Carbon::parse($scannedAtValue, 'Asia/Shanghai') : null,
            'has_data' => $count > 0 || $bytes > 0,
            'directory' => static::resolveLegacyDirectory($siteRecord),
        ];
    }

    /**
     * @return array{count:int,bytes:int,scanned_at:?Carbon,has_data:bool,directory:?string}
     */
    public static function refresh(int|object|string $site, ?int $updatedBy = null): array
    {
        $siteRecord = static::siteRecord($site);

        if (! $siteRecord) {
            return [
                'count' => 0,
                'bytes' => 0,
                'scanned_at' => null,
                'has_data' => false,
                'directory' => null,
            ];
        }

        $stats = static::scan($siteRecord);
        $now = now('Asia/Shanghai');

        foreach ([
            static::COUNT_KEY => (string) $stats['count'],
            static::BYTES_KEY => (string) $stats['bytes'],
            static::SCANNED_AT_KEY => $now->toDateTimeString(),
        ] as $key => $value) {
            DB::table('site_settings')->updateOrInsert(
                ['site_id' => $siteRecord->id, 'setting_key' => $key],
                [
                    'setting_value' => $value,
                    'autoload' => 0,
                    'updated_by' => $updatedBy && $updatedBy > 0 ? $updatedBy : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        return [
            'count' => $stats['count'],
            'bytes' => $stats['bytes'],
            'scanned_at' => $now,
            'has_data' => $stats['count'] > 0 || $stats['bytes'] > 0,
            'directory' => $stats['directory'],
        ];
    }

    public static function hasLegacyDirectory(int|object|string $site): bool
    {
        $siteRecord = static::siteRecord($site);

        return $siteRecord !== null && static::resolveLegacyDirectory($siteRecord) !== null;
    }

    protected static function siteRecord(int|object|string $site): ?object
    {
        if (is_object($site) && isset($site->id, $site->site_key)) {
            return (object) [
                'id' => (int) $site->id,
                'site_key' => trim((string) $site->site_key),
            ];
        }

        if (is_int($site) || is_numeric($site)) {
            return DB::table('sites')
                ->where('id', (int) $site)
                ->first(['id', 'site_key']);
        }

        if (is_string($site)) {
            return DB::table('sites')
                ->where('site_key', trim($site))
                ->first(['id', 'site_key']);
        }

        return null;
    }

    /**
     * @return array{count:int,bytes:int,directory:?string}
     */
    protected static function scan(object $site): array
    {
        $directory = static::resolveLegacyDirectory($site);

        if (! $directory || ! File::isDirectory($directory)) {
            return ['count' => 0, 'bytes' => 0, 'directory' => $directory];
        }

        $files = File::allFiles($directory);

        return [
            'count' => count($files),
            'bytes' => (int) collect($files)->sum(fn ($file): int => (int) $file->getSize()),
            'directory' => $directory,
        ];
    }

    protected static function resolveLegacyDirectory(object $site): ?string
    {
        $preferred = Site::mediaAbsolutePath($site, 'attachments/up');
        if (File::isDirectory($preferred)) {
            return $preferred;
        }

        $fallback = Site::root($site).DIRECTORY_SEPARATOR.'up';
        if (File::isDirectory($fallback)) {
            return $fallback;
        }

        $attachmentRoot = Site::mediaAbsolutePath($site, 'attachments');
        $matched = static::findCaseInsensitiveDirectory($attachmentRoot, 'up');
        if ($matched) {
            return $matched;
        }

        return static::findCaseInsensitiveDirectory(Site::root($site), 'up');
    }

    protected static function findCaseInsensitiveDirectory(string $parent, string $target): ?string
    {
        if (! File::isDirectory($parent)) {
            return null;
        }

        $target = mb_strtolower($target);

        foreach (File::directories($parent) as $directory) {
            if (mb_strtolower(basename($directory)) === $target) {
                return $directory;
            }
        }

        return null;
    }
}
