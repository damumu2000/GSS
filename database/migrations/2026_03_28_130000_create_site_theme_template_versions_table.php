<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_theme_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('theme_code', 80);
            $table->string('template_name', 120);
            $table->string('source_type', 20);
            $table->longText('template_source')->nullable();
            $table->string('action', 40)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();

            $table->index(['site_id', 'theme_code', 'template_name'], 'site_theme_template_versions_lookup_idx');
            $table->index(['site_id', 'consumed_at'], 'site_theme_template_versions_consumed_idx');
            $table->index(['site_id', 'theme_code', 'template_name', 'is_favorite'], 'site_theme_template_versions_favorite_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_theme_template_versions');
    }
};
