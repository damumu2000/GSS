<?php

namespace App\Modules\WechatOfficial\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WechatOfficialSettings
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
            'enabled' => false,
            'official_name' => '',
            'app_id' => '',
            'app_secret' => '',
            'token' => '',
            'encoding_aes_key' => '',
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
        $row = DB::table('module_wechat_official_accounts')
            ->where('site_id', $siteId)
            ->first();

        return $this->siteCache[$siteId] = [
            'enabled' => $row ? (bool) ($row->enabled ?? $defaults['enabled']) : $defaults['enabled'],
            'official_name' => $this->stringValue($row->official_name ?? null, $defaults['official_name']),
            'app_id' => $this->stringValue($row->app_id ?? null, $defaults['app_id']),
            'app_secret' => $this->decryptValue($row->app_secret ?? null, $defaults['app_secret']),
            'token' => $this->decryptValue($row->token ?? null, $defaults['token']),
            'encoding_aes_key' => $this->decryptValue($row->encoding_aes_key ?? null, $defaults['encoding_aes_key']),
        ];
    }

    /**
     * @param array<string, mixed> $values
     */
    public function saveForSite(int $siteId, array $values, int $userId): void
    {
        $defaults = $this->defaults();
        $existing = DB::table('module_wechat_official_accounts')
            ->where('site_id', $siteId)
            ->first();
        $now = now();

        DB::table('module_wechat_official_accounts')->updateOrInsert(
            ['site_id' => $siteId],
            [
                'official_name' => $this->stringValue($values['official_name'] ?? null, $defaults['official_name']),
                'app_id' => $this->stringValue($values['app_id'] ?? null, $defaults['app_id']),
                'app_secret' => $this->encryptValue($values['app_secret'] ?? null),
                'token' => $this->encryptValue($values['token'] ?? null),
                'encoding_aes_key' => $this->encryptValue($values['encoding_aes_key'] ?? null),
                'enabled' => ! empty($values['enabled']),
                'updated_by' => $userId,
                'created_by' => (int) ($existing->created_by ?? $userId),
                'created_at' => $existing->created_at ?? $now,
                'updated_at' => $now,
            ],
        );

        unset($this->siteCache[$siteId]);
        Cache::forget(WechatOfficialApi::accessTokenCacheKey($siteId));
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
