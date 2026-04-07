<?php

namespace App\Http\Controllers;

use App\Support\Site as SitePath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SiteMediaController extends Controller
{
    public function show(Request $request, string $siteKey, string $path): BinaryFileResponse
    {
        abort_if(str_contains($path, '..'), 404);

        $absolutePath = SitePath::mediaAbsolutePath($siteKey, $path);

        abort_unless(File::isFile($absolutePath), 404);

        return response()->file($absolutePath, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
