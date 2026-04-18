<?php

namespace App\Modules\WechatOfficial\Support;

use Illuminate\Support\Facades\DB;

class WechatOfficialLogger
{
    /**
     * @param array<string, mixed> $requestPayload
     * @param array<string, mixed> $responsePayload
     */
    public function record(
        int $siteId,
        string $action,
        string $status,
        string $message,
        array $requestPayload = [],
        array $responsePayload = [],
        int $userId = 0,
        string $channel = 'api'
    ): void {
        DB::table('module_wechat_official_logs')->insert([
            'site_id' => $siteId,
            'channel' => $channel,
            'action' => trim($action),
            'status' => trim($status) !== '' ? trim($status) : 'success',
            'request_payload' => $requestPayload !== [] ? json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'response_payload' => $responsePayload !== [] ? json_encode($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'message' => trim($message) !== '' ? trim($message) : null,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
