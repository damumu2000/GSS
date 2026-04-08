<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Support\SiteSecurity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function __construct(
        protected SiteSecurity $siteSecurity,
    ) {
    }

    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'security.view');

        return view('admin.site.security.index', [
            'currentSite' => $currentSite,
            'security' => $this->siteSecurity->sitePagePayload((int) $currentSite->id),
        ]);
    }
}
