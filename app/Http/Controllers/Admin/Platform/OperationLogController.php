<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OperationLogController extends Controller
{
    /**
     * Display recent operation logs.
     */
    public function index(Request $request): View
    {
        $this->authorizePlatform($request, 'platform.log.view');

        $currentSite = $this->currentSite($request);

        $keyword = trim((string) $request->query('keyword', ''));
        $module = trim((string) $request->query('module', ''));
        $action = trim((string) $request->query('action', ''));

        $logQuery = DB::table('operation_logs')
            ->leftJoin('users', 'users.id', '=', 'operation_logs.user_id')
            ->where('operation_logs.scope', 'platform');

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
            ->where('scope', 'platform')
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

        return view('admin.platform.logs.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'logs' => $logs,
            'keyword' => $keyword,
            'selectedScope' => 'platform',
            'selectedModule' => $module,
            'selectedAction' => $action,
            'moduleOptions' => $moduleOptions,
            'pageTitle' => '操作日志',
            'pageDescription' => '仅展示平台管理相关的最近操作记录，系统最多保留最近 500 条。',
            'showScopeFilter' => false,
            'formRoute' => route('admin.logs.index'),
        ]);
    }
}
