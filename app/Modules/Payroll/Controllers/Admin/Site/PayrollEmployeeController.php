<?php

namespace App\Modules\Payroll\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Support\PayrollModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PayrollEmployeeController extends Controller
{
    public function __construct(
        protected PayrollModule $payrollModule
    ) {
    }

    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'payroll.employee');
        $module = $this->resolveModuleOrAbort((int) $currentSite->id);
        $keyword = trim((string) $request->query('keyword', ''));
        $status = trim((string) $request->query('status', ''));

        $employees = DB::table('module_payroll_employees')
            ->where('site_id', $currentSite->id)
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery->where('name', 'like', '%'.$keyword.'%')
                        ->orWhere('mobile', 'like', '%'.$keyword.'%')
                        ->orWhere('wechat_nickname', 'like', '%'.$keyword.'%')
                        ->orWhere('wechat_openid', 'like', '%'.$keyword.'%');
                });
            })
            ->when(in_array($status, ['pending', 'approved', 'disabled'], true), fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('payroll::admin.employees.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'module' => $module,
            'employees' => $employees,
            'keyword' => $keyword,
            'status' => $status,
        ]);
    }

    public function approve(Request $request, string $employeeId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'payroll.employee');
        $employee = $this->findEmployeeOrAbort((int) $currentSite->id, $employeeId);

        if ($this->duplicateEmployeeName((int) $currentSite->id, (string) $employee->name, (int) $employee->id)) {
            return redirect()
                ->route('admin.payroll.employees.index')
                ->withErrors(['employee' => '姓名重复，请先处理重复员工后再审核。']);
        }

        DB::table('module_payroll_employees')
            ->where('id', $employee->id)
            ->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => (int) $request->user()->id,
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('admin.payroll.employees.index')
            ->with('status', '员工已审核通过。');
    }

    public function toggle(Request $request, string $employeeId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'payroll.employee');
        $employee = $this->findEmployeeOrAbort((int) $currentSite->id, $employeeId);

        $nextStatus = $employee->status === 'disabled' ? 'approved' : 'disabled';

        if ($nextStatus === 'approved' && $this->duplicateEmployeeName((int) $currentSite->id, (string) $employee->name, (int) $employee->id)) {
            return redirect()
                ->route('admin.payroll.employees.index')
                ->withErrors(['employee' => '姓名重复，请先处理重复员工后再启用。']);
        }

        DB::table('module_payroll_employees')
            ->where('id', $employee->id)
            ->update([
                'status' => $nextStatus,
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('admin.payroll.employees.index')
            ->with('status', $nextStatus === 'disabled' ? '员工账户已禁用。' : '员工账户已启用。');
    }

    public function resetPassword(Request $request, string $employeeId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'payroll.employee');
        $employee = $this->findEmployeeOrAbort((int) $currentSite->id, $employeeId);

        DB::table('module_payroll_employees')
            ->where('id', $employee->id)
            ->update([
                'password_enabled' => 0,
                'password_hash' => null,
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('admin.payroll.employees.index')
            ->with('status', '员工自定义密码已重置。');
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveModuleOrAbort(int $siteId): array
    {
        $module = $this->payrollModule->boundForSite($siteId);
        abort_unless(is_array($module), 404);

        return $module;
    }

    protected function findEmployeeOrAbort(int $siteId, string $employeeId): object
    {
        $employee = DB::table('module_payroll_employees')
            ->where('site_id', $siteId)
            ->where('id', $employeeId)
            ->first();

        abort_unless($employee, 404);

        return $employee;
    }

    protected function duplicateEmployeeName(int $siteId, string $name, int $excludeId): bool
    {
        $sanitizedName = $this->sanitizeName($name);

        if ($sanitizedName === '') {
            return false;
        }

        return DB::table('module_payroll_employees')
            ->where('site_id', $siteId)
            ->where('id', '!=', $excludeId)
            ->get(['name'])
            ->contains(fn ($employee) => $this->sanitizeName((string) $employee->name) === $sanitizedName);
    }

    protected function sanitizeName(string $value): string
    {
        return trim((string) \Illuminate\Support\Str::of($value)
            ->replace("\u{3000}", ' ')
            ->replace("\xc2\xa0", ' ')
            ->squish());
    }
}
