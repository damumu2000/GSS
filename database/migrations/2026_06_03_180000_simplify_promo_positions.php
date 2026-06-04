<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promo_positions')) {
            return;
        }

        Schema::table('promo_positions', function (Blueprint $table): void {
            if ($this->indexExists('promo_positions', 'promo_positions_site_code_scope_unique')) {
                $table->dropUnique('promo_positions_site_code_scope_unique');
            }

            if ($this->indexExists('promo_positions', 'promo_positions_site_code_scope_lookup_index')) {
                $table->dropIndex('promo_positions_site_code_scope_lookup_index');
            }

            if ($this->indexExists('promo_positions', 'promo_positions_site_scope_status_index')) {
                $table->dropIndex('promo_positions_site_scope_status_index');
            }

            if (Schema::hasColumn('promo_positions', 'channel_id')) {
                if ($this->foreignKeyExists('promo_positions', 'promo_positions_channel_id_foreign')) {
                    $table->dropForeign('promo_positions_channel_id_foreign');
                }
                $table->dropColumn('channel_id');
            }

            foreach (['page_scope', 'template_name', 'scope_hash', 'allow_multiple', 'max_items'] as $column) {
                if (Schema::hasColumn('promo_positions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        try {
            Schema::table('promo_positions', function (Blueprint $table): void {
                $table->unique(['site_id', 'code'], 'promo_positions_site_code_unique');
            });
        } catch (Throwable) {
            //
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('promo_positions')) {
            return;
        }

        Schema::table('promo_positions', function (Blueprint $table): void {
            if ($this->indexExists('promo_positions', 'promo_positions_site_code_unique')) {
                $table->dropUnique('promo_positions_site_code_unique');
            }

            if (! Schema::hasColumn('promo_positions', 'channel_id')) {
                $table->foreignId('channel_id')->nullable()->after('site_id')->constrained('channels')->nullOnDelete();
            }

            if (! Schema::hasColumn('promo_positions', 'page_scope')) {
                $table->string('page_scope', 20)->default('global')->after('name');
            }

            if (! Schema::hasColumn('promo_positions', 'template_name')) {
                $table->string('template_name', 50)->nullable()->after('display_mode');
            }

            if (! Schema::hasColumn('promo_positions', 'scope_hash')) {
                $table->string('scope_hash', 64)->default('global|site|default')->after('template_name');
            }

            if (! Schema::hasColumn('promo_positions', 'allow_multiple')) {
                $table->boolean('allow_multiple')->default(false)->after('scope_hash');
            }

            if (! Schema::hasColumn('promo_positions', 'max_items')) {
                $table->unsignedSmallInteger('max_items')->default(1)->after('allow_multiple');
            }
        });

        Schema::table('promo_positions', function (Blueprint $table): void {
            $table->unique(['site_id', 'code', 'scope_hash'], 'promo_positions_site_code_scope_unique');
            $table->index(['site_id', 'code', 'page_scope'], 'promo_positions_site_code_scope_lookup_index');
            $table->index(['site_id', 'page_scope', 'status'], 'promo_positions_site_scope_status_index');
        });
    }

    protected function indexExists(string $table, string $name): bool
    {
        try {
            foreach (Schema::getIndexes($table) as $index) {
                if (($index['name'] ?? null) === $name) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    protected function foreignKeyExists(string $table, string $name): bool
    {
        try {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (($foreignKey['name'] ?? null) === $name) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }
};
