<?php

namespace App\Modules\Payroll\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class PayrollSettings
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $siteCache = [];

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'enabled' => true,
            'registration_enabled' => true,
            'wechat_app_id' => '',
            'wechat_app_secret' => '',
            'registration_disabled_message' => '当前已禁止自动注册，请联系管理员。',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forSite(int $siteId): array
    {
        if (isset($this->siteCache[$siteId])) {
            return $this->siteCache[$siteId];
        }

        $defaults = $this->defaults();
        $settings = DB::table('site_settings')
            ->where('site_id', $siteId)
            ->whereIn('setting_key', [
                'module.payroll.enabled',
                'module.payroll.registration_enabled',
                'module.payroll.wechat_app_id',
                'module.payroll.wechat_app_secret',
                'module.payroll.registration_disabled_message',
            ])
            ->pluck('setting_value', 'setting_key');

        return $this->siteCache[$siteId] = [
            'enabled' => $this->booleanValue($settings->get('module.payroll.enabled'), $defaults['enabled']),
            'registration_enabled' => $this->booleanValue($settings->get('module.payroll.registration_enabled'), $defaults['registration_enabled']),
            'wechat_app_id' => $this->stringValue($settings->get('module.payroll.wechat_app_id'), $defaults['wechat_app_id']),
            'wechat_app_secret' => $this->decryptValue($settings->get('module.payroll.wechat_app_secret'), $defaults['wechat_app_secret']),
            'registration_disabled_message' => $this->stringValue(
                $settings->get('module.payroll.registration_disabled_message'),
                $defaults['registration_disabled_message']
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function saveForSite(int $siteId, array $values, int $userId): void
    {
        $payloads = [
            'module.payroll.enabled' => ! empty($values['enabled']) ? '1' : '0',
            'module.payroll.registration_enabled' => ! empty($values['registration_enabled']) ? '1' : '0',
            'module.payroll.wechat_app_id' => $this->stringValue($values['wechat_app_id'] ?? null, ''),
            'module.payroll.wechat_app_secret' => $this->encryptValue($values['wechat_app_secret'] ?? null),
            'module.payroll.registration_disabled_message' => $this->stringValue(
                $values['registration_disabled_message'] ?? null,
                $this->defaults()['registration_disabled_message']
            ),
        ];

        foreach ($payloads as $key => $value) {
            DB::table('site_settings')->updateOrInsert(
                ['site_id' => $siteId, 'setting_key' => $key],
                [
                    'setting_value' => $value,
                    'autoload' => 1,
                    'updated_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        unset($this->siteCache[$siteId]);
    }

    protected function booleanValue(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }

    protected function stringValue(mixed $value, string $default): string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : $default;
    }

    protected function encryptValue(mixed $value): string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? '' : Crypt::encryptString($normalized);
    }

    protected function decryptValue(mixed $value, string $default): string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($normalized);
        } catch (DecryptException) {
            return $default;
        }
    }
}
