<?php

namespace App\Modules\Payroll\Support;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayrollWechatAuth
{
    public function authorizeUrl(string $appId, string $callbackUrl, string $state): string
    {
        $query = http_build_query([
            'appid' => $appId,
            'redirect_uri' => $callbackUrl,
            'response_type' => 'code',
            'scope' => 'snsapi_userinfo',
            'state' => $state,
        ]);

        return 'https://open.weixin.qq.com/connect/oauth2/authorize?'.$query.'#wechat_redirect';
    }

    /**
     * @return array{openid: string, unionid: string, nickname: string, avatar: string}
     */
    public function userProfile(string $appId, string $appSecret, string $code): array
    {
        $tokenResponse = Http::retry(2, 200)
            ->get('https://api.weixin.qq.com/sns/oauth2/access_token', [
                'appid' => $appId,
                'secret' => $appSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
            ])
            ->throw()
            ->json();

        if (! is_array($tokenResponse) || empty($tokenResponse['access_token']) || empty($tokenResponse['openid'])) {
            throw new RequestException(Http::response($tokenResponse, 422));
        }

        $userinfo = Http::retry(2, 200)
            ->get('https://api.weixin.qq.com/sns/userinfo', [
                'access_token' => $tokenResponse['access_token'],
                'openid' => $tokenResponse['openid'],
                'lang' => 'zh_CN',
            ])
            ->throw()
            ->json();

        return [
            'openid' => trim((string) ($userinfo['openid'] ?? $tokenResponse['openid'] ?? '')),
            'unionid' => trim((string) ($userinfo['unionid'] ?? '')),
            'nickname' => trim((string) ($userinfo['nickname'] ?? '')),
            'avatar' => trim((string) ($userinfo['headimgurl'] ?? '')),
        ];
    }

    public function generateState(): string
    {
        return Str::random(24);
    }
}
