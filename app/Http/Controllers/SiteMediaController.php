<?php

namespace App\Http\Controllers;

use App\Support\Site as SitePath;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SiteMediaController extends Controller
{
    public function show(Request $request, string $siteKey, string $path): BinaryFileResponse
    {
        abort_unless(preg_match('/^[a-z0-9][a-z0-9\-]*$/', $siteKey) === 1, 404);
        abort_if(str_contains($path, '..'), 404);

        $absolutePath = SitePath::mediaAbsolutePath($siteKey, $path);

        abort_unless(File::isFile($absolutePath), 404);

        $response = response()->file($absolutePath, [
            'Cache-Control' => 'public, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);

        $response->setEtag(sha1($absolutePath.'|'.File::lastModified($absolutePath).'|'.File::size($absolutePath)));
        $response->setLastModified(Carbon::createFromTimestamp(File::lastModified($absolutePath)));
        $response->isNotModified($request);

        return $response;
    }
}
