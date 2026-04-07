<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OperationLogController extends Controller
{
    /**
     * Display recent logs for the active site only.
     */
    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'log.view');

        $keyword = trim((string) $request->query('keyword', ''));
        $module = trim((string) $request->query('module', ''));
        $action = trim((string) $request->query('action', ''));

        $logQuery = DB::table('operation_logs')
            ->leftJoin('users', 'users.id', '=', 'operation_logs.user_id')
            ->where('operation_logs.scope', 'site')
            ->where('operation_logs.site_id', $currentSite->id);

        if ($module !== '') {
            $logQuery->where('operation_logs.module', $module);
        }

        if ($action !== '') {
            $logQuery->where('operation_logs.action', 'like', '%' . $action . '%');
        }

        if ($keyword !== '') {
            $logQuery->where(function ($query) use ($keyword): void {
                $query->where('operation_logs.action', 'like', '%' . $keyword . '%')
                    ->orWhere('operation_logs.module', 'like', '%' . $keyword . '%')
                    ->orWhere('operation_logs.target_type', 'like', '%' . $keyword . '%')
                    ->orWhere('users.name', 'like', '%' . $keyword . '%')
                    ->orWhere('users.username', 'like', '%' . $keyword . '%');
            });
        }

        $moduleOptions = DB::table('operation_logs')
            ->select('module')
            ->where('scope', 'site')
            ->where('site_id', $currentSite->id)
            ->whereNotNull('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        $logs = $logQuery
            ->orderByDesc('operation_logs.id')
            ->paginate(20, [
                'operation_logs.id',
                'operation_logs.scope',
                'operation_logs.module',
                'operation_logs.action',
                'operation_logs.target_type',
                'operation_logs.target_id',
                'operation_logs.site_id',
                'operation_logs.created_at',
                'users.name as user_name',
                'users.username',
                'operation_logs.payload',
            ])
            ->withQueryString();

        $logs = $this->decorateOperationLogs($logs);

        return view('admin.site.logs.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'logs' => $logs,
            'keyword' => $keyword,
            'selectedScope' => '',
            'selectedModule' => $module,
            'selectedAction' => $action,
            'moduleOptions' => $moduleOptions,
            'pageTitle' => '站点日志',
            'pageDescription' => '仅展示当前站点相关的最近操作记录。',
            'showScopeFilter' => false,
            'formRoute' => route('admin.site-logs.index'),
        ]);
    }
}
