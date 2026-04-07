<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
}
