<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Site-owned template metadata is now stored in site_template_meta.
    }

    public function down(): void
    {
        // no-op
    }
};
