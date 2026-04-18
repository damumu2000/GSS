<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_wechat_official_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('official_name', 80)->default('');
            $table->string('app_id', 100)->default('');
            $table->text('app_secret')->nullable();
            $table->text('token')->nullable();
            $table->text('encoding_aes_key')->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('created_by')->default(0);
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->timestamps();
            $table->unique('site_id');
        });

        Schema::create('module_wechat_official_menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedInteger('sort')->default(0);
            $table->string('name', 50);
            $table->string('type', 40)->default('view');
            $table->string('key', 120)->default('');
            $table->string('url', 1000)->default('');
            $table->string('media_id', 120)->default('');
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->index(['site_id', 'parent_id']);
        });

        Schema::create('module_wechat_official_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->unsignedBigInteger('attachment_id')->nullable();
            $table->string('type', 30)->default('image');
            $table->string('title', 160)->default('');
            $table->string('wechat_media_id', 120)->default('');
            $table->string('wechat_url', 1000)->default('');
            $table->string('file_path', 1000)->default('');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index(['site_id', 'type']);
        });

        Schema::create('module_wechat_official_article_pushes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->unsignedBigInteger('content_id')->default(0);
            $table->string('title', 200)->default('');
            $table->string('status', 30)->default('draft');
            $table->string('draft_media_id', 120)->default('');
            $table->string('publish_id', 120)->default('');
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('created_by')->default(0);
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->timestamps();
            $table->index(['site_id', 'content_id']);
            $table->index(['site_id', 'status']);
        });

        Schema::create('module_wechat_official_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('channel', 30)->default('api');
            $table->string('action', 60)->default('');
            $table->string('status', 30)->default('success');
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->text('message')->nullable();
            $table->unsignedBigInteger('created_by')->default(0);
            $table->timestamps();
            $table->index(['site_id', 'channel']);
            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_wechat_official_logs');
        Schema::dropIfExists('module_wechat_official_article_pushes');
        Schema::dropIfExists('module_wechat_official_materials');
        Schema::dropIfExists('module_wechat_official_menus');
        Schema::dropIfExists('module_wechat_official_accounts');
    }
};
