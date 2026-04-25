<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class SiteExpiration
{
    public const ADMIN_BLOCK_AFTER_DAYS = 7;

    /**
     * @return array{
     *   is_expired: bool,
     *   expired_days: int,
     *   frontend_blocked: bool,
     *   admin_blocked: bool,
     *   level: string,
     *   label: string,
     *   hint: string,
     *   dashboard_title: string,
     *   dashboard_note: string
     * }
     */
    public function status(object $site): array
    {
        $expiresAt = $this->expiresAt($site);

        if (! $expiresAt) {
            return $this->normal('长期有效', '服务状态正常');
        }

        $today = Carbon::now('Asia/Shanghai')->startOfDay();
        $expiresDay = $expiresAt->copy()->startOfDay();
        $diffDays = $today->diffInDays($expiresDay, false);

        if ($diffDays >= 0) {
            $hint = $diffDays === 0 ? '今日到期' : '剩余 '.$diffDays.' 天';

            return $this->normal($hint, '服务状态正常');
        }

        $expiredDays = abs((int) $diffDays);
        $adminBlocked = $expiredDays >= self::ADMIN_BLOCK_AFTER_DAYS;
        $limitLabel = $adminBlocked ? '限制后台' : '限制前台';

        return [
            'is_expired' => true,
            'expired_days' => $expiredDays,
            'frontend_blocked' => true,
            'admin_blocked' => $adminBlocked,
            'level' => $adminBlocked ? 'danger' : 'warning',
            'label' => '已过期 '.$expiredDays.' 天',
            'hint' => $limitLabel,
            'dashboard_title' => $adminBlocked
                ? '你所属的站点已经过期多日'
                : '站点已过期',
            'dashboard_note' => $adminBlocked
                ? '目前已限制后台功能，请联系客服尽快续费，未续费站点将会进行数据清理。'
                : '目前已限制前台访问，7天后将关闭后台功能。',
        ];
    }

    protected function expiresAt(object $site): ?Carbon
    {
        $value = $site->expires_at ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value, 'Asia/Shanghai')->endOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, bool|int|string>
     */
    protected function normal(string $label, string $hint): array
    {
        return [
            'is_expired' => false,
            'expired_days' => 0,
            'frontend_blocked' => false,
            'admin_blocked' => false,
            'level' => 'normal',
            'label' => $label,
            'hint' => $hint,
            'dashboard_title' => $label,
            'dashboard_note' => $hint,
        ];
    }
}
