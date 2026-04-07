<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_theme_template_meta', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('theme_code', 80);
            $table->string('template_name', 120);
            $table->string('title', 120)->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'theme_code', 'template_name'], 'site_theme_template_meta_unique');
            $table->index(['site_id', 'theme_code'], 'site_theme_template_meta_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_theme_template_meta');
    }
};
