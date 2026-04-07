<?php

namespace App\Support;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Schema;

class DatabaseHealth
{
    public function hasPendingMigrations(): bool
    {
        if (! Schema::hasTable('migrations')) {
            return true;
        }

        /** @var Migrator $migrator */
        $migrator = app(Migrator::class);

        if (! $migrator->repositoryExists()) {
            return true;
        }

        $files = $migrator->getMigrationFiles([database_path('migrations')]);
        $ran = $migrator->getRepository()->getRan();

        return ! empty(array_diff(array_keys($files), $ran));
    }

    public function warningMessage(): string
    {
        return '系统检测到数据库结构尚未升级完成，请联系平台管理员执行迁移后再登录后台。';
    }
}
