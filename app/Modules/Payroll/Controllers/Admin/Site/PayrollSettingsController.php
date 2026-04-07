<?php

namespace App\Modules\Payroll\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Support\PayrollModule;
use App\Modules\Payroll\Support\PayrollSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PayrollSettingsController extends Controller
{
    public function __construct(
        protected PayrollModule $payrollModule,
        protected PayrollSettings $payrollSettings
    ) {
    }

    public function edit(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'payroll.setting');
        $module = $this->resolveModuleOrAbort((int) $currentSite->id);

        return view('payroll::admin.settings', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'module' => $module,
            'settings' => $this->payrollSettings->forSite((int) $currentSite->id),
        ]);
    }

    public function help(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'payroll.view');
        $module = $this->resolveModuleOrAbort((int) $currentSite->id);

        return view('payroll::admin.help', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'module' => $module,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'payroll.setting');
        $this->resolveModuleOrAbort((int) $currentSite->id);
        $currentSettings = $this->payrollSettings->forSite((int) $currentSite->id);

        $request->merge([
            'wechat_app_id' => trim((string) $request->input('wechat_app_id')),
            'wechat_app_secret' => trim((string) $request->input('wechat_app_secret')),
            'registration_disabled_message' => trim((string) $request->input('registration_disabled_message')),
        ]);

        $validated = Validator::make(
            $request->all(),
            [
                'enabled' => ['nullable', 'boolean'],
                'registration_enabled' => ['nullable', 'boolean'],
                'wechat_app_id' => ['nullable', 'string', 'max:100'],
                'wechat_app_secret' => ['nullable', 'string', 'max:150'],
                'registration_disabled_message' => ['required', 'string', 'max:120'],
            ],
            [
                'registration_disabled_message.required' => '请填写注册关闭提示文案。',
                'registration_disabled_message.max' => '注册关闭提示文案不能超过 120 个字符。',
            ]
        )->validate();

        $this->payrollSettings->saveForSite((int) $currentSite->id, [
            'enabled' => $request->boolean('enabled'),
            'registration_enabled' => $request->boolean('registration_enabled'),
            'wechat_app_id' => $validated['wechat_app_id'] ?? '',
            'wechat_app_secret' => ($validated['wechat_app_secret'] ?? '') !== ''
                ? $validated['wechat_app_secret']
                : ($currentSettings['wechat_app_secret'] ?? ''),
            'registration_disabled_message' => $validated['registration_disabled_message'],
        ], (int) $request->user()->id);

        return redirect()
            ->route('admin.payroll.settings')
            ->with('status', '工资查询模块配置已保存。');
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
}
