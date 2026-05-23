<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_security_events') && Schema::hasColumn('site_security_events', 'risk_score')) {
            Schema::table('site_security_events', function (Blueprint $table): void {
                $table->dropColumn('risk_score');
            });
        }

        if (Schema::hasTable('site_security_ip_reputations') && Schema::hasColumn('site_security_ip_reputations', 'risk_score')) {
            Schema::table('site_security_ip_reputations', function (Blueprint $table): void {
                $table->dropColumn('risk_score');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('site_security_events') && ! Schema::hasColumn('site_security_events', 'risk_score')) {
            Schema::table('site_security_events', function (Blueprint $table): void {
                $table->unsignedTinyInteger('risk_score')->default(0)->after('rule_name');
            });
        }

        if (Schema::hasTable('site_security_ip_reputations') && ! Schema::hasColumn('site_security_ip_reputations', 'risk_score')) {
            Schema::table('site_security_ip_reputations', function (Blueprint $table): void {
                $table->unsignedInteger('risk_score')->default(0)->after('ip_hash');
            });
        }
    }
};
