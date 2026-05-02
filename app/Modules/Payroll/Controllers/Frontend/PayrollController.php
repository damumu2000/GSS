<?php

namespace App\Modules\Payroll\Controllers\Frontend;

use App\Http\Controllers\SiteController;
use App\Modules\Payroll\Support\PayrollModule;
use App\Modules\Payroll\Support\PayrollSettings;
use App\Modules\Payroll\Support\PayrollWechatAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Throwable;

class PayrollController extends SiteController
{
    public function __construct(
        protected PayrollModule $payrollModule,
        protected PayrollSettings $payrollSettings,
        protected PayrollWechatAuth $payrollWechatAuth
    ) {
    }

    public function index(Request $request): Response|RedirectResponse
    {
        [$site, $settings, $siteQuery, $available] = $this->resolvePayrollContext($request);

        if (! $available) {
            return $this->disabledResponse($site, $settings, $siteQuery);
        }

        if ($redirect = $this->captureLocalPreviewIdentity($request, $site, $siteQuery)) {
            return $redirect;
        }

        $employee = $this->resolveApprovedEmployee($request, $site, $settings, $siteQuery);

        if ($employee instanceof RedirectResponse || $employee instanceof Response) {
            return $employee;
        }

        if ($this->requiresPasswordUnlock($request, $site, $employee)) {
            return redirect()->route('site.payroll.password', $siteQuery);
        }

        $batches = $this->employeeBatches((int) $site->id, (string) $employee->name);

        return response()->view('payroll::frontend.index', [
            'site' => $site,
            'settings' => $settings,
            'siteQuery' => $siteQuery,
            'employee' => $employee,
            'batches' => $batches,
        ]);
    }

    public function wechatRedirect(Request $request): RedirectResponse|Response
    {
        [$site, $settings, $siteQuery, $available] = $this->resolvePayrollContext($request);

        if (! $available) {
            return $this->disabledResponse($site, $settings, $siteQuery);
        }

        if ($this->captureLocalPreviewIdentity($request, $site, $siteQuery)) {
            return redirect()->route('site.payroll.index', $siteQuery);
        }

        if (! $this->isWechatReady($settings)) {
            return response()->view('payroll::frontend.disabled', [
                'site' => $site,
                'settings' => $settings,
                'siteQuery' => $siteQuery,
                'disabledTitle' => '微信登录暂未配置',
                'disabledMessage' => '当前站点尚未完成微信网页登录配置，请联系管理员。',
            ]);
        }

        $state = $this->payrollWechatAuth->generateState();
        $request->session()->put($this->stateSessionKey((int) $site->id), $state);

        return redirect()->away($this->payrollWechatAuth->authorizeUrl(
            (string) $settings['wechat_app_id'],
            route('site.payroll.wechat.callback', $siteQuery),
            $state
        ));
    }

    public function wechatCallback(Request $request): RedirectResponse|Response
    {
        [$site, $settings, $siteQuery, $available] = $this->resolvePayrollContext($request);

        if (! $available) {
            return redirect()->route('site.payroll.index', $siteQuery);
        }

        $state = trim((string) $request->query('state', ''));
        $code = trim((string) $request->query('code', ''));
        $expectedState = (string) $request->session()->pull($this->stateSessionKey((int) $site->id), '');

        if ($state === '' || $expectedState === '' || ! hash_equals($expectedState, $state) || $code === '') {
            return response()->view('payroll::frontend.disabled', [
                'site' => $site,
                'settings' => $settings,
                'siteQuery' => $siteQuery,
                'disabledTitle' => '微信登录校验失败',
                'disabledMessage' => '当前微信登录校验未通过，请重新从微信入口打开工资查询页面。',
            ]);
        }

        try {
            $identity = $this->payrollWechatAuth->userProfile(
                (string) $settings['wechat_app_id'],
                (string) $settings['wechat_app_secret'],
                $code
            );
        } catch (Throwable) {
            return response()->view('payroll::frontend.disabled', [
                'site' => $site,
                'settings' => $settings,
                'siteQuery' => $siteQuery,
                'disabledTitle' => '微信登录失败',
                'disabledMessage' => '当前无法完成微信授权，请稍后重试或联系管理员。',
            ]);
        }

        $request->session()->put($this->identitySessionKey((int) $site->id), $identity);

        return redirect()->route('site.payroll.index', $siteQuery);
    }

