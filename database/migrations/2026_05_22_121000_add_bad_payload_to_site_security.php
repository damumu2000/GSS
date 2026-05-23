<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_settings')) {
            $now = now();

            foreach ([
                'security.block_bad_payload_enabled' => '1',
                'security.payload_max_fields' => '80',
                'security.payload_max_value_length' => '2000',
            ] as $key => $value) {
                DB::table('system_settings')->updateOrInsert(
                    ['setting_key' => $key],
                    [
                        'setting_value' => $value,
                        'autoload' => 1,
                        'updated_by' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }

        Schema::table('site_security_daily_stats', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_security_daily_stats', 'blocked_bad_payload')) {
                $table->unsignedInteger('blocked_bad_payload')->default(0)->after('blocked_bad_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_security_daily_stats', function (Blueprint $table): void {
            if (Schema::hasColumn('site_security_daily_stats', 'blocked_bad_payload')) {
                $table->dropColumn('blocked_bad_payload');
            }
        });
    }
};
