<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_security_daily_stats', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_security_daily_stats', 'blocked_probe_abuse')) {
                $table->unsignedInteger('blocked_probe_abuse')->default(0)->after('blocked_rate_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_security_daily_stats', function (Blueprint $table): void {
            if (Schema::hasColumn('site_security_daily_stats', 'blocked_probe_abuse')) {
                $table->dropColumn('blocked_probe_abuse');
            }
        });
    }
};
