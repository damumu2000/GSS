<?php

namespace App\Modules\WechatOfficial\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WechatOfficialApi
{
    public function __construct(
        protected WechatOfficialLogger $logger
    ) {
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<int, array<string, mixed>> $buttons
     */
    public function syncMenus(int $siteId, array $settings, array $buttons, int $userId): void
    {
        $accessToken = $this->accessToken($siteId, $settings, $userId);
        $payload = ['button' => $buttons];

        $response = Http::retry(2, 200)
            ->timeout(12)
            ->asJson()
            ->post('https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.urlencode($accessToken), $payload);

        $json = $response->json();
        $errCode = (int) ($json['errcode'] ?? -1);
        $errMessage = trim((string) ($json['errmsg'] ?? ''));

        if (! $response->successful() || $errCode !== 0) {
            $this->logger->record(
                $siteId,
                'menu_sync',
                'failed',
                $errMessage !== '' ? '公众号菜单同步失败：'.$errMessage : '公众号菜单同步失败。',
                $payload,
                is_array($json) ? $json : [],
                $userId
            );

            throw new RuntimeException($errMessage !== '' ? '公众号菜单同步失败：'.$errMessage : '公众号菜单同步失败。');
        }

        DB::table('module_wechat_official_menus')
            ->where('site_id', $siteId)
            ->update([
                'last_synced_at' => now(),
                'updated_at' => now(),
            ]);

        $this->logger->record(
            $siteId,
            'menu_sync',
            'success',
            '公众号菜单已同步到微信。',
            $payload,
            is_array($json) ? $json : [],
            $userId
        );
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array<string, mixed>>
     */
    public function pullMenus(int $siteId, array $settings, int $userId): array
    {
        $accessToken = $this->accessToken($siteId, $settings, $userId);

        $response = Http::retry(2, 200)
            ->timeout(12)
            ->get('https://api.weixin.qq.com/cgi-bin/get_current_selfmenu_info?access_token='.urlencode($accessToken));

        $json = $response->json();
        $errCode = (int) ($json['errcode'] ?? 0);
        $errMessage = trim((string) ($json['errmsg'] ?? ''));

        if (! $response->successful() || $errCode !== 0) {
            $this->logger->record(
                $siteId,
                'menu_pull',
                'failed',
                $errMessage !== '' ? '公众号菜单拉取失败：'.$errMessage : '公众号菜单拉取失败。',
                [],
                is_array($json) ? $json : [],
                $userId
            );

            throw new RuntimeException($errMessage !== '' ? '公众号菜单拉取失败：'.$errMessage : '公众号菜单拉取失败。');
        }

        $buttons = $json['selfmenu_info']['button'] ?? [];

        $this->logger->record(
            $siteId,
            'menu_pull',
            'success',
            '公众号菜单已从微信拉取。',
            [],
            is_array($json) ? $json : [],
            $userId
        );

        return is_array($buttons) ? $buttons : [];
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function checkConnection(int $siteId, array $settings, int $userId): string
    {
        $this->accessToken($siteId, $settings, $userId);

        $message = '公众号配置检测通过，可正常获取 access_token。';

        $this->logger->record(
            $siteId,
            'config_check',
            'success',
            $message,
            [
                'app_id' => trim((string) ($settings['app_id'] ?? '')),
            ],
            [],
            $userId
        );

        return $message;
    }

    public static function accessTokenCacheKey(int $siteId): string
    {
        return 'wechat_official:access_token:'.$siteId;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $article
     */
    public function createDraft(int $siteId, array $settings, array $article, int $userId): string
    {
        $accessToken = $this->accessToken($siteId, $settings, $userId);
        $payload = ['articles' => [$article]];

        $response = Http::retry(2, 200)
            ->timeout(15)
            ->asJson()
            ->post('https://api.weixin.qq.com/cgi-bin/draft/add?access_token='.urlencode($accessToken), $payload);

        $json = $response->json();
        $mediaId = trim((string) ($json['media_id'] ?? ''));
        $errCode = (int) ($json['errcode'] ?? 0);
        $errMessage = trim((string) ($json['errmsg'] ?? ''));

        if (! $response->successful() || $mediaId === '' || $errCode !== 0) {
            $this->logger->record(
                $siteId,
                'article_draft_create',
                'failed',
                $errMessage !== '' ? '公众号草稿生成失败：'.$errMessage : '公众号草稿生成失败。',
                $payload,
                is_array($json) ? $json : [],
                $userId
            );

            throw new RuntimeException($errMessage !== '' ? '公众号草稿生成失败：'.$errMessage : '公众号草稿生成失败。');
        }

        $this->logger->record(
            $siteId,
            'article_draft_create',
            'success',
            '公众号草稿已生成。',
            [
                'title' => $article['title'] ?? '',
                'thumb_media_id' => $article['thumb_media_id'] ?? '',
            ],
            is_array($json) ? $json : [],
            $userId
        );

        return $mediaId;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function publishDraft(int $siteId, array $settings, string $draftMediaId, int $userId): string
    {
        $accessToken = $this->accessToken($siteId, $settings, $userId);
        $payload = ['media_id' => trim($draftMediaId)];

        $response = Http::retry(2, 200)
            ->timeout(15)
            ->asJson()
            ->post('https://api.weixin.qq.com/cgi-bin/freepublish/submit?access_token='.urlencode($accessToken), $payload);

        $json = $response->json();
        $publishId = trim((string) ($json['publish_id'] ?? ''));
        $errCode = (int) ($json['errcode'] ?? 0);
        $errMessage = trim((string) ($json['errmsg'] ?? ''));

        if (! $response->successful() || $publishId === '' || $errCode !== 0) {
            $this->logger->record(
                $siteId,
                'article_publish_submit',
                'failed',
                $errMessage !== '' ? '公众号发布提交失败：'.$errMessage : '公众号发布提交失败。',
                $payload,
                is_array($json) ? $json : [],
                $userId
            );

            throw new RuntimeException($errMessage !== '' ? '公众号发布提交失败：'.$errMessage : '公众号发布提交失败。');
        }

        $this->logger->record(
            $siteId,
            'article_publish_submit',
            'success',
            '公众号文章发布任务已提交。',
            $payload,
            is_array($json) ? $json : [],
            $userId
        );

        return $publishId;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{status: string, article_id: string, message: string}
     */
    public function queryPublishStatus(int $siteId, array $settings, string $publishId, int $userId): array
    {
        $accessToken = $this->accessToken($siteId, $settings, $userId);
        $payload = ['publish_id' => trim($publishId)];

        $response = Http::retry(2, 200)
            ->timeout(15)
            ->asJson()
            ->post('https://api.weixin.qq.com/cgi-bin/freepublish/get?access_token='.urlencode($accessToken), $payload);

        $json = $response->json();
        $errCode = (int) ($json['errcode'] ?? 0);
        $errMessage = trim((string) ($json['errmsg'] ?? ''));

        if (! $response->successful() || $errCode !== 0) {
            $this->logger->record(
                $siteId,
                'article_publish_query',
                'failed',
                $errMessage !== '' ? '公众号发布状态查询失败：'.$errMessage : '公众号发布状态查询失败。',
                $payload,
                is_array($json) ? $json : [],
                $userId
            );

            throw new RuntimeException($errMessage !== '' ? '公众号发布状态查询失败：'.$errMessage : '公众号发布状态查询失败。');
        }

        $statusCode = (int) ($json['publish_status'] ?? -1);
        $normalizedStatus = match ($statusCode) {
            0 => 'published',
            1 => 'publishing',
            2, 3, 4 => 'publish_failed',
            default => 'publishing',
        };
        $articleId = trim((string) ($json['article_id'] ?? ''));
        $message = match ($normalizedStatus) {
            'published' => '公众号文章已发布成功。',
            'publish_failed' => '公众号文章发布失败，请查看接口日志。',
            default => '公众号文章仍在发布处理中。',
        };

        $this->logger->record(
            $siteId,
            'article_publish_query',
            $normalizedStatus === 'publish_failed' ? 'failed' : 'success',
            $message,
            $payload,
            is_array($json) ? $json : [],
            $userId
        );

        return [
            'status' => $normalizedStatus,
            'article_id' => $articleId,
            'message' => $message,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{media_id: string, url: string}
     */
    public function syncImageMaterial(int $siteId, array $settings, string $absolutePath, string $fileName, int $userId): array
    {
        $accessToken = $this->accessToken($siteId, $settings, $userId);
        $handle = fopen($absolutePath, 'r');

        if ($handle === false) {
            throw new RuntimeException('素材文件读取失败，无法同步到微信。');
        }

        try {
            $response = Http::retry(2, 200)
                ->timeout(20)
                ->attach('media', $handle, $fileName)
                ->post('https://api.weixin.qq.com/cgi-bin/material/add_material?access_token='.urlencode($accessToken).'&type=image');
        } finally {
            fclose($handle);
        }

        $json = $response->json();
        $mediaId = trim((string) ($json['media_id'] ?? ''));
        $url = trim((string) ($json['url'] ?? ''));
        $errCode = (int) ($json['errcode'] ?? 0);
        $errMessage = trim((string) ($json['errmsg'] ?? ''));

        if (! $response->successful() || $mediaId === '' || $errCode !== 0) {
            $this->logger->record(
                $siteId,
                'material_sync',
                'failed',
                $errMessage !== '' ? '公众号图片素材同步失败：'.$errMessage : '公众号图片素材同步失败。',
                ['file_name' => $fileName],
                is_array($json) ? $json : [],
                $userId
            );

            throw new RuntimeException($errMessage !== '' ? '公众号图片素材同步失败：'.$errMessage : '公众号图片素材同步失败。');
        }

        $this->logger->record(
            $siteId,
            'material_sync',
            'success',
            '公众号图片素材已同步。',
            ['file_name' => $fileName],
            is_array($json) ? $json : [],
            $userId
        );

        return [
            'media_id' => $mediaId,
            'url' => $url,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    protected function accessToken(int $siteId, array $settings, int $userId): string
    {
        $appId = trim((string) ($settings['app_id'] ?? ''));
        $appSecret = trim((string) ($settings['app_secret'] ?? ''));

        if ($appId === '' || $appSecret === '') {
            throw new RuntimeException('请先在公众号配置中填写 AppID 和 AppSecret。');
        }

        return Cache::remember(self::accessTokenCacheKey($siteId), now()->addSeconds(7000), function () use ($siteId, $appId, $appSecret, $userId): string {
            $response = Http::retry(2, 200)
                ->timeout(12)
                ->get('https://api.weixin.qq.com/cgi-bin/token', [
                    'grant_type' => 'client_credential',
                    'appid' => $appId,
                    'secret' => $appSecret,
                ]);

            $json = $response->json();
            $accessToken = trim((string) ($json['access_token'] ?? ''));
            $errMessage = trim((string) ($json['errmsg'] ?? ''));

            if (! $response->successful() || $accessToken === '') {
                $this->logger->record(
                    $siteId,
                    'access_token',
                    'failed',
                    $errMessage !== '' ? '获取公众号 access_token 失败：'.$errMessage : '获取公众号 access_token 失败。',
                    ['appid' => $appId],
                    is_array($json) ? $json : [],
                    $userId
                );

                throw new RuntimeException($errMessage !== '' ? '获取公众号 access_token 失败：'.$errMessage : '获取公众号 access_token 失败。');
            }

            $this->logger->record(
                $siteId,
                'access_token',
                'success',
                '公众号 access_token 获取成功。',
                ['appid' => $appId],
                [],
                $userId
            );

            return $accessToken;
        });
    }
}
