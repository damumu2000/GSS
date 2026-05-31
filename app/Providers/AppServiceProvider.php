<?php

namespace App\Providers;

use App\Support\PlatformMailSettings;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->guardDestructiveDatabaseResetCommands();

        if ($this->runningInQueueWorkerConsole()) {
            PlatformMailSettings::recordQueueWorkerHeartbeat();
        }

        Queue::looping(static function (): void {
            PlatformMailSettings::recordQueueWorkerHeartbeat();
        });

        $modulesRoot = app_path('Modules');

        if (! File::isDirectory($modulesRoot)) {
            return;
        }

        foreach (File::directories($modulesRoot) as $modulePath) {
            $moduleName = basename($modulePath);
            $viewPath = $modulePath.'/Views';
            $migrationPath = $modulePath.'/Database/Migrations';
            $namespace = Str::snake($moduleName);

            if (File::isDirectory($viewPath)) {
                $this->loadViewsFrom($viewPath, $namespace);
            }

            if (File::isDirectory($migrationPath)) {
                $this->loadMigrationsFrom($migrationPath);
            }
        }
    }

    protected function runningInQueueWorkerConsole(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        $argv = $_SERVER['argv'] ?? [];
        if (! is_array($argv) || $argv === []) {
            return false;
        }

        $commandLine = implode(' ', array_map(static fn ($value): string => (string) $value, $argv));

        return str_contains($commandLine, 'queue:work')
            || str_contains($commandLine, 'queue:listen');
    }

    protected function guardDestructiveDatabaseResetCommands(): void
    {
        if (! app()->runningInConsole() || app()->environment('testing')) {
            return;
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            $command = (string) $event->command;
            $blockedCommands = [
                'db:wipe',
                'migrate:fresh',
                'migrate:reset',
                'migrate:refresh',
            ];

            if (! in_array($command, $blockedCommands, true)) {
                return;
            }

            $connection = (string) config('database.default');
            $database = (string) config("database.connections.{$connection}.database", '');

            if ($connection !== 'sqlite' || $database !== ':memory:') {
                throw new RuntimeException(sprintf(
                    'Blocked destructive database command [%s] on [%s:%s]. Use non-destructive migrations only.',
                    $command,
                    $connection,
                    $database
                ));
            }
        });
    }
}
