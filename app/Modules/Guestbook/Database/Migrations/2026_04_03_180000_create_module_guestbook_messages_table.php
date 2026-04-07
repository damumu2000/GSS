<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_guestbook_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->unsignedInteger('display_no');
            $table->string('name', 50);
            $table->string('phone', 50);
            $table->text('content');
            $table->text('original_content')->nullable();
            $table->string('status', 20)->default('pending');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->text('reply_content')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->unsignedBigInteger('replied_by')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'display_no']);
            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'created_at', 'id']);
            $table->index(['site_id', 'replied_at', 'id']);
            $table->index(['site_id', 'is_read', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_guestbook_messages');
    }
};
