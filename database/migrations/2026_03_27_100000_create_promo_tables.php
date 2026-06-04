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
            $table->string('code', 80);
            $table->string('name', 50);
            $table->string('display_mode', 20)->default('single');
            $table->unsignedTinyInteger('status')->default(1);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'code'], 'promo_positions_site_code_unique');
            $table->index(['site_id', 'status'], 'promo_positions_site_status_index');
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
