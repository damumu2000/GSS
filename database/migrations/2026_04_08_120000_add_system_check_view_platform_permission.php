<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('platform_permissions')->updateOrInsert(
            ['code' => 'system.check.view'],
            [
                'module' => 'system',
                'name' => '查看系统检查',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permissionId = DB::table('platform_permissions')
            ->where('code', 'system.check.view')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        foreach (['super_admin', 'platform_admin'] as $roleCode) {
            $roleId = DB::table('platform_roles')
                ->where('code', $roleCode)
                ->value('id');

            if (! $roleId) {
                continue;
            }

            DB::table('platform_role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('platform_permissions')
            ->where('code', 'system.check.view')
            ->value('id');

        if ($permissionId) {
            DB::table('platform_role_permissions')
                ->where('permission_id', $permissionId)
                ->delete();
        }

        DB::table('platform_permissions')
            ->where('code', 'system.check.view')
            ->delete();
    }
};
