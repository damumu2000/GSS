<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('site_permissions')->updateOrInsert(
            ['code' => 'site.role.manage'],
            [
                'module' => 'user',
                'name' => '管理操作角色',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $permissionId = (int) DB::table('site_permissions')
            ->where('code', 'site.role.manage')
            ->value('id');

        if ($permissionId < 1) {
            return;
        }

        $siteAdminRoles = DB::table('site_roles')
            ->where('code', 'site_admin')
            ->get(['id', 'site_id']);

        foreach ($siteAdminRoles as $role) {
            $siteId = $role->site_id;

            if ($siteId === null) {
                $boundSiteIds = DB::table('sites')->pluck('id');

                foreach ($boundSiteIds as $boundSiteId) {
                    DB::table('site_role_permissions')->updateOrInsert(
                        [
                            'site_id' => (int) $boundSiteId,
                            'role_id' => (int) $role->id,
                            'permission_id' => $permissionId,
                        ],
                        ['created_at' => $now, 'updated_at' => $now],
                    );
                }

                continue;
            }

            DB::table('site_role_permissions')->updateOrInsert(
                [
                    'site_id' => (int) $siteId,
                    'role_id' => (int) $role->id,
                    'permission_id' => $permissionId,
                ],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        $permissionId = (int) DB::table('site_permissions')
            ->where('code', 'site.role.manage')
            ->value('id');

        if ($permissionId < 1) {
            return;
        }

        $siteAdminRoleIds = DB::table('site_roles')
            ->where('code', 'site_admin')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($siteAdminRoleIds !== []) {
            DB::table('site_role_permissions')
                ->where('permission_id', $permissionId)
                ->whereIn('role_id', $siteAdminRoleIds)
                ->delete();
        }

        $hasRemainingBindings = DB::table('site_role_permissions')
            ->where('permission_id', $permissionId)
            ->exists();

        if (! $hasRemainingBindings) {
            DB::table('site_permissions')
                ->where('id', $permissionId)
                ->delete();
        }
    }
};
