<?php

namespace Database\Seeders;

use App\Support\Site as SitePath;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CmsBootstrapSeeder extends Seeder
{
    /**
     * Seed the application's CMS baseline data.
     */
    public function run(): void
    {
        $now = now();

        $platformRoles = [
            ['name' => '总管理员', 'code' => 'super_admin', 'description' => '拥有平台和所有站点权限'],
            ['name' => '平台管理员', 'code' => 'platform_admin', 'description' => '负责平台运维配置'],
        ];

        foreach ($platformRoles as $role) {
            DB::table('platform_roles')->updateOrInsert(
                ['code' => $role['code']],
                $role + ['status' => 1, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $roles = [
            ['name' => '站点管理员', 'code' => 'site_admin', 'description' => '负责单个站点的完整管理'],
            ['name' => '内容编辑', 'code' => 'editor', 'description' => '负责内容录入和编辑'],
            ['name' => '审核员', 'code' => 'reviewer', 'description' => '负责审核发布'],
            ['name' => '附件管理员', 'code' => 'uploader', 'description' => '负责附件资源管理'],
            ['name' => '模板编辑', 'code' => 'template_editor', 'description' => '负责模板和主题配置'],
        ];

        foreach ($roles as $role) {
            DB::table('site_roles')->updateOrInsert(
                ['code' => $role['code']],
                $role + ['status' => 1, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $platformPermissions = [
            ['module' => 'platform', 'name' => '进入平台后台', 'code' => 'platform.view'],
            ['module' => 'database', 'name' => '管理数据库', 'code' => 'database.manage'],
            ['module' => 'module', 'name' => '管理功能模块', 'code' => 'module.manage', 'description' => '管理平台模块列表、启用和禁用模块'],
            ['module' => 'site', 'name' => '管理站点', 'code' => 'site.manage'],
            ['module' => 'user', 'name' => '管理平台用户', 'code' => 'platform.user.manage'],
            ['module' => 'platform_role', 'name' => '管理平台角色', 'code' => 'platform.role.manage'],
            ['module' => 'system', 'name' => '查看系统检查', 'code' => 'system.check.view'],
            ['module' => 'system', 'name' => '管理系统设置', 'code' => 'system.setting.manage'],
            ['module' => 'log', 'name' => '查看平台日志', 'code' => 'platform.log.view'],
        ];

        foreach ($platformPermissions as $permission) {
            DB::table('platform_permissions')->updateOrInsert(
                ['code' => $permission['code']],
                $permission + ['created_at' => $now, 'updated_at' => $now],
            );
        }

        DB::table('platform_role_permissions')
            ->whereIn('permission_id', DB::table('platform_permissions')->where('code', 'theme.market.manage')->pluck('id'))
            ->delete();

        DB::table('platform_permissions')
            ->where('code', 'theme.market.manage')
            ->delete();

        $legacyPlatformNoticePermissionId = DB::table('platform_permissions')
            ->where('code', 'platform.notice.manage')
            ->value('id');

        if ($legacyPlatformNoticePermissionId) {
            DB::table('platform_role_permissions')
                ->where('permission_id', $legacyPlatformNoticePermissionId)
                ->delete();

            DB::table('platform_permissions')
                ->where('id', $legacyPlatformNoticePermissionId)
                ->delete();
        }

        $permissions = [
            ['module' => 'channel', 'name' => '管理栏目', 'code' => 'channel.manage'],
            ['module' => 'module', 'name' => '使用功能模块', 'code' => 'module.use', 'description' => '查看当前站点已绑定的功能模块'],
            ['module' => 'promo', 'name' => '管理图宣', 'code' => 'promo.manage'],
            ['module' => 'content', 'name' => '管理内容', 'code' => 'content.manage'],
            ['module' => 'content', 'name' => '发布内容', 'code' => 'content.publish'],
            ['module' => 'content', 'name' => '审核内容', 'code' => 'content.audit'],
            ['module' => 'attachment', 'name' => '管理附件', 'code' => 'attachment.manage'],
            ['module' => 'theme', 'name' => '使用主题', 'code' => 'theme.use'],
            ['module' => 'theme', 'name' => '编辑模板', 'code' => 'theme.edit'],
            ['module' => 'security', 'name' => '查看安全防护', 'code' => 'security.view'],
            ['module' => 'setting', 'name' => '管理站点设置', 'code' => 'setting.manage'],
            ['module' => 'user', 'name' => '管理站点用户', 'code' => 'site.user.manage'],
            ['module' => 'log', 'name' => '查看操作日志', 'code' => 'log.view'],
        ];

        foreach ($permissions as $permission) {
            DB::table('site_permissions')->updateOrInsert(
                ['code' => $permission['code']],
                $permission + ['created_at' => $now, 'updated_at' => $now],
            );
        }

        $platformRolePermissions = config('cms.default_platform_role_permissions', []);

        foreach ($platformRolePermissions as $roleCode => $permissionCodes) {
            $roleId = DB::table('platform_roles')->where('code', $roleCode)->value('id');

            if (! $roleId) {
                continue;
            }

            foreach ($permissionCodes as $permissionCode) {
                $permissionId = DB::table('platform_permissions')->where('code', $permissionCode)->value('id');

                if (! $permissionId) {
                    continue;
                }

                DB::table('platform_role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        $moduleManagePermissionId = DB::table('platform_permissions')
            ->where('code', 'module.manage')
            ->value('id');

        if ($moduleManagePermissionId) {
            $platformRoleIds = DB::table('platform_roles')
                ->whereIn('code', ['super_admin', 'platform_admin'])
                ->pluck('id');

            foreach ($platformRoleIds as $roleId) {
                DB::table('platform_role_permissions')->updateOrInsert(
                    ['role_id' => (int) $roleId, 'permission_id' => (int) $moduleManagePermissionId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        $superAdminId = (int) config('cms.super_admin_user_id', 1);
        $superAdminRoleId = DB::table('platform_roles')->where('code', 'super_admin')->value('id');

        if ($superAdminId > 0 && $superAdminRoleId) {
            DB::table('platform_user_roles')->updateOrInsert(
                ['user_id' => $superAdminId],
                ['role_id' => $superAdminRoleId, 'created_at' => $now, 'updated_at' => $now],
            );

            DB::table('platform_user_roles')
                ->where('user_id', $superAdminId)
                ->where('role_id', '!=', $superAdminRoleId)
                ->delete();
        }

        $siteId = DB::table('sites')->where('site_key', 'site')->value('id');

        if (! $siteId) {
            DB::table('sites')->insert([
                'name' => '示例学校',
                'site_key' => 'site',
                'status' => 1,
                'template_limit' => 5,
                'active_site_template_id' => null,
                'contact_phone' => '010-88886666',
                'contact_email' => 'office@example-school.cn',
                'address' => '北京市示例区教育路 66 号',
                'seo_title' => '示例学校官网',
                'seo_keywords' => '示例学校,学校官网',
                'seo_description' => '示例学校官网演示站点',
                'remark' => '<p>默认演示站点，可用于平台级多站点后台的功能测试与主题预览。</p>',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $siteId = DB::getPdo()->lastInsertId();
        }

        $siteTemplateId = DB::table('site_templates')
            ->where('site_id', $siteId)
            ->where('template_key', 'default')
            ->value('id');

        if (! $siteTemplateId) {
            $siteTemplateId = DB::table('site_templates')->insertGetId([
                'site_id' => $siteId,
                'name' => '默认模板',
                'template_key' => 'default',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('sites')
            ->where('id', $siteId)
            ->update([
                'template_limit' => max(1, (int) (DB::table('sites')->where('id', $siteId)->value('template_limit') ?: 5)),
                'active_site_template_id' => $siteTemplateId,
                'updated_at' => $now,
            ]);

        $templateRoot = SitePath::siteTemplateRoot('site', 'default');
        $legacyTemplateRoot = storage_path('app/theme_templates/site');
        $this->syncDefaultTemplateScaffold($templateRoot, $legacyTemplateRoot);

        if (! File::exists($templateRoot.DIRECTORY_SEPARATOR.'home.tpl')) {
            File::put($templateRoot.DIRECTORY_SEPARATOR.'home.tpl', '站点模板还未启用，请先在后台模板管理中启用可访问模板。');
        }

        DB::table('site_domains')->updateOrInsert(
            ['domain' => 'site.local'],
            [
                'site_id' => $siteId,
                'is_primary' => 1,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $defaultSiteRolePermissions = config('cms.default_site_role_permissions', []);

        foreach ($defaultSiteRolePermissions as $roleCode => $permissionCodes) {
            $roleId = DB::table('site_roles')
                ->where('code', $roleCode)
                ->whereNull('site_id')
                ->value('id');

            if (! $roleId) {
                continue;
            }

            foreach ($permissionCodes as $permissionCode) {
                $permissionId = DB::table('site_permissions')
                    ->where('code', $permissionCode)
                    ->value('id');

                if (! $permissionId) {
                    continue;
                }

                DB::table('site_role_permissions')->updateOrInsert(
                    ['site_id' => $siteId, 'role_id' => $roleId, 'permission_id' => $permissionId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        $moduleUsePermissionId = DB::table('site_permissions')
            ->where('code', 'module.use')
            ->value('id');

        $siteAdminRoleId = DB::table('site_roles')
            ->where('code', 'site_admin')
            ->whereNull('site_id')
            ->value('id');

        if ($moduleUsePermissionId && $siteAdminRoleId && $siteId) {
            DB::table('site_role_permissions')->updateOrInsert(
                ['site_id' => $siteId, 'role_id' => $siteAdminRoleId, 'permission_id' => $moduleUsePermissionId],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        DB::table('channels')->updateOrInsert(
            ['site_id' => $siteId, 'slug' => 'platform-notices'],
            [
                'parent_id' => null,
                'name' => '平台公告',
                'type' => 'list',
                'path' => '/platform-notices',
                'depth' => 0,
                'sort' => 0,
                'status' => 1,
                'is_nav' => 0,
                'created_by' => $superAdminId,
                'updated_by' => $superAdminId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $platformNoticeChannelId = DB::table('channels')
            ->where('site_id', $siteId)
            ->where('slug', 'platform-notices')
            ->value('id');

        if ($platformNoticeChannelId) {
            DB::table('contents')->updateOrInsert(
                ['site_id' => $siteId, 'channel_id' => $platformNoticeChannelId, 'slug' => 'platform-welcome-notice'],
                [
                    'type' => 'article',
                    'title' => '平台公告发布通道已启用',
                    'summary' => '这里用于统一发布平台更新、安全提醒与系统维护通知。',
                    'content' => '<p>平台公告栏目已启用。后续可在此统一发布平台更新、安全提醒、维护窗口与服务通知。</p>',
                    'status' => 'published',
                    'audit_status' => 'approved',
                    'published_at' => $now,
                    'created_by' => $superAdminId,
                    'updated_by' => $superAdminId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    protected function syncDefaultTemplateScaffold(string $templateRoot, string $legacyTemplateRoot): void
    {
        File::ensureDirectoryExists($templateRoot);

        if (! File::isDirectory($legacyTemplateRoot)) {
            return;
        }

        foreach (File::allFiles($legacyTemplateRoot) as $file) {
            $relativePath = ltrim(str_replace($legacyTemplateRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $targetPath = $templateRoot.DIRECTORY_SEPARATOR.$relativePath;

            if (File::exists($targetPath)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($targetPath));
            File::copy($file->getPathname(), $targetPath);
        }
    }
}
