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
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('channels')->nullOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('type', 30)->default('list');
            $table->string('path', 255)->nullable();
            $table->unsignedInteger('depth')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->boolean('is_nav')->default(true);
            $table->string('list_template', 150)->nullable();
            $table->string('detail_template', 150)->nullable();
            $table->string('link_url', 500)->nullable();
            $table->string('link_target', 20)->default('_self');
            $table->string('seo_title')->nullable();
            $table->string('seo_keywords')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['site_id', 'slug']);
            $table->index(['site_id', 'parent_id', 'sort']);
        });

        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('channels')->nullOnDelete();
            $table->string('type', 20)->default('article');
            $table->string('title');
            $table->string('title_color', 20)->nullable();
            $table->boolean('title_bold')->default(false);
            $table->boolean('title_italic')->default(false);
            $table->string('sub_title')->nullable();
            $table->string('slug')->nullable();
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('author', 100)->nullable();
            $table->string('source', 100)->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('audit_status', 20)->default('draft');
            $table->boolean('is_top')->default(false);
            $table->boolean('is_recommend')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['site_id', 'channel_id', 'status']);
            $table->index(['site_id', 'type', 'published_at']);
            $table->index(['site_id', 'type', 'sort'], 'contents_site_type_sort_index');
            $table->index(
                ['site_id', 'type', 'deleted_at', 'sort', 'updated_at'],
                'contents_site_type_deleted_sort_updated_index'
            );
            $table->index(
                ['site_id', 'type', 'status', 'deleted_at', 'updated_at'],
                'contents_site_type_status_deleted_updated_index'
            );
        });

        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('title');
            $table->string('title_color', 20)->nullable();
            $table->boolean('title_bold')->default(false);
            $table->boolean('title_italic')->default(false);
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['content_id', 'version_no']);
        });

        Schema::create('content_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['content_id', 'channel_id']);
            $table->index('channel_id');
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('origin_name');
            $table->string('stored_name');
            $table->string('disk', 30)->default('public');
            $table->string('path');
            $table->string('url')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('sha1', 40)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index('site_id');
            $table->index(['site_id', 'created_at'], 'attachments_site_created_at_index');
        });

        Schema::create('attachment_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->constrained('attachments')->cascadeOnDelete();
            $table->string('relation_type', 100);
            $table->unsignedBigInteger('relation_id');
            $table->string('usage_slot', 50)->default('content_reference');
            $table->timestamps();
            $table->index(['relation_type', 'relation_id']);
            $table->index('attachment_id', 'attachment_relations_attachment_id_idx');
            $table->unique(
                ['attachment_id', 'relation_type', 'relation_id', 'usage_slot'],
                'attachment_relations_unique_reference_slot'
            );
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('setting_key', 100);
            $table->longText('setting_value')->nullable();
            $table->boolean('autoload')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['site_id', 'setting_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('attachment_relations');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('content_channels');
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('contents');
        Schema::dropIfExists('channels');
    }
};
