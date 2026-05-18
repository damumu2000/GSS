<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemSettings
{
    /**
     * @var array<string, mixed>
     */
    protected array $defaults;

    /**
     * @var array<string, string|null>|null
     */
    protected ?array $cached = null;

    public function __construct()
    {
        $this->defaults = config('cms.system_setting_defaults', []);
    }

    public function hasTable(): bool
    {
        return Schema::hasTable('system_settings');
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        if (! $this->hasTable()) {
            return $this->cached = [];
        }

        return $this->cached = DB::table('system_settings')
            ->pluck('setting_value', 'setting_key')
            ->map(fn ($value) => $value === null ? null : (string) $value)
            ->all();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        if (array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== '') {
            return $settings[$key];
        }

        if ($default !== null) {
            return $default;
        }

        return $this->defaults[$key] ?? null;
    }

    public function string(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default ? '1' : '0');

        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }

    public function adminEnabled(): bool
    {
        return $this->bool('admin.enabled', true);
    }

    public function adminDisabledMessage(): string
    {
        return $this->string('admin.disabled_message', '后台暂时关闭，请联系系统管理员。');
    }

    /**
     * @return array<int, string>
     */
    public function attachmentAllowedExtensions(): array
    {
        $raw = strtolower($this->string('attachment.allowed_extensions', 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar'));

        return collect(preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function imageExtensions(): array
    {
        $allowed = $this->attachmentAllowedExtensions();
        $images = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        $result = array_values(array_intersect($images, $allowed));

        return $result !== [] ? $result : $images;
    }

    public function attachmentMaxSizeMb(): int
    {
        return max(1, $this->int('attachment.max_size_mb', 10));
    }

    public function attachmentImageMaxSizeMb(): int
    {
        return max(1, $this->int('attachment.image_max_size_mb', 5));
    }

    public function attachmentImageMaxWidth(): int
    {
        return max(1, $this->int('attachment.image_max_width', 4096));
    }

    public function attachmentImageMaxHeight(): int
    {
        return max(1, $this->int('attachment.image_max_height', 4096));
    }

    public function attachmentImageAutoResizeEnabled(): bool
    {
        return $this->bool('attachment.image_auto_resize', false);
    }

    public function attachmentImageAutoCompressEnabled(): bool
    {
        return $this->bool('attachment.image_auto_compress', false);
    }

    public function attachmentImageQuality(): int
    {
        return min(100, max(1, $this->int('attachment.image_quality', 82)));
    }

    public function siteProtectionEnabled(): bool
    {
        return $this->bool('security.site_protection_enabled', true);
    }

    public function securityBlockBadPathEnabled(): bool
    {
        return $this->bool('security.block_bad_path_enabled', true);
    }

    public function securityBlockSqlInjectionEnabled(): bool
    {
        return $this->bool('security.block_sql_injection_enabled', true);
    }

    public function securityBlockXssEnabled(): bool
    {
        return $this->bool('security.block_xss_enabled', true);
    }

    public function securityBlockPathTraversalEnabled(): bool
    {
        return $this->bool('security.block_path_traversal_enabled', true);
    }

    public function securityBlockBadUploadEnabled(): bool
    {
        return $this->bool('security.block_bad_upload_enabled', true);
    }

    public function securityRateLimitEnabled(): bool
    {
        return $this->bool('security.rate_limit_enabled', true);
    }

    public function securityRateLimitWindowSeconds(): int
    {
        return max(1, $this->int('security.rate_limit_window_seconds', 10));
    }

    public function securityRateLimitMaxRequests(): int
    {
        return max(1, $this->int('security.rate_limit_max_requests', 30));
    }

    public function securityRateLimitSensitiveMaxRequests(): int
    {
        return max(1, $this->int('security.rate_limit_sensitive_max_requests', 10));
    }

    public function securityRateLimitBlockSeconds(): int
    {
        return max(0, min(86400, $this->int('security.rate_limit_block_seconds', 60)));
    }

    public function securityEventRetentionLimit(): int
    {
        return max(20, $this->int('security.event_retention_limit', 200));
    }

    public function securityStatsRetentionDays(): int
    {
        return max(7, $this->int('security.stats_retention_days', 180));
    }

    public function mailEnabled(): bool
    {
        return $this->bool('mail.enabled', false);
    }

    public function mailDriver(): string
    {
        $driver = strtolower(trim($this->string('mail.driver', 'log')));

        return in_array($driver, ['smtp', 'log'], true) ? $driver : 'log';
    }

    public function mailHost(): string
    {
        return trim($this->string('mail.host', ''));
    }

    public function mailPort(): int
    {
        return max(1, min(65535, $this->int('mail.port', 465)));
    }

    public function mailUsername(): string
    {
        return trim($this->string('mail.username', ''));
    }

    public function mailPasswordEncrypted(): string
    {
        return trim($this->string('mail.password_encrypted', ''));
    }

    public function mailPasswordConfigured(): bool
    {
        return $this->mailPasswordEncrypted() !== '';
    }

    public function mailEncryption(): string
    {
        $encryption = strtolower(trim($this->string('mail.encryption', 'ssl')));

        return in_array($encryption, ['', 'ssl', 'tls'], true) ? $encryption : 'ssl';
    }

    public function mailFromAddress(): string
    {
        return trim($this->string('mail.from_address', ''));
    }

    public function mailFromName(): string
    {
        return trim($this->string('mail.from_name', (string) config('app.name')));
    }

    public function mailReplyToAddress(): string
    {
        return trim($this->string('mail.reply_to_address', ''));
    }

    public function mailTimeoutSeconds(): int
    {
        return max(1, min(60, $this->int('mail.timeout_seconds', 10)));
    }

    public function mailRateLimitEnabled(): bool
    {
        return $this->bool('mail.rate_limit_enabled', true);
    }

    public function mailRateLimitWindowSeconds(): int
    {
        return max(10, min(3600, $this->int('mail.rate_limit_window_seconds', 60)));
    }

    public function mailRateLimitGlobalMax(): int
    {
        return max(1, min(10000, $this->int('mail.rate_limit_global_max', 20)));
    }

    public function mailRateLimitSiteMax(): int
    {
        return max(1, min(10000, $this->int('mail.rate_limit_site_max', 10)));
    }

    public function mailRateLimitSceneMax(): int
    {
        return max(1, min(10000, $this->int('mail.rate_limit_scene_max', 5)));
    }

    public function mailRateLimitRecipientWindowSeconds(): int
    {
        return max(60, min(86400, $this->int('mail.rate_limit_recipient_window_seconds', 600)));
    }

    public function mailRateLimitRecipientMax(): int
    {
        return max(1, min(10000, $this->int('mail.rate_limit_recipient_max', 5)));
    }

    public function guestbookLimitIpWindowSeconds(): int
    {
        return max(10, min(3600, $this->int('guestbook.limit_ip_window_seconds', 60)));
    }

    public function guestbookLimitIpMaxAttempts(): int
    {
        return max(1, min(100, $this->int('guestbook.limit_ip_max_attempts', 3)));
    }

    public function guestbookLimitIpBlockSeconds(): int
    {
        return max(10, min(86400, $this->int('guestbook.limit_ip_block_seconds', 60)));
    }

    public function guestbookLimitPhoneWindowSeconds(): int
    {
        return max(60, min(86400, $this->int('guestbook.limit_phone_window_seconds', 600)));
    }

    public function guestbookLimitPhoneMaxAttempts(): int
    {
        return max(1, min(50, $this->int('guestbook.limit_phone_max_attempts', 2)));
    }

    public function guestbookLimitPhoneBlockSeconds(): int
    {
        return max(10, min(86400, $this->int('guestbook.limit_phone_block_seconds', 600)));
    }

    public function guestbookLimitCaptchaVerifyWindowSeconds(): int
    {
        return max(10, min(3600, $this->int('guestbook.limit_captcha_verify_window_seconds', 30)));
    }

    public function guestbookLimitCaptchaVerifyMaxAttempts(): int
    {
        return max(1, min(200, $this->int('guestbook.limit_captcha_verify_max_attempts', 10)));
    }

    public function guestbookLimitCaptchaVerifyBlockSeconds(): int
    {
        return max(10, min(86400, $this->int('guestbook.limit_captcha_verify_block_seconds', 30)));
    }

    public function loginServiceAgreementContent(): string
    {
        return trim($this->string('login.service_agreement_content', "欢迎使用本系统。\n登录前请确认你已阅读并同意本平台服务协议，并承诺合法合规使用系统功能。\n如不同意服务协议内容，请不要继续登录。"));
    }

    /**
     * @return array<string, mixed>
     */
    public function formDefaults(): array
    {
        return [
            'system_name' => $this->string('system.name', (string) config('app.name')),
            'system_version' => $this->string('system.version', '1.0.0'),
            'admin_logo' => $this->string('admin.logo', '/logo.jpg'),
            'admin_favicon' => $this->string('admin.favicon', '/Favicon.ico'),
            'attachment_allowed_extensions' => implode(',', $this->attachmentAllowedExtensions()),
            'attachment_max_size_mb' => $this->attachmentMaxSizeMb(),
            'attachment_image_max_size_mb' => $this->attachmentImageMaxSizeMb(),
            'attachment_image_max_width' => $this->attachmentImageMaxWidth(),
            'attachment_image_max_height' => $this->attachmentImageMaxHeight(),
            'attachment_image_auto_resize' => $this->attachmentImageAutoResizeEnabled(),
            'attachment_image_auto_compress' => $this->attachmentImageAutoCompressEnabled(),
            'attachment_image_quality' => $this->attachmentImageQuality(),
            'admin_enabled' => $this->adminEnabled(),
            'admin_disabled_message' => $this->adminDisabledMessage(),
            'security_site_protection_enabled' => $this->siteProtectionEnabled(),
            'security_block_bad_path_enabled' => $this->securityBlockBadPathEnabled(),
            'security_block_sql_injection_enabled' => $this->securityBlockSqlInjectionEnabled(),
            'security_block_xss_enabled' => $this->securityBlockXssEnabled(),
            'security_block_path_traversal_enabled' => $this->securityBlockPathTraversalEnabled(),
            'security_block_bad_upload_enabled' => $this->securityBlockBadUploadEnabled(),
            'security_rate_limit_enabled' => $this->securityRateLimitEnabled(),
            'security_rate_limit_window_seconds' => $this->securityRateLimitWindowSeconds(),
            'security_rate_limit_max_requests' => $this->securityRateLimitMaxRequests(),
            'security_rate_limit_sensitive_max_requests' => $this->securityRateLimitSensitiveMaxRequests(),
            'security_rate_limit_block_seconds' => $this->securityRateLimitBlockSeconds(),
            'security_event_retention_limit' => $this->securityEventRetentionLimit(),
            'security_stats_retention_days' => $this->securityStatsRetentionDays(),
            'mail_enabled' => $this->mailEnabled(),
            'mail_driver' => $this->mailDriver(),
            'mail_host' => $this->mailHost(),
            'mail_port' => $this->mailPort(),
            'mail_username' => $this->mailUsername(),
            'mail_password_configured' => $this->mailPasswordConfigured(),
            'mail_encryption' => $this->mailEncryption(),
            'mail_from_address' => $this->mailFromAddress(),
            'mail_from_name' => $this->mailFromName(),
            'mail_reply_to_address' => $this->mailReplyToAddress(),
            'mail_timeout_seconds' => $this->mailTimeoutSeconds(),
            'mail_rate_limit_enabled' => $this->mailRateLimitEnabled(),
            'mail_rate_limit_window_seconds' => $this->mailRateLimitWindowSeconds(),
            'mail_rate_limit_global_max' => $this->mailRateLimitGlobalMax(),
            'mail_rate_limit_site_max' => $this->mailRateLimitSiteMax(),
            'mail_rate_limit_scene_max' => $this->mailRateLimitSceneMax(),
            'mail_rate_limit_recipient_window_seconds' => $this->mailRateLimitRecipientWindowSeconds(),
            'mail_rate_limit_recipient_max' => $this->mailRateLimitRecipientMax(),
            'login_service_agreement_content' => $this->loginServiceAgreementContent(),
        ];
    }
}
