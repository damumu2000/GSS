<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Theme-market bindings have been removed in favor of site-owned templates.
    }

    public function down(): void
    {
        // no-op
    }
};
