<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use App\Support\DatabaseInspector;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DatabaseController extends Controller
{
    public function __construct(
        protected DatabaseInspector $databaseInspector,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorizePlatform($request, 'database.manage');
        $currentSite = $this->currentSite($request);
        $keyword = trim((string) $request->query('keyword', ''));
        $tables = $this->databaseInspector->tables();

        if ($keyword !== '') {
            $tables = $tables->filter(fn (array $table): bool => str_contains(strtolower($table['name']), strtolower($keyword)))->values();
        }

        $tables = $this->paginateTables($tables, max(1, (int) $request->query('page', 1)), 20);

        return view('admin.platform.database.index', [
            'currentSite' => $currentSite,
            'overview' => $this->databaseInspector->overview(),
            'tables' => $tables,
            'keyword' => $keyword,
        ]);
    }

    public function show(Request $request, string $table): View
    {
        $this->authorizePlatform($request, 'database.manage');
        $currentSite = $this->currentSite($request);
        $detail = $this->databaseInspector->detail($table, max(1, (int) $request->query('page', 1)), 10);
        $activeTab = $this->normalizeTab((string) $request->query('tab', 'structure'));

        return view('admin.platform.database.show', [
            'currentSite' => $currentSite,
            'overview' => $this->databaseInspector->overview(),
            'detail' => $detail,
            'activeTab' => $activeTab,
        ]);
    }

    protected function normalizeTab(string $tab): string
    {
        return in_array($tab, ['structure', 'data', 'meta'], true) ? $tab : 'structure';
    }

    protected function paginateTables(Collection $tables, int $page, int $perPage): LengthAwarePaginator
    {
        $total = $tables->count();
        $items = $tables->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page']
        );
    }
}
