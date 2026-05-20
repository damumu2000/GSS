<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FrontendContent
{
    public static function visibleQuery(int $siteId, ?string $type = null, string $tableAlias = 'contents'): Builder
    {
        $query = DB::table('contents')
            ->where("{$tableAlias}.site_id", $siteId);

        return static::applyVisibleScope($query, $tableAlias, $type);
    }

    public static function applyVisibleScope(Builder $query, string $tableAlias = 'contents', ?string $type = null): Builder
    {
        $query->whereNull("{$tableAlias}.deleted_at")
            ->where("{$tableAlias}.status", 'published');

        if ($type !== null && $type !== '') {
            $query->where("{$tableAlias}.type", $type);
        }

        return $query;
    }
}
