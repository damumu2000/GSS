<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ErrorPageBrand
{
    protected static ?string $systemName = null;

    public static function systemName(): string
    {
        if (static::$systemName !== null) {
            return static::$systemName;
        }

        return static::$systemName = rescue(
            function (): string {
                if (! Schema::hasTable('system_settings')) {
                    return (string) config('app.name');
                }

                $value = DB::table('system_settings')
                    ->where('setting_key', 'system.name')
                    ->value('setting_value');

                return is_string($value) && trim($value) !== ''
                    ? trim($value)
                    : (string) config('app.name');
            },
            (string) config('app.name'),
            false,
        );
    }
}
