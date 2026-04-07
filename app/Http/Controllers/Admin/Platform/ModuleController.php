<?php

namespace App\Http\Controllers\Admin\Platform;

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

    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizePlatform($request, 'module.manage');
        $this->moduleManager->synchronize();

        return view('admin.platform.modules.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'modules' => $this->moduleManager->all(),
        ]);
    }

    public function show(Request $request, string $module): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizePlatform($request, 'module.manage');
        $this->moduleManager->synchronize();

        $resolvedModule = $this->moduleManager->findByCode($module);
        abort_unless($resolvedModule, 404);

        return view('admin.platform.modules.show', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'module' => $resolvedModule,
        ]);
    }

    public function toggle(Request $request, string $module): RedirectResponse
    {
        $this->authorizePlatform($request, 'module.manage');
        $this->moduleManager->synchronize();

        $targetModule = $this->moduleManager->findByCode($module);
        abort_unless($targetModule, 404);

        if (($targetModule['missing_manifest'] ?? false) || ($targetModule['invalid_manifest'] ?? false)) {
            return redirect()
                ->route('admin.platform.modules.show', $targetModule['code'])
                ->with('status', '当前模块文件异常，暂不支持切换启用状态，请先修复模块文件。');
        }

        $resolvedModule = $this->moduleManager->toggleStatus($module);
        abort_unless($resolvedModule, 404);

        return redirect()
            ->route('admin.platform.modules.show', $resolvedModule['code'])
            ->with('status', $resolvedModule['status'] ? '模块已启用。' : '模块已禁用。');
    }
}
