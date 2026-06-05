<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PlatformSecuritySettings
{
    public function __construct(
        protected SystemSettings $systemSettings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function formDefaults(): array
    {
        return [
            'security_site_protection_enabled' => $this->systemSettings->siteProtectionEnabled(),
            'security_block_bad_path_enabled' => $this->systemSettings->securityBlockBadPathEnabled(),
            'security_block_sql_injection_enabled' => $this->systemSettings->securityBlockSqlInjectionEnabled(),
            'security_block_xss_enabled' => $this->systemSettings->securityBlockXssEnabled(),
            'security_block_path_traversal_enabled' => $this->systemSettings->securityBlockPathTraversalEnabled(),
            'security_block_bad_upload_enabled' => $this->systemSettings->securityBlockBadUploadEnabled(),
            'security_block_bad_client_enabled' => $this->systemSettings->securityBlockBadClientEnabled(),
            'security_block_bad_method_enabled' => $this->systemSettings->securityBlockBadMethodEnabled(),
            'security_block_bad_payload_enabled' => $this->systemSettings->securityBlockBadPayloadEnabled(),
            'security_payload_max_fields' => $this->systemSettings->securityPayloadMaxFields(),
            'security_payload_max_value_length' => $this->systemSettings->securityPayloadMaxValueLength(),
            'security_rate_limit_enabled' => $this->systemSettings->securityRateLimitEnabled(),
            'security_rate_limit_window_seconds' => $this->systemSettings->securityRateLimitWindowSeconds(),
            'security_rate_limit_max_requests' => $this->systemSettings->securityRateLimitMaxRequests(),
            'security_rate_limit_sensitive_max_requests' => $this->systemSettings->securityRateLimitSensitiveMaxRequests(),
            'security_rate_limit_block_seconds' => $this->systemSettings->securityRateLimitBlockSeconds(),
            'security_scan_probe_enabled' => $this->systemSettings->securityScanProbeEnabled(),
            'security_scan_probe_window_seconds' => $this->systemSettings->securityScanProbeWindowSeconds(),
            'security_scan_probe_threshold' => $this->systemSettings->securityScanProbeThreshold(),
            'security_malicious_auto_block_enabled' => $this->systemSettings->securityMaliciousAutoBlockEnabled(),
            'security_malicious_auto_block_window_seconds' => $this->systemSettings->securityMaliciousAutoBlockWindowSeconds(),
            'security_malicious_auto_block_threshold' => $this->systemSettings->securityMaliciousAutoBlockThreshold(),
            'security_malicious_auto_block_seconds' => $this->systemSettings->securityMaliciousAutoBlockSeconds(),
            'security_ip_allowlist' => implode("\n", $this->systemSettings->securityIpAllowlist()),
            'security_ip_blocklist' => implode("\n", $this->systemSettings->securityIpBlocklist()),
            'security_event_retention_limit' => $this->systemSettings->securityEventRetentionLimit(),
            'security_stats_retention_days' => $this->systemSettings->securityStatsRetentionDays(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validateAndStore(Request $request, int $userId): array
    {
        $validator = Validator::make($request->all(), [
            'security_site_protection_enabled' => ['nullable', 'boolean'],
            'security_block_bad_path_enabled' => ['nullable', 'boolean'],
            'security_block_sql_injection_enabled' => ['nullable', 'boolean'],
            'security_block_xss_enabled' => ['nullable', 'boolean'],
            'security_block_path_traversal_enabled' => ['nullable', 'boolean'],
            'security_block_bad_upload_enabled' => ['nullable', 'boolean'],
            'security_block_bad_client_enabled' => ['nullable', 'boolean'],
            'security_block_bad_method_enabled' => ['nullable', 'boolean'],
            'security_block_bad_payload_enabled' => ['nullable', 'boolean'],
            'security_payload_max_fields' => ['required', 'integer', 'min:10', 'max:1000'],
            'security_payload_max_value_length' => ['required', 'integer', 'min:256', 'max:20000'],
            'security_rate_limit_enabled' => ['nullable', 'boolean'],
            'security_rate_limit_window_seconds' => ['required', 'integer', 'min:1', 'max:300'],
            'security_rate_limit_max_requests' => ['required', 'integer', 'min:1', 'max:1000'],
            'security_rate_limit_sensitive_max_requests' => ['required', 'integer', 'min:1', 'max:500'],
            'security_rate_limit_block_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'security_scan_probe_enabled' => ['nullable', 'boolean'],
            'security_scan_probe_window_seconds' => ['required', 'integer', 'min:10', 'max:86400'],
            'security_scan_probe_threshold' => ['required', 'integer', 'min:1', 'max:100'],
            'security_malicious_auto_block_enabled' => ['nullable', 'boolean'],
            'security_malicious_auto_block_window_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
            'security_malicious_auto_block_threshold' => ['required', 'integer', 'min:3', 'max:100'],
            'security_malicious_auto_block_seconds' => ['required', 'integer', 'min:60', 'max:604800'],
            'security_ip_allowlist' => ['nullable', 'string', 'max:5000'],
            'security_ip_blocklist' => ['nullable', 'string', 'max:5000'],
            'security_event_retention_limit' => ['required', 'integer', 'min:20', 'max:1000'],
            'security_stats_retention_days' => ['required', 'integer', 'min:7', 'max:3650'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $maxRequests = (int) $request->input('security_rate_limit_max_requests', $this->systemSettings->securityRateLimitMaxRequests());
            $sensitiveMaxRequests = (int) $request->input('security_rate_limit_sensitive_max_requests', $this->systemSettings->securityRateLimitSensitiveMaxRequests());

            if ($sensitiveMaxRequests > $maxRequests) {
                $validator->errors()->add('security_rate_limit_sensitive_max_requests', '敏感页面阈值不能高于普通页面阈值。');
            }

            foreach ([
                'security_ip_allowlist' => 'IP 白名单',
                'security_ip_blocklist' => 'IP 黑名单',
            ] as $field => $label) {
                foreach ($this->normalizeIpList((string) $request->input($field, '')) as $item) {
                    if (! $this->isValidIpPattern($item)) {
                        $validator->errors()->add($field, $label.'仅支持单个 IP 或 IPv4 CIDR 网段。');
                        break;
                    }
                }
            }
        });

        $validated = $validator->validate();
        $settings = [
            'security.site_protection_enabled' => $request->boolean('security_site_protection_enabled') ? '1' : '0',
            'security.block_bad_path_enabled' => $request->boolean('security_block_bad_path_enabled') ? '1' : '0',
            'security.block_sql_injection_enabled' => $request->boolean('security_block_sql_injection_enabled') ? '1' : '0',
            'security.block_xss_enabled' => $request->boolean('security_block_xss_enabled') ? '1' : '0',
            'security.block_path_traversal_enabled' => $request->boolean('security_block_path_traversal_enabled') ? '1' : '0',
            'security.block_bad_upload_enabled' => $request->boolean('security_block_bad_upload_enabled') ? '1' : '0',
            'security.block_bad_client_enabled' => $request->boolean('security_block_bad_client_enabled') ? '1' : '0',
            'security.block_bad_method_enabled' => $request->boolean('security_block_bad_method_enabled') ? '1' : '0',
            'security.block_bad_payload_enabled' => $request->boolean('security_block_bad_payload_enabled') ? '1' : '0',
            'security.payload_max_fields' => (string) $validated['security_payload_max_fields'],
            'security.payload_max_value_length' => (string) $validated['security_payload_max_value_length'],
            'security.rate_limit_enabled' => $request->boolean('security_rate_limit_enabled') ? '1' : '0',
            'security.rate_limit_window_seconds' => (string) $validated['security_rate_limit_window_seconds'],
            'security.rate_limit_max_requests' => (string) $validated['security_rate_limit_max_requests'],
            'security.rate_limit_sensitive_max_requests' => (string) $validated['security_rate_limit_sensitive_max_requests'],
            'security.rate_limit_block_seconds' => (string) $validated['security_rate_limit_block_seconds'],
            'security.scan_probe_enabled' => $request->boolean('security_scan_probe_enabled') ? '1' : '0',
            'security.scan_probe_window_seconds' => (string) $validated['security_scan_probe_window_seconds'],
            'security.scan_probe_threshold' => (string) $validated['security_scan_probe_threshold'],
            'security.malicious_auto_block_enabled' => $request->boolean('security_malicious_auto_block_enabled') ? '1' : '0',
            'security.malicious_auto_block_window_seconds' => (string) $validated['security_malicious_auto_block_window_seconds'],
            'security.malicious_auto_block_threshold' => (string) $validated['security_malicious_auto_block_threshold'],
            'security.malicious_auto_block_seconds' => (string) $validated['security_malicious_auto_block_seconds'],
            'security.ip_allowlist' => implode("\n", $this->normalizeIpList((string) ($validated['security_ip_allowlist'] ?? ''))),
            'security.ip_blocklist' => implode("\n", $this->normalizeIpList((string) ($validated['security_ip_blocklist'] ?? ''))),
            'security.event_retention_limit' => (string) $validated['security_event_retention_limit'],
            'security.stats_retention_days' => (string) $validated['security_stats_retention_days'],
        ];

        $now = now();

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

        return $settings;
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeIpList(string $value): array
    {
        return collect(preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    protected function isValidIpPattern(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if (! str_contains($value, '/')) {
            return false;
        }

        [$subnet, $mask] = array_pad(explode('/', $value, 2), 2, null);

        return filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && is_numeric($mask)
            && (int) $mask >= 0
            && (int) $mask <= 32;
    }
}
