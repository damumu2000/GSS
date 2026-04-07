<?php

namespace App\Support\SystemChecks;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DatabaseHealthCheck
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $items = [
            $this->connectionItem(),
            $this->migrationItem(),
            $this->requiredTablesItem(),
        ];

        return [
            'key' => 'database',
            'title' => '数据库健康',
            'status' => $this->overallStatus($items),
            'summary' => '检查数据库连接、迁移状态和关键表结构是否完整。',
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function connectionItem(): array
    {
        try {
            $pdo = DB::connection()->getPdo();

            return [
                'label' => '数据库连接',
                'status' => 'ok',
                'value' => sprintf(
                    '%s / %s',
                    (string) config('database.default'),
                    (string) DB::connection()->getDatabaseName()
                ),
                'message' => '数据库连接正常。',
                'suggestion' => '',
                'details' => $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION) ?: '',
            ];
        } catch (Throwable $exception) {
            return [
                'label' => '数据库连接',
                'status' => 'error',
                'value' => '连接失败',
                'message' => $exception->getMessage(),
                'suggestion' => '请先检查数据库账号、密码、主机和端口配置。',
                'details' => '',
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function migrationItem(): array
    {
        if (! Schema::hasTable('migrations')) {
            return [
                'label' => '迁移状态',
                'status' => 'error',
                'value' => '缺少 migrations 表',
                'message' => '无法确认数据库结构是否完整。',
                'suggestion' => '请执行安全迁移：php artisan migrate --force',
                'details' => '',
            ];
        }

        $executed = DB::table('migrations')->pluck('migration')->all();
        $expected = $this->expectedMigrationNames();
        $missing = array_values(array_diff($expected, $executed));

        if ($missing === []) {
            return [
                'label' => '迁移状态',
                'status' => 'ok',
                'value' => sprintf('%d / %d', count($executed), count($expected)),
                'message' => '主迁移链和模块迁移都已执行。',
                'suggestion' => '',
                'details' => '',
            ];
        }

        return [
            'label' => '迁移状态',
            'status' => 'warning',
            'value' => sprintf('缺少 %d 条', count($missing)),
            'message' => '检测到未执行的迁移。',
            'suggestion' => '请在部署窗口执行：php artisan migrate --force',
            'details' => implode('，', array_slice($missing, 0, 6)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function requiredTablesItem(): array
    {
        $required = [
            'users',
            'sites',
            'channels',
            'contents',
            'attachments',
            'system_settings',
            'modules',
            'site_module_bindings',
            'module_guestbook_messages',
            'module_payroll_batches',
        ];

        $missing = array_values(array_filter($required, static fn (string $table): bool => ! Schema::hasTable($table)));

        if ($missing === []) {
            return [
                'label' => '关键表结构',
                'status' => 'ok',
                'value' => sprintf('%d 张表', count($required)),
                'message' => '核心业务表结构完整。',
                'suggestion' => '',
                'details' => '',
            ];
        }

        return [
            'label' => '关键表结构',
            'status' => 'error',
            'value' => sprintf('缺少 %d 张表', count($missing)),
            'message' => '系统关键表不完整，部分功能可能无法正常运行。',
            'suggestion' => '请先完成缺失迁移，再继续商用部署。',
            'details' => implode('，', $missing),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function expectedMigrationNames(): array
    {
        $paths = [
            base_path('database/migrations'),
            app_path('Modules/Guestbook/Database/Migrations'),
            app_path('Modules/Payroll/Database/Migrations'),
        ];

        $names = [];

        foreach ($paths as $path) {
            if (! File::isDirectory($path)) {
                continue;
            }

            foreach (File::files($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $names[] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            }
        }

        sort($names);

        return array_values(array_unique($names));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function overallStatus(array $items): string
    {
        $priority = ['ok' => 0, 'warning' => 1, 'error' => 2];
        $max = 0;

        foreach ($items as $item) {
            $max = max($max, $priority[$item['status']] ?? 0);
        }

        return array_search($max, $priority, true) ?: 'ok';
    }
}
