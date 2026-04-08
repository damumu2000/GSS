<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('platform_permissions')
            ->where('code', 'platform.notice.manage')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('platform_role_permissions')
            ->where('permission_id', $permissionId)
            ->delete();

        DB::table('platform_permissions')
            ->where('id', $permissionId)
            ->delete();
    }

    public function down(): void
    {
        // Legacy permission intentionally not restored.
    }
};
