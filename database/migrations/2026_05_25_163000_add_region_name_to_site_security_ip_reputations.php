<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_security_ip_reputations') || Schema::hasColumn('site_security_ip_reputations', 'region_name')) {
            return;
        }

        Schema::table('site_security_ip_reputations', function (Blueprint $table): void {
            $table->string('region_name', 100)->nullable()->after('client_ip');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_security_ip_reputations') || ! Schema::hasColumn('site_security_ip_reputations', 'region_name')) {
            return;
        }

        Schema::table('site_security_ip_reputations', function (Blueprint $table): void {
            $table->dropColumn('region_name');
        });
    }
};
