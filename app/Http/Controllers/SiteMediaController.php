<?php

namespace App\Http\Controllers;

use App\Support\Site as SitePath;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SiteMediaController extends Controller
{
    public function attachment(Request $request, string $path): BinaryFileResponse
    {
        $siteKey = $this->resolveAttachmentSiteKey($request);
        abort_unless($siteKey !== null, 404);
        abort_if(str_contains($path, '..'), 404);

        $normalizedPath = $this->normalizeAttachmentPath($path);
        abort_unless($normalizedPath !== null, 404);

        $absolutePath = SitePath::mediaAbsolutePath($siteKey, 'attachments/'.$normalizedPath);

        abort_unless(File::isFile($absolutePath), 404);

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

    protected function resolveAttachmentSiteKey(Request $request): ?string
    {
        $host = mb_strtolower(trim((string) $request->getHost()));

        if ($host !== '') {
            $siteKey = DB::table('site_domains')
                ->join('sites', 'sites.id', '=', 'site_domains.site_id')
                ->whereRaw('LOWER(site_domains.domain) = ?', [$host])
                ->where('site_domains.status', 1)
                ->where('sites.status', 1)
                ->value('sites.site_key');

            if (is_string($siteKey) && trim($siteKey) !== '') {
                return trim($siteKey);
            }
        }

        if (! in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return null;
        }

        $siteKey = trim((string) $request->query('site', ''));

        if ($siteKey === '' || preg_match('/^[a-z0-9][a-z0-9\-]*$/', $siteKey) !== 1) {
            return null;
        }

        $exists = DB::table('sites')
            ->where('site_key', $siteKey)
            ->where('status', 1)
            ->exists();

        return $exists ? $siteKey : null;
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
