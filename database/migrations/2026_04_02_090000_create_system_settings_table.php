<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('setting_key', 100)->unique();
            $table->longText('setting_value')->nullable();
            $table->boolean('autoload')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();

        DB::table('platform_permissions')->updateOrInsert(
            ['code' => 'system.setting.manage'],
            [
                'module' => 'system',
                'name' => '管理系统设置',
                'description' => '管理平台级系统设置、后台开关与资源库上传限制',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $permissionId = DB::table('platform_permissions')
            ->where('code', 'system.setting.manage')
            ->value('id');

        if ($permissionId) {
            $roleIds = DB::table('platform_roles')
                ->whereIn('code', ['super_admin', 'platform_admin'])
                ->pluck('id')
                ->all();

            foreach ($roleIds as $roleId) {
                DB::table('platform_role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        $defaults = [
            'system.name' => config('app.name'),
            'system.version' => '1.0.0',
            'admin.logo' => '/logo.jpg',
            'admin.favicon' => '/Favicon.ico',
            'attachment.allowed_extensions' => 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar',
            'attachment.max_size_mb' => '10',
            'attachment.image_max_size_mb' => '5',
            'attachment.image_max_width' => '4096',
            'attachment.image_max_height' => '4096',
            'attachment.image_auto_resize' => '0',
            'attachment.image_auto_compress' => '0',
            'attachment.image_quality' => '82',
            'admin.enabled' => '1',
            'admin.disabled_message' => '后台暂时关闭，请联系系统管理员。',
            'security.site_protection_enabled' => '1',
            'security.block_bad_path_enabled' => '1',
            'security.block_sql_injection_enabled' => '1',
            'security.block_xss_enabled' => '1',
            'security.block_path_traversal_enabled' => '1',
            'security.block_bad_upload_enabled' => '1',
            'security.rate_limit_enabled' => '1',
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '30',
            'security.rate_limit_sensitive_max_requests' => '10',
            'security.event_retention_limit' => '200',
            'security.stats_retention_days' => '180',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                [
                    'setting_value' => $value,
                    'autoload' => 1,
                    'updated_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('platform_permissions')
            ->where('code', 'system.setting.manage')
            ->value('id');

        if ($permissionId) {
            DB::table('platform_role_permissions')
                ->where('permission_id', $permissionId)
                ->delete();
        }

        DB::table('platform_permissions')
            ->where('code', 'system.setting.manage')
            ->delete();

        Schema::dropIfExists('system_settings');
    }
};
