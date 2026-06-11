<?php

namespace App\Support;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DatabaseHealth
{
    protected const CACHE_KEY = 'database_health:pending_migrations';

    protected const CACHE_SECONDS = 15;

    public function hasPendingMigrations(): bool
    {
        return (bool) Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function (): bool {
            return $this->checkPendingMigrations();
        });
    }

    public function forgetPendingMigrationsCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected function checkPendingMigrations(): bool
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
