<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('promo_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('channels')->nullOnDelete();
            $table->string('code', 80);
            $table->string('name', 50);
            $table->string('page_scope', 20)->default('global');
            $table->string('display_mode', 20)->default('single');
            $table->string('template_name', 50)->nullable();
            $table->string('scope_hash', 64);
            $table->boolean('allow_multiple')->default(false);
            $table->unsignedSmallInteger('max_items')->default(1);
            $table->unsignedTinyInteger('status')->default(1);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'code', 'scope_hash'], 'promo_positions_site_code_scope_unique');
            $table->index(['site_id', 'code', 'page_scope'], 'promo_positions_site_code_scope_lookup_index');
            $table->index(['site_id', 'status'], 'promo_positions_site_status_index');
            $table->index(['site_id', 'page_scope', 'status'], 'promo_positions_site_scope_status_index');
        });

        Schema::create('promo_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('promo_positions')->cascadeOnDelete();
            $table->foreignId('attachment_id')->constrained('attachments')->cascadeOnDelete();
            $table->string('title', 80)->nullable();
            $table->string('subtitle', 160)->nullable();
            $table->string('link_url', 2048)->nullable();
            $table->string('link_target', 16)->default('_self');
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->json('display_payload')->nullable();
            $table->timestamps();

            $table->index(['position_id', 'status', 'sort'], 'promo_items_position_status_sort_index');
            $table->index(['site_id', 'status', 'start_at', 'end_at'], 'promo_items_site_status_window_index');
            $table->index(['status', 'end_at'], 'promo_items_status_end_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_items');
        Schema::dropIfExists('promo_positions');
    }
};
