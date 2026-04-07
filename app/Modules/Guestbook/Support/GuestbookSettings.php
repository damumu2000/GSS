<?php

namespace App\Modules\Guestbook\Support;

use Illuminate\Support\Facades\DB;

class GuestbookSettings
{
    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'enabled' => true,
            'name' => '留言板',
            'notice' => '如果您有任何意见、建议或需要反映情况，请在这里留言。我们会尽快查看并处理。感谢您的参与！',
            'notice_image' => '',
            'theme' => 'default',
            'show_name' => false,
            'show_after_reply' => true,
            'captcha_enabled' => true,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function themeOptions(): array
    {
        return [
            'default' => [
                'label' => '基础模板',
                'description' => '通用简洁，适合多数学校站点与常规留言场景。',
                'swatches' => ['#0050b3', '#dce9fb', '#f5f7fa'],
                'profile' => [
                    'primary' => '#0050b3',
                    'primary_deep' => '#003f8f',
                    'primary_soft' => 'rgba(0, 80, 179, 0.08)',
                    'primary_border' => 'rgba(0, 80, 179, 0.08)',
                    'bg' => '#f5f7fa',
                    'panel' => '#ffffff',
                    'text' => '#1f2937',
                    'muted' => '#8c8c8c',
                    'line' => '#e5e7eb',
                    'success' => '#059669',
                    'warning' => '#b45309',
                    'hero_gradient' => 'linear-gradient(135deg, rgba(0, 80, 179, 0.08) 0%, rgba(255, 255, 255, 0.96) 100%)',
                    'hero_border' => 'rgba(0, 80, 179, 0.08)',
                    'badge_bg' => '#f5f7fa',
                    'badge_text' => '#667085',
                    'reply_bg' => '#f8fafc',
                    'reply_text' => '#475467',
                    'flash_bg' => 'rgba(16,185,129,0.10)',
                ],
            ],
            'china-red' => [
                'label' => '中国红模板',
                'description' => '庄重正式，适合政务、机关及事业单位公开留言场景。',
                'swatches' => ['#b22222', '#d4a64f', '#fff6f4'],
                'profile' => [
                    'primary' => '#b22222',
                    'primary_deep' => '#7a1212',
                    'primary_soft' => 'rgba(178, 34, 34, 0.10)',
                    'primary_border' => 'rgba(178, 34, 34, 0.14)',
                    'bg' => '#faf7f5',
                    'panel' => '#ffffff',
                    'text' => '#2a1f1f',
                    'muted' => '#8f7a7a',
                    'line' => '#efe2df',
                    'success' => '#b45309',
                    'warning' => '#8f2d1f',
                    'hero_gradient' => 'linear-gradient(135deg, rgba(178, 34, 34, 0.12) 0%, rgba(255, 246, 244, 0.98) 50%, rgba(212, 166, 79, 0.18) 100%)',
                    'hero_border' => 'rgba(178, 34, 34, 0.14)',
                    'badge_bg' => 'rgba(178, 34, 34, 0.08)',
                    'badge_text' => '#8f2d1f',
                    'reply_bg' => '#fff8f6',
                    'reply_text' => '#674646',
                    'flash_bg' => 'rgba(180, 83, 9, 0.12)',
                ],
            ],
            'education-green' => [
                'label' => '清新绿模板',
                'description' => '清爽自然，适合学校、教育机构与校园沟通场景。',
                'swatches' => ['#2f8f57', '#ddefe3', '#f3fbf6'],
                'profile' => [
                    'primary' => '#2f8f57',
                    'primary_deep' => '#1f6b40',
                    'primary_soft' => 'rgba(47, 143, 87, 0.10)',
                    'primary_border' => 'rgba(47, 143, 87, 0.14)',
                    'bg' => '#f4faf6',
                    'panel' => '#ffffff',
                    'text' => '#1f2f28',
                    'muted' => '#74867c',
                    'line' => '#dde7df',
                    'success' => '#2f8f57',
                    'warning' => '#9a6700',
                    'hero_gradient' => 'linear-gradient(135deg, rgba(47, 143, 87, 0.12) 0%, rgba(255, 255, 255, 0.96) 55%, rgba(221, 239, 227, 0.95) 100%)',
                    'hero_border' => 'rgba(47, 143, 87, 0.12)',
                    'badge_bg' => 'rgba(47, 143, 87, 0.08)',
                    'badge_text' => '#2f8f57',
                    'reply_bg' => '#f5fbf7',
                    'reply_text' => '#426151',
                    'flash_bg' => 'rgba(47, 143, 87, 0.10)',
                ],
            ],
            'vibrant-orange' => [
                'label' => '活力橙模板',
                'description' => '明快亲和，适合幼教、青少年活动与活力型栏目。',
                'swatches' => ['#f28c28', '#ffe4c7', '#fff7ef'],
                'profile' => [
                    'primary' => '#f28c28',
                    'primary_deep' => '#c96a10',
                    'primary_soft' => 'rgba(242, 140, 40, 0.12)',
                    'primary_border' => 'rgba(242, 140, 40, 0.16)',
                    'bg' => '#fff9f3',
                    'panel' => '#ffffff',
                    'text' => '#35261d',
                    'muted' => '#907669',
                    'line' => '#f2e3d6',
                    'success' => '#ea7f1b',
                    'warning' => '#b45309',
                    'hero_gradient' => 'linear-gradient(135deg, rgba(242, 140, 40, 0.14) 0%, rgba(255, 255, 255, 0.96) 55%, rgba(255, 228, 199, 0.95) 100%)',
                    'hero_border' => 'rgba(242, 140, 40, 0.16)',
                    'badge_bg' => 'rgba(242, 140, 40, 0.10)',
                    'badge_text' => '#c96a10',
                    'reply_bg' => '#fff8f1',
                    'reply_text' => '#6f4d37',
                    'flash_bg' => 'rgba(242, 140, 40, 0.12)',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forSite(int $siteId): array
    {
        $defaults = $this->defaults();
        $settings = DB::table('site_settings')
            ->where('site_id', $siteId)
            ->whereIn('setting_key', [
                'module.guestbook.enabled',
                'module.guestbook.name',
                'module.guestbook.notice',
                'module.guestbook.notice_image',
                'module.guestbook.theme',
                'module.guestbook.show_name',
                'module.guestbook.show_after_reply',
                'module.guestbook.captcha_enabled',
            ])
            ->pluck('setting_value', 'setting_key');

        $themeKey = $this->stringValue($settings->get('module.guestbook.theme'), $defaults['theme']);
        $themeOptions = $this->themeOptions();
        if (! array_key_exists($themeKey, $themeOptions)) {
            $themeKey = $defaults['theme'];
        }

        return [
            'enabled' => $this->booleanValue($settings->get('module.guestbook.enabled'), $defaults['enabled']),
            'name' => $this->stringValue($settings->get('module.guestbook.name'), $defaults['name']),
            'notice' => $this->stringValue($settings->get('module.guestbook.notice'), $defaults['notice']),
            'notice_image' => $this->stringValue($settings->get('module.guestbook.notice_image'), $defaults['notice_image']),
            'theme' => $themeKey,
            'theme_profile' => $themeOptions[$themeKey]['profile'],
            'show_name' => $this->booleanValue($settings->get('module.guestbook.show_name'), $defaults['show_name']),
            'show_after_reply' => $this->booleanValue($settings->get('module.guestbook.show_after_reply'), $defaults['show_after_reply']),
            'captcha_enabled' => $this->booleanValue($settings->get('module.guestbook.captcha_enabled'), $defaults['captcha_enabled']),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function saveForSite(int $siteId, array $values, int $userId): void
    {
        $payloads = [
            'module.guestbook.enabled' => ! empty($values['enabled']) ? '1' : '0',
            'module.guestbook.name' => $this->stringValue($values['name'] ?? null, $this->defaults()['name']),
            'module.guestbook.notice' => $this->stringValue($values['notice'] ?? null, $this->defaults()['notice']),
            'module.guestbook.notice_image' => $this->stringValue($values['notice_image'] ?? null, $this->defaults()['notice_image']),
            'module.guestbook.theme' => $this->normalizeTheme((string) ($values['theme'] ?? $this->defaults()['theme'])),
            'module.guestbook.show_name' => ! empty($values['show_name']) ? '1' : '0',
            'module.guestbook.show_after_reply' => ! empty($values['show_after_reply']) ? '1' : '0',
            'module.guestbook.captcha_enabled' => ! empty($values['captcha_enabled']) ? '1' : '0',
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

    protected function normalizeTheme(string $theme): string
    {
        $theme = trim($theme);

        return array_key_exists($theme, $this->themeOptions()) ? $theme : $this->defaults()['theme'];
    }
}
