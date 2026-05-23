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
                'security.block_bad_client_enabled' => '1',
                'security.block_bad_method_enabled' => '1',
                'security.ip_allowlist' => '',
                'security.ip_blocklist' => '',
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

        Schema::table('site_security_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_security_events', 'risk_level')) {
                $table->string('risk_level', 20)->default('medium')->after('rule_name');
            }

            if (! Schema::hasColumn('site_security_events', 'action')) {
                $table->string('action', 40)->default('block')->after('risk_level');
            }

            if (! Schema::hasColumn('site_security_events', 'user_agent')) {
                $table->string('user_agent', 255)->nullable()->after('client_ip');
            }

            if (! Schema::hasColumn('site_security_events', 'referer')) {
                $table->string('referer', 255)->nullable()->after('user_agent');
            }

            if (! Schema::hasColumn('site_security_events', 'request_query')) {
                $table->text('request_query')->nullable()->after('referer');
            }

            if (! Schema::hasColumn('site_security_events', 'fingerprint')) {
                $table->string('fingerprint', 64)->nullable()->after('ip_hash');
            }
        });

        Schema::table('site_security_daily_stats', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_security_daily_stats', 'blocked_ip_blocklist')) {
                $table->unsignedInteger('blocked_ip_blocklist')->default(0)->after('blocked_probe_abuse');
            }

            if (! Schema::hasColumn('site_security_daily_stats', 'blocked_bad_client')) {
                $table->unsignedInteger('blocked_bad_client')->default(0)->after('blocked_ip_blocklist');
            }

            if (! Schema::hasColumn('site_security_daily_stats', 'blocked_bad_method')) {
                $table->unsignedInteger('blocked_bad_method')->default(0)->after('blocked_bad_client');
            }
        });

        if (! Schema::hasTable('site_security_ip_reputations')) {
            Schema::create('site_security_ip_reputations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->string('client_ip', 45)->nullable();
                $table->string('ip_hash', 64);
                $table->unsignedInteger('hit_count')->default(0);
                $table->unsignedInteger('high_risk_count')->default(0);
                $table->string('last_rule_code', 64)->nullable();
                $table->string('last_request_path', 255)->nullable();
                $table->string('status', 20)->default('monitored');
                $table->timestamp('blocked_until')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->unique(['site_id', 'ip_hash'], 'site_security_ip_reputations_site_ip_unique');
                $table->index(['site_id', 'status'], 'site_security_ip_reputations_site_status_idx');
                $table->index(['site_id', 'blocked_until'], 'site_security_ip_reputations_site_blocked_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_security_ip_reputations');

        Schema::table('site_security_daily_stats', function (Blueprint $table): void {
            if (Schema::hasColumn('site_security_daily_stats', 'blocked_ip_blocklist')) {
                $table->dropColumn('blocked_ip_blocklist');
            }

            if (Schema::hasColumn('site_security_daily_stats', 'blocked_bad_client')) {
                $table->dropColumn('blocked_bad_client');
            }

            if (Schema::hasColumn('site_security_daily_stats', 'blocked_bad_method')) {
                $table->dropColumn('blocked_bad_method');
            }
        });

        Schema::table('site_security_events', function (Blueprint $table): void {
            $columns = [
                'risk_level',
                'action',
                'user_agent',
                'referer',
                'request_query',
                'fingerprint',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('site_security_events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
