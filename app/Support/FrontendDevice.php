<?php

namespace App\Support;

use Illuminate\Http\Request;

class FrontendDevice
{
    public static function mode(Request $request): string
    {
        $forcedDevice = self::forced($request);
        if ($forcedDevice !== null) {
            return $forcedDevice;
        }

        $userAgent = (string) $request->userAgent();
        if ($userAgent === '') {
            return 'pc';
        }

        return preg_match('/Mobile|Android|iPhone|iPod|iPad|Windows Phone|BlackBerry|IEMobile|Opera Mini|Mobi/i', $userAgent) === 1
            ? 'mobile'
            : 'pc';
    }

    public static function forced(Request $request): ?string
    {
        $device = strtolower(trim((string) $request->query('device', '')));

        return match ($device) {
            'mobile', 'm', 'h5', 'wap' => 'mobile',
            'pc', 'desktop' => 'pc',
            default => null,
        };
    }
}
