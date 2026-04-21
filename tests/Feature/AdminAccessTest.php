<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $temporaryPayrollUploads = [];
    protected array $temporaryFilesystemBackups = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryPayrollUploads as $path) {
            if (is_string($path) && $path !== '' && File::exists($path)) {
                File::delete($path);
            }
        }

        $this->temporaryPayrollUploads = [];

        foreach ($this->temporaryFilesystemBackups as $backup) {
            if (($backup['type'] ?? '') === 'file') {
                if (($backup['original_exists'] ?? false) && isset($backup['backup_path']) && File::exists($backup['backup_path'])) {
                    File::ensureDirectoryExists(dirname($backup['path']));
                    File::copy($backup['backup_path'], $backup['path']);
                } elseif (! empty($backup['path']) && File::exists($backup['path'])) {
                    File::delete($backup['path']);
                }

                if (isset($backup['backup_path']) && File::exists($backup['backup_path'])) {
                    File::delete($backup['backup_path']);
                }
            }
        }

        $this->temporaryFilesystemBackups = [];

        parent::tearDown();
    }

    protected function backupFileForTest(string $path): void
    {
        $backupPath = storage_path('framework/testing-backup-'.md5($path).'-'.uniqid().'.bak');
        File::ensureDirectoryExists(dirname($backupPath));

        $originalExists = File::exists($path);

        if ($originalExists) {
            File::copy($path, $backupPath);
        }

        $this->temporaryFilesystemBackups[] = [
            'type' => 'file',
            'path' => $path,
            'backup_path' => $backupPath,
            'original_exists' => $originalExists,
        ];
    }

    protected function superAdmin(): User
    {
        return User::query()->findOrFail((int) config('cms.super_admin_user_id', 1));
    }

    protected function loginCaptcha(): string
    {
        $response = $this->get(route('login.captcha'));
        $content = (string) $response->getContent();

        if (preg_match('/<text[^>]*>([A-Z0-9]{4})<\/text>/', $content, $matches) === 1) {
            return $matches[1];
        }

        $captcha = session('auth.login.captcha', '');

        return is_string($captcha) ? $captcha : '';
    }

    protected function loginThrottleKeyForCurrentDevice(string $username): string
    {
        $this->get(route('login'))->assertOk();

        return 'auth-login:'.sha1((string) session('auth.login.device').'|127.0.0.1|'.mb_strtolower(trim($username)));
    }

    protected function siteSecurityRateKeyForPath(string $path): string
    {
        $siteId = (int) DB::table('sites')
            ->where('status', 1)
            ->orderBy('id')
            ->value('id');

        return 'site-security-rate:'.$siteId.':'.sha1('127.0.0.1|'.$path);
    }

    protected function disableSiteSecurityRateLimit(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'security.rate_limit_enabled'],
            ['setting_value' => '0'],
        );
    }

    public function test_login_page_returns_security_headers(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_login_page_uses_hsts_when_request_is_forwarded_as_https(): void
    {
        $this->seed(DatabaseSeeder::class);

        $defaultSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_domains')->updateOrInsert(
            ['site_id' => $defaultSiteId, 'domain' => 'www.guanshanshan.cn'],
            [
                'is_primary' => 0,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
            'HTTP_X_FORWARDED_HOST' => 'www.guanshanshan.cn',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('/login')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_platform_admin_can_open_platform_dashboard_and_logs(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.logs.index'))
            ->assertOk();
    }

    public function test_platform_dashboard_reads_official_notices_from_main_site_only(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $otherSiteId = $this->createAdditionalSite('platform-remote-site', '平台远程站点');
        $noticeChannelId = (int) DB::table('channels')
            ->where('site_id', 1)
            ->where('slug', 'platform-notices')
            ->value('id');

        $platformNoticeId = (int) DB::table('contents')->insertGetId([
            'site_id' => 1,
            'channel_id' => $noticeChannelId,
            'type' => 'article',
            'title' => '平台统一更新公告',
            'status' => 'published',
            'audit_status' => 'approved',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('contents')->insert([
            [
                'site_id' => 1,
                'channel_id' => null,
                'type' => 'article',
                'title' => '主网站普通新闻',
                'status' => 'published',
                'audit_status' => 'approved',
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $otherSiteId,
                'channel_id' => null,
                'type' => 'article',
                'title' => '远程站点无关公告',
                'status' => 'published',
                'audit_status' => 'approved',
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('官闪闪公告栏')
            ->assertSee('平台统一更新公告')
            ->assertSee(route('site.article', ['id' => $platformNoticeId, 'site' => 'site']), false)
            ->assertViewHas('platformNotices', function ($items): bool {
                $titles = collect($items)->pluck('title')->all();

                return in_array('平台统一更新公告', $titles, true)
                    && ! in_array('主网站普通新闻', $titles, true)
                    && ! in_array('远程站点无关公告', $titles, true);
            });
    }

    public function test_platform_dashboard_recent_articles_aggregate_across_sites_with_site_prefix(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $otherSiteId = $this->createAdditionalSite('platform-recent-demo', '平台远程站点');

        DB::table('contents')->insert([
            [
                'site_id' => 1,
                'channel_id' => null,
                'type' => 'article',
                'title' => '主站最新文章',
                'status' => 'published',
                'audit_status' => 'approved',
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'published_at' => now()->subMinutes(2),
                'created_at' => now()->subMinutes(2),
                'updated_at' => now()->subMinutes(2),
            ],
            [
                'site_id' => $otherSiteId,
                'channel_id' => null,
                'type' => 'article',
                'title' => '远程站点文章',
                'status' => 'published',
                'audit_status' => 'approved',
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'published_at' => now()->subMinute(),
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
        ]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('示例学校 · 主站最新文章')
            ->assertSee('平台远程站点 · 远程站点文章');
    }

    public function test_site_dashboard_platform_notices_respect_top_and_sort_order(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('dashboard-notice-order-operator', true, 'site_admin');
        $noticeChannelId = (int) DB::table('channels')
            ->where('site_id', 1)
            ->where('slug', 'platform-notices')
            ->value('id');

        DB::table('contents')
            ->where('site_id', 1)
            ->where('channel_id', $noticeChannelId)
            ->delete();

        $olderTopId = (int) DB::table('contents')->insertGetId([
            'site_id' => 1,
            'channel_id' => $noticeChannelId,
            'type' => 'article',
            'title' => '置顶低排序公告',
            'status' => 'published',
            'audit_status' => 'approved',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'is_top' => 1,
            'sort' => 10,
            'published_at' => now()->subDays(3),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        $higherTopId = (int) DB::table('contents')->insertGetId([
            'site_id' => 1,
            'channel_id' => $noticeChannelId,
            'type' => 'article',
            'title' => '置顶高排序公告',
            'status' => 'published',
            'audit_status' => 'approved',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'is_top' => 1,
            'sort' => 99,
            'published_at' => now()->subDays(5),
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $latestNormalId = (int) DB::table('contents')->insertGetId([
            'site_id' => 1,
            'channel_id' => $noticeChannelId,
            'type' => 'article',
            'title' => '普通最新公告',
            'status' => 'published',
            'audit_status' => 'approved',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'is_top' => 0,
            'sort' => 0,
            'published_at' => now()->subHour(),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($operator)
            ->get(route('admin.site-dashboard'))
            ->assertOk();

        $response->assertViewHas('platformNotices', function ($items) use ($higherTopId, $olderTopId, $latestNormalId): bool {
            $ids = collect($items)->pluck('id')->take(3)->values()->all();

            return $ids === [$higherTopId, $olderTopId, $latestNormalId];
        });
    }

    public function test_platform_dashboard_still_opens_when_notice_channel_is_missing(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();

        DB::table('channels')
            ->where('site_id', 1)
            ->where('slug', 'platform-notices')
            ->delete();

        $this->assertNull(
            DB::table('channels')
                ->where('site_id', 1)
                ->where('slug', 'platform-notices')
                ->value('id')
        );

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $remainingChannelId = DB::table('channels')
            ->where('site_id', 1)
            ->where('slug', 'platform-notices')
            ->value('id');

        $this->assertNull($remainingChannelId);
    }

    public function test_platform_dashboard_hides_switcher_when_only_one_site_exists(): void
    {
        $this->seed(DatabaseSeeder::class);

        DB::table('sites')->where('site_key', 'demo-school-2')->delete();

        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('切换站点主控');
    }

    public function test_platform_admin_can_open_system_settings_page(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get(route('admin.platform.settings.index'))
            ->assertOk()
            ->assertSee('系统设置')
            ->assertSee('资源库上传设置')
            ->assertSee('后台开关');
    }

    public function test_platform_admin_can_open_system_checks_page(): void
    {
        $this->seed(DatabaseSeeder::class);

        Cache::flush();
        Http::fake([
            'registry.npmjs.org/*' => Http::response(['version' => '1.15.7']),
        ]);

        $manifest = json_decode(File::get(public_path('vendor/vendor-assets.json')), true, 512, JSON_THROW_ON_ERROR);
        $currentVersion = data_get($manifest, 'sortablejs.version', 'unknown');

        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get(route('admin.platform.system-checks.index'))
            ->assertOk()
            ->assertSee('系统检查')
            ->assertSee('数据库健康')
            ->assertSee('运行环境检查')
            ->assertSee('部署状态检查')
            ->assertSee('静态资源与安全检查')
            ->assertSee('SORTABLEJS')
            ->assertSee($currentVersion)
            ->assertSee('1.15.7');
    }

    public function test_platform_admin_can_open_system_checks_page_with_dedicated_permission_only(): void
    {
        $this->seed(DatabaseSeeder::class);

        Cache::flush();
        Http::fake([
            'registry.npmjs.org/*' => Http::response(['version' => '1.15.7']),
        ]);

        $user = $this->createPlatformIdentity('system-check-viewer', 'platform_admin');
        $platformAdminRoleId = (int) DB::table('platform_roles')->where('code', 'platform_admin')->value('id');
        $systemSettingPermissionId = (int) DB::table('platform_permissions')->where('code', 'system.setting.manage')->value('id');
        $systemCheckPermissionId = (int) DB::table('platform_permissions')->where('code', 'system.check.view')->value('id');

        DB::table('platform_role_permissions')
            ->where('role_id', $platformAdminRoleId)
            ->where('permission_id', $systemSettingPermissionId)
            ->delete();

        DB::table('platform_role_permissions')->updateOrInsert(
            ['role_id' => $platformAdminRoleId, 'permission_id' => $systemCheckPermissionId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $this->actingAs($user)
            ->get(route('admin.platform.system-checks.index'))
            ->assertOk()
            ->assertSee('系统检查');
    }

    public function test_platform_admin_can_upgrade_static_vendor_from_system_checks_page(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manifestPath = public_path('vendor/vendor-assets.json');
        $assetPath = public_path('vendor/sortablejs/Sortable.min.js');
        $this->backupFileForTest($manifestPath);
        $this->backupFileForTest($assetPath);

        File::ensureDirectoryExists(dirname($assetPath));
        File::put($assetPath, 'window.SortableVersion="1.15.3";');
        File::put($manifestPath, json_encode([
            'sortablejs' => [
                'package' => 'sortablejs',
                'version' => '1.15.3',
                'file' => 'public/vendor/sortablejs/Sortable.min.js',
                'source' => 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js',
                'sha256' => hash_file('sha256', $assetPath),
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        Cache::flush();
        Http::fake([
            'https://registry.npmjs.org/*' => Http::response(['version' => '1.15.7']),
            'https://cdn.jsdelivr.net/npm/sortablejs@1.15.7/Sortable.min.js' => Http::response('window.SortableVersion="1.15.7";'),
        ]);

        $user = $this->superAdmin();

        $this->actingAs($user)
            ->post(route('admin.platform.system-checks.static-vendors.upgrade', ['asset' => 'sortablejs']))
            ->assertRedirect(route('admin.platform.system-checks.index'))
            ->assertSessionHas('status', 'SORTABLEJS 已升级到 1.15.7。');

        $manifest = json_decode((string) File::get($manifestPath), true);

        $this->assertSame('1.15.7', $manifest['sortablejs']['version'] ?? null);
        $this->assertSame(
            'https://cdn.jsdelivr.net/npm/sortablejs@1.15.7/Sortable.min.js',
            $manifest['sortablejs']['source'] ?? null
        );
        $this->assertStringContainsString('1.15.7', (string) File::get($assetPath));
    }

    public function test_super_admin_can_clear_platform_app_cache_from_system_checks_page(): void
    {
        $this->seed(DatabaseSeeder::class);

        Cache::put('admin-access-test-cache-key', 'cached-value', 600);
        $this->assertSame('cached-value', Cache::get('admin-access-test-cache-key'));

        $user = $this->superAdmin();

        $this->actingAs($user)
            ->post(route('admin.platform.system-checks.cache.clear', ['action' => 'app']))
            ->assertRedirect(route('admin.platform.system-checks.index'))
            ->assertSessionHas('status', '应用缓存已清理。');

        $this->assertNull(Cache::get('admin-access-test-cache-key'));
    }

    public function test_non_super_platform_admin_cannot_clear_platform_cache(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->createPlatformIdentity('cache-maintainer', 'platform_admin');
        Cache::put('admin-access-test-cache-key', 'cached-value', 600);

        $this->actingAs($user)
            ->post(route('admin.platform.system-checks.cache.clear', ['action' => 'app']))
            ->assertRedirect(route('admin.platform.system-checks.index'))
            ->assertSessionHas('status', '只有总管理员可以执行缓存清理。');

        $this->assertSame('cached-value', Cache::get('admin-access-test-cache-key'));
    }

    public function test_platform_admin_can_open_database_management_pages(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get(route('admin.platform.database.index'))
            ->assertOk()
            ->assertSee('数据库管理')
            ->assertSee('users')
            ->assertSee('sites');

        $this->actingAs($user)
            ->get(route('admin.platform.database.show', ['table' => 'users', 'tab' => 'data']))
            ->assertOk()
            ->assertSee('数据预览')
            ->assertSee('******');
    }

    public function test_platform_admin_can_open_module_management_pages(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get(route('admin.platform.modules.index'))
            ->assertOk()
            ->assertSee('模块管理')
            ->assertSee('留言板');

        $this->actingAs($user)
            ->get(route('admin.platform.modules.show', 'guestbook'))
            ->assertOk()
            ->assertSee('留言板')
            ->assertSee('guestbook');
    }

    public function test_platform_admin_can_toggle_module_status_and_bind_module_to_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();
        $initialStatus = (int) DB::table('modules')->where('code', 'guestbook')->value('status');

        $this->actingAs($user)
            ->post(route('admin.platform.modules.toggle', 'guestbook'))
            ->assertRedirect(route('admin.platform.modules.show', 'guestbook'))
            ->assertSessionHas('status', $initialStatus === 1 ? '模块已禁用。' : '模块已启用。');

        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');

        $this->assertSame($initialStatus === 1 ? 0 : 1, (int) DB::table('modules')->where('id', $moduleId)->value('status'));

        $this->actingAs($user)
            ->post(route('admin.platform.sites.update', $siteId), [
                'name' => '示例学校',
                'site_key' => 'site',
                'status' => '1',
                'domains' => 'site.test',
                'contact_phone' => '010-12345678',
                'contact_email' => 'school@openai.com',
                'address' => '示例地址 1 号',
                'attachment_storage_limit_mb' => 512,
                'theme_ids' => [],
                'module_ids' => [$moduleId],
                'seo_title' => '示例学校官网',
                'seo_keywords' => '示例学校,校园',
                'seo_description' => '示例学校官网描述',
                'opened_at' => now()->format('Y-m-d'),
                'expires_at' => '',
                'remark' => '站点备注',
                'site_admin_ids' => [],
            ])
            ->assertRedirect(route('admin.platform.sites.edit', $siteId))
            ->assertSessionHas('status', '站点信息已更新。');

        $this->assertDatabaseHas('site_module_bindings', [
            'site_id' => $siteId,
            'module_id' => $moduleId,
        ]);

        $siteAdmin = $this->createSiteOperator('module-site-admin', true, 'site_admin');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-modules.show', 'guestbook'))
            ->assertOk()
            ->assertSee('留言板');
    }

    public function test_module_manager_read_path_does_not_write_module_rows(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get(route('admin.platform.modules.index'))
            ->assertOk();

        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        $frozenUpdatedAt = now()->subDay();

        DB::table('modules')->where('id', $moduleId)->update([
            'updated_at' => $frozenUpdatedAt,
        ]);

        app(\App\Support\Modules\ModuleManager::class)->all();

        $this->assertSame(
            $frozenUpdatedAt->format('Y-m-d H:i:s'),
            (string) DB::table('modules')->where('id', $moduleId)->value('updated_at'),
        );
    }

    public function test_disabled_bound_module_is_preserved_when_updating_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($user)
            ->post(route('admin.platform.modules.toggle', 'guestbook'))
            ->assertRedirect();

        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');

        $payload = [
            'name' => '示例学校',
            'site_key' => 'site',
            'status' => '1',
            'domains' => 'site.test',
            'contact_phone' => '010-12345678',
            'contact_email' => 'school@openai.com',
            'address' => '示例地址 1 号',
            'attachment_storage_limit_mb' => 512,
            'theme_ids' => [],
            'module_ids' => [$moduleId],
            'seo_title' => '示例学校官网',
            'seo_keywords' => '示例学校,校园',
            'seo_description' => '示例学校官网描述',
            'opened_at' => now()->format('Y-m-d'),
            'expires_at' => '',
            'remark' => '站点备注',
            'site_admin_ids' => [],
        ];

        $this->actingAs($user)
            ->post(route('admin.platform.sites.update', $siteId), $payload)
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.platform.modules.toggle', 'guestbook'))
            ->assertRedirect();

        $this->assertSame(0, (int) DB::table('modules')->where('id', $moduleId)->value('status'));

        $this->actingAs($user)
            ->get(route('admin.platform.sites.edit', $siteId))
            ->assertOk()
            ->assertSee('平台已禁用，当前绑定保留');

        $this->actingAs($user)
            ->post(route('admin.platform.sites.update', $siteId), $payload)
            ->assertRedirect(route('admin.platform.sites.edit', $siteId));

        $this->assertDatabaseHas('site_module_bindings', [
            'site_id' => $siteId,
            'module_id' => $moduleId,
        ]);
    }

    public function test_site_module_pages_require_module_specific_permission(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($user)
            ->get(route('admin.platform.modules.index'))
            ->assertOk();

        $this->assertDatabaseHas('site_permissions', [
            'code' => 'guestbook.view',
        ]);

        $this->actingAs($user)
            ->post(route('admin.platform.modules.toggle', 'guestbook'))
            ->assertRedirect();

        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');

        $this->actingAs($user)
            ->post(route('admin.platform.sites.update', $siteId), [
                'name' => '示例学校',
                'site_key' => 'site',
                'status' => '1',
                'domains' => 'site.test',
                'contact_phone' => '010-12345678',
                'contact_email' => 'school@openai.com',
                'address' => '示例地址 1 号',
                'attachment_storage_limit_mb' => 512,
                'theme_ids' => [],
                'module_ids' => [$moduleId],
                'seo_title' => '示例学校官网',
                'seo_keywords' => '示例学校,校园',
                'seo_description' => '示例学校官网描述',
                'opened_at' => now()->format('Y-m-d'),
                'expires_at' => '',
                'remark' => '站点备注',
                'site_admin_ids' => [],
            ])
            ->assertRedirect();

        $siteAdmin = $this->createSiteOperator('guestbook-auditor', true, 'site_admin');
        $siteAdminRoleId = (int) DB::table('site_roles')->where('code', 'site_admin')->whereNull('site_id')->value('id');
        $guestbookViewPermissionId = (int) DB::table('site_permissions')->where('code', 'guestbook.view')->value('id');

        DB::table('site_role_permissions')
            ->where('site_id', $siteId)
            ->where('role_id', $siteAdminRoleId)
            ->where('permission_id', $guestbookViewPermissionId)
            ->delete();

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-dashboard'))
            ->assertOk()
            ->assertDontSee('留言板');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-modules.show', 'guestbook'))
            ->assertForbidden();
    }

    public function test_site_role_update_auto_adds_module_use_when_module_permission_selected(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $editorRoleId = (int) DB::table('site_roles')->where('code', 'editor')->whereNull('site_id')->value('id');
        $guestbookViewPermissionId = (int) DB::table('site_permissions')->where('code', 'guestbook.view')->value('id');
        $moduleUsePermissionId = (int) DB::table('site_permissions')->where('code', 'module.use')->value('id');

        DB::table('site_role_permissions')
            ->where('site_id', $siteId)
            ->where('role_id', $editorRoleId)
            ->where('permission_id', $moduleUsePermissionId)
            ->delete();

        $this->actingAs($user)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.site-roles.update', $editorRoleId), [
                'permission_ids' => [$guestbookViewPermissionId],
            ])
            ->assertRedirect(route('admin.site-roles.edit', $editorRoleId));

        $this->assertDatabaseHas('site_role_permissions', [
            'site_id' => $siteId,
            'role_id' => $editorRoleId,
            'permission_id' => $guestbookViewPermissionId,
        ]);
        $this->assertDatabaseHas('site_role_permissions', [
            'site_id' => $siteId,
            'role_id' => $editorRoleId,
            'permission_id' => $moduleUsePermissionId,
        ]);
    }

    public function test_site_role_edit_displays_module_permissions_in_dedicated_group(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $editorRoleId = (int) DB::table('site_roles')->where('code', 'editor')->whereNull('site_id')->value('id');

        $this->actingAs($user)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-roles.edit', $editorRoleId))
            ->assertOk()
            ->assertSee('功能模块权限配置')
            ->assertSee('留言板')
            ->assertDontSee('使用功能模块');
    }

    public function test_platform_module_permissions_are_granted_to_default_platform_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $modulePath = app_path('Modules/PlatformOpsTest');
        File::ensureDirectoryExists($modulePath);
        File::put($modulePath.'/module.json', json_encode([
            'name' => '平台巡检',
            'code' => 'platform_ops_test',
            'version' => '1.0.0',
            'scope' => 'platform',
            'permissions' => ['platform_ops_test.manage'],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        try {
            app(\App\Support\Modules\ModuleManager::class)->synchronize();

            $permissionId = (int) DB::table('platform_permissions')
                ->where('code', 'platform_ops_test.manage')
                ->value('id');

            $superAdminRoleId = (int) DB::table('platform_roles')->where('code', 'super_admin')->value('id');
            $platformAdminRoleId = (int) DB::table('platform_roles')->where('code', 'platform_admin')->value('id');

            $this->assertNotSame(0, $permissionId);
            $this->assertDatabaseHas('platform_role_permissions', [
                'role_id' => $superAdminRoleId,
                'permission_id' => $permissionId,
            ]);
            $this->assertDatabaseHas('platform_role_permissions', [
                'role_id' => $platformAdminRoleId,
                'permission_id' => $permissionId,
            ]);
        } finally {
            File::deleteDirectory($modulePath);
        }
    }

    public function test_missing_module_manifest_still_appears_in_platform_module_management(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $modulePath = app_path('Modules/MissingModuleTest');
        File::ensureDirectoryExists($modulePath);
        File::put($modulePath.'/module.json', json_encode([
            'name' => '缺失文件模块',
            'code' => 'missing_module_test',
            'version' => '1.0.0',
            'scope' => 'site',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        app(\App\Support\Modules\ModuleManager::class)->synchronize();
        File::deleteDirectory($modulePath);

        $this->actingAs($user)
            ->get(route('admin.platform.modules.index'))
            ->assertOk()
            ->assertSee('缺失文件模块')
            ->assertSee('文件缺失');

        $this->actingAs($user)
            ->get(route('admin.platform.modules.show', 'missing_module_test'))
            ->assertOk()
            ->assertSee('文件缺失');
    }

    public function test_invalid_module_manifest_still_appears_in_platform_module_management(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $modulePath = app_path('Modules/InvalidManifestTest');
        File::ensureDirectoryExists($modulePath);
        File::put($modulePath.'/module.json', '{"name":"损坏模块","code":"Invalid-Module",');

        try {
            $this->actingAs($user)
                ->get(route('admin.platform.modules.index'))
                ->assertOk()
                ->assertSee('Invalid Manifest Test')
                ->assertSee('配置异常');

            $this->actingAs($user)
                ->get(route('admin.platform.modules.show', 'invalid_manifest_invalid_manifest_test'))
                ->assertOk()
                ->assertSee('配置异常')
                ->assertSee('module.json 解析失败')
                ->assertDontSee('启用模块')
                ->assertDontSee('禁用模块');

            $this->actingAs($user)
                ->post(route('admin.platform.modules.toggle', 'invalid_manifest_invalid_manifest_test'))
                ->assertRedirect(route('admin.platform.modules.show', 'invalid_manifest_invalid_manifest_test'))
                ->assertSessionHas('status', '当前模块文件异常，暂不支持切换启用状态，请先修复模块文件。');
        } finally {
            File::deleteDirectory($modulePath);
        }
    }

    public function test_guestbook_frontend_submission_creates_pending_message(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );
        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.guestbook.captcha_enabled'],
            ['setting_value' => '0', 'autoload' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->post(route('site.guestbook.store', ['site' => 'site']), [
            'name' => '张老师',
            'phone' => '13800138000',
            'content' => '这里是前台提交的留言内容，希望尽快收到回复。',
        ])->assertRedirect(route('site.guestbook.index', ['site' => 'site']));

        $this->assertDatabaseHas('module_guestbook_messages', [
            'site_id' => $siteId,
            'display_no' => 1,
            'name' => '张老师',
            'status' => 'pending',
        ]);
    }

    public function test_guestbook_frontend_failed_submissions_are_rate_limited(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $rateLimitKey = 'guestbook-submit:'.$siteId.':'.sha1('127.0.0.1');
        for ($i = 0; $i < 20; $i++) {
            RateLimiter::hit($rateLimitKey, 30);
        }

        $this->from(route('site.guestbook.create', ['site' => 'site']))
            ->post(route('site.guestbook.store', ['site' => 'site']), [
                'name' => '张老师',
                'phone' => '13800138000',
                'content' => '这是第二十一次重复提交，会被频率限制拦截。',
                'captcha' => 'BBBB',
            ])
            ->assertRedirect(route('site.guestbook.create', ['site' => 'site']))
            ->assertSessionHasErrors(['form']);
    }

    public function test_guestbook_frontend_list_only_shows_public_messages(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::table('module_guestbook_messages')->insert([
            [
                'site_id' => $siteId,
                'display_no' => 1,
                'name' => '公开留言人',
                'phone' => '13800138000',
                'content' => '公开留言正文',
                'status' => 'replied',
                'is_read' => 1,
                'read_at' => now(),
                'reply_content' => '公开回复',
                'replied_at' => now(),
                'replied_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $siteId,
                'display_no' => 2,
                'name' => '隐藏留言人',
                'phone' => '13800138001',
                'content' => '隐藏留言正文',
                'status' => 'pending',
                'is_read' => 0,
                'read_at' => null,
                'reply_content' => null,
                'replied_at' => null,
                'replied_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->get(route('site.guestbook.index', ['site' => 'site']))
            ->assertOk()
            ->assertSee('公开留言正文')
            ->assertSee('公开回复')
            ->assertDontSee('隐藏留言正文');
    }

    public function test_guestbook_frontend_list_shows_pending_message_when_show_after_reply_is_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.guestbook.show_after_reply'],
            ['setting_value' => '0', 'autoload' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.guestbook.show_name'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        DB::table('module_guestbook_messages')->insert([
            'site_id' => $siteId,
            'display_no' => 3,
            'name' => '直接展示留言',
            'phone' => '13800138003',
            'content' => '关闭回复后才展示后，这条未回复留言也应该在前台显示。',
            'status' => 'pending',
            'is_read' => 0,
            'reply_content' => null,
            'replied_at' => null,
            'replied_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('site.guestbook.index', ['site' => 'site']))
            ->assertOk()
            ->assertSee('直接展示留言');
    }

    public function test_guestbook_frontend_masks_name_when_full_name_display_is_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.guestbook.show_name'],
            ['setting_value' => '0', 'autoload' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.guestbook.show_after_reply'],
            ['setting_value' => '0', 'autoload' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        DB::table('module_guestbook_messages')->insert([
            'site_id' => $siteId,
            'display_no' => 15,
            'name' => '王鹏鹏',
            'phone' => '13800138015',
            'content' => '用于测试前台姓名脱敏展示。',
            'status' => 'pending',
            'is_read' => 0,
            'reply_content' => null,
            'replied_at' => null,
            'replied_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('site.guestbook.index', ['site' => 'site']))
            ->assertOk()
            ->assertSee('王***')
            ->assertDontSee('王鹏鹏');
    }

    public function test_guestbook_frontend_shows_friendly_page_when_guestbook_is_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.guestbook.enabled'],
            ['setting_value' => '0', 'autoload' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->get(route('site.guestbook.index', ['site' => 'site']))
            ->assertOk()
            ->assertSee('暂未开放')
            ->assertSee('暂时无法查看或提交留言');

        $this->get(route('site.guestbook.create', ['site' => 'site']))
            ->assertOk()
            ->assertSee('暂未开放');
    }

    public function test_guestbook_menu_shows_pending_count_badge(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.guestbook.name'],
            ['setting_value' => '留言板', 'autoload' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        DB::table('module_guestbook_messages')->insert([
            [
                'site_id' => $siteId,
                'display_no' => 90101,
                'name' => '待回复留言一',
                'phone' => '13800138011',
                'content' => '第一条未回复留言',
                'status' => 'pending',
                'is_read' => 0,
                'reply_content' => null,
                'replied_at' => null,
                'replied_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $siteId,
                'display_no' => 90102,
                'name' => '待回复留言二',
                'phone' => '13800138012',
                'content' => '第二条未回复留言',
                'status' => 'pending',
                'is_read' => 0,
                'reply_content' => null,
                'replied_at' => null,
                'replied_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $siteId,
                'display_no' => 90103,
                'name' => '已回复留言',
                'phone' => '13800138013',
                'content' => '这条不应计入角标',
                'status' => 'replied',
                'is_read' => 1,
                'reply_content' => '已回复',
                'replied_at' => now(),
                'replied_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $operator = $this->createCustomSiteOperator('guestbook-menu-badge', $siteId, ['guestbook.view']);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-dashboard'))
            ->assertOk()
            ->assertSee('留言板')
            ->assertSee('+2');
    }

    public function test_guestbook_frontend_detail_page_only_shows_public_message(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::table('module_guestbook_messages')->insert([
            [
                'site_id' => $siteId,
                'display_no' => 8,
                'name' => '公开来信人',
                'phone' => '13800138008',
                'content' => '这是一条公开留言的完整正文内容。',
                'status' => 'replied',
                'is_read' => 1,
                'read_at' => now(),
                'reply_content' => '这是后台给出的公开回复内容。',
                'replied_at' => now(),
                'replied_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $siteId,
                'display_no' => 9,
                'name' => '隐藏来信人',
                'phone' => '13800138009',
                'content' => '这是一条隐藏留言的完整正文内容。',
                'status' => 'pending',
                'is_read' => 0,
                'read_at' => null,
                'reply_content' => null,
                'replied_at' => null,
                'replied_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->get(route('site.guestbook.show', ['site' => 'site', 'displayNo' => 8]))
            ->assertOk()
            ->assertSee('00008')
            ->assertSee('这是一条公开留言的完整正文内容。')
            ->assertSee('这是后台给出的公开回复内容。');

        $this->get(route('site.guestbook.show', ['site' => 'site', 'displayNo' => 9]))
            ->assertNotFound();
    }

    public function test_site_admin_can_update_guestbook_settings_and_reply_message(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $messageId = DB::table('module_guestbook_messages')->insertGetId([
            'site_id' => $siteId,
            'display_no' => 1,
            'name' => '李老师',
            'phone' => '13800138002',
            'content' => '请问本周是否开放校内参观？',
            'status' => 'pending',
            'is_read' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $siteAdmin = $this->createSiteOperator('guestbook-site-admin', true, 'site_admin');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.guestbook.settings.update'), [
                'enabled' => '1',
                'name' => '校长留言板',
                'notice' => '请文明留言，内容需真实准确。系统会自动生成留言编号，便于后续查询。',
                'theme' => 'default',
                'show_name' => '1',
                'show_after_reply' => '1',
                'captcha_enabled' => '1',
            ])
            ->assertRedirect(route('admin.guestbook.settings'));

        $this->assertSame('校长留言板', DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'module.guestbook.name')
            ->value('setting_value'));

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.guestbook.update', $messageId), [
                'content' => '这里是后台编辑后的留言正文内容，用于确认正文也可以被同步更新。',
                'reply_content' => '您好，本周五下午开放参观，请提前预约。',
            ])
            ->assertRedirect(route('admin.guestbook.show', $messageId));

        $this->assertDatabaseHas('module_guestbook_messages', [
            'id' => $messageId,
            'content' => '这里是后台编辑后的留言正文内容，用于确认正文也可以被同步更新。',
            'original_content' => '请问本周是否开放校内参观？',
            'status' => 'replied',
            'replied_by' => $siteAdmin->id,
        ]);
    }

    public function test_guestbook_settings_reject_external_notice_image(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $siteAdmin = $this->createSiteOperator('guestbook-notice-image-admin', true, 'site_admin');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.guestbook.settings'))
            ->post(route('admin.guestbook.settings.update'), [
                'enabled' => '1',
                'name' => '校长留言板',
                'notice' => '请文明留言，内容需真实准确。',
                'notice_image' => 'https://evil.example/notice.png',
                'theme' => 'default',
                'show_name' => '1',
                'show_after_reply' => '1',
                'captcha_enabled' => '1',
            ])
            ->assertRedirect(route('admin.guestbook.settings'))
            ->assertSessionHasErrors(['notice_image']);
    }

    public function test_guestbook_settings_library_only_shows_visible_images_and_rejects_inaccessible_notice_image(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $operator = $this->createCustomSiteOperator('guestbook-attachment-guard', $siteId, ['guestbook.setting']);
        $otherOperator = $this->createSiteOperator('guestbook-attachment-owner', true, 'editor');

        $this->setAttachmentSharing($siteId, false, $operator->id);

        $ownAttachmentId = $this->createSiteAttachment($siteId, $operator->id, 'guestbook-own-notice.jpg');
        $foreignAttachmentId = $this->createSiteAttachment($siteId, $otherOperator->id, 'guestbook-foreign-notice.jpg');
        $foreignAttachmentUrl = (string) DB::table('attachments')->where('id', $foreignAttachmentId)->value('url');

        $this->assertNotSame(0, $ownAttachmentId);
        $this->assertNotSame(0, $foreignAttachmentId);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.library-feed', [
                'mode' => 'picker',
                'context' => 'guestbook',
                'image_only' => 1,
            ]))
            ->assertOk()
            ->assertSee('guestbook-own-notice.jpg')
            ->assertDontSee('guestbook-foreign-notice.jpg');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.guestbook.settings'))
            ->post(route('admin.guestbook.settings.update'), [
                'enabled' => '1',
                'name' => '留言板附件权限测试',
                'notice' => '测试不可访问背景图是否会被拦截。',
                'notice_image' => $foreignAttachmentUrl,
                'theme' => 'default',
                'show_name' => '1',
                'show_after_reply' => '1',
                'captcha_enabled' => '1',
            ])
            ->assertRedirect(route('admin.guestbook.settings'))
            ->assertSessionHasErrors([
                'notice_image' => '发布须知背景图地址格式不正确，请重新选择资源库图片。',
            ]);
    }

    public function test_guestbook_settings_reject_inaccessible_notice_attachment_link(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $operator = $this->createCustomSiteOperator('guestbook-link-guard', $siteId, ['guestbook.setting']);
        $otherOperator = $this->createSiteOperator('guestbook-link-owner', true, 'editor');

        $this->setAttachmentSharing($siteId, false, $operator->id);

        $foreignAttachmentId = $this->createSiteAttachment($siteId, $otherOperator->id, 'guestbook-foreign-manual.pdf');
        $foreignAttachmentUrl = (string) DB::table('attachments')->where('id', $foreignAttachmentId)->value('url');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.guestbook.settings'))
            ->post(route('admin.guestbook.settings.update'), [
                'enabled' => '1',
                'name' => '留言板链接权限测试',
                'notice' => '<p>请先阅读<a href="'.$foreignAttachmentUrl.'" target="_blank">说明附件</a></p>',
                'notice_image' => '',
                'theme' => 'default',
                'show_name' => '1',
                'show_after_reply' => '1',
                'captcha_enabled' => '1',
            ])
            ->assertRedirect(route('admin.guestbook.settings'))
            ->assertSessionHasErrors([
                'notice' => '发布须知中包含不可访问的资源链接，请重新从可用资源中选择。',
            ]);
    }

    public function test_guestbook_reply_only_user_cannot_edit_original_message_content(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $roleId = (int) DB::table('site_roles')->insertGetId([
            'site_id' => $siteId,
            'name' => '留言回复员',
            'code' => 'guestbook_reply_only',
            'description' => '仅允许查看和回复留言',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionIds = DB::table('site_permissions')
            ->whereIn('code', ['guestbook.view', 'guestbook.reply'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($permissionIds as $permissionId) {
            DB::table('site_role_permissions')->insert([
                'site_id' => $siteId,
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $replyUser = User::query()->create([
            'username' => 'guestbook-reply-only',
            'name' => 'Guestbook Reply Only',
            'email' => 'guestbook-reply-only@example.com',
            'password' => 'ChangeMe123!',
            'status' => 1,
        ]);

        DB::table('site_user_roles')->insert([
            'site_id' => $siteId,
            'user_id' => $replyUser->id,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $messageId = DB::table('module_guestbook_messages')->insertGetId([
            'site_id' => $siteId,
            'display_no' => 21,
            'name' => '王老师',
            'phone' => '13800138021',
            'content' => '原始留言正文内容。',
            'status' => 'pending',
            'is_read' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($replyUser)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.guestbook.show', $messageId))
            ->post(route('admin.guestbook.update', $messageId), [
                'content' => '被越权修改后的留言正文。',
                'reply_content' => '这是允许提交的回复内容。',
            ])
            ->assertRedirect(route('admin.guestbook.show', $messageId))
            ->assertSessionHasErrors(['content']);

        $this->assertDatabaseHas('module_guestbook_messages', [
            'id' => $messageId,
            'content' => '原始留言正文内容。',
            'reply_content' => null,
            'status' => 'pending',
        ]);
    }

    public function test_guestbook_settings_disable_show_after_reply_makes_existing_pending_messages_public(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::table('module_guestbook_messages')->insert([
            'site_id' => $siteId,
            'display_no' => 11,
            'name' => '待公开留言',
            'phone' => '13800138011',
            'content' => '这是一条待公开留言。',
            'status' => 'pending',
            'is_read' => 0,
            'reply_content' => null,
            'replied_at' => null,
            'replied_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $siteAdmin = $this->createSiteOperator('guestbook-setting-admin', true, 'site_admin');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.guestbook.settings.update'), [
                'enabled' => '1',
                'name' => '校长留言板',
                'notice' => '请文明留言，内容需真实准确。系统会自动生成留言编号，便于后续查询。',
                'theme' => 'default',
                'show_name' => '1',
                'captcha_enabled' => '1',
            ])
            ->assertRedirect(route('admin.guestbook.settings'));

        $this->assertDatabaseHas('module_guestbook_messages', [
            'site_id' => $siteId,
            'display_no' => 11,
            'status' => 'pending',
        ]);
    }

    public function test_site_admin_can_open_payroll_management_pages(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'payroll')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $siteAdmin = $this->createSiteOperator('payroll-site-admin', true, 'site_admin');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.payroll.settings'))
            ->assertOk()
            ->assertSee('模块配置')
            ->assertSee('微信登录配置');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.payroll.batches.index'))
            ->assertOk()
            ->assertSee('工资信息')
            ->assertSee('新增工资批次');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.payroll.employees.index'))
            ->assertOk()
            ->assertSee('员工管理');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.payroll.help'))
            ->assertOk()
            ->assertSee('使用帮助')
            ->assertSee('新增月份批次');
    }

    public function test_payroll_frontend_local_preview_can_show_employee_batch_and_detail(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'payroll')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.payroll.enabled'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        DB::table('module_payroll_employees')->insert([
            'site_id' => $siteId,
            'wechat_openid' => 'wx-preview-user',
            'wechat_nickname' => '沐老师',
            'name' => '沐老师',
            'mobile' => '13800138000',
            'status' => 'approved',
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batchId = (int) DB::table('module_payroll_batches')->insertGetId([
            'site_id' => $siteId,
            'month_key' => '2026-03',
            'status' => 'imported',
            'salary_file_name' => 'salary-march.xls',
            'performance_file_name' => 'performance-march.xls',
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('module_payroll_records')->insert([
            [
                'site_id' => $siteId,
                'batch_id' => $batchId,
                'employee_name' => '沐老师',
                'sheet_type' => 'salary',
                'items_json' => json_encode([
                    ['label' => '姓名', 'value' => '沐老师'],
                    ['label' => '岗位工资', 'value' => '3810'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'row_hash' => hash('sha256', 'salary'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $siteId,
                'batch_id' => $batchId,
                'employee_name' => '沐老师',
                'sheet_type' => 'performance',
                'items_json' => json_encode([
                    ['label' => '姓名', 'value' => '沐老师'],
                    ['label' => '基础绩效', 'value' => '1410'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'row_hash' => hash('sha256', 'performance'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->followingRedirects()
            ->get(route('site.payroll.index', ['site' => 'site', 'mock_openid' => 'wx-preview-user']))
            ->assertOk()
            ->assertSee('我的薪资信息列表')
            ->assertSee('2026年3月')
            ->assertSee('工资条')
            ->assertSee('绩效');

        $this->withSession([
            'payroll.identity.'.$siteId => [
                'openid' => 'wx-preview-user',
                'unionid' => '',
                'nickname' => '沐老师',
                'avatar' => '',
            ],
        ])->get(route('site.payroll.show', ['batch' => $batchId, 'type' => 'salary', 'site' => 'site']))
            ->assertOk()
            ->assertSee('2026年3月 工资条详情')
            ->assertSee('岗位工资')
            ->assertSee('3810');
    }

    public function test_payroll_settings_can_be_updated_without_callback_url(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'payroll')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $siteAdmin = $this->createSiteOperator('payroll-setting-admin', true, 'site_admin');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.payroll.settings'))
            ->post(route('admin.payroll.settings.update'), [
                'enabled' => '1',
                'registration_enabled' => '1',
                'wechat_app_id' => 'wx-test-appid',
                'wechat_app_secret' => 'wx-test-secret',
                'registration_disabled_message' => '已禁止自动注册，请联系管理员。',
            ])
            ->assertRedirect(route('admin.payroll.settings'))
            ->assertSessionHasNoErrors();

        $settings = DB::table('site_settings')
            ->where('site_id', $siteId)
            ->whereIn('setting_key', [
                'module.payroll.enabled',
                'module.payroll.registration_enabled',
                'module.payroll.wechat_app_id',
                'module.payroll.registration_disabled_message',
            ])
            ->pluck('setting_value', 'setting_key');

        $this->assertSame('1', $settings->get('module.payroll.enabled'));
        $this->assertSame('1', $settings->get('module.payroll.registration_enabled'));
        $this->assertSame('wx-test-appid', $settings->get('module.payroll.wechat_app_id'));
        $this->assertSame('已禁止自动注册，请联系管理员。', $settings->get('module.payroll.registration_disabled_message'));
        $this->assertDatabaseMissing('site_settings', [
            'site_id' => $siteId,
            'setting_key' => 'module.payroll.wechat_callback_url',
        ]);
    }

    public function test_payroll_invalid_spreadsheet_does_not_clear_existing_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'payroll')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $batchId = (int) DB::table('module_payroll_batches')->insertGetId([
            'site_id' => $siteId,
            'month_key' => '2026-04',
            'status' => 'imported',
            'salary_file_name' => 'old-salary.xlsx',
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('module_payroll_records')->insert([
            'site_id' => $siteId,
            'batch_id' => $batchId,
            'employee_name' => '沐老师',
            'sheet_type' => 'salary',
            'items_json' => json_encode([
                ['label' => '姓名', 'value' => '沐老师'],
                ['label' => '岗位工资', 'value' => '3810'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'row_hash' => hash('sha256', 'existing'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $siteAdmin = $this->createSiteOperator('payroll-invalid-import-admin', true, 'site_admin');
        $invalidFile = $this->createPayrollSpreadsheetUpload([
            ['工号', '金额'],
            ['001', '100'],
        ], 'invalid-payroll.xlsx');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.payroll.batches.edit', $batchId))
            ->post(route('admin.payroll.batches.update', $batchId), [
                'salary_file' => $invalidFile,
            ])
            ->assertRedirect(route('admin.payroll.batches.edit', $batchId))
            ->assertSessionHasErrors(['salary_file']);

        $this->assertDatabaseHas('module_payroll_records', [
            'site_id' => $siteId,
            'batch_id' => $batchId,
            'employee_name' => '沐老师',
            'sheet_type' => 'salary',
        ]);

        $this->assertSame(1, DB::table('module_payroll_records')
            ->where('site_id', $siteId)
            ->where('batch_id', $batchId)
            ->where('sheet_type', 'salary')
            ->count());
    }

    public function test_payroll_frontend_registration_creates_pending_employee(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'payroll')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.payroll.enabled'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.payroll.registration_enabled'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        $this->withSession([
            'payroll.identity.'.$siteId => [
                'openid' => 'wx-new-employee',
                'unionid' => '',
                'nickname' => '新老师',
                'avatar' => '',
            ],
        ])->get(route('site.payroll.index', ['site' => 'site']))
            ->assertRedirect(route('site.payroll.register', ['site' => 'site']));

        $this->withSession([
            'payroll.identity.'.$siteId => [
                'openid' => 'wx-new-employee',
                'unionid' => '',
                'nickname' => '新老师',
                'avatar' => '',
            ],
        ])->post(route('site.payroll.register.store', ['site' => 'site']), [
            'name' => '王老师',
            'mobile' => '13800138000',
        ])->assertRedirect(route('site.payroll.register', ['site' => 'site']));

        $this->assertDatabaseHas('module_payroll_employees', [
            'site_id' => $siteId,
            'wechat_openid' => 'wx-new-employee',
            'name' => '王老师',
            'mobile' => '13800138000',
            'status' => 'pending',
        ]);
    }

    public function test_payroll_frontend_registration_does_not_downgrade_approved_employee(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'payroll')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.payroll.enabled'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.payroll.registration_enabled'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        DB::table('module_payroll_employees')->insert([
            'site_id' => $siteId,
            'wechat_openid' => 'wx-approved-employee',
            'wechat_unionid' => '',
            'wechat_nickname' => '已审核老师',
            'wechat_avatar' => '',
            'name' => '李老师',
            'mobile' => '13800138001',
            'status' => 'approved',
            'password_enabled' => 0,
            'password_hash' => null,
            'approved_at' => now(),
            'approved_by' => 1,
            'last_login_at' => null,
            'last_login_ip' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession([
            'payroll.identity.'.$siteId => [
                'openid' => 'wx-approved-employee',
                'unionid' => '',
                'nickname' => '已审核老师',
                'avatar' => '',
            ],
        ])->from(route('site.payroll.register', ['site' => 'site']))
            ->post(route('site.payroll.register.store', ['site' => 'site']), [
                'name' => '王老师',
                'mobile' => '13800138000',
            ]);

        $response->assertRedirect(route('site.payroll.register', ['site' => 'site']));
        $response->assertSessionHasErrors('register');

        $this->assertDatabaseHas('module_payroll_employees', [
            'site_id' => $siteId,
            'wechat_openid' => 'wx-approved-employee',
            'name' => '李老师',
            'mobile' => '13800138001',
            'status' => 'approved',
        ]);
    }

    public function test_payroll_frontend_registration_rejects_duplicate_name(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'payroll')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.payroll.enabled'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.payroll.registration_enabled'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        DB::table('module_payroll_employees')->insert([
            'site_id' => $siteId,
            'wechat_openid' => 'wx-existing-employee',
            'wechat_unionid' => '',
            'wechat_nickname' => '已存在老师',
            'wechat_avatar' => '',
            'name' => '王老师',
            'mobile' => '13800138011',
            'status' => 'approved',
            'password_enabled' => 0,
            'password_hash' => null,
            'approved_at' => now(),
            'approved_by' => 1,
            'last_login_at' => null,
            'last_login_ip' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession([
            'payroll.identity.'.$siteId => [
                'openid' => 'wx-new-duplicate',
                'unionid' => '',
                'nickname' => '新老师',
                'avatar' => '',
            ],
        ])->from(route('site.payroll.register', ['site' => 'site']))
            ->post(route('site.payroll.register.store', ['site' => 'site']), [
                'name' => '王老师',
                'mobile' => '13800138000',
            ]);

        $response->assertRedirect(route('site.payroll.register', ['site' => 'site']));
        $response->assertSessionHasErrors(['register' => '姓名重复，请联系管理员处理该问题。']);

        $this->assertDatabaseMissing('module_payroll_employees', [
            'site_id' => $siteId,
            'wechat_openid' => 'wx-new-duplicate',
        ]);
    }

    public function test_payroll_frontend_requires_password_unlock_before_access(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'payroll')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.payroll.enabled'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        DB::table('module_payroll_employees')->insert([
            'site_id' => $siteId,
            'wechat_openid' => 'wx-password-user',
            'wechat_nickname' => '密码老师',
            'name' => '密码老师',
            'mobile' => '13800138001',
            'status' => 'approved',
            'password_enabled' => 1,
            'password_hash' => Hash::make('123456'),
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession([
            'payroll.identity.'.$siteId => [
                'openid' => 'wx-password-user',
                'unionid' => '',
                'nickname' => '密码老师',
                'avatar' => '',
            ],
        ])->get(route('site.payroll.index', ['site' => 'site']))
            ->assertRedirect(route('site.payroll.password', ['site' => 'site']));

        $this->followingRedirects()
            ->withSession([
                'payroll.identity.'.$siteId => [
                    'openid' => 'wx-password-user',
                    'unionid' => '',
                    'nickname' => '密码老师',
                    'avatar' => '',
                ],
            ])->post(route('site.payroll.password.unlock', ['site' => 'site']), [
                'password' => '123456',
            ])->assertOk()
            ->assertSee('我的薪资信息列表');
    }

    public function test_site_admin_can_export_payroll_batch_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'payroll')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $batchId = (int) DB::table('module_payroll_batches')->insertGetId([
            'site_id' => $siteId,
            'month_key' => '2026-12',
            'status' => 'imported',
            'salary_file_name' => 'salary-december.xls',
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('module_payroll_records')->insert([
            'site_id' => $siteId,
            'batch_id' => $batchId,
            'employee_name' => '沐老师',
            'sheet_type' => 'salary',
            'items_json' => json_encode([
                ['label' => '姓名', 'value' => '沐老师'],
                ['label' => '岗位工资', 'value' => '3810'],
                ['label' => '薪级工资', 'value' => '4201'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'row_hash' => hash('sha256', 'export-salary'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $siteAdmin = $this->createSiteOperator('payroll-export-admin', true, 'site_admin');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.payroll.batches.export', ['batch' => $batchId, 'type' => 'salary']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition');
    }

    public function test_payroll_import_rejects_duplicate_names_in_uploaded_sheet(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(\App\Support\Modules\ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'payroll')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $batchId = (int) DB::table('module_payroll_batches')->insertGetId([
            'site_id' => $siteId,
            'month_key' => '2026-05',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $siteAdmin = $this->createSiteOperator('payroll-duplicate-import-admin', true, 'site_admin');
        $duplicateFile = $this->createPayrollSpreadsheetUpload([
            ['姓名', '岗位工资'],
            ['王老师', '3810'],
            ['王老师', '4201'],
        ], 'duplicate-payroll.xlsx');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.payroll.batches.edit', $batchId))
            ->post(route('admin.payroll.batches.update', $batchId), [
                'salary_file' => $duplicateFile,
            ])
            ->assertRedirect(route('admin.payroll.batches.edit', $batchId))
            ->assertSessionHasErrors(['salary_file' => '检测到姓名“王老师”重复，请检查后重新提交。']);

        $this->assertSame(0, DB::table('module_payroll_records')
            ->where('site_id', $siteId)
            ->where('batch_id', $batchId)
            ->where('sheet_type', 'salary')
            ->count());
    }

    public function test_module_manager_removes_stale_permissions_after_manifest_changes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $modulePath = app_path('Modules/StalePermissionTest');
        File::ensureDirectoryExists($modulePath);
        File::put($modulePath.'/module.json', json_encode([
            'name' => '权限漂移测试',
            'code' => 'stale_permission_test',
            'version' => '1.0.0',
            'scope' => 'site',
            'permissions' => ['stale_permission_test.view', 'stale_permission_test.reply'],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $manager = app(\App\Support\Modules\ModuleManager::class);

        try {
            $manager->synchronize();

            $replyPermissionId = (int) DB::table('site_permissions')
                ->where('code', 'stale_permission_test.reply')
                ->value('id');
            $this->assertNotSame(0, $replyPermissionId);

            File::put($modulePath.'/module.json', json_encode([
                'name' => '权限漂移测试',
                'code' => 'stale_permission_test',
                'version' => '1.0.1',
                'scope' => 'site',
                'permissions' => ['stale_permission_test.view'],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $manager->synchronize();

            $this->assertDatabaseMissing('site_permissions', [
                'code' => 'stale_permission_test.reply',
            ]);
            $this->assertDatabaseHas('site_permissions', [
                'code' => 'stale_permission_test.view',
            ]);
        } finally {
            File::deleteDirectory($modulePath);
        }
    }

    public function test_new_site_admin_does_not_inherit_permissions_from_missing_module_files(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $modulePath = app_path('Modules/MissingPermissionCarryTest');
        File::ensureDirectoryExists($modulePath);
        File::put($modulePath.'/module.json', json_encode([
            'name' => '缺失权限继承测试',
            'code' => 'missing_permission_carry_test',
            'version' => '1.0.0',
            'scope' => 'site',
            'permissions' => ['missing_permission_carry_test.view'],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $manager = app(\App\Support\Modules\ModuleManager::class);
        $manager->synchronize();
        File::deleteDirectory($modulePath);
        $manager->synchronize();

        $this->actingAs($user)
            ->post(route('admin.platform.sites.store'), [
                'name' => '模块权限新站点测试',
                'site_key' => 'module-permission-new-site',
                'status' => '1',
                'domains' => 'module-permission-new-site.test',
                'contact_phone' => '010-12345678',
                'contact_email' => 'module@test.com',
                'address' => '示例地址 2 号',
                'attachment_storage_limit_mb' => 128,
                'theme_ids' => [],
                'module_ids' => [],
                'seo_title' => '模块权限新站点测试',
                'seo_keywords' => '模块,权限',
                'seo_description' => '模块权限新站点测试描述',
                'opened_at' => now()->format('Y-m-d'),
                'expires_at' => '',
                'remark' => '测试站点',
                'site_admin_ids' => [],
            ])
            ->assertRedirect();

        $siteId = (int) DB::table('sites')->where('site_key', 'module-permission-new-site')->value('id');
        $siteAdminRoleId = (int) DB::table('site_roles')->where('code', 'site_admin')->whereNull('site_id')->value('id');
        $missingPermissionId = (int) DB::table('site_permissions')
            ->where('code', 'missing_permission_carry_test.view')
            ->value('id');

        if ($missingPermissionId !== 0) {
            $this->assertDatabaseMissing('site_role_permissions', [
                'site_id' => $siteId,
                'role_id' => $siteAdminRoleId,
                'permission_id' => $missingPermissionId,
            ]);
        }
    }

    public function test_platform_admin_can_update_system_settings_with_uploaded_brand_assets(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $logo = UploadedFile::fake()->image('admin-logo.png', 360, 80);
        $favicon = UploadedFile::fake()->create('favicon.ico', 12, 'image/x-icon');

        $this->actingAs($user)
            ->post(route('admin.platform.settings.update'), [
                'system_name' => '站群后台',
                'system_version' => '2.1.0',
                'current_tab' => 'basic',
                'attachment_allowed_extensions' => 'jpg,jpeg,png,webp,pdf,zip',
                'attachment_max_size_mb' => 18,
                'attachment_image_max_size_mb' => 6,
                'attachment_image_max_width' => 3200,
                'attachment_image_max_height' => 2400,
                'attachment_image_auto_resize' => '1',
                'attachment_image_auto_compress' => '1',
                'attachment_image_quality' => 76,
                'admin_enabled' => '1',
                'admin_disabled_message' => '后台暂时关闭，请联系管理员。',
                'admin_logo_file' => $logo,
                'admin_favicon_file' => $favicon,
            ])
            ->assertRedirect(route('admin.platform.settings.index', ['tab' => 'basic']))
            ->assertSessionHas('status', '系统设置已更新。');

        $logoPath = DB::table('system_settings')->where('setting_key', 'admin.logo')->value('setting_value');
        $faviconPath = DB::table('system_settings')->where('setting_key', 'admin.favicon')->value('setting_value');

        $this->assertSame('站群后台', DB::table('system_settings')->where('setting_key', 'system.name')->value('setting_value'));
        $this->assertSame('2.1.0', DB::table('system_settings')->where('setting_key', 'system.version')->value('setting_value'));
        $this->assertSame('jpg,jpeg,png,webp,pdf,zip', DB::table('system_settings')->where('setting_key', 'attachment.allowed_extensions')->value('setting_value'));
        $this->assertSame('18', DB::table('system_settings')->where('setting_key', 'attachment.max_size_mb')->value('setting_value'));
        $this->assertSame('1', DB::table('system_settings')->where('setting_key', 'attachment.image_auto_resize')->value('setting_value'));
        $this->assertSame('1', DB::table('system_settings')->where('setting_key', 'attachment.image_auto_compress')->value('setting_value'));
        $this->assertSame('76', DB::table('system_settings')->where('setting_key', 'attachment.image_quality')->value('setting_value'));
        $this->assertSame('/logo_x.png', $logoPath);
        $this->assertSame('/Favicon_x.ico', $faviconPath);
        $this->assertFileExists(public_path(ltrim($logoPath, '/')));
        $this->assertFileExists(public_path(ltrim($faviconPath, '/')));
    }

    public function test_platform_admin_can_clear_uploaded_brand_assets(): void
    {
        $this->seed(DatabaseSeeder::class);

        file_put_contents(public_path('logo_x.png'), 'logo');
        file_put_contents(public_path('Favicon_x.ico'), 'favicon');

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'admin.logo'],
            ['setting_value' => '/logo_x.png', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'admin.favicon'],
            ['setting_value' => '/Favicon_x.ico', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        $this->actingAs($this->superAdmin())
            ->post(route('admin.platform.settings.update'), [
                'system_name' => '站群后台',
                'system_version' => '2.1.0',
                'current_tab' => 'basic',
                'attachment_allowed_extensions' => 'jpg,jpeg,png,webp,pdf,zip',
                'attachment_max_size_mb' => 18,
                'attachment_image_max_size_mb' => 6,
                'attachment_image_max_width' => 3200,
                'attachment_image_max_height' => 2400,
                'attachment_image_auto_resize' => '1',
                'attachment_image_auto_compress' => '1',
                'attachment_image_quality' => 76,
                'admin_enabled' => '1',
                'admin_disabled_message' => '后台暂时关闭，请联系管理员。',
                'admin_logo_clear' => '1',
                'admin_favicon_clear' => '1',
            ])
            ->assertRedirect(route('admin.platform.settings.index', ['tab' => 'basic']));

        $this->assertSame('', DB::table('system_settings')->where('setting_key', 'admin.logo')->value('setting_value'));
        $this->assertSame('', DB::table('system_settings')->where('setting_key', 'admin.favicon')->value('setting_value'));
        $this->assertFileDoesNotExist(public_path('logo_x.png'));
        $this->assertFileDoesNotExist(public_path('Favicon_x.ico'));
    }

    public function test_platform_admin_can_disable_admin_from_system_settings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();

        $this->actingAs($user)
            ->post(route('admin.platform.settings.update'), [
                'system_name' => '站群后台',
                'system_version' => '2.1.0',
                'current_tab' => 'access',
                'attachment_allowed_extensions' => 'jpg,jpeg,png,webp,pdf,zip',
                'attachment_max_size_mb' => 18,
                'attachment_image_max_size_mb' => 6,
                'attachment_image_max_width' => 3200,
                'attachment_image_max_height' => 2400,
                'attachment_image_auto_resize' => '0',
                'attachment_image_auto_compress' => '0',
                'attachment_image_quality' => 82,
                'admin_disabled_message' => '后台维护中，请稍后再试。',
            ])
            ->assertRedirect(route('admin.platform.settings.index', ['tab' => 'access']));

        $this->assertSame('0', DB::table('system_settings')->where('setting_key', 'admin.enabled')->value('setting_value'));
    }

    public function test_platform_admin_system_settings_submission_is_sanitized(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.platform.settings.update'), [
                'system_name' => "  官闪闪管理端\x00\x1F  ",
                'system_version' => "  GSS \u{200B}1.0.0  ",
                'current_tab' => 'basic',
                'attachment_allowed_extensions' => ' JPG , png , pdf , <bad> , png ',
                'attachment_max_size_mb' => 18,
                'attachment_image_max_size_mb' => 6,
                'attachment_image_max_width' => 3200,
                'attachment_image_max_height' => 2400,
                'attachment_image_auto_resize' => '1',
                'attachment_image_auto_compress' => '1',
                'attachment_image_quality' => 76,
                'admin_enabled' => '1',
                'admin_disabled_message' => "  后台维护中，\r\n\r\n\r\n请稍后再试。\u{200B}  ",
            ])
            ->assertRedirect(route('admin.platform.settings.index', ['tab' => 'basic']));

        $this->assertSame('官闪闪管理端', DB::table('system_settings')->where('setting_key', 'system.name')->value('setting_value'));
        $this->assertSame('GSS 1.0.0', DB::table('system_settings')->where('setting_key', 'system.version')->value('setting_value'));
        $this->assertSame('jpg,png,pdf', DB::table('system_settings')->where('setting_key', 'attachment.allowed_extensions')->value('setting_value'));
        $this->assertSame("后台维护中，\n\n请稍后再试。", DB::table('system_settings')->where('setting_key', 'admin.disabled_message')->value('setting_value'));
    }

    public function test_platform_admin_system_settings_rejects_invalid_extension_payload(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->superAdmin())
            ->from(route('admin.platform.settings.index', ['tab' => 'upload']))
            ->post(route('admin.platform.settings.update'), [
                'system_name' => '站群后台',
                'system_version' => '2.1.0',
                'current_tab' => 'upload',
                'attachment_allowed_extensions' => '<<<>>>',
                'attachment_max_size_mb' => 18,
                'attachment_image_max_size_mb' => 6,
                'attachment_image_max_width' => 3200,
                'attachment_image_max_height' => 2400,
                'attachment_image_auto_resize' => '1',
                'attachment_image_auto_compress' => '1',
                'attachment_image_quality' => 76,
                'admin_enabled' => '1',
                'admin_disabled_message' => '后台暂时关闭，请联系管理员。',
            ])
            ->assertRedirect(route('admin.platform.settings.index', ['tab' => 'upload']))
            ->assertSessionHasErrors(['attachment_allowed_extensions']);
    }

    public function test_admin_disabled_blocks_site_operator_but_allows_super_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'admin.enabled'],
            ['setting_value' => '0', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'admin.disabled_message'],
            ['setting_value' => '后台维护中，请稍后再试。', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        $operator = $this->createSiteOperator('admin-disabled-site-operator', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username' => '后台维护中，请稍后再试。']);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_library_upload_rejects_file_larger_than_system_limit(): void
    {
        $this->seed(DatabaseSeeder::class);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.allowed_extensions'],
            ['setting_value' => 'pdf', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.max_size_mb'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        $operator = $this->createSiteOperator('library-size-limit-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $file = UploadedFile::fake()->create('oversized.pdf', 2048, 'application/pdf');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.attachments.library-upload'), [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_library_upload_rejects_file_type_not_in_system_limit(): void
    {
        $this->seed(DatabaseSeeder::class);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.allowed_extensions'],
            ['setting_value' => 'png', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        $operator = $this->createSiteOperator('library-type-limit-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $file = UploadedFile::fake()->create('manual.pdf', 200, 'application/pdf');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.attachments.library-upload'), [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_library_upload_rejects_file_when_site_storage_limit_would_be_exceeded(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('library-storage-limit-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'attachment.storage_limit_mb'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        DB::table('attachments')->insert([
            'site_id' => $siteId,
            'origin_name' => 'existing.pdf',
            'stored_name' => 'existing.pdf',
            'disk' => 'site',
            'path' => 'web/site/media/attachments/existing.pdf',
            'url' => 'http://127.0.0.1:8000/site-media/site/attachments/existing.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 900 * 1024,
            'uploaded_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $file = UploadedFile::fake()->create('incoming.pdf', 200, 'application/pdf');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.attachments.library-upload'), [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_library_upload_resizes_large_jpeg_when_auto_resize_is_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.allowed_extensions'],
            ['setting_value' => 'jpg,jpeg,png,webp', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.max_size_mb'],
            ['setting_value' => '10', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_max_size_mb'],
            ['setting_value' => '10', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_max_width'],
            ['setting_value' => '1200', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_max_height'],
            ['setting_value' => '1200', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_auto_resize'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_quality'],
            ['setting_value' => '72', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        $operator = $this->createSiteOperator('library-image-resize-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $sourcePath = storage_path('framework/testing/large-upload-source.jpg');
        $image = imagecreatetruecolor(2400, 1800);
        $background = imagecolorallocate($image, 210, 220, 235);
        imagefilledrectangle($image, 0, 0, 2400, 1800, $background);
        $accent = imagecolorallocate($image, 35, 80, 140);
        imagefilledrectangle($image, 120, 120, 2280, 1680, $accent);
        imagejpeg($image, $sourcePath, 95);
        imagedestroy($image);

        $file = new UploadedFile($sourcePath, 'campus.jpg', 'image/jpeg', null, true);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.attachments.library-upload'), [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertJsonStructure(['attachment' => ['id', 'name', 'url', 'path', 'relativeUrl', 'extension']]);

        $attachment = DB::table('attachments')
            ->where('site_id', $siteId)
            ->latest('id')
            ->first();

        $this->assertNotNull($attachment);
        $storedPath = storage_path('app/'.$attachment->path);
        $this->assertFileExists($storedPath);

        $dimensions = getimagesize($storedPath);
        $this->assertIsArray($dimensions);
        $this->assertSame(1200, $dimensions[0]);
        $this->assertSame(900, $dimensions[1]);

        @unlink($sourcePath);
    }

    public function test_library_upload_allows_oversized_original_image_when_auto_compress_is_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.allowed_extensions'],
            ['setting_value' => 'jpg,jpeg,png,webp', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.max_size_mb'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_max_size_mb'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_max_width'],
            ['setting_value' => '4000', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_max_height'],
            ['setting_value' => '4000', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_auto_resize'],
            ['setting_value' => '0', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_auto_compress'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_quality'],
            ['setting_value' => '65', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        $operator = $this->createSiteOperator('library-image-size-compress-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $sourcePath = storage_path('framework/testing/oversized-original-source.jpg');
        $image = imagecreatetruecolor(2400, 1800);
        $background = imagecolorallocate($image, 210, 220, 235);
        imagefilledrectangle($image, 0, 0, 2400, 1800, $background);
        $accent = imagecolorallocate($image, 35, 80, 140);
        imagefilledrectangle($image, 120, 120, 2280, 1680, $accent);
        imagejpeg($image, $sourcePath, 95);
        imagedestroy($image);
        file_put_contents($sourcePath, str_repeat('A', 1100 * 1024), FILE_APPEND);

        $file = new UploadedFile($sourcePath, 'oversized-original.jpg', 'image/jpeg', null, true);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.attachments.library-upload'), [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertJsonStructure(['attachment' => ['id', 'name', 'url', 'path', 'relativeUrl', 'extension']]);

        $attachment = DB::table('attachments')
            ->where('site_id', $siteId)
            ->latest('id')
            ->first();

        $this->assertNotNull($attachment);
        $this->assertLessThanOrEqual(1024 * 1024, (int) $attachment->size);

        @unlink($sourcePath);
    }

    public function test_library_upload_converts_non_transparent_png_to_jpeg_when_auto_compress_is_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.allowed_extensions'],
            ['setting_value' => 'jpg,jpeg,png,webp', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.max_size_mb'],
            ['setting_value' => '10', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_max_size_mb'],
            ['setting_value' => '10', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_auto_resize'],
            ['setting_value' => '0', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_auto_compress'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_quality'],
            ['setting_value' => '72', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        $operator = $this->createSiteOperator('library-png-convert-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $file = UploadedFile::fake()->image('plain-photo.png', 1600, 1200);

        $response = $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.attachments.library-upload'), [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->json('attachment');

        $attachment = DB::table('attachments')
            ->where('id', $response['id'] ?? 0)
            ->first();

        $this->assertNotNull($attachment);
        $this->assertSame('jpg', $attachment->extension);
        $this->assertSame('image/jpeg', $attachment->mime_type);
        $this->assertStringEndsWith('.jpg', (string) $attachment->stored_name);
    }

    public function test_library_upload_converts_opaque_rgba_png_to_jpeg_when_auto_compress_is_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.allowed_extensions'],
            ['setting_value' => 'jpg,jpeg,png,webp', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.max_size_mb'],
            ['setting_value' => '10', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_max_size_mb'],
            ['setting_value' => '10', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_auto_compress'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_quality'],
            ['setting_value' => '72', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        $operator = $this->createSiteOperator('library-opaque-rgba-png-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $sourcePath = storage_path('framework/testing/opaque-rgba.png');

        $image = imagecreatetruecolor(1200, 900);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $background = imagecolorallocatealpha($image, 245, 245, 245, 0);
        imagefilledrectangle($image, 0, 0, 1200, 900, $background);
        $accent = imagecolorallocatealpha($image, 64, 115, 255, 0);
        imagefilledrectangle($image, 120, 120, 1080, 780, $accent);
        imagepng($image, $sourcePath);

        $file = new UploadedFile($sourcePath, 'opaque-rgba.png', 'image/png', null, true);

        $response = $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.attachments.library-upload'), [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->json('attachment');

        $attachment = DB::table('attachments')
            ->where('id', $response['id'] ?? 0)
            ->first();

        $this->assertNotNull($attachment);
        $this->assertSame('jpg', $attachment->extension);
        $this->assertSame('image/jpeg', $attachment->mime_type);

        @unlink($sourcePath);
    }

    public function test_library_upload_rejects_oversized_original_image_when_only_auto_resize_is_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.allowed_extensions'],
            ['setting_value' => 'jpg,jpeg,png,webp', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.max_size_mb'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_max_size_mb'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_max_width'],
            ['setting_value' => '4000', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_max_height'],
            ['setting_value' => '4000', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_auto_resize'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_auto_compress'],
            ['setting_value' => '0', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_quality'],
            ['setting_value' => '65', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        $operator = $this->createSiteOperator('library-image-size-only-resize-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $sourcePath = storage_path('framework/testing/oversized-no-compress-source.jpg');
        $image = imagecreatetruecolor(2400, 1800);
        $background = imagecolorallocate($image, 210, 220, 235);
        imagefilledrectangle($image, 0, 0, 2400, 1800, $background);
        imagejpeg($image, $sourcePath, 95);
        imagedestroy($image);
        file_put_contents($sourcePath, str_repeat('A', 1100 * 1024), FILE_APPEND);

        $file = new UploadedFile($sourcePath, 'oversized-no-compress.jpg', 'image/jpeg', null, true);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.attachments.library-upload'), [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        @unlink($sourcePath);
    }

    public function test_attachment_replace_overwrites_original_file_and_preserves_path(): void
    {
        $this->seed(DatabaseSeeder::class);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.allowed_extensions'],
            ['setting_value' => 'jpg,jpeg,png,webp,pdf', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.max_size_mb'],
            ['setting_value' => '10', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_max_size_mb'],
            ['setting_value' => '10', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_max_width'],
            ['setting_value' => '1600', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_max_height'],
            ['setting_value' => '1600', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_auto_resize'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.image_quality'],
            ['setting_value' => '72', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        $operator = $this->createSiteOperator('attachment-replace-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $path = 'web/site/media/attachments/2026/04/replace-target.jpg';
        $absolutePath = storage_path('app/'.$path);
        $this->backupFileForTest($absolutePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        $originalImage = imagecreatetruecolor(400, 300);
        $originalBackground = imagecolorallocate($originalImage, 220, 220, 220);
        imagefilledrectangle($originalImage, 0, 0, 400, 300, $originalBackground);
        imagejpeg($originalImage, $absolutePath, 90);
        imagedestroy($originalImage);

        $attachmentId = (int) DB::table('attachments')->insertGetId([
            'site_id' => $siteId,
            'origin_name' => 'original-cover.jpg',
            'stored_name' => 'replace-target.jpg',
            'disk' => 'site',
            'path' => $path,
            'url' => 'http://127.0.0.1:8000/site-media/site/attachments/2026/04/replace-target.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => (int) filesize($absolutePath),
            'width' => 400,
            'height' => 300,
            'uploaded_by' => $operator->id,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $replacementSource = storage_path('framework/testing/replace-target-new.jpg');
        $this->temporaryPayrollUploads[] = $replacementSource;
        File::ensureDirectoryExists(dirname($replacementSource));
        $replacementImage = imagecreatetruecolor(1800, 1200);
        $replacementBackground = imagecolorallocate($replacementImage, 35, 80, 140);
        imagefilledrectangle($replacementImage, 0, 0, 1800, 1200, $replacementBackground);
        imagejpeg($replacementImage, $replacementSource, 96);
        imagedestroy($replacementImage);

        $file = new UploadedFile($replacementSource, 'new-cover.jpg', 'image/jpeg', null, true);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.attachments.replace', $attachmentId), [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertJsonPath('message', '附件已替换，原路径保持不变。')
            ->assertJsonPath('attachment.path', $path)
            ->assertJsonPath('attachment.extension', 'jpg');

        $attachment = DB::table('attachments')->where('id', $attachmentId)->first();

        $this->assertNotNull($attachment);
        $this->assertSame($path, $attachment->path);
        $this->assertSame('new-cover.jpg', $attachment->origin_name);
        $this->assertSame('jpg', $attachment->extension);
        $this->assertSame('replace-target.jpg', $attachment->stored_name);
        $this->assertSame('http://127.0.0.1:8000/site-media/site/attachments/2026/04/replace-target.jpg', $attachment->url);
        $this->assertGreaterThan(strtotime('-1 hour'), strtotime((string) $attachment->created_at));

        $dimensions = getimagesize($absolutePath);
        $this->assertIsArray($dimensions);
        $this->assertSame(1600, $dimensions[0]);
        $this->assertSame(1066, $dimensions[1]);
    }

    public function test_attachment_replace_rejects_file_with_different_extension(): void
    {
        $this->seed(DatabaseSeeder::class);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'attachment.allowed_extensions'],
            ['setting_value' => 'jpg,jpeg,png,webp,pdf', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        $operator = $this->createSiteOperator('attachment-replace-mismatch-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $path = 'web/site/media/attachments/2026/04/replace-mismatch.jpg';
        $absolutePath = storage_path('app/'.$path);
        $this->backupFileForTest($absolutePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        $originalImage = imagecreatetruecolor(400, 300);
        $originalBackground = imagecolorallocate($originalImage, 220, 220, 220);
        imagefilledrectangle($originalImage, 0, 0, 400, 300, $originalBackground);
        imagejpeg($originalImage, $absolutePath, 90);
        imagedestroy($originalImage);

        $attachmentId = (int) DB::table('attachments')->insertGetId([
            'site_id' => $siteId,
            'origin_name' => 'replace-mismatch.jpg',
            'stored_name' => 'replace-mismatch.jpg',
            'disk' => 'site',
            'path' => $path,
            'url' => 'http://127.0.0.1:8000/site-media/site/attachments/2026/04/replace-mismatch.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => (int) filesize($absolutePath),
            'width' => 400,
            'height' => 300,
            'uploaded_by' => $operator->id,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $file = UploadedFile::fake()->image('replace-mismatch.png', 800, 600);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.attachments.replace', $attachmentId), [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_attachment_library_feed_appends_cache_version_to_attachment_url(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('attachment-library-version-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $updatedAt = now()->subMinutes(5);

        $attachmentId = (int) DB::table('attachments')->insertGetId([
            'site_id' => $siteId,
            'origin_name' => 'cache-version.jpg',
            'stored_name' => 'cache-version.jpg',
            'disk' => 'site',
            'path' => 'web/site/media/attachments/2026/04/cache-version.jpg',
            'url' => 'http://127.0.0.1:8000/site-media/site/attachments/2026/04/cache-version.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 1024,
            'width' => 400,
            'height' => 300,
            'uploaded_by' => $operator->id,
            'created_at' => now()->subDay(),
            'updated_at' => $updatedAt,
        ]);

        $response = $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->getJson(route('admin.attachments.library-feed', [
                'mode' => 'editor',
                'context' => 'workspace',
            ]))
            ->assertOk()
            ->json('attachments');

        $item = collect($response)->firstWhere('id', $attachmentId);

        $this->assertNotNull($item);
        $this->assertSame(
            'http://127.0.0.1:8000/site-media/site/attachments/2026/04/cache-version.jpg?v='.$updatedAt->timestamp,
            $item['url'] ?? null,
        );
    }

    public function test_site_media_response_uses_revalidation_cache_headers(): void
    {
        $this->seed(DatabaseSeeder::class);

        $absolutePath = storage_path('app/web/site/media/attachments/2026/04/cache-header.jpg');
        $this->backupFileForTest($absolutePath);
        File::ensureDirectoryExists(dirname($absolutePath));

        $image = imagecreatetruecolor(200, 120);
        $background = imagecolorallocate($image, 220, 220, 220);
        imagefilledrectangle($image, 0, 0, 200, 120, $background);
        imagejpeg($image, $absolutePath, 88);
        imagedestroy($image);

        $response = $this->get('/site-media/site/attachments/2026/04/cache-header.jpg')
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0');

        $cacheControl = (string) $response->headers->get('Cache-Control', '');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
    }

    public function test_platform_admin_can_open_site_dashboard(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get(route('admin.site-dashboard'))
            ->assertOk()
            ->assertSee('站点工作台');
    }

    public function test_bound_site_operator_is_redirected_to_site_dashboard_from_admin_root(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('site-operator', true, 'editor');

        $this->actingAs($operator)
            ->get('/admin')
            ->assertRedirect(route('admin.site-dashboard'));
    }

    public function test_unbound_operator_cannot_enter_admin_backend(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->create([
            'username' => 'no-site-user',
            'name' => 'No Site User',
            'email' => 'nosite@example.com',
            'password' => 'ChangeMe123!',
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_site_operator_cannot_access_platform_only_pages_but_can_access_site_logs(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('bound-site-admin', true, 'site_admin');

        $this->actingAs($operator)
            ->get(route('admin.platform.users.index'))
            ->assertForbidden();

        $this->actingAs($operator)
            ->get(route('admin.logs.index'))
            ->assertForbidden();

        $this->actingAs($operator)
            ->get(route('admin.platform.sites.index'))
            ->assertForbidden();

        $this->actingAs($operator)
            ->get(route('admin.platform.roles.index'))
            ->assertForbidden();

        $this->actingAs($operator)
            ->get(route('admin.site-logs.index'))
            ->assertOk();
    }

    public function test_site_operator_cannot_write_platform_sites(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('platform-site-write-blocked', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.platform.sites.store'), [
                'name' => '越权创建站点',
                'site_key' => 'forbidden-platform-site',
            ])
            ->assertForbidden();

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.platform.sites.update', $siteId), [
                'name' => '越权修改站点',
                'site_key' => 'site',
            ])
            ->assertForbidden();
    }

    public function test_site_operator_cannot_write_platform_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('platform-user-write-blocked', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $platformIdentity = $this->createPlatformIdentity('platform-edit-target', 'platform_admin');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.platform.users.store'), [
                'username' => 'forbidden-platform-user',
                'name' => 'Forbidden Platform User',
                'email' => 'forbidden-platform-user@example.com',
                'password' => 'ChangeMe123!',
                'status' => 1,
                'role_id' => DB::table('platform_roles')->where('code', 'platform_admin')->value('id'),
            ])
            ->assertForbidden();

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.platform.users.update', $platformIdentity->id), [
                'username' => $platformIdentity->username,
                'name' => '越权修改平台管理员',
                'email' => $platformIdentity->email,
                'status' => 1,
                'role_id' => DB::table('platform_roles')->where('code', 'platform_admin')->value('id'),
            ])
            ->assertForbidden();

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.platform.users.destroy', $platformIdentity->id))
            ->assertForbidden();
    }

    public function test_platform_admin_role_management_is_single_select_and_superadmin_is_locked(): void
    {
        $this->seed(DatabaseSeeder::class);

        $platformAdmin = $this->superAdmin();
        $themeRoleId = (int) DB::table('platform_roles')
            ->where('code', '!=', 'super_admin')
            ->where('status', 1)
            ->orderBy('id')
            ->value('id');
        $superAdminRoleId = (int) DB::table('platform_roles')
            ->where('code', 'super_admin')
            ->value('id');

        $createdUser = $this->actingAs($platformAdmin)
            ->post(route('admin.platform.users.store'), [
                'username' => 'single-role-platform-user',
                'name' => 'Single Role Platform User',
                'email' => 'single-role-platform-user@example.com',
                'password' => 'ChangeMe123!',
                'status' => 1,
                'role_id' => $themeRoleId,
            ])
            ->assertRedirect(route('admin.platform.users.index'));

        $singleRoleUserId = (int) DB::table('users')->where('username', 'single-role-platform-user')->value('id');
        $assignedRoleIds = DB::table('platform_user_roles')->where('user_id', $singleRoleUserId)->pluck('role_id')->all();

        $this->assertSame([$themeRoleId], $assignedRoleIds);

        $this->actingAs($platformAdmin)
            ->post(route('admin.platform.users.update', $platformAdmin->id), [
                'username' => $platformAdmin->username,
                'name' => 'Super Admin',
                'email' => $platformAdmin->email,
                'status' => 1,
                'role_id' => $themeRoleId,
            ])
            ->assertRedirect(route('admin.platform.users.index'));

        $superAdminAssignedRoleIds = DB::table('platform_user_roles')
            ->where('user_id', $platformAdmin->id)
            ->pluck('role_id')
            ->all();

        $this->assertSame([$superAdminRoleId], $superAdminAssignedRoleIds);

        $this->assertTrue(
            DB::table('platform_user_roles')
                ->where('user_id', $platformAdmin->id)
                ->where('role_id', $superAdminRoleId)
                ->exists(),
            'superadmin 必须始终保留总管理员权限。',
        );
    }

    public function test_platform_user_create_validation_rejects_invalid_username_email_mobile_and_missing_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        $platformAdmin = $this->superAdmin();

        $this->actingAs($platformAdmin)
            ->from(route('admin.platform.users.create'))
            ->post(route('admin.platform.users.store'), [
                'username' => '中文账号',
                'name' => 'A',
                'email' => '测试@测试.cn',
                'mobile' => 'abc123',
                'password' => '1234567',
                'role_id' => '',
            ])
            ->assertRedirect(route('admin.platform.users.create'))
            ->assertSessionHasErrors(['username', 'name', 'email', 'mobile', 'password', 'role_id']);
    }

    public function test_platform_user_update_validation_rejects_invalid_username_email_and_mobile(): void
    {
        $this->seed(DatabaseSeeder::class);

        $platformAdmin = $this->superAdmin();
        $targetUser = $this->createPlatformIdentity('platform-user-validation-target', 'platform_admin');
        $roleId = (int) DB::table('platform_roles')->where('code', 'platform_admin')->value('id');

        $this->actingAs($platformAdmin)
            ->from(route('admin.platform.users.edit', $targetUser->id))
            ->post(route('admin.platform.users.update', $targetUser->id), [
                'username' => '1坏账号',
                'name' => '名',
                'email' => '测试',
                'mobile' => '电话',
                'password' => '123',
                'role_id' => $roleId,
            ])
            ->assertRedirect(route('admin.platform.users.edit', $targetUser->id))
            ->assertSessionHasErrors(['username', 'name', 'email', 'mobile', 'password']);
    }

    public function test_platform_role_management_requires_platform_role_permission(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = $this->superAdmin();
        $platformAdmin = $this->createPlatformIdentity('platform-role-forbidden', 'platform_admin');

        $this->actingAs($superAdmin)
            ->get(route('admin.platform.roles.index'))
            ->assertOk();

        $this->actingAs($platformAdmin)
            ->get(route('admin.platform.roles.index'))
            ->assertForbidden();
    }

    public function test_platform_admin_cannot_change_own_platform_role_assignment(): void
    {
        $this->seed(DatabaseSeeder::class);

        $platformAdmin = $this->createPlatformIdentity('self-role-protected', 'platform_admin');
        $themeAdminRoleId = (int) DB::table('platform_roles')
            ->where('code', 'theme_admin')
            ->value('id');

        $this->actingAs($platformAdmin)
            ->from(route('admin.platform.users.edit', $platformAdmin->id))
            ->post(route('admin.platform.users.update', $platformAdmin->id), [
                'username' => $platformAdmin->username,
                'name' => $platformAdmin->name,
                'email' => $platformAdmin->email,
                'role_id' => $themeAdminRoleId,
            ])
            ->assertRedirect(route('admin.platform.users.edit', $platformAdmin->id))
            ->assertSessionHasErrors('role_id');

        $this->assertSame(
            'platform_admin',
            DB::table('platform_roles')
                ->join('platform_user_roles', 'platform_user_roles.role_id', '=', 'platform_roles.id')
                ->where('platform_user_roles.user_id', $platformAdmin->id)
                ->value('platform_roles.code')
        );
    }

    public function test_non_super_platform_admin_cannot_update_permissions_of_own_platform_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        $platformAdmin = $this->createPlatformIdentity('platform-role-editor', 'platform_admin');
        $platformAdminRoleId = (int) DB::table('platform_roles')->where('code', 'platform_admin')->value('id');
        $roleManagePermissionId = (int) DB::table('platform_permissions')->where('code', 'platform.role.manage')->value('id');
        $siteManagePermissionId = (int) DB::table('platform_permissions')->where('code', 'site.manage')->value('id');

        DB::table('platform_role_permissions')->updateOrInsert(
            ['role_id' => $platformAdminRoleId, 'permission_id' => $roleManagePermissionId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $this->actingAs($platformAdmin)
            ->from(route('admin.platform.roles.edit', $platformAdminRoleId))
            ->post(route('admin.platform.roles.update', $platformAdminRoleId), [
                'name' => '平台管理员',
                'description' => '负责平台运维配置',
                'permission_ids' => [$siteManagePermissionId],
            ])
            ->assertRedirect(route('admin.platform.roles.edit', $platformAdminRoleId))
            ->assertSessionHasErrors('permission_ids');

        $assignedPermissionCodes = DB::table('platform_role_permissions')
            ->join('platform_permissions', 'platform_permissions.id', '=', 'platform_role_permissions.permission_id')
            ->where('platform_role_permissions.role_id', $platformAdminRoleId)
            ->pluck('platform_permissions.code')
            ->all();

        $this->assertContains('platform.user.manage', $assignedPermissionCodes);
    }

    public function test_super_admin_platform_role_is_locked_for_update(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = $this->superAdmin();
        $superAdminRoleId = (int) DB::table('platform_roles')->where('code', 'super_admin')->value('id');
        $beforePermissionIds = DB::table('platform_role_permissions')
            ->where('role_id', $superAdminRoleId)
            ->pluck('permission_id')
            ->all();
        $beforeDescription = DB::table('platform_roles')->where('id', $superAdminRoleId)->value('description');

        $this->actingAs($superAdmin)
            ->from(route('admin.platform.roles.edit', $superAdminRoleId))
            ->post(route('admin.platform.roles.update', $superAdminRoleId), [
                'name' => '被误改的总管理员',
                'description' => 'forbidden update',
                'permission_ids' => [],
            ])
            ->assertRedirect(route('admin.platform.roles.edit', $superAdminRoleId))
            ->assertSessionHas('status', '总管理员为系统内置核心角色，不支持编辑。');

        $this->assertSame(
            '总管理员',
            DB::table('platform_roles')->where('id', $superAdminRoleId)->value('name'),
            '总管理员平台角色名称不应被修改。',
        );

        $this->assertSame(
            $beforeDescription,
            DB::table('platform_roles')->where('id', $superAdminRoleId)->value('description'),
            '总管理员平台角色说明不应被修改。',
        );

        $this->assertSame(
            $beforePermissionIds,
            DB::table('platform_role_permissions')->where('role_id', $superAdminRoleId)->pluck('permission_id')->all(),
            '总管理员平台角色权限不应被修改。',
        );
    }

    public function test_site_operator_cannot_write_platform_themes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('platform-theme-write-blocked', true, 'template_editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.platform.settings.update'), [
                'system_name' => '越权修改系统名称',
                'system_version' => '1.0.0',
                'attachment_allowed_extensions' => 'jpg,png,webp',
                'attachment_max_size_mb' => 10,
                'attachment_image_max_size_mb' => 5,
                'attachment_image_max_width' => 1920,
                'attachment_image_max_height' => 1080,
                'attachment_image_auto_resize' => 1,
                'attachment_image_auto_compress' => 1,
                'attachment_image_quality' => 82,
                'attachment_image_strip_exif' => 1,
            ])
            ->assertForbidden();
    }

    public function test_single_site_operator_dashboard_hides_site_switcher(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('single-site-operator', true, 'editor');

        $this->actingAs($operator)
            ->get(route('admin.site-dashboard'))
            ->assertOk()
            ->assertDontSee('切换站点主控');
    }

    public function test_site_dashboard_renders_current_workspace_modules(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('site-dashboard-summary', true, 'editor');
        $siteId = (int) DB::table('site_user_roles')
            ->where('user_id', $operator->id)
            ->value('site_id');

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'site.filing_number'],
            [
                'setting_value' => '京ICP备20260001号',
                'autoload' => 1,
                'updated_by' => $operator->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->actingAs($operator)
            ->get(route('admin.site-dashboard'))
            ->assertOk()
            ->assertSee('站点工作台')
            ->assertSee('近 7 天访问趋势')
            ->assertSee('近期文章')
            ->assertSee('官闪闪公告栏');
    }

    public function test_multi_site_operator_dashboard_shows_site_switcher_and_can_switch_bound_sites(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('multi-site-operator', true, 'editor');
        $secondSiteId = $this->createAdditionalSite('demo-school-2', '第二示例学校');
        $editorRoleId = (int) DB::table('site_roles')->where('code', 'editor')->value('id');

        DB::table('site_user_roles')->insert([
            'site_id' => $secondSiteId,
            'user_id' => $operator->id,
            'role_id' => $editorRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->get(route('admin.site-dashboard'))
            ->assertOk()
            ->assertSee('示例学校')
            ->assertSee('第二示例学校');

        $this->actingAs($operator)
            ->post(route('admin.site-context.update'), ['site_id' => $secondSiteId])
            ->assertRedirect();

        $this->assertSame($secondSiteId, (int) session('current_site_id'));
    }

    public function test_site_operator_cannot_switch_to_unbound_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('restricted-operator', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $unboundSiteId = $this->createAdditionalSite('demo-school-2', '第二示例学校');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.site-context.update'), ['site_id' => $unboundSiteId])
            ->assertRedirect()
            ->assertSessionHas('status', '当前账号不能进入所选站点。');

        $this->assertSame($siteId, (int) session('current_site_id'));
    }

    public function test_platform_admin_can_switch_to_any_site_context(): void
    {
        $this->seed(DatabaseSeeder::class);

        $platformAdmin = $this->superAdmin();
        $targetSiteId = $this->createAdditionalSite('platform-target-site', '平台切换目标站点');

        $this->actingAs($platformAdmin)
            ->withSession(['current_site_id' => 1])
            ->post(route('admin.site-context.update'), ['site_id' => $targetSiteId])
            ->assertRedirect()
            ->assertSessionHas('status', '当前站点已切换。');

        $this->assertSame($targetSiteId, (int) session('current_site_id'));
    }

    public function test_stale_site_context_for_operator_falls_back_to_first_bound_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('stale-site-context-operator', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => 999999])
            ->get(route('admin.site-dashboard'))
            ->assertOk()
            ->assertSee('站点工作台');

        $this->assertSame($siteId, (int) session('current_site_id'));
    }

    public function test_stale_site_context_for_platform_admin_falls_back_to_first_available_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $platformAdmin = $this->superAdmin();

        $this->actingAs($platformAdmin)
            ->withSession(['current_site_id' => 999999])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('平台工作台')
            ->assertSee('示例学校');

        $this->assertSame(1, (int) session('current_site_id'));
    }

    public function test_platform_user_index_excludes_site_only_operators(): void
    {
        $this->seed(DatabaseSeeder::class);

        $platformAdmin = $this->superAdmin();
        $siteOperator = $this->createSiteOperator('only-site-operator', true, 'editor');

        $this->actingAs($platformAdmin)
            ->get(route('admin.platform.users.index'))
            ->assertOk()
            ->assertDontSee($siteOperator->username);
    }

    public function test_non_super_platform_admin_cannot_see_or_manage_system_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $systemAdmin = $this->superAdmin();
        $platformAdmin = $this->createPlatformIdentity('limited-platform-admin', 'platform_admin');
        $themeAdminRoleId = (int) DB::table('platform_roles')
            ->where('code', 'theme_admin')
            ->value('id');

        $this->actingAs($platformAdmin)
            ->get(route('admin.platform.users.index'))
            ->assertOk()
            ->assertDontSee($systemAdmin->username)
            ->assertDontSee($systemAdmin->name ?: $systemAdmin->username)
            ->assertDontSee(route('admin.platform.users.edit', $systemAdmin->id), false);

        $this->actingAs($platformAdmin)
            ->get(route('admin.platform.users.edit', $systemAdmin->id))
            ->assertNotFound();

        $this->actingAs($platformAdmin)
            ->post(route('admin.platform.users.update', $systemAdmin->id), [
                'username' => $systemAdmin->username,
                'name' => '非法修改系统管理员',
                'email' => $systemAdmin->email,
                'mobile' => $systemAdmin->mobile,
                'role_id' => $themeAdminRoleId,
            ])
            ->assertNotFound();

        $this->actingAs($platformAdmin)
            ->post(route('admin.platform.users.destroy', $systemAdmin->id))
            ->assertNotFound();

        $this->assertSame(
            'Super Admin',
            (string) DB::table('users')->where('id', $systemAdmin->id)->value('name')
        );
    }

    public function test_site_user_index_and_site_admin_candidates_exclude_platform_identities(): void
    {
        $this->seed(DatabaseSeeder::class);

        $platformOwner = $this->superAdmin();
        $platformIdentity = $this->createPlatformIdentity('platform-only-user', 'platform_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $siteAdminRoleId = (int) DB::table('site_roles')->where('code', 'site_admin')->value('id');

        DB::table('site_user_roles')->updateOrInsert(
            ['site_id' => $siteId, 'user_id' => $platformIdentity->id, 'role_id' => $siteAdminRoleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $siteOperator = $this->createSiteOperator('site-admin-checker', true, 'site_admin');

        $this->actingAs($siteOperator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-users.index'))
            ->assertOk()
            ->assertDontSee($platformIdentity->username);

        $this->actingAs($platformOwner)
            ->get(route('admin.platform.sites.edit', $siteId))
            ->assertOk()
            ->assertDontSee($platformIdentity->username);
    }

    public function test_site_operator_sidebar_hides_system_management_group(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteOperator = $this->createSiteOperator('site-menu-checker', true, 'site_admin');

        $this->actingAs($siteOperator)
            ->get(route('admin.site-dashboard'))
            ->assertOk()
            ->assertDontSee('系统管理')
            ->assertDontSee(route('admin.platform.users.index'), false)
            ->assertSee('站点配置')
            ->assertSee('内容管理');
    }

    public function test_site_operator_cannot_delete_attachment_from_unbound_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('attachment-checker', true, 'site_admin');
        $boundSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $unboundSiteId = $this->createAdditionalSite('locked-foreign-site', '外部测试站点');

        $attachmentId = (int) DB::table('attachments')->insertGetId([
            'site_id' => $unboundSiteId,
            'origin_name' => 'foreign-file.pdf',
            'stored_name' => 'foreign-file.pdf',
            'disk' => 'site',
            'path' => 'locked-foreign-site/media/attachments/foreign-file.pdf',
            'url' => '/site-media/locked-foreign-site/media/attachments/foreign-file.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 1024,
            'uploaded_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $boundSiteId])
            ->post(route('admin.attachments.destroy', $attachmentId))
            ->assertNotFound();

        $this->assertTrue(
            DB::table('attachments')->where('id', $attachmentId)->exists(),
            '跨站点附件不应被当前站点操作员删除。',
        );
    }

    public function test_article_editor_resource_library_only_shows_own_attachments_when_attachment_share_is_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $seedOperator = $this->createPlatformIdentity('attachment-share-editor-seeder');
        $channelId = $this->createSiteChannel($siteId, 'attachment-share-editor-channel', '附件共享编辑栏目', $seedOperator->id);
        $editor = $this->createRestrictedContentOperator('attachment-share-editor', $siteId, [$channelId]);
        $otherEditor = $this->createRestrictedContentOperator('attachment-share-other-editor', $siteId, [$channelId]);

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'attachment.share_enabled'],
            [
                'setting_value' => '0',
                'autoload' => 1,
                'updated_by' => $editor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $ownAttachment = $this->createSiteAttachment($siteId, $editor->id, 'editor-private-library.pdf');
        $otherAttachment = $this->createSiteAttachment($siteId, $otherEditor->id, 'other-editor-library.pdf');

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '附件共享关闭测试文章',
            'slug' => 'attachment-share-off-library',
            'summary' => '用于附件共享测试',
            'content' => '<p>正文</p>',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($editor)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.library-feed', [
                'mode' => 'editor',
                'context' => 'content',
            ]))
            ->assertOk();

        $response->assertSee('editor-private-library.pdf', false);
        $response->assertDontSee('other-editor-library.pdf', false);
        $this->assertGreaterThan(0, $ownAttachment);
        $this->assertGreaterThan(0, $otherAttachment);
    }

    public function test_article_editor_resource_library_shows_all_attachments_when_attachment_share_is_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $seedOperator = $this->createPlatformIdentity('attachment-share-editor-open-seeder');
        $channelId = $this->createSiteChannel($siteId, 'attachment-share-editor-open-channel', '附件共享开放栏目', $seedOperator->id);
        $editor = $this->createRestrictedContentOperator('attachment-share-editor-open', $siteId, [$channelId]);
        $otherEditor = $this->createRestrictedContentOperator('attachment-share-editor-open-peer', $siteId, [$channelId]);

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'attachment.share_enabled'],
            [
                'setting_value' => '1',
                'autoload' => 1,
                'updated_by' => $editor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $ownAttachment = $this->createSiteAttachment($siteId, $editor->id, 'editor-private-open-library.pdf');
        $sharedAttachment = $this->createSiteAttachment($siteId, $otherEditor->id, 'other-editor-open-library.pdf');

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '附件共享开启测试文章',
            'slug' => 'attachment-share-on-library',
            'summary' => '用于附件共享测试',
            'content' => '<p>正文</p>',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($editor)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.library-feed', [
                'mode' => 'editor',
                'context' => 'content',
            ]))
            ->assertOk();

        $response->assertSee('editor-private-open-library.pdf', false);
        $response->assertSee('other-editor-open-library.pdf', false);
        $this->assertGreaterThan(0, $ownAttachment);
        $this->assertGreaterThan(0, $sharedAttachment);
    }

    public function test_theme_editor_attachment_library_feed_preserves_relative_url_and_visibility(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $editor = $this->createSiteOperator('theme-library-editor', true, 'template_editor');
        $otherEditor = $this->createSiteOperator('theme-library-owner', true, 'editor');

        $this->setAttachmentSharing($siteId, false, $editor->id);

        $ownAttachmentId = $this->createSiteAttachment($siteId, $editor->id, 'theme-own-image.jpg');
        $otherAttachmentId = $this->createSiteAttachment($siteId, $otherEditor->id, 'theme-other-image.jpg');
        $ownRelativeUrl = (string) (parse_url((string) DB::table('attachments')->where('id', $ownAttachmentId)->value('url'), PHP_URL_PATH) ?: '');

        $response = $this->actingAs($editor)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.library-feed', [
                'mode' => 'picker',
                'context' => 'theme',
                'image_only' => 1,
            ]))
            ->assertOk()
            ->assertSee('theme-own-image.jpg')
            ->assertDontSee('theme-other-image.jpg');

        $response->assertJsonFragment([
            'id' => $ownAttachmentId,
            'relativeUrl' => $ownRelativeUrl,
        ]);

        $this->assertGreaterThan(0, $otherAttachmentId);
    }

    public function test_uploader_with_attachment_manage_can_only_see_own_attachments_on_attachment_index_when_share_is_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $uploader = $this->createSiteOperator('attachment-share-uploader', true, 'uploader');
        $otherOperator = $this->createRestrictedContentOperator('attachment-share-uploader-peer', $siteId, []);

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'attachment.share_enabled'],
            [
                'setting_value' => '0',
                'autoload' => 1,
                'updated_by' => $uploader->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $ownAttachment = $this->createSiteAttachment($siteId, $uploader->id, 'uploader-private-attachment.pdf');
        $otherAttachment = $this->createSiteAttachment($siteId, $otherOperator->id, 'other-owner-attachment.pdf');

        $this->actingAs($uploader)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.index'))
            ->assertOk()
            ->assertSee('uploader-private-attachment.pdf', false)
            ->assertDontSee('other-owner-attachment.pdf', false);

        $this->assertGreaterThan(0, $ownAttachment);
        $this->assertGreaterThan(0, $otherAttachment);
    }

    public function test_attachment_index_is_scoped_to_owner_for_restricted_editor_when_share_is_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $seedOperator = $this->createPlatformIdentity('attachment-share-index-seeder');
        $channelId = $this->createSiteChannel($siteId, 'attachment-share-index-channel', '附件共享索引栏目', $seedOperator->id);
        $editor = $this->createRestrictedContentOperator('attachment-share-index-editor', $siteId, [$channelId]);
        $otherEditor = $this->createRestrictedContentOperator('attachment-share-index-peer', $siteId, [$channelId]);

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'attachment.share_enabled'],
            [
                'setting_value' => '0',
                'autoload' => 1,
                'updated_by' => $editor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $ownAttachment = $this->createSiteAttachment($siteId, $editor->id, 'index-private-attachment.pdf');
        $otherAttachment = $this->createSiteAttachment($siteId, $otherEditor->id, 'index-other-attachment.pdf');

        $this->actingAs($editor)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.index'))
            ->assertOk()
            ->assertSee('index-private-attachment.pdf', false)
            ->assertDontSee('index-other-attachment.pdf', false);

        $this->assertGreaterThan(0, $ownAttachment);
        $this->assertGreaterThan(0, $otherAttachment);
    }

    public function test_attachment_management_page_requires_attachment_or_content_permission(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $operator = $this->createSiteOperator('attachment-page-forbidden', true, 'template_editor');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.index'))
            ->assertForbidden();
    }

    public function test_site_operator_bulk_attachment_delete_does_not_touch_unbound_site_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('attachment-bulk-checker', true, 'site_admin');
        $boundSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $unboundSiteId = $this->createAdditionalSite('bulk-attachment-remote-site', '远程附件批量站点');

        $attachmentId = (int) DB::table('attachments')->insertGetId([
            'site_id' => $unboundSiteId,
            'origin_name' => 'foreign-bulk-file.pdf',
            'stored_name' => 'foreign-bulk-file.pdf',
            'disk' => 'site',
            'path' => 'bulk-attachment-remote-site/media/attachments/foreign-bulk-file.pdf',
            'url' => '/site-media/bulk-attachment-remote-site/media/attachments/foreign-bulk-file.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 1024,
            'uploaded_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $boundSiteId])
            ->post(route('admin.attachments.bulk'), [
                'action' => 'delete',
                'ids' => [$attachmentId],
            ])
            ->assertRedirect(route('admin.attachments.index'));

        $this->assertTrue(
            DB::table('attachments')->where('id', $attachmentId)->exists(),
            '跨站点附件不应被批量删除。',
        );
    }

    public function test_attachment_sharing_disabled_limits_editor_visibility_to_own_uploads(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $seedOperator = $this->createPlatformIdentity('attachment-share-editor-seeder');
        $channelId = $this->createSiteChannel($siteId, 'attachment-share-editor-channel', '附件共享编辑栏目', $seedOperator->id);
        $editor = $this->createRestrictedContentOperator('attachment-share-editor', $siteId, [$channelId]);
        $otherEditor = $this->createRestrictedContentOperator('attachment-share-other', $siteId, [$channelId]);

        $this->setAttachmentSharing($siteId, false, $editor->id);
        $this->createSiteAttachment($siteId, $editor->id, 'editor-own-visible.pdf');
        $this->createSiteAttachment($siteId, $otherEditor->id, 'editor-other-hidden.pdf');

        $this->actingAs($editor)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.index'))
            ->assertOk()
            ->assertSee('editor-own-visible.pdf')
            ->assertDontSee('editor-other-hidden.pdf');

        $this->actingAs($editor)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.library-feed', [
                'mode' => 'editor',
                'context' => 'content',
            ]))
            ->assertOk()
            ->assertSee('editor-own-visible.pdf')
            ->assertDontSee('editor-other-hidden.pdf');
    }

    public function test_attachment_sharing_enabled_allows_editor_to_see_all_site_uploads(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $seedOperator = $this->createPlatformIdentity('attachment-share-open-seeder');
        $channelId = $this->createSiteChannel($siteId, 'attachment-share-open-channel', '附件共享开放栏目', $seedOperator->id);
        $editor = $this->createRestrictedContentOperator('attachment-share-open-editor', $siteId, [$channelId]);
        $otherEditor = $this->createRestrictedContentOperator('attachment-share-open-other', $siteId, [$channelId]);

        $this->setAttachmentSharing($siteId, true, $editor->id);
        $this->createSiteAttachment($siteId, $editor->id, 'shared-attachment-own.pdf');
        $this->createSiteAttachment($siteId, $otherEditor->id, 'shared-attachment-other.pdf');

        $this->actingAs($editor)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.index'))
            ->assertOk()
            ->assertSee('shared-attachment-own.pdf')
            ->assertSee('shared-attachment-other.pdf');

        $this->actingAs($editor)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.library-feed', [
                'mode' => 'editor',
                'context' => 'content',
            ]))
            ->assertOk()
            ->assertSee('shared-attachment-own.pdf')
            ->assertSee('shared-attachment-other.pdf');
    }

    public function test_attachment_index_can_display_uploaded_by_name_and_filter_unused_resources_by_last_used_at(): void
    {
        $this->seed(DatabaseSeeder::class);

        $attachmentManager = $this->createSiteOperator('attachment-usage-checker', true, 'uploader');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->setAttachmentSharing($siteId, true, $attachmentManager->id);

        $staleAttachmentId = $this->createSiteAttachment($siteId, $attachmentManager->id, 'stale-unused-resource.pdf');
        $recentAttachmentId = $this->createSiteAttachment($siteId, $attachmentManager->id, 'recent-unused-resource.pdf');

        DB::table('attachments')->where('id', $staleAttachmentId)->update([
            'usage_count' => 0,
            'created_at' => now()->subDays(45),
            'last_used_at' => null,
        ]);

        DB::table('attachments')->where('id', $recentAttachmentId)->update([
            'usage_count' => 0,
            'created_at' => now()->subDays(45),
            'last_used_at' => now()->subDays(5),
        ]);

        $this->actingAs($attachmentManager)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.index', ['unused_days' => 30]))
            ->assertOk()
            ->assertSee('stale-unused-resource.pdf')
            ->assertDontSee('recent-unused-resource.pdf')
            ->assertSee($attachmentManager->name);
    }

    public function test_same_attachment_used_as_cover_and_body_is_grouped_in_usage_details(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('attachment-usage-detail-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $attachmentId = $this->createSiteAttachment($siteId, $siteAdmin->id, 'cover-and-body-shared.jpg');
        $attachmentUrl = (string) DB::table('attachments')->where('id', $attachmentId)->value('url');

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'type' => 'article',
            'title' => '封面与正文共用同图',
            'slug' => 'cover-body-shared-image',
            'cover_image' => $attachmentUrl,
            'content' => '<p><img src="'.$attachmentUrl.'" alt="shared"></p>',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $siteAdmin->id,
            'updated_by' => $siteAdmin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(\App\Support\ContentAttachmentRelationSync::class)->syncForContent($siteId, $contentId);

        $this->assertSame(2, (int) DB::table('attachments')->where('id', $attachmentId)->value('usage_count'));

        $response = $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->getJson(route('admin.attachments.usages', $attachmentId));

        $response->assertOk()
            ->assertJsonCount(1, 'items');

        $this->assertSame(
            ['封面图', '正文图片'],
            data_get($response->json(), 'items.0.relation_labels')
        );
    }

    public function test_soft_deleted_content_keeps_attachment_usage_and_shows_recycle_bin_status(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('attachment-recycle-soft-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $attachmentId = $this->createSiteAttachment($siteId, $siteAdmin->id, 'recycle-soft-usage.jpg');
        $attachmentUrl = (string) DB::table('attachments')->where('id', $attachmentId)->value('url');

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'type' => 'article',
            'title' => '进入回收站仍保留引用',
            'slug' => 'keep-attachment-usage-after-soft-delete',
            'cover_image' => $attachmentUrl,
            'content' => '<p><img src="'.$attachmentUrl.'" alt="shared"></p>',
            'status' => 'published',
            'audit_status' => 'published',
            'created_by' => $siteAdmin->id,
            'updated_by' => $siteAdmin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(\App\Support\ContentAttachmentRelationSync::class)->syncForContent($siteId, $contentId);

        $this->assertSame(2, (int) DB::table('attachments')->where('id', $attachmentId)->value('usage_count'));

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.articles.destroy', $contentId))
            ->assertRedirect(route('admin.articles.index'));

        $this->assertNotNull(DB::table('contents')->where('id', $contentId)->value('deleted_at'));
        $this->assertSame(2, (int) DB::table('attachments')->where('id', $attachmentId)->value('usage_count'));

        $response = $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->getJson(route('admin.attachments.usages', $attachmentId));

        $response->assertOk()
            ->assertJsonCount(1, 'items');

        $this->assertSame('回收站', data_get($response->json(), 'items.0.status_label'));
        $this->assertSame(
            ['封面图', '正文图片'],
            data_get($response->json(), 'items.0.relation_labels')
        );
    }

    public function test_force_deleting_recycled_content_clears_attachment_usage(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('attachment-recycle-force-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $attachmentId = $this->createSiteAttachment($siteId, $siteAdmin->id, 'recycle-force-usage.jpg');
        $attachmentUrl = (string) DB::table('attachments')->where('id', $attachmentId)->value('url');

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'type' => 'article',
            'title' => '彻底删除后移除引用',
            'slug' => 'clear-attachment-usage-after-force-delete',
            'content' => '<p><img src="'.$attachmentUrl.'" alt="shared"></p>',
            'status' => 'published',
            'audit_status' => 'published',
            'created_by' => $siteAdmin->id,
            'updated_by' => $siteAdmin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(\App\Support\ContentAttachmentRelationSync::class)->syncForContent($siteId, $contentId);

        $this->assertSame(1, (int) DB::table('attachments')->where('id', $attachmentId)->value('usage_count'));

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.articles.destroy', $contentId))
            ->assertRedirect(route('admin.articles.index'));

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.recycle-bin.destroy', $contentId))
            ->assertRedirect(route('admin.recycle-bin.index'));

        $this->assertDatabaseMissing('attachment_relations', [
            'relation_type' => 'content',
            'relation_id' => $contentId,
        ]);
        $this->assertSame(0, (int) DB::table('attachments')->where('id', $attachmentId)->value('usage_count'));
    }

    public function test_recycled_content_has_no_edit_or_view_action_in_attachment_usage_details(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('attachment-recycle-usage-actions-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $attachmentId = $this->createSiteAttachment($siteId, $siteAdmin->id, 'recycle-usage-actions.jpg');
        $attachmentUrl = (string) DB::table('attachments')->where('id', $attachmentId)->value('url');

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'type' => 'article',
            'title' => '回收站内容不应提供编辑入口',
            'slug' => 'recycled-content-has-no-edit-url',
            'content' => '<p><img src="'.$attachmentUrl.'" alt="recycled"></p>',
            'status' => 'published',
            'audit_status' => 'published',
            'created_by' => $siteAdmin->id,
            'updated_by' => $siteAdmin->id,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => now(),
        ]);

        app(\App\Support\ContentAttachmentRelationSync::class)->syncForContent($siteId, $contentId);

        $response = $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->getJson(route('admin.attachments.usages', $attachmentId));

        $response->assertOk()
            ->assertJsonCount(1, 'items');

        $this->assertSame('回收站', data_get($response->json(), 'items.0.status_label'));
        $this->assertNull(data_get($response->json(), 'items.0.edit_url'));
        $this->assertNull(data_get($response->json(), 'items.0.view_url'));
    }

    public function test_recycled_article_cannot_be_edited_until_restored(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('recycled-edit-guard-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'type' => 'article',
            'title' => '回收站文章禁止编辑',
            'slug' => 'recycled-article-edit-guard',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $siteAdmin->id,
            'updated_by' => $siteAdmin->id,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.articles.edit', $contentId))
            ->assertForbidden();
    }

    public function test_attachment_manager_is_limited_to_own_attachments_when_sharing_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $attachmentManager = $this->createSiteOperator('attachment-share-uploader', true, 'uploader');
        $editor = $this->createSiteOperator('attachment-share-owner', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->setAttachmentSharing($siteId, false, $attachmentManager->id);
        $this->createSiteAttachment($siteId, $attachmentManager->id, 'uploader-own-resource.pdf');
        $this->createSiteAttachment($siteId, $editor->id, 'uploader-visible-foreign.pdf');

        $this->actingAs($attachmentManager)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.index'))
            ->assertOk()
            ->assertSee('uploader-own-resource.pdf')
            ->assertDontSee('uploader-visible-foreign.pdf');
    }

    public function test_editor_cannot_delete_other_users_attachment_when_sharing_is_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $seedOperator = $this->createPlatformIdentity('attachment-delete-editor-seeder');
        $channelId = $this->createSiteChannel($siteId, 'attachment-delete-editor-channel', '附件删除测试栏目', $seedOperator->id);
        $editor = $this->createRestrictedContentOperator('attachment-delete-editor', $siteId, [$channelId]);
        $otherEditor = $this->createRestrictedContentOperator('attachment-delete-owner', $siteId, [$channelId]);

        $this->setAttachmentSharing($siteId, false, $editor->id);
        $foreignAttachmentId = $this->createSiteAttachment($siteId, $otherEditor->id, 'delete-foreign-hidden.pdf');

        $this->actingAs($editor)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.attachments.destroy', $foreignAttachmentId))
            ->assertNotFound();

        $this->assertTrue(
            DB::table('attachments')->where('id', $foreignAttachmentId)->exists(),
            '附件共享关闭时，普通编辑者不应删除他人上传的附件。',
        );
    }

    public function test_site_operator_cannot_open_article_from_unbound_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('content-checker', true, 'editor');
        $boundSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $unboundSiteId = $this->createAdditionalSite('remote-content-site', '远程内容站点');

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $unboundSiteId,
            'type' => 'article',
            'title' => '跨站点测试文章',
            'slug' => 'cross-site-article',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $boundSiteId])
            ->get(route('admin.articles.edit', $contentId))
            ->assertNotFound();
    }

    public function test_site_operator_can_only_see_own_content_even_when_sharing_same_channel(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $channelId = $this->createSiteChannel($siteId, 'shared-operator-channel', '共享栏目', $this->createPlatformIdentity('shared-channel-seeder')->id);
        $operatorA = $this->createRestrictedContentOperator('shared-content-operator-a', $siteId, [$channelId]);
        $operatorB = $this->createRestrictedContentOperator('shared-content-operator-b', $siteId, [$channelId]);

        $ownContentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '操作员A自己的文章',
            'slug' => 'operator-a-own-article',
            'summary' => 'A own',
            'content' => '<p>A own</p>',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $operatorA->id,
            'updated_by' => $operatorA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherContentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '操作员B的文章',
            'slug' => 'operator-b-article',
            'summary' => 'B own',
            'content' => '<p>B own</p>',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $operatorB->id,
            'updated_by' => $operatorB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operatorA)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee('操作员A自己的文章')
            ->assertDontSee('操作员B的文章');

        $this->actingAs($operatorA)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.articles.edit', $otherContentId))
            ->assertNotFound();

        $this->assertSame(
            $ownContentId,
            (int) DB::table('contents')->where('id', $ownContentId)->value('id'),
        );
    }

    public function test_site_operator_can_see_all_articles_in_manageable_channel_when_article_sharing_is_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $channelId = $this->createSiteChannel($siteId, 'article-sharing-visible-channel', '文章共享可见栏目', $this->createPlatformIdentity('article-sharing-channel-seeder')->id);
        $operatorA = $this->createRestrictedContentOperator('article-sharing-operator-a', $siteId, [$channelId]);
        $operatorB = $this->createRestrictedContentOperator('article-sharing-operator-b', $siteId, [$channelId]);

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'content.article_share_enabled'],
            [
                'setting_value' => '1',
                'autoload' => 1,
                'updated_by' => $operatorA->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $otherContentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '共享开启后可见的文章',
            'slug' => 'article-sharing-enabled-visible-article',
            'summary' => 'sharing enabled',
            'content' => '<p>sharing enabled</p>',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $operatorB->id,
            'updated_by' => $operatorB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operatorA)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee('共享开启后可见的文章');

        $this->actingAs($operatorA)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.articles.edit', $otherContentId))
            ->assertOk()
            ->assertSee('共享开启后可见的文章');

        $this->actingAs($operatorA)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.content-preview.article', $otherContentId))
            ->assertOk()
            ->assertSee('共享开启后可见的文章');
    }

    public function test_restricted_operator_update_preserves_existing_unmanageable_content_channels(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $seedOperator = $this->createPlatformIdentity('content-channel-preserve-seeder');
        $visibleChannelId = $this->createSiteChannel($siteId, 'content-visible-channel', '可操作栏目', $seedOperator->id);
        $hiddenChannelId = $this->createSiteChannel($siteId, 'content-hidden-channel', '隐藏关联栏目', $seedOperator->id);
        $operator = $this->createRestrictedContentOperator('content-channel-preserve-operator', $siteId, [$visibleChannelId]);

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $hiddenChannelId,
            'type' => 'article',
            'title' => '多栏目文章',
            'slug' => 'multi-channel-article',
            'summary' => '多栏目测试',
            'content' => '<p>原始正文</p>',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('content_channels')->insert([
            [
                'content_id' => $contentId,
                'channel_id' => $hiddenChannelId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'content_id' => $contentId,
                'channel_id' => $visibleChannelId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.articles.update', $contentId), [
                'channel_ids' => [$visibleChannelId],
                'title' => '多栏目文章已修改',
                'summary' => '多栏目测试',
                'cover_image' => '',
                'content' => '<p>修改后的正文</p>',
                'author' => 'Restricted Editor',
                'source' => '本站',
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.articles.edit', $contentId))
            ->assertSessionHas('status', '文章已更新。');

        $channelIds = DB::table('content_channels')
            ->where('content_id', $contentId)
            ->orderBy('channel_id')
            ->pluck('channel_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame([$visibleChannelId, $hiddenChannelId], $channelIds);
        $this->assertSame(
            $hiddenChannelId,
            (int) DB::table('contents')->where('id', $contentId)->value('channel_id'),
            '当前主栏目如果属于不可操作的既有关联，应在普通修改时保持不变。',
        );
    }

    public function test_article_sharing_enabled_does_not_expose_other_operator_pages(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $channelId = $this->createSiteChannel($siteId, 'page-sharing-restricted-channel', '页面共享限制栏目', $this->createPlatformIdentity('page-sharing-channel-seeder')->id);
        $operatorA = $this->createRestrictedContentOperator('page-sharing-operator-a', $siteId, [$channelId]);
        $operatorB = $this->createRestrictedContentOperator('page-sharing-operator-b', $siteId, [$channelId]);

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'content.article_share_enabled'],
            [
                'setting_value' => '1',
                'autoload' => 1,
                'updated_by' => $operatorA->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $otherPageId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'page',
            'title' => '共享开启后仍不可见的单页面',
            'slug' => 'page-sharing-still-restricted',
            'summary' => 'page restricted',
            'content' => '<p>page restricted</p>',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $operatorB->id,
            'updated_by' => $operatorB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operatorA)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.pages.index'))
            ->assertOk()
            ->assertDontSee('共享开启后仍不可见的单页面');

        $this->actingAs($operatorA)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.pages.edit', $otherPageId))
            ->assertNotFound();

        $this->actingAs($operatorA)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.content-preview.page', $otherPageId))
            ->assertNotFound();
    }

    public function test_site_admin_can_see_other_operators_content_in_manageable_channel(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('shared-content-site-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $channelId = $this->createSiteChannel($siteId, 'site-admin-visible-channel', '站点管理员可见栏目', $siteAdmin->id);
        $contentOperator = $this->createRestrictedContentOperator('shared-content-editor', $siteId, [$channelId]);

        $foreignContentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '普通操作员文章',
            'slug' => 'editor-owned-article',
            'summary' => 'editor content',
            'content' => '<p>editor content</p>',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $contentOperator->id,
            'updated_by' => $contentOperator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee('普通操作员文章');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.articles.edit', $foreignContentId))
            ->assertOk();
    }

    public function test_site_operator_cannot_open_channel_from_unbound_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('channel-checker', true, 'site_admin');
        $boundSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $unboundSiteId = $this->createAdditionalSite('remote-channel-site', '远程栏目站点');

        $channelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $unboundSiteId,
            'name' => '远程测试栏目',
            'slug' => 'remote-channel',
            'type' => 'list',
            'path' => '/remote-channel',
            'depth' => 0,
            'sort' => 0,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $boundSiteId])
            ->get(route('admin.channels.edit', $channelId))
            ->assertNotFound();
    }

    public function test_site_operator_bulk_channel_delete_does_not_touch_unbound_site_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('channel-bulk-checker', true, 'site_admin');
        $boundSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $unboundSiteId = $this->createAdditionalSite('bulk-channel-remote-site', '远程栏目批量站点');

        $channelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $unboundSiteId,
            'name' => '远程批量栏目',
            'slug' => 'bulk-remote-channel',
            'type' => 'list',
            'path' => '/bulk-remote-channel',
            'depth' => 0,
            'sort' => 0,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $boundSiteId])
            ->post(route('admin.channels.bulk'), [
                'action' => 'delete',
                'ids' => [$channelId],
            ])
            ->assertRedirect(route('admin.channels.index'));

        $this->assertTrue(
            DB::table('channels')->where('id', $channelId)->exists(),
            '跨站点栏目不应被批量删除。',
        );
    }

    public function test_site_operator_dashboard_reads_platform_notices_from_main_site_only(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('notice-checker', true, 'editor');
        $boundSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('third-school', '第三示例学校');
        $noticeChannelId = (int) DB::table('channels')
            ->where('site_id', 1)
            ->where('slug', 'platform-notices')
            ->value('id');

        DB::table('contents')->insert([
            [
                'site_id' => 1,
                'channel_id' => $noticeChannelId,
                'type' => 'article',
                'title' => '平台统一维护通知',
                'status' => 'published',
                'audit_status' => 'approved',
                'created_by' => $operator->id,
                'updated_by' => $operator->id,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => 1,
                'channel_id' => null,
                'type' => 'article',
                'title' => '主网站普通动态',
                'status' => 'published',
                'audit_status' => 'approved',
                'created_by' => $operator->id,
                'updated_by' => $operator->id,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $otherSiteId,
                'channel_id' => null,
                'type' => 'article',
                'title' => '第三站点私有通知',
                'status' => 'published',
                'audit_status' => 'approved',
                'created_by' => $operator->id,
                'updated_by' => $operator->id,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $boundSiteId])
            ->get(route('admin.site-dashboard'))
            ->assertOk()
            ->assertSee('平台公告')
            ->assertSee('平台统一维护通知')
            ->assertViewHas('platformNotices', function ($items): bool {
                $titles = collect($items)->pluck('title')->all();

                return in_array('平台统一维护通知', $titles, true)
                    && ! in_array('主网站普通动态', $titles, true)
                    && ! in_array('第三站点私有通知', $titles, true);
            });
    }

    public function test_site_operator_dashboard_notice_link_always_points_to_main_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $otherSiteId = $this->createAdditionalSite('second-bound-site', '第二绑定站点');
        $editorRoleId = (int) DB::table('site_roles')->where('code', 'editor')->value('id');

        $operator = User::query()->create([
            'username' => 'platform-notice-link-checker',
            'name' => 'Platform Notice Link Checker',
            'email' => 'platform-notice-link-checker@example.com',
            'password' => 'ChangeMe123!',
            'status' => 1,
        ]);

        DB::table('site_user_roles')->insert([
            'site_id' => $otherSiteId,
            'user_id' => $operator->id,
            'role_id' => $editorRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($operator)
            ->withSession(['current_site_id' => $otherSiteId])
            ->get(route('admin.site-dashboard'));

        $response->assertOk()
            ->assertViewHas('platformNoticeSiteKey', 'site');
    }

    public function test_site_logs_only_show_current_site_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('site-log-checker', true, 'site_admin');
        $boundSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('remote-log-site', '远程日志站点');
        $localContentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $boundSiteId,
            'type' => 'article',
            'title' => '本站日志目标文章',
            'slug' => 'local-log-target',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('operation_logs')->insert([
            [
                'scope' => 'site',
                'module' => 'content',
                'action' => 'publish',
                'site_id' => $boundSiteId,
                'user_id' => $operator->id,
                'target_type' => 'content',
                'target_id' => $localContentId,
                'payload' => json_encode(['title' => '本站日志'], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'scope' => 'site',
                'module' => 'content',
                'action' => 'delete',
                'site_id' => $otherSiteId,
                'user_id' => $operator->id,
                'target_type' => 'content',
                'target_id' => 2,
                'payload' => json_encode(['title' => '外站日志'], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $boundSiteId])
            ->get(route('admin.site-logs.index'))
            ->assertOk()
            ->assertSee('站点日志')
            ->assertSee('内容 · 本站日志目标文章 #' . $localContentId)
            ->assertViewHas('logs', function ($logs): bool {
                return $logs->count() === 1
                    && $logs->first()->action === 'publish';
            });
    }

    public function test_platform_logs_show_readable_target_names(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $siteName = (string) DB::table('sites')->where('id', $siteId)->value('name');

        DB::table('operation_logs')->insert([
            [
                'scope' => 'platform',
                'module' => 'site',
                'action' => 'update',
                'site_id' => null,
                'user_id' => $user->id,
                'target_type' => 'site',
                'target_id' => $siteId,
                'payload' => json_encode(['name' => $siteName], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'scope' => 'platform',
                'module' => 'user',
                'action' => 'update',
                'site_id' => null,
                'user_id' => $user->id,
                'target_type' => 'user',
                'target_id' => $user->id,
                'payload' => json_encode(['name' => $user->name], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($user)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.logs.index'))
            ->assertOk()
            ->assertSee('站点 · ' . $siteName . ' #' . $siteId)
            ->assertSee('管理员 · ' . $user->name . ' #' . $user->id);
    }

    public function test_site_logs_show_operator_target_label_for_site_user_and_auth_logs(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('site-log-operator-label', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('operation_logs')->insert([
            [
                'scope' => 'site',
                'module' => 'auth',
                'action' => 'login',
                'site_id' => $siteId,
                'user_id' => $operator->id,
                'target_type' => 'user',
                'target_id' => $operator->id,
                'payload' => json_encode(['username' => $operator->username], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'scope' => 'site',
                'module' => 'site_user',
                'action' => 'update',
                'site_id' => $siteId,
                'user_id' => $operator->id,
                'target_type' => 'user',
                'target_id' => $operator->id,
                'payload' => json_encode(['username' => $operator->username], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-logs.index'))
            ->assertOk()
            ->assertSee('操作员 · ' . $operator->name . ' #' . $operator->id)
            ->assertDontSee('管理员 · ' . $operator->name . ' #' . $operator->id);
    }

    public function test_site_logs_translate_legacy_role_target_type_into_site_role_label(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('site-log-role-label', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $roleId = (int) DB::table('site_roles')->where('code', 'editor')->value('id');
        $roleName = (string) DB::table('site_roles')->where('id', $roleId)->value('name');

        DB::table('operation_logs')->insert([
            'scope' => 'site',
            'module' => 'site_role',
            'action' => 'update',
            'site_id' => $siteId,
            'user_id' => $operator->id,
            'target_type' => 'role',
            'target_id' => $roleId,
            'payload' => json_encode(['role_name' => $roleName], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-logs.index'))
            ->assertOk()
            ->assertSee('站点角色 · ' . $roleName . ' #' . $roleId)
            ->assertDontSee('role #' . $roleId, false);
    }

    public function test_platform_logs_show_readable_attachment_target_names(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $attachmentId = (int) DB::table('attachments')->insertGetId([
            'site_id' => $siteId,
            'origin_name' => '平台日志附件.png',
            'stored_name' => 'platform-log-attachment.png',
            'disk' => 'site',
            'path' => 'web/site/media/attachments/2026/04/platform-log-attachment.png',
            'url' => '/site-media/site/attachments/2026/04/platform-log-attachment.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size' => 1024,
            'sha1' => sha1('platform-log-attachment'),
            'uploaded_by' => $user->id,
            'usage_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('operation_logs')->insert([
            'scope' => 'platform',
            'module' => 'attachment',
            'action' => 'delete',
            'site_id' => $siteId,
            'user_id' => $user->id,
            'target_type' => 'attachment',
            'target_id' => $attachmentId,
            'payload' => json_encode(['name' => '平台日志附件.png'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.logs.index'))
            ->assertOk()
            ->assertSee('资源 · 平台日志附件.png #' . $attachmentId);
    }

    public function test_site_settings_only_load_current_site_information(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('settings-scope-checker', true, 'site_admin');
        $boundSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('settings-remote-site', '远程设置站点');

        DB::table('sites')->where('id', $otherSiteId)->update([
            'name' => '远程设置站点',
            'contact_phone' => '020-12345678',
            'contact_email' => 'remote-settings@example.com',
            'address' => '远程站点地址',
            'seo_title' => '远程SEO标题',
            'updated_at' => now(),
        ]);

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $otherSiteId, 'setting_key' => 'site.filing_number'],
            [
                'setting_value' => '远程备案号',
                'autoload' => 1,
                'updated_by' => $operator->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $boundSiteId])
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertViewHas('currentSite', fn ($site): bool => (int) $site->id === $boundSiteId)
            ->assertSee('示例学校')
            ->assertDontSee('远程设置站点')
            ->assertDontSee('远程备案号');
    }

    public function test_recycle_bin_only_lists_deleted_content_from_current_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('recycle-scope-checker', true, 'editor');
        $boundSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('recycle-remote-site', '远程回收列表站点');

        DB::table('contents')->insert([
            [
                'site_id' => $boundSiteId,
                'type' => 'article',
                'title' => '本站回收文章',
                'slug' => 'local-recycle-entry',
                'status' => 'draft',
                'audit_status' => 'draft',
                'deleted_at' => now(),
                'created_by' => $operator->id,
                'updated_by' => $operator->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $otherSiteId,
                'type' => 'article',
                'title' => '外站回收文章',
                'slug' => 'foreign-recycle-entry',
                'status' => 'draft',
                'audit_status' => 'draft',
                'deleted_at' => now(),
                'created_by' => $operator->id,
                'updated_by' => $operator->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $boundSiteId])
            ->get(route('admin.recycle-bin.index'))
            ->assertOk()
            ->assertSee('本站回收文章')
            ->assertDontSee('外站回收文章')
            ->assertViewHas('deletedContents', function ($contents): bool {
                return collect($contents->items())->pluck('title')->contains('本站回收文章')
                    && ! collect($contents->items())->pluck('title')->contains('外站回收文章');
            });
    }

    public function test_site_operator_bulk_publish_does_not_touch_unbound_site_articles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('content-bulk-checker', true, 'reviewer');
        $boundSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $unboundSiteId = $this->createAdditionalSite('bulk-content-remote-site', '远程内容批量站点');

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $unboundSiteId,
            'type' => 'article',
            'title' => '远程批量文章',
            'slug' => 'bulk-remote-article',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $boundSiteId])
            ->post(route('admin.articles.bulk'), [
                'action' => 'publish',
                'ids' => [$contentId],
            ])
            ->assertRedirect(route('admin.articles.index'));

        $content = DB::table('contents')->where('id', $contentId)->first();

        $this->assertSame('draft', $content->status, '跨站点文章不应被批量发布。');
        $this->assertSame('draft', $content->audit_status, '跨站点文章审核状态不应被批量变更。');
    }

    public function test_site_admin_can_create_channel_with_hyphenated_slug(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('channel-slug-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.channels.store'), [
                'name' => '平台公告',
                'slug' => 'school-news-feed',
                'type' => 'list',
                'parent_id' => '',
                'is_nav' => '1',
                'list_template' => '',
                'detail_template' => '',
            ])
            ->assertRedirect(route('admin.channels.index'))
            ->assertSessionHas('status', '栏目已创建。');

        $this->assertDatabaseHas('channels', [
            'site_id' => $siteId,
            'name' => '平台公告',
            'slug' => 'school-news-feed',
            'path' => '/school-news-feed',
        ]);
    }

    public function test_site_admin_channel_slugify_returns_legal_slug_for_chinese_name(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('channel-slugify-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $response = $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->getJson(route('admin.channels.slugify', ['name' => '上大分']));

        $response
            ->assertOk()
            ->assertJsonPath('slug', 'shangdafen');
    }

    public function test_editor_role_cannot_access_site_logs_or_settings_or_theme_editor(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('limited-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-logs.index'))
            ->assertForbidden();

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.settings.index'))
            ->assertForbidden();

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.themes.editor'))
            ->assertForbidden();
    }

    public function test_template_editor_role_can_access_theme_pages_but_not_site_settings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('template-editor-user', true, 'template_editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.themes.index'))
            ->assertOk();

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.themes.editor'))
            ->assertOk();

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }

    public function test_site_dashboard_quick_links_follow_role_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $channelId = $this->createSiteChannel($siteId, 'dashboard-quick-channel', '工作台快速栏目', $this->createPlatformIdentity('dashboard-quick-seeder')->id);
        $editor = $this->createRestrictedContentOperator('dashboard-quick-editor', $siteId, [$channelId]);
        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '工作台链接测试文章',
            'status' => 'published',
            'audit_status' => 'approved',
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now()->addMinute(),
        ]);

        $this->actingAs($editor)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-dashboard'))
            ->assertOk()
            ->assertSee('文章管理')
            ->assertSee('单页面管理')
            ->assertSee('回收站')
            ->assertSee(route('admin.articles.edit', $contentId), false)
            ->assertDontSee('模板管理')
            ->assertDontSee('站点设置')
            ->assertDontSee('站点日志')
            ->assertDontSee('操作员管理');

        $templateEditor = $this->createSiteOperator('dashboard-quick-template', true, 'template_editor');

        $this->actingAs($templateEditor)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-dashboard'))
            ->assertOk()
            ->assertSee('模板管理')
            ->assertDontSee(route('admin.articles.edit', $contentId), false)
            ->assertDontSee('站点设置');
    }

    public function test_site_dashboard_scopes_recent_content_and_counts_to_manageable_channels(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $operatorSeed = $this->createPlatformIdentity('dashboard-channel-seeder');
        $allowedChannelId = $this->createSiteChannel($siteId, 'dashboard-allowed', '工作台允许栏目', $operatorSeed->id);
        $blockedChannelId = $this->createSiteChannel($siteId, 'dashboard-blocked', '工作台限制栏目', $operatorSeed->id);
        $operator = $this->createRestrictedContentOperator('dashboard-restricted-user', $siteId, [$allowedChannelId]);

        DB::table('contents')->insert([
            [
                'site_id' => $siteId,
                'channel_id' => $allowedChannelId,
                'type' => 'article',
                'title' => '允许栏目文章',
                'status' => 'draft',
                'audit_status' => 'draft',
                'published_at' => null,
                'created_by' => $operator->id,
                'updated_by' => $operator->id,
                'created_at' => now(),
                'updated_at' => now()->addMinutes(2),
            ],
            [
                'site_id' => $siteId,
                'channel_id' => $blockedChannelId,
                'type' => 'article',
                'title' => '限制栏目文章',
                'status' => 'published',
                'audit_status' => 'approved',
                'created_by' => $operator->id,
                'updated_by' => $operator->id,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now()->addMinutes(5),
            ],
        ]);

        DB::table('site_visit_daily_stats')->insert([
            'site_id' => $siteId,
            'stat_date' => now()->toDateString(),
            'page_views' => 16,
            'article_views' => 8,
            'channel_views' => 4,
            'home_views' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-dashboard'))
            ->assertOk()
            ->assertSee('允许栏目文章')
            ->assertSee('近 7 天访问趋势')
            ->assertViewHas('recentContents', function ($items): bool {
                $titles = collect($items)->pluck('title')->all();

                return in_array('允许栏目文章', $titles, true)
                    && ! in_array('限制栏目文章', $titles, true);
            })
            ->assertViewHas('insights', function (array $insights): bool {
                return isset($insights['hero'][0]['value'], $insights['trend'][0]['value'], $insights['assets']['unused_ratio'])
                    && $insights['hero'][0]['value'] === '16';
            });
    }

    public function test_site_security_blocks_sql_injection_and_records_stats(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->get('/?site=site&keyword='.urlencode('union select 1'))
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截')
            ->assertSee('SQL 注入拦截')
            ->assertDontSee('无权访问当前页面');

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $siteId,
            'blocked_total' => 1,
            'blocked_sql_injection' => 1,
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'sql_injection',
            'rule_name' => 'SQL 注入拦截',
            'request_path' => '/',
            'request_method' => 'GET',
        ]);
    }

    public function test_site_security_blocks_frequent_refresh_requests(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        foreach ([
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '2',
            'security.rate_limit_sensitive_max_requests' => '1',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $this->get('/?site=site')->assertOk();
        $this->get('/?site=site')->assertOk();
        $this->get('/?site=site')->assertForbidden();

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $siteId,
            'blocked_total' => 1,
            'blocked_rate_limit' => 1,
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'rate_limit',
            'rule_name' => '频繁刷新拦截',
        ]);
    }

    public function test_site_media_frequent_refresh_is_counted_by_lightweight_security_guard(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        foreach ([
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '2',
            'security.rate_limit_sensitive_max_requests' => '1',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $mediaPath = storage_path('app/web/site/media/attachments/2026/04/security-rate-limit.jpg');
        File::ensureDirectoryExists(dirname($mediaPath));
        File::put($mediaPath, 'security-media');

        $this->get('/site-media/site/attachments/2026/04/security-rate-limit.jpg')->assertOk();
        $this->get('/site-media/site/attachments/2026/04/security-rate-limit.jpg')->assertOk();
        $this->get('/site-media/site/attachments/2026/04/security-rate-limit.jpg')->assertForbidden();

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $siteId,
            'blocked_total' => 1,
            'blocked_rate_limit' => 1,
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'rate_limit',
            'request_path' => '/site-media/site/attachments/2026/04/security-rate-limit.jpg',
            'request_method' => 'GET',
        ]);
    }

    public function test_site_admin_can_view_security_page_and_only_see_current_site_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('security-remote-site', '远程安全站点');

        DB::table('site_security_daily_stats')->insert([
            [
                'site_id' => $mainSiteId,
                'stat_date' => now()->toDateString(),
                'blocked_total' => 12,
                'blocked_bad_path' => 4,
                'blocked_sql_injection' => 3,
                'blocked_xss' => 2,
                'blocked_path_traversal' => 1,
                'blocked_bad_upload' => 1,
                'blocked_rate_limit' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $otherSiteId,
                'stat_date' => now()->toDateString(),
                'blocked_total' => 88,
                'blocked_bad_path' => 88,
                'blocked_sql_injection' => 0,
                'blocked_xss' => 0,
                'blocked_path_traversal' => 0,
                'blocked_bad_upload' => 0,
                'blocked_rate_limit' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('site_security_events')->insert([
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'bad_path',
                'rule_name' => '恶意扫描路径',
                'request_path' => '/.env',
                'request_method' => 'GET',
                'ip_hash' => hash('sha256', '127.0.0.1'),
                'created_at' => now(),
            ],
            [
                'site_id' => $otherSiteId,
                'rule_code' => 'bad_path',
                'rule_name' => '恶意扫描路径',
                'request_path' => '/wp-admin',
                'request_method' => 'GET',
                'ip_hash' => hash('sha256', '127.0.0.2'),
                'created_at' => now(),
            ],
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('安护盾')
            ->assertSee('/.env')
            ->assertDontSee('/wp-admin')
            ->assertSee('12')
            ->assertSee('恶意扫描路径')
            ->assertDontSee('127.0.0.2');
    }

    public function test_site_security_page_reflects_disabled_protection_state(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-disabled-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'security.site_protection_enabled'],
            ['setting_value' => '0', 'updated_at' => now(), 'created_at' => now()]
        );

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('未启用');
    }

    public function test_site_security_regions_only_count_recent_seven_day_events(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-region-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_events')->insert([
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'sql_injection',
                'rule_name' => 'SQL 注入拦截',
                'request_path' => '/demo-security/sql',
                'request_method' => 'GET',
                'client_ip' => '8.8.8.8',
                'region_name' => '广东',
                'ip_hash' => hash('sha256', '8.8.8.8'),
                'created_at' => now()->subDays(2),
            ],
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'xss',
                'rule_name' => 'XSS 攻击拦截',
                'request_path' => '/demo-security/xss',
                'request_method' => 'GET',
                'client_ip' => '1.1.1.1',
                'region_name' => '上海',
                'ip_hash' => hash('sha256', '1.1.1.1'),
                'created_at' => now()->subDays(9),
            ],
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('广东')
            ->assertDontSee('上海');
    }

    public function test_site_security_regions_are_not_limited_to_latest_hundred_recent_events(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-region-window-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $rows = [];

        for ($i = 0; $i < 100; $i++) {
            $rows[] = [
                'site_id' => $mainSiteId,
                'rule_code' => 'rate_limit',
                'rule_name' => '频繁刷新拦截',
                'request_path' => '/demo-security/rate-'.$i,
                'request_method' => 'GET',
                'client_ip' => '8.8.8.'.($i % 50 + 1),
                'region_name' => '广东',
                'ip_hash' => hash('sha256', '8.8.8.'.($i % 50 + 1)),
                'created_at' => now()->subDays(1)->subSeconds($i),
            ];
        }

        $rows[] = [
            'site_id' => $mainSiteId,
            'rule_code' => 'sql_injection',
            'rule_name' => 'SQL 注入拦截',
            'request_path' => '/demo-security/sql-beijing',
            'request_method' => 'GET',
            'client_ip' => '9.9.9.9',
            'region_name' => '北京',
            'ip_hash' => hash('sha256', '9.9.9.9'),
            'created_at' => now()->subDays(6),
        ];

        DB::table('site_security_events')->insert($rows);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('广东')
            ->assertSee('北京');
    }

    public function test_site_dashboard_still_renders_for_expiring_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('expiry-warning-operator', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('sites')->where('id', $siteId)->update([
            'expires_at' => now()->addDays(12)->toDateString(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-dashboard'))
            ->assertOk()
            ->assertSee('站点工作台')
            ->assertSee('近 7 天访问趋势');
    }

    public function test_frontend_site_visits_are_recorded_into_daily_stats_and_article_views(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $identity = $this->createPlatformIdentity('visit-tracking-seeder');
        $channelId = $this->createSiteChannel($siteId, 'visit-track-channel', '访问统计栏目', $identity->id);

        $articleId = DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '访问统计文章',
            'status' => 'published',
            'audit_status' => 'approved',
            'published_at' => now(),
            'created_by' => $identity->id,
            'updated_by' => $identity->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('content_channels')->insert([
            'content_id' => $articleId,
            'channel_id' => $channelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('site.home', ['site' => 'site']))
            ->assertOk();

        $this->get(route('site.article', ['site' => 'site', 'id' => $articleId]))
            ->assertOk();

        $this->assertDatabaseHas('site_visit_daily_stats', [
            'site_id' => $siteId,
            'stat_date' => now('Asia/Shanghai')->toDateString(),
            'page_views' => 2,
            'article_views' => 1,
            'home_views' => 1,
        ]);

        $this->assertSame(1, (int) DB::table('contents')->where('id', $articleId)->value('view_count'));
    }

    public function test_uploader_role_cannot_access_recycle_bin_or_theme_pages(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('uploader-only-user', true, 'uploader');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.recycle-bin.index'))
            ->assertForbidden();

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.themes.index'))
            ->assertForbidden();
    }

    public function test_editor_role_can_access_recycle_bin_but_not_theme_list(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('recycle-editor-user', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.recycle-bin.index'))
            ->assertOk();

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.themes.index'))
            ->assertForbidden();
    }

    public function test_site_admin_cannot_open_or_delete_platform_identity_through_site_user_routes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('site-user-guard', true, 'site_admin');
        $platformIdentity = $this->createPlatformIdentity('hidden-platform-user', 'platform_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-users.edit', $platformIdentity->id))
            ->assertNotFound();

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.site-users.destroy', $platformIdentity->id))
            ->assertNotFound();
    }

    public function test_site_admin_cannot_open_custom_role_from_other_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('role-guard-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('role-remote-site', '远程角色站点');

        $foreignRoleId = (int) DB::table('site_roles')->insertGetId([
            'site_id' => $otherSiteId,
            'name' => '远程角色',
            'code' => 'foreign_role_guard',
            'description' => '仅用于越权测试',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-roles.edit', $foreignRoleId))
            ->assertNotFound();

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.site-roles.destroy', $foreignRoleId))
            ->assertNotFound();
    }

    public function test_site_admin_cannot_bind_operator_to_role_from_other_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('role-binding-guard', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('role-bind-remote-site', '远程角色绑定站点');

        $foreignRoleId = (int) DB::table('site_roles')->insertGetId([
            'site_id' => $otherSiteId,
            'name' => '远程绑定角色',
            'code' => 'foreign_bind_role_guard',
            'description' => '仅用于角色绑定越权测试',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $operator = User::query()->create([
            'username' => 'bind-target-operator',
            'name' => 'Bind Target Operator',
            'email' => 'bind-target-operator@example.com',
            'password' => 'ChangeMe123!',
            'status' => 1,
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.site-users.store'), [
                'username' => $operator->username,
                'name' => $operator->name,
                'email' => $operator->email,
                'mobile' => '',
                'status' => 1,
                'password' => 'ChangeMe123!',
                'role_ids' => ['site:' . $foreignRoleId],
            ]);

        $this->assertFalse(
            DB::table('site_user_roles')
                ->where('site_id', $siteId)
                ->where('role_id', $foreignRoleId)
                ->exists(),
            '不应允许将当前站点操作员绑定到其他站点的角色。',
        );
    }

    public function test_site_admin_cannot_update_operator_with_role_from_other_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('role-update-guard', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('role-update-remote-site', '远程角色更新站点');
        $foreignRoleId = (int) DB::table('site_roles')->insertGetId([
            'site_id' => $otherSiteId,
            'name' => '远程更新角色',
            'code' => 'foreign_update_role_guard',
            'description' => '仅用于角色更新越权测试',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $operator = $this->createSiteOperator('role-update-target', true, 'editor');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.site-users.edit', $operator->id))
            ->post(route('admin.site-users.update', $operator->id), [
                'username' => $operator->username,
                'name' => $operator->name,
                'email' => $operator->email,
                'mobile' => '',
                'status' => 1,
                'role_id' => 'site:' . $foreignRoleId,
            ])
            ->assertSessionHasErrors(['role_id']);

        $this->assertFalse(
            DB::table('site_user_roles')
                ->where('site_id', $siteId)
                ->where('user_id', $operator->id)
                ->where('role_id', $foreignRoleId)
                ->exists(),
            '不应允许在更新操作员时绑定其他站点的角色。',
        );
    }

    public function test_editor_role_cannot_update_site_settings_or_switch_theme(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('setting-write-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.settings.update'), [
                'name' => '越权修改站点',
                'contact_phone' => '010-12345678',
            ])
            ->assertForbidden();

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.themes.update'), [
                'theme_code' => 'school_modern',
            ])
            ->assertForbidden();
    }

    public function test_site_admin_can_upload_and_save_site_brand_assets_in_site_settings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('site-setting-brand-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $logoResponse = $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.settings.media-upload'), [
                'slot' => 'logo',
                'file' => UploadedFile::fake()->image('site-logo.png', 240, 240),
            ]);

        $logoResponse->assertOk();
        $logoUrl = $logoResponse->json('url');

        $faviconResponse = $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.settings.media-upload'), [
                'slot' => 'favicon',
                'file' => UploadedFile::fake()->image('site-favicon.png', 64, 64),
            ]);

        $faviconResponse->assertOk();
        $faviconUrl = $faviconResponse->json('url');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.settings.update'), [
                'name' => '示例学校',
                'filing_number' => '',
                'contact_phone' => '010-12345678',
                'contact_email' => '',
                'address' => '示例地址 1 号',
                'logo' => $logoUrl,
                'favicon' => $faviconUrl,
                'seo_title' => '示例学校官网',
                'seo_keywords' => '示例学校,校园',
                'seo_description' => '示例学校官网描述',
                'article_requires_review' => '0',
                'article_share_enabled' => '0',
                'attachment_share_enabled' => '0',
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('status', '站点设置已更新。');

        $site = DB::table('sites')->where('id', $siteId)->first();
        $this->assertSame($logoUrl, $site->logo);
        $this->assertSame($faviconUrl, $site->favicon);
    }

    public function test_site_admin_site_settings_update_sanitizes_submitted_content(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('site-setting-sanitize-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.settings.update'), [
                'name' => "  示例学校\x07  ",
                'filing_number' => "  京 ICP 备 123 号 \u{200B} ",
                'contact_phone' => " 010-12345678 \x07 ",
                'contact_email' => '',
                'address' => "  示例地址\t 1 号  ",
                'logo' => " /site-media/site/media/brand/logo.png \u{200B} ",
                'favicon' => " /site-media/site/media/brand/favicon.ico \u{200B} ",
                'seo_title' => "  示例学校官网 \x07 ",
                'seo_keywords' => " 示例学校, 校园资讯 \u{200B} ",
                'seo_description' => "  示例学校官网描述\t说明  ",
                'article_requires_review' => '1',
                'article_share_enabled' => '1',
                'attachment_share_enabled' => '1',
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('status', '站点设置已更新。');

        $site = DB::table('sites')->where('id', $siteId)->first();
        $this->assertSame('示例学校', $site->name);
        $this->assertSame('010-12345678', $site->contact_phone);
        $this->assertSame('示例地址 1 号', $site->address);
        $this->assertSame('/site-media/site/media/brand/logo.png', $site->logo);
        $this->assertSame('/site-media/site/media/brand/favicon.ico', $site->favicon);
        $this->assertSame('示例学校官网', $site->seo_title);
        $this->assertSame('示例学校, 校园资讯', $site->seo_keywords);
        $this->assertSame('示例学校官网描述 说明', $site->seo_description);
        $this->assertSame(
            '京 ICP 备 123 号',
            DB::table('site_settings')
                ->where('site_id', $siteId)
                ->where('setting_key', 'site.filing_number')
                ->value('setting_value')
        );
        $this->assertSame(
            '1',
            DB::table('site_settings')
                ->where('site_id', $siteId)
                ->where('setting_key', 'content.article_requires_review')
                ->value('setting_value')
        );
        $this->assertSame(
            '1',
            DB::table('site_settings')
                ->where('site_id', $siteId)
                ->where('setting_key', 'content.article_share_enabled')
                ->value('setting_value')
        );
        $this->assertSame(
            '1',
            DB::table('site_settings')
                ->where('site_id', $siteId)
                ->where('setting_key', 'attachment.share_enabled')
                ->value('setting_value')
        );
    }

    public function test_site_admin_cannot_submit_unsafe_brand_asset_paths_in_site_settings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('site-setting-unsafe-path-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.settings.index'))
            ->post(route('admin.settings.update'), [
                'name' => '示例学校',
                'filing_number' => '',
                'contact_phone' => '010-12345678',
                'contact_email' => '',
                'address' => '示例地址 1 号',
                'logo' => 'javascript:alert(1)',
                'favicon' => 'data:image/svg+xml;base64,abc',
                'seo_title' => '示例学校官网',
                'seo_keywords' => '示例学校,校园',
                'seo_description' => '示例学校官网描述',
                'article_requires_review' => '0',
                'article_share_enabled' => '0',
                'attachment_share_enabled' => '0',
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors(['logo', 'favicon']);
    }

    public function test_site_admin_can_update_site_settings_with_cn_contact_email(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('site-setting-cn-email-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.settings.update'), [
                'name' => '示例学校',
                'filing_number' => '',
                'contact_phone' => '010-12345678',
                'contact_email' => 'office@example.edu.cn',
                'address' => '示例地址 1 号',
                'logo' => '',
                'favicon' => '',
                'seo_title' => '示例学校官网',
                'seo_keywords' => '示例学校,校园',
                'seo_description' => '示例学校官网描述',
                'article_requires_review' => '0',
                'article_share_enabled' => '0',
                'attachment_share_enabled' => '0',
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('status', '站点设置已更新。');

        $this->assertSame('office@example.edu.cn', DB::table('sites')->where('id', $siteId)->value('contact_email'));
    }

    public function test_site_admin_cannot_update_site_settings_with_chinese_contact_email(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('site-setting-invalid-email-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.settings.index'))
            ->post(route('admin.settings.update'), [
                'name' => '示例学校',
                'filing_number' => '',
                'contact_phone' => '010-12345678',
                'contact_email' => '测试@测试.cn',
                'address' => '示例地址 1 号',
                'logo' => '',
                'favicon' => '',
                'seo_title' => '示例学校官网',
                'seo_keywords' => '示例学校,校园',
                'seo_description' => '示例学校官网描述',
                'article_requires_review' => '0',
                'article_share_enabled' => '0',
                'attachment_share_enabled' => '0',
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors(['contact_email']);
    }

    public function test_platform_admin_can_update_site_attachment_storage_limit(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->post(route('admin.platform.sites.update', $siteId), [
                'name' => '示例学校',
                'site_key' => 'site',
                'status' => '1',
                'domains' => 'site.test',
                'contact_phone' => '010-12345678',
                'contact_email' => 'school@openai.com',
                'address' => '示例地址 1 号',
                'attachment_storage_limit_mb' => 512,
                'default_theme_id' => '',
                'theme_ids' => [],
                'seo_title' => '示例学校官网',
                'seo_keywords' => '示例学校,校园',
                'seo_description' => '示例学校官网描述',
                'opened_at' => now()->format('Y-m-d'),
                'expires_at' => '',
                'remark' => '站点备注',
                'site_admin_ids' => [],
            ])
            ->assertRedirect(route('admin.platform.sites.edit', $siteId))
            ->assertSessionHas('status', '站点信息已更新。');

        $this->assertSame(
            '512',
            DB::table('site_settings')
                ->where('site_id', $siteId)
                ->where('setting_key', 'attachment.storage_limit_mb')
                ->value('setting_value')
        );
    }

    public function test_platform_admin_site_update_sanitizes_submitted_content(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->post(route('admin.platform.sites.update', $siteId), [
                'name' => "  示例学校\x07  ",
                'site_key' => 'site',
                'status' => '1',
                'domains' => " Site.Test \n\nnews.site.test \n site.test ",
                'contact_phone' => " 010-12345678 \x07 ",
                'contact_email' => '',
                'address' => "  示例地址\t 1 号  ",
                'attachment_storage_limit_mb' => 256,
                'theme_ids' => [],
                'seo_title' => "  示例学校官网 \x07 ",
                'seo_keywords' => " 示例学校, 校园资讯 \u{200B} ",
                'seo_description' => "  示例学校官网描述\t说明  ",
                'opened_at' => now()->format('Y-m-d'),
                'expires_at' => '',
                'remark' => '<script>alert(1)</script><p onclick="alert(2)">欢迎访问</p><a href="javascript:alert(3)" target="_blank">危险链接</a><a href="https://example.com" target="_blank" onclick="alert(4)">安全链接</a>',
                'site_admin_ids' => [],
            ])
            ->assertRedirect(route('admin.platform.sites.edit', $siteId))
            ->assertSessionHas('status', '站点信息已更新。');

        $site = DB::table('sites')->where('id', $siteId)->first();

        $this->assertSame('示例学校', $site->name);
        $this->assertSame('site', $site->site_key);
        $this->assertSame('010-12345678', $site->contact_phone);
        $this->assertNull($site->contact_email);
        $this->assertSame('示例地址 1 号', $site->address);
        $this->assertSame('示例学校官网', $site->seo_title);
        $this->assertSame('示例学校, 校园资讯', $site->seo_keywords);
        $this->assertSame('示例学校官网描述 说明', $site->seo_description);
        $this->assertStringNotContainsString('<script', $site->remark);
        $this->assertStringNotContainsString('onclick=', $site->remark);
        $this->assertStringNotContainsString('javascript:', $site->remark);
        $this->assertStringContainsString('<p>欢迎访问</p>', $site->remark);
        $this->assertStringContainsString('href="https://example.com"', $site->remark);
        $this->assertStringContainsString('rel="noopener noreferrer"', $site->remark);

        $this->assertSame(
            ['site.test', 'news.site.test'],
            DB::table('site_domains')
                ->where('site_id', $siteId)
                ->orderByDesc('is_primary')
                ->orderBy('domain')
                ->pluck('domain')
                ->all()
        );
    }

    public function test_platform_admin_can_update_site_with_cn_contact_email(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->post(route('admin.platform.sites.update', $siteId), [
                'name' => '示例学校',
                'site_key' => 'site',
                'status' => '1',
                'domains' => 'site.test',
                'contact_phone' => '010-12345678',
                'contact_email' => 'school@example.edu.cn',
                'address' => '示例地址 1 号',
                'attachment_storage_limit_mb' => 512,
                'theme_ids' => [],
                'module_ids' => [],
                'seo_title' => '示例学校官网',
                'seo_keywords' => '示例学校,校园',
                'seo_description' => '示例学校官网描述',
                'opened_at' => now()->format('Y-m-d'),
                'expires_at' => '',
                'remark' => '站点备注',
                'site_admin_ids' => [],
            ])
            ->assertRedirect(route('admin.platform.sites.edit', $siteId))
            ->assertSessionHas('status', '站点信息已更新。');

        $this->assertSame('school@example.edu.cn', DB::table('sites')->where('id', $siteId)->value('contact_email'));
    }

    public function test_platform_admin_cannot_update_site_with_chinese_contact_email(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->from(route('admin.platform.sites.edit', $siteId))
            ->post(route('admin.platform.sites.update', $siteId), [
                'name' => '示例学校',
                'site_key' => 'site',
                'status' => '1',
                'domains' => 'site.test',
                'contact_phone' => '010-12345678',
                'contact_email' => '测试@测试.cn',
                'address' => '示例地址 1 号',
                'attachment_storage_limit_mb' => 512,
                'theme_ids' => [],
                'module_ids' => [],
                'seo_title' => '示例学校官网',
                'seo_keywords' => '示例学校,校园',
                'seo_description' => '示例学校官网描述',
                'opened_at' => now()->format('Y-m-d'),
                'expires_at' => '',
                'remark' => '站点备注',
                'site_admin_ids' => [],
            ])
            ->assertRedirect(route('admin.platform.sites.edit', $siteId))
            ->assertSessionHasErrors(['contact_email']);
    }

    public function test_template_editor_can_switch_theme(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('theme-write-user', true, 'template_editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $newTemplateId = (int) DB::table('site_templates')->insertGetId([
            'site_id' => $siteId,
            'name' => 'School Modern',
            'template_key' => 'school_modern',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.themes.update'), [
                'site_template_id' => $newTemplateId,
            ])
            ->assertRedirect(route('admin.themes.index'));

        $this->assertSame(
            $newTemplateId,
            (int) DB::table('sites')->where('id', $siteId)->value('active_site_template_id'),
        );
    }

    public function test_site_admin_cannot_assign_foreign_channel_when_updating_role_scope(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('role-channel-guard', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('foreign-channel-site', '外站栏目站点');
        $editorRoleId = (int) DB::table('site_roles')->where('code', 'editor')->value('id');
        $foreignChannelId = $this->createSiteChannel($otherSiteId, 'foreign-guard-channel', '外站栏目', $siteAdmin->id);
        $contentPermissionId = (int) DB::table('site_permissions')->where('code', 'content.manage')->value('id');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.site-roles.update', $editorRoleId), [
                'permission_ids' => [$contentPermissionId],
                'channel_ids' => [$foreignChannelId],
            ])
            ->assertRedirect(route('admin.site-roles.edit', $editorRoleId));

        $this->assertTrue(
            DB::table('site_role_permissions')
                ->where('site_id', $siteId)
                ->where('role_id', $editorRoleId)
                ->where('permission_id', $contentPermissionId)
                ->exists(),
            '角色更新应仅按当前权限配置生效，忽略已废弃的栏目范围载荷。',
        );
    }

    public function test_site_admin_role_is_locked_for_update(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('locked-site-admin-role-update', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $siteAdminRoleId = (int) DB::table('site_roles')->where('code', 'site_admin')->value('id');
        $beforePermissionIds = DB::table('site_role_permissions')
            ->where('site_id', $siteId)
            ->where('role_id', $siteAdminRoleId)
            ->pluck('permission_id')
            ->all();

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.site-roles.update', $siteAdminRoleId), [
                'permission_ids' => [],
                'channel_ids' => [],
            ])
            ->assertRedirect(route('admin.site-roles.edit', $siteAdminRoleId))
            ->assertSessionHas('status', '站点管理员为系统内置核心角色，不支持编辑。');

        $this->assertSame(
            $beforePermissionIds,
            DB::table('site_role_permissions')
                ->where('site_id', $siteId)
                ->where('role_id', $siteAdminRoleId)
                ->pluck('permission_id')
                ->all(),
            '站点管理员角色不应被更新权限。',
        );
    }

    public function test_site_admin_role_is_locked_for_delete(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('locked-site-admin-role-delete', true, 'site_admin');
        $siteAdminRoleId = (int) DB::table('site_roles')->where('code', 'site_admin')->value('id');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => (int) DB::table('sites')->where('site_key', 'site')->value('id')])
            ->post(route('admin.site-roles.destroy', $siteAdminRoleId))
            ->assertRedirect(route('admin.site-roles.index'))
            ->assertSessionHas('status', '站点管理员为系统内置核心角色，不支持删除。');

        $this->assertTrue(
            DB::table('site_roles')->where('id', $siteAdminRoleId)->exists(),
            '站点管理员角色不应被删除。',
        );
    }

    public function test_site_admin_cannot_restore_or_force_delete_recycle_item_from_other_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('recycle-guard-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('remote-recycle-site', '远程回收站点');

        $foreignContentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $otherSiteId,
            'type' => 'article',
            'title' => '远程已删除文章',
            'slug' => 'remote-recycle-article',
            'status' => 'draft',
            'audit_status' => 'draft',
            'deleted_at' => now(),
            'created_by' => $siteAdmin->id,
            'updated_by' => $siteAdmin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.recycle-bin.restore', $foreignContentId))
            ->assertNotFound();

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.recycle-bin.destroy', $foreignContentId))
            ->assertNotFound();
    }

    public function test_site_admin_can_empty_current_site_recycle_bin_without_touching_other_sites(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('recycle-empty-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('remote-empty-recycle-site', '远程清空回收站点');

        $localDeletedContentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'type' => 'article',
            'title' => '本站待清空文章',
            'slug' => 'local-empty-recycle-article',
            'status' => 'draft',
            'audit_status' => 'draft',
            'deleted_at' => now(),
            'created_by' => $siteAdmin->id,
            'updated_by' => $siteAdmin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $foreignDeletedContentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $otherSiteId,
            'type' => 'article',
            'title' => '外站待保留文章',
            'slug' => 'foreign-empty-recycle-article',
            'status' => 'draft',
            'audit_status' => 'draft',
            'deleted_at' => now(),
            'created_by' => $siteAdmin->id,
            'updated_by' => $siteAdmin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.recycle-bin.empty'))
            ->assertRedirect(route('admin.recycle-bin.index'))
            ->assertSessionHas('status', '回收站已清空。');

        $this->assertFalse(
            DB::table('contents')->where('id', $localDeletedContentId)->exists(),
            '当前站点回收站内容应被彻底删除。',
        );
        $this->assertTrue(
            DB::table('contents')->where('id', $foreignDeletedContentId)->exists(),
            '其他站点回收站内容不应被清空。',
        );
    }

    public function test_site_admin_bulk_recycle_actions_do_not_touch_other_site_items(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('recycle-bulk-guard-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('remote-bulk-recycle-site', '远程批量回收站点');

        $foreignContentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $otherSiteId,
            'type' => 'article',
            'title' => '远程批量已删除文章',
            'slug' => 'remote-bulk-recycle-article',
            'status' => 'draft',
            'audit_status' => 'draft',
            'deleted_at' => now(),
            'created_by' => $siteAdmin->id,
            'updated_by' => $siteAdmin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.recycle-bin.bulk'), [
                'action' => 'restore',
                'ids' => [$foreignContentId],
            ])
            ->assertRedirect(route('admin.recycle-bin.index'));

        $this->assertNotNull(
            DB::table('contents')->where('id', $foreignContentId)->value('deleted_at'),
            '跨站点回收站内容不应被批量恢复。',
        );

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.recycle-bin.bulk'), [
                'action' => 'delete',
                'ids' => [$foreignContentId],
            ])
            ->assertRedirect(route('admin.recycle-bin.index'));

        $this->assertTrue(
            DB::table('contents')->where('id', $foreignContentId)->exists(),
            '跨站点回收站内容不应被批量彻底删除。',
        );
    }

    public function test_site_admin_cannot_update_or_delete_operator_from_other_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('operator-guard-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('remote-operator-site', '远程操作员站点');
        $foreignOperator = $this->createSiteOperator('foreign-operator-user', false);
        $editorRoleId = (int) DB::table('site_roles')->where('code', 'editor')->value('id');

        DB::table('site_user_roles')->insert([
            'site_id' => $otherSiteId,
            'user_id' => $foreignOperator->id,
            'role_id' => $editorRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.site-users.update', $foreignOperator->id), [
                'username' => 'foreign-operator-user',
                'name' => 'Foreign Operator User',
                'email' => 'foreign-operator-user@example.com',
                'mobile' => '',
                'status' => 1,
                'role_ids' => [$editorRoleId],
            ])
            ->assertNotFound();

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.site-users.destroy', $foreignOperator->id))
            ->assertNotFound();
    }

    public function test_site_admin_can_update_own_profile_without_role_payload(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('self-edit-site-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $originalRoleIds = DB::table('site_user_roles')
            ->where('site_id', $siteId)
            ->where('user_id', $siteAdmin->id)
            ->pluck('role_id')
            ->all();

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.site-users.edit', $siteAdmin->id))
            ->post(route('admin.site-users.update', $siteAdmin->id), [
                'username' => $siteAdmin->username,
                'name' => 'Self Edit Site Admin Updated',
                'email' => $siteAdmin->email,
                'mobile' => '13800138000',
                'remark' => '<p>交接备注已更新</p>',
                'status' => 1,
            ])
            ->assertRedirect(route('admin.site-users.edit', ['user' => $siteAdmin->id, 'site_id' => $siteId]))
            ->assertSessionHas('status', '站点账号已更新。');

        $this->assertSame(
            'Self Edit Site Admin Updated',
            DB::table('users')->where('id', $siteAdmin->id)->value('name'),
            '编辑自己的操作员资料时应成功保存基础信息。',
        );

        $this->assertSame(
            $originalRoleIds,
            DB::table('site_user_roles')
                ->where('site_id', $siteId)
                ->where('user_id', $siteAdmin->id)
                ->pluck('role_id')
                ->all(),
            '编辑自己的操作员资料时不应篡改既有角色绑定。',
        );

        $this->assertSame(
            1,
            (int) DB::table('users')->where('id', $siteAdmin->id)->value('status'),
            '编辑自己的操作员资料时不应意外改动账号状态。',
        );

        $this->assertSame(
            '<p>交接备注已更新</p>',
            DB::table('users')->where('id', $siteAdmin->id)->value('remark'),
            '编辑操作员资料时应成功保存备注信息。',
        );
    }

    public function test_site_admin_cannot_change_own_username(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('self-edit-username-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.site-users.edit', $siteAdmin->id))
            ->post(route('admin.site-users.update', $siteAdmin->id), [
                'username' => 'mutated-self-username',
                'name' => 'Self Edit Username Protected',
                'email' => $siteAdmin->email,
                'mobile' => '13800138111',
                'status' => 1,
            ])
            ->assertRedirect(route('admin.site-users.edit', ['user' => $siteAdmin->id, 'site_id' => $siteId]))
            ->assertSessionHas('status', '站点账号已更新。');

        $this->assertSame(
            'self-edit-username-admin',
            DB::table('users')->where('id', $siteAdmin->id)->value('username'),
            '编辑自己的操作员资料时不应允许修改自己的用户名。',
        );
    }

    public function test_site_operator_can_edit_own_profile_without_user_manage_permission(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('self-edit-operator', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-users.edit', $operator->id))
            ->assertOk();

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.site-users.edit', $operator->id))
            ->post(route('admin.site-users.update', $operator->id), [
                'username' => $operator->username,
                'name' => 'Self Edit Operator Updated',
                'email' => $operator->email,
                'mobile' => '13800138222',
                'remark' => '<p>操作员个人资料已更新</p>',
                'status' => 1,
            ])
            ->assertRedirect(route('admin.site-users.edit', ['user' => $operator->id, 'site_id' => $siteId]))
            ->assertSessionHas('status', '站点账号已更新。');

        $this->assertSame(
            'Self Edit Operator Updated',
            DB::table('users')->where('id', $operator->id)->value('name'),
            '普通操作员应能够更新自己的基础资料。',
        );

        $this->assertSame(
            '<p>操作员个人资料已更新</p>',
            DB::table('users')->where('id', $operator->id)->value('remark'),
            '普通操作员应能够更新自己的备注信息。',
        );
    }

    public function test_self_edit_profile_preserves_managed_channels(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $seedOperator = $this->createPlatformIdentity('self-edit-channel-preserve-seeder');
        $channelA = $this->createSiteChannel($siteId, 'self-edit-channel-a', '自助编辑栏目A', $seedOperator->id);
        $channelB = $this->createSiteChannel($siteId, 'self-edit-channel-b', '自助编辑栏目B', $seedOperator->id);
        $operator = $this->createRestrictedContentOperator('self-edit-channel-keeper', $siteId, [$channelA, $channelB]);

        $originalChannelIds = DB::table('site_user_channels')
            ->where('site_id', $siteId)
            ->where('user_id', $operator->id)
            ->orderBy('channel_id')
            ->pluck('channel_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.site-users.edit', $operator->id))
            ->post(route('admin.site-users.update', $operator->id), [
                'username' => $operator->username,
                'name' => 'Self Edit Channel Keeper Updated',
                'email' => $operator->email,
                'mobile' => '13800138223',
                'remark' => '<p>自助编辑不应清空栏目</p>',
                'status' => 1,
            ])
            ->assertRedirect(route('admin.site-users.edit', ['user' => $operator->id, 'site_id' => $siteId]))
            ->assertSessionHas('status', '站点账号已更新。');

        $updatedChannelIds = DB::table('site_user_channels')
            ->where('site_id', $siteId)
            ->where('user_id', $operator->id)
            ->orderBy('channel_id')
            ->pluck('channel_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame(
            $originalChannelIds,
            $updatedChannelIds,
            '自助编辑个人资料时不应清空或改动既有可管理栏目。',
        );
    }

    public function test_self_edit_avatar_library_only_shows_attachments_visible_under_attachment_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('self-avatar-reviewer', true, 'reviewer');
        $otherOperator = $this->createSiteOperator('self-avatar-owner', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $operatorRoleId = (int) DB::table('site_user_roles')
            ->where('site_id', $siteId)
            ->where('user_id', $operator->id)
            ->value('role_id');

        $this->setAttachmentSharing($siteId, true, $otherOperator->id);
        DB::table('site_role_permissions')
            ->where('site_id', $siteId)
            ->where('role_id', $operatorRoleId)
            ->delete();
        $ownAttachmentId = $this->createSiteAttachment($siteId, $operator->id, 'self-avatar-own.jpg');
        $foreignAttachmentId = $this->createSiteAttachment($siteId, $otherOperator->id, 'self-avatar-foreign.jpg');

        $this->assertNotSame(0, $ownAttachmentId);
        $this->assertNotSame(0, $foreignAttachmentId);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.library-feed', [
                'mode' => 'avatar',
                'context' => 'avatar',
                'image_only' => 1,
            ]))
            ->assertOk()
            ->assertSee('self-avatar-own.jpg')
            ->assertSee('self-avatar-foreign.jpg');
    }

    public function test_site_admin_can_open_avatar_library_when_editing_site_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('avatar-library-site-admin', true, 'site_admin');
        $otherOperator = $this->createSiteOperator('avatar-library-target-user', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->setAttachmentSharing($siteId, false, $siteAdmin->id);
        $this->createSiteAttachment($siteId, $otherOperator->id, 'avatar-library-target.jpg');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.library-feed', [
                'mode' => 'avatar',
                'context' => 'avatar',
                'image_only' => 1,
            ]))
            ->assertOk()
            ->assertSee('avatar-library-target.jpg');
    }

    public function test_self_edit_profile_can_bind_same_site_avatar_without_visibility_recheck(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('self-avatar-guard', true, 'reviewer');
        $otherOperator = $this->createSiteOperator('self-avatar-source', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $operatorRoleId = (int) DB::table('site_user_roles')
            ->where('site_id', $siteId)
            ->where('user_id', $operator->id)
            ->value('role_id');

        $this->setAttachmentSharing($siteId, false, $otherOperator->id);
        DB::table('site_role_permissions')
            ->where('site_id', $siteId)
            ->where('role_id', $operatorRoleId)
            ->delete();
        $foreignAttachmentId = $this->createSiteAttachment($siteId, $otherOperator->id, 'self-avatar-forbidden.jpg');
        $foreignAttachmentUrl = (string) DB::table('attachments')->where('id', $foreignAttachmentId)->value('url');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.site-users.edit', $operator->id))
            ->post(route('admin.site-users.update', $operator->id), [
                'username' => $operator->username,
                'name' => 'Self Avatar Guard Updated',
                'email' => $operator->email,
                'mobile' => '13800138333',
                'avatar' => $foreignAttachmentUrl,
                'remark' => '<p>尝试绑定不可访问头像</p>',
                'status' => 1,
            ])
            ->assertRedirect(route('admin.site-users.edit', ['user' => $operator->id, 'site_id' => $siteId]))
            ->assertSessionHas('status', '站点账号已更新。');

        $this->assertSame(
            $foreignAttachmentUrl,
            DB::table('users')->where('id', $operator->id)->value('avatar'),
            '自助编辑头像时，只要是当前站点的合法图片资源，就应允许保存。',
        );
    }

    public function test_site_user_manager_avatar_library_can_see_all_site_avatar_images_when_share_is_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $manager = $this->createCustomSiteOperator('avatar-user-manager', $siteId, ['site.user.manage']);
        $otherOperator = $this->createSiteOperator('avatar-foreign-owner', true, 'editor');

        $this->setAttachmentSharing($siteId, false, $manager->id);

        $ownAttachmentId = $this->createSiteAttachment($siteId, $manager->id, 'avatar-manager-own.jpg');
        $foreignAttachmentId = $this->createSiteAttachment($siteId, $otherOperator->id, 'avatar-manager-foreign.jpg');

        $this->actingAs($manager)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.library-feed', [
                'mode' => 'avatar',
                'context' => 'avatar',
                'image_only' => 1,
            ]))
            ->assertOk()
            ->assertSee('avatar-manager-own.jpg')
            ->assertSee('avatar-manager-foreign.jpg');

        $this->assertGreaterThan(0, $ownAttachmentId);
        $this->assertGreaterThan(0, $foreignAttachmentId);
    }

    public function test_site_admin_can_save_other_user_avatar_without_submit_time_visibility_recheck(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('avatar-submit-site-admin', true, 'site_admin');
        $targetUser = $this->createSiteOperator('avatar-submit-target-user', true, 'editor');
        $otherOperator = $this->createSiteOperator('avatar-submit-foreign-owner', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $foreignAttachmentId = $this->createSiteAttachment($siteId, $otherOperator->id, 'avatar-submit-foreign.jpg');
        $foreignAttachmentUrl = (string) DB::table('attachments')->where('id', $foreignAttachmentId)->value('url');
        $targetRoleId = (int) DB::table('site_user_roles')
            ->where('site_id', $siteId)
            ->where('user_id', $targetUser->id)
            ->value('role_id');

        $this->setAttachmentSharing($siteId, false, $siteAdmin->id);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.site-users.edit', $targetUser->id))
            ->post(route('admin.site-users.update', $targetUser->id), [
                'username' => $targetUser->username,
                'name' => 'Target User Updated',
                'email' => $targetUser->email,
                'mobile' => '13800138111',
                'avatar' => $foreignAttachmentUrl,
                'remark' => '管理员修改头像',
                'status' => 1,
                'role_id' => 'site:'.$targetRoleId,
                'channel_ids' => [],
            ])
            ->assertRedirect(route('admin.site-users.edit', ['user' => $targetUser->id, 'site_id' => $siteId]))
            ->assertSessionHas('status', '站点账号已更新。');

        $this->assertSame(
            $foreignAttachmentUrl,
            DB::table('users')->where('id', $targetUser->id)->value('avatar'),
            '管理员编辑操作员头像时，保存阶段不应再重复做资源可见性判断。',
        );
    }

    public function test_self_edit_profile_rejects_avatar_outside_current_site_media_path(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('self-avatar-path-guard', true, 'reviewer');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.site-users.edit', $operator->id))
            ->post(route('admin.site-users.update', $operator->id), [
                'username' => $operator->username,
                'name' => 'Self Avatar Path Guard',
                'email' => $operator->email,
                'mobile' => '13800138222',
                'avatar' => 'https://example.com/evil-avatar.jpg',
                'remark' => '测试非法头像路径',
                'status' => 1,
            ])
            ->assertRedirect(route('admin.site-users.edit', $operator->id))
            ->assertSessionHasErrors(['avatar']);
    }

    public function test_promo_item_library_only_shows_visible_images_but_allows_direct_attachment_binding(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $operator = $this->createCustomSiteOperator('promo-attachment-guard', $siteId, ['promo.manage']);
        $otherOperator = $this->createSiteOperator('promo-attachment-owner', true, 'editor');

        $this->setAttachmentSharing($siteId, false, $operator->id);

        $positionId = (int) DB::table('promo_positions')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => null,
            'code' => 'promo-guard-position',
            'name' => '图宣附件权限测试位',
            'page_scope' => 'global',
            'display_mode' => 'single',
            'template_name' => null,
            'scope_hash' => sha1('global|site|default'),
            'allow_multiple' => 1,
            'max_items' => 3,
            'status' => 1,
            'remark' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ownAttachmentId = $this->createSiteAttachment($siteId, $operator->id, 'promo-own-image.jpg');
        $foreignAttachmentId = $this->createSiteAttachment($siteId, $otherOperator->id, 'promo-foreign-image.jpg');

        $itemId = (int) DB::table('promo_items')->insertGetId([
            'site_id' => $siteId,
            'position_id' => $positionId,
            'attachment_id' => $ownAttachmentId,
            'title' => '原始图宣',
            'subtitle' => null,
            'link_url' => null,
            'link_target' => '_self',
            'sort' => 1,
            'status' => 1,
            'start_at' => null,
            'end_at' => null,
            'display_payload' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.attachments.library-feed', [
                'mode' => 'picker',
                'context' => 'promo',
                'image_only' => 1,
            ]))
            ->assertOk()
            ->assertSee('promo-own-image.jpg')
            ->assertDontSee('promo-foreign-image.jpg');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.promos.items.store', ['position' => $positionId]), [
                'attachment_id' => $foreignAttachmentId,
                'title' => '越权图宣',
                'subtitle' => '测试',
                'link_url' => '',
                'link_target' => '_self',
                'status' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->postJson(route('admin.promos.items.replace-image', ['position' => $positionId, 'item' => $itemId]), [
                'attachment_id' => $foreignAttachmentId,
            ])
            ->assertOk();

        $this->assertSame(
            $foreignAttachmentId,
            (int) DB::table('promo_items')->where('id', $itemId)->value('attachment_id'),
            '图宣保存和换图不再额外校验当前操作员对图片的可见性。',
        );
    }

    public function test_promo_item_rejects_protocol_relative_link_url(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $operator = $this->createCustomSiteOperator('promo-link-guard', $siteId, ['promo.manage']);

        $positionId = (int) DB::table('promo_positions')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => null,
            'code' => 'promo-link-guard-position',
            'name' => '图宣跳转地址测试位',
            'page_scope' => 'global',
            'display_mode' => 'single',
            'template_name' => null,
            'scope_hash' => sha1('global|promo-link-guard-position|single'),
            'allow_multiple' => 1,
            'max_items' => 3,
            'status' => 1,
            'remark' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $attachmentId = $this->createSiteAttachment($siteId, $operator->id, 'promo-link-guard-image.jpg');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.promos.items.store', ['position' => $positionId]), [
                'attachment_id' => $attachmentId,
                'title' => '协议相对地址测试',
                'subtitle' => '',
                'link_url' => '//evil.example/path',
                'link_target' => '_self',
                'status' => '1',
            ])
            ->assertSessionHasErrors([
                'link_url' => '跳转地址格式不正确，仅支持站内相对路径或完整网址。',
            ]);

        $this->assertDatabaseMissing('promo_items', [
            'position_id' => $positionId,
            'title' => '协议相对地址测试',
        ]);
    }

    public function test_restricted_content_operator_cannot_bind_inaccessible_cover_attachment(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $seedOperator = $this->createPlatformIdentity('content-attachment-guard-seeder');
        $channelId = $this->createSiteChannel($siteId, 'content-attachment-guard-channel', '内容附件权限栏目', $seedOperator->id);
        $operator = $this->createRestrictedContentOperator('content-attachment-guard', $siteId, [$channelId]);
        $otherOperator = $this->createRestrictedContentOperator('content-attachment-owner', $siteId, [$channelId]);

        $this->setAttachmentSharing($siteId, false, $operator->id);

        $foreignAttachmentId = $this->createSiteAttachment($siteId, $otherOperator->id, 'content-foreign-image.jpg');
        $foreignAttachmentUrl = (string) DB::table('attachments')->where('id', $foreignAttachmentId)->value('url');

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '内容附件权限测试文章',
            'slug' => 'content-attachment-guard-article',
            'summary' => '用于保存权限校验',
            'content' => '<p>原始正文</p>',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.articles.edit', $contentId))
            ->post(route('admin.articles.update', $contentId), [
                'channel_id' => $channelId,
                'title' => '内容附件权限测试文章',
                'summary' => '用于保存权限校验',
                'cover_image' => $foreignAttachmentUrl,
                'content' => '<p><img src="'.$foreignAttachmentUrl.'" alt="forbidden"></p>',
                'author' => 'Restricted Editor',
                'source' => '本站',
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.articles.edit', $contentId))
            ->assertSessionHasErrors([
                'cover_image' => '封面图不可访问，请重新从可用资源中选择。',
            ]);

        $content = DB::table('contents')->where('id', $contentId)->first(['cover_image', 'content']);
        $this->assertSame('', (string) ($content->cover_image ?? ''));
        $this->assertSame('<p>原始正文</p>', (string) $content->content);
    }

    public function test_restricted_content_operator_can_save_body_attachment_reference_via_srcset_without_visibility_validation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $seedOperator = $this->createPlatformIdentity('content-srcset-guard-seeder');
        $channelId = $this->createSiteChannel($siteId, 'content-srcset-guard-channel', '内容 Srcset 权限栏目', $seedOperator->id);
        $operator = $this->createRestrictedContentOperator('content-srcset-guard', $siteId, [$channelId]);
        $otherOperator = $this->createRestrictedContentOperator('content-srcset-owner', $siteId, [$channelId]);

        $this->setAttachmentSharing($siteId, false, $operator->id);

        $foreignAttachmentId = $this->createSiteAttachment($siteId, $otherOperator->id, 'content-foreign-srcset.jpg');
        $foreignAttachmentUrl = (string) DB::table('attachments')->where('id', $foreignAttachmentId)->value('url');

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '内容 Srcset 权限测试文章',
            'slug' => 'content-srcset-attachment-guard-article',
            'summary' => '用于 srcset 保存权限校验',
            'content' => '<p>原始正文</p>',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.articles.update', $contentId), [
                'channel_id' => $channelId,
                'title' => '内容 Srcset 权限测试文章',
                'summary' => '用于 srcset 保存权限校验',
                'cover_image' => '',
                'content' => '<p><img srcset="'.$foreignAttachmentUrl.' 1x" alt="forbidden"></p>',
                'author' => 'Restricted Editor',
                'source' => '本站',
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.articles.edit', $contentId))
            ->assertSessionHasNoErrors();

        $content = DB::table('contents')->where('id', $contentId)->value('content');
        $this->assertSame('<p><img srcset="'.$foreignAttachmentUrl.' 1x" alt="forbidden"></p>', (string) $content);
    }

    public function test_site_user_update_rejects_invalid_username_format(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('username-rule-admin', true, 'site_admin');
        $editor = $this->createSiteOperator('username-rule-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.site-users.edit', $editor->id))
            ->post(route('admin.site-users.update', $editor->id), [
                'username' => 'bad name!*',
                'name' => 'Editor Updated',
                'email' => $editor->email,
                'mobile' => '13800138111',
                'status' => 1,
                'role_id' => 'site:'.DB::table('site_roles')->where('code', 'editor')->value('id'),
            ])
            ->assertRedirect(route('admin.site-users.edit', $editor->id))
            ->assertSessionHasErrors([
                'username' => '用户名需以字母开头，可使用字母、数字、下划线或中划线。',
            ]);

        $this->assertSame(
            'username-rule-editor',
            DB::table('users')->where('id', $editor->id)->value('username'),
            '非法用户名不应写入数据库。',
        );
    }

    public function test_disabled_site_operator_cannot_login_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('disabled-site-operator', true, 'editor');

        DB::table('users')
            ->where('id', $operator->id)
            ->update(['status' => 0]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'ChangeMe123!',
                'captcha' => $this->loginCaptcha(),
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => '账号已停用，如有疑问请联系站点管理员。',
            ]);

        $this->assertGuest();
    }

    public function test_site_operator_login_records_last_login_and_site_operation_log(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('site-login-recorder', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('users')
            ->where('id', $operator->id)
            ->update([
                'last_login_at' => null,
                'last_login_ip' => null,
            ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'ChangeMe123!',
                'captcha' => $this->loginCaptcha(),
            ])
            ->assertRedirect(route('admin.site-dashboard'));

        $userRecord = DB::table('users')->where('id', $operator->id)->first(['last_login_at', 'last_login_ip']);

        $this->assertNotNull($userRecord?->last_login_at);
        $this->assertNotNull($userRecord?->last_login_ip);

        $this->assertDatabaseHas('operation_logs', [
            'scope' => 'site',
            'site_id' => $siteId,
            'user_id' => $operator->id,
            'module' => 'auth',
            'action' => 'login',
            'target_type' => 'user',
            'target_id' => (string) $operator->id,
        ]);
    }

    public function test_platform_admin_login_records_platform_operation_log(): void
    {
        $this->seed(DatabaseSeeder::class);

        $platformAdmin = $this->createPlatformIdentity('platform-login-recorder', 'platform_admin');

        DB::table('users')
            ->where('id', $platformAdmin->id)
            ->update([
                'last_login_at' => null,
                'last_login_ip' => null,
            ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $platformAdmin->username,
                'password' => 'ChangeMe123!',
                'captcha' => $this->loginCaptcha(),
            ])
            ->assertRedirect(route('admin.dashboard'));

        $userRecord = DB::table('users')->where('id', $platformAdmin->id)->first(['last_login_at', 'last_login_ip']);

        $this->assertNotNull($userRecord?->last_login_at);
        $this->assertNotNull($userRecord?->last_login_ip);

        $this->assertDatabaseHas('operation_logs', [
            'scope' => 'platform',
            'site_id' => null,
            'user_id' => $platformAdmin->id,
            'module' => 'auth',
            'action' => 'login',
            'target_type' => 'user',
            'target_id' => (string) $platformAdmin->id,
        ]);
    }

    public function test_login_is_rate_limited_after_repeated_failures_and_clears_on_success(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->disableSiteSecurityRateLimit();

        $operator = $this->createSiteOperator('login-rate-limit-user', true, 'editor');
        $throttleKey = $this->loginThrottleKeyForCurrentDevice($operator->username);

        RateLimiter::clear($throttleKey);

        for ($i = 0; $i < 10; $i++) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $operator->username,
                    'password' => 'bad-password',
                    'captcha' => $this->loginCaptcha(),
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['username']);
        }

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'bad-password',
                'captcha' => $this->loginCaptcha(),
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => '登录尝试过于频繁，请稍后再试。剩余 5 分钟后可再试。',
            ]);

        RateLimiter::clear($throttleKey);
        RateLimiter::clear($this->siteSecurityRateKeyForPath('/login/captcha'));

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'ChangeMe123!',
                'captcha' => $this->loginCaptcha(),
            ])
            ->assertRedirect(route('admin.site-dashboard'));

        $this->assertSame(0, RateLimiter::attempts($throttleKey));
    }

    public function test_login_page_prompts_captcha_after_too_many_failures(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('login-captcha-user', true, 'editor');

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'bad-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => '用户名或密码不正确。 再输错 9 次后将限制登录5分钟。',
            ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('为保障账号安全，请填写图形验证码。');

        for ($i = 0; $i < 1; $i++) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $operator->username,
                    'password' => 'bad-password',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors([
                    'username' => '用户名或密码不正确。 再输错 8 次后将限制登录5分钟。',
                ]);
        }

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('为保障账号安全，请填写图形验证码。');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('为保障账号安全，请填写图形验证码。');
    }

    public function test_login_failure_does_not_keep_password_value_on_login_page(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('login-password-keep-user', true, 'editor');

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'bad-password',
            ])
            ->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('value="bad-password"', false);
    }

    public function test_login_captcha_error_keeps_captcha_visible_and_uses_new_message(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('login-captcha-error-user', true, 'editor');

        for ($i = 0; $i < 2; $i++) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $operator->username,
                    'password' => 'bad-password',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['username']);
        }

        $captcha = $this->loginCaptcha();

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'ChangeMe123!',
                'captcha' => 'WRNG',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'captcha' => '验证码输入错误，请从新输入。 再输错 7 次后将限制登录5分钟。',
            ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('验证码')
            ->assertSee('为保障账号安全，请填写图形验证码。');

        $this->assertNotSame('', $captcha);
    }

    public function test_login_captcha_required_request_without_captcha_does_not_error(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('login-captcha-missing-user', true, 'editor');

        for ($i = 0; $i < 2; $i++) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $operator->username,
                    'password' => 'bad-password',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['username']);
        }

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'ChangeMe123!',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'captcha' => '验证码输入错误，请从新输入。 再输错 7 次后将限制登录5分钟。',
            ]);
    }

    public function test_login_page_keeps_captcha_visible_after_lockout_refresh(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->disableSiteSecurityRateLimit();

        $operator = $this->createSiteOperator('login-lockout-refresh-user', true, 'editor');
        $throttleKey = $this->loginThrottleKeyForCurrentDevice($operator->username);

        for ($i = 0; $i < 2; $i++) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $operator->username,
                    'password' => 'bad-password',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['username']);
        }

        while (RateLimiter::attempts($throttleKey) < 10) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $operator->username,
                    'password' => 'bad-password',
                    'captcha' => $this->loginCaptcha(),
                ])
                ->assertRedirect(route('login'));
        }

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'ChangeMe123!',
                'captcha' => $this->loginCaptcha(),
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => '登录尝试过于频繁，请稍后再试。剩余 5 分钟后可再试。',
            ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('验证码')
            ->assertSee('为保障账号安全，请填写图形验证码。');
    }

    public function test_lockout_requires_correct_captcha_before_showing_lockout_message(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->disableSiteSecurityRateLimit();

        $operator = $this->createSiteOperator('login-lockout-captcha-user', true, 'editor');
        $throttleKey = $this->loginThrottleKeyForCurrentDevice($operator->username);

        for ($i = 0; $i < 2; $i++) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $operator->username,
                    'password' => 'bad-password',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['username']);
        }

        while (RateLimiter::attempts($throttleKey) < 10) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $operator->username,
                    'password' => 'bad-password',
                    'captcha' => $this->loginCaptcha(),
                ])
                ->assertRedirect(route('login'));
        }

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'ChangeMe123!',
                'captcha' => 'WRNG',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => '登录尝试过于频繁，请稍后再试。剩余 5 分钟后可再试。',
            ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'ChangeMe123!',
                'captcha' => $this->loginCaptcha(),
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => '登录尝试过于频繁，请稍后再试。剩余 5 分钟后可再试。',
            ]);
    }

    public function test_login_captcha_remains_visible_and_lockout_restarts_count_after_expiry(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->disableSiteSecurityRateLimit();

        $operator = $this->createSiteOperator('login-lockout-expired-user', true, 'editor');
        $throttleKey = $this->loginThrottleKeyForCurrentDevice($operator->username);

        for ($i = 0; $i < 2; $i++) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $operator->username,
                    'password' => 'bad-password',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['username']);
        }

        while (RateLimiter::attempts($throttleKey) < 10) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $operator->username,
                    'password' => 'bad-password',
                    'captcha' => $this->loginCaptcha(),
                ])
                ->assertRedirect(route('login'));
        }

        $this->travel(301)->seconds();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('验证码')
            ->assertSee('为保障账号安全，请填写图形验证码。');

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'ChangeMe123!',
                'captcha' => 'WRNG',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'captcha' => '验证码输入错误，请从新输入。 再输错 9 次后将限制登录5分钟。',
            ]);

        $this->assertSame(1, RateLimiter::attempts($throttleKey));
    }

    public function test_login_failure_message_counts_captcha_errors_toward_lockout(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('login-captcha-count-user', true, 'editor');

        for ($i = 0; $i < 2; $i++) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $operator->username,
                    'password' => 'bad-password',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['username']);
        }

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'ChangeMe123!',
                'captcha' => 'WRNG',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'captcha' => '验证码输入错误，请从新输入。 再输错 7 次后将限制登录5分钟。',
            ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'bad-password',
                'captcha' => $this->loginCaptcha(),
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => '用户名或密码不正确。 再输错 6 次后将限制登录5分钟。',
            ]);
    }

    public function test_login_failure_count_does_not_reset_when_switching_username_on_same_device(): void
    {
        $this->seed(DatabaseSeeder::class);

        $firstOperator = $this->createSiteOperator('login-device-user-one', true, 'editor');
        $secondOperator = $this->createSiteOperator('login-device-user-two', true, 'editor');

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $firstOperator->username,
                'password' => 'bad-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => '用户名或密码不正确。 再输错 9 次后将限制登录5分钟。',
            ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $secondOperator->username,
                'password' => 'bad-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => '用户名或密码不正确。 再输错 9 次后将限制登录5分钟。',
            ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('为保障账号安全，请填写图形验证码。');
    }

    public function test_login_lockout_does_not_apply_to_other_username_on_same_device(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->disableSiteSecurityRateLimit();

        $firstOperator = $this->createSiteOperator('login-lock-user-one', true, 'editor');
        $secondOperator = $this->createSiteOperator('login-lock-user-two', true, 'editor');
        $firstThrottleKey = $this->loginThrottleKeyForCurrentDevice($firstOperator->username);

        for ($i = 0; $i < 2; $i++) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $firstOperator->username,
                    'password' => 'bad-password',
                ])
                ->assertRedirect(route('login'));
        }

        while (RateLimiter::attempts($firstThrottleKey) < 10) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $firstOperator->username,
                    'password' => 'bad-password',
                    'captcha' => $this->loginCaptcha(),
                ])
                ->assertRedirect(route('login'));
        }

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $secondOperator->username,
                'password' => 'bad-password',
                'captcha' => $this->loginCaptcha(),
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => '用户名或密码不正确。 再输错 9 次后将限制登录5分钟。',
            ]);
    }

    public function test_site_operator_can_only_login_on_bound_site_domain_but_platform_admin_is_not_affected(): void
    {
        $this->seed(DatabaseSeeder::class);

        $remoteSiteId = $this->createAdditionalSite('remote-login-site', '远程登录站点');

        DB::table('site_domains')->insert([
            'site_id' => $remoteSiteId,
            'domain' => 'remote-login.test',
            'is_primary' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $foreignOperator = $this->createSiteOperator('foreign-domain-operator', true, 'editor');
        $remoteOperator = $this->createSiteOperator('remote-domain-operator', false);
        $editorRoleId = (int) DB::table('site_roles')->where('code', 'editor')->value('id');

        DB::table('site_user_roles')->insert([
            'site_id' => $remoteSiteId,
            'user_id' => $remoteOperator->id,
            'role_id' => $editorRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->from('http://remote-login.test/login')
            ->post('http://remote-login.test/login', [
                'username' => $foreignOperator->username,
                'password' => 'ChangeMe123!',
            ])
            ->assertRedirect('http://remote-login.test/login')
            ->assertSessionHasErrors([
                'username' => '当前账号无权登录该站点，请使用对应站点域名登录。',
            ]);

        $this->from('http://remote-login.test/login')
            ->post('http://remote-login.test/login', [
                'username' => $remoteOperator->username,
                'password' => 'ChangeMe123!',
            ])
            ->assertRedirect(route('admin.site-dashboard'));

        \Illuminate\Support\Facades\Auth::logout();

        $this->from('http://remote-login.test/login')
            ->post('http://remote-login.test/login', [
                'username' => 'superadmin',
                'password' => 'ChangeMe123!',
            ])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_unbound_domain_shows_domain_unbound_page_for_site_and_login(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('http://unbound-domain.test/')
            ->assertOk()
            ->assertSee('当前域名尚未绑定站点')
            ->assertSee('unbound-domain.test');

        $this->get('http://unbound-domain.test/login')
            ->assertOk()
            ->assertSee('当前域名尚未绑定站点')
            ->assertSee('unbound-domain.test')
            ->assertDontSee('欢迎登录');
    }

    public function test_login_captcha_check_is_guarded_by_site_security_rate_limit(): void
    {
        $this->seed(DatabaseSeeder::class);

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('login.captcha.check'), ['captcha' => 'ABCD'])
                ->assertOk();
        }

        $this->post(route('login.captcha.check'), ['captcha' => 'ABCD'])
            ->assertForbidden();
    }

    public function test_disabled_site_operator_is_logged_out_on_next_admin_request(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('disabled-session-user', true, 'editor');

        DB::table('users')
            ->where('id', $operator->id)
            ->update(['status' => 0]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => 1])
            ->get(route('admin.site-dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => '账号已停用，如有疑问请联系站点管理员。',
            ]);

        $this->assertGuest();
    }

    public function test_edit_site_operator_page_preselects_current_status(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('status-prefill-admin', true, 'site_admin');
        $operator = $this->createSiteOperator('status-prefill-target', true, 'editor');

        DB::table('users')
            ->where('id', $operator->id)
            ->update(['status' => 0]);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $response = $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-users.edit', $operator->id))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/<input[^>]*name="status"(?=[^>]*value="0")(?=[^>]*checked)[^>]*>/i',
            $response->getContent(),
            '编辑页应默认勾选当前账号状态。',
        );
    }

    public function test_site_admin_cannot_create_operator_with_platform_role_id(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('site-user-role-guard-create', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $platformRoleId = (int) DB::table('platform_roles')
            ->where('code', 'platform_admin')
            ->value('id');
        $beforeCount = DB::table('users')->count();

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.site-users.create'))
            ->post(route('admin.site-users.store'), [
                'username' => 'invalid-platform-role-operator',
                'name' => 'Invalid Platform Role Operator',
                'email' => 'invalid-platform-role-operator@example.com',
                'mobile' => '',
                'password' => 'ChangeMe123!',
                'status' => 1,
                'role_id' => 'platform:' . $platformRoleId,
            ])
            ->assertSessionHasErrors(['role_id']);

        $this->assertSame($beforeCount, DB::table('users')->count(), '不应在提交平台角色时创建垃圾操作员账号。');
    }

    public function test_operator_without_content_manage_role_ignores_channel_submission(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('content-channel-scope-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $blockedChannelId = $this->createSiteChannel($siteId, 'blocked-channel-scope', '受限栏目', $siteAdmin->id);
        $uploaderRoleId = (int) DB::table('site_roles')->where('code', 'uploader')->value('id');
        $username = 'uploader-no-content-scope';

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.site-users.create'))
            ->post(route('admin.site-users.store'), [
                'username' => $username,
                'name' => 'Uploader No Content Scope',
                'email' => $username.'@example.com',
                'mobile' => '',
                'password' => 'ChangeMe123!',
                'status' => 1,
                'role_id' => 'site:' . $uploaderRoleId,
                'channel_ids' => [$blockedChannelId],
            ])
            ->assertRedirect(route('admin.site-users.index'));

        $userId = (int) DB::table('users')->where('username', $username)->value('id');

        $this->assertGreaterThan(0, $userId, '应成功创建测试操作员。');
        $this->assertSame(
            [],
            DB::table('site_user_channels')
                ->where('site_id', $siteId)
                ->where('user_id', $userId)
                ->pluck('channel_id')
                ->all(),
            '不具备内容管理权限的角色不应保留栏目范围。',
        );
    }

    public function test_site_admin_cannot_update_operator_with_platform_role_id(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('site-user-role-guard-update', true, 'site_admin');
        $operator = $this->createSiteOperator('valid-site-operator', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $platformRoleId = (int) DB::table('platform_roles')
            ->where('code', 'platform_admin')
            ->value('id');
        $existingRoleIds = DB::table('site_user_roles')
            ->where('site_id', $siteId)
            ->where('user_id', $operator->id)
            ->pluck('role_id')
            ->all();

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.site-users.edit', $operator->id))
            ->post(route('admin.site-users.update', $operator->id), [
                'username' => $operator->username,
                'name' => $operator->name,
                'email' => $operator->email,
                'mobile' => $operator->mobile,
                'status' => 1,
                'role_id' => 'platform:' . $platformRoleId,
            ])
            ->assertSessionHasErrors(['role_id']);

        $this->assertSame(
            $existingRoleIds,
            DB::table('site_user_roles')
                ->where('site_id', $siteId)
                ->where('user_id', $operator->id)
                ->pluck('role_id')
                ->all(),
            '提交平台角色时不应篡改现有站点角色绑定。',
        );
    }

    public function test_restricted_content_operator_cannot_create_article_in_unassigned_channel(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $allowedChannelId = $this->createSiteChannel($siteId, 'allowed-news', '允许栏目', $this->createPlatformIdentity('channel-seeder-a')->id);
        $blockedChannelId = $this->createSiteChannel($siteId, 'blocked-news', '限制栏目', $this->createPlatformIdentity('channel-seeder-b')->id);
        $operator = $this->createRestrictedContentOperator('restricted-content-writer', $siteId, [$allowedChannelId]);

        $beforeCount = DB::table('contents')->where('site_id', $siteId)->count();

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.articles.store'), [
                'channel_id' => $blockedChannelId,
                'title' => '越权投递文章',
                'summary' => '仅用于权限测试',
                'content' => 'forbidden',
                'author' => 'Restricted Writer',
                'source' => '本站',
                'status' => 'draft',
            ])
            ->assertForbidden();

        $this->assertSame(
            $beforeCount,
            DB::table('contents')->where('site_id', $siteId)->count(),
            '受限内容角色不应向未授权栏目创建内容。',
        );
    }

    public function test_restricted_content_operator_cannot_move_article_to_unassigned_channel(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $allowedChannelId = $this->createSiteChannel($siteId, 'allowed-update-news', '允许修改栏目', $this->createPlatformIdentity('channel-seeder-c')->id);
        $blockedChannelId = $this->createSiteChannel($siteId, 'blocked-update-news', '限制修改栏目', $this->createPlatformIdentity('channel-seeder-d')->id);
        $operator = $this->createRestrictedContentOperator('restricted-content-editor', $siteId, [$allowedChannelId]);

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $allowedChannelId,
            'type' => 'article',
            'title' => '允许栏目文章',
            'slug' => 'allowed-channel-article',
            'summary' => '仅用于权限测试',
            'content' => 'original',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.articles.update', $contentId), [
                'channel_id' => $blockedChannelId,
                'title' => '试图跨栏目移动文章',
                'summary' => '仅用于权限测试',
                'content' => 'forbidden-update',
                'author' => 'Restricted Editor',
                'source' => '本站',
                'status' => 'draft',
            ])
            ->assertForbidden();

        $this->assertSame(
            $allowedChannelId,
            (int) DB::table('contents')->where('id', $contentId)->value('channel_id'),
            '受限内容角色不应把内容移动到未授权栏目。',
        );
    }

    public function test_article_submit_for_publish_enters_pending_when_review_is_enabled_for_editor(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = User::query()->create([
            'username' => 'article-review-editor',
            'name' => 'Article Review Editor',
            'email' => 'article-review-editor@example.com',
            'password' => 'ChangeMe123!',
            'status' => 1,
        ]);
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $channelId = (int) DB::table('channels')->where('site_id', $siteId)->orderBy('id')->value('id');
        $publishOnlyRoleId = (int) DB::table('site_roles')->insertGetId([
            'site_id' => $siteId,
            'name' => '发布编辑',
            'code' => 'publish_only_editor',
            'description' => '仅有发布无审核权限',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $contentManagePermissionId = (int) DB::table('site_permissions')->where('code', 'content.manage')->value('id');
        $contentPublishPermissionId = (int) DB::table('site_permissions')->where('code', 'content.publish')->value('id');

        DB::table('site_user_roles')->insert([
            'site_id' => $siteId,
            'user_id' => $operator->id,
            'role_id' => $publishOnlyRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_role_permissions')->insert([
            [
                'site_id' => $siteId,
                'role_id' => $publishOnlyRoleId,
                'permission_id' => $contentManagePermissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $siteId,
                'role_id' => $publishOnlyRoleId,
                'permission_id' => $contentPublishPermissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'content.article_requires_review'],
            [
                'setting_value' => '1',
                'autoload' => 1,
                'updated_by' => $operator->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.articles.store'), [
                'channel_id' => $channelId,
                'title' => '普通编辑提交审核文章',
                'summary' => '用于审核流程测试',
                'content' => '<p>审核测试正文</p>',
                'author' => $operator->name,
                'source' => '本站',
                'status' => 'published',
            ])
            ->assertRedirect(route('admin.articles.index'));

        $content = DB::table('contents')
            ->where('site_id', $siteId)
            ->where('title', '普通编辑提交审核文章')
            ->first();

        $this->assertNotNull($content);
        $this->assertSame('pending', $content->status);

        $this->assertTrue(
            DB::table('content_review_records')
                ->where('content_id', $content->id)
                ->where('action', 'submitted')
                ->exists(),
            '开启审核后，普通编辑者提交正式发布应写入提交审核记录。',
        );
    }

    public function test_plain_editor_can_submit_review_without_publish_permission_when_review_is_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $editor = $this->createSiteOperator('article-review-plain-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $channelId = (int) DB::table('channels')->where('site_id', $siteId)->orderBy('id')->value('id');

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'content.article_requires_review'],
            [
                'setting_value' => '1',
                'autoload' => 1,
                'updated_by' => $editor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->actingAs($editor)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.articles.create'))
            ->assertOk()
            ->assertSee('提交审核')
            ->assertDontSee('name="status" value="published" checked disabled', false)
            ->assertDontSee('name="status" value="published" disabled', false);

        $this->actingAs($editor)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.articles.store'), [
                'channel_id' => $channelId,
                'title' => '普通编辑无发布权限提审',
                'summary' => '用于无发布权限提审测试',
                'content' => '<p>提审正文</p>',
                'author' => $editor->name,
                'source' => '本站',
                'status' => 'published',
            ])
            ->assertRedirect(route('admin.articles.index'));

        $content = DB::table('contents')
            ->where('site_id', $siteId)
            ->where('title', '普通编辑无发布权限提审')
            ->first();

        $this->assertNotNull($content);
        $this->assertSame('pending', $content->status);
        $this->assertSame('pending', $content->audit_status);
    }

    public function test_article_publish_by_auditor_enters_pending_when_review_is_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $reviewer = $this->createSiteOperator('article-review-auditor', true, 'reviewer');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $channelId = (int) DB::table('channels')->where('site_id', $siteId)->orderBy('id')->value('id');

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'content.article_requires_review'],
            [
                'setting_value' => '1',
                'autoload' => 1,
                'updated_by' => $reviewer->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->actingAs($reviewer)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.articles.store'), [
                'channel_id' => $channelId,
                'title' => '审核员提交待审核文章',
                'summary' => '用于待审核测试',
                'content' => '<p>审核员发布正文</p>',
                'author' => $reviewer->name,
                'source' => '本站',
                'status' => 'published',
            ])
            ->assertRedirect(route('admin.articles.index'));

        $content = DB::table('contents')
            ->where('site_id', $siteId)
            ->where('title', '审核员提交待审核文章')
            ->first();

        $this->assertNotNull($content);
        $this->assertSame('pending', $content->status);

        $this->assertTrue(
            DB::table('content_review_records')
                ->where('content_id', $content->id)
                ->where('action', 'submitted')
                ->exists(),
            '开启审核后，编辑页提交正式发布应统一进入待审核并写入提交记录。',
        );
    }

    public function test_article_update_for_publish_reenters_pending_when_review_is_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $reviewer = $this->createSiteOperator('article-review-update-auditor', true, 'reviewer');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $channelId = (int) DB::table('channels')->where('site_id', $siteId)->orderBy('id')->value('id');

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'content.article_requires_review'],
            [
                'setting_value' => '1',
                'autoload' => 1,
                'updated_by' => $reviewer->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '待重新审核文章',
            'summary' => '旧内容',
            'content' => '<p>旧内容</p>',
            'status' => 'published',
            'audit_status' => 'approved',
            'published_at' => now()->subDay(),
            'created_by' => $reviewer->id,
            'updated_by' => $reviewer->id,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->actingAs($reviewer)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.articles.update', $contentId), [
                'channel_id' => $channelId,
                'title' => '待重新审核文章',
                'summary' => '新内容摘要',
                'content' => '<p>新内容</p>',
                'author' => $reviewer->name,
                'source' => '本站',
                'status' => 'published',
                'published_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('admin.articles.edit', ['content' => $contentId]));

        $content = DB::table('contents')->where('id', $contentId)->first();

        $this->assertNotNull($content);
        $this->assertSame('pending', $content->status);
        $this->assertTrue(
            DB::table('content_review_records')
                ->where('content_id', $contentId)
                ->where('action', 'submitted')
                ->exists(),
            '开启审核后，文章修改后再次正式发布也应重新进入待审核。',
        );
    }

    public function test_article_review_page_requires_audit_permission(): void
    {
        $this->seed(DatabaseSeeder::class);

        $reviewer = $this->createSiteOperator('article-review-page-auditor', true, 'reviewer');
        $editor = $this->createSiteOperator('article-review-page-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($reviewer)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.article-reviews.index'))
            ->assertOk()
            ->assertSee('文章审核');

        $this->actingAs($editor)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.article-reviews.index'))
            ->assertForbidden();
    }

    public function test_article_review_reject_writes_reason_and_rejected_status(): void
    {
        $this->seed(DatabaseSeeder::class);

        $reviewer = $this->createSiteOperator('article-review-rejector', true, 'reviewer');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $channelId = (int) DB::table('channels')->where('site_id', $siteId)->orderBy('id')->value('id');

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '待驳回测试文章',
            'summary' => '用于驳回测试',
            'content' => '<p>待审核正文</p>',
            'status' => 'pending',
            'audit_status' => 'pending',
            'created_by' => $reviewer->id,
            'updated_by' => $reviewer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.article-reviews.reject', $contentId), [
                'reason' => '标题不符合发布规范',
            ])
            ->assertRedirect(route('admin.article-reviews.index', ['status' => 'pending']));

        $this->assertSame(
            'rejected',
            DB::table('contents')->where('id', $contentId)->value('status'),
            '驳回后文章状态应变为已驳回。',
        );

        $latestRejectRecord = DB::table('content_review_records')
            ->where('content_id', $contentId)
            ->where('action', 'rejected')
            ->orderByDesc('created_at')
            ->first();

        $this->assertNotNull($latestRejectRecord);
        $this->assertSame('标题不符合发布规范', $latestRejectRecord->reason);
        $this->assertSame($reviewer->name, $latestRejectRecord->reviewer_name);
    }

    public function test_article_edit_page_shows_latest_reject_information(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('article-reject-info-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $channelId = (int) DB::table('channels')->where('site_id', $siteId)->orderBy('id')->value('id');

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '驳回信息展示文章',
            'summary' => '用于编辑页回显',
            'content' => '<p>驳回回显示例正文</p>',
            'status' => 'rejected',
            'audit_status' => 'rejected',
            'created_by' => $siteAdmin->id,
            'updated_by' => $siteAdmin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('content_review_records')->insert([
            [
                'content_id' => $contentId,
                'site_id' => $siteId,
                'reviewer_user_id' => $siteAdmin->id,
                'reviewer_name' => '审核员甲',
                'reviewer_phone' => '13800138001',
                'action' => 'rejected',
                'reason' => '首图缺失，需要补充封面图',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
            [
                'content_id' => $contentId,
                'site_id' => $siteId,
                'reviewer_user_id' => $siteAdmin->id,
                'reviewer_name' => '审核员乙',
                'reviewer_phone' => '13800138002',
                'action' => 'rejected',
                'reason' => '标题过长，需要缩短后重提',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.articles.edit', $contentId))
            ->assertOk()
            ->assertSee('最近一次审核已驳回')
            ->assertSee('标题过长，需要缩短后重提')
            ->assertSee('审核员乙')
            ->assertSee('13800138002')
            ->assertSee('2 次');
    }

    public function test_article_review_menu_only_visible_for_auditor_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        $reviewer = $this->createSiteOperator('article-review-menu-auditor', true, 'reviewer');
        $editor = $this->createSiteOperator('article-review-menu-editor', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'content.article_requires_review'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_by' => $reviewer->id, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->actingAs($reviewer)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-dashboard'))
            ->assertOk()
            ->assertSee('文章审核')
            ->assertSee(route('admin.article-reviews.index'), false);

        $this->actingAs($editor)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-dashboard'))
            ->assertOk()
            ->assertDontSee('文章审核')
            ->assertDontSee(route('admin.article-reviews.index'), false);
    }

    protected function createAdditionalSite(string $siteKey, string $name): int
    {
        return (int) DB::table('sites')->insertGetId([
            'name' => $name,
            'site_key' => $siteKey,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createSiteOperator(string $username, bool $bindToSite, string $roleCode = 'editor'): User
    {
        $user = User::query()->create([
            'username' => $username,
            'name' => ucfirst(str_replace('-', ' ', $username)),
            'email' => $username.'@example.com',
            'password' => 'ChangeMe123!',
            'status' => 1,
        ]);

        if ($bindToSite) {
            $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
            $editorRoleId = (int) DB::table('site_roles')->where('code', $roleCode)->value('id');

            DB::table('site_user_roles')->insert([
                'site_id' => $siteId,
                'user_id' => $user->id,
                'role_id' => $editorRoleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $channelIds = Schema::hasTable('site_role_channels')
                ? DB::table('site_role_channels')
                    ->where('site_id', $siteId)
                    ->where('role_id', $editorRoleId)
                    ->pluck('channel_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all()
                : [];

            foreach ($channelIds as $channelId) {
                DB::table('site_user_channels')->insert([
                    'site_id' => $siteId,
                    'user_id' => $user->id,
                    'channel_id' => $channelId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $user;
    }

    protected function createPayrollSpreadsheetUpload(array $rows, string $filename = 'payroll.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $columns) {
            foreach ($columns as $columnIndex => $value) {
                $worksheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 1), $value);
            }
        }

        $path = storage_path('app/testing-'.uniqid('payroll-', true).'.xlsx');
        File::ensureDirectoryExists(dirname($path));

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $this->temporaryPayrollUploads[] = $path;

        return new UploadedFile(
            $path,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    protected function createPlatformIdentity(string $username, string $roleCode = 'platform_admin'): User
    {
        $user = User::query()->create([
            'username' => $username,
            'name' => ucfirst(str_replace('-', ' ', $username)),
            'email' => $username.'@example.com',
            'password' => 'ChangeMe123!',
            'status' => 1,
        ]);

        $roleId = (int) DB::table('platform_roles')->where('code', $roleCode)->value('id');

        DB::table('platform_user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    protected function createRestrictedContentOperator(string $username, int $siteId, array $channelIds): User
    {
        $user = User::query()->create([
            'username' => $username,
            'name' => ucfirst(str_replace('-', ' ', $username)),
            'email' => $username.'@example.com',
            'password' => 'ChangeMe123!',
            'status' => 1,
        ]);

        $roleId = (int) DB::table('site_roles')->insertGetId([
            'site_id' => $siteId,
            'name' => '受限内容角色',
            'code' => 'restricted_content_'.str_replace('-', '_', $username),
            'description' => '仅用于内容栏目权限测试',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_user_roles')->insert([
            'site_id' => $siteId,
            'user_id' => $user->id,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contentManagePermissionId = (int) DB::table('site_permissions')->where('code', 'content.manage')->value('id');

        DB::table('site_role_permissions')->insert([
            'site_id' => $siteId,
            'role_id' => $roleId,
            'permission_id' => $contentManagePermissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($channelIds as $channelId) {
            if (Schema::hasTable('site_role_channels')) {
                DB::table('site_role_channels')->insert([
                    'site_id' => $siteId,
                    'role_id' => $roleId,
                    'channel_id' => $channelId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('site_user_channels')->insert([
                'site_id' => $siteId,
                'user_id' => $user->id,
                'channel_id' => $channelId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $user;
    }

    protected function createSiteChannel(int $siteId, string $slug, string $name, int $operatorId): int
    {
        return (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => null,
            'name' => $name,
            'slug' => $slug,
            'type' => 'list',
            'path' => '/'.$slug,
            'depth' => 0,
            'sort' => 0,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $operatorId,
            'updated_by' => $operatorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createCustomSiteOperator(string $username, int $siteId, array $permissionCodes): User
    {
        $user = User::query()->create([
            'username' => $username,
            'name' => ucfirst(str_replace('-', ' ', $username)),
            'email' => $username.'@example.com',
            'password' => 'ChangeMe123!',
            'status' => 1,
        ]);

        $roleId = (int) DB::table('site_roles')->insertGetId([
            'site_id' => $siteId,
            'name' => '自定义权限角色',
            'code' => 'custom_'.str_replace('-', '_', $username),
            'description' => '仅用于权限测试',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_user_roles')->insert([
            'site_id' => $siteId,
            'user_id' => $user->id,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($permissionCodes as $permissionCode) {
            $permissionId = (int) DB::table('site_permissions')->where('code', $permissionCode)->value('id');

            if ($permissionId < 1) {
                continue;
            }

            DB::table('site_role_permissions')->insert([
                'site_id' => $siteId,
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $user;
    }

    protected function createSiteAttachment(int $siteId, int $uploadedBy, string $originName): int
    {
        $token = uniqid('attachment_', true);

        return (int) DB::table('attachments')->insertGetId([
            'site_id' => $siteId,
            'origin_name' => $originName,
            'stored_name' => $token.'_'.$originName,
            'disk' => 'site',
            'path' => 'tests/attachments/'.$siteId.'/'.$token.'_'.$originName,
            'url' => '/site-media/tests/attachments/'.$siteId.'/'.$token.'_'.$originName,
            'mime_type' => 'application/octet-stream',
            'extension' => strtolower((string) pathinfo($originName, PATHINFO_EXTENSION)),
            'size' => 1024,
            'uploaded_by' => $uploadedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function setAttachmentSharing(int $siteId, bool $enabled, int $updatedBy): void
    {
        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'attachment.share_enabled'],
            [
                'setting_value' => $enabled ? '1' : '0',
                'autoload' => 1,
                'updated_by' => $updatedBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
