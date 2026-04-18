<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_module_bindings')) {
            return;
        }

        Schema::table('site_module_bindings', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_module_bindings', 'is_trial')) {
                $table->boolean('is_trial')->default(false)->after('module_id');
            }

            if (! Schema::hasColumn('site_module_bindings', 'is_paused')) {
                $table->boolean('is_paused')->default(false)->after('is_trial');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_module_bindings')) {
            return;
        }

        Schema::table('site_module_bindings', function (Blueprint $table): void {
            if (Schema::hasColumn('site_module_bindings', 'is_paused')) {
                $table->dropColumn('is_paused');
            }

            if (Schema::hasColumn('site_module_bindings', 'is_trial')) {
                $table->dropColumn('is_trial');
            }
        });
    }
};

