<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use App\Support\Modules\ModuleManager;
use App\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function __construct(
        protected ModuleManager $moduleManager,
        protected SystemSettings $systemSettings
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
            'guestbookRiskSettings' => $resolvedModule['code'] === 'guestbook' ? [
                'limit_ip_window_seconds' => (string) old('limit_ip_window_seconds', $this->systemSettings->guestbookLimitIpWindowSeconds()),
                'limit_ip_max_attempts' => (string) old('limit_ip_max_attempts', $this->systemSettings->guestbookLimitIpMaxAttempts()),
                'limit_ip_block_seconds' => (string) old('limit_ip_block_seconds', $this->systemSettings->guestbookLimitIpBlockSeconds()),
                'limit_phone_window_seconds' => (string) old('limit_phone_window_seconds', $this->systemSettings->guestbookLimitPhoneWindowSeconds()),
                'limit_phone_max_attempts' => (string) old('limit_phone_max_attempts', $this->systemSettings->guestbookLimitPhoneMaxAttempts()),
                'limit_phone_block_seconds' => (string) old('limit_phone_block_seconds', $this->systemSettings->guestbookLimitPhoneBlockSeconds()),
                'limit_captcha_verify_window_seconds' => (string) old('limit_captcha_verify_window_seconds', $this->systemSettings->guestbookLimitCaptchaVerifyWindowSeconds()),
                'limit_captcha_verify_max_attempts' => (string) old('limit_captcha_verify_max_attempts', $this->systemSettings->guestbookLimitCaptchaVerifyMaxAttempts()),
                'limit_captcha_verify_block_seconds' => (string) old('limit_captcha_verify_block_seconds', $this->systemSettings->guestbookLimitCaptchaVerifyBlockSeconds()),
            ] : null,
        ]);
    }

    public function updateGuestbookRisk(Request $request, string $module): RedirectResponse
    {
        $this->authorizePlatform($request, 'module.manage');
        $this->moduleManager->synchronize();

        $targetModule = $this->moduleManager->findByCode($module);
        abort_unless($targetModule && $targetModule['code'] === 'guestbook', 404);

        $validated = Validator::make($request->all(), [
            'limit_ip_window_seconds' => ['required', 'integer', 'min:10', 'max:3600'],
            'limit_ip_max_attempts' => ['required', 'integer', 'min:1', 'max:100'],
            'limit_ip_block_seconds' => ['required', 'integer', 'min:10', 'max:86400'],
            'limit_phone_window_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
            'limit_phone_max_attempts' => ['required', 'integer', 'min:1', 'max:50'],
            'limit_phone_block_seconds' => ['required', 'integer', 'min:10', 'max:86400'],
            'limit_captcha_verify_window_seconds' => ['required', 'integer', 'min:10', 'max:3600'],
            'limit_captcha_verify_max_attempts' => ['required', 'integer', 'min:1', 'max:200'],
            'limit_captcha_verify_block_seconds' => ['required', 'integer', 'min:10', 'max:86400'],
        ], [
            'limit_ip_window_seconds.required' => '请填写 IP 限流时间窗口。',
            'limit_ip_max_attempts.required' => '请填写 IP 限流次数。',
            'limit_ip_block_seconds.required' => '请填写 IP 超限限制时长。',
            'limit_phone_window_seconds.required' => '请填写手机号限流时间窗口。',
            'limit_phone_max_attempts.required' => '请填写手机号限流次数。',
            'limit_phone_block_seconds.required' => '请填写手机号超限限制时长。',
            'limit_captcha_verify_window_seconds.required' => '请填写验证码校验限流时间窗口。',
            'limit_captcha_verify_max_attempts.required' => '请填写验证码校验限流次数。',
            'limit_captcha_verify_block_seconds.required' => '请填写验证码超限限制时长。',
        ])->validate();

        $now = now();
        $userId = (int) $request->user()->id;
        $settings = [
            'guestbook.limit_ip_window_seconds' => (string) $validated['limit_ip_window_seconds'],
            'guestbook.limit_ip_max_attempts' => (string) $validated['limit_ip_max_attempts'],
            'guestbook.limit_ip_block_seconds' => (string) $validated['limit_ip_block_seconds'],
            'guestbook.limit_phone_window_seconds' => (string) $validated['limit_phone_window_seconds'],
            'guestbook.limit_phone_max_attempts' => (string) $validated['limit_phone_max_attempts'],
            'guestbook.limit_phone_block_seconds' => (string) $validated['limit_phone_block_seconds'],
            'guestbook.limit_captcha_verify_window_seconds' => (string) $validated['limit_captcha_verify_window_seconds'],
            'guestbook.limit_captcha_verify_max_attempts' => (string) $validated['limit_captcha_verify_max_attempts'],
            'guestbook.limit_captcha_verify_block_seconds' => (string) $validated['limit_captcha_verify_block_seconds'],
        ];

        foreach ($settings as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                [
                    'setting_value' => $value,
                    'autoload' => 1,
                    'updated_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        return redirect()
            ->route('admin.platform.modules.show', $targetModule['code'])
            ->with('status', '留言风控参数已更新。');
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
