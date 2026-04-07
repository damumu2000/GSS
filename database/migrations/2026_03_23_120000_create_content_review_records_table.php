<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_review_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('content_id');
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('reviewer_user_id')->nullable();
            $table->string('reviewer_name', 100)->nullable();
            $table->string('reviewer_phone', 50)->nullable();
            $table->string('action', 20);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['content_id', 'created_at']);
            $table->index(['content_id', 'action', 'created_at'], 'content_review_records_content_action_created_index');
            $table->index(['site_id', 'action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_review_records');
    }
};
