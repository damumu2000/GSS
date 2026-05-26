<?php

namespace App\Http\Controllers;

use App\Support\Site as SitePath;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SiteMediaController extends Controller
{
    protected const ATTACHMENT_BASE_CACHE_TTL_SECONDS = 86400;

    public function attachment(Request $request, string $path): BinaryFileResponse
    {
        $attachmentBase = $this->resolveAttachmentBase($request);
        abort_unless(is_string($attachmentBase) && $attachmentBase !== '', 404);

        $normalizedPath = $this->normalizeAttachmentPath($path);
        abort_unless($normalizedPath !== null, 404);
        abort_unless(! $this->pathHasHiddenSegments($normalizedPath), 404);

        $attachmentRoot = storage_path('app/'.$attachmentBase);
        $resolvedAttachmentRoot = realpath($attachmentRoot);
        abort_unless(is_string($resolvedAttachmentRoot) && File::isDirectory($resolvedAttachmentRoot), 404);

        $absolutePath = realpath($resolvedAttachmentRoot.DIRECTORY_SEPARATOR.$normalizedPath);
        abort_unless(is_string($absolutePath) && File::isFile($absolutePath), 404);
        abort_unless($this->pathWithinRoot($resolvedAttachmentRoot, $absolutePath), 404);

        $response = response()->file($absolutePath, [
            'Cache-Control' => 'public, max-age=2592000',
        ]);

        $response->setEtag(sha1($absolutePath.'|'.File::lastModified($absolutePath).'|'.File::size($absolutePath)));
        $response->setLastModified(Carbon::createFromTimestamp(File::lastModified($absolutePath)));
        $response->isNotModified($request);

        return $response;
    }

    public function show(Request $request, string $siteKey, string $path): BinaryFileResponse
    {
        abort_unless(preg_match('/^[a-z0-9][a-z0-9\-]*$/', $siteKey) === 1, 404);
        abort_if(str_contains($path, '..'), 404);

        $absolutePath = SitePath::mediaAbsolutePath($siteKey, $path);

        abort_unless(File::isFile($absolutePath), 404);

        $response = response()->file($absolutePath, [
            'Cache-Control' => 'public, max-age=2592000',
        ]);

        $response->setEtag(sha1($absolutePath.'|'.File::lastModified($absolutePath).'|'.File::size($absolutePath)));
        $response->setLastModified(Carbon::createFromTimestamp(File::lastModified($absolutePath)));
        $response->isNotModified($request);

        return $response;
    }

    protected function resolveAttachmentBase(Request $request): ?string
    {
        $host = mb_strtolower(trim((string) $request->getHost()));

        if ($host !== '' && ! in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $cacheKey = $this->attachmentBaseCacheKey($host);
            $cachedBase = null;

            try {
                $cachedBase = Cache::get($cacheKey);
            } catch (\Throwable $exception) {
                Log::warning('Attachment base cache read failed.', [
                    'host' => $host,
                    'message' => $exception->getMessage(),
                ]);
            }

            if (is_string($cachedBase) && $cachedBase !== '') {
                return $cachedBase;
            }

            $siteKey = DB::table('site_domains')
                ->join('sites', 'sites.id', '=', 'site_domains.site_id')
                ->whereRaw('LOWER(site_domains.domain) = ?', [$host])
                ->where('site_domains.status', 1)
                ->where('sites.status', 1)
                ->value('sites.site_key');

            if (is_string($siteKey) && trim($siteKey) !== '') {
                $attachmentBase = SitePath::mediaRelative(trim($siteKey), 'attachments');

                try {
                    Cache::put($cacheKey, $attachmentBase, now()->addSeconds(self::ATTACHMENT_BASE_CACHE_TTL_SECONDS));
                } catch (\Throwable $exception) {
                    Log::warning('Attachment base cache write failed.', [
                        'host' => $host,
                        'site_key' => trim($siteKey),
                        'message' => $exception->getMessage(),
                    ]);
                }

                return $attachmentBase;
            }
        }

        if (! in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return null;
        }

        $siteKey = trim((string) $request->query('site', ''));

        if ($siteKey === '' || preg_match('/^[a-z0-9][a-z0-9\-]*$/', $siteKey) !== 1) {
            return null;
        }

        $cacheKey = $this->attachmentBaseCacheKey('local', $siteKey);
        $cachedBase = null;

        try {
            $cachedBase = Cache::get($cacheKey);
        } catch (\Throwable $exception) {
            Log::warning('Attachment base cache read failed.', [
                'host' => 'local',
                'site_key' => $siteKey,
                'message' => $exception->getMessage(),
            ]);
        }

        if (is_string($cachedBase) && $cachedBase !== '') {
            return $cachedBase;
        }

        $exists = DB::table('sites')
            ->where('site_key', $siteKey)
            ->where('status', 1)
            ->exists();

        if (! $exists) {
            return null;
        }

        $attachmentBase = SitePath::mediaRelative($siteKey, 'attachments');

        try {
            Cache::put($cacheKey, $attachmentBase, now()->addSeconds(self::ATTACHMENT_BASE_CACHE_TTL_SECONDS));
        } catch (\Throwable $exception) {
            Log::warning('Attachment base cache write failed.', [
                'host' => 'local',
                'site_key' => $siteKey,
                'message' => $exception->getMessage(),
            ]);
        }

        return $attachmentBase;
    }

    protected function attachmentBaseCacheKey(string $host, ?string $siteKey = null): string
    {
        $host = mb_strtolower(trim($host));
        $suffix = $siteKey !== null && trim($siteKey) !== '' ? ':'.mb_strtolower(trim($siteKey)) : '';

        return 'attachment-base:'.$host.$suffix;
    }

    protected function pathHasHiddenSegments(string $path): bool
    {
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || str_starts_with($segment, '.')) {
                return true;
            }
        }

        return false;
    }

    protected function pathWithinRoot(string $root, string $path): bool
    {
        $normalizedRoot = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $normalizedPath = rtrim($path, DIRECTORY_SEPARATOR);

        return str_starts_with($normalizedPath, $normalizedRoot);
    }

    protected function normalizeAttachmentPath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '.')) {
            return null;
        }

        if (preg_match('#^[A-Za-z0-9/_\.-]+$#', $path) !== 1) {
            return null;
        }

        return $path;
    }
}
