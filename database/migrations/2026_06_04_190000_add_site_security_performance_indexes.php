<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_security_events')) {
            Schema::table('site_security_events', function (Blueprint $table): void {
                if (! Schema::hasIndex('site_security_events', 'site_security_events_site_created_id_idx')) {
                    $table->index(['site_id', 'created_at', 'id'], 'site_security_events_site_created_id_idx');
                }

                if (! Schema::hasIndex('site_security_events', 'site_security_events_site_ip_created_idx')) {
                    $table->index(['site_id', 'ip_hash', 'created_at'], 'site_security_events_site_ip_created_idx');
                }

                if (! Schema::hasIndex('site_security_events', 'site_security_events_site_rule_created_idx')) {
                    $table->index(['site_id', 'rule_code', 'created_at'], 'site_security_events_site_rule_created_idx');
                }

                if (Schema::hasColumn('site_security_events', 'fingerprint')
                    && ! Schema::hasIndex('site_security_events', 'site_security_events_site_fingerprint_created_idx')) {
                    $table->index(['site_id', 'fingerprint', 'created_at'], 'site_security_events_site_fingerprint_created_idx');
                }
            });
        }

        if (Schema::hasTable('site_security_ip_reputations')) {
            Schema::table('site_security_ip_reputations', function (Blueprint $table): void {
                if (! Schema::hasIndex('site_security_ip_reputations', 'site_security_ip_reputations_site_seen_idx')) {
                    $table->index(['site_id', 'last_seen_at'], 'site_security_ip_reputations_site_seen_idx');
                }

                if (! Schema::hasIndex('site_security_ip_reputations', 'site_security_ip_reputations_site_status_seen_idx')) {
                    $table->index(['site_id', 'status', 'last_seen_at'], 'site_security_ip_reputations_site_status_seen_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('site_security_ip_reputations')) {
            Schema::table('site_security_ip_reputations', function (Blueprint $table): void {
                if (Schema::hasIndex('site_security_ip_reputations', 'site_security_ip_reputations_site_status_seen_idx')) {
                    $table->dropIndex('site_security_ip_reputations_site_status_seen_idx');
                }

                if (Schema::hasIndex('site_security_ip_reputations', 'site_security_ip_reputations_site_seen_idx')) {
                    $table->dropIndex('site_security_ip_reputations_site_seen_idx');
                }
            });
        }

        if (Schema::hasTable('site_security_events')) {
            Schema::table('site_security_events', function (Blueprint $table): void {
                if (Schema::hasColumn('site_security_events', 'fingerprint')
                    && Schema::hasIndex('site_security_events', 'site_security_events_site_fingerprint_created_idx')) {
                    $table->dropIndex('site_security_events_site_fingerprint_created_idx');
                }

                if (Schema::hasIndex('site_security_events', 'site_security_events_site_rule_created_idx')) {
                    $table->dropIndex('site_security_events_site_rule_created_idx');
                }

                if (Schema::hasIndex('site_security_events', 'site_security_events_site_ip_created_idx')) {
                    $table->dropIndex('site_security_events_site_ip_created_idx');
                }

                if (Schema::hasIndex('site_security_events', 'site_security_events_site_created_id_idx')) {
                    $table->dropIndex('site_security_events_site_created_id_idx');
                }
            });
        }
    }
};
