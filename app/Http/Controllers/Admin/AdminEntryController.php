<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Platform\DashboardController as PlatformDashboardController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdminEntryController extends Controller
{
    /**
     * Redirect the authenticated user into the correct admin domain.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($this->isPlatformAdmin($request->user()->id)) {
            if ($this->defaultAdminRouteForRequest($request, (int) $request->user()->id) === 'admin.site-dashboard') {
                return redirect()->route('admin.site-dashboard');
            }

            return app(PlatformDashboardController::class)($request);
        }

        return redirect()->route($this->defaultAdminRoute($request->user()->id));
    }
}
