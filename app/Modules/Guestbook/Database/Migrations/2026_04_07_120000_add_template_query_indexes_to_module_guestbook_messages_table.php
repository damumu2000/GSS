<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('module_guestbook_messages')) {
            return;
        }

        Schema::table('module_guestbook_messages', function (Blueprint $table): void {
            $table->index(['site_id', 'created_at', 'id'], 'gb_messages_site_created_id_idx');
            $table->index(['site_id', 'replied_at', 'id'], 'gb_messages_site_replied_id_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('module_guestbook_messages')) {
            return;
        }

        Schema::table('module_guestbook_messages', function (Blueprint $table): void {
            $table->dropIndex('gb_messages_site_created_id_idx');
            $table->dropIndex('gb_messages_site_replied_id_idx');
        });
    }
};