    public function register(Request $request): Response|RedirectResponse
    {
        [$site, $settings, $siteQuery, $available] = $this->resolvePayrollContext($request);

        if (! $available) {
            return $this->disabledResponse($site, $settings, $siteQuery);
        }

        if ($redirect = $this->captureLocalPreviewIdentity($request, $site, $siteQuery)) {
            return $redirect;
        }

        $identity = $this->currentIdentity($request, (int) $site->id);
        if ($identity === null) {
            return redirect()->route('site.payroll.wechat.redirect', $siteQuery);
        }

        $employee = $this->employeeByOpenid((int) $site->id, $identity['openid']);

        return response()->view('payroll::frontend.register', [
            'site' => $site,
            'settings' => $settings,
            'siteQuery' => $siteQuery,
            'employee' => $employee,
        ]);
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        [$site, $settings, $siteQuery, $available] = $this->resolvePayrollContext($request);

        if (! $available) {
            return redirect()->route('site.payroll.index', $siteQuery);
        }

        $identity = $this->currentIdentity($request, (int) $site->id);
        if ($identity === null) {
            return redirect()->route('site.payroll.wechat.redirect', $siteQuery);
        }

        if (! $settings['registration_enabled']) {
            return redirect()
                ->route('site.payroll.register', $siteQuery)
                ->withErrors(['register' => $settings['registration_disabled_message']]);
        }

        $limiterKey = 'payroll-register:'.$site->id.':'.$request->ip().':'.$identity['openid'];
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            return redirect()
                ->route('site.payroll.register', $siteQuery)
                ->withErrors(['register' => '提交过于频繁，请稍后再试。']);
        }

        RateLimiter::hit($limiterKey, 120);

        $request->merge([
            'name' => $this->sanitizeName($request->input('name')),
            'mobile' => preg_replace('/\D+/', '', (string) $request->input('mobile')),
        ]);

