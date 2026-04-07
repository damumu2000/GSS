<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use App\Support\ThemeTemplateLocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class ThemeMarketController extends Controller
{
    /**
     * Display the platform theme market page.
     */
    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizePlatform($request, 'theme.market.manage');

        $themes = DB::table('themes')
            ->leftJoin('theme_versions', function ($join): void {
                $join->on('theme_versions.theme_id', '=', 'themes.id')
                    ->where('theme_versions.is_current', '=', 1);
            })
            ->orderBy('themes.id')
            ->get([
                'themes.id',
                'themes.name',
                'themes.code',
                'themes.description',
                'themes.cover_image',
                'theme_versions.version',
            ]);

        return view('admin.platform.themes.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'themes' => $themes,
        ]);
    }

    /**
     * Display the create page for a theme.
     */
    public function create(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizePlatform($request, 'theme.market.manage');

        return view('admin.platform.themes.create', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
        ]);
    }

    /**
     * Display the edit page for a theme.
     */
    public function edit(Request $request, string $themeId): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizePlatform($request, 'theme.market.manage');
        $theme = DB::table('themes')->where('id', $themeId)->first();
        abort_unless($theme, 404);

        $version = DB::table('theme_versions')
            ->where('theme_id', $themeId)
            ->where('is_current', 1)
            ->first();

        $themeRoot = ThemeTemplateLocator::defaultRoot((string) $theme->code);
        $themeFiles = File::isDirectory($themeRoot)
            ? collect(File::allFiles($themeRoot))
                ->map(fn ($file): string => str_replace($themeRoot.'/', '', $file->getPathname()))
                ->sort()
                ->values()
                ->all()
            : [];

        return view('admin.platform.themes.edit', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'theme' => $theme,
            'version' => $version,
            'themeRoot' => $themeRoot,
            'themeFiles' => $themeFiles,
        ]);
    }

    /**
     * Store a newly created theme.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizePlatform($request, 'theme.market.manage');
        $validated = $this->validateTheme($request);

        $themeId = DB::table('themes')->insertGetId([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('theme_versions')->insert([
            'theme_id' => $themeId,
            'version' => $validated['version'],
            'package_path' => 'storage/app/theme_templates/'.$validated['code'],
            'manifest_json' => json_encode([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'version' => $validated['version'],
            ], JSON_UNESCAPED_UNICODE),
            'is_current' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logOperation(
            'platform',
            'theme_market',
            'create',
            null,
            $request->user()->id,
            'theme',
            $themeId,
            ['code' => $validated['code'], 'version' => $validated['version']],
            $request,
        );

        return redirect()
            ->route('admin.platform.themes.index')
            ->with('status', '主题已创建。');
    }

    /**
     * Update an existing theme.
     */
    public function update(Request $request, string $themeId): RedirectResponse
    {
        $this->authorizePlatform($request, 'theme.market.manage');
        $theme = DB::table('themes')->where('id', $themeId)->first();
        abort_unless($theme, 404);

        $validated = $this->validateTheme($request, $themeId);

        DB::table('themes')->where('id', $themeId)->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'updated_at' => now(),
        ]);

        DB::table('theme_versions')
            ->where('theme_id', $themeId)
            ->where('is_current', 1)
            ->update([
                'version' => $validated['version'],
                'package_path' => 'storage/app/theme_templates/'.$theme->code,
                'manifest_json' => json_encode([
                    'name' => $validated['name'],
                    'code' => $theme->code,
                    'version' => $validated['version'],
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        $this->logOperation(
            'platform',
            'theme_market',
            'update',
            null,
            $request->user()->id,
            'theme',
            (int) $themeId,
            ['code' => $theme->code, 'version' => $validated['version']],
            $request,
        );

        return redirect()
            ->route('admin.platform.themes.index')
            ->with('status', '主题已更新。');
    }

    /**
     * Validate a theme payload.
     *
     * @return array<string, mixed>
     */
    protected function validateTheme(Request $request, ?string $themeId = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'version' => ['required', 'string', 'max:30'],
        ];

        if ($themeId === null) {
            $rules['code'] = ['required', 'string', 'max:50', 'unique:themes,code'];
        }

        return $request->validate($rules);
    }
}
