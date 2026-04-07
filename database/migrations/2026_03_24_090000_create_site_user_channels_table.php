<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_user_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['site_id', 'user_id', 'channel_id']);
            $table->index(['site_id', 'user_id']);
        });

        if (! Schema::hasTable('site_role_channels')) {
            return;
        }

        $now = now();

        $rows = DB::table('site_user_roles')
            ->join('site_role_channels', function ($join): void {
                $join->on('site_role_channels.site_id', '=', 'site_user_roles.site_id')
                    ->on('site_role_channels.role_id', '=', 'site_user_roles.role_id');
            })
            ->distinct()
            ->orderBy('site_user_roles.site_id')
            ->orderBy('site_user_roles.user_id')
            ->orderBy('site_role_channels.channel_id')
            ->get([
                'site_user_roles.site_id',
                'site_user_roles.user_id',
                'site_role_channels.channel_id',
            ]);

        foreach ($rows as $row) {
            DB::table('site_user_channels')->insert([
                'site_id' => $row->site_id,
                'user_id' => $row->user_id,
                'channel_id' => $row->channel_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_user_channels');
    }
};
