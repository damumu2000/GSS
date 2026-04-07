<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class PromoItemExpiryManager
{
    public function deactivateExpiredItems(?int $siteId = null): int
    {
        $query = DB::table('promo_items')
            ->where('status', 1)
            ->whereNotNull('end_at')
            ->where('end_at', '<', now());

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        return $query->update([
            'status' => 0,
            'updated_at' => now(),
        ]);
    }
}
