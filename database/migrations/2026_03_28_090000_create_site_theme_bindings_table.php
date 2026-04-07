<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_theme_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['site_id', 'theme_id']);
        });

        $now = now();

        $rows = DB::table('sites')
            ->whereNotNull('default_theme_id')
            ->get(['id', 'default_theme_id'])
            ->map(fn ($site) => [
                'site_id' => (int) $site->id,
                'theme_id' => (int) $site->default_theme_id,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->unique(fn (array $row) => $row['site_id'].':'.$row['theme_id'])
            ->values()
            ->all();

        if ($rows !== []) {
            DB::table('site_theme_bindings')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_theme_bindings');
    }
};