        $validated = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:20', 'regex:/^[\p{Han}A-Za-z·]{2,20}$/u'],
            'mobile' => ['required', 'regex:/^1[3-9]\d{9}$/'],
        ], [
            'name.required' => '请填写姓名。',
            'name.regex' => '姓名仅支持中文、英文和间隔号。',
            'mobile.required' => '请填写手机号码。',
            'mobile.regex' => '请填写 11 位大陆手机号。',
        ])->validate();

        $employee = $this->employeeByOpenid((int) $site->id, $identity['openid']);

        if ($duplicateName = $this->duplicateEmployeeName((int) $site->id, $validated['name'], $employee?->id)) {
            return redirect()
                ->route('site.payroll.register', $siteQuery)
                ->withErrors(['register' => '姓名重复，请联系管理员处理该问题。']);
        }

        if ($employee && in_array($employee->status, ['approved', 'disabled'], true)) {
            return redirect()
                ->route('site.payroll.register', $siteQuery)
                ->withErrors(['register' => $employee->status === 'disabled'
                    ? '当前账户已被禁用，不能重新登记，请联系管理员。'
                    : '当前账户已审核通过，无需重复登记。']);
        }

        if ($employee) {
            DB::table('module_payroll_employees')
                ->where('id', $employee->id)
                ->update([
                    'wechat_unionid' => $identity['unionid'],
                    'wechat_nickname' => $identity['nickname'],
                    'wechat_avatar' => $identity['avatar'],
                    'name' => $validated['name'],
                    'mobile' => $validated['mobile'],
                    'updated_at' => now(),
                ]);
        } else {
            try {
                DB::table('module_payroll_employees')->insert([
                    'site_id' => $site->id,
                    'wechat_openid' => $identity['openid'],
                    'wechat_unionid' => $identity['unionid'],
                    'wechat_nickname' => $identity['nickname'],
                    'wechat_avatar' => $identity['avatar'],
                    'name' => $validated['name'],
                    'mobile' => $validated['mobile'],
                    'status' => 'pending',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            } catch (QueryException $exception) {
                if ($this->isDuplicateOpenidException($exception)) {
                    return redirect()
                        ->route('site.payroll.register', $siteQuery)
                        ->withErrors(['register' => '当前微信身份已存在，请刷新页面后重试。']);
                }

                throw $exception;
            }
        }

        return redirect()
            ->route('site.payroll.register', $siteQuery)
            ->with('status', '登记信息已提交，请等待管理员审核。');
    }

    public function password(Request $request): Response|RedirectResponse
    {
        [$site, $settings, $siteQuery, $available] = $this->resolvePayrollContext($request);

        if (! $available) {
            return $this->disabledResponse($site, $settings, $siteQuery);
        }

        if ($redirect = $this->captureLocalPreviewIdentity($request, $site, $siteQuery)) {
            return $redirect;
        }

        $employee = $this->resolveApprovedEmployee($request, $site, $settings, $siteQuery);

        if ($employee instanceof RedirectResponse || $employee instanceof Response) {
            return $employee;
        }

        return response()->view('payroll::frontend.password', [
            'site' => $site,
            'settings' => $settings,
            'siteQuery' => $siteQuery,
            'employee' => $employee,
        ]);
    }

    public function unlock(Request $request): RedirectResponse
    {
        [$site, $settings, $siteQuery, $available] = $this->resolvePayrollContext($request);

        if (! $available) {
            return redirect()->route('site.payroll.index', $siteQuery);
        }

        $employee = $this->resolveApprovedEmployee($request, $site, $settings, $siteQuery);

        if ($employee instanceof RedirectResponse || $employee instanceof Response) {
            return redirect()->route('site.payroll.index', $siteQuery);
        }

        $limiterKey = 'payroll-unlock:'.$site->id.':'.$employee->id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($limiterKey, 8)) {
            return redirect()
                ->route('site.payroll.password', $siteQuery)
                ->withErrors(['password' => '密码输入次数过多，请稍后再试。']);
        }

        $validated = Validator::make($request->all(), [
            'password' => ['required', 'string', 'min:4', 'max:32'],
        ], [
            'password.required' => '请输入密码。',
        ])->validate();

        if (! $employee->password_enabled || ! is_string($employee->password_hash) || ! Hash::check($validated['password'], $employee->password_hash)) {
            RateLimiter::hit($limiterKey, 180);

            return redirect()
                ->route('site.payroll.password', $siteQuery)
                ->withErrors(['password' => '密码不正确，请重新输入。']);
        }

        RateLimiter::clear($limiterKey);
        $request->session()->put($this->unlockSessionKey((int) $site->id, (int) $employee->id), true);

        return redirect()->route('site.payroll.index', $siteQuery);
    }

    public function passwordManage(Request $request): Response|RedirectResponse
    {
        [$site, $settings, $siteQuery, $available] = $this->resolvePayrollContext($request);

        if (! $available) {
            return $this->disabledResponse($site, $settings, $siteQuery);
        }

        if ($redirect = $this->captureLocalPreviewIdentity($request, $site, $siteQuery)) {
            return $redirect;
        }

        $employee = $this->resolveApprovedEmployee($request, $site, $settings, $siteQuery);

        if ($employee instanceof RedirectResponse || $employee instanceof Response) {
            return $employee;
        }

        if ($this->requiresPasswordUnlock($request, $site, $employee)) {
            return redirect()->route('site.payroll.password', $siteQuery);
        }

        return response()->view('payroll::frontend.password', [
            'site' => $site,
            'settings' => $settings,
            'siteQuery' => $siteQuery,
            'employee' => $employee,
            'manageMode' => true,
        ]);
    }

    public function passwordSave(Request $request): RedirectResponse
    {
        [$site, $settings, $siteQuery, $available] = $this->resolvePayrollContext($request);

        if (! $available) {
            return redirect()->route('site.payroll.index', $siteQuery);
        }

        $employee = $this->resolveApprovedEmployee($request, $site, $settings, $siteQuery);

        if ($employee instanceof RedirectResponse || $employee instanceof Response) {
            return redirect()->route('site.payroll.index', $siteQuery);
        }

        if ($this->requiresPasswordUnlock($request, $site, $employee)) {
            return redirect()->route('site.payroll.password', $siteQuery);
        }

        $validated = Validator::make($request->all(), [
            'password_enabled' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:4', 'max:32', 'confirmed'],
        ], [
            'password.confirmed' => '两次输入的密码不一致。',
            'password.min' => '密码至少需要 4 位。',
        ])->after(function ($validator) use ($request, $employee): void {
            if (! $request->boolean('password_enabled')) {
                return;
            }

            $password = trim((string) $request->input('password', ''));
            $hasExistingPassword = is_string($employee->password_hash) && trim($employee->password_hash) !== '';

            if ($password === '' && ! $hasExistingPassword) {
                $validator->errors()->add('password', '开启密码保护后，请先设置登录密码。');
            }
        })->validate();

        $enabled = $request->boolean('password_enabled');

        DB::table('module_payroll_employees')
            ->where('id', $employee->id)
            ->update([
                'password_enabled' => $enabled ? 1 : 0,
                'password_hash' => $enabled && ! empty($validated['password']) ? Hash::make($validated['password']) : ($enabled ? $employee->password_hash : null),
                'updated_at' => now(),
            ]);

        if (! $enabled) {
            $request->session()->forget($this->unlockSessionKey((int) $site->id, (int) $employee->id));
        }

        return redirect()
            ->route('site.payroll.password.manage', $siteQuery)
            ->with('status', '密码设置已保存。');
    }

    public function logout(Request $request): RedirectResponse
    {
        [$site, , $siteQuery, ] = $this->resolvePayrollContext($request);
        $identity = $this->currentIdentity($request, (int) $site->id);

        if ($identity) {
            $employee = $this->employeeByOpenid((int) $site->id, $identity['openid']);
            if ($employee) {
                $request->session()->forget($this->unlockSessionKey((int) $site->id, (int) $employee->id));
                $request->session()->forget($this->loginSessionKey((int) $site->id, (int) $employee->id));
            }
        }

        $request->session()->forget($this->identitySessionKey((int) $site->id));

        return redirect()->route('site.payroll.index', $siteQuery);
    }

    public function detail(Request $request, string $batchId, string $type): Response|RedirectResponse
    {
        [$site, $settings, $siteQuery, $available] = $this->resolvePayrollContext($request);

        if (! $available) {
            return $this->disabledResponse($site, $settings, $siteQuery);
        }

        if ($redirect = $this->captureLocalPreviewIdentity($request, $site, $siteQuery)) {
            return $redirect;
        }

        $employee = $this->resolveApprovedEmployee($request, $site, $settings, $siteQuery);

        if ($employee instanceof RedirectResponse || $employee instanceof Response) {
            return $employee;
        }

        if ($this->requiresPasswordUnlock($request, $site, $employee)) {
            return redirect()->route('site.payroll.password', $siteQuery);
        }

        $batch = DB::table('module_payroll_batches')
            ->where('site_id', $site->id)
            ->where('id', $batchId)
            ->first();

        abort_unless($batch, 404);

        $record = DB::table('module_payroll_records')
            ->where('site_id', $site->id)
            ->where('batch_id', $batch->id)
            ->where('sheet_type', $type)
            ->where('employee_name', $employee->name)
            ->first();

        abort_unless($record, 404);

        return response()->view('payroll::frontend.show', [
            'site' => $site,
            'settings' => $settings,
            'siteQuery' => $siteQuery,
            'sheetType' => $type,
            'batch' => $batch,
            'employee' => $employee,
            'items' => json_decode((string) $record->items_json, true) ?: [],
        ]);
    }

    /**
     * @return array{0: object, 1: array<string,mixed>, 2: array<string,string>, 3: bool}
     */
    protected function resolvePayrollContext(Request $request): array
    {
        $site = $this->resolvedPayrollSite($request);
        abort_unless($site, 404);
        if ($disabled = $this->renderWhenFrontendDisabled($site)) {
            throw new HttpResponseException($disabled);
        }

        $settings = $this->payrollSettings->forSite((int) $site->id);
        $module = $this->payrollModule->activeForSite((int) $site->id);
        $siteQuery = $request->query('site') ? ['site' => $site->site_key] : [];
        $available = is_array($module) && $settings['enabled'];

        return [$site, $settings, $siteQuery, $available];
    }

    protected function resolvedPayrollSite(Request $request): ?object
    {
        $host = mb_strtolower(trim((string) $request->getHost()));

        if ($host !== '') {
            $site = \Illuminate\Support\Facades\DB::table('site_domains')
                ->join('sites', 'sites.id', '=', 'site_domains.site_id')
                ->whereRaw('LOWER(site_domains.domain) = ?', [$host])
                ->where('site_domains.status', 1)
                ->where('sites.status', 1)
                ->first(['sites.*']);

            if ($site) {
                return $site;
            }
        }

        if (! in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return null;
        }

        $siteKey = trim((string) $request->query('site', ''));
        if ($siteKey === '') {
            return null;
        }

        return \Illuminate\Support\Facades\DB::table('sites')
            ->where('site_key', $siteKey)
            ->where('status', 1)
            ->first();
    }

    protected function disabledResponse(object $site, array $settings, array $siteQuery): Response
    {
        return response()->view('payroll::frontend.disabled', [
            'site' => $site,
            'settings' => $settings,
            'siteQuery' => $siteQuery,
        ]);
    }

    protected function captureLocalPreviewIdentity(Request $request, object $site, array $siteQuery): ?RedirectResponse
    {
        $host = mb_strtolower(trim((string) $request->getHost()));
        if (! in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return null;
        }

        $mockOpenid = trim((string) $request->query('mock_openid', ''));
        if ($mockOpenid === '') {
            return null;
        }

        $request->session()->put($this->identitySessionKey((int) $site->id), [
            'openid' => $mockOpenid,
            'unionid' => trim((string) $request->query('mock_unionid', '')),
            'nickname' => trim((string) $request->query('mock_nickname', '本地预览用户')),
            'avatar' => trim((string) $request->query('mock_avatar', '')),
        ]);

        return redirect()->route('site.payroll.index', $siteQuery);
    }

    protected function resolveApprovedEmployee(Request $request, object $site, array $settings, array $siteQuery): \stdClass|RedirectResponse|Response
    {
        $identity = $this->currentIdentity($request, (int) $site->id);
        if ($identity === null) {
            return redirect()->route('site.payroll.wechat.redirect', $siteQuery);
        }

        $employee = $this->employeeByOpenid((int) $site->id, $identity['openid']);

        if (! $employee) {
            if (! $settings['registration_enabled']) {
                return response()->view('payroll::frontend.disabled', [
                    'site' => $site,
                    'settings' => $settings,
                    'siteQuery' => $siteQuery,
                    'disabledTitle' => '已禁止自动注册',
                    'disabledMessage' => $settings['registration_disabled_message'],
                ]);
            }

            return redirect()->route('site.payroll.register', $siteQuery);
        }

        if ($employee->status === 'pending') {
            return redirect()->route('site.payroll.register', $siteQuery);
        }

        if ($employee->status === 'disabled') {
            return response()->view('payroll::frontend.disabled', [
                'site' => $site,
                'settings' => $settings,
                'siteQuery' => $siteQuery,
                'disabledTitle' => '账户已禁用',
                'disabledMessage' => '当前工资查询账户已被禁用，请联系管理员处理。',
            ]);
        }

        $sanitizedName = $this->sanitizeName($employee->name);

        if ($this->duplicateEmployeeName((int) $site->id, $sanitizedName, (int) $employee->id)) {
            return response()->view('payroll::frontend.disabled', [
                'site' => $site,
                'settings' => $settings,
                'siteQuery' => $siteQuery,
                'disabledTitle' => '姓名信息异常',
                'disabledMessage' => '当前姓名存在重复，请联系管理员处理。',
            ]);
        }

        $resolvedEmployee = (object) array_merge((array) $employee, [
            'name' => $sanitizedName,
            'wechat_nickname' => $identity['nickname'] ?: $employee->wechat_nickname,
            'wechat_unionid' => $identity['unionid'] ?: $employee->wechat_unionid,
            'wechat_avatar' => $identity['avatar'] ?: $employee->wechat_avatar,
        ]);

        $updates = [
            'name' => $sanitizedName,
            'wechat_nickname' => $resolvedEmployee->wechat_nickname,
            'wechat_unionid' => $resolvedEmployee->wechat_unionid,
            'wechat_avatar' => $resolvedEmployee->wechat_avatar,
        ];

        if (! $request->session()->has($this->loginSessionKey((int) $site->id, (int) $employee->id))) {
            $updates['last_login_at'] = now();
            $updates['last_login_ip'] = $request->ip();

            DB::table('module_payroll_login_logs')->insert([
                'site_id' => $site->id,
                'employee_id' => $employee->id,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'created_at' => now(),
            ]);

            $request->session()->put($this->loginSessionKey((int) $site->id, (int) $employee->id), true);
        }

        DB::table('module_payroll_employees')
            ->where('id', $employee->id)
            ->update(array_merge($updates, ['updated_at' => now()]));

        return $resolvedEmployee;
    }

    /**
     * @return array<string, string>|null
     */
    protected function currentIdentity(Request $request, int $siteId): ?array
    {
        $identity = $request->session()->get($this->identitySessionKey($siteId));

        if (! is_array($identity) || trim((string) ($identity['openid'] ?? '')) === '') {
            return null;
        }

        return [
            'openid' => trim((string) ($identity['openid'] ?? '')),
            'unionid' => trim((string) ($identity['unionid'] ?? '')),
            'nickname' => trim((string) ($identity['nickname'] ?? '')),
            'avatar' => trim((string) ($identity['avatar'] ?? '')),
        ];
    }

    protected function employeeByOpenid(int $siteId, string $openid): ?object
    {
        $employee = DB::table('module_payroll_employees')
            ->where('site_id', $siteId)
            ->where('wechat_openid', $openid)
            ->first();

        return $employee ?: null;
    }

    protected function duplicateEmployeeName(int $siteId, string $name, ?int $excludeId = null): bool
    {
        $sanitizedName = $this->sanitizeName($name);

        if ($sanitizedName === '') {
            return false;
        }

        return DB::table('module_payroll_employees')
            ->where('site_id', $siteId)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->get(['name'])
            ->contains(function ($employee) use ($sanitizedName): bool {
                return $this->sanitizeName((string) $employee->name) === $sanitizedName;
            });
    }

    protected function isDuplicateOpenidException(QueryException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, 'module_payroll_employees_site_openid_unique')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint');
    }

    protected function requiresPasswordUnlock(Request $request, object $site, object $employee): bool
    {
        return (bool) $employee->password_enabled
            && ! $request->session()->has($this->unlockSessionKey((int) $site->id, (int) $employee->id));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function employeeBatches(int $siteId, string $employeeName): array
    {
        $recordRows = DB::table('module_payroll_records')
            ->join('module_payroll_batches', 'module_payroll_batches.id', '=', 'module_payroll_records.batch_id')
            ->where('module_payroll_records.site_id', $siteId)
            ->where('module_payroll_records.employee_name', $employeeName)
            ->orderByDesc('module_payroll_batches.month_key')
            ->get([
                'module_payroll_batches.id as batch_id',
                'module_payroll_batches.month_key',
                'module_payroll_records.sheet_type',
            ]);

        return $recordRows
            ->groupBy('batch_id')
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'batch_id' => (int) $first->batch_id,
                    'month_key' => (string) $first->month_key,
                    'has_salary' => $group->contains(fn ($row) => $row->sheet_type === 'salary'),
                    'has_performance' => $group->contains(fn ($row) => $row->sheet_type === 'performance'),
                ];
            })
            ->values()
            ->all();
    }

    protected function identitySessionKey(int $siteId): string
    {
        return 'payroll.identity.'.$siteId;
    }

    protected function stateSessionKey(int $siteId): string
    {
        return 'payroll.wechat_state.'.$siteId;
    }

    protected function unlockSessionKey(int $siteId, int $employeeId): string
    {
        return 'payroll.unlock.'.$siteId.'.'.$employeeId;
    }

    protected function loginSessionKey(int $siteId, int $employeeId): string
    {
        return 'payroll.login.'.$siteId.'.'.$employeeId;
    }

    protected function sanitizeName(mixed $value): string
    {
        return trim(Str::of((string) $value)->replace("\u{3000}", ' ')->replace("\xc2\xa0", ' ')->squish()->value());
    }

    protected function isWechatReady(array $settings): bool
    {
        return trim((string) ($settings['wechat_app_id'] ?? '')) !== ''
            && trim((string) ($settings['wechat_app_secret'] ?? '')) !== '';
    }
}
