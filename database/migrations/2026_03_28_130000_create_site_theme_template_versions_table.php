<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Site-owned template versions are now stored in site_template_versions.
    }

    public function down(): void
    {
        // no-op
    }
};
