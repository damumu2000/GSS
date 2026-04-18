<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->string('template_name', 150)->nullable()->after('type');
            $table->index(['site_id', 'type', 'template_name'], 'contents_site_type_template_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropIndex('contents_site_type_template_name_index');
            $table->dropColumn('template_name');
        });
    }
};
