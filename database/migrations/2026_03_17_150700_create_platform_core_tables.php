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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('site_key', 50)->unique();
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedBigInteger('default_theme_id')->nullable();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_email', 100)->nullable();
            $table->string('address')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_keywords')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->text('remark')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('site_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 255)->unique();
            $table->boolean('is_primary')->default(false);
            $table->boolean('https_enabled')->default(true);
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('platform_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('platform_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('name', 100);
            $table->string('code', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('platform_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('platform_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('platform_permissions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('platform_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('platform_roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique('user_id');
        });

        Schema::create('site_roles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('name', 100);
            $table->string('code', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();
            $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            $table->index(['site_id', 'code']);
        });

        Schema::create('site_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('module', 50);
            $table->string('name', 100);
            $table->string('code', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
            $table->index(['module', 'code']);
        });

        Schema::create('site_role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('site_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('site_permissions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['site_id', 'role_id', 'permission_id'], 'site_role_permissions_unique');
            $table->index(['site_id', 'role_id'], 'site_role_permissions_site_role_index');
        });

        Schema::create('site_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('site_roles')->cascadeOnDelete();
            $table->timestamps();
            $table->index('site_id', 'site_user_roles_site_id_index');
            $table->index('user_id', 'site_user_roles_user_id_index');
            $table->index('role_id', 'site_user_roles_role_id_index');
            $table->unique(['site_id', 'user_id'], 'site_user_roles_site_id_user_id_unique');
        });

        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->string('description', 500)->nullable();
            $table->string('cover_image')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('theme_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theme_id')->constrained()->cascadeOnDelete();
            $table->string('version', 30);
            $table->string('package_path');
            $table->json('manifest_json')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            $table->unique(['theme_id', 'version']);
        });

        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope', 20)->default('site');
            $table->string('module', 50);
            $table->string('action', 50);
            $table->string('target_type', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['site_id', 'module', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
        Schema::dropIfExists('theme_versions');
        Schema::dropIfExists('themes');
        Schema::dropIfExists('site_user_roles');
        Schema::dropIfExists('site_role_permissions');
        Schema::dropIfExists('site_permissions');
        Schema::dropIfExists('site_roles');
        Schema::dropIfExists('platform_user_roles');
        Schema::dropIfExists('platform_role_permissions');
        Schema::dropIfExists('platform_permissions');
        Schema::dropIfExists('platform_roles');
        Schema::dropIfExists('site_domains');
        Schema::dropIfExists('sites');
    }
};
