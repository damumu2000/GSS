<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Support\Modules\ModuleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function __construct(
        protected ModuleManager $moduleManager
    ) {
    }

    public function show(Request $request, string $module): View|RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $sitePermissionCodes = $this->sitePermissionCodes((int) $request->user()->id, (int) $currentSite->id);

        $resolvedModule = $this->moduleManager
            ->boundSiteModules((int) $currentSite->id)
            ->firstWhere('code', $module);

        abort_unless($resolvedModule, 404);

        if (! empty($resolvedModule['binding_is_paused'])) {
            return redirect()
                ->route('admin.site-dashboard')
                ->withErrors(['module' => '该模块已停用，请联系客服处理。']);
        }

        $entryPermission = $resolvedModule['entry_permission'] ?? null;
        if (is_string($entryPermission) && $entryPermission !== '' && ! in_array($entryPermission, $sitePermissionCodes, true)) {
            $this->authorizeSite($request, (int) $currentSite->id, $entryPermission);
        }

        return view('admin.site.modules.show', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'module' => $resolvedModule,
        ]);
    }
}
