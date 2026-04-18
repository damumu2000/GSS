<?php

namespace App\Modules\WechatOfficial\Support;

use App\Support\Modules\ModuleManager;

class WechatOfficialModule
{
    /**
     * @var array<int, array<string, mixed>|null>
     */
    protected array $boundCache = [];

    public function __construct(
        protected ModuleManager $moduleManager,
        protected WechatOfficialSettings $settings
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function boundForSite(int $siteId): ?array
    {
        if (array_key_exists($siteId, $this->boundCache)) {
            return $this->boundCache[$siteId];
        }

        $module = $this->moduleManager
            ->boundSiteModules($siteId)
            ->firstWhere('code', 'wechat_official');

        return $this->boundCache[$siteId] = is_array($module) ? $module : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeForSite(int $siteId): ?array
    {
        $module = $this->boundForSite($siteId);

        if (! is_array($module)) {
            return null;
        }

        return $this->settings->forSite($siteId)['enabled'] ? $module : null;
    }
}
