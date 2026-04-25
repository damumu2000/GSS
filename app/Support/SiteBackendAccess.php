<?php

namespace App\Support;

class SiteBackendAccess
{
    public function __construct(
        protected SiteExpiration $siteExpiration,
    ) {
    }

    /**
     * @return array{allowed: bool, reason: string, message: string}
     */
    public function status(object $site): array
    {
        if ((int) ($site->status ?? 1) !== 1) {
            return [
                'allowed' => false,
                'reason' => 'closed',
                'message' => '当前站点已被平台关闭，暂无法登录后台。',
            ];
        }

        $expiration = $this->siteExpiration->status($site);
        if ($expiration['admin_blocked'] ?? false) {
            return [
                'allowed' => false,
                'reason' => 'expired',
                'message' => '你所属的站点已经过期多日，目前已限制后台功能，请联系客服尽快续费。',
            ];
        }

        return [
            'allowed' => true,
            'reason' => 'normal',
            'message' => '',
        ];
    }
}
