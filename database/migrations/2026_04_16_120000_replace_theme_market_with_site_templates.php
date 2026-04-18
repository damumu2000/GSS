<?php

use App\Support\Site as SitePath;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            if (! Schema::hasColumn('sites', 'template_limit')) {
                $table->unsignedTinyInteger('template_limit')->default(1)->after('status');
            }

            if (! Schema::hasColumn('sites', 'active_site_template_id')) {
                $table->unsignedBigInteger('active_site_template_id')->nullable()->after('template_limit');
            }
        });

        if (! Schema::hasTable('site_templates')) {
            Schema::create('site_templates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('template_key', 50);
                $table->unsignedTinyInteger('status')->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['site_id', 'template_key']);
                $table->index(['site_id', 'status']);
            });
        }

        if (! Schema::hasTable('site_template_meta')) {
            Schema::create('site_template_meta', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_template_id')->constrained('site_templates')->cascadeOnDelete();
                $table->string('template_name', 120);
                $table->string('title', 120)->nullable();
                $table->timestamps();

                $table->unique(['site_template_id', 'template_name'], 'site_template_meta_unique');
            });
        }

        if (! Schema::hasTable('site_template_versions')) {
            Schema::create('site_template_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_template_id')->constrained('site_templates')->cascadeOnDelete();
                $table->string('template_name', 120);
                $table->string('source_type', 20);
                $table->longText('template_source')->nullable();
                $table->string('action', 40)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('consumed_at')->nullable();
                $table->boolean('is_favorite')->default(false);
                $table->timestamps();

                $table->index(['site_template_id', 'template_name'], 'site_template_versions_lookup_idx');
                $table->index(['site_template_id', 'consumed_at'], 'site_template_versions_consumed_idx');
                $table->index(['site_template_id', 'template_name', 'is_favorite'], 'site_template_versions_favorite_idx');
            });
        }

        $now = now();
        $sites = DB::table('sites')->orderBy('id')->get(['id', 'site_key', 'template_limit', 'active_site_template_id']);

        foreach ($sites as $site) {
            $templateId = DB::table('site_templates')
                ->where('site_id', (int) $site->id)
                ->orderBy('id')
                ->value('id');

            if (! $templateId) {
                $templateId = DB::table('site_templates')->insertGetId([
                    'site_id' => (int) $site->id,
                    'name' => '默认模板',
                    'template_key' => 'default',
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->ensureInitialSiteTemplate((string) $site->site_key, 'default');
            }

            DB::table('sites')
                ->where('id', (int) $site->id)
                ->update([
                    'template_limit' => max(1, min(50, (int) ($site->template_limit ?? 1))),
                    'active_site_template_id' => (int) ($site->active_site_template_id ?: $templateId),
                ]);
        }

        Schema::table('sites', function (Blueprint $table): void {
            if (Schema::hasColumn('sites', 'default_theme_id')) {
                $table->dropColumn('default_theme_id');
            }
        });

        Schema::dropIfExists('site_theme_template_versions');
        Schema::dropIfExists('site_theme_template_meta');
        Schema::dropIfExists('site_theme_bindings');
        Schema::dropIfExists('theme_versions');
        Schema::dropIfExists('themes');
    }

    public function down(): void
    {
        Schema::dropIfExists('site_template_versions');
        Schema::dropIfExists('site_template_meta');
        Schema::dropIfExists('site_templates');

        Schema::table('sites', function (Blueprint $table): void {
            if (! Schema::hasColumn('sites', 'default_theme_id')) {
                $table->unsignedBigInteger('default_theme_id')->nullable();
            }

            if (Schema::hasColumn('sites', 'active_site_template_id')) {
                $table->dropColumn('active_site_template_id');
            }

            if (Schema::hasColumn('sites', 'template_limit')) {
                $table->dropColumn('template_limit');
            }
        });
    }

    protected function ensureInitialSiteTemplate(string $siteKey, string $templateKey): void
    {
        $root = SitePath::themeOverrideRoot($siteKey, $templateKey);
        $legacyRoot = storage_path('app/theme_templates/site');

        File::ensureDirectoryExists($root);

        if (File::isDirectory($legacyRoot)) {
            foreach (File::allFiles($legacyRoot) as $file) {
                $relativePath = ltrim(str_replace($legacyRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR);
                $targetPath = $root.DIRECTORY_SEPARATOR.$relativePath;

                if (File::exists($targetPath)) {
                    continue;
                }

                File::ensureDirectoryExists(dirname($targetPath));
                File::copy($file->getPathname(), $targetPath);
            }
        }

        $homeTemplate = $root.DIRECTORY_SEPARATOR.'home.tpl';

        if (File::exists($homeTemplate)) {
            return;
        }

        File::put($homeTemplate, '站点模板还未启用，请先在后台模板管理中启用可访问模板。');
    }
};
