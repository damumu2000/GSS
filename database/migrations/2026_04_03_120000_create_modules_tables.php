<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 100)->unique();
            $table->string('version', 50)->nullable();
            $table->string('scope', 20)->default('site');
            $table->string('author', 100)->nullable();
            $table->string('platform_entry_route', 150)->nullable();
            $table->string('site_entry_route', 150)->nullable();
            $table->text('description')->nullable();
            $table->boolean('status')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('site_module_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->boolean('is_trial')->default(false);
            $table->boolean('is_paused')->default(false);
            $table->timestamps();
            $table->unique(['site_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_module_bindings');
        Schema::dropIfExists('modules');
    }
};
