<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use App\Support\PlatformMailSettings;
use App\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function __construct(
        protected SystemSettings $systemSettings,
        protected PlatformMailSettings $platformMailSettings,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorizePlatform($request, 'system.setting.manage');
        $currentSite = $this->currentSite($request);
        $activeTab = $this->normalizeTab((string) $request->query('tab', 'basic'));

        return view('admin.platform.settings.index', [
            'currentSite' => $currentSite,
            'settings' => $this->systemSettings->formDefaults(),
            'activeTab' => $activeTab,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizePlatform($request, 'system.setting.manage');

        $validator = Validator::make($request->all(), [
            'system_name' => ['required', 'string', 'max:100'],
            'system_version' => ['required', 'string', 'max:50'],
            'attachment_allowed_extensions' => ['required', 'string', 'max:255'],
            'attachment_max_size_mb' => ['required', 'integer', 'min:1', 'max:1024'],
            'attachment_image_max_size_mb' => ['required', 'integer', 'min:1', 'max:512'],
            'attachment_image_max_width' => ['required', 'integer', 'min:100', 'max:20000'],
            'attachment_image_max_height' => ['required', 'integer', 'min:100', 'max:20000'],
            'attachment_image_auto_resize' => ['nullable', 'boolean'],
            'attachment_image_auto_compress' => ['nullable', 'boolean'],
            'attachment_image_quality' => ['required', 'integer', 'min:1', 'max:100'],
            'admin_enabled' => ['nullable', 'boolean'],
            'admin_disabled_message' => ['nullable', 'string', 'max:255'],
            'security_site_protection_enabled' => ['nullable', 'boolean'],
            'security_block_bad_path_enabled' => ['nullable', 'boolean'],
            'security_block_sql_injection_enabled' => ['nullable', 'boolean'],
            'security_block_xss_enabled' => ['nullable', 'boolean'],
            'security_block_path_traversal_enabled' => ['nullable', 'boolean'],
            'security_block_bad_upload_enabled' => ['nullable', 'boolean'],
            'security_rate_limit_enabled' => ['nullable', 'boolean'],
            'security_rate_limit_window_seconds' => ['nullable', 'integer', 'min:1', 'max:300'],
            'security_rate_limit_max_requests' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'security_rate_limit_sensitive_max_requests' => ['nullable', 'integer', 'min:1', 'max:500'],
            'security_event_retention_limit' => ['nullable', 'integer', 'min:20', 'max:1000'],
            'security_stats_retention_days' => ['nullable', 'integer', 'min:7', 'max:3650'],
            'admin_logo_file' => ['nullable', 'file', 'image', 'max:3072'],
            'admin_favicon_file' => ['nullable', 'file', 'mimes:ico,png', 'max:1024'],
            'admin_logo_clear' => ['nullable', 'boolean'],
            'admin_favicon_clear' => ['nullable', 'boolean'],
            'mail_enabled' => ['nullable', 'boolean'],
            'mail_driver' => ['nullable', 'string', 'in:smtp,log'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'string', 'in:,ssl,tls'],
            'mail_from_address' => ['nullable', 'email:filter', 'max:100'],
            'mail_from_name' => ['nullable', 'string', 'max:100'],
            'mail_reply_to_address' => ['nullable', 'email:filter', 'max:100'],
            'mail_timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:60'],
            'mail_rate_limit_enabled' => ['nullable', 'boolean'],
            'mail_rate_limit_window_seconds' => ['nullable', 'integer', 'min:10', 'max:3600'],
            'mail_rate_limit_global_max' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'mail_rate_limit_site_max' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'mail_rate_limit_scene_max' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'mail_rate_limit_recipient_window_seconds' => ['nullable', 'integer', 'min:60', 'max:86400'],
            'mail_rate_limit_recipient_max' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ], [
            'system_name.required' => '请填写系统名称。',
            'system_version.required' => '请填写系统版本号。',
            'attachment_allowed_extensions.required' => '请填写资源库允许上传的文件类型。',
            'admin_logo_file.image' => '后台 Logo 请上传图片文件。',
            'admin_favicon_file.mimes' => '后台 ICO 仅支持 ICO 或 PNG 文件。',
            'mail_from_address.email' => '发件邮箱格式不正确，请重新填写。',
            'mail_reply_to_address.email' => '回复邮箱格式不正确，请重新填写。',
        ]);

        $validator->after(function ($validator) use ($request): void {
            $normalizedExtensions = $this->normalizeExtensions((string) $request->input('attachment_allowed_extensions', ''));

            if ($normalizedExtensions === '') {
                $validator->errors()->add('attachment_allowed_extensions', '请至少保留一个合法的文件扩展名。');
            }

            if ((int) $request->input('attachment_image_max_size_mb', 0) > (int) $request->input('attachment_max_size_mb', 0)) {
                $validator->errors()->add('attachment_image_max_size_mb', '图片大小限制不能超过单文件大小限制。');
            }

            if ($request->boolean('admin_logo_clear') && $request->hasFile('admin_logo_file')) {
                $validator->errors()->add('admin_logo_file', '请不要同时上传和清除后台 Logo。');
            }

            if ($request->boolean('admin_favicon_clear') && $request->hasFile('admin_favicon_file')) {
                $validator->errors()->add('admin_favicon_file', '请不要同时上传和清除后台 ICO。');
            }

            $maxRequests = (int) $request->input('security_rate_limit_max_requests', $this->systemSettings->securityRateLimitMaxRequests());
            $sensitiveMaxRequests = (int) $request->input('security_rate_limit_sensitive_max_requests', $this->systemSettings->securityRateLimitSensitiveMaxRequests());

            if ($sensitiveMaxRequests > $maxRequests) {
                $validator->errors()->add('security_rate_limit_sensitive_max_requests', '敏感页面阈值不能高于普通页面阈值。');
            }

            $mailDriver = strtolower(trim((string) $request->input('mail_driver', 'log')));
            $mailEnabled = $request->boolean('mail_enabled');
            $mailPassword = trim((string) $request->input('mail_password', ''));
            $mailUsername = trim((string) $request->input('mail_username', ''));
            $hasStoredPassword = $this->systemSettings->mailPasswordConfigured();

            if ($mailEnabled && $mailDriver === 'smtp') {
                if (trim((string) $request->input('mail_host', '')) === '') {
                    $validator->errors()->add('mail_host', 'SMTP 主机不能为空。');
                }

                if ((int) $request->input('mail_port', 0) <= 0) {
                    $validator->errors()->add('mail_port', 'SMTP 端口不能为空。');
                }

                if (trim((string) $request->input('mail_from_address', '')) === '') {
                    $validator->errors()->add('mail_from_address', '请填写发件邮箱。');
                }

                if (trim((string) $request->input('mail_from_name', '')) === '') {
                    $validator->errors()->add('mail_from_name', '请填写发件名称。');
                }
            }

            if ($mailPassword !== '' && $mailUsername === '') {
                $validator->errors()->add('mail_username', '填写 SMTP 密码时必须同时填写 SMTP 用户名。');
            }

            if ($mailUsername !== '' && $mailPassword === '' && ! $hasStoredPassword) {
                $validator->errors()->add('mail_password', '填写 SMTP 用户名后必须设置 SMTP 密码。');
            }

            if ($mailUsername === '' && $hasStoredPassword) {
                $validator->errors()->add('mail_username', '已设置 SMTP 密码时必须保留 SMTP 用户名。');
            }

            $mailGlobalMax = (int) $request->input('mail_rate_limit_global_max', $this->systemSettings->mailRateLimitGlobalMax());
            $mailSiteMax = (int) $request->input('mail_rate_limit_site_max', $this->systemSettings->mailRateLimitSiteMax());
            $mailSceneMax = (int) $request->input('mail_rate_limit_scene_max', $this->systemSettings->mailRateLimitSceneMax());

            if ($mailSiteMax > $mailGlobalMax) {
                $validator->errors()->add('mail_rate_limit_site_max', '单站点发送上限不能高于平台总发送上限。');
            }

            if ($mailSceneMax > $mailSiteMax) {
                $validator->errors()->add('mail_rate_limit_scene_max', '单场景发送上限不能高于单站点发送上限。');
            }
        });

        $validated = $validator->validate();

        $now = now();
        $userId = (int) $request->user()->id;
        $systemName = $this->sanitizeSingleLine((string) $validated['system_name'], 100);
        $systemVersion = $this->sanitizeSingleLine((string) $validated['system_version'], 50);
        $normalizedExtensions = $this->normalizeExtensions((string) $validated['attachment_allowed_extensions']);
        $disabledMessage = $this->sanitizeTextarea((string) ($validated['admin_disabled_message'] ?? ''), 255);
        $mailPassword = trim((string) ($validated['mail_password'] ?? ''));
        $storedEncryptedPassword = $this->systemSettings->mailPasswordEncrypted();
        $mailPasswordEncrypted = $mailPassword !== ''
            ? $this->platformMailSettings->encryptPassword($mailPassword)
            : $storedEncryptedPassword;

        $settings = [
            'system.name' => $systemName,
            'system.version' => $systemVersion,
            'attachment.allowed_extensions' => $normalizedExtensions,
            'attachment.max_size_mb' => (string) $validated['attachment_max_size_mb'],
            'attachment.image_max_size_mb' => (string) $validated['attachment_image_max_size_mb'],
            'attachment.image_max_width' => (string) $validated['attachment_image_max_width'],
            'attachment.image_max_height' => (string) $validated['attachment_image_max_height'],
            'attachment.image_auto_resize' => $request->boolean('attachment_image_auto_resize') ? '1' : '0',
            'attachment.image_auto_compress' => $request->boolean('attachment_image_auto_compress') ? '1' : '0',
            'attachment.image_quality' => (string) $validated['attachment_image_quality'],
            'admin.enabled' => $request->boolean('admin_enabled') ? '1' : '0',
            'admin.disabled_message' => $disabledMessage,
            'security.site_protection_enabled' => $request->boolean('security_site_protection_enabled') ? '1' : '0',
            'security.block_bad_path_enabled' => $request->boolean('security_block_bad_path_enabled') ? '1' : '0',
            'security.block_sql_injection_enabled' => $request->boolean('security_block_sql_injection_enabled') ? '1' : '0',
            'security.block_xss_enabled' => $request->boolean('security_block_xss_enabled') ? '1' : '0',
            'security.block_path_traversal_enabled' => $request->boolean('security_block_path_traversal_enabled') ? '1' : '0',
            'security.block_bad_upload_enabled' => $request->boolean('security_block_bad_upload_enabled') ? '1' : '0',
            'security.rate_limit_enabled' => $request->boolean('security_rate_limit_enabled') ? '1' : '0',
            'security.rate_limit_window_seconds' => (string) ($validated['security_rate_limit_window_seconds'] ?? $this->systemSettings->securityRateLimitWindowSeconds()),
            'security.rate_limit_max_requests' => (string) ($validated['security_rate_limit_max_requests'] ?? $this->systemSettings->securityRateLimitMaxRequests()),
            'security.rate_limit_sensitive_max_requests' => (string) ($validated['security_rate_limit_sensitive_max_requests'] ?? $this->systemSettings->securityRateLimitSensitiveMaxRequests()),
            'security.event_retention_limit' => (string) ($validated['security_event_retention_limit'] ?? $this->systemSettings->securityEventRetentionLimit()),
            'security.stats_retention_days' => (string) ($validated['security_stats_retention_days'] ?? $this->systemSettings->securityStatsRetentionDays()),
            'mail.enabled' => $request->boolean('mail_enabled') ? '1' : '0',
            'mail.driver' => strtolower(trim((string) ($validated['mail_driver'] ?? 'log'))),
            'mail.host' => $this->sanitizeSingleLine((string) ($validated['mail_host'] ?? ''), 255),
            'mail.port' => (string) ($validated['mail_port'] ?? $this->systemSettings->mailPort()),
            'mail.username' => $this->sanitizeSingleLine((string) ($validated['mail_username'] ?? ''), 255),
            'mail.password_encrypted' => $mailPasswordEncrypted,
            'mail.encryption' => strtolower(trim((string) ($validated['mail_encryption'] ?? 'ssl'))),
            'mail.from_address' => $this->sanitizeSingleLine((string) ($validated['mail_from_address'] ?? ''), 100),
            'mail.from_name' => $this->sanitizeSingleLine((string) ($validated['mail_from_name'] ?? ''), 100),
            'mail.reply_to_address' => $this->sanitizeSingleLine((string) ($validated['mail_reply_to_address'] ?? ''), 100),
            'mail.timeout_seconds' => (string) ($validated['mail_timeout_seconds'] ?? $this->systemSettings->mailTimeoutSeconds()),
            'mail.rate_limit_enabled' => $request->boolean('mail_rate_limit_enabled') ? '1' : '0',
            'mail.rate_limit_window_seconds' => (string) ($validated['mail_rate_limit_window_seconds'] ?? $this->systemSettings->mailRateLimitWindowSeconds()),
            'mail.rate_limit_global_max' => (string) ($validated['mail_rate_limit_global_max'] ?? $this->systemSettings->mailRateLimitGlobalMax()),
            'mail.rate_limit_site_max' => (string) ($validated['mail_rate_limit_site_max'] ?? $this->systemSettings->mailRateLimitSiteMax()),
            'mail.rate_limit_scene_max' => (string) ($validated['mail_rate_limit_scene_max'] ?? $this->systemSettings->mailRateLimitSceneMax()),
            'mail.rate_limit_recipient_window_seconds' => (string) ($validated['mail_rate_limit_recipient_window_seconds'] ?? $this->systemSettings->mailRateLimitRecipientWindowSeconds()),
            'mail.rate_limit_recipient_max' => (string) ($validated['mail_rate_limit_recipient_max'] ?? $this->systemSettings->mailRateLimitRecipientMax()),
        ];

        if ($request->boolean('admin_logo_clear')) {
            $this->deletePublicBrandAssets('logo');
            $settings['admin.logo'] = '';
        } elseif ($request->hasFile('admin_logo_file')) {
            $settings['admin.logo'] = $this->storePublicBrandAsset($request->file('admin_logo_file'), 'logo');
        }

        if ($request->boolean('admin_favicon_clear')) {
            $this->deletePublicBrandAssets('Favicon');
            $settings['admin.favicon'] = '';
        } elseif ($request->hasFile('admin_favicon_file')) {
            $settings['admin.favicon'] = $this->storePublicBrandAsset($request->file('admin_favicon_file'), 'Favicon');
        }

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

        $this->logOperation(
            'platform',
            'system_setting',
            'update',
            null,
            $userId,
            'system_setting',
            null,
            [
                'system_name' => $settings['system.name'],
                'system_version' => $settings['system.version'],
                'admin_enabled' => $settings['admin.enabled'],
            ],
            $request,
        );

        $activeTab = $this->normalizeTab((string) $request->input('current_tab', 'basic'));

        return redirect()
            ->route('admin.platform.settings.index', ['tab' => $activeTab])
            ->with('status', '系统设置已更新。');
    }

    public function sendTestMail(Request $request): RedirectResponse
    {
        $this->authorizePlatform($request, 'system.setting.manage');

        $validated = Validator::make($request->all(), [
            'mail_test_to' => ['required', 'email:filter', 'max:100'],
        ], [
            'mail_test_to.required' => '请填写测试收件邮箱。',
            'mail_test_to.email' => '测试收件邮箱格式不正确，请重新填写。',
        ])->validate();

        try {
            $driver = $this->platformMailSettings->sendTestMail($validated['mail_test_to']);
        } catch (\Throwable $exception) {
            return redirect()
                ->route('admin.platform.settings.index', ['tab' => 'mail'])
                ->withErrors([
                    'mail_test_to' => $exception->getMessage(),
                ]);
        }

        $this->logOperation(
            'platform',
            'system_setting',
            'mail_test',
            null,
            (int) $request->user()->id,
            'system_setting',
            null,
            [
                'driver' => $driver,
                'to' => $validated['mail_test_to'],
            ],
            $request,
        );

        $message = $driver === 'log'
            ? '测试邮件已写入日志通道，当前未执行真实投递。'
            : '测试邮件已发送，请检查收件箱。';

        return redirect()
            ->route('admin.platform.settings.index', ['tab' => 'mail'])
            ->with('status', $message);
    }

    protected function normalizeTab(string $tab): string
    {
        return in_array($tab, ['basic', 'upload', 'security', 'access', 'mail'], true) ? $tab : 'basic';
    }

    protected function storePublicBrandAsset(UploadedFile $file, string $prefix): string
    {
        $extension = $this->normalizeBrandExtension($file, $prefix);
        $this->deletePublicBrandAssets($prefix);

        $filename = sprintf('%s_x.%s', $prefix, $extension);
        $file->move(public_path(), $filename);

        return '/' . $filename;
    }

    protected function deletePublicBrandAssets(string $prefix): void
    {
        $publicPath = public_path();

        foreach (glob($publicPath . DIRECTORY_SEPARATOR . $prefix . '_*.*') ?: [] as $existingFile) {
            if (is_file($existingFile)) {
                @unlink($existingFile);
            }
        }
    }

    protected function normalizeExtensions(string $value): string
    {
        return collect(preg_split('/\s*,\s*/', strtolower($value), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => preg_match('/^[a-z0-9]+$/', $item) === 1)
            ->unique()
            ->values()
            ->implode(',');
    }

    protected function sanitizeSingleLine(string $value, int $maxLength): string
    {
        $sanitized = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $sanitized = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]+/u', '', $sanitized) ?? '';
        $sanitized = preg_replace('/\s+/u', ' ', $sanitized) ?? '';
        $sanitized = trim($sanitized);

        return Str::limit($sanitized, $maxLength, '');
    }

    protected function sanitizeTextarea(string $value, int $maxLength): string
    {
        $sanitized = str_replace(["\r\n", "\r"], "\n", $value);
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $sanitized) ?? '';
        $sanitized = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]+/u', '', $sanitized) ?? '';
        $sanitized = preg_replace("/\n{3,}/u", "\n\n", $sanitized) ?? '';
        $sanitized = trim($sanitized);

        return Str::limit($sanitized, $maxLength, '');
    }

    protected function normalizeBrandExtension(UploadedFile $file, string $prefix): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        if ($prefix === 'Favicon') {
            return $extension === 'png' ? 'png' : 'ico';
        }

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
            ? $extension
            : 'png';
    }
}
