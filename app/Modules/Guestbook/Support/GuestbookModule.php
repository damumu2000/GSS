<?php

namespace App\Modules\Guestbook\Support;

use App\Support\Modules\ModuleManager;

class GuestbookModule
{
    public function __construct(
        protected ModuleManager $moduleManager,
        protected GuestbookSettings $settings
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function boundForSite(int $siteId): ?array
    {
        $module = $this->moduleManager
            ->boundSiteModules($siteId)
            ->firstWhere('code', 'guestbook');

        return is_array($module) ? $module : null;
    }

    public function activeForSite(int $siteId): ?array
    {
        $module = $this->boundForSite($siteId);

        if (! is_array($module)) {
            return null;
        }

        return $this->settings->forSite($siteId)['enabled'] ? $module : null;
    }
}
