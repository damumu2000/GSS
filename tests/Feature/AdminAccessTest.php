<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\Site\ContentController;
use App\Jobs\SendGuestbookMessageNotificationJob;
use App\Mail\GuestbookMessageNotificationMail;
use App\Mail\PlatformTestMail;
use App\Models\User;
use App\Modules\Guestbook\Support\GuestbookSettings;
use App\Support\AdminEntryGate;
use App\Support\ContentAttachmentRelationSync;
use App\Support\FrontendPageCache;
use App\Support\LegacyAspAccessSiteImporter;
use App\Support\Modules\ModuleManager;
use App\Support\PlatformMailSettings;
use App\Support\SiteSecurity;
use App\Support\SiteVisitStatsBuffer;
use App\Support\SystemChecks\PerformanceCacheHealthCheck;
use App\Support\SystemChecks\SchedulerHealthCheck;
use App\Support\SystemSettings;
use App\Support\ThemeTags;
use Database\Seeders\CmsBootstrapSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
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

    protected array $temporaryLegacyImportDirs = [];

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

        foreach ($this->temporaryLegacyImportDirs as $path) {
            if (is_string($path) && $path !== '' && File::isDirectory($path)) {
                File::deleteDirectory($path);
            }
        }

        $this->temporaryLegacyImportDirs = [];

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

    protected function siteSecurityDeviceRateKey(string $deviceId, string $scope, ?string $path = null): string
    {
        $siteId = (int) DB::table('sites')
            ->where('status', 1)
            ->orderBy('id')
            ->value('id');
        $parts = [$scope, $deviceId];

        if ($path !== null && $path !== '') {
            $parts[] = mb_strtolower($path);
        }

        return 'site-security-rate-device:'.$siteId.':'.sha1(implode('|', $parts));
    }

    protected function siteSecurityDeviceRateLimitBlockKey(string $deviceId): string
    {
        $siteId = (int) DB::table('sites')
            ->where('status', 1)
            ->orderBy('id')
            ->value('id');

        return 'site-security-rate-block-device:'.$siteId.':'.sha1($deviceId);
    }

    protected function siteSecuritySiteWideRateKey(): string
    {
        $siteId = (int) DB::table('sites')
            ->where('status', 1)
            ->orderBy('id')
            ->value('id');

        return 'site-security-rate:'.$siteId.':site:'.sha1('127.0.0.1');
    }

    protected function siteSecurityFormWideRateKey(): string
    {
        $siteId = (int) DB::table('sites')
            ->where('status', 1)
            ->orderBy('id')
            ->value('id');

        return 'site-security-rate:'.$siteId.':form:'.sha1('127.0.0.1');
    }

    protected function siteSecurityMediaWideRateKey(): string
    {
        $siteId = (int) DB::table('sites')
            ->where('status', 1)
            ->orderBy('id')
            ->value('id');

        return 'site-security-rate:'.$siteId.':media:'.sha1('127.0.0.1');
    }

    protected function siteSecurityPathScanKey(): string
    {
        $siteId = (int) DB::table('sites')
            ->where('status', 1)
            ->orderBy('id')
            ->value('id');

        return 'site-security-path-scan:'.$siteId.':'.sha1('127.0.0.1');
    }

    protected function siteSecurityRateLimitBlockKey(): string
    {
        $siteId = (int) DB::table('sites')
            ->where('status', 1)
            ->orderBy('id')
            ->value('id');

        return 'site-security-rate-block:'.$siteId.':'.sha1('127.0.0.1');
    }

    protected function siteSecurityProbeBlockKey(): string
    {
        $siteId = (int) DB::table('sites')
            ->where('status', 1)
            ->orderBy('id')
            ->value('id');

        return 'site-security-probe-block:'.$siteId.':'.sha1('127.0.0.1');
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

        $response = $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Download-Options', 'noopen')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->assertHeader('X-XSS-Protection', '0')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups')
            ->assertHeader('Cross-Origin-Embedder-Policy', 'unsafe-none')
            ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin')
            ->assertHeader('Content-Security-Policy');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('frame-src', $csp);
        $this->assertStringContainsString('https://player.bilibili.com', $csp);
        $this->assertStringContainsString('https://www.bilibili.com', $csp);
        $this->assertFalse($response->headers->has('X-Powered-By'));
        $this->assertFalse($response->headers->has('X-Generator'));

        $cookieNames = collect($response->headers->getCookies())
            ->map(fn ($cookie) => $cookie->getName())
            ->all();
        $this->assertContains('REQ-TOKEN', $cookieNames);
        $this->assertNotContains('XSRF-TOKEN', $cookieNames);
    }

    public function test_login_page_bypasses_frontend_security_runtime_blocks(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $siteId,
            'client_ip' => '127.0.0.1',
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'hit_count' => 3,
            'high_risk_count' => 3,
            'last_rule_code' => 'probe_abuse',
            'last_request_path' => '/login',
            'status' => 'blocked',
            'blocked_until' => now()->addHour(),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('欢迎登录')
            ->assertDontSee('安护盾拦截');
    }

    public function test_public_directory_does_not_contain_sensitive_scan_targets(): void
    {
        $blockedNames = [
            '.DS_Store',
            '.env',
            '.env.backup',
            '.env.local',
            '.env.production',
            '.htaccess',
            '.htpasswd',
            '.user.ini',
            'phpinfo.php',
        ];
        $blockedExtensions = [
            '.bak',
            '.backup',
            '.dump',
            '.log',
            '.old',
            '.orig',
            '.sql',
            '.tar',
            '.tgz',
            '.zip',
        ];
        $violations = [];

        foreach (File::allFiles(public_path()) as $file) {
            $name = $file->getFilename();
            $normalizedPath = str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());
            $lowerPath = mb_strtolower($normalizedPath);

            if (in_array($name, $blockedNames, true)) {
                $violations[] = $normalizedPath;

                continue;
            }

            foreach ($blockedExtensions as $extension) {
                if (str_ends_with($lowerPath, $extension)) {
                    $violations[] = $normalizedPath;

                    break;
                }
            }
        }

        $this->assertSame([], $violations, 'public 目录存在可被外部扫描直接命中的敏感文件。');
    }

    public function test_login_page_uses_hsts_when_request_is_forwarded_as_https(): void
    {
        $this->seed(DatabaseSeeder::class);
        putenv('SECURITY_HEADERS_HSTS_APP=true');
        $_ENV['SECURITY_HEADERS_HSTS_APP'] = 'true';
        $_SERVER['SECURITY_HEADERS_HSTS_APP'] = 'true';

        try {
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
        } finally {
            putenv('SECURITY_HEADERS_HSTS_APP');
            unset($_ENV['SECURITY_HEADERS_HSTS_APP'], $_SERVER['SECURITY_HEADERS_HSTS_APP']);
        }
    }

    public function test_admin_entry_gate_hides_login_and_admin_until_entry_cookie_is_issued(): void
    {
        $this->seed(DatabaseSeeder::class);
        putenv('CMS_ADMIN_ENTRY_GATE_ENABLED=true');
        $_ENV['CMS_ADMIN_ENTRY_GATE_ENABLED'] = 'true';
        $_SERVER['CMS_ADMIN_ENTRY_GATE_ENABLED'] = 'true';

        try {
            $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

            DB::table('site_settings')->updateOrInsert(
                ['site_id' => $siteId, 'setting_key' => 'security.admin_entry_path'],
                [
                    'setting_value' => 'school-console-x7k',
                    'autoload' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            app(AdminEntryGate::class)->forgetEntryPathForSite($siteId);

            $this->get('/login')->assertNotFound();
            $this->get('/login/captcha')->assertNotFound();
            $this->get('/admin')->assertNotFound();
            $this->get('/wrong-console-x7k')->assertNotFound();

            $this->get('/school-console-x7k')
                ->assertRedirect(route('login'));

            $this->get('/login')
                ->assertOk()
                ->assertSee('登录');

            $this->get('/login/captcha')
                ->assertOk()
                ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8');

            $this->get('/admin')
                ->assertRedirect(route('login'));
        } finally {
            putenv('CMS_ADMIN_ENTRY_GATE_ENABLED');
            unset($_ENV['CMS_ADMIN_ENTRY_GATE_ENABLED'], $_SERVER['CMS_ADMIN_ENTRY_GATE_ENABLED']);
        }
    }

    public function test_admin_entry_gate_accepts_five_character_entry_path(): void
    {
        $this->seed(DatabaseSeeder::class);
        putenv('CMS_ADMIN_ENTRY_GATE_ENABLED=true');
        $_ENV['CMS_ADMIN_ENTRY_GATE_ENABLED'] = 'true';
        $_SERVER['CMS_ADMIN_ENTRY_GATE_ENABLED'] = 'true';

        try {
            $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

            DB::table('site_settings')->updateOrInsert(
                ['site_id' => $siteId, 'setting_key' => 'security.admin_entry_path'],
                [
                    'setting_value' => 'abc12',
                    'autoload' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            app(AdminEntryGate::class)->forgetEntryPathForSite($siteId);

            $this->get('/abc12')
                ->assertRedirect(route('login'));
        } finally {
            putenv('CMS_ADMIN_ENTRY_GATE_ENABLED');
            unset($_ENV['CMS_ADMIN_ENTRY_GATE_ENABLED'], $_SERVER['CMS_ADMIN_ENTRY_GATE_ENABLED']);
        }
    }

    public function test_admin_entry_gate_hides_unbound_domain_login_page(): void
    {
        $this->seed(DatabaseSeeder::class);
        putenv('CMS_ADMIN_ENTRY_GATE_ENABLED=true');
        $_ENV['CMS_ADMIN_ENTRY_GATE_ENABLED'] = 'true';
        $_SERVER['CMS_ADMIN_ENTRY_GATE_ENABLED'] = 'true';

        try {
            $this->withServerVariables([
                'HTTP_HOST' => 'unbound-entry.test',
            ])->get('/login')
                ->assertNotFound()
                ->assertDontSee('域名');

            $this->withServerVariables([
                'HTTP_HOST' => 'unbound-entry.test',
            ])->post('/login', [
                'username' => 'admin',
                'password' => 'secret',
            ])->assertNotFound();
        } finally {
            putenv('CMS_ADMIN_ENTRY_GATE_ENABLED');
            unset($_ENV['CMS_ADMIN_ENTRY_GATE_ENABLED'], $_SERVER['CMS_ADMIN_ENTRY_GATE_ENABLED']);
        }
    }

    public function test_authenticated_admin_can_open_admin_without_entry_cookie(): void
    {
        $this->seed(DatabaseSeeder::class);
        putenv('CMS_ADMIN_ENTRY_GATE_ENABLED=true');
        $_ENV['CMS_ADMIN_ENTRY_GATE_ENABLED'] = 'true';
        $_SERVER['CMS_ADMIN_ENTRY_GATE_ENABLED'] = 'true';

        try {
            $this->actingAs($this->superAdmin())
                ->get('/admin')
                ->assertOk();
        } finally {
            putenv('CMS_ADMIN_ENTRY_GATE_ENABLED');
            unset($_ENV['CMS_ADMIN_ENTRY_GATE_ENABLED'], $_SERVER['CMS_ADMIN_ENTRY_GATE_ENABLED']);
        }
    }

    public function test_logout_keeps_login_page_available_when_admin_entry_gate_is_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);
        putenv('CMS_ADMIN_ENTRY_GATE_ENABLED=true');
        $_ENV['CMS_ADMIN_ENTRY_GATE_ENABLED'] = 'true';
        $_SERVER['CMS_ADMIN_ENTRY_GATE_ENABLED'] = 'true';

        try {
            $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
            $admin = $this->superAdmin();

            DB::table('site_settings')->updateOrInsert(
                ['site_id' => $siteId, 'setting_key' => 'security.admin_entry_path'],
                [
                    'setting_value' => 'school-console-x7k',
                    'autoload' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            app(AdminEntryGate::class)->forgetEntryPathForSite($siteId);

            $this->actingAs($admin)
                ->withSession(['current_site_id' => $siteId])
                ->post(route('logout'))
                ->assertRedirect(route('login'))
                ->assertHeader('Clear-Site-Data', '"cache", "storage"');

            $this->get('/login')
                ->assertOk()
                ->assertSee('登录');
        } finally {
            putenv('CMS_ADMIN_ENTRY_GATE_ENABLED');
            unset($_ENV['CMS_ADMIN_ENTRY_GATE_ENABLED'], $_SERVER['CMS_ADMIN_ENTRY_GATE_ENABLED']);
        }
    }

    public function test_login_token_expired_shows_clear_message_after_admin_entry_gate(): void
    {
        $this->seed(DatabaseSeeder::class);
        putenv('CMS_ADMIN_ENTRY_GATE_ENABLED=true');
        $_ENV['CMS_ADMIN_ENTRY_GATE_ENABLED'] = 'true';
        $_SERVER['CMS_ADMIN_ENTRY_GATE_ENABLED'] = 'true';

        try {
            $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

            DB::table('site_settings')->updateOrInsert(
                ['site_id' => $siteId, 'setting_key' => 'security.admin_entry_path'],
                [
                    'setting_value' => 'school-console-x7k',
                    'autoload' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            app(AdminEntryGate::class)->forgetEntryPathForSite($siteId);

            $this->get('/school-console-x7k')
                ->assertRedirect(route('login'));

            $request = Request::create('/login', 'POST', [
                'username' => 'admin',
            ]);
            $request->setLaravelSession($this->app['session.store']);

            $response = $this->app->make(ExceptionHandler::class)
                ->render($request, new TokenMismatchException('CSRF token mismatch.'));

            $this->assertSame(302, $response->getStatusCode());
            $this->assertSame(route('login'), $response->headers->get('Location'));
            $this->assertSame(
                '登录令牌已过期，请刷新页面后重试。',
                $request->session()->get('errors')->getBag('default')->first('username'),
            );
        } finally {
            putenv('CMS_ADMIN_ENTRY_GATE_ENABLED');
            unset($_ENV['CMS_ADMIN_ENTRY_GATE_ENABLED'], $_SERVER['CMS_ADMIN_ENTRY_GATE_ENABLED']);
        }
    }

    public function test_login_token_expired_without_admin_entry_gate_stays_hidden(): void
    {
        $this->seed(DatabaseSeeder::class);
        putenv('CMS_ADMIN_ENTRY_GATE_ENABLED=true');
        $_ENV['CMS_ADMIN_ENTRY_GATE_ENABLED'] = 'true';
        $_SERVER['CMS_ADMIN_ENTRY_GATE_ENABLED'] = 'true';

        try {
            $request = Request::create('/login', 'POST', [
                'username' => 'admin',
            ]);
            $request->setLaravelSession($this->app['session.store']);

            $response = $this->app->make(ExceptionHandler::class)
                ->render($request, new TokenMismatchException('CSRF token mismatch.'));

            $this->assertSame(404, $response->getStatusCode());
        } finally {
            putenv('CMS_ADMIN_ENTRY_GATE_ENABLED');
            unset($_ENV['CMS_ADMIN_ENTRY_GATE_ENABLED'], $_SERVER['CMS_ADMIN_ENTRY_GATE_ENABLED']);
        }
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
            ->assertViewHas('platformNotices', function ($items): bool {
                $titles = collect($items)->pluck('title')->all();

                return in_array('平台统一更新公告', $titles, true)
                    && ! in_array('主网站普通新闻', $titles, true)
                    && ! in_array('远程站点无关公告', $titles, true);
            });
    }

    public function test_platform_dashboard_ignores_deleted_platform_notice(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $noticeChannelId = (int) DB::table('channels')
            ->where('site_id', 1)
            ->where('slug', 'platform-notices')
            ->value('id');

        DB::table('contents')->insert([
            [
                'site_id' => 1,
                'channel_id' => $noticeChannelId,
                'type' => 'article',
                'title' => '正常平台公告',
                'status' => 'published',
                'audit_status' => 'approved',
                'deleted_at' => null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => 1,
                'channel_id' => $noticeChannelId,
                'type' => 'article',
                'title' => '已回收平台公告',
                'status' => 'published',
                'audit_status' => 'approved',
                'deleted_at' => now(),
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
            ->assertSee('正常平台公告')
            ->assertDontSee('已回收平台公告');
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
            ->assertSee('主站最新文章')
            ->assertSee('示例学校')
            ->assertSee('远程站点文章')
            ->assertSee('平台远程站点');
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

    public function test_platform_dashboard_does_not_render_site_switcher(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('data-site-context-switcher', false);
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
            ->assertSee('自动任务调度检查')
            ->assertSee('安护盾健康')
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

    public function test_system_checks_reports_redis_failover_when_redis_is_unavailable(): void
    {
        $this->seed(DatabaseSeeder::class);

        config([
            'cms.frontend_page_cache.enabled' => true,
            'cache.default' => 'failover',
            'cache.stores.failover.stores' => ['redis', 'array'],
            'database.redis.cache.port' => 1,
        ]);

        Cache::forgetDriver('redis');
        Cache::forgetDriver('failover');

        Http::fake([
            'registry.npmjs.org/*' => Http::response(['version' => '1.15.7']),
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.platform.system-checks.index'))
            ->assertOk()
            ->assertSee('Redis 应用缓存')
            ->assertSee('已降级')
            ->assertSee('Redis 不可用')
            ->assertSee('符合条件的前台公开页面将使用整页缓存')
            ->assertDontSee('前台整页缓存当前由后备缓存接管');
    }

    public function test_cache_configuration_uses_redis_failover_without_project_file_store(): void
    {
        $this->assertSame(['redis', 'database', 'array'], config('cache.stores.failover.stores'));

        $projectCacheConfig = require config_path('cache.php');
        $this->assertArrayNotHasKey('file', $projectCacheConfig['stores']);
    }

    public function test_frontend_page_cache_serves_public_pages_and_flushes_by_site_version(): void
    {
        $this->seed(DatabaseSeeder::class);

        config([
            'cms.frontend_page_cache.enabled' => true,
            'cms.frontend_page_cache.ttl' => 300,
            'cache.default' => 'array',
        ]);

        Cache::forgetDriver('array');
        Cache::forgetDriver('database');

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->get('/?site=site')
            ->assertOk()
            ->assertHeader('X-Frontend-Page-Cache', 'MISS');

        $this->get('/?site=site')
            ->assertOk()
            ->assertHeader('X-Frontend-Page-Cache', 'HIT');

        FrontendPageCache::flushSite($siteId);

        $this->get('/?site=site')
            ->assertOk()
            ->assertHeader('X-Frontend-Page-Cache', 'MISS');
    }

    public function test_redis_cache_connection_has_bounded_failover_timeouts(): void
    {
        $this->assertSame(1.0, config('database.redis.cache.timeout'));
        $this->assertSame(1.0, config('database.redis.cache.read_timeout'));
        $this->assertSame(100, config('database.redis.cache.retry_interval'));
        $this->assertSame(1, config('database.redis.cache.max_retries'));
        $this->assertSame(500, config('database.redis.cache.backoff_cap'));
    }

    public function test_system_checks_reports_unavailable_direct_redis_store(): void
    {
        config([
            'cache.default' => 'redis',
            'database.redis.cache.port' => 1,
        ]);

        Cache::forgetDriver('redis');

        $items = app(PerformanceCacheHealthCheck::class)->inspect()['items'];
        $redisItem = collect($items)->firstWhere('label', 'Redis 应用缓存');

        $this->assertSame('error', $redisItem['status'] ?? null);
        $this->assertSame('不可用', $redisItem['value'] ?? null);
        $this->assertSame('Redis 应用缓存读写失败。', $redisItem['message'] ?? null);
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

        config([
            'cache.default' => 'failover',
            'cache.stores.failover.stores' => ['array', 'database'],
        ]);

        Cache::forgetDriver('array');
        Cache::forgetDriver('database');
        Cache::forgetDriver('failover');

        Cache::put('admin-access-test-cache-key', 'cached-value', 600);
        Cache::store('database')->put('admin-access-test-fallback-cache-key', 'fallback-value', 600);
        $this->assertSame('cached-value', Cache::get('admin-access-test-cache-key'));
        $this->assertSame('fallback-value', Cache::store('database')->get('admin-access-test-fallback-cache-key'));

        $user = $this->superAdmin();

        $this->actingAs($user)
            ->post(route('admin.platform.system-checks.cache.clear', ['action' => 'app']))
            ->assertRedirect(route('admin.platform.system-checks.index'))
            ->assertSessionHas('status', '应用缓存已清理。');

        $this->assertNull(Cache::get('admin-access-test-cache-key'));
        $this->assertNull(Cache::store('database')->get('admin-access-test-fallback-cache-key'));
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

    public function test_super_admin_cannot_enter_app_cache_clear_while_clear_lock_is_held(): void
    {
        $this->seed(DatabaseSeeder::class);

        Cache::put('admin-access-test-cache-key', 'cached-value', 600);
        $lock = Cache::store('database')->lock('system-checks:cache-action:app', 30);
        $this->assertTrue($lock->get());

        try {
            $this->actingAs($this->superAdmin())
                ->post(route('admin.platform.system-checks.cache.clear', ['action' => 'app']))
                ->assertRedirect(route('admin.platform.system-checks.index'))
                ->assertSessionHas('status', '应用缓存正在处理中，请稍后再试。');

            $this->assertSame('cached-value', Cache::get('admin-access-test-cache-key'));
        } finally {
            $lock->release();
        }
    }

    public function test_app_cache_clear_does_not_remove_database_locks(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lock = Cache::store('database')->lock('system-checks:cache-action:sentinel', 30);
        $this->assertTrue($lock->get());

        try {
            $this->actingAs($this->superAdmin())
                ->post(route('admin.platform.system-checks.cache.clear', ['action' => 'app']))
                ->assertRedirect(route('admin.platform.system-checks.index'))
                ->assertSessionHas('status', '应用缓存已清理。');

            $contender = Cache::store('database')->lock('system-checks:cache-action:sentinel', 30);
            $this->assertFalse($contender->get());
        } finally {
            $lock->release();
        }
    }

    public function test_super_admin_can_clear_all_platform_cache_stores(): void
    {
        $this->seed(DatabaseSeeder::class);

        config([
            'cache.default' => 'failover',
            'cache.stores.failover.stores' => ['array', 'database'],
        ]);

        Cache::forgetDriver('array');
        Cache::forgetDriver('database');
        Cache::forgetDriver('failover');

        Cache::put('admin-clear-all-primary-key', 'primary-value', 600);
        Cache::store('database')->put('admin-clear-all-fallback-key', 'fallback-value', 600);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.platform.system-checks.cache.clear-all'))
            ->assertRedirect(route('admin.platform.system-checks.index', ['tab' => 'cache']))
            ->assertSessionHas('status', '已完成一键清除。');

        $this->assertNull(Cache::get('admin-clear-all-primary-key'));
        $this->assertNull(Cache::store('database')->get('admin-clear-all-fallback-key'));
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
        app(ModuleManager::class)->synchronize();
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

    public function test_platform_site_basic_update_preserves_existing_module_bindings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            [
                'site_id' => $siteId,
                'module_id' => $moduleId,
            ],
            [
                'is_trial' => false,
                'is_paused' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

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
    }

    public function test_platform_remove_site_module_only_deletes_current_site_module_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $remoteSiteId = $this->createAdditionalSite('remote-module-site', '远程模块站点');
        app(ModuleManager::class)->synchronize();
        $guestbookModuleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        $payrollModuleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        foreach ([$siteId, $remoteSiteId] as $boundSiteId) {
            DB::table('site_module_bindings')->insert([
                'site_id' => $boundSiteId,
                'module_id' => $guestbookModuleId,
                'is_trial' => 0,
                'is_paused' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('site_module_bindings')->insert([
            'site_id' => $siteId,
            'module_id' => $payrollModuleId,
            'is_trial' => 0,
            'is_paused' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('module_guestbook_messages')->insert([
            [
                'site_id' => $siteId,
                'display_no' => 1,
                'name' => '当前站留言',
                'phone' => '13800000001',
                'content' => '当前站留言内容',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $remoteSiteId,
                'display_no' => 1,
                'name' => '远程站留言',
                'phone' => '13800000002',
                'content' => '远程站留言内容',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('site_settings')->insert([
            [
                'site_id' => $siteId,
                'setting_key' => 'module.guestbook.name',
                'setting_value' => '当前站留言板',
                'autoload' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $remoteSiteId,
                'setting_key' => 'module.guestbook.name',
                'setting_value' => '远程站留言板',
                'autoload' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $siteId,
                'setting_key' => 'module.payroll.enabled',
                'setting_value' => '1',
                'autoload' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $currentAttachmentId = $this->createSiteAttachment($siteId, $user->id, 'current-guestbook-notice.jpg');
        $remoteAttachmentId = $this->createSiteAttachment($remoteSiteId, $user->id, 'remote-guestbook-notice.jpg');
        DB::table('attachment_relations')->insert([
            [
                'attachment_id' => $currentAttachmentId,
                'relation_type' => 'guestbook_setting',
                'relation_id' => $siteId,
                'usage_slot' => 'notice_image',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'attachment_id' => $remoteAttachmentId,
                'relation_type' => 'guestbook_setting',
                'relation_id' => $remoteSiteId,
                'usage_slot' => 'notice_image',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'attachment_id' => $currentAttachmentId,
                'relation_type' => 'guestbook_setting',
                'relation_id' => $remoteSiteId,
                'usage_slot' => 'notice_link',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($user)
            ->post(route('admin.platform.sites.modules.remove', ['site' => $siteId, 'module' => $guestbookModuleId]))
            ->assertRedirect(route('admin.platform.sites.modules', $siteId));

        $this->assertDatabaseMissing('module_guestbook_messages', [
            'site_id' => $siteId,
            'name' => '当前站留言',
        ]);
        $this->assertDatabaseHas('module_guestbook_messages', [
            'site_id' => $remoteSiteId,
            'name' => '远程站留言',
        ]);
        $this->assertDatabaseMissing('site_settings', [
            'site_id' => $siteId,
            'setting_key' => 'module.guestbook.name',
        ]);
        $this->assertDatabaseHas('site_settings', [
            'site_id' => $remoteSiteId,
            'setting_key' => 'module.guestbook.name',
        ]);
        $this->assertDatabaseHas('site_settings', [
            'site_id' => $siteId,
            'setting_key' => 'module.payroll.enabled',
        ]);
        $this->assertDatabaseMissing('site_module_bindings', [
            'site_id' => $siteId,
            'module_id' => $guestbookModuleId,
        ]);
        $this->assertDatabaseHas('site_module_bindings', [
            'site_id' => $remoteSiteId,
            'module_id' => $guestbookModuleId,
        ]);
        $this->assertDatabaseHas('site_module_bindings', [
            'site_id' => $siteId,
            'module_id' => $payrollModuleId,
        ]);
        $this->assertDatabaseMissing('attachment_relations', [
            'attachment_id' => $currentAttachmentId,
            'relation_type' => 'guestbook_setting',
            'relation_id' => $siteId,
        ]);
        $this->assertDatabaseHas('attachment_relations', [
            'attachment_id' => $remoteAttachmentId,
            'relation_type' => 'guestbook_setting',
            'relation_id' => $remoteSiteId,
        ]);
        $this->assertDatabaseHas('attachment_relations', [
            'attachment_id' => $currentAttachmentId,
            'relation_type' => 'guestbook_setting',
            'relation_id' => $remoteSiteId,
        ]);
    }

    public function test_platform_site_basic_update_preserves_admin_bindings_when_admin_field_is_absent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $siteAdmin = $this->createSiteOperator('preserved-site-admin', false, 'site_admin');
        $siteAdminRoleId = (int) DB::table('site_roles')->where('code', 'site_admin')->value('id');

        DB::table('site_user_roles')->insert([
            'site_id' => $siteId,
            'user_id' => $siteAdmin->id,
            'role_id' => $siteAdminRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
                'seo_title' => '示例学校官网',
                'seo_keywords' => '示例学校,校园',
                'seo_description' => '示例学校官网描述',
                'opened_at' => now()->format('Y-m-d'),
                'expires_at' => '',
                'remark' => '站点备注',
            ])
            ->assertRedirect(route('admin.platform.sites.edit', $siteId))
            ->assertSessionHas('status', '站点信息已更新。');

        $this->assertDatabaseHas('site_user_roles', [
            'site_id' => $siteId,
            'user_id' => $siteAdmin->id,
            'role_id' => $siteAdminRoleId,
        ]);
    }

    public function test_platform_site_basic_update_can_clear_admin_bindings_when_admin_picker_is_submitted_empty(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $siteAdmin = $this->createSiteOperator('cleared-site-admin', false, 'site_admin');
        $siteAdminRoleId = (int) DB::table('site_roles')->where('code', 'site_admin')->value('id');

        DB::table('site_user_roles')->insert([
            'site_id' => $siteId,
            'user_id' => $siteAdmin->id,
            'role_id' => $siteAdminRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
                'seo_title' => '示例学校官网',
                'seo_keywords' => '示例学校,校园',
                'seo_description' => '示例学校官网描述',
                'opened_at' => now()->format('Y-m-d'),
                'expires_at' => '',
                'remark' => '站点备注',
                'site_admin_ids_present' => '1',
            ])
            ->assertRedirect(route('admin.platform.sites.edit', $siteId))
            ->assertSessionHas('status', '站点信息已更新。');

        $this->assertDatabaseMissing('site_user_roles', [
            'site_id' => $siteId,
            'user_id' => $siteAdmin->id,
            'role_id' => $siteAdminRoleId,
        ]);
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

        app(ModuleManager::class)->all();

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
        app(ModuleManager::class)->synchronize();

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $editorRoleId = (int) DB::table('site_roles')->where('code', 'editor')->whereNull('site_id')->value('id');
        $guestbookViewPermissionId = (int) DB::table('site_permissions')->where('code', 'guestbook.view')->value('id');
        $moduleUsePermissionId = (int) DB::table('site_permissions')->where('code', 'module.use')->value('id');
        $guestbookModuleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');

        DB::table('modules')->where('id', $guestbookModuleId)->update([
            'status' => 1,
            'updated_at' => now(),
        ]);

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $guestbookModuleId],
            [
                'is_trial' => 0,
                'is_paused' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

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
        app(ModuleManager::class)->synchronize();

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $editorRoleId = (int) DB::table('site_roles')->where('code', 'editor')->whereNull('site_id')->value('id');
        $guestbookModuleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');

        DB::table('modules')->where('id', $guestbookModuleId)->update([
            'status' => 1,
            'updated_at' => now(),
        ]);

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $guestbookModuleId],
            [
                'is_trial' => 0,
                'is_paused' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->actingAs($user)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-roles.edit', $editorRoleId))
            ->assertOk()
            ->assertSee('功能模块权限配置')
            ->assertSee('留言板')
            ->assertDontSee('使用功能模块');
    }

    public function test_site_role_module_permissions_are_hidden_and_rejected_when_module_not_bound(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(ModuleManager::class)->synchronize();

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $editorRoleId = (int) DB::table('site_roles')->where('code', 'editor')->whereNull('site_id')->value('id');
        $guestbookModuleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        $guestbookViewPermissionId = (int) DB::table('site_permissions')->where('code', 'guestbook.view')->value('id');
        $moduleUsePermissionId = (int) DB::table('site_permissions')->where('code', 'module.use')->value('id');

        DB::table('site_module_bindings')
            ->where('site_id', $siteId)
            ->where('module_id', $guestbookModuleId)
            ->delete();

        DB::table('site_role_permissions')
            ->where('site_id', $siteId)
            ->where('role_id', $editorRoleId)
            ->whereIn('permission_id', [$guestbookViewPermissionId, $moduleUsePermissionId])
            ->delete();

        $this->actingAs($user)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-roles.edit', $editorRoleId))
            ->assertOk()
            ->assertDontSee('功能模块权限配置')
            ->assertDontSee('留言板');

        $this->actingAs($user)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.site-roles.update', $editorRoleId), [
                'permission_ids' => [$guestbookViewPermissionId, $moduleUsePermissionId],
            ])
            ->assertRedirect(route('admin.site-roles.edit', $editorRoleId));

        $this->assertDatabaseMissing('site_role_permissions', [
            'site_id' => $siteId,
            'role_id' => $editorRoleId,
            'permission_id' => $guestbookViewPermissionId,
        ]);
        $this->assertDatabaseMissing('site_role_permissions', [
            'site_id' => $siteId,
            'role_id' => $editorRoleId,
            'permission_id' => $moduleUsePermissionId,
        ]);
    }

    public function test_legacy_asp_importer_supports_string_channel_ids_multi_channel_news_and_grouped_about_pages(): void
    {
        $this->seed(DatabaseSeeder::class);

        $sourceDir = storage_path('framework/testing-legacy-asp-'.uniqid());
        File::ensureDirectoryExists($sourceDir);
        $this->temporaryLegacyImportDirs[] = $sourceDir;

        $this->writeLegacyImportSpreadsheet($sourceDir.'/Type_D.xlsx', [
            ['T_ID', 'T_Name', 'T_type', 'T_dlei', 'shunxu', 'en'],
            [1, '最新动态', 2, '', 110, 'News'],
        ]);

        $this->writeLegacyImportSpreadsheet($sourceDir.'/Type.xlsx', [
            ['Type_ID', 'Type_Name', 'Type_type', 'dalei', 'shunxu', 'flag', 'en'],
            ['1', '旧单页栏目应忽略', 1, 0, 0, 1, 'about'],
            ['a11', '园内新闻', 2, 1, 10, 1, 'Garden News'],
            ['a12', '园务公开', 2, 1, 20, 1, 'news'],
        ]);

        $this->writeLegacyImportSpreadsheet($sourceDir.'/About.xlsx', [
            ['About_ID', 'About_Name', 'About_content'],
            [1, '幼儿园介绍', '<p>园所介绍内容</p>'],
        ]);

        File::put($sourceDir.'/News.xml', <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<dataroot>
  <News>
    <News_ID>100</News_ID>
    <News_Type>a11,a12</News_Type>
    <News_Title>多栏目文章</News_Title>
    <News_Pic>/Up/demo.jpg</News_Pic>
    <News_Content><p>文章内容</p></News_Content>
    <News_Date>2026-05-15 10:20:30</News_Date>
    <News_count>23</News_count>
  </News>
  <News>
    <News_ID>101</News_ID>
    <News_Type>a22</News_Type>
    <News_Title>异常栏目文章</News_Title>
    <News_Pic></News_Pic>
    <News_Content><p>异常栏目文章内容</p></News_Content>
    <News_Date>2026-05-15 11:20:30</News_Date>
    <News_count>7</News_count>
  </News>
</dataroot>
XML);

        $result = app(LegacyAspAccessSiteImporter::class)->import('site', '测试站点', $sourceDir, true);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $parentChannelId = (int) DB::table('channels')
            ->where('site_id', $siteId)
            ->where('slug', 'News')
            ->value('id');
        $firstArticleChannelId = (int) DB::table('channels')
            ->where('site_id', $siteId)
            ->where('slug', 'Garden-News')
            ->value('id');
        $secondArticleChannelId = (int) DB::table('channels')
            ->where('site_id', $siteId)
            ->where('slug', 'legacy-list-a12')
            ->value('id');
        $pageParentChannelId = (int) DB::table('channels')
            ->where('site_id', $siteId)
            ->where('slug', 'legacy-pages')
            ->value('id');
        $pageContentId = (int) DB::table('contents')
            ->where('site_id', $siteId)
            ->where('slug', 'legacy-page-content-1')
            ->value('id');
        $articleId = (int) DB::table('contents')
            ->where('site_id', $siteId)
            ->where('slug', 'legacy-news-100')
            ->value('id');
        $fallbackChannelId = (int) DB::table('channels')
            ->where('site_id', $siteId)
            ->where('slug', 'legacy-exception-content')
            ->value('id');
        $fallbackArticleId = (int) DB::table('contents')
            ->where('site_id', $siteId)
            ->where('slug', 'legacy-news-101')
            ->value('id');

        $this->assertSame(0, (int) $result['imported']['articles_skipped']);
        $this->assertGreaterThan(0, $parentChannelId);
        $this->assertDatabaseHas('channels', [
            'id' => $firstArticleChannelId,
            'parent_id' => $parentChannelId,
            'name' => '园内新闻',
        ]);
        $this->assertDatabaseHas('channels', [
            'id' => $secondArticleChannelId,
            'parent_id' => $parentChannelId,
            'name' => '园务公开',
        ]);
        $this->assertDatabaseMissing('channels', [
            'site_id' => $siteId,
            'slug' => 'legacy-list-1',
            'name' => '旧单页栏目应忽略',
        ]);
        $this->assertDatabaseHas('contents', [
            'id' => $articleId,
            'channel_id' => $firstArticleChannelId,
            'title' => '多栏目文章',
            'cover_image' => '/Up/demo.jpg',
            'view_count' => 23,
        ]);
        $this->assertDatabaseHas('content_channels', [
            'content_id' => $articleId,
            'channel_id' => $firstArticleChannelId,
        ]);
        $this->assertDatabaseHas('content_channels', [
            'content_id' => $articleId,
            'channel_id' => $secondArticleChannelId,
        ]);
        $this->assertDatabaseHas('channels', [
            'id' => $pageParentChannelId,
            'name' => '单页内容',
            'parent_id' => null,
            'type' => 'page',
        ]);
        $this->assertDatabaseMissing('channels', [
            'site_id' => $siteId,
            'slug' => 'legacy-page-1',
            'name' => '幼儿园介绍',
        ]);
        $this->assertDatabaseHas('channels', [
            'site_id' => $siteId,
            'slug' => 'legacy-pages',
            'type' => 'page',
        ]);
        $this->assertDatabaseHas('contents', [
            'id' => $pageContentId,
            'channel_id' => $pageParentChannelId,
            'type' => 'page',
            'title' => '幼儿园介绍',
        ]);
        $this->assertDatabaseHas('content_channels', [
            'content_id' => $pageContentId,
            'channel_id' => $pageParentChannelId,
        ]);
        $this->assertDatabaseHas('channels', [
            'id' => $fallbackChannelId,
            'name' => '异常内容',
            'parent_id' => null,
            'type' => 'list',
        ]);
        $this->assertDatabaseHas('contents', [
            'id' => $fallbackArticleId,
            'channel_id' => $fallbackChannelId,
            'title' => '异常栏目文章',
            'view_count' => 7,
        ]);
        $this->assertDatabaseHas('content_channels', [
            'content_id' => $fallbackArticleId,
            'channel_id' => $fallbackChannelId,
        ]);
        $this->assertContains('文章 101《异常栏目文章》未找到对应栏目 ID a22，已导入到“异常内容”。', $result['warnings']);
    }

    public function test_legacy_asp_importer_articles_only_updates_existing_articles_and_imports_missing_without_touching_pages(): void
    {
        $this->seed(DatabaseSeeder::class);

        $sourceDir = storage_path('framework/testing-legacy-asp-articles-only-'.uniqid());
        File::ensureDirectoryExists($sourceDir);
        $this->temporaryLegacyImportDirs[] = $sourceDir;

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        $parentChannelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => null,
            'name' => '已改父栏目',
            'slug' => 'News',
            'type' => 'list',
            'path' => '/News',
            'depth' => 0,
            'sort' => 1,
            'status' => 1,
            'is_nav' => 1,
            'list_template' => 'list',
            'detail_template' => 'detail',
            'link_url' => null,
            'link_target' => '_self',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $articleChannelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => $parentChannelId,
            'name' => '已改子栏目',
            'slug' => 'Garden-News',
            'type' => 'list',
            'path' => '/Garden-News',
            'depth' => 1,
            'sort' => 1,
            'status' => 1,
            'is_nav' => 1,
            'list_template' => 'list',
            'detail_template' => 'detail',
            'link_url' => null,
            'link_target' => '_self',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('contents')->insert([
            'site_id' => $siteId,
            'channel_id' => $articleChannelId,
            'type' => 'article',
            'template_name' => 'detail',
            'title' => '旧标题',
            'slug' => 'legacy-news-100',
            'summary' => '旧摘要',
            'content' => '<p>旧内容</p>',
            'cover_image' => null,
            'author' => null,
            'source' => '旧来源',
            'status' => 'published',
            'audit_status' => 'approved',
            'is_top' => 0,
            'is_recommend' => 0,
            'sort' => 100,
            'view_count' => 1,
            'published_at' => $now->copy()->subDay(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->writeLegacyImportSpreadsheet($sourceDir.'/Type_D.xlsx', [
            ['T_ID', 'T_Name', 'T_type', 'T_dlei', 'shunxu', 'en'],
            [1, '最新动态', 2, '', 110, 'News'],
        ]);

        $this->writeLegacyImportSpreadsheet($sourceDir.'/Type.xlsx', [
            ['Type_ID', 'Type_Name', 'Type_type', 'dalei', 'shunxu', 'flag', 'en'],
            ['a11', '园内新闻', 2, 1, 10, 1, 'Garden News'],
        ]);

        File::put($sourceDir.'/News.xml', <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<dataroot>
  <News>
    <News_ID>100</News_ID>
    <News_Type>a11</News_Type>
    <News_Title>更新后的文章</News_Title>
    <News_Pic>/Up/new-demo.jpg</News_Pic>
    <News_Content><p>更新后的正文</p></News_Content>
    <News_Date>2026-05-20 10:20:30</News_Date>
    <News_count>88</News_count>
  </News>
  <News>
    <News_ID>102</News_ID>
    <News_Type>a11</News_Type>
    <News_Title>补导新文章</News_Title>
    <News_Pic></News_Pic>
    <News_Content><p>补导正文</p></News_Content>
    <News_Date>2026-05-20 11:20:30</News_Date>
    <News_count>9</News_count>
  </News>
</dataroot>
XML);

        $result = app(LegacyAspAccessSiteImporter::class)->import('site', '测试站点', $sourceDir, true, true);

        $this->assertTrue((bool) $result['articles_only']);
        $this->assertSame(0, (int) $result['imported']['pages_created']);
        $this->assertSame(0, (int) $result['imported']['pages_updated']);
        $this->assertSame(1, (int) $result['imported']['articles_updated']);
        $this->assertSame(1, (int) $result['imported']['articles_created']);
        $this->assertDatabaseHas('channels', [
            'id' => $parentChannelId,
            'name' => '已改父栏目',
        ]);
        $this->assertDatabaseHas('channels', [
            'id' => $articleChannelId,
            'name' => '已改子栏目',
        ]);
        $this->assertDatabaseMissing('channels', [
            'site_id' => $siteId,
            'slug' => 'legacy-pages',
        ]);
        $this->assertDatabaseMissing('contents', [
            'site_id' => $siteId,
            'slug' => 'legacy-page-content-1',
        ]);
        $this->assertDatabaseHas('contents', [
            'site_id' => $siteId,
            'slug' => 'legacy-news-100',
            'title' => '更新后的文章',
            'cover_image' => '/Up/new-demo.jpg',
            'view_count' => 88,
        ]);
        $this->assertDatabaseHas('contents', [
            'site_id' => $siteId,
            'slug' => 'legacy-news-102',
            'title' => '补导新文章',
            'channel_id' => $articleChannelId,
        ]);
    }

    public function test_legacy_asp_importer_skips_unchanged_content_and_channel_writes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $sourceDir = storage_path('framework/testing-legacy-asp-unchanged-'.uniqid());
        File::ensureDirectoryExists($sourceDir);
        $this->temporaryLegacyImportDirs[] = $sourceDir;

        $site = DB::table('sites')
            ->where('site_key', 'site')
            ->first(['id', 'name']);
        $siteId = (int) $site->id;
        $siteName = (string) $site->name;
        $originalTimestamp = Carbon::parse('2026-05-20 09:00:00');
        $publishedAt = Carbon::parse('2026-05-20 10:20:30');

        $parentChannelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => null,
            'name' => '新闻',
            'slug' => 'News',
            'type' => 'list',
            'path' => '/News',
            'depth' => 0,
            'sort' => 1,
            'status' => 1,
            'is_nav' => 1,
            'list_template' => 'list',
            'detail_template' => 'detail',
            'link_url' => null,
            'link_target' => '_self',
            'created_at' => $originalTimestamp,
            'updated_at' => $originalTimestamp,
        ]);

        $articleChannelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => $parentChannelId,
            'name' => '园内新闻',
            'slug' => 'Garden-News',
            'type' => 'list',
            'path' => '/Garden-News',
            'depth' => 1,
            'sort' => 1,
            'status' => 1,
            'is_nav' => 1,
            'list_template' => 'list',
            'detail_template' => 'detail',
            'link_url' => null,
            'link_target' => '_self',
            'created_at' => $originalTimestamp,
            'updated_at' => $originalTimestamp,
        ]);

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $articleChannelId,
            'type' => 'article',
            'template_name' => 'detail',
            'title' => '重复导入文章',
            'title_color' => null,
            'title_bold' => 0,
            'title_italic' => 0,
            'sub_title' => null,
            'slug' => 'legacy-news-300',
            'summary' => '重复导入正文',
            'content' => '<p>重复导入正文</p>',
            'cover_image' => '/Up/same.jpg',
            'author' => null,
            'source' => $siteName,
            'status' => 'published',
            'audit_status' => 'approved',
            'is_top' => 0,
            'is_recommend' => 0,
            'sort' => 300,
            'view_count' => 5,
            'published_at' => $publishedAt,
            'created_at' => $originalTimestamp,
            'updated_at' => $originalTimestamp,
        ]);

        DB::table('content_channels')->insert([
            'content_id' => $contentId,
            'channel_id' => $articleChannelId,
            'created_at' => $originalTimestamp,
            'updated_at' => $originalTimestamp,
        ]);

        $this->writeLegacyImportSpreadsheet($sourceDir.'/Type_D.xlsx', [
            ['T_ID', 'T_Name', 'T_type', 'T_dlei', 'shunxu', 'en'],
            [1, '新闻', 2, '', 110, 'News'],
        ]);

        $this->writeLegacyImportSpreadsheet($sourceDir.'/Type.xlsx', [
            ['Type_ID', 'Type_Name', 'Type_type', 'dalei', 'shunxu', 'flag', 'en'],
            ['a11', '园内新闻', 2, 1, 10, 1, 'Garden News'],
        ]);

        File::put($sourceDir.'/News.xml', <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<dataroot>
  <News>
    <News_ID>300</News_ID>
    <News_Type>a11</News_Type>
    <News_Title>重复导入文章</News_Title>
    <News_Pic>/Up/same.jpg</News_Pic>
    <News_Content><p>重复导入正文</p></News_Content>
    <News_Date>2026-05-20 10:20:30</News_Date>
    <News_count>5</News_count>
  </News>
</dataroot>
XML);

        Carbon::setTestNow(Carbon::parse('2026-05-28 12:00:00'));

        try {
            $result = app(LegacyAspAccessSiteImporter::class)->import('site', '测试站点', $sourceDir, true, true);
        } finally {
            Carbon::setTestNow();
        }

        $content = DB::table('contents')
            ->where('id', $contentId)
            ->first(['updated_at']);
        $contentChannel = DB::table('content_channels')
            ->where('content_id', $contentId)
            ->where('channel_id', $articleChannelId)
            ->first(['updated_at']);

        $this->assertSame(1, (int) $result['imported']['articles_updated']);
        $this->assertSame($originalTimestamp->format('Y-m-d H:i:s'), Carbon::parse($content->updated_at)->format('Y-m-d H:i:s'));
        $this->assertSame($originalTimestamp->format('Y-m-d H:i:s'), Carbon::parse($contentChannel->updated_at)->format('Y-m-d H:i:s'));
    }

    public function test_legacy_asp_importer_articles_only_imports_cover_only_article_without_body(): void
    {
        $this->seed(DatabaseSeeder::class);

        $sourceDir = storage_path('framework/testing-legacy-asp-cover-only-'.uniqid());
        File::ensureDirectoryExists($sourceDir);
        $this->temporaryLegacyImportDirs[] = $sourceDir;

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        $parentChannelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => null,
            'name' => '新闻',
            'slug' => 'News',
            'type' => 'list',
            'path' => '/News',
            'depth' => 0,
            'sort' => 1,
            'status' => 1,
            'is_nav' => 1,
            'list_template' => 'list',
            'detail_template' => 'detail',
            'link_url' => null,
            'link_target' => '_self',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $articleChannelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => $parentChannelId,
            'name' => '园内新闻',
            'slug' => 'Garden-News',
            'type' => 'list',
            'path' => '/Garden-News',
            'depth' => 1,
            'sort' => 1,
            'status' => 1,
            'is_nav' => 1,
            'list_template' => 'list',
            'detail_template' => 'detail',
            'link_url' => null,
            'link_target' => '_self',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->writeLegacyImportSpreadsheet($sourceDir.'/Type_D.xlsx', [
            ['T_ID', 'T_Name', 'T_type', 'T_dlei', 'shunxu', 'en'],
            [1, '新闻', 2, '', 110, 'News'],
        ]);

        $this->writeLegacyImportSpreadsheet($sourceDir.'/Type.xlsx', [
            ['Type_ID', 'Type_Name', 'Type_type', 'dalei', 'shunxu', 'flag', 'en'],
            ['a11', '园内新闻', 2, 1, 10, 1, 'Garden News'],
        ]);

        File::put($sourceDir.'/News.xml', <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<dataroot>
  <News>
    <News_ID>2472</News_ID>
    <News_Type>a11</News_Type>
    <News_Title>六一儿童节花絮</News_Title>
    <News_Pic>/Up/liuyi.jpg</News_Pic>
    <News_Content></News_Content>
    <News_Date>2026-05-20 10:20:30</News_Date>
    <News_count>6</News_count>
  </News>
</dataroot>
XML);

        $result = app(LegacyAspAccessSiteImporter::class)->import('site', '测试站点', $sourceDir, true, true);

        $content = DB::table('contents')
            ->where('site_id', $siteId)
            ->where('slug', 'legacy-news-2472')
            ->first(['title', 'cover_image', 'content', 'channel_id']);

        $this->assertSame(0, (int) $result['imported']['articles_skipped']);
        $this->assertSame(1, (int) $result['imported']['articles_created']);
        $this->assertContains('文章 2472《六一儿童节花絮》缺少正文，已按封面图导入。', $result['warnings']);
        $this->assertNotNull($content);
        $this->assertSame('六一儿童节花絮', $content->title);
        $this->assertSame('/Up/liuyi.jpg', $content->cover_image);
        $this->assertSame($articleChannelId, (int) $content->channel_id);
        $this->assertStringContainsString('<img src="/Up/liuyi.jpg"', (string) $content->content);
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
            app(ModuleManager::class)->synchronize();

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

        app(ModuleManager::class)->synchronize();
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
        app(ModuleManager::class)->synchronize();

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

    public function test_guestbook_frontend_submission_dispatches_notification_job_when_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);
        Bus::fake();

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        foreach ([
            'module.guestbook.captcha_enabled' => '0',
            'module.guestbook.email_notify_enabled' => '1',
            'module.guestbook.email_notify_on' => 'submitted',
            'module.guestbook.email_notify_to' => 'guestbook@example.com',
        ] as $key => $value) {
            DB::table('site_settings')->updateOrInsert(
                ['site_id' => $siteId, 'setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $this->post(route('site.guestbook.store', ['site' => 'site']), [
            'name' => '张老师',
            'phone' => '13800138000',
            'content' => '这里是前台提交的留言内容，希望尽快收到回复。',
        ])->assertRedirect(route('site.guestbook.index', ['site' => 'site']));

        Bus::assertDispatched(SendGuestbookMessageNotificationJob::class, function ($job) use ($siteId): bool {
            return $job->siteId === $siteId
                && $job->trigger === 'submitted';
        });
    }

    public function test_guestbook_frontend_failed_submissions_are_rate_limited(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $rateLimitKey = 'guestbook-submit-ip:'.$siteId.':'.sha1('127.0.0.1');
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit($rateLimitKey, 60);
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

    public function test_guestbook_frontend_requires_captcha_after_repeat_submission_risk(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );
        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.guestbook.captcha_enabled'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->post(route('site.guestbook.store', ['site' => 'site']), [
            'name' => '张老师',
            'phone' => '13800138000',
            'content' => '第一条留言。',
        ])->assertRedirect(route('site.guestbook.index', ['site' => 'site']));

        $this->postJson(route('site.guestbook.store', ['site' => 'site']), [
            'name' => '张老师',
            'phone' => '13800138000',
            'content' => '第二条留言。',
        ])->assertStatus(422)->assertJsonValidationErrors(['captcha']);
    }

    public function test_guestbook_frontend_list_only_shows_public_messages(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

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
        app(ModuleManager::class)->synchronize();

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
        app(ModuleManager::class)->synchronize();

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
        app(ModuleManager::class)->synchronize();

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
        app(ModuleManager::class)->synchronize();

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
        app(ModuleManager::class)->synchronize();

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
        app(ModuleManager::class)->synchronize();

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
                'email_notify_enabled' => '1',
                'email_notify_to' => 'guestbook@example.com',
                'email_notify_on' => 'replied',
            ])
            ->assertRedirect(route('admin.guestbook.settings'));

        $this->assertSame('校长留言板', DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'module.guestbook.name')
            ->value('setting_value'));
        $this->assertSame('1', DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'module.guestbook.email_notify_enabled')
            ->value('setting_value'));
        $this->assertSame('guestbook@example.com', DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'module.guestbook.email_notify_to')
            ->value('setting_value'));
        $this->assertSame('replied', DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'module.guestbook.email_notify_on')
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

    public function test_guestbook_admin_reply_dispatches_notification_job_when_configured(): void
    {
        $this->seed(DatabaseSeeder::class);
        Bus::fake();

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        foreach ([
            'module.guestbook.email_notify_enabled' => '1',
            'module.guestbook.email_notify_on' => 'replied',
            'module.guestbook.email_notify_to' => 'guestbook@example.com',
        ] as $key => $value) {
            DB::table('site_settings')->updateOrInsert(
                ['site_id' => $siteId, 'setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $messageId = DB::table('module_guestbook_messages')->insertGetId([
            'site_id' => $siteId,
            'display_no' => 11,
            'name' => '李老师',
            'phone' => '13800138002',
            'content' => '请问本周是否开放校内参观？',
            'status' => 'pending',
            'is_read' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $siteAdmin = $this->createSiteOperator('guestbook-reply-mail-admin', true, 'site_admin');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.guestbook.update', $messageId), [
                'content' => '请问本周是否开放校内参观？',
                'reply_content' => '您好，本周五下午开放参观，请提前预约。',
            ])
            ->assertRedirect(route('admin.guestbook.show', $messageId));

        Bus::assertDispatched(SendGuestbookMessageNotificationJob::class, function ($job) use ($siteId, $messageId): bool {
            return $job->siteId === $siteId
                && $job->messageId === $messageId
                && $job->trigger === 'replied';
        });
    }

    public function test_guestbook_admin_can_mark_message_resolved_offline(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $messageId = (int) DB::table('module_guestbook_messages')->insertGetId([
            'site_id' => $siteId,
            'display_no' => 601,
            'name' => '线下办理留言',
            'phone' => '13800138101',
            'content' => '这条留言在线下已经处理。',
            'status' => 'pending',
            'is_read' => 0,
            'reply_content' => null,
            'replied_at' => null,
            'replied_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $siteAdmin = $this->createSiteOperator('guestbook-resolve-offline-admin', true, 'site_admin');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.guestbook.resolve-offline', $messageId), [
                'keyword' => '线下',
                'read_status' => 'unread',
                'reply_status' => 'pending',
                'page' => 2,
            ])
            ->assertRedirect(route('admin.guestbook.index', [
                'keyword' => '线下',
                'read_status' => 'unread',
                'reply_status' => 'pending',
                'page' => 2,
            ]));

        $this->assertDatabaseHas('module_guestbook_messages', [
            'id' => $messageId,
            'status' => 'resolved_offline',
            'reply_content' => null,
            'replied_by' => $siteAdmin->id,
            'is_read' => 1,
        ]);

        $this->assertSame(0, DB::table('module_guestbook_messages')
            ->where('site_id', $siteId)
            ->where('status', 'pending')
            ->count());
    }

    public function test_guestbook_frontend_does_not_show_offline_resolved_message_when_show_after_reply_is_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::table('module_guestbook_messages')->insert([
            [
                'site_id' => $siteId,
                'display_no' => 701,
                'name' => '公开回复留言',
                'phone' => '13800138201',
                'content' => '这条留言有公开回复。',
                'status' => 'replied',
                'is_read' => 1,
                'reply_content' => '这是公开回复。',
                'replied_at' => now(),
                'replied_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $siteId,
                'display_no' => 702,
                'name' => '线下办理留言',
                'phone' => '13800138202',
                'content' => '这条留言线下办理，不应前台公开。',
                'status' => 'resolved_offline',
                'is_read' => 1,
                'reply_content' => null,
                'replied_at' => now(),
                'replied_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->get(route('site.guestbook.index', ['site' => 'site']))
            ->assertOk()
            ->assertSee('这条留言有公开回复。')
            ->assertDontSee('这条留言线下办理，不应前台公开。');

        $this->get(route('site.guestbook.show', ['site' => 'site', 'displayNo' => 702]))
            ->assertNotFound();
    }

    public function test_cms_bootstrap_seeder_does_not_override_existing_site_active_template(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $defaultTemplateId = (int) DB::table('site_templates')
            ->where('site_id', $siteId)
            ->where('template_key', 'default')
            ->value('id');

        $customTemplateId = (int) DB::table('site_templates')->insertGetId([
            'site_id' => $siteId,
            'name' => '正式模板',
            'template_key' => 'formal',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sites')->where('id', $siteId)->update([
            'active_site_template_id' => $customTemplateId,
            'updated_at' => now(),
        ]);

        $this->seed(CmsBootstrapSeeder::class);

        $this->assertSame($customTemplateId, (int) DB::table('sites')->where('id', $siteId)->value('active_site_template_id'));
        $this->assertNotSame($defaultTemplateId, (int) DB::table('sites')->where('id', $siteId)->value('active_site_template_id'));
    }

    public function test_cms_bootstrap_seeder_does_not_recreate_demo_site_content_for_existing_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_domains')
            ->where('site_id', $siteId)
            ->where('domain', 'site.local')
            ->delete();

        DB::table('contents')
            ->where('site_id', $siteId)
            ->where('slug', 'platform-welcome-notice')
            ->delete();

        DB::table('channels')
            ->where('site_id', $siteId)
            ->where('slug', 'platform-notices')
            ->delete();

        $this->seed(CmsBootstrapSeeder::class);

        $this->assertDatabaseMissing('site_domains', [
            'site_id' => $siteId,
            'domain' => 'site.local',
        ]);

        $this->assertDatabaseMissing('channels', [
            'site_id' => $siteId,
            'slug' => 'platform-notices',
        ]);

        $this->assertDatabaseMissing('contents', [
            'site_id' => $siteId,
            'slug' => 'platform-welcome-notice',
        ]);
    }

    public function test_guestbook_notification_job_sends_mail_using_site_contact_email_fallback(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        DB::table('sites')->where('id', $siteId)->update([
            'contact_email' => 'school@example.com',
            'updated_at' => now(),
        ]);

        foreach ([
            'mail.enabled' => '1',
            'mail.driver' => 'log',
            'mail.from_address' => 'no-reply@example.com',
            'mail.from_name' => '站点通知',
            'mail.rate_limit_enabled' => '0',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        foreach ([
            'module.guestbook.email_notify_enabled' => '1',
            'module.guestbook.email_notify_on' => 'submitted',
            'module.guestbook.email_notify_to' => '',
        ] as $key => $value) {
            DB::table('site_settings')->updateOrInsert(
                ['site_id' => $siteId, 'setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $messageId = DB::table('module_guestbook_messages')->insertGetId([
            'site_id' => $siteId,
            'display_no' => 21,
            'name' => '王老师',
            'phone' => '13800138021',
            'content' => '这里是一条需要发送通知的留言内容。',
            'status' => 'pending',
            'is_read' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $job = new SendGuestbookMessageNotificationJob($siteId, $messageId, 'submitted');
        $job->handle(app(PlatformMailSettings::class), app(GuestbookSettings::class));

        Mail::assertSent(GuestbookMessageNotificationMail::class, function ($mail): bool {
            return $mail->hasTo('school@example.com');
        });
    }

    public function test_guestbook_list_delete_requires_delete_permission_and_removes_message(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $messageId = (int) DB::table('module_guestbook_messages')->insertGetId([
            'site_id' => $siteId,
            'display_no' => 31,
            'name' => '删除测试',
            'phone' => '13800138031',
            'content' => '这是一条待删除的留言。',
            'status' => 'pending',
            'is_read' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $viewer = $this->createCustomSiteOperator('guestbook-delete-viewer', $siteId, ['guestbook.view']);
        $deleter = $this->createCustomSiteOperator('guestbook-delete-operator', $siteId, ['guestbook.view', 'guestbook.delete']);

        $this->actingAs($viewer)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.guestbook.index'))
            ->assertOk()
            ->assertDontSee('>删除<', false);

        $this->actingAs($viewer)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.guestbook.destroy', $messageId))
            ->assertForbidden();

        $this->assertDatabaseHas('module_guestbook_messages', [
            'id' => $messageId,
            'site_id' => $siteId,
        ]);

        $this->actingAs($deleter)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.guestbook.index'))
            ->assertOk()
            ->assertSee('>删除<', false);

        $this->actingAs($deleter)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.guestbook.destroy', $messageId))
            ->assertRedirect(route('admin.guestbook.index'));

        $this->assertDatabaseMissing('module_guestbook_messages', [
            'id' => $messageId,
            'site_id' => $siteId,
        ]);
    }

    public function test_guestbook_settings_reject_external_notice_image(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

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
        app(ModuleManager::class)->synchronize();

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
        app(ModuleManager::class)->synchronize();

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

    public function test_guestbook_settings_validate_name_length_and_notification_email(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');
        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $siteAdmin = $this->createSiteOperator('guestbook-settings-guard-admin', true, 'site_admin');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.guestbook.settings'))
            ->post(route('admin.guestbook.settings.update'), [
                'enabled' => '1',
                'name' => '这是一个明显超出十五个中文字符限制的留言板名称',
                'notice' => '请文明留言，内容需真实准确。',
                'theme' => 'default',
                'show_name' => '1',
                'show_after_reply' => '1',
                'captcha_enabled' => '1',
                'email_notify_enabled' => '1',
                'email_notify_to' => "bad@example.com\r\nBcc:test@example.com",
                'email_notify_on' => 'submitted',
            ])
            ->assertRedirect(route('admin.guestbook.settings'))
            ->assertSessionHasErrors([
                'name' => '留言板名称最多支持 15 个中文字符。',
                'email_notify_to' => '通知收件邮箱格式不正确，请重新填写。',
            ]);
    }

    public function test_guestbook_reply_only_user_cannot_edit_original_message_content(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

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
        app(ModuleManager::class)->synchronize();

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
        app(ModuleManager::class)->synchronize();

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
        app(ModuleManager::class)->synchronize();

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
            ->assertSee('2026年03月')
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

    public function test_payroll_frontend_detail_hides_zero_amount_rows_and_keeps_take_home_total(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

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
            'wechat_openid' => 'wx-detail-user',
            'wechat_nickname' => '明老师',
            'name' => '明老师',
            'mobile' => '13800138001',
            'status' => 'approved',
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batchId = (int) DB::table('module_payroll_batches')->insertGetId([
            'site_id' => $siteId,
            'month_key' => '2026-04',
            'status' => 'imported',
            'salary_file_name' => 'salary-april.xls',
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('module_payroll_records')->insert([
            'site_id' => $siteId,
            'batch_id' => $batchId,
            'employee_name' => '明老师',
            'sheet_type' => 'salary',
            'items_json' => json_encode([
                ['label' => '姓名', 'value' => '明老师'],
                ['label' => '岗位工资', 'value' => '3810'],
                ['label' => '补发金额', 'value' => '0.00'],
                ['label' => '实发合计', 'value' => '3810.00'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'row_hash' => hash('sha256', 'detail-zero-filter'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession([
            'payroll.identity.'.$siteId => [
                'openid' => 'wx-detail-user',
                'unionid' => '',
                'nickname' => '明老师',
                'avatar' => '',
            ],
        ])->get(route('site.payroll.show', ['batch' => $batchId, 'type' => 'salary', 'site' => 'site']))
            ->assertOk()
            ->assertSee('岗位工资')
            ->assertDontSee('补发金额')
            ->assertSee('实发合计')
            ->assertSee('row-total', false);
    }

    public function test_payroll_frontend_local_site_preview_bootstraps_demo_data_without_wechat(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

        $response = $this->followingRedirects()
            ->get(route('site.payroll.index', ['site' => 'site']));

        $response->assertOk()
            ->assertSee('本地预览老师')
            ->assertSee('我的薪资信息列表')
            ->assertSee('工资条')
            ->assertSee('绩效');

        $this->assertDatabaseHas('module_payroll_employees', [
            'site_id' => $siteId,
            'wechat_openid' => 'wx-local-payroll-preview',
            'name' => '本地预览老师',
            'status' => 'approved',
            'password_enabled' => 0,
        ]);

        $employeeName = '本地预览老师';
        $this->assertSame(50, DB::table('module_payroll_batches')
            ->where('site_id', $siteId)
            ->where('salary_file_name', 'like', 'local-preview-salary-%')
            ->count());
        $this->assertSame(100, DB::table('module_payroll_records')
            ->where('site_id', $siteId)
            ->where('employee_name', $employeeName)
            ->count());
    }

    public function test_payroll_frontend_local_site_preview_batches_are_paginated(): void
    {
        $this->seed(DatabaseSeeder::class);

        app(ModuleManager::class)->synchronize();

        $firstPage = $this->followingRedirects()
            ->get(route('site.payroll.index', ['site' => 'site']));

        $firstPage->assertOk()
            ->assertSee('payroll_page=2', false)
            ->assertSee('工资条')
            ->assertSee('绩效');

        preg_match_all('/\d{4}年\d+月/u', $firstPage->getContent(), $firstPageMonths);
        $this->assertCount(10, array_unique($firstPageMonths[0]));

        $secondPage = $this->followingRedirects()
            ->get(route('site.payroll.index', ['site' => 'site', 'payroll_page' => 2]));

        $secondPage->assertOk()
            ->assertSee('payroll_page=1', false);
    }

    public function test_payroll_frontend_local_site_preview_preserves_password_settings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

        $this->followingRedirects()
            ->get(route('site.payroll.index', ['site' => 'site']))
            ->assertOk();

        $hash = Hash::make('123456');

        DB::table('module_payroll_employees')
            ->where('site_id', $siteId)
            ->where('wechat_openid', 'wx-local-payroll-preview')
            ->update([
                'password_enabled' => 1,
                'password_hash' => $hash,
                'updated_at' => now(),
            ]);

        $this->get(route('site.payroll.logout', ['site' => 'site']));

        $this->followingRedirects()
            ->get(route('site.payroll.index', ['site' => 'site']))
            ->assertOk()
            ->assertSee('请输入密码');

        $this->assertDatabaseHas('module_payroll_employees', [
            'site_id' => $siteId,
            'wechat_openid' => 'wx-local-payroll-preview',
            'password_enabled' => 1,
            'password_hash' => $hash,
        ]);
    }

    public function test_payroll_password_page_redirects_to_index_when_password_protection_has_been_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'payroll')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.payroll.enabled'],
            ['setting_value' => '1', 'autoload' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        $employeeId = (int) DB::table('module_payroll_employees')->insertGetId([
            'site_id' => $siteId,
            'wechat_openid' => 'wx-password-refresh-user',
            'wechat_unionid' => '',
            'wechat_nickname' => '密码页用户',
            'wechat_avatar' => '',
            'name' => '密码页老师',
            'mobile' => '13800138111',
            'status' => 'approved',
            'password_enabled' => 1,
            'password_hash' => Hash::make('123456'),
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batchId = (int) DB::table('module_payroll_batches')->insertGetId([
            'site_id' => $siteId,
            'month_key' => '2026-05',
            'status' => 'imported',
            'salary_file_name' => 'salary.xlsx',
            'performance_file_name' => 'performance.xlsx',
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('module_payroll_records')->insert([
            'site_id' => $siteId,
            'batch_id' => $batchId,
            'employee_name' => '密码页老师',
            'sheet_type' => 'salary',
            'items_json' => json_encode([['label' => '姓名', 'value' => '密码页老师']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'row_hash' => hash('sha256', 'password-refresh-salary'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $session = [
            'payroll.identity.'.$siteId => [
                'openid' => 'wx-password-refresh-user',
                'unionid' => '',
                'nickname' => '密码页用户',
                'avatar' => '',
            ],
        ];

        $this->withSession($session)
            ->get(route('site.payroll.password', ['site' => 'site']))
            ->assertOk()
            ->assertSee('请输入密码');

        DB::table('module_payroll_employees')
            ->where('id', $employeeId)
            ->update([
                'password_enabled' => 0,
                'password_hash' => null,
                'updated_at' => now(),
            ]);

        $this->withSession($session)
            ->get(route('site.payroll.password', ['site' => 'site']))
            ->assertRedirect(route('site.payroll.index', ['site' => 'site']));

        $this->followingRedirects()
            ->withSession($session)
            ->post(route('site.payroll.password.unlock', ['site' => 'site']), ['password' => '123456'])
            ->assertOk()
            ->assertSee('我的薪资信息列表')
            ->assertSee('工资条');
    }

    public function test_payroll_password_flow_is_not_treated_as_rapid_path_scan(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        app(ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'payroll')->update(['status' => 1, 'updated_at' => $now]);
        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => $now, 'updated_at' => $now],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.payroll.enabled'],
            ['setting_value' => '1', 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now],
        );

        foreach ([
            'security.scan_probe_enabled' => '1',
            'security.scan_probe_window_seconds' => '300',
            'security.scan_probe_threshold' => '3',
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '100',
            'security.rate_limit_sensitive_max_requests' => '20',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        DB::table('module_payroll_employees')->insert([
            'site_id' => $siteId,
            'wechat_openid' => 'wx-payroll-scan-user',
            'wechat_unionid' => '',
            'wechat_nickname' => '工资测试用户',
            'wechat_avatar' => '',
            'name' => '工资测试老师',
            'mobile' => '13800138112',
            'status' => 'approved',
            'password_enabled' => 1,
            'password_hash' => Hash::make('123456'),
            'approved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Cache::forget($this->siteSecurityPathScanKey());
        RateLimiter::clear($this->siteSecurityProbeBlockKey());

        $session = [
            'payroll.identity.'.$siteId => [
                'openid' => 'wx-payroll-scan-user',
                'unionid' => '',
                'nickname' => '工资测试用户',
                'avatar' => '',
            ],
        ];

        $this->withSession($session)
            ->get(route('site.payroll.index', ['site' => 'site']))
            ->assertRedirect(route('site.payroll.password', ['site' => 'site']));

        $this->withSession($session)
            ->get(route('site.payroll.password', ['site' => 'site']))
            ->assertOk()
            ->assertSee('请输入密码');

        $this->withSession($session)
            ->post(route('site.payroll.password.unlock', ['site' => 'site']), ['password' => '123456'])
            ->assertRedirect(route('site.payroll.index', ['site' => 'site']));

        $this->assertDatabaseMissing('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'probe_abuse',
            'request_path' => '/payroll/password/unlock',
        ]);
    }

    public function test_guestbook_frontend_routes_are_not_treated_as_rapid_path_scan(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        app(ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'guestbook')->update(['status' => 1, 'updated_at' => $now]);
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => $now, 'updated_at' => $now],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.guestbook.enabled'],
            ['setting_value' => '1', 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now],
        );

        foreach ([
            'security.scan_probe_enabled' => '1',
            'security.scan_probe_window_seconds' => '300',
            'security.scan_probe_threshold' => '3',
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '100',
            'security.rate_limit_sensitive_max_requests' => '20',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        DB::table('module_guestbook_messages')->insert([
            'site_id' => $siteId,
            'display_no' => 100001,
            'name' => '留言老师',
            'phone' => '13800138000',
            'content' => '留言内容测试',
            'status' => 'replied',
            'is_read' => 1,
            'reply_content' => '留言回复测试',
            'replied_at' => $now,
            'replied_by' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Cache::forget($this->siteSecurityPathScanKey());
        RateLimiter::clear($this->siteSecurityProbeBlockKey());

        $this->get(route('site.guestbook.index', ['site' => 'site']))->assertOk();
        $this->get(route('site.guestbook.create', ['site' => 'site']))->assertOk();
        $this->get(route('site.guestbook.captcha', ['site' => 'site']))->assertOk();
        $this->get(route('site.guestbook.show', ['site' => 'site', 'displayNo' => 100001]))->assertOk();

        $this->assertDatabaseMissing('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'probe_abuse',
        ]);
    }

    public function test_payroll_settings_can_be_updated_without_callback_url(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

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
        app(ModuleManager::class)->synchronize();

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
        app(ModuleManager::class)->synchronize();

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
        app(ModuleManager::class)->synchronize();

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
        app(ModuleManager::class)->synchronize();

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
        app(ModuleManager::class)->synchronize();

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

    public function test_payroll_frontend_same_session_does_not_rewrite_employee_when_profile_is_unchanged(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

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

        $employeeId = (int) DB::table('module_payroll_employees')->insertGetId([
            'site_id' => $siteId,
            'wechat_openid' => 'wx-no-rewrite-user',
            'wechat_unionid' => 'union-no-rewrite',
            'wechat_nickname' => '稳定昵称',
            'wechat_avatar' => 'https://example.com/avatar.png',
            'name' => '稳定老师',
            'mobile' => '13800138009',
            'status' => 'approved',
            'password_enabled' => 0,
            'password_hash' => null,
            'approved_at' => now(),
            'last_login_at' => null,
            'last_login_ip' => null,
            'created_at' => now(),
            'updated_at' => now()->subDay(),
        ]);

        $batchId = (int) DB::table('module_payroll_batches')->insertGetId([
            'site_id' => $siteId,
            'month_key' => '2026-05',
            'status' => 'imported',
            'salary_file_name' => 'salary.xlsx',
            'performance_file_name' => 'performance.xlsx',
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('module_payroll_records')->insert([
            'site_id' => $siteId,
            'batch_id' => $batchId,
            'employee_name' => '稳定老师',
            'sheet_type' => 'salary',
            'items_json' => json_encode([['label' => '姓名', 'value' => '稳定老师']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'row_hash' => hash('sha256', 'no-rewrite-salary'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $identitySession = [
            'payroll.identity.'.$siteId => [
                'openid' => 'wx-no-rewrite-user',
                'unionid' => 'union-no-rewrite',
                'nickname' => '稳定昵称',
                'avatar' => 'https://example.com/avatar.png',
            ],
        ];

        $this->withSession($identitySession)
            ->get(route('site.payroll.index', ['site' => 'site']))
            ->assertOk();

        $firstUpdatedAt = DB::table('module_payroll_employees')
            ->where('id', $employeeId)
            ->value('updated_at');

        $loginSession = array_merge($identitySession, [
            'payroll.login.'.$siteId.'.'.$employeeId => true,
        ]);

        $this->travel(5)->seconds();

        $this->withSession($loginSession)
            ->get(route('site.payroll.index', ['site' => 'site']))
            ->assertOk();

        $secondUpdatedAt = DB::table('module_payroll_employees')
            ->where('id', $employeeId)
            ->value('updated_at');

        $this->assertSame((string) $firstUpdatedAt, (string) $secondUpdatedAt);
        $this->assertSame(1, DB::table('module_payroll_login_logs')
            ->where('site_id', $siteId)
            ->where('employee_id', $employeeId)
            ->count());
    }

    public function test_payroll_frontend_logout_redirects_to_logged_out_page_and_clears_session(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'payroll')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'module.payroll.enabled'],
            ['setting_value' => '1', 'autoload' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        $employeeId = (int) DB::table('module_payroll_employees')->insertGetId([
            'site_id' => $siteId,
            'wechat_openid' => 'wx-logout-user',
            'wechat_nickname' => '退出老师',
            'name' => '退出老师',
            'mobile' => '13800138002',
            'status' => 'approved',
            'password_enabled' => 0,
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession([
            'payroll.identity.'.$siteId => [
                'openid' => 'wx-logout-user',
                'unionid' => '',
                'nickname' => '退出老师',
                'avatar' => '',
            ],
            'payroll.unlock.'.$siteId.'.'.$employeeId => true,
            'payroll.login.'.$siteId.'.'.$employeeId => now()->timestamp,
        ])->post(route('site.payroll.logout', ['site' => 'site']));

        $response->assertRedirect(route('site.payroll.logout.done', ['site' => 'site']));
        $response->assertSessionMissing('payroll.identity.'.$siteId);
        $response->assertSessionMissing('payroll.unlock.'.$siteId.'.'.$employeeId);
        $response->assertSessionMissing('payroll.login.'.$siteId.'.'.$employeeId);

        $this->get(route('site.payroll.logout.done', ['site' => 'site']))
            ->assertOk()
            ->assertSee('你已经安全退出')
            ->assertSee('可直接关闭此页面');
    }

    public function test_site_admin_can_export_payroll_batch_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

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

        $response = $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.payroll.batches.export', ['batch' => $batchId, 'type' => 'salary']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF姓名,", $content);
        $this->assertStringContainsString('岗位工资', $content);
        $this->assertStringContainsString('沐老师', $content);
    }

    public function test_payroll_import_rejects_duplicate_names_in_uploaded_sheet(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

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

    public function test_payroll_import_accepts_standard_horizontal_csv_sheet(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'payroll')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $batchId = (int) DB::table('module_payroll_batches')->insertGetId([
            'site_id' => $siteId,
            'month_key' => '2026-06',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('module_payroll_employees')->insert([
            'site_id' => $siteId,
            'name' => '王老师',
            'mobile' => '13800138000',
            'wechat_openid' => 'wx-csv-import-001',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $siteAdmin = $this->createSiteOperator('payroll-csv-import-admin', true, 'site_admin');
        $csvFile = $this->createPayrollCsvUpload([
            ['姓名', '岗位工资', '薪级工资'],
            ['王老师', '3810', '4201'],
        ], 'salary.csv');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.payroll.batches.edit', $batchId))
            ->post(route('admin.payroll.batches.update', $batchId), [
                'salary_file' => $csvFile,
            ])
            ->assertRedirect(route('admin.payroll.batches.edit', $batchId))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('module_payroll_records', [
            'site_id' => $siteId,
            'batch_id' => $batchId,
            'employee_name' => '王老师',
            'sheet_type' => 'salary',
        ]);

        $firstRecord = DB::table('module_payroll_records')
            ->where('site_id', $siteId)
            ->where('batch_id', $batchId)
            ->where('employee_name', '王老师')
            ->where('sheet_type', 'salary')
            ->first(['id', 'row_hash']);

        $sameCsvFile = $this->createPayrollCsvUpload([
            ['姓名', '岗位工资', '薪级工资'],
            ['王老师', '3810', '4201'],
        ], 'salary-again.csv');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.payroll.batches.edit', $batchId))
            ->post(route('admin.payroll.batches.update', $batchId), [
                'salary_file' => $sameCsvFile,
            ])
            ->assertRedirect(route('admin.payroll.batches.edit', $batchId))
            ->assertSessionDoesntHaveErrors();

        $secondRecord = DB::table('module_payroll_records')
            ->where('site_id', $siteId)
            ->where('batch_id', $batchId)
            ->where('employee_name', '王老师')
            ->where('sheet_type', 'salary')
            ->first(['id', 'row_hash']);

        $this->assertSame((int) $firstRecord->id, (int) $secondRecord->id);
        $this->assertSame((string) $firstRecord->row_hash, (string) $secondRecord->row_hash);
        $this->assertSame(1, DB::table('module_payroll_records')
            ->where('site_id', $siteId)
            ->where('batch_id', $batchId)
            ->where('sheet_type', 'salary')
            ->count());
    }

    public function test_payroll_import_accepts_legacy_xls_sheet(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'payroll')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $batchId = (int) DB::table('module_payroll_batches')->insertGetId([
            'site_id' => $siteId,
            'month_key' => '2026-08',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('module_payroll_employees')->insert([
            'site_id' => $siteId,
            'name' => '李老师',
            'mobile' => '13800138001',
            'wechat_openid' => 'wx-xls-import-001',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $siteAdmin = $this->createSiteOperator('payroll-xls-import-admin', true, 'site_admin');
        $xlsFile = $this->createPayrollSpreadsheetUpload([
            ['姓名', '岗位工资', '薪级工资'],
            ['李老师', '4000', '2100'],
        ], 'salary-legacy.xls');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.payroll.batches.edit', $batchId))
            ->post(route('admin.payroll.batches.update', $batchId), [
                'salary_file' => $xlsFile,
            ])
            ->assertRedirect(route('admin.payroll.batches.edit', $batchId))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('module_payroll_records', [
            'site_id' => $siteId,
            'batch_id' => $batchId,
            'employee_name' => '李老师',
            'sheet_type' => 'salary',
        ]);
    }

    public function test_payroll_import_rejects_non_standard_csv_with_excel_hint(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        app(ModuleManager::class)->synchronize();

        DB::table('modules')->where('code', 'payroll')->update(['status' => 1, 'updated_at' => now()]);
        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $batchId = (int) DB::table('module_payroll_batches')->insertGetId([
            'site_id' => $siteId,
            'month_key' => '2026-07',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $siteAdmin = $this->createSiteOperator('payroll-csv-invalid-admin', true, 'site_admin');
        $csvFile = $this->createPayrollCsvUpload([
            ['项目', '王老师', '李老师'],
            ['绩效A', '95', '88'],
        ], 'invalid-performance.csv');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->from(route('admin.payroll.batches.edit', $batchId))
            ->post(route('admin.payroll.batches.update', $batchId), [
                'performance_file' => $csvFile,
            ])
            ->assertRedirect(route('admin.payroll.batches.edit', $batchId))
            ->assertSessionHasErrors(['performance_file' => '该 CSV 不符合模板格式，请改用 Excel 文件上传。']);
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

        $manager = app(ModuleManager::class);

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

        $manager = app(ModuleManager::class);
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

    public function test_platform_admin_can_save_security_rate_limit_block_seconds_setting(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.platform.security.settings.update'), [
                'system_name' => '站群后台',
                'system_version' => '2.1.0',
                'current_tab' => 'security',
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
                'security_site_protection_enabled' => '1',
                'security_block_bad_path_enabled' => '1',
                'security_block_sql_injection_enabled' => '1',
                'security_block_xss_enabled' => '1',
                'security_block_path_traversal_enabled' => '1',
                'security_block_bad_upload_enabled' => '1',
                'security_block_bad_client_enabled' => '1',
                'security_block_bad_method_enabled' => '1',
                'security_block_bad_payload_enabled' => '1',
                'security_payload_max_fields' => '80',
                'security_payload_max_value_length' => '2000',
                'security_rate_limit_enabled' => '1',
                'security_rate_limit_window_seconds' => '12',
                'security_rate_limit_max_requests' => '33',
                'security_rate_limit_sensitive_max_requests' => '9',
                'security_rate_limit_block_seconds' => '90',
                'security_scan_probe_enabled' => '1',
                'security_scan_probe_window_seconds' => '600',
                'security_scan_probe_threshold' => '4',
                'security_malicious_auto_block_enabled' => '1',
                'security_malicious_auto_block_window_seconds' => '3600',
                'security_malicious_auto_block_threshold' => '10',
                'security_malicious_auto_block_seconds' => '86400',
                'security_event_retention_limit' => '260',
                'security_stats_retention_days' => '365',
            ])
            ->assertRedirect(route('admin.platform.security.index'))
            ->assertSessionHas('status', '安护盾设置已更新。');

        $this->assertSame('90', DB::table('system_settings')->where('setting_key', 'security.rate_limit_block_seconds')->value('setting_value'));
    }

    public function test_platform_admin_can_save_security_scan_probe_settings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.platform.security.settings.update'), [
                'system_name' => '站群后台',
                'system_version' => '2.1.0',
                'current_tab' => 'security',
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
                'security_site_protection_enabled' => '1',
                'security_block_bad_path_enabled' => '1',
                'security_block_sql_injection_enabled' => '1',
                'security_block_xss_enabled' => '1',
                'security_block_path_traversal_enabled' => '1',
                'security_block_bad_upload_enabled' => '1',
                'security_block_bad_client_enabled' => '1',
                'security_block_bad_method_enabled' => '1',
                'security_block_bad_payload_enabled' => '1',
                'security_payload_max_fields' => '80',
                'security_payload_max_value_length' => '2000',
                'security_rate_limit_enabled' => '1',
                'security_rate_limit_window_seconds' => '12',
                'security_rate_limit_max_requests' => '33',
                'security_rate_limit_sensitive_max_requests' => '9',
                'security_rate_limit_block_seconds' => '90',
                'security_scan_probe_enabled' => '1',
                'security_scan_probe_window_seconds' => '600',
                'security_scan_probe_threshold' => '4',
                'security_malicious_auto_block_enabled' => '1',
                'security_malicious_auto_block_window_seconds' => '3600',
                'security_malicious_auto_block_threshold' => '10',
                'security_malicious_auto_block_seconds' => '86400',
                'security_event_retention_limit' => '260',
                'security_stats_retention_days' => '365',
            ])
            ->assertRedirect(route('admin.platform.security.index'))
            ->assertSessionHas('status', '安护盾设置已更新。');

        $this->assertSame('1', DB::table('system_settings')->where('setting_key', 'security.scan_probe_enabled')->value('setting_value'));
        $this->assertSame('600', DB::table('system_settings')->where('setting_key', 'security.scan_probe_window_seconds')->value('setting_value'));
        $this->assertSame('4', DB::table('system_settings')->where('setting_key', 'security.scan_probe_threshold')->value('setting_value'));
    }

    public function test_platform_admin_can_save_security_ip_lists(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.platform.security.settings.update'), [
                'system_name' => '站群后台',
                'system_version' => '2.1.0',
                'current_tab' => 'security',
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
                'security_site_protection_enabled' => '1',
                'security_block_bad_path_enabled' => '1',
                'security_block_sql_injection_enabled' => '1',
                'security_block_xss_enabled' => '1',
                'security_block_path_traversal_enabled' => '1',
                'security_block_bad_upload_enabled' => '1',
                'security_block_bad_client_enabled' => '1',
                'security_block_bad_method_enabled' => '1',
                'security_block_bad_payload_enabled' => '1',
                'security_payload_max_fields' => '42',
                'security_payload_max_value_length' => '1200',
                'security_rate_limit_enabled' => '1',
                'security_rate_limit_window_seconds' => '12',
                'security_rate_limit_max_requests' => '33',
                'security_rate_limit_sensitive_max_requests' => '9',
                'security_rate_limit_block_seconds' => '90',
                'security_scan_probe_enabled' => '1',
                'security_scan_probe_window_seconds' => '600',
                'security_scan_probe_threshold' => '4',
                'security_malicious_auto_block_enabled' => '1',
                'security_malicious_auto_block_window_seconds' => '3600',
                'security_malicious_auto_block_threshold' => '10',
                'security_malicious_auto_block_seconds' => '86400',
                'security_ip_allowlist' => "192.168.1.10\n10.10.0.0/24",
                'security_ip_blocklist' => "203.0.113.7\n198.51.100.0/24",
                'security_event_retention_limit' => '260',
                'security_stats_retention_days' => '365',
            ])
            ->assertRedirect(route('admin.platform.security.index'))
            ->assertSessionHas('status', '安护盾设置已更新。');

        $this->assertSame("192.168.1.10\n10.10.0.0/24", DB::table('system_settings')->where('setting_key', 'security.ip_allowlist')->value('setting_value'));
        $this->assertSame("203.0.113.7\n198.51.100.0/24", DB::table('system_settings')->where('setting_key', 'security.ip_blocklist')->value('setting_value'));
        $this->assertSame('1', DB::table('system_settings')->where('setting_key', 'security.block_bad_client_enabled')->value('setting_value'));
        $this->assertSame('1', DB::table('system_settings')->where('setting_key', 'security.block_bad_method_enabled')->value('setting_value'));
        $this->assertSame('1', DB::table('system_settings')->where('setting_key', 'security.block_bad_payload_enabled')->value('setting_value'));
        $this->assertSame('42', DB::table('system_settings')->where('setting_key', 'security.payload_max_fields')->value('setting_value'));
        $this->assertSame('1200', DB::table('system_settings')->where('setting_key', 'security.payload_max_value_length')->value('setting_value'));
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

    public function test_platform_admin_can_save_mail_service_settings_with_encrypted_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.platform.settings.update'), [
                'system_name' => '站群后台',
                'system_version' => '2.1.0',
                'current_tab' => 'mail',
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
                'mail_enabled' => '1',
                'mail_driver' => 'smtp',
                'mail_host' => 'smtp.example.com',
                'mail_port' => '465',
                'mail_username' => 'mailer@example.com',
                'mail_password' => 'secret-pass',
                'mail_encryption' => 'ssl',
                'mail_from_address' => 'no-reply@example.com',
                'mail_from_name' => '站点通知',
                'mail_reply_to_address' => 'reply@example.com',
                'mail_timeout_seconds' => '12',
                'mail_rate_limit_enabled' => '1',
                'mail_rate_limit_window_seconds' => '90',
                'mail_rate_limit_global_max' => '30',
                'mail_rate_limit_site_max' => '12',
                'mail_rate_limit_scene_max' => '6',
                'mail_rate_limit_recipient_window_seconds' => '900',
                'mail_rate_limit_recipient_max' => '4',
            ])
            ->assertRedirect(route('admin.platform.settings.index', ['tab' => 'mail']))
            ->assertSessionHas('status', '系统设置已更新。');

        $this->assertSame('1', DB::table('system_settings')->where('setting_key', 'mail.enabled')->value('setting_value'));
        $this->assertSame('smtp', DB::table('system_settings')->where('setting_key', 'mail.driver')->value('setting_value'));
        $this->assertSame('smtp.example.com', DB::table('system_settings')->where('setting_key', 'mail.host')->value('setting_value'));
        $this->assertSame('465', DB::table('system_settings')->where('setting_key', 'mail.port')->value('setting_value'));
        $this->assertSame('mailer@example.com', DB::table('system_settings')->where('setting_key', 'mail.username')->value('setting_value'));
        $this->assertSame('no-reply@example.com', DB::table('system_settings')->where('setting_key', 'mail.from_address')->value('setting_value'));
        $this->assertSame('站点通知', DB::table('system_settings')->where('setting_key', 'mail.from_name')->value('setting_value'));
        $this->assertSame('reply@example.com', DB::table('system_settings')->where('setting_key', 'mail.reply_to_address')->value('setting_value'));
        $this->assertSame('12', DB::table('system_settings')->where('setting_key', 'mail.timeout_seconds')->value('setting_value'));
        $this->assertSame('1', DB::table('system_settings')->where('setting_key', 'mail.rate_limit_enabled')->value('setting_value'));
        $this->assertSame('90', DB::table('system_settings')->where('setting_key', 'mail.rate_limit_window_seconds')->value('setting_value'));
        $this->assertSame('30', DB::table('system_settings')->where('setting_key', 'mail.rate_limit_global_max')->value('setting_value'));
        $this->assertSame('12', DB::table('system_settings')->where('setting_key', 'mail.rate_limit_site_max')->value('setting_value'));
        $this->assertSame('6', DB::table('system_settings')->where('setting_key', 'mail.rate_limit_scene_max')->value('setting_value'));
        $this->assertSame('900', DB::table('system_settings')->where('setting_key', 'mail.rate_limit_recipient_window_seconds')->value('setting_value'));
        $this->assertSame('4', DB::table('system_settings')->where('setting_key', 'mail.rate_limit_recipient_max')->value('setting_value'));

        $encryptedPassword = (string) DB::table('system_settings')->where('setting_key', 'mail.password_encrypted')->value('setting_value');
        $this->assertNotSame('', $encryptedPassword);
        $this->assertStringStartsWith('enc:', $encryptedPassword);
        $this->assertStringNotContainsString('secret-pass', $encryptedPassword);
    }

    public function test_platform_admin_can_send_mail_service_test_message(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'mail.enabled'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'mail.driver'],
            ['setting_value' => 'log', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'mail.from_address'],
            ['setting_value' => 'no-reply@example.com', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'mail.from_name'],
            ['setting_value' => '站点通知', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        $this->actingAs($this->superAdmin())
            ->post(route('admin.platform.settings.mail-test'), [
                'mail_test_to' => 'receiver@example.com',
            ])
            ->assertRedirect(route('admin.platform.settings.index', ['tab' => 'mail']))
            ->assertSessionHas('status', '测试邮件已写入日志通道，当前未执行真实投递。');

        Mail::assertSent(PlatformTestMail::class, function ($mail): bool {
            return $mail->hasTo('receiver@example.com');
        });
    }

    public function test_platform_settings_mail_tab_displays_queue_worker_diagnostics(): void
    {
        $this->seed(DatabaseSeeder::class);

        PlatformMailSettings::recordQueueWorkerHeartbeat();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.platform.settings.index', ['tab' => 'mail']))
            ->assertOk()
            ->assertSee('队列执行状态')
            ->assertSee('当前队列连接：')
            ->assertSee('worker 状态：');
    }

    public function test_platform_dashboard_displays_system_status_board(): void
    {
        $this->seed(DatabaseSeeder::class);

        Cache::forget('system-checks:laravel:latest');

        $this->actingAs($this->superAdmin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('系统状态')
            ->assertSee('data-system-check-action-status', false)
            ->assertSee('加载中')
            ->assertDontSee('近期文章');
    }

    public function test_platform_dashboard_system_status_endpoint_returns_json_payload(): void
    {
        $this->seed(DatabaseSeeder::class);

        Http::fake([
            'https://repo.packagist.org/p2/laravel/framework.json' => Http::response([
                'packages' => [
                    'laravel/framework' => [
                        ['version' => 'v13.8.0'],
                    ],
                ],
            ]),
        ]);

        Cache::forget('system-checks:laravel:latest');
        Cache::store('database')->forget(SchedulerHealthCheck::HEARTBEAT_CACHE_KEY);

        $this->actingAs($this->superAdmin())
            ->getJson(route('admin.platform.dashboard.system-status'))
            ->assertOk()
            ->assertJsonPath('overall_status', 'error')
            ->assertJsonStructure([
                'checked_at',
                'overall_status',
                'items' => [
                    '*' => ['title', 'state', 'status_class', 'meta', 'detail', 'action_url'],
                ],
            ]);
    }

    public function test_platform_dashboard_combines_redis_and_frontend_cache_status_in_one_card(): void
    {
        $this->seed(DatabaseSeeder::class);

        config([
            'cms.frontend_page_cache.enabled' => true,
            'cache.default' => 'failover',
            'cache.stores.failover.stores' => ['redis', 'array'],
            'database.redis.cache.port' => 1,
        ]);

        Cache::forgetDriver('redis');
        Cache::forgetDriver('failover');

        Http::fake([
            'https://repo.packagist.org/p2/laravel/framework.json' => Http::response([
                'packages' => [
                    'laravel/framework' => [
                        ['version' => 'v13.8.0'],
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->superAdmin())
            ->getJson(route('admin.platform.dashboard.system-status'))
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Redis 应用缓存',
                'state' => '已降级',
                'status_class' => 'pending',
                'meta' => "Redis：关闭\n整页缓存：开启",
            ]);

        $redisItem = collect($response->json('items'))->firstWhere('title', 'Redis 应用缓存');
        $frontendItem = collect($response->json('items'))->firstWhere('title', '前台整页缓存');

        $this->assertNotNull($redisItem);
        $this->assertNull($frontendItem);
    }

    public function test_platform_admin_mail_service_test_message_is_rate_limited(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        foreach ([
            'mail.enabled' => '1',
            'mail.driver' => 'log',
            'mail.from_address' => 'no-reply@example.com',
            'mail.from_name' => '站点通知',
            'mail.rate_limit_enabled' => '1',
            'mail.rate_limit_window_seconds' => '60',
            'mail.rate_limit_global_max' => '1',
            'mail.rate_limit_site_max' => '1',
            'mail.rate_limit_scene_max' => '1',
            'mail.rate_limit_recipient_window_seconds' => '600',
            'mail.rate_limit_recipient_max' => '1',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        RateLimiter::clear('platform-mail:global');
        RateLimiter::clear('platform-mail:scene:platform_test');
        RateLimiter::clear('platform-mail:recipient:'.sha1('limited@example.com'));

        $this->actingAs($this->superAdmin())
            ->post(route('admin.platform.settings.mail-test'), [
                'mail_test_to' => 'limited@example.com',
            ])
            ->assertRedirect(route('admin.platform.settings.index', ['tab' => 'mail']))
            ->assertSessionHas('status', '测试邮件已写入日志通道，当前未执行真实投递。');

        $this->actingAs($this->superAdmin())
            ->post(route('admin.platform.settings.mail-test'), [
                'mail_test_to' => 'limited@example.com',
            ])
            ->assertRedirect(route('admin.platform.settings.index', ['tab' => 'mail']))
            ->assertSessionHasErrors(['mail_test_to']);

        Mail::assertSent(PlatformTestMail::class, 1);
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
        $this->assertSame('/atts/2026/04/replace-target.jpg', $attachment->url);
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
        $this->assertSame('/atts/2026/04/cache-version.jpg', parse_url((string) ($item['url'] ?? ''), PHP_URL_PATH));
        parse_str((string) parse_url((string) ($item['url'] ?? ''), PHP_URL_QUERY), $query);
        $this->assertSame(['site' => 'site', 'v' => (string) $updatedAt->timestamp], $query);
    }

    public function test_site_media_response_uses_public_cache_headers(): void
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
            ->assertOk();

        $cacheControl = (string) $response->headers->get('Cache-Control', '');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=2592000', $cacheControl);
        $this->assertFalse($response->headers->has('Pragma'));
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

    public function test_authenticated_user_visiting_login_is_redirected_to_admin_entry(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->superAdmin())
            ->get(route('login'))
            ->assertRedirect(route('admin.entry'));

        $operator = $this->createSiteOperator('login-return-site-operator', true, 'editor');

        $this->actingAs($operator)
            ->get(route('login'))
            ->assertRedirect(route('admin.entry'));
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

    public function test_multi_site_operator_security_page_shows_site_switcher(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('security-multi-site-operator', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $secondSiteId = $this->createAdditionalSite('security-demo-school-2', '第二安全示例学校');
        $siteAdminRoleId = (int) DB::table('site_roles')->where('code', 'site_admin')->value('id');

        DB::table('site_user_roles')->insert([
            'site_id' => $secondSiteId,
            'user_id' => $operator->id,
            'role_id' => $siteAdminRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('示例学校')
            ->assertSee('第二安全示例学校');
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
            ->assertDontSee('安全防护')
            ->assertSeeInOrder(['内容管理', '功能模块', '安护盾', '站点配置'])
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

        app(ContentAttachmentRelationSync::class)->syncForContent($siteId, $contentId);

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

    public function test_content_attachment_relation_sync_skips_unchanged_rows(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('attachment-unchanged-sync-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $attachmentId = $this->createSiteAttachment($siteId, $siteAdmin->id, 'unchanged-sync.jpg');
        $attachmentUrl = (string) DB::table('attachments')->where('id', $attachmentId)->value('url');
        $originalTimestamp = Carbon::parse('2026-05-20 09:00:00');

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'type' => 'article',
            'title' => '附件关系重复同步',
            'slug' => 'attachment-relation-repeat-sync',
            'cover_image' => $attachmentUrl,
            'content' => '',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $siteAdmin->id,
            'updated_by' => $siteAdmin->id,
            'created_at' => $originalTimestamp,
            'updated_at' => $originalTimestamp,
        ]);

        Carbon::setTestNow($originalTimestamp);

        try {
            app(ContentAttachmentRelationSync::class)->syncForContent($siteId, $contentId);

            Carbon::setTestNow(Carbon::parse('2026-05-28 12:00:00'));

            app(ContentAttachmentRelationSync::class)->syncForContent($siteId, $contentId);
        } finally {
            Carbon::setTestNow();
        }

        $relation = DB::table('attachment_relations')
            ->where('attachment_id', $attachmentId)
            ->where('relation_type', 'content')
            ->where('relation_id', $contentId)
            ->first(['updated_at']);

        $this->assertSame($originalTimestamp->format('Y-m-d H:i:s'), Carbon::parse($relation->updated_at)->format('Y-m-d H:i:s'));
        $this->assertSame(1, (int) DB::table('attachments')->where('id', $attachmentId)->value('usage_count'));
        $this->assertSame($originalTimestamp->format('Y-m-d H:i:s'), Carbon::parse(DB::table('attachments')->where('id', $attachmentId)->value('last_used_at'))->format('Y-m-d H:i:s'));
    }

    public function test_content_channel_sync_skips_unchanged_rows(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('content-channel-unchanged-sync-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $timestamp = Carbon::parse('2026-05-20 09:00:00');
        $channelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => null,
            'name' => '同步栏目',
            'slug' => 'sync-channel',
            'type' => 'list',
            'path' => '/sync-channel',
            'depth' => 0,
            'sort' => 1,
            'status' => 1,
            'is_nav' => 1,
            'list_template' => 'list',
            'detail_template' => 'detail',
            'link_url' => null,
            'link_target' => '_self',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '栏目关系重复同步',
            'slug' => 'content-channel-repeat-sync',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $siteAdmin->id,
            'updated_by' => $siteAdmin->id,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        DB::table('content_channels')->insert([
            'content_id' => $contentId,
            'channel_id' => $channelId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-05-28 12:00:00'));

        try {
            $controller = new ContentController;
            $method = new \ReflectionMethod($controller, 'syncContentChannels');
            $method->setAccessible(true);
            $method->invoke($controller, $contentId, [$channelId]);
        } finally {
            Carbon::setTestNow();
        }

        $relation = DB::table('content_channels')
            ->where('content_id', $contentId)
            ->where('channel_id', $channelId)
            ->first(['updated_at']);

        $this->assertSame($timestamp->format('Y-m-d H:i:s'), Carbon::parse($relation->updated_at)->format('Y-m-d H:i:s'));
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

        app(ContentAttachmentRelationSync::class)->syncForContent($siteId, $contentId);

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

        app(ContentAttachmentRelationSync::class)->syncForContent($siteId, $contentId);

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

        app(ContentAttachmentRelationSync::class)->syncForContent($siteId, $contentId);

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

    public function test_article_index_order_does_not_promote_recently_edited_content(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('article-index-order-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $channelId = $this->createSiteChannel($siteId, 'article-index-order-channel', '文章排序栏目', $operator->id);
        $now = now();

        DB::table('contents')->insert([
            [
                'site_id' => $siteId,
                'channel_id' => $channelId,
                'type' => 'article',
                'title' => '发布时间较新的文章',
                'slug' => 'article-index-newer-published',
                'summary' => 'newer',
                'content' => '<p>newer</p>',
                'status' => 'published',
                'audit_status' => 'approved',
                'sort' => 100,
                'created_by' => $operator->id,
                'updated_by' => $operator->id,
                'published_at' => $now->copy()->subDay(),
                'created_at' => $now->copy()->subDay(),
                'updated_at' => $now->copy()->subDay(),
            ],
            [
                'site_id' => $siteId,
                'channel_id' => $channelId,
                'type' => 'article',
                'title' => '刚刚编辑的旧文章',
                'slug' => 'article-index-recently-edited-old',
                'summary' => 'old',
                'content' => '<p>old</p>',
                'status' => 'published',
                'audit_status' => 'approved',
                'sort' => 100,
                'created_by' => $operator->id,
                'updated_by' => $operator->id,
                'published_at' => $now->copy()->subDays(30),
                'created_at' => $now->copy()->subDays(30),
                'updated_at' => $now,
            ],
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSeeInOrder([
                '发布时间较新的文章',
                '刚刚编辑的旧文章',
            ]);
    }

    public function test_article_index_channel_filter_includes_descendant_channels(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $operator = $this->createSiteOperator('article-tree-filter-admin', true, 'site_admin');

        $parentId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => null,
            'name' => '一级新闻栏目',
            'slug' => 'article-tree-filter-parent',
            'type' => 'list',
            'path' => '/article-tree-filter-parent',
            'depth' => 0,
            'sort' => 0,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $childId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => $parentId,
            'name' => '二级新闻栏目',
            'slug' => 'article-tree-filter-child',
            'type' => 'list',
            'path' => '/article-tree-filter-parent/article-tree-filter-child',
            'depth' => 1,
            'sort' => 0,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherParentId = $this->createSiteChannel($siteId, 'article-tree-filter-other-parent', '其他一级栏目', $operator->id);

        $visibleContentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $childId,
            'type' => 'article',
            'title' => '一级筛选应该显示的子栏目文章',
            'slug' => 'article-tree-filter-visible',
            'summary' => 'visible',
            'content' => '<p>visible</p>',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('content_channels')->insert([
            'content_id' => $visibleContentId,
            'channel_id' => $childId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('contents')->insert([
            'site_id' => $siteId,
            'channel_id' => $otherParentId,
            'type' => 'article',
            'title' => '一级筛选不应该显示的其他栏目文章',
            'slug' => 'article-tree-filter-hidden',
            'summary' => 'hidden',
            'content' => '<p>hidden</p>',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.articles.index', ['channel_id' => $parentId]))
            ->assertOk()
            ->assertSee('一级筛选应该显示的子栏目文章')
            ->assertDontSee('一级筛选不应该显示的其他栏目文章');
    }

    public function test_page_index_channel_filter_includes_descendant_page_channels_from_parent_group(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $operator = $this->createSiteOperator('page-tree-filter-admin', true, 'site_admin');

        $parentId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => null,
            'name' => '单页一级分组',
            'slug' => 'page-tree-filter-parent',
            'type' => 'list',
            'path' => '/page-tree-filter-parent',
            'depth' => 0,
            'sort' => 0,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $childPageChannelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => $parentId,
            'name' => '二级单页栏目',
            'slug' => 'page-tree-filter-child',
            'type' => 'page',
            'path' => '/page-tree-filter-parent/page-tree-filter-child',
            'depth' => 1,
            'sort' => 0,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherPageChannelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => null,
            'name' => '其他单页栏目',
            'slug' => 'page-tree-filter-other',
            'type' => 'page',
            'path' => '/page-tree-filter-other',
            'depth' => 0,
            'sort' => 0,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $visiblePageId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $childPageChannelId,
            'type' => 'page',
            'title' => '一级筛选应该显示的子栏目单页',
            'slug' => 'page-tree-filter-visible',
            'summary' => 'visible',
            'content' => '<p>visible</p>',
            'status' => 'published',
            'audit_status' => 'published',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('content_channels')->insert([
            'content_id' => $visiblePageId,
            'channel_id' => $childPageChannelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('contents')->insert([
            'site_id' => $siteId,
            'channel_id' => $otherPageChannelId,
            'type' => 'page',
            'title' => '一级筛选不应该显示的其他单页',
            'slug' => 'page-tree-filter-hidden',
            'summary' => 'hidden',
            'content' => '<p>hidden</p>',
            'status' => 'published',
            'audit_status' => 'published',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.pages.index', ['channel_id' => $parentId]))
            ->assertOk()
            ->assertSee('一级筛选应该显示的子栏目单页')
            ->assertDontSee('一级筛选不应该显示的其他单页');
    }

    public function test_article_review_channel_filter_includes_descendant_channels(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $operator = $this->createSiteOperator('review-tree-filter-admin', true, 'site_admin');

        $parentId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => null,
            'name' => '审核一级栏目',
            'slug' => 'review-tree-filter-parent',
            'type' => 'list',
            'path' => '/review-tree-filter-parent',
            'depth' => 0,
            'sort' => 0,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $childId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => $parentId,
            'name' => '审核二级栏目',
            'slug' => 'review-tree-filter-child',
            'type' => 'list',
            'path' => '/review-tree-filter-parent/review-tree-filter-child',
            'depth' => 1,
            'sort' => 0,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherParentId = $this->createSiteChannel($siteId, 'review-tree-filter-other-parent', '审核其他栏目', $operator->id);

        $visibleContentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $childId,
            'type' => 'article',
            'title' => '审核一级筛选应该显示的子栏目文章',
            'slug' => 'review-tree-filter-visible',
            'summary' => 'visible',
            'content' => '<p>visible</p>',
            'status' => 'pending',
            'audit_status' => 'pending',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('content_channels')->insert([
            'content_id' => $visibleContentId,
            'channel_id' => $childId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('contents')->insert([
            'site_id' => $siteId,
            'channel_id' => $otherParentId,
            'type' => 'article',
            'title' => '审核一级筛选不应该显示的其他栏目文章',
            'slug' => 'review-tree-filter-hidden',
            'summary' => 'hidden',
            'content' => '<p>hidden</p>',
            'status' => 'pending',
            'audit_status' => 'pending',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.article-reviews.index', ['channel_id' => $parentId]))
            ->assertOk()
            ->assertSee('审核一级筛选应该显示的子栏目文章')
            ->assertDontSee('审核一级筛选不应该显示的其他栏目文章');
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

    public function test_site_operator_cannot_delete_channel_used_as_secondary_content_channel(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('channel-secondary-reference-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $primaryChannelId = $this->createSiteChannel($siteId, 'channel-secondary-reference-primary', '主栏目', $operator->id);
        $secondaryChannelId = $this->createSiteChannel($siteId, 'channel-secondary-reference-secondary', '第二栏目', $operator->id);

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $primaryChannelId,
            'type' => 'article',
            'title' => '第二栏目引用文章',
            'slug' => 'channel-secondary-reference-article',
            'summary' => 'secondary channel reference',
            'content' => '<p>secondary channel reference</p>',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('content_channels')->insert([
            'content_id' => $contentId,
            'channel_id' => $secondaryChannelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.channels.destroy', $secondaryChannelId))
            ->assertRedirect(route('admin.channels.index'));

        $this->assertTrue(
            DB::table('channels')->where('id', $secondaryChannelId)->exists(),
            '被多栏目文章引用的栏目不应被删除。',
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
                'deleted_at' => null,
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
                'deleted_at' => null,
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
                'deleted_at' => null,
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

    public function test_site_dashboard_ignores_deleted_platform_notice(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('site-dashboard-notice-filter', true, 'site_admin');
        $noticeChannelId = (int) DB::table('channels')
            ->where('site_id', 1)
            ->where('slug', 'platform-notices')
            ->value('id');

        DB::table('contents')->insert([
            'site_id' => 1,
            'channel_id' => $noticeChannelId,
            'type' => 'article',
            'title' => '站点可见平台公告',
            'status' => 'published',
            'audit_status' => 'approved',
            'deleted_at' => null,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('contents')->insert([
            'site_id' => 1,
            'channel_id' => $noticeChannelId,
            'type' => 'article',
            'title' => '站点已回收平台公告',
            'status' => 'published',
            'audit_status' => 'approved',
            'deleted_at' => now(),
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => 1])
            ->get(route('admin.site-dashboard'))
            ->assertOk()
            ->assertSee('站点可见平台公告')
            ->assertDontSee('站点已回收平台公告');
    }

    public function test_theme_tags_frontend_content_queries_ignore_non_published_and_deleted_content(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = DB::table('sites')->where('site_key', 'site')->first();
        $this->assertNotNull($site);
        $settings = collect();
        $channels = DB::table('channels')
            ->where('site_id', $site->id)
            ->where('status', 1)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
        $tags = new ThemeTags($site, $settings, $channels);
        $identity = $this->createPlatformIdentity('frontend-visibility-tester');
        $channelId = $this->createSiteChannel((int) $site->id, 'frontend-visible-channel', '前台可见栏目', $identity->id);

        DB::table('contents')->insert([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '前台可见文章',
            'status' => 'published',
            'audit_status' => 'approved',
            'published_at' => now(),
            'created_by' => $identity->id,
            'updated_by' => $identity->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('contents')->insert([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '前台草稿文章',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $identity->id,
            'updated_by' => $identity->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('contents')->insert([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '前台待审核文章',
            'status' => 'pending',
            'audit_status' => 'pending',
            'created_by' => $identity->id,
            'updated_by' => $identity->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('contents')->insert([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '前台回收站文章',
            'status' => 'published',
            'audit_status' => 'approved',
            'deleted_at' => now(),
            'published_at' => now(),
            'created_by' => $identity->id,
            'updated_by' => $identity->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $items = $tags->contentList([
            'type' => 'article',
            'site_wide' => true,
            'status' => 'draft',
            'limit' => 10,
        ]);

        $titles = $items->pluck('title')->all();

        $this->assertContains('前台可见文章', $titles);
        $this->assertNotContains('前台草稿文章', $titles);
        $this->assertNotContains('前台待审核文章', $titles);
        $this->assertNotContains('前台回收站文章', $titles);
    }

    public function test_theme_children_can_hide_disabled_child_channels(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = DB::table('sites')->where('site_key', 'site')->first();
        $this->assertNotNull($site);

        $identity = $this->createPlatformIdentity('frontend-child-channel-tester');
        $parentId = $this->createSiteChannel((int) $site->id, 'frontend-child-parent', '前台父栏目', $identity->id);

        DB::table('channels')->insert([
            [
                'site_id' => $site->id,
                'parent_id' => $parentId,
                'name' => '显示子栏目',
                'slug' => 'frontend-visible-child',
                'type' => 'list',
                'path' => '/frontend-child-parent/frontend-visible-child',
                'depth' => 1,
                'sort' => 1,
                'status' => 1,
                'is_nav' => 0,
                'created_by' => $identity->id,
                'updated_by' => $identity->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $site->id,
                'parent_id' => $parentId,
                'name' => '停用子栏目',
                'slug' => 'frontend-hidden-child',
                'type' => 'list',
                'path' => '/frontend-child-parent/frontend-hidden-child',
                'depth' => 1,
                'sort' => 2,
                'status' => 0,
                'is_nav' => 1,
                'created_by' => $identity->id,
                'updated_by' => $identity->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $tags = new ThemeTags($site, collect(), collect());

        $children = $tags->children([
            'channel_id' => $parentId,
            'status' => 1,
            'limit' => 20,
        ]);

        $this->assertSame(['显示子栏目'], $children->pluck('name')->all());
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
            ->assertSee('内容 · 本站日志目标文章 #'.$localContentId)
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
            ->assertSee('站点 · '.$siteName.' #'.$siteId)
            ->assertSee('管理员 · '.$user->name.' #'.$user->id);
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
            ->assertSee('操作员 · '.$operator->name.' #'.$operator->id)
            ->assertDontSee('管理员 · '.$operator->name.' #'.$operator->id);
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
            ->assertSee('站点角色 · '.$roleName.' #'.$roleId)
            ->assertDontSee('role #'.$roleId, false);
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
            ->assertSee('资源 · 平台日志附件.png #'.$attachmentId);
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

    public function test_site_admin_can_disable_channel_from_edit_form(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('channel-status-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $channelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'name' => '栏目开关测试',
            'slug' => 'channel-status-check',
            'type' => 'list',
            'path' => '/channel-status-check',
            'depth' => 0,
            'sort' => 999,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.channels.update', $channelId), [
                'name' => '栏目开关测试',
                'slug' => 'channel-status-check',
                'type' => 'list',
                'parent_id' => '',
                'is_nav' => '1',
                'list_template' => '',
                'detail_template' => '',
            ])
            ->assertRedirect(route('admin.channels.index'))
            ->assertSessionHas('status', '栏目已更新。');

        $this->assertDatabaseHas('channels', [
            'id' => $channelId,
            'site_id' => $siteId,
            'status' => 0,
        ]);
    }

    public function test_site_admin_can_save_channel_with_imported_long_slug(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('channel-imported-long-slug-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $slug = 'legacy-school-pic-cat-5';
        $channelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'name' => '优秀教师',
            'slug' => $slug,
            'type' => 'list',
            'path' => '/'.$slug,
            'depth' => 0,
            'sort' => 999,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.channels.update', $channelId), [
                'name' => '优秀教师风采',
                'slug' => $slug,
                'type' => 'list',
                'parent_id' => '',
                'is_nav' => '1',
                'status' => '1',
                'list_template' => '',
                'detail_template' => '',
            ])
            ->assertRedirect(route('admin.channels.index'))
            ->assertSessionHas('status', '栏目已更新。');

        $this->assertDatabaseHas('channels', [
            'id' => $channelId,
            'name' => '优秀教师风采',
            'slug' => $slug,
        ]);
    }

    public function test_content_create_channel_options_hide_disabled_channels(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('channel-hidden-content-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('channels')->insert([
            [
                'site_id' => $siteId,
                'name' => '可选栏目A',
                'slug' => 'enabled-channel-a',
                'type' => 'list',
                'path' => '/enabled-channel-a',
                'depth' => 0,
                'sort' => 1001,
                'status' => 1,
                'is_nav' => 1,
                'created_by' => $operator->id,
                'updated_by' => $operator->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $siteId,
                'name' => '关闭栏目B',
                'slug' => 'disabled-channel-b',
                'type' => 'list',
                'path' => '/disabled-channel-b',
                'depth' => 0,
                'sort' => 1002,
                'status' => 0,
                'is_nav' => 1,
                'created_by' => $operator->id,
                'updated_by' => $operator->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.articles.create'))
            ->assertOk()
            ->assertSee('可选栏目A')
            ->assertDontSee('关闭栏目B');
    }

    public function test_article_channel_options_include_list_child_under_page_channel(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('page-parent-list-content-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $parentId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'name' => '学校概况',
            'slug' => 'page-parent-for-list',
            'type' => 'page',
            'path' => '/page-parent-for-list',
            'depth' => 0,
            'sort' => 1101,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('channels')->insert([
            'site_id' => $siteId,
            'parent_id' => $parentId,
            'name' => '学校新闻',
            'slug' => 'list-child-under-page',
            'type' => 'list',
            'path' => '/page-parent-for-list/list-child-under-page',
            'depth' => 1,
            'sort' => 1102,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.articles.create'))
            ->assertOk()
            ->assertSee('学校概况')
            ->assertSee('学校新闻');
    }

    public function test_theme_page_content_list_keeps_page_channel_when_it_has_article_child_channels(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = DB::table('sites')->where('site_key', 'site')->first();
        $this->assertNotNull($site);

        $identity = $this->createPlatformIdentity('page-channel-with-child-list-tester');
        $parentId = (int) DB::table('channels')->insertGetId([
            'site_id' => $site->id,
            'name' => '校情简介',
            'slug' => 'page-with-child-list',
            'type' => 'page',
            'path' => '/page-with-child-list',
            'depth' => 0,
            'sort' => 1201,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $identity->id,
            'updated_by' => $identity->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('channels')->insert([
            'site_id' => $site->id,
            'parent_id' => $parentId,
            'name' => '校情新闻',
            'slug' => 'article-child-under-page-for-template',
            'type' => 'list',
            'path' => '/page-with-child-list/article-child-under-page-for-template',
            'depth' => 1,
            'sort' => 1202,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $identity->id,
            'updated_by' => $identity->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $site->id,
            'channel_id' => $parentId,
            'type' => 'page',
            'title' => '校情简介单页',
            'content' => '<p>单页内容</p>',
            'status' => 'published',
            'audit_status' => 'approved',
            'sort' => 10,
            'published_at' => now(),
            'created_by' => $identity->id,
            'updated_by' => $identity->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('content_channels')->insert([
            'content_id' => $contentId,
            'channel_id' => $parentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $channels = DB::table('channels')
            ->where('site_id', $site->id)
            ->where('status', 1)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $items = (new ThemeTags($site, collect(), $channels))->contentList([
            'channel_id' => $parentId,
            'type' => 'page',
            'limit' => 50,
        ]);

        $this->assertSame(['校情简介单页'], $items->pluck('title')->all());
    }

    public function test_frontend_blocks_disabled_channel_and_its_article(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('channel-frontend-status-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $channelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'name' => '前台关闭栏目',
            'slug' => 'frontend-disabled-channel',
            'type' => 'list',
            'path' => '/frontend-disabled-channel',
            'depth' => 0,
            'sort' => 1003,
            'status' => 0,
            'is_nav' => 1,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $articleId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '关闭栏目文章',
            'slug' => 'disabled-channel-article',
            'status' => 'published',
            'audit_status' => 'approved',
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('content_channels')->insert([
            'content_id' => $articleId,
            'channel_id' => $channelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/cat/frontend-disabled-channel')
            ->assertNotFound();

        $this->get('/article/'.$articleId)
            ->assertNotFound();
    }

    public function test_page_create_rejects_disabled_channel_submission(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('disabled-page-channel-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $channelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'name' => '关闭单页栏目',
            'slug' => 'disabled-page-channel',
            'type' => 'page',
            'path' => '/disabled-page-channel',
            'depth' => 0,
            'sort' => 1004,
            'status' => 0,
            'is_nav' => 1,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.pages.store'), [
                'channel_ids' => [$channelId],
                'title' => '关闭栏目单页',
                'template_name' => '',
                'summary' => 'summary',
                'content' => '<p>content</p>',
                'status' => 'draft',
            ])
            ->assertForbidden();
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

    public function test_theme_editor_refresh_frontend_cache_button_clears_only_current_site_runtime_caches(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('template-editor-cache-refresher', true, 'template_editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $siteTemplate = DB::table('site_templates')
            ->where('site_id', $siteId)
            ->orderBy('id')
            ->first(['id', 'template_key']);

        $this->assertNotNull($siteTemplate);

        DB::table('site_domains')->updateOrInsert(
            ['site_id' => $siteId, 'domain' => 'www.refresh-cache.test'],
            [
                'is_primary' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $otherSiteId = $this->createAdditionalSite('refresh-cache-foreign-site', '缓存隔离测试站点');

        Cache::store('database')->forever('frontend-page-cache:site:'.$siteId.':version', 7);
        Cache::forever('theme-asset-base:local:site', 'web/site/theme');
        Cache::forever('attachment-base:local:site', 'web/site/media/attachments');
        Cache::forever('theme-asset-base:www.refresh-cache.test', 'web/site/theme');
        Cache::forever('attachment-base:www.refresh-cache.test', 'web/site/media/attachments');

        Cache::store('database')->forever('frontend-page-cache:site:'.$otherSiteId.':version', 11);
        Cache::forever('theme-asset-base:local:refresh-cache-foreign-site', 'web/refresh-cache-foreign-site/theme');
        Cache::forever('attachment-base:local:refresh-cache-foreign-site', 'web/refresh-cache-foreign-site/media/attachments');
        Cache::forever('theme-asset-base:foreign.refresh-cache.test', 'web/refresh-cache-foreign-site/theme');
        Cache::forever('attachment-base:foreign.refresh-cache.test', 'web/refresh-cache-foreign-site/media/attachments');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.themes.editor'))
            ->assertOk()
            ->assertSee('刷新前台缓存');

        $response = $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.themes.editor.refresh-frontend-cache'), [
                'site_template_id' => (int) $siteTemplate->id,
                'panel' => 'editor',
            ]);

        $response
            ->assertRedirect(route('admin.themes.editor', [
                'site_template_id' => (int) $siteTemplate->id,
                'panel' => 'editor',
            ]))
            ->assertSessionHas('status', '当前站点前台缓存已刷新。');

        $this->assertSame(8, (int) Cache::store('database')->get('frontend-page-cache:site:'.$siteId.':version'));
        $this->assertNull(Cache::get('theme-asset-base:local:site'));
        $this->assertNull(Cache::get('attachment-base:local:site'));
        $this->assertNull(Cache::get('theme-asset-base:www.refresh-cache.test'));
        $this->assertNull(Cache::get('attachment-base:www.refresh-cache.test'));

        $this->assertSame(11, (int) Cache::store('database')->get('frontend-page-cache:site:'.$otherSiteId.':version'));
        $this->assertSame('web/refresh-cache-foreign-site/theme', Cache::get('theme-asset-base:local:refresh-cache-foreign-site'));
        $this->assertSame('web/refresh-cache-foreign-site/media/attachments', Cache::get('attachment-base:local:refresh-cache-foreign-site'));
        $this->assertSame('web/refresh-cache-foreign-site/theme', Cache::get('theme-asset-base:foreign.refresh-cache.test'));
        $this->assertSame('web/refresh-cache-foreign-site/media/attachments', Cache::get('attachment-base:foreign.refresh-cache.test'));
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
            ->assertSee('当前请求已被安全防护拦截')
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
            'risk_level' => 'high',
            'action' => 'block',
            'request_path' => '/',
            'request_method' => 'GET',
        ]);

        $this->assertDatabaseHas('site_security_ip_reputations', [
            'site_id' => $siteId,
            'client_ip' => '127.0.0.1',
            'last_rule_code' => 'sql_injection',
            'hit_count' => 1,
            'high_risk_count' => 1,
            'status' => 'monitored',
        ]);
    }

    public function test_site_security_blocked_response_uses_hsts_when_request_is_forwarded_as_https(): void
    {
        $this->seed(DatabaseSeeder::class);
        putenv('SECURITY_HEADERS_HSTS_APP=true');
        $_ENV['SECURITY_HEADERS_HSTS_APP'] = 'true';
        $_SERVER['SECURITY_HEADERS_HSTS_APP'] = 'true';

        try {
            $this->withServerVariables([
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_PORT' => '443',
            ])->get('/?site=site&keyword='.urlencode('union select 1'))
                ->assertForbidden()
                ->assertSee('当前请求已被安全防护拦截')
                ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Content-Security-Policy');
        } finally {
            putenv('SECURITY_HEADERS_HSTS_APP');
            unset($_ENV['SECURITY_HEADERS_HSTS_APP'], $_SERVER['SECURITY_HEADERS_HSTS_APP']);
        }
    }

    public function test_site_security_samples_repeated_blocked_events_but_keeps_counts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->withHeader('User-Agent', 'SecurityTestAgent/1.0')
            ->get('/?site=site&keyword='.urlencode('union select 1'))
            ->assertForbidden();
        $this->withHeader('User-Agent', 'SecurityTestAgent/2.0')
            ->get('/?site=site&keyword='.urlencode('union select 1'))
            ->assertForbidden();

        $this->assertSame(2, (int) DB::table('site_security_daily_stats')
            ->where('site_id', $siteId)
            ->value('blocked_total'));
        $this->assertSame(2, (int) DB::table('site_security_daily_stats')
            ->where('site_id', $siteId)
            ->value('blocked_sql_injection'));
        $this->assertSame(1, DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->where('rule_code', 'sql_injection')
            ->count());
        $this->assertDatabaseHas('site_security_ip_reputations', [
            'site_id' => $siteId,
            'client_ip' => '127.0.0.1',
            'hit_count' => 2,
            'high_risk_count' => 2,
        ]);
    }

    public function test_site_security_records_bound_domain_event_on_matching_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $remoteSiteId = $this->createAdditionalSite('security-domain-site', '域名安全站点');

        DB::table('site_domains')->insert([
            'site_id' => $remoteSiteId,
            'domain' => 'security-domain.test',
            'is_primary' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('http://security-domain.test/?keyword='.urlencode('union select 1'))
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $remoteSiteId,
            'blocked_total' => 1,
            'blocked_sql_injection' => 1,
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $remoteSiteId,
            'rule_code' => 'sql_injection',
            'rule_name' => 'SQL 注入拦截',
        ]);

        $this->assertDatabaseMissing('site_security_events', [
            'site_id' => $mainSiteId,
            'rule_code' => 'sql_injection',
            'rule_name' => 'SQL 注入拦截',
        ]);
    }

    public function test_site_security_records_forwarded_host_event_on_matching_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $remoteSiteId = $this->createAdditionalSite('security-forwarded-site', '转发域名安全站点');

        DB::table('site_domains')->insert([
            'site_id' => $remoteSiteId,
            'domain' => 'security-forwarded.test',
            'is_primary' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => '127.0.0.1:8000',
            'HTTP_X_FORWARDED_HOST' => 'security-forwarded.test',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('/?keyword='.urlencode('union select 1'))
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $remoteSiteId,
            'rule_code' => 'sql_injection',
            'rule_name' => 'SQL 注入拦截',
        ]);

        $this->assertDatabaseMissing('site_security_events', [
            'site_id' => $mainSiteId,
            'rule_code' => 'sql_injection',
            'rule_name' => 'SQL 注入拦截',
        ]);
    }

    public function test_platform_admin_can_save_site_security_exceptions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

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
                'security_mode' => 'custom',
                'security_custom_rate_limit_max_requests' => '22',
                'security_custom_rate_limit_sensitive_max_requests' => '11',
                'security_custom_scan_probe_threshold' => '2',
                'security_ip_allowlist' => "192.168.1.10\n10.10.0.0/24",
                'security_ip_blocklist' => "203.0.113.7\n198.51.100.0/24",
                'security_path_allowlist' => "/trusted-webhook\n/callback/status",
                'security_rule_exceptions' => "bad_payload\nrate_limit",
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

        $this->assertSame('custom', DB::table('site_settings')->where('site_id', $siteId)->where('setting_key', 'security.mode')->value('setting_value'));
        $this->assertSame('22', DB::table('site_settings')->where('site_id', $siteId)->where('setting_key', 'security.custom_rate_limit_max_requests')->value('setting_value'));
        $this->assertSame('11', DB::table('site_settings')->where('site_id', $siteId)->where('setting_key', 'security.custom_rate_limit_sensitive_max_requests')->value('setting_value'));
        $this->assertSame('2', DB::table('site_settings')->where('site_id', $siteId)->where('setting_key', 'security.custom_scan_probe_threshold')->value('setting_value'));
        $this->assertSame("192.168.1.10\n10.10.0.0/24", DB::table('site_settings')->where('site_id', $siteId)->where('setting_key', 'security.ip_allowlist')->value('setting_value'));
        $this->assertSame("203.0.113.7\n198.51.100.0/24", DB::table('site_settings')->where('site_id', $siteId)->where('setting_key', 'security.ip_blocklist')->value('setting_value'));
        $this->assertSame("/trusted-webhook\n/callback/status", DB::table('site_settings')->where('site_id', $siteId)->where('setting_key', 'security.path_allowlist')->value('setting_value'));
        $this->assertSame("bad_payload\nrate_limit", DB::table('site_settings')->where('site_id', $siteId)->where('setting_key', 'security.rule_exceptions')->value('setting_value'));
    }

    public function test_platform_security_overview_aggregates_recent_site_security_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('platform-security-overview-site', '平台安护盾总览站点');

        DB::table('site_security_daily_stats')->insert([
            [
                'site_id' => $mainSiteId,
                'stat_date' => now('Asia/Shanghai')->toDateString(),
                'blocked_total' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $mainSiteId,
                'stat_date' => now('Asia/Shanghai')->subDays(2)->toDateString(),
                'blocked_total' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $otherSiteId,
                'stat_date' => now('Asia/Shanghai')->subDay()->toDateString(),
                'blocked_total' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $otherSiteId,
                'stat_date' => now('Asia/Shanghai')->subDays(10)->toDateString(),
                'blocked_total' => 99,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('site_security_ip_reputations')->insert([
            [
                'site_id' => $mainSiteId,
                'client_ip' => '8.8.8.8',
                'ip_hash' => hash('sha256', '8.8.8.8'),
                'hit_count' => 4,
                'high_risk_count' => 2,
                'last_rule_code' => 'probe_abuse',
                'last_request_path' => '/wp-admin',
                'status' => 'blocked',
                'blocked_until' => now()->addMinutes(10),
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $otherSiteId,
                'client_ip' => '9.9.9.9',
                'ip_hash' => hash('sha256', '9.9.9.9'),
                'hit_count' => 2,
                'high_risk_count' => 1,
                'last_rule_code' => 'sql_injection',
                'last_request_path' => '/search',
                'status' => 'monitored',
                'blocked_until' => null,
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('site_security_events')->insert([
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'probe_abuse',
                'rule_name' => '扫描试探超限',
                'request_path' => '/wp-admin',
                'request_method' => 'GET',
                'client_ip' => '8.8.8.8',
                'ip_hash' => hash('sha256', '8.8.8.8'),
                'risk_level' => 'critical',
                'action' => 'temporary_block',
                'created_at' => now()->subMinutes(5),
            ],
            [
                'site_id' => $otherSiteId,
                'rule_code' => 'sql_injection',
                'rule_name' => 'SQL 注入',
                'request_path' => '/search',
                'request_method' => 'GET',
                'client_ip' => '9.9.9.9',
                'ip_hash' => hash('sha256', '9.9.9.9'),
                'risk_level' => 'high',
                'action' => 'block',
                'created_at' => now()->subMinutes(2),
            ],
            [
                'site_id' => $otherSiteId,
                'rule_code' => 'rate_limit',
                'rule_name' => '频繁刷新',
                'request_path' => '/list',
                'request_method' => 'GET',
                'client_ip' => '9.9.9.9',
                'ip_hash' => hash('sha256', '9.9.9.9'),
                'risk_level' => 'medium',
                'action' => 'rate_limited',
                'created_at' => now()->subDays(10),
            ],
        ]);

        DB::table('site_settings')->insert([
            'site_id' => $otherSiteId,
            'setting_key' => 'security.mode',
            'setting_value' => 'strict',
            'autoload' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.platform.security.index'))
            ->assertOk()
            ->assertSee('安护盾总览')
            ->assertSee('aria-controls="platform-security-panel-settings"', false)
            ->assertSee('id="platform-security-panel-sites"', false)
            ->assertSee('data-platform-security-tab-panel="sites" hidden', false)
            ->assertSee('18')
            ->assertSee('2')
            ->assertSee('扫描试探超限')
            ->assertSee('SQL 注入')
            ->assertSee('8.8.8.8')
            ->assertSee('9.9.9.9')
            ->assertSee(str_replace('&', '&amp;', route('admin.platform.security.ip-detail', ['site_id' => $otherSiteId, 'client_ip' => '9.9.9.9'])), false)
            ->assertSee('严格模式')
            ->assertDontSee('>99<', false);
    }

    public function test_platform_security_overview_can_open_site_ip_detail(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $ip = '8.8.8.8';
        $ipHash = hash('sha256', $ip);

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $siteId,
            'client_ip' => $ip,
            'ip_hash' => $ipHash,
            'hit_count' => 3,
            'high_risk_count' => 2,
            'last_rule_code' => 'probe_abuse',
            'last_request_path' => '/wp-admin',
            'status' => 'blocked',
            'blocked_until' => now()->addMinutes(10),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_security_events')->insert([
            'site_id' => $siteId,
            'rule_code' => 'probe_abuse',
            'rule_name' => '扫描试探超限',
            'request_path' => '/wp-admin',
            'request_method' => 'GET',
            'client_ip' => $ip,
            'ip_hash' => $ipHash,
            'risk_level' => 'critical',
            'action' => 'temporary_block',
            'user_agent' => 'PlatformSecurityAgent/1.0',
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.platform.security.ip-detail', ['site_id' => $siteId, 'client_ip' => $ip]))
            ->assertOk()
            ->assertSee('平台安护盾 IP 详情')
            ->assertSee($ip)
            ->assertSee('临时封禁')
            ->assertSee('PlatformSecurityAgent/1.0');
    }

    public function test_platform_security_overview_can_update_site_ip_policy(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->superAdmin();
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $ip = '8.8.4.4';

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $siteId,
            'client_ip' => $ip,
            'ip_hash' => hash('sha256', $ip),
            'hit_count' => 2,
            'high_risk_count' => 1,
            'last_rule_code' => 'bad_path',
            'last_request_path' => '/.env',
            'status' => 'monitored',
            'blocked_until' => null,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['current_site_id' => 1])
            ->post(route('admin.platform.security.ip-policy.store'), [
                'site_id' => $siteId,
                'client_ip' => $ip,
                'action' => 'block',
            ])
            ->assertRedirect(route('admin.platform.security.ip-detail', ['site_id' => $siteId, 'client_ip' => $ip]))
            ->assertSessionHas('status', '已加入站点 IP 黑名单。');

        $this->assertSame($ip, DB::table('site_settings')->where('site_id', $siteId)->where('setting_key', 'security.ip_blocklist')->value('setting_value'));
        $this->assertDatabaseHas('operation_logs', [
            'scope' => 'platform',
            'module' => 'security',
            'action' => 'security_block_ip',
            'user_id' => $user->id,
            'target_type' => 'site_security_ip',
        ]);
    }

    public function test_site_security_site_ip_allowlist_only_bypasses_matching_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $remoteSiteId = $this->createAdditionalSite('security-ip-allow-remote-site', 'IP 白名单远程站点');

        DB::table('site_domains')->insert([
            'site_id' => $remoteSiteId,
            'domain' => 'security-ip-allow.test',
            'is_primary' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $mainSiteId, 'setting_key' => 'security.ip_allowlist'],
            ['setting_value' => '127.0.0.1', 'autoload' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->get('/?site=site&keyword='.urlencode('union select 1'))->assertOk();
        $this->get('http://security-ip-allow.test/?keyword='.urlencode('union select 1'))->assertForbidden()->assertSee('当前请求已被安全防护拦截');

        $this->assertDatabaseMissing('site_security_events', [
            'site_id' => $mainSiteId,
            'rule_code' => 'sql_injection',
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $remoteSiteId,
            'rule_code' => 'sql_injection',
        ]);
    }

    public function test_site_security_site_ip_blocklist_only_blocks_matching_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $remoteSiteId = $this->createAdditionalSite('security-ip-block-remote-site', 'IP 黑名单远程站点');

        DB::table('site_domains')->insert([
            'site_id' => $remoteSiteId,
            'domain' => 'security-ip-block.test',
            'is_primary' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $mainSiteId, 'setting_key' => 'security.ip_blocklist'],
            ['setting_value' => '127.0.0.1', 'autoload' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->get('/?site=site')->assertForbidden()->assertSee('当前请求已被安全防护拦截');
        $this->assertNotSame(403, $this->get('http://security-ip-block.test/')->getStatusCode());

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $mainSiteId,
            'rule_code' => 'ip_blocklist',
            'rule_name' => '站点黑名单 IP 拦截',
        ]);

        $this->assertDatabaseMissing('site_security_events', [
            'site_id' => $remoteSiteId,
            'rule_code' => 'ip_blocklist',
        ]);
    }

    public function test_site_security_observe_mode_records_without_blocking(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'security.mode'],
            ['setting_value' => 'observe', 'autoload' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        $this->get('/?site=site&keyword='.urlencode('union select 1'))
            ->assertOk();

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'sql_injection',
            'rule_name' => 'SQL 注入拦截',
            'action' => 'record',
        ]);
    }

    public function test_site_security_observe_mode_does_not_create_runtime_or_ip_blocks(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'security.mode'],
            ['setting_value' => 'observe', 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
        );

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'security.rate_limit_block_seconds'],
            ['setting_value' => '60', 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
        );

        RateLimiter::clear($this->siteSecurityProbeBlockKey());

        $this->get('/?site=site&keyword='.urlencode('union select 1'))->assertOk();
        $this->get('/?site=site&keyword='.urlencode('sleep(1)'))->assertOk();
        $this->get('/?site=site&keyword='.urlencode('information_schema'))->assertOk();
        $this->get('/?site=site')->assertOk();

        $this->assertFalse(RateLimiter::tooManyAttempts($this->siteSecurityProbeBlockKey(), 1));
        $this->assertDatabaseHas('site_security_ip_reputations', [
            'site_id' => $siteId,
            'client_ip' => '127.0.0.1',
            'status' => 'monitored',
            'blocked_until' => null,
        ]);
    }

    public function test_site_security_event_records_request_context_safely(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->withHeaders([
            'User-Agent' => 'sqlmap/1.7',
            'Referer' => 'https://example.test/source',
        ])->get('/?site=site&keyword='.urlencode('union select 1').'&token=secret-value')
            ->assertForbidden();

        $event = DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->where('rule_code', 'bad_client')
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('sqlmap/1.7', $event->user_agent);
        $this->assertSame('https://example.test/source', $event->referer);
        $this->assertStringContainsString('"keyword":"union select 1"', (string) $event->request_query);
        $this->assertStringContainsString('"token":"[filtered]"', (string) $event->request_query);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $event->fingerprint);
    }

    public function test_site_security_blocks_xss_and_records_stats(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->get('/?site=site&keyword='.urlencode('<script>alert(1)</script>'))
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $siteId,
            'blocked_total' => 1,
            'blocked_xss' => 1,
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'xss',
            'rule_name' => 'XSS 攻击拦截',
            'request_path' => '/',
            'request_method' => 'GET',
        ]);
    }

    public function test_site_security_does_not_block_benign_sql_related_text(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/?site=site&keyword='.urlencode('union selection of student clubs'))
            ->assertOk();
    }

    public function test_site_security_does_not_block_benign_frontend_text(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/?site=site&keyword='.urlencode('javascript framework overview and svg icons'))
            ->assertOk();
    }

    public function test_site_security_ip_allowlist_skips_rule_checks(): void
    {
        $this->seed(DatabaseSeeder::class);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'security.ip_allowlist'],
            ['setting_value' => '127.0.0.1', 'autoload' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->get('/?site=site&keyword='.urlencode('union select 1'))->assertOk();

        $this->assertDatabaseMissing('site_security_events', [
            'rule_code' => 'sql_injection',
            'client_ip' => '127.0.0.1',
        ]);
    }

    public function test_site_security_ip_blocklist_blocks_before_rule_checks(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'security.ip_blocklist'],
            ['setting_value' => '127.0.0.1', 'autoload' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->get('/?site=site')->assertForbidden()->assertSee('当前请求已被安全防护拦截');

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $siteId,
            'blocked_total' => 1,
            'blocked_ip_blocklist' => 1,
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'ip_blocklist',
            'rule_name' => '黑名单 IP 拦截',
            'risk_level' => 'critical',
            'action' => 'temporary_block',
            'client_ip' => '127.0.0.1',
        ]);
    }

    public function test_site_security_blocks_script_scanner_user_agent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->withHeader('User-Agent', 'sqlmap/1.7')
            ->get('/?site=site')
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $siteId,
            'blocked_total' => 1,
            'blocked_bad_client' => 1,
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'bad_client',
            'rule_name' => '脚本扫描器拦截',
            'risk_level' => 'high',
            'action' => 'block',
            'client_ip' => '127.0.0.1',
        ]);
    }

    public function test_site_security_blocks_dangerous_http_method(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->call('TRACE', '/?site=site')
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $siteId,
            'blocked_total' => 1,
            'blocked_bad_method' => 1,
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'bad_method',
            'rule_name' => '异常请求方法拦截',
            'risk_level' => 'medium',
            'action' => 'block',
            'client_ip' => '127.0.0.1',
        ]);
    }

    public function test_site_security_blocks_too_many_request_parameters(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $query = ['site' => 'site'];

        foreach (range(1, 85) as $index) {
            $query['p'.$index] = 'x';
        }

        $this->get('/?'.http_build_query($query))
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $siteId,
            'blocked_total' => 1,
            'blocked_bad_payload' => 1,
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'bad_payload',
            'rule_name' => '异常请求参数拦截',
            'risk_level' => 'high',
            'action' => 'block',
            'client_ip' => '127.0.0.1',
        ]);
    }

    public function test_site_security_blocks_oversized_request_parameter(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/?site=site&payload='.str_repeat('a', 2001))
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');
    }

    public function test_site_security_rule_exception_only_skips_matching_site_rule(): void
    {
        $this->seed(DatabaseSeeder::class);

        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $remoteSiteId = $this->createAdditionalSite('security-exception-remote-site', '例外远程站点');

        DB::table('site_domains')->insert([
            'site_id' => $remoteSiteId,
            'domain' => 'security-exception.test',
            'is_primary' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $mainSiteId, 'setting_key' => 'security.rule_exceptions'],
            ['setting_value' => 'bad_payload', 'autoload' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        $query = ['site' => 'site'];
        foreach (range(1, 85) as $index) {
            $query['p'.$index] = 'x';
        }

        $this->get('/?'.http_build_query($query))->assertOk();

        $remoteQuery = [];
        foreach (range(1, 85) as $index) {
            $remoteQuery['p'.$index] = 'x';
        }

        $this->get('http://security-exception.test/?'.http_build_query($remoteQuery))
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');
    }

    public function test_site_security_blocks_path_traversal_and_records_stats(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->get('/?site=site&file='.urlencode('../.env'))
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $siteId,
            'blocked_total' => 1,
            'blocked_path_traversal' => 1,
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'path_traversal',
            'rule_name' => '路径穿越拦截',
            'request_path' => '/',
            'request_method' => 'GET',
        ]);
    }

    public function test_site_security_blocks_bad_path_and_records_stats(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        Route::middleware('web')->get('/wp-admin', fn () => response('ok'));

        $this->get('/wp-admin?site=site')
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截')
            ->assertSee('当前请求已被安全防护拦截');

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $siteId,
            'blocked_total' => 1,
            'blocked_bad_path' => 1,
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'bad_path',
            'rule_name' => '恶意扫描路径',
            'request_path' => '/wp-admin',
            'request_method' => 'GET',
        ]);
    }

    public function test_site_security_path_allowlist_only_bypasses_matching_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $remoteSiteId = $this->createAdditionalSite('security-allowlist-remote-site', '白名单远程站点');

        DB::table('site_domains')->insert([
            'site_id' => $remoteSiteId,
            'domain' => 'security-allowlist.test',
            'is_primary' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $mainSiteId, 'setting_key' => 'security.path_allowlist'],
            ['setting_value' => '/wp-admin', 'autoload' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        Route::middleware('web')->get('/wp-admin', fn () => response('ok'));

        $this->assertNotSame(403, $this->get('/wp-admin?site=site')->getStatusCode());
        $this->get('http://security-allowlist.test/wp-admin')->assertForbidden()->assertSee('当前请求已被安全防护拦截');
    }

    public function test_site_security_blocks_common_compliance_scan_paths(): void
    {
        $this->seed(DatabaseSeeder::class);

        Route::middleware('web')->get('/.git/config', fn () => response('ok'));
        Route::middleware('web')->get('/.DS_Store', fn () => response('ok'));
        Route::middleware('web')->get('/.user.ini', fn () => response('ok'));
        Route::middleware('web')->get('/composer.json', fn () => response('ok'));
        Route::middleware('web')->get('/database.sql', fn () => response('ok'));
        Route::middleware('web')->get('/swagger-ui/index.html', fn () => response('ok'));
        Route::middleware('web')->get('/actuator/health', fn () => response('ok'));
        Route::middleware('web')->get('/docs/swagger-ui-note', fn () => response('ok'));

        $this->get('/docs/swagger-ui-note?site=site')
            ->assertOk();

        $this->get('/.git/config?site=site')
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->get('/.DS_Store?site=site')
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->get('/.user.ini?site=site')
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->get('/composer.json?site=site')
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->get('/database.sql?site=site')
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->get('/swagger-ui/index.html?site=site')
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->get('/actuator/health?site=site')
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');
    }

    public function test_site_security_blocks_bad_upload_and_records_stats(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        Route::middleware('web')->post('/demo-security/upload', fn () => response('ok'));

        $this->post('/demo-security/upload?site=site', [
            'file' => UploadedFile::fake()->create('shell.php', 1, 'application/x-httpd-php'),
        ])
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $siteId,
            'blocked_total' => 1,
            'blocked_bad_upload' => 1,
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'bad_upload',
            'rule_name' => '可疑上传拦截',
            'request_path' => '/demo-security/upload',
            'request_method' => 'POST',
        ]);
    }

    public function test_site_security_blocks_bad_upload_with_disguised_double_extension(): void
    {
        $this->seed(DatabaseSeeder::class);

        Route::middleware('web')->post('/demo-security/upload', fn () => response('ok'));

        $this->post('/demo-security/upload?site=site', [
            'file' => UploadedFile::fake()->create('shell.php.jpg', 1, 'image/jpeg'),
        ])
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');
    }

    public function test_site_security_blocks_bad_upload_with_dangerous_mime_even_when_extension_looks_safe(): void
    {
        $this->seed(DatabaseSeeder::class);

        Route::middleware('web')->post('/demo-security/upload', fn () => response('ok'));

        $this->post('/demo-security/upload?site=site', [
            'file' => UploadedFile::fake()->create('manual.jpg', 1, 'application/x-httpd-php'),
        ])
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');
    }

    public function test_site_security_escalates_repeated_probe_hits_into_temporary_block(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        foreach ([
            'security.scan_probe_enabled' => '1',
            'security.scan_probe_window_seconds' => '300',
            'security.scan_probe_threshold' => '3',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        RateLimiter::clear($this->siteSecurityProbeBlockKey());

        Route::middleware('web')->get('/wp-admin', fn () => response('ok'));

        $this->get('/wp-admin?site=site')->assertForbidden()->assertSee('当前请求已被安全防护拦截');
        $this->get('/wp-admin?site=site')->assertForbidden()->assertSee('当前请求已被安全防护拦截');
        $this->get('/wp-admin?site=site')->assertForbidden()->assertSee('当前请求已被安全防护拦截');

        $probeEventCountBeforeBlockedRetry = DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->where('rule_code', 'probe_abuse')
            ->count();

        $this->get('/?site=site')->assertForbidden()->assertSee('当前请求已被安全防护拦截');

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $siteId,
            'blocked_probe_abuse' => 1,
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'probe_abuse',
            'rule_name' => '扫描试探超限',
        ]);

        $this->assertSame(
            $probeEventCountBeforeBlockedRetry,
            DB::table('site_security_events')
                ->where('site_id', $siteId)
                ->where('rule_code', 'probe_abuse')
                ->count()
        );

        $this->travel(61)->seconds();

        $this->get('/?site=site')->assertOk();
    }

    public function test_site_security_auto_blocks_repeated_malicious_hits_for_configured_seconds(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        foreach ([
            'security.scan_probe_enabled' => '1',
            'security.scan_probe_window_seconds' => '300',
            'security.scan_probe_threshold' => '99',
            'security.rate_limit_block_seconds' => '60',
            'security.malicious_auto_block_enabled' => '1',
            'security.malicious_auto_block_window_seconds' => '3600',
            'security.malicious_auto_block_threshold' => '3',
            'security.malicious_auto_block_seconds' => '86400',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        foreach (['union select 1', 'union select 2', 'union select 3'] as $keyword) {
            $this->get('/?site=site&keyword='.urlencode($keyword))->assertForbidden()->assertSee('当前请求已被安全防护拦截');
        }

        $this->assertDatabaseHas('site_security_ip_reputations', [
            'site_id' => $siteId,
            'client_ip' => '127.0.0.1',
            'status' => 'blocked',
        ]);

        $blockedUntil = (string) DB::table('site_security_ip_reputations')
            ->where('site_id', $siteId)
            ->where('client_ip', '127.0.0.1')
            ->value('blocked_until');

        $this->assertGreaterThan(now()->addHours(23)->getTimestamp(), strtotime($blockedUntil));

        $this->travel(61)->seconds();

        $eventCountBeforeRuntimeBlock = DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->count();

        $this->get('/?site=site')->assertForbidden()->assertSee('当前请求已被安全防护拦截');

        $this->assertSame(
            $eventCountBeforeRuntimeBlock,
            DB::table('site_security_events')
                ->where('site_id', $siteId)
                ->count()
        );

        app(SiteSecurity::class)->clearRuntimeBlocksForIp($siteId, '127.0.0.1');
    }

    public function test_site_security_custom_mode_uses_site_probe_threshold(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        foreach ([
            ['setting_key' => 'security.mode', 'setting_value' => 'custom'],
            ['setting_key' => 'security.custom_scan_probe_threshold', 'setting_value' => '2'],
        ] as $setting) {
            DB::table('site_settings')->updateOrInsert(
                ['site_id' => $siteId, 'setting_key' => $setting['setting_key']],
                ['setting_value' => $setting['setting_value'], 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        foreach ([
            'security.scan_probe_enabled' => '1',
            'security.scan_probe_window_seconds' => '300',
            'security.scan_probe_threshold' => '5',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        Route::middleware('web')->get('/wp-admin', fn () => response('ok'));

        $this->get('/wp-admin?site=site')->assertForbidden()->assertSee('当前请求已被安全防护拦截');
        $this->get('/wp-admin?site=site')->assertForbidden()->assertSee('当前请求已被安全防护拦截');
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
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $this->get('/?site=site')->assertOk()->assertCookie(SiteSecurity::DEVICE_COOKIE_NAME);
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-one')->get('/?site=site')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-one')->get('/?site=site')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-one')->get('/?site=site')->assertForbidden();
        $this->assertTrue(RateLimiter::tooManyAttempts($this->siteSecurityDeviceRateLimitBlockKey('device-one'), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts($this->siteSecurityRateLimitBlockKey(), 1));

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

    public function test_site_security_frequent_refresh_is_scoped_by_anonymous_device(): void
    {
        $this->seed(DatabaseSeeder::class);

        $now = now();

        foreach ([
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '2',
            'security.rate_limit_sensitive_max_requests' => '1',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-a')->get('/?site=site')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-a')->get('/?site=site')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-a')->get('/?site=site')->assertForbidden();

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-b')->get('/?site=site')->assertOk();

        $this->assertTrue(RateLimiter::tooManyAttempts($this->siteSecurityDeviceRateLimitBlockKey('device-a'), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts($this->siteSecurityDeviceRateLimitBlockKey('device-b'), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts($this->siteSecurityRateLimitBlockKey(), 1));
    }

    public function test_site_security_ip_fallback_still_blocks_shared_network_spikes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        foreach ([
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '2',
            'security.rate_limit_sensitive_max_requests' => '1',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $ipFallbackKey = 'site-security-rate:'.$siteId.':site:'.sha1('127.0.0.1');

        for ($i = 0; $i < 180; $i++) {
            RateLimiter::hit($ipFallbackKey, 10);
        }

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-fallback')->get('/?site=site')
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->assertTrue(RateLimiter::tooManyAttempts($this->siteSecurityRateLimitBlockKey(), 1));
    }

    public function test_site_security_ip_fallback_does_not_block_normal_shared_network_until_high_threshold(): void
    {
        $this->seed(DatabaseSeeder::class);

        $now = now();

        foreach ([
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '2',
            'security.rate_limit_sensitive_max_requests' => '1',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        for ($i = 1; $i <= 179; $i++) {
            $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'shared-device-'.$i)
                ->get('/?site=site')
                ->assertOk();
        }

        $this->assertFalse(RateLimiter::tooManyAttempts($this->siteSecurityRateLimitBlockKey(), 1));

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'shared-device-180')
            ->get('/?site=site')
            ->assertOk();

        $this->assertFalse(RateLimiter::tooManyAttempts($this->siteSecurityRateLimitBlockKey(), 1));

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'shared-device-181')
            ->get('/?site=site')
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->assertTrue(RateLimiter::tooManyAttempts($this->siteSecurityRateLimitBlockKey(), 1));
    }

    public function test_site_security_guestbook_page_refresh_uses_normal_site_wide_threshold(): void
    {
        $this->seed(DatabaseSeeder::class);

        $now = now();

        foreach ([
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '100',
            'security.rate_limit_sensitive_max_requests' => '10',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        RateLimiter::clear($this->siteSecurityRateLimitBlockKey());
        RateLimiter::clear($this->siteSecuritySiteWideRateKey());
        RateLimiter::clear($this->siteSecurityRateKeyForPath('/guestbook/create'));
        RateLimiter::clear($this->siteSecurityRateKeyForPath('/guestbook/captcha'));

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $siteSecurity = new SiteSecurity(new SystemSettings);
        $method = new \ReflectionMethod($siteSecurity, 'matchRateLimit');
        $method->setAccessible(true);
        $makeRequest = function (string $path, string $routeName): Request {
            $request = Request::create($path, 'GET', ['site' => 'site'], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
            $route = new \Illuminate\Routing\Route(['GET', 'HEAD'], ltrim($path, '/'), ['as' => $routeName, 'uses' => fn () => null]);
            $request->setRouteResolver(fn () => $route);

            return $request;
        };

        for ($i = 0; $i < 7; $i++) {
            $this->assertNull($method->invoke($siteSecurity, $makeRequest('/guestbook/create', 'site.guestbook.create'), $siteId));
            $this->assertNull($method->invoke($siteSecurity, $makeRequest('/guestbook/captcha', 'site.guestbook.captcha'), $siteId));
        }
    }

    public function test_site_security_media_route_does_not_count_as_frontend_page_refresh(): void
    {
        $this->seed(DatabaseSeeder::class);

        $now = now();

        foreach ([
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '100',
            'security.rate_limit_sensitive_max_requests' => '10',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        RateLimiter::clear($this->siteSecuritySiteWideRateKey());
        RateLimiter::clear($this->siteSecurityMediaWideRateKey());
        RateLimiter::clear($this->siteSecurityRateKeyForPath('/site-media/site/attachments/demo.jpg'));

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $siteSecurity = new SiteSecurity(new SystemSettings);
        $method = new \ReflectionMethod($siteSecurity, 'matchRateLimit');
        $method->setAccessible(true);
        $request = Request::create('/site-media/site/attachments/demo.jpg', 'GET', ['site' => 'site'], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $route = new \Illuminate\Routing\Route(['GET', 'HEAD'], 'site-media/{siteKey}/{path}', ['as' => 'site.media', 'uses' => fn () => null]);
        $request->setRouteResolver(fn () => $route);

        $this->assertNull($method->invoke($siteSecurity, $request, $siteId));
        $this->assertSame(0, RateLimiter::attempts($this->siteSecuritySiteWideRateKey()));
        $this->assertSame(1, RateLimiter::attempts($this->siteSecurityMediaWideRateKey()));
    }

    public function test_site_security_strict_mode_tightens_rate_limit_threshold(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => 'security.mode'],
            ['setting_value' => 'strict', 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
        );

        foreach ([
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '3',
            'security.rate_limit_sensitive_max_requests' => '3',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-strict')->get('/?site=site')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-strict')->get('/?site=site')->assertForbidden();

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'rate_limit',
            'rule_name' => '频繁刷新拦截',
        ]);
    }

    public function test_site_security_custom_mode_uses_site_rate_limit_thresholds(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        foreach ([
            ['setting_key' => 'security.mode', 'setting_value' => 'custom'],
            ['setting_key' => 'security.custom_rate_limit_max_requests', 'setting_value' => '1'],
            ['setting_key' => 'security.custom_rate_limit_sensitive_max_requests', 'setting_value' => '1'],
        ] as $setting) {
            DB::table('site_settings')->updateOrInsert(
                ['site_id' => $siteId, 'setting_key' => $setting['setting_key']],
                ['setting_value' => $setting['setting_value'], 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        foreach ([
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '5',
            'security.rate_limit_sensitive_max_requests' => '5',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-custom')->get('/?site=site')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-custom')->get('/?site=site')->assertForbidden();
    }

    public function test_site_security_rate_limit_block_persists_for_configured_seconds(): void
    {
        $this->seed(DatabaseSeeder::class);

        $now = now();

        foreach ([
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '2',
            'security.rate_limit_sensitive_max_requests' => '1',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        RateLimiter::clear($this->siteSecurityRateLimitBlockKey());
        RateLimiter::clear($this->siteSecuritySiteWideRateKey());
        RateLimiter::clear($this->siteSecurityRateKeyForPath('/'));
        RateLimiter::clear($this->siteSecurityDeviceRateLimitBlockKey('device-persist'));
        RateLimiter::clear($this->siteSecurityDeviceRateKey('device-persist', 'site'));
        RateLimiter::clear($this->siteSecurityDeviceRateKey('device-persist', 'path', '/'));

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-persist')->get('/?site=site')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-persist')->get('/?site=site')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-persist')->get('/?site=site')->assertForbidden();

        RateLimiter::clear($this->siteSecuritySiteWideRateKey());
        RateLimiter::clear($this->siteSecurityRateKeyForPath('/'));
        RateLimiter::clear($this->siteSecurityDeviceRateKey('device-persist', 'site'));
        RateLimiter::clear($this->siteSecurityDeviceRateKey('device-persist', 'path', '/'));

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-persist')->get('/?site=site')->assertForbidden();

        $this->travel(61)->seconds();

        RateLimiter::clear($this->siteSecuritySiteWideRateKey());
        RateLimiter::clear($this->siteSecurityRateKeyForPath('/'));
        RateLimiter::clear($this->siteSecurityDeviceRateKey('device-persist', 'site'));
        RateLimiter::clear($this->siteSecurityDeviceRateKey('device-persist', 'path', '/'));

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-persist')->get('/?site=site')->assertOk();
    }

    public function test_site_security_rate_limit_block_does_not_get_recorded_as_probe_abuse(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        foreach ([
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '2',
            'security.rate_limit_sensitive_max_requests' => '1',
            'security.rate_limit_block_seconds' => '60',
            'security.scan_probe_enabled' => '1',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        RateLimiter::clear($this->siteSecurityRateLimitBlockKey());
        RateLimiter::clear($this->siteSecurityProbeBlockKey());
        RateLimiter::clear($this->siteSecuritySiteWideRateKey());
        RateLimiter::clear($this->siteSecurityRateKeyForPath('/'));
        RateLimiter::clear($this->siteSecurityDeviceRateLimitBlockKey('device-no-probe'));
        RateLimiter::clear($this->siteSecurityDeviceRateKey('device-no-probe', 'site'));
        RateLimiter::clear($this->siteSecurityDeviceRateKey('device-no-probe', 'path', '/'));

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-no-probe')->get('/?site=site')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-no-probe')->get('/?site=site')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-no-probe')->get('/?site=site')->assertForbidden()->assertSee('当前请求已被安全防护拦截');

        RateLimiter::clear($this->siteSecuritySiteWideRateKey());
        RateLimiter::clear($this->siteSecurityRateKeyForPath('/'));
        RateLimiter::clear($this->siteSecurityDeviceRateKey('device-no-probe', 'site'));
        RateLimiter::clear($this->siteSecurityDeviceRateKey('device-no-probe', 'path', '/'));

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-no-probe')->get('/?site=site')->assertForbidden()->assertSee('当前请求已被安全防护拦截');

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $siteId,
            'blocked_rate_limit' => 2,
            'blocked_probe_abuse' => 0,
        ]);
    }

    public function test_site_security_rate_limit_block_is_scoped_per_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $remoteSiteId = $this->createAdditionalSite('security-rate-remote-site', '限流远程站点');
        $now = now();

        DB::table('site_domains')->insert([
            'site_id' => $remoteSiteId,
            'domain' => 'security-rate-remote.test',
            'is_primary' => 1,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '2',
            'security.rate_limit_sensitive_max_requests' => '1',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-site-scope')->get('/?site=site')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-site-scope')->get('/?site=site')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-site-scope')->get('/?site=site')->assertForbidden()->assertSee('当前请求已被安全防护拦截');

        $this->assertNotSame(403, $this->get('http://security-rate-remote.test/')->getStatusCode());
    }

    public function test_site_security_blocks_fast_post_requests_across_multiple_paths(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        foreach ([
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '5',
            'security.rate_limit_sensitive_max_requests' => '1',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        Route::middleware('web')->post('/security-form-a', fn () => response('ok'));
        Route::middleware('web')->post('/security-form-b', fn () => response('ok'));

        RateLimiter::clear($this->siteSecurityRateLimitBlockKey());
        RateLimiter::clear($this->siteSecurityFormWideRateKey());
        RateLimiter::clear($this->siteSecurityRateKeyForPath('/security-form-a'));
        RateLimiter::clear($this->siteSecurityRateKeyForPath('/security-form-b'));
        RateLimiter::clear($this->siteSecurityDeviceRateLimitBlockKey('device-form'));
        RateLimiter::clear($this->siteSecurityDeviceRateKey('device-form', 'form'));
        RateLimiter::clear($this->siteSecurityDeviceRateKey('device-form', 'path', '/security-form-a'));
        RateLimiter::clear($this->siteSecurityDeviceRateKey('device-form', 'path', '/security-form-b'));

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-form')->post('/security-form-a?site=site')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-form')->post('/security-form-b?site=site')->assertForbidden()->assertSee('当前请求已被安全防护拦截');

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $siteId,
            'blocked_rate_limit' => 1,
        ]);
    }

    public function test_site_security_temporarily_blocks_ip_after_repeated_high_risk_hits(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        foreach ([
            'security.scan_probe_enabled' => '0',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $this->get('/?site=site&keyword='.urlencode('union select 1'))->assertForbidden();
        $this->get('/?site=site&keyword='.urlencode('sleep(1)'))->assertForbidden();
        $this->get('/?site=site&keyword='.urlencode('information_schema'))->assertForbidden();

        $this->assertDatabaseHas('site_security_ip_reputations', [
            'site_id' => $siteId,
            'client_ip' => '127.0.0.1',
            'status' => 'blocked',
            'high_risk_count' => 3,
        ]);

        $eventCountBeforeBlockedRetry = DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->count();

        $this->get('/?site=site')
            ->assertForbidden()
            ->assertSee('当前请求已被安全防护拦截');

        $this->assertSame(
            $eventCountBeforeBlockedRetry,
            DB::table('site_security_events')
                ->where('site_id', $siteId)
                ->count()
        );
    }

    public function test_site_security_temporary_ip_block_is_scoped_per_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $remoteSiteId = $this->createAdditionalSite('security-block-remote-site', '封禁远程站点');
        $now = now();

        DB::table('site_domains')->insert([
            'site_id' => $remoteSiteId,
            'domain' => 'security-block-remote.test',
            'is_primary' => 1,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            'security.scan_probe_enabled' => '0',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $this->get('/?site=site&keyword='.urlencode('union select 1'))->assertForbidden();
        $this->get('/?site=site&keyword='.urlencode('sleep(1)'))->assertForbidden();
        $this->get('/?site=site&keyword='.urlencode('information_schema'))->assertForbidden();
        $this->get('/?site=site')->assertForbidden()->assertSee('当前请求已被安全防护拦截');

        $this->assertNotSame(403, $this->get('http://security-block-remote.test/')->getStatusCode());
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

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-media')->get('/site-media/site/attachments/2026/04/security-rate-limit.jpg')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-media')->get('/site-media/site/attachments/2026/04/security-rate-limit.jpg')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-media')->get('/site-media/site/attachments/2026/04/security-rate-limit.jpg')->assertForbidden();

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

    public function test_site_media_rate_limit_accumulates_across_multiple_assets(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        foreach ([
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '1',
            'security.rate_limit_sensitive_max_requests' => '1',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $firstPath = storage_path('app/web/site/media/attachments/2026/04/security-rate-limit-a.jpg');
        $secondPath = storage_path('app/web/site/media/attachments/2026/04/security-rate-limit-b.jpg');
        File::ensureDirectoryExists(dirname($firstPath));
        File::put($firstPath, 'security-media-a');
        File::put($secondPath, 'security-media-b');

        RateLimiter::clear($this->siteSecurityRateLimitBlockKey());
        RateLimiter::clear($this->siteSecurityMediaWideRateKey());
        RateLimiter::clear($this->siteSecurityRateKeyForPath('/site-media/site/attachments/2026/04/security-rate-limit-a.jpg'));
        RateLimiter::clear($this->siteSecurityRateKeyForPath('/site-media/site/attachments/2026/04/security-rate-limit-b.jpg'));
        RateLimiter::clear($this->siteSecurityDeviceRateLimitBlockKey('device-media-assets'));
        RateLimiter::clear($this->siteSecurityDeviceRateKey('device-media-assets', 'media'));
        RateLimiter::clear($this->siteSecurityDeviceRateKey('device-media-assets', 'path', '/site-media/site/attachments/2026/04/security-rate-limit-a.jpg'));
        RateLimiter::clear($this->siteSecurityDeviceRateKey('device-media-assets', 'path', '/site-media/site/attachments/2026/04/security-rate-limit-b.jpg'));

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-media-assets')->get('/site-media/site/attachments/2026/04/security-rate-limit-a.jpg')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-media-assets')->get('/site-media/site/attachments/2026/04/security-rate-limit-b.jpg')->assertForbidden()->assertSee('当前请求已被安全防护拦截');

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $siteId,
            'blocked_rate_limit' => 1,
        ]);
    }

    public function test_site_security_blocks_rapid_multi_path_scan_requests(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        foreach ([
            'security.scan_probe_enabled' => '1',
            'security.scan_probe_window_seconds' => '300',
            'security.scan_probe_threshold' => '3',
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '20',
            'security.rate_limit_sensitive_max_requests' => '10',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        Route::middleware('web')->get('/scan-step-a', fn () => response('ok-a'));
        Route::middleware('web')->get('/scan-step-b', fn () => response('ok-b'));
        Route::middleware('web')->get('/scan-step-c', fn () => response('ok-c'));

        Cache::forget($this->siteSecurityPathScanKey());
        RateLimiter::clear($this->siteSecurityProbeBlockKey());

        $this->assertNotSame(403, $this->get('/scan-step-a?site=site')->getStatusCode());
        $this->assertNotSame(403, $this->get('/scan-step-b?site=site')->getStatusCode());
        $this->get('/scan-step-c?site=site')->assertForbidden()->assertSee('当前请求已被安全防护拦截');

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $siteId,
            'blocked_probe_abuse' => 1,
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $siteId,
            'rule_code' => 'probe_abuse',
            'request_path' => '/scan-step-c',
        ]);
    }

    public function test_site_security_disabled_scan_probe_does_not_block_rapid_multi_path_scan_requests(): void
    {
        $this->seed(DatabaseSeeder::class);

        $now = now();

        foreach ([
            'security.scan_probe_enabled' => '0',
            'security.scan_probe_window_seconds' => '300',
            'security.scan_probe_threshold' => '3',
            'security.rate_limit_window_seconds' => '10',
            'security.rate_limit_max_requests' => '20',
            'security.rate_limit_sensitive_max_requests' => '10',
            'security.rate_limit_block_seconds' => '60',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        Route::middleware('web')->get('/scan-disabled-a', fn () => response('ok-a'));
        Route::middleware('web')->get('/scan-disabled-b', fn () => response('ok-b'));
        Route::middleware('web')->get('/scan-disabled-c', fn () => response('ok-c'));

        Cache::forget($this->siteSecurityPathScanKey());
        RateLimiter::clear($this->siteSecurityProbeBlockKey());

        $this->assertNotSame(403, $this->get('/scan-disabled-a?site=site')->getStatusCode());
        $this->assertNotSame(403, $this->get('/scan-disabled-b?site=site')->getStatusCode());
        $this->assertNotSame(403, $this->get('/scan-disabled-c?site=site')->getStatusCode());
    }

    public function test_site_security_rapid_path_scan_drops_legacy_path_list_cache_format(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        foreach ([
            'security.scan_probe_enabled' => '1',
            'security.scan_probe_window_seconds' => '300',
            'security.scan_probe_threshold' => '99',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        Cache::put($this->siteSecurityPathScanKey(), ['/legacy-a', '/legacy-b'], now()->addMinute());

        $request = Request::create('/scan-fresh', 'GET', ['site' => 'site'], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $siteSecurity = new SiteSecurity(new SystemSettings);
        $method = new \ReflectionMethod($siteSecurity, 'matchRapidPathScan');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($siteSecurity, $request, $siteId));
        $this->assertSame(['/scan-fresh'], array_keys(Cache::get($this->siteSecurityPathScanKey(), [])));
    }

    public function test_site_security_rapid_path_scan_ignores_theme_and_legacy_asset_requests(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $siteSecurity = new SiteSecurity(new SystemSettings);
        $method = new \ReflectionMethod($siteSecurity, 'matchRapidPathScan');
        $method->setAccessible(true);

        Cache::forget($this->siteSecurityPathScanKey());

        $themeAssetRequest = Request::create('/theme-assets/default/assets/app.css', 'GET', ['site' => 'site'], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $legacyAssetRequest = Request::create('/Up/liuyi.jpg', 'GET', ['site' => 'site'], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        $this->assertNull($method->invoke($siteSecurity, $themeAssetRequest, $siteId));
        $this->assertNull($method->invoke($siteSecurity, $legacyAssetRequest, $siteId));
        $this->assertSame([], Cache::get($this->siteSecurityPathScanKey(), []));
    }

    public function test_site_media_frequent_refresh_uses_route_site_key_for_local_preview_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $remoteSiteId = $this->createAdditionalSite('security-media-remote-site', '媒体限流远程站点');
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

        $mediaPath = storage_path('app/web/security-media-remote-site/media/attachments/2026/04/security-rate-limit.jpg');
        File::ensureDirectoryExists(dirname($mediaPath));
        File::put($mediaPath, 'security-media-remote');

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-media-remote')->get('/site-media/security-media-remote-site/attachments/2026/04/security-rate-limit.jpg')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-media-remote')->get('/site-media/security-media-remote-site/attachments/2026/04/security-rate-limit.jpg')->assertOk();
        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-media-remote')->get('/site-media/security-media-remote-site/attachments/2026/04/security-rate-limit.jpg')->assertForbidden();

        $this->assertDatabaseHas('site_security_daily_stats', [
            'site_id' => $remoteSiteId,
            'blocked_total' => 1,
            'blocked_rate_limit' => 1,
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $remoteSiteId,
            'rule_code' => 'rate_limit',
            'request_path' => '/site-media/security-media-remote-site/attachments/2026/04/security-rate-limit.jpg',
            'request_method' => 'GET',
        ]);

        $this->assertDatabaseMissing('site_security_daily_stats', [
            'site_id' => $mainSiteId,
            'blocked_rate_limit' => 1,
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
            ->assertSee('总拦截次数')
            ->assertDontSee('近 7 天高危次数')
            ->assertSee('/.env')
            ->assertDontSee('/wp-admin')
            ->assertSee('12')
            ->assertSee('直接拦截')
            ->assertDontSee('127.0.0.2');
    }

    public function test_site_security_page_shows_suspicious_ip_reputation_without_policy_panels(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-posture-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_daily_stats')->insert([
            'site_id' => $mainSiteId,
            'stat_date' => now()->toDateString(),
            'blocked_total' => 5,
            'blocked_bad_path' => 1,
            'blocked_sql_injection' => 2,
            'blocked_xss' => 0,
            'blocked_path_traversal' => 1,
            'blocked_bad_upload' => 0,
            'blocked_rate_limit' => 0,
            'blocked_probe_abuse' => 1,
            'blocked_ip_blocklist' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => '8.8.8.8',
            'ip_hash' => hash('sha256', '8.8.8.8'),
            'hit_count' => 5,
            'high_risk_count' => 4,
            'last_rule_code' => 'probe_abuse',
            'last_request_path' => '/wp-admin',
            'status' => 'blocked',
            'blocked_until' => now()->addMinutes(5),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('site_settings')->insert([
            'site_id' => $mainSiteId,
            'setting_key' => 'security.ip_blocklist',
            'setting_value' => '8.8.8.8',
            'autoload' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertDontSee('当前风险态势')
            ->assertDontSee('黑白名单策略')
            ->assertSee('可疑 IP 排行')
            ->assertSee('8.8.8.8')
            ->assertSee('已封禁')
            ->assertSee('已拉黑')
            ->assertDontSee('解封');
    }

    public function test_site_security_page_reflects_cidr_site_policy_labels_for_suspicious_ip(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-cidr-policy-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => '192.168.1.88',
            'ip_hash' => hash('sha256', '192.168.1.88'),
            'hit_count' => 3,
            'high_risk_count' => 2,
            'last_rule_code' => 'bad_path',
            'last_request_path' => '/.env',
            'status' => 'monitored',
            'blocked_until' => null,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_settings')->insert([
            'site_id' => $mainSiteId,
            'setting_key' => 'security.ip_blocklist',
            'setting_value' => '192.168.1.0/24',
            'autoload' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('192.168.1.88')
            ->assertSee('已封禁')
            ->assertSee('已拉黑');
    }

    public function test_site_security_page_reflects_global_blocklist_label_for_suspicious_ip(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-global-policy-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => '203.0.113.88',
            'ip_hash' => hash('sha256', '203.0.113.88'),
            'hit_count' => 2,
            'high_risk_count' => 1,
            'last_rule_code' => 'bad_path',
            'last_request_path' => '/.env',
            'status' => 'monitored',
            'blocked_until' => null,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'security.ip_blocklist'],
            ['setting_value' => '203.0.113.0/24', 'autoload' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('203.0.113.88')
            ->assertSee('已封禁')
            ->assertSee('平台已拉黑')
            ->assertDontSee('>加白<', false)
            ->assertDontSee('>拉黑<', false)
            ->assertDontSee('解封');
    }

    public function test_site_security_page_reflects_global_allowlist_label_for_suspicious_ip(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-global-allow-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => '198.51.100.88',
            'ip_hash' => hash('sha256', '198.51.100.88'),
            'hit_count' => 2,
            'high_risk_count' => 1,
            'last_rule_code' => 'bad_path',
            'last_request_path' => '/.env',
            'status' => 'blocked',
            'blocked_until' => now()->addMinutes(10),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'security.ip_allowlist'],
            ['setting_value' => '198.51.100.0/24', 'autoload' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('198.51.100.88')
            ->assertSee('观察中')
            ->assertSee('平台已加白')
            ->assertDontSee('>加白<', false)
            ->assertDontSee('>拉黑<', false)
            ->assertDontSee('解封');
    }

    public function test_site_security_page_prefers_global_blocklist_over_site_allowlist(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-global-block-priority-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => '203.0.113.99',
            'ip_hash' => hash('sha256', '203.0.113.99'),
            'hit_count' => 1,
            'high_risk_count' => 1,
            'last_rule_code' => 'bad_path',
            'last_request_path' => '/.env',
            'status' => 'monitored',
            'blocked_until' => null,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'security.ip_blocklist'],
            ['setting_value' => '203.0.113.0/24', 'autoload' => 1, 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('site_settings')->insert([
            'site_id' => $mainSiteId,
            'setting_key' => 'security.ip_allowlist',
            'setting_value' => '203.0.113.99',
            'autoload' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('203.0.113.99')
            ->assertSee('已封禁')
            ->assertSee('平台已拉黑')
            ->assertDontSee('已加白');
    }

    public function test_site_security_site_admin_cannot_change_site_ip_policy_when_global_policy_matches(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-global-override-action-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'security.ip_blocklist'],
            ['setting_value' => '203.0.113.0/24', 'autoload' => 1, 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('site_settings')->insert([
            'site_id' => $mainSiteId,
            'setting_key' => 'security.ip_blocklist',
            'setting_value' => '203.0.113.88',
            'autoload' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => '203.0.113.88',
            'ip_hash' => hash('sha256', '203.0.113.88'),
            'hit_count' => 2,
            'high_risk_count' => 1,
            'last_rule_code' => 'probe_abuse',
            'last_request_path' => '/wp-admin',
            'status' => 'blocked',
            'blocked_until' => now()->addMinutes(10),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->from(route('admin.security.index'))
            ->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->post(route('admin.security.ip-policy.store'), [
                'client_ip' => '203.0.113.88',
                'action' => 'allow',
            ])
            ->assertRedirect(route('admin.security.index'))
            ->assertSessionHasErrors([
                'client_ip' => '该 IP 当前受平台全局黑白名单控制，请先在平台安全设置中调整。',
            ]);

        $this->from(route('admin.security.index'))
            ->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->post(route('admin.security.ip-policy.store'), [
                'client_ip' => '203.0.113.88',
                'action' => 'release_block',
            ])
            ->assertRedirect(route('admin.security.index'))
            ->assertSessionHasErrors([
                'client_ip' => '该 IP 当前受平台全局黑白名单控制，请先在平台安全设置中调整。',
            ]);

        $this->assertNull(DB::table('site_settings')->where('site_id', $mainSiteId)->where('setting_key', 'security.ip_allowlist')->value('setting_value'));
        $this->assertSame('203.0.113.88', DB::table('site_settings')->where('site_id', $mainSiteId)->where('setting_key', 'security.ip_blocklist')->value('setting_value'));
        $this->assertDatabaseHas('site_security_ip_reputations', [
            'site_id' => $mainSiteId,
            'client_ip' => '203.0.113.88',
            'status' => 'blocked',
        ]);
    }

    public function test_site_security_viewer_cannot_change_site_ip_policy(): void
    {
        $this->seed(DatabaseSeeder::class);

        $reviewer = $this->createSiteOperator('security-viewer-policy-reviewer', true, 'reviewer');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => '8.8.8.8',
            'ip_hash' => hash('sha256', '8.8.8.8'),
            'hit_count' => 1,
            'high_risk_count' => 1,
            'last_rule_code' => 'bad_path',
            'last_request_path' => '/.env',
            'status' => 'monitored',
            'blocked_until' => null,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertDontSee('>加白<', false)
            ->assertDontSee('>拉黑<', false)
            ->assertDontSee('>移白<', false)
            ->assertDontSee('>移黑<', false)
            ->assertDontSee('>解封<', false);

        $this->actingAs($reviewer)
            ->withSession(['current_site_id' => $mainSiteId])
            ->post(route('admin.security.ip-policy.store'), [
                'client_ip' => '8.8.8.8',
                'action' => 'block',
            ])
            ->assertForbidden();

        $this->assertNull(DB::table('site_settings')->where('site_id', $mainSiteId)->where('setting_key', 'security.ip_blocklist')->value('setting_value'));
    }

    public function test_site_security_page_prefers_allowlist_label_when_ip_matches_both_site_policies(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-overlap-policy-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => '192.168.1.88',
            'ip_hash' => hash('sha256', '192.168.1.88'),
            'hit_count' => 3,
            'high_risk_count' => 2,
            'last_rule_code' => 'bad_path',
            'last_request_path' => '/.env',
            'status' => 'blocked',
            'blocked_until' => now()->addMinutes(10),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_settings')->insert([
            [
                'site_id' => $mainSiteId,
                'setting_key' => 'security.ip_allowlist',
                'setting_value' => '192.168.1.0/24',
                'autoload' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $mainSiteId,
                'setting_key' => 'security.ip_blocklist',
                'setting_value' => '192.168.1.0/24',
                'autoload' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('192.168.1.88')
            ->assertSee('已加白')
            ->assertSee('观察中')
            ->assertDontSee('已拉黑')
            ->assertDontSee('解封');
    }

    public function test_site_security_page_labels_expired_temporary_block_as_released(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-expired-block-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => '203.0.113.77',
            'ip_hash' => hash('sha256', '203.0.113.77'),
            'hit_count' => 3,
            'high_risk_count' => 2,
            'last_rule_code' => 'probe_abuse',
            'last_request_path' => '/payroll/password/unlock',
            'status' => 'blocked',
            'blocked_until' => now()->subMinutes(10),
            'last_seen_at' => now()->subMinutes(9),
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('203.0.113.77')
            ->assertSee('封禁已解除')
            ->assertSee('已于 ')
            ->assertDontSee('至 ')
            ->assertDontSee('解封');
    }

    public function test_site_security_page_labels_expired_24_hour_block_as_released(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-expired-24h-block-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $blockedAt = now()->subDays(2);

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => '203.0.113.78',
            'ip_hash' => hash('sha256', '203.0.113.78'),
            'hit_count' => 3,
            'high_risk_count' => 2,
            'last_rule_code' => 'probe_abuse',
            'last_request_path' => '/wp-admin',
            'status' => 'blocked',
            'blocked_until' => $blockedAt->copy()->addDay(),
            'last_seen_at' => $blockedAt,
            'created_at' => $blockedAt,
            'updated_at' => $blockedAt,
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('203.0.113.78')
            ->assertSee('24小时封禁已解除')
            ->assertSee('已于 ')
            ->assertDontSee('解封');
    }

    public function test_site_security_page_labels_limited_status_as_access_restricted(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-limited-status-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => '198.51.100.66',
            'ip_hash' => hash('sha256', '198.51.100.66'),
            'hit_count' => 5,
            'high_risk_count' => 0,
            'last_rule_code' => 'rate_limit',
            'last_request_path' => '/guestbook',
            'status' => 'limited',
            'blocked_until' => null,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('198.51.100.66')
            ->assertSee('访问受限')
            ->assertDontSee('限速中');
    }

    public function test_site_security_page_labels_expired_limited_status_as_released(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-expired-limited-status-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => '198.51.100.67',
            'ip_hash' => hash('sha256', '198.51.100.67'),
            'hit_count' => 5,
            'high_risk_count' => 0,
            'last_rule_code' => 'rate_limit',
            'last_request_path' => '/guestbook/create',
            'status' => 'limited',
            'blocked_until' => null,
            'last_seen_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('198.51.100.67')
            ->assertSee('访问限制已解除')
            ->assertDontSee('访问受限');
    }

    public function test_site_security_suspicious_ip_ranking_prioritizes_blocked_status(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-ranking-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_ip_reputations')->insert([
            [
                'site_id' => $mainSiteId,
                'client_ip' => '8.8.8.8',
                'ip_hash' => hash('sha256', '8.8.8.8'),
                'hit_count' => 2,
                'high_risk_count' => 1,
                'last_rule_code' => 'probe_abuse',
                'last_request_path' => '/blocked-first',
                'status' => 'blocked',
                'blocked_until' => now()->addMinutes(5),
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $mainSiteId,
                'client_ip' => '9.9.9.9',
                'ip_hash' => hash('sha256', '9.9.9.9'),
                'hit_count' => 20,
                'high_risk_count' => 10,
                'last_rule_code' => 'bad_path',
                'last_request_path' => '/monitored-later',
                'status' => 'monitored',
                'blocked_until' => null,
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('data-security-ip-detail-link', false)
            ->assertSeeInOrder([
                '8.8.8.8',
                '9.9.9.9',
            ], false);
    }

    public function test_site_security_suspicious_ip_ranking_uses_effective_allowlist_status(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-effective-ranking-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_ip_reputations')->insert([
            [
                'site_id' => $mainSiteId,
                'client_ip' => '8.8.8.8',
                'ip_hash' => hash('sha256', '8.8.8.8'),
                'hit_count' => 2,
                'high_risk_count' => 1,
                'last_rule_code' => 'probe_abuse',
                'last_request_path' => '/allowlisted-history-blocked',
                'status' => 'blocked',
                'blocked_until' => now()->addMinutes(5),
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $mainSiteId,
                'client_ip' => '9.9.9.9',
                'ip_hash' => hash('sha256', '9.9.9.9'),
                'hit_count' => 1,
                'high_risk_count' => 1,
                'last_rule_code' => 'probe_abuse',
                'last_request_path' => '/still-blocked',
                'status' => 'blocked',
                'blocked_until' => now()->addMinutes(5),
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('site_settings')->insert([
            'site_id' => $mainSiteId,
            'setting_key' => 'security.ip_allowlist',
            'setting_value' => '8.8.8.8',
            'autoload' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSeeInOrder([
                '9.9.9.9',
                '8.8.8.8',
            ], false);
    }

    public function test_site_security_suspicious_ip_ranking_prefers_more_recent_record_on_same_weight(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-ranking-recent-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_ip_reputations')->insert([
            [
                'site_id' => $mainSiteId,
                'client_ip' => '8.8.8.8',
                'ip_hash' => hash('sha256', '8.8.8.8'),
                'hit_count' => 2,
                'high_risk_count' => 1,
                'last_rule_code' => 'bad_path',
                'last_request_path' => '/older',
                'status' => 'monitored',
                'blocked_until' => null,
                'last_seen_at' => now()->subMinutes(5),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $mainSiteId,
                'client_ip' => '9.9.9.9',
                'ip_hash' => hash('sha256', '9.9.9.9'),
                'hit_count' => 2,
                'high_risk_count' => 1,
                'last_rule_code' => 'bad_path',
                'last_request_path' => '/newer',
                'status' => 'monitored',
                'blocked_until' => null,
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSeeInOrder([
                '9.9.9.9',
                '8.8.8.8',
            ], false);
    }

    public function test_site_security_site_admin_can_add_suspicious_ip_to_site_blocklist(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-action-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $remoteSiteId = $this->createAdditionalSite('security-ip-action-remote-site', 'IP 处置远程站点');

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => '8.8.8.8',
            'ip_hash' => hash('sha256', '8.8.8.8'),
            'hit_count' => 2,
            'high_risk_count' => 1,
            'last_rule_code' => 'bad_path',
            'last_request_path' => '/.env',
            'status' => 'monitored',
            'blocked_until' => null,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->post(route('admin.security.ip-policy.store'), [
                'client_ip' => '8.8.8.8',
                'action' => 'block',
            ])
            ->assertRedirect(route('admin.security.index'))
            ->assertSessionHas('status', '已加入站点 IP 黑名单。');

        $this->assertSame('8.8.8.8', DB::table('site_settings')->where('site_id', $mainSiteId)->where('setting_key', 'security.ip_blocklist')->value('setting_value'));
        $this->assertNull(DB::table('site_settings')->where('site_id', $remoteSiteId)->where('setting_key', 'security.ip_blocklist')->value('setting_value'));
        $this->assertDatabaseHas('site_security_ip_reputations', [
            'site_id' => $mainSiteId,
            'client_ip' => '8.8.8.8',
            'status' => 'blocked',
            'blocked_until' => null,
        ]);
        $this->assertDatabaseHas('operation_logs', [
            'scope' => 'site',
            'module' => 'security',
            'action' => 'security_block_ip',
            'site_id' => $mainSiteId,
            'user_id' => $siteAdmin->id,
            'target_type' => 'site_security_ip',
        ]);
    }

    public function test_site_security_site_admin_can_add_suspicious_ip_to_site_allowlist_and_remove_blocklist_conflict(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-allow-action-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $ip = '8.8.8.8';

        DB::table('site_settings')->insert([
            'site_id' => $mainSiteId,
            'setting_key' => 'security.ip_blocklist',
            'setting_value' => "8.8.8.8\n1.1.1.1",
            'autoload' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => $ip,
            'ip_hash' => hash('sha256', $ip),
            'hit_count' => 2,
            'high_risk_count' => 1,
            'last_rule_code' => 'bad_path',
            'last_request_path' => '/.env',
            'status' => 'blocked',
            'blocked_until' => now()->addMinutes(10),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        RateLimiter::hit('site-security-rate-block:'.$mainSiteId.':'.sha1($ip), 60);
        RateLimiter::hit('site-security-probe-block:'.$mainSiteId.':'.sha1($ip), 60);
        RateLimiter::hit('site-security-reputation-block:'.$mainSiteId.':'.sha1($ip), 60);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->post(route('admin.security.ip-policy.store'), [
                'client_ip' => $ip,
                'action' => 'allow',
            ])
            ->assertRedirect(route('admin.security.index'))
            ->assertSessionHas('status', '已加入站点 IP 白名单。');

        $this->assertSame('8.8.8.8', DB::table('site_settings')->where('site_id', $mainSiteId)->where('setting_key', 'security.ip_allowlist')->value('setting_value'));
        $this->assertSame('1.1.1.1', DB::table('site_settings')->where('site_id', $mainSiteId)->where('setting_key', 'security.ip_blocklist')->value('setting_value'));
        $this->assertDatabaseHas('site_security_ip_reputations', [
            'site_id' => $mainSiteId,
            'client_ip' => $ip,
            'status' => 'monitored',
            'blocked_until' => null,
        ]);
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-rate-block:'.$mainSiteId.':'.sha1($ip), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-probe-block:'.$mainSiteId.':'.sha1($ip), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-reputation-block:'.$mainSiteId.':'.sha1($ip), 1));
        $this->assertDatabaseHas('operation_logs', [
            'scope' => 'site',
            'module' => 'security',
            'action' => 'security_allow_ip',
            'site_id' => $mainSiteId,
            'user_id' => $siteAdmin->id,
            'target_type' => 'site_security_ip',
        ]);
    }

    public function test_site_security_site_admin_can_remove_suspicious_ip_from_site_allowlist(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-remove-allow-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_settings')->insert([
            'site_id' => $mainSiteId,
            'setting_key' => 'security.ip_allowlist',
            'setting_value' => "8.8.8.8\n1.1.1.1",
            'autoload' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->post(route('admin.security.ip-policy.store'), [
                'client_ip' => '8.8.8.8',
                'action' => 'remove_allow',
            ])
            ->assertRedirect(route('admin.security.index'))
            ->assertSessionHas('status', '已移出站点 IP 白名单。');

        $this->assertSame('1.1.1.1', DB::table('site_settings')->where('site_id', $mainSiteId)->where('setting_key', 'security.ip_allowlist')->value('setting_value'));
        $this->assertDatabaseHas('operation_logs', [
            'scope' => 'site',
            'module' => 'security',
            'action' => 'security_remove_allow_ip',
            'site_id' => $mainSiteId,
            'user_id' => $siteAdmin->id,
            'target_type' => 'site_security_ip',
        ]);
    }

    public function test_site_security_site_admin_can_remove_suspicious_ip_from_site_blocklist(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-remove-block-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $ip = '8.8.8.8';

        DB::table('site_settings')->insert([
            'site_id' => $mainSiteId,
            'setting_key' => 'security.ip_blocklist',
            'setting_value' => "8.8.8.8\n1.1.1.1",
            'autoload' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => $ip,
            'ip_hash' => hash('sha256', $ip),
            'hit_count' => 3,
            'high_risk_count' => 2,
            'last_rule_code' => 'probe_abuse',
            'last_request_path' => '/wp-admin',
            'status' => 'blocked',
            'blocked_until' => null,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        RateLimiter::hit('site-security-rate-block:'.$mainSiteId.':'.sha1($ip), 60);
        RateLimiter::hit('site-security-probe-block:'.$mainSiteId.':'.sha1($ip), 60);
        RateLimiter::hit('site-security-reputation-block:'.$mainSiteId.':'.sha1($ip), 60);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->post(route('admin.security.ip-policy.store'), [
                'client_ip' => $ip,
                'action' => 'remove_block',
            ])
            ->assertRedirect(route('admin.security.index'))
            ->assertSessionHas('status', '已移出站点 IP 黑名单。');

        $this->assertSame('1.1.1.1', DB::table('site_settings')->where('site_id', $mainSiteId)->where('setting_key', 'security.ip_blocklist')->value('setting_value'));
        $this->assertDatabaseHas('site_security_ip_reputations', [
            'site_id' => $mainSiteId,
            'client_ip' => $ip,
            'status' => 'monitored',
            'blocked_until' => null,
        ]);
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-rate-block:'.$mainSiteId.':'.sha1($ip), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-probe-block:'.$mainSiteId.':'.sha1($ip), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-reputation-block:'.$mainSiteId.':'.sha1($ip), 1));
        $this->assertDatabaseHas('operation_logs', [
            'scope' => 'site',
            'module' => 'security',
            'action' => 'security_remove_block_ip',
            'site_id' => $mainSiteId,
            'user_id' => $siteAdmin->id,
            'target_type' => 'site_security_ip',
        ]);
    }

    public function test_site_security_site_admin_can_release_temporary_ip_block(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-release-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => '8.8.8.8',
            'ip_hash' => hash('sha256', '8.8.8.8'),
            'hit_count' => 5,
            'high_risk_count' => 4,
            'last_rule_code' => 'probe_abuse',
            'last_request_path' => '/wp-admin',
            'status' => 'blocked',
            'blocked_until' => now()->addMinutes(10),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->post(route('admin.security.ip-policy.store'), [
                'client_ip' => '8.8.8.8',
                'action' => 'release_block',
            ])
            ->assertRedirect(route('admin.security.index'))
            ->assertSessionHas('status', '已解除临时封禁。');

        $this->assertDatabaseHas('site_security_ip_reputations', [
            'site_id' => $mainSiteId,
            'client_ip' => '8.8.8.8',
            'status' => 'monitored',
            'blocked_until' => null,
        ]);
        $this->assertDatabaseHas('operation_logs', [
            'scope' => 'site',
            'module' => 'security',
            'action' => 'security_release_ip_block',
            'site_id' => $mainSiteId,
            'user_id' => $siteAdmin->id,
            'target_type' => 'site_security_ip',
        ]);
    }

    public function test_site_security_release_temporary_ip_block_clears_runtime_block_keys(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-release-runtime-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $ip = '8.8.8.8';

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => $ip,
            'ip_hash' => hash('sha256', $ip),
            'hit_count' => 5,
            'high_risk_count' => 4,
            'last_rule_code' => 'probe_abuse',
            'last_request_path' => '/',
            'status' => 'blocked',
            'blocked_until' => now()->addMinutes(10),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        RateLimiter::hit('site-security-rate-block:'.$mainSiteId.':'.sha1($ip), 60);
        RateLimiter::hit('site-security-probe-block:'.$mainSiteId.':'.sha1($ip), 60);
        RateLimiter::hit('site-security-reputation-block:'.$mainSiteId.':'.sha1($ip), 60);
        RateLimiter::hit('site-security-rate:'.$mainSiteId.':site:'.sha1($ip), 60);
        RateLimiter::hit('site-security-rate:'.$mainSiteId.':form:'.sha1($ip), 60);
        RateLimiter::hit('site-security-rate:'.$mainSiteId.':media:'.sha1($ip), 60);
        RateLimiter::hit('site-security-rate:'.$mainSiteId.':'.sha1($ip.'|/'), 60);
        RateLimiter::hit('site-security-probe:'.$mainSiteId.':'.sha1($ip), 60);
        RateLimiter::hit('site-security-probe:'.$mainSiteId.':probe_abuse:'.sha1($ip), 60);
        Cache::put('site-security-path-scan:'.$mainSiteId.':'.sha1($ip), ['/scan-a', '/scan-b', '/scan-c'], now()->addMinute());

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->post(route('admin.security.ip-policy.store'), [
                'client_ip' => $ip,
                'action' => 'release_block',
            ])
            ->assertRedirect(route('admin.security.index'))
            ->assertSessionHas('status', '已解除临时封禁。');

        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-rate-block:'.$mainSiteId.':'.sha1($ip), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-probe-block:'.$mainSiteId.':'.sha1($ip), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-reputation-block:'.$mainSiteId.':'.sha1($ip), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-rate:'.$mainSiteId.':site:'.sha1($ip), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-rate:'.$mainSiteId.':form:'.sha1($ip), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-rate:'.$mainSiteId.':media:'.sha1($ip), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-rate:'.$mainSiteId.':'.sha1($ip.'|/'), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-probe:'.$mainSiteId.':'.sha1($ip), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-probe:'.$mainSiteId.':probe_abuse:'.sha1($ip), 1));
        $this->assertNull(Cache::get('site-security-path-scan:'.$mainSiteId.':'.sha1($ip)));
    }

    public function test_site_security_site_admin_can_view_suspicious_ip_detail_for_current_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-detail-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('security-ip-detail-remote-site', 'IP 详情远程站点');
        $ip = '8.8.8.8';
        $ipHash = hash('sha256', $ip);

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $mainSiteId,
            'client_ip' => $ip,
            'ip_hash' => $ipHash,
            'hit_count' => 5,
            'high_risk_count' => 3,
            'last_rule_code' => 'probe_abuse',
            'last_request_path' => '/wp-admin',
            'status' => 'blocked',
            'blocked_until' => now()->addMinutes(10),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_security_events')->insert([
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'probe_abuse',
                'rule_name' => '扫描试探超限',
                'request_path' => '/wp-admin',
                'request_method' => 'GET',
                'client_ip' => $ip,
                'ip_hash' => $ipHash,
                'risk_level' => 'critical',
                'action' => 'temporary_block',
                'request_query' => '{"path":"wp-admin"}',
                'referer' => 'https://example.test/',
                'user_agent' => 'SecurityTestAgent/1.0',
                'created_at' => now()->subMinute(),
            ],
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'probe_abuse',
                'rule_name' => '扫描试探超限',
                'request_path' => '/wp-login.php',
                'request_method' => 'GET',
                'client_ip' => $ip,
                'ip_hash' => $ipHash,
                'risk_level' => 'critical',
                'action' => 'temporary_block',
                'request_query' => '',
                'referer' => '',
                'user_agent' => 'SecurityTestAgent/1.0',
                'created_at' => now()->subMinutes(2),
            ],
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'rate_limit',
                'rule_name' => '异常高频访问',
                'request_path' => '/guestbook',
                'request_method' => 'GET',
                'client_ip' => $ip,
                'ip_hash' => $ipHash,
                'risk_level' => 'medium',
                'action' => 'rate_limited',
                'request_query' => '',
                'referer' => '',
                'user_agent' => 'SecurityRateAgent/1.0',
                'created_at' => now()->subMinutes(3),
            ],
            [
                'site_id' => $otherSiteId,
                'rule_code' => 'sql_injection',
                'rule_name' => 'SQL 注入',
                'request_path' => '/remote-only',
                'request_method' => 'GET',
                'client_ip' => $ip,
                'ip_hash' => $ipHash,
                'risk_level' => 'high',
                'action' => 'block',
                'request_query' => '{"q":"remote"}',
                'referer' => 'https://remote.test/',
                'user_agent' => 'RemoteAgent/1.0',
                'created_at' => now(),
            ],
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.ip-detail', ['client_ip' => $ip]))
            ->assertOk()
            ->assertSee('IP 详情')
            ->assertSee('data-security-ip-detail-content', false)
            ->assertSee($ip)
            ->assertSee('封禁原因聚合')
            ->assertSee('命中 2 次')
            ->assertSee('扫描试探超限')
            ->assertSee('/wp-admin')
            ->assertSee('SecurityTestAgent/1.0')
            ->assertSee('自动拦截')
            ->assertDontSee('限速拦截')
            ->assertDontSee('/remote-only');
    }

    public function test_site_security_ip_detail_is_scoped_to_current_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-detail-scope-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('security-ip-detail-only-remote-site', '仅外站命中');
        $ip = '8.8.4.4';

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $otherSiteId,
            'client_ip' => $ip,
            'ip_hash' => hash('sha256', $ip),
            'hit_count' => 2,
            'high_risk_count' => 1,
            'last_rule_code' => 'bad_path',
            'last_request_path' => '/.env',
            'status' => 'monitored',
            'blocked_until' => null,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.ip-detail', ['client_ip' => $ip]))
            ->assertNotFound();
    }

    public function test_site_logs_show_readable_site_security_ip_policy_action(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-log-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $ip = '8.8.8.8';

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->post(route('admin.security.ip-policy.store'), [
                'client_ip' => $ip,
                'action' => 'block',
            ])
            ->assertRedirect(route('admin.security.index'));

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.site-logs.index'))
            ->assertOk()
            ->assertSee('安护盾拉黑 IP')
            ->assertSee('安护盾 IP · '.$ip);
    }

    public function test_site_security_high_risk_total_follows_rule_risk_level(): void
    {
        $this->seed(DatabaseSeeder::class);

        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_daily_stats')->insert([
            'site_id' => $mainSiteId,
            'stat_date' => now()->toDateString(),
            'blocked_total' => 2,
            'blocked_bad_path' => 0,
            'blocked_sql_injection' => 1,
            'blocked_xss' => 0,
            'blocked_path_traversal' => 0,
            'blocked_bad_upload' => 0,
            'blocked_rate_limit' => 0,
            'blocked_probe_abuse' => 0,
            'blocked_ip_blocklist' => 0,
            'blocked_bad_client' => 0,
            'blocked_bad_method' => 1,
            'blocked_bad_payload' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = app(SiteSecurity::class)->sitePagePayload($mainSiteId);
        $badMethodType = collect($payload['types'])->firstWhere('code', 'bad_method');

        $this->assertSame(2, $payload['total_blocked']);
        $this->assertSame(1, $payload['seven_day_high_risk']);
        $this->assertSame(false, $badMethodType['is_high_risk'] ?? null);
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

    public function test_site_security_regions_resolve_missing_event_region_from_ip(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-region-fallback-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_events')->insert([
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'bad_path',
                'rule_name' => '异常路径拦截',
                'request_path' => '/demo-security/private-a',
                'request_method' => 'GET',
                'client_ip' => '10.12.0.8',
                'region_name' => null,
                'ip_hash' => hash('sha256', '10.12.0.8'),
                'created_at' => now()->subDay(),
            ],
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'bad_path',
                'rule_name' => '异常路径拦截',
                'request_path' => '/demo-security/private-b',
                'request_method' => 'GET',
                'client_ip' => '10.12.0.8',
                'region_name' => '',
                'ip_hash' => hash('sha256', '10.12.0.8'),
                'created_at' => now()->subDay()->subMinute(),
            ],
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('内网来源')
            ->assertDontSee('未知地区');
    }

    public function test_site_security_recent_events_only_render_from_last_seven_days(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-events-window-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_daily_stats')->insert([
            'site_id' => $mainSiteId,
            'stat_date' => now()->subDays(2)->toDateString(),
            'blocked_total' => 2,
            'blocked_bad_path' => 0,
            'blocked_sql_injection' => 1,
            'blocked_xss' => 0,
            'blocked_path_traversal' => 1,
            'blocked_bad_upload' => 0,
            'blocked_rate_limit' => 0,
            'blocked_probe_abuse' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_security_events')->insert([
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'sql_injection',
                'rule_name' => 'SQL 注入拦截',
                'request_path' => '/recent-sql',
                'request_method' => 'GET',
                'client_ip' => '8.8.8.8',
                'ip_hash' => hash('sha256', '8.8.8.8'),
                'created_at' => now()->subDays(2),
            ],
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'path_traversal',
                'rule_name' => '路径穿越拦截',
                'request_path' => '/old-path',
                'request_method' => 'GET',
                'client_ip' => '9.9.9.9',
                'ip_hash' => hash('sha256', '9.9.9.9'),
                'created_at' => now()->subDays(9),
            ],
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('/recent-sql')
            ->assertDontSee('/old-path');
    }

    public function test_site_security_recent_events_include_records_for_all_visible_filter_types(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-events-filter-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $rows = [];

        for ($i = 0; $i < 12; $i++) {
            $rows[] = [
                'site_id' => $mainSiteId,
                'rule_code' => 'bad_path',
                'rule_name' => '恶意扫描路径',
                'request_path' => '/latest-noise-'.$i,
                'request_method' => 'GET',
                'client_ip' => '10.0.0.'.($i + 1),
                'ip_hash' => hash('sha256', '10.0.0.'.($i + 1)),
                'region_name' => null,
                'action' => 'block',
                'risk_level' => 'medium',
                'created_at' => now()->subHours(1)->subSeconds($i),
            ];
        }

        $rows[] = [
            'site_id' => $mainSiteId,
            'rule_code' => 'rate_limit',
            'rule_name' => '频繁刷新拦截',
            'request_path' => '/only-rate-limit-filter',
            'request_method' => 'GET',
            'client_ip' => '8.8.4.4',
            'ip_hash' => hash('sha256', '8.8.4.4'),
            'region_name' => null,
            'action' => 'rate_limited',
            'risk_level' => 'medium',
            'created_at' => now()->subDays(2),
        ];

        $rows[] = [
            'site_id' => $mainSiteId,
            'rule_code' => 'bad_payload',
            'rule_name' => '异常请求参数拦截',
            'request_path' => '/only-bad-payload-filter',
            'request_method' => 'GET',
            'client_ip' => '8.8.8.9',
            'ip_hash' => hash('sha256', '8.8.8.9'),
            'region_name' => null,
            'action' => 'block',
            'risk_level' => 'high',
            'created_at' => now()->subDays(3),
        ];

        $rows[] = [
            'site_id' => $mainSiteId,
            'rule_code' => 'sql_injection',
            'rule_name' => 'SQL 注入拦截',
            'request_path' => '/only-sql-filter',
            'request_method' => 'GET',
            'client_ip' => '8.8.8.10',
            'ip_hash' => hash('sha256', '8.8.8.10'),
            'region_name' => null,
            'action' => 'block',
            'risk_level' => 'high',
            'created_at' => now()->subDays(4),
        ];

        $rows[] = [
            'site_id' => $mainSiteId,
            'rule_code' => 'xss',
            'rule_name' => 'XSS 攻击拦截',
            'request_path' => '/only-xss-filter',
            'request_method' => 'GET',
            'client_ip' => '8.8.8.11',
            'ip_hash' => hash('sha256', '8.8.8.11'),
            'region_name' => null,
            'action' => 'block',
            'risk_level' => 'high',
            'created_at' => now()->subDays(4)->subMinute(),
        ];

        $rows[] = [
            'site_id' => $mainSiteId,
            'rule_code' => 'path_traversal',
            'rule_name' => '路径穿越拦截',
            'request_path' => '/only-traversal-filter',
            'request_method' => 'GET',
            'client_ip' => '8.8.8.12',
            'ip_hash' => hash('sha256', '8.8.8.12'),
            'region_name' => null,
            'action' => 'block',
            'risk_level' => 'high',
            'created_at' => now()->subDays(4)->subMinutes(2),
        ];

        $rows[] = [
            'site_id' => $mainSiteId,
            'rule_code' => 'bad_upload',
            'rule_name' => '可疑上传拦截',
            'request_path' => '/only-upload-filter',
            'request_method' => 'POST',
            'client_ip' => '8.8.8.13',
            'ip_hash' => hash('sha256', '8.8.8.13'),
            'region_name' => null,
            'action' => 'block',
            'risk_level' => 'high',
            'created_at' => now()->subDays(4)->subMinutes(3),
        ];

        DB::table('site_security_events')->insert($rows);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('/only-rate-limit-filter')
            ->assertSee('/only-bad-payload-filter')
            ->assertSee('/only-sql-filter')
            ->assertSee('/only-xss-filter')
            ->assertSee('/only-traversal-filter')
            ->assertSee('/only-upload-filter');
    }

    public function test_site_security_recent_events_prioritize_risk_level_before_recency(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-events-priority-site-admin', true, 'site_admin');
        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_events')->insert([
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'bad_path',
                'rule_name' => '恶意扫描路径',
                'request_path' => '/latest-medium-event',
                'request_method' => 'GET',
                'client_ip' => '10.10.10.10',
                'ip_hash' => hash('sha256', '10.10.10.10'),
                'region_name' => null,
                'action' => 'block',
                'risk_level' => 'medium',
                'created_at' => now()->subMinutes(1),
            ],
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'sql_injection',
                'rule_name' => 'SQL 注入拦截',
                'request_path' => '/older-high-risk-event',
                'request_method' => 'GET',
                'client_ip' => '10.10.10.11',
                'ip_hash' => hash('sha256', '10.10.10.11'),
                'region_name' => null,
                'action' => 'block',
                'risk_level' => 'high',
                'created_at' => now()->subMinutes(5),
            ],
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $mainSiteId])
            ->get(route('admin.security.index', [
                'security_modal' => 'events',
                'security_event_filter' => 'all',
            ]))
            ->assertOk()
            ->assertSeeInOrder([
                '/older-high-risk-event',
                '/latest-medium-event',
            ], false);
    }

    public function test_site_security_pruning_preserves_high_risk_records_beyond_rate_limit_noise(): void
    {
        $this->seed(DatabaseSeeder::class);

        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('security-prune-remote-site', '远程保留站点');
        $now = now();

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'security.event_retention_limit'],
            ['setting_value' => '20', 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
        );

        DB::table('site_security_events')->insert([
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'sql_injection',
                'rule_name' => 'SQL 注入拦截',
                'request_path' => '/keep-sql',
                'request_method' => 'GET',
                'client_ip' => '8.8.8.8',
                'ip_hash' => hash('sha256', '8.8.8.8'),
                'created_at' => $now->copy()->subMinutes(40),
            ],
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'path_traversal',
                'rule_name' => '路径穿越拦截',
                'request_path' => '/keep-path',
                'request_method' => 'GET',
                'client_ip' => '9.9.9.9',
                'ip_hash' => hash('sha256', '9.9.9.9'),
                'created_at' => $now->copy()->subMinutes(39),
            ],
            [
                'site_id' => $otherSiteId,
                'rule_code' => 'sql_injection',
                'rule_name' => 'SQL 注入拦截',
                'request_path' => '/other-site',
                'request_method' => 'GET',
                'client_ip' => '7.7.7.7',
                'ip_hash' => hash('sha256', '7.7.7.7'),
                'created_at' => $now->copy()->subMinutes(38),
            ],
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'rate_limit',
                'rule_name' => '频繁刷新拦截',
                'request_path' => '/rate-old-outside-window',
                'request_method' => 'GET',
                'client_ip' => '4.4.4.4',
                'ip_hash' => hash('sha256', '4.4.4.4'),
                'created_at' => $now->copy()->subDays(9),
            ],
        ]);

        $rows = [];
        for ($i = 0; $i < 30; $i++) {
            $rows[] = [
                'site_id' => $mainSiteId,
                'rule_code' => 'rate_limit',
                'rule_name' => '频繁刷新拦截',
                'request_path' => '/rate-'.$i,
                'request_method' => 'GET',
                'client_ip' => '6.6.6.'.($i + 1),
                'ip_hash' => hash('sha256', '6.6.6.'.($i + 1)),
                'created_at' => $now->copy()->subMinutes(30 - $i),
            ];
        }

        DB::table('site_security_events')->insert($rows);

        $siteSecurity = app(SiteSecurity::class);

        \Closure::bind(function () use ($mainSiteId): void {
            $this->pruneEvents($mainSiteId);
        }, $siteSecurity, $siteSecurity)();

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $mainSiteId,
            'rule_code' => 'sql_injection',
            'request_path' => '/keep-sql',
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $mainSiteId,
            'rule_code' => 'path_traversal',
            'request_path' => '/keep-path',
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $otherSiteId,
            'rule_code' => 'sql_injection',
            'request_path' => '/other-site',
        ]);

        $this->assertDatabaseMissing('site_security_events', [
            'site_id' => $mainSiteId,
            'rule_code' => 'rate_limit',
            'request_path' => '/rate-old-outside-window',
        ]);
    }

    public function test_site_security_pruning_caps_recent_medium_risk_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'security.event_retention_limit'],
            ['setting_value' => '20', 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
        );

        $rows = [];
        for ($i = 1; $i <= 80; $i++) {
            $ip = '10.30.0.'.($i % 250);
            $rows[] = [
                'site_id' => $mainSiteId,
                'rule_code' => 'bad_path',
                'rule_name' => '恶意扫描路径',
                'request_path' => '/recent-medium-noise-'.$i,
                'request_method' => 'GET',
                'client_ip' => $ip,
                'ip_hash' => hash('sha256', $ip),
                'risk_level' => 'medium',
                'action' => 'block',
                'created_at' => $now->copy()->subMinutes($i),
            ];
        }
        DB::table('site_security_events')->insert($rows);

        app(SiteSecurity::class)->pruneSecurityStorage($mainSiteId);

        $this->assertSame(20, DB::table('site_security_events')
            ->where('site_id', $mainSiteId)
            ->count());
        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $mainSiteId,
            'request_path' => '/recent-medium-noise-80',
        ]);
        $this->assertDatabaseMissing('site_security_events', [
            'site_id' => $mainSiteId,
            'request_path' => '/recent-medium-noise-1',
        ]);
    }

    public function test_site_security_pruning_preserves_recent_probe_abuse_records_within_seven_day_window(): void
    {
        $this->seed(DatabaseSeeder::class);

        $mainSiteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $now = now();

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'security.event_retention_limit'],
            ['setting_value' => '20', 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]
        );

        DB::table('site_security_events')->insert([
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'probe_abuse',
                'rule_name' => '扫描试探超限',
                'request_path' => '/probe-keep',
                'request_method' => 'GET',
                'client_ip' => '8.8.4.4',
                'ip_hash' => hash('sha256', '8.8.4.4'),
                'created_at' => $now->copy()->subDays(3),
            ],
            [
                'site_id' => $mainSiteId,
                'rule_code' => 'probe_abuse',
                'rule_name' => '扫描试探超限',
                'request_path' => '/probe-keep-2',
                'request_method' => 'GET',
                'client_ip' => '8.8.4.5',
                'ip_hash' => hash('sha256', '8.8.4.5'),
                'created_at' => $now->copy()->subDays(2),
            ],
        ]);

        $rows = [];
        for ($i = 0; $i < 40; $i++) {
            $rows[] = [
                'site_id' => $mainSiteId,
                'rule_code' => 'rate_limit',
                'rule_name' => '频繁刷新拦截',
                'request_path' => '/rate-window-'.$i,
                'request_method' => 'GET',
                'client_ip' => '5.5.5.'.($i + 1),
                'ip_hash' => hash('sha256', '5.5.5.'.($i + 1)),
                'created_at' => $now->copy()->subMinutes(40 - $i),
            ];
        }

        DB::table('site_security_events')->insert($rows);

        $siteSecurity = app(SiteSecurity::class);

        \Closure::bind(function () use ($mainSiteId): void {
            $this->pruneEvents($mainSiteId);
        }, $siteSecurity, $siteSecurity)();

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $mainSiteId,
            'rule_code' => 'probe_abuse',
            'request_path' => '/probe-keep',
        ]);

        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $mainSiteId,
            'rule_code' => 'probe_abuse',
            'request_path' => '/probe-keep-2',
        ]);
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
        app(SiteVisitStatsBuffer::class)->flushPending();
        DB::table('site_visit_daily_stats')->where('site_id', $siteId)->delete();

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

        app(SiteVisitStatsBuffer::class)->flushPending();

        $this->assertDatabaseHas('site_visit_daily_stats', [
            'site_id' => $siteId,
            'stat_date' => now('Asia/Shanghai')->toDateString(),
            'page_views' => 2,
            'article_views' => 1,
            'home_views' => 1,
        ]);

        $this->assertSame(1, (int) DB::table('contents')->where('id', $articleId)->value('view_count'));
    }

    public function test_frontend_article_route_hides_draft_pending_rejected_and_deleted_articles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $identity = $this->createPlatformIdentity('frontend-hidden-article-tester');
        $channelId = $this->createSiteChannel($siteId, 'frontend-hidden-articles', '前台隐藏文章栏目', $identity->id);

        $draftId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '前台隐藏草稿文章',
            'status' => 'draft',
            'audit_status' => 'draft',
            'created_by' => $identity->id,
            'updated_by' => $identity->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pendingId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '前台隐藏待审文章',
            'status' => 'pending',
            'audit_status' => 'pending',
            'created_by' => $identity->id,
            'updated_by' => $identity->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rejectedId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '前台隐藏驳回文章',
            'status' => 'rejected',
            'audit_status' => 'rejected',
            'created_by' => $identity->id,
            'updated_by' => $identity->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deletedId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '前台隐藏回收站文章',
            'status' => 'published',
            'audit_status' => 'approved',
            'deleted_at' => now(),
            'published_at' => now(),
            'created_by' => $identity->id,
            'updated_by' => $identity->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$draftId, $pendingId, $rejectedId, $deletedId] as $contentId) {
            $this->get(route('site.article', ['site' => 'site', 'id' => $contentId]))
                ->assertNotFound();
        }
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
                'role_ids' => ['site:'.$foreignRoleId],
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
                'role_id' => 'site:'.$foreignRoleId,
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

    public function test_site_admin_can_update_admin_entry_path_without_current_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('site-setting-entry-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.settings.update'), [
                'name' => '示例学校',
                'filing_number' => '',
                'contact_phone' => '010-12345678',
                'contact_email' => '',
                'address' => '示例地址 1 号',
                'logo' => '',
                'favicon' => '',
                'seo_title' => '示例学校官网',
                'seo_keywords' => '示例学校,校园',
                'seo_description' => '示例学校官网描述',
                'article_requires_review' => '0',
                'article_share_enabled' => '0',
                'attachment_share_enabled' => '0',
                'site_frontend_enabled' => '1',
                'admin_entry_path' => 'school-console-x7k',
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('status', '站点设置已更新。');

        $this->assertSame('school-console-x7k', DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'security.admin_entry_path')
            ->value('setting_value'));
    }

    public function test_admin_entry_path_cache_is_cleared_after_site_setting_update(): void
    {
        $this->seed(DatabaseSeeder::class);
        putenv('CMS_ADMIN_ENTRY_GATE_ENABLED=true');
        $_ENV['CMS_ADMIN_ENTRY_GATE_ENABLED'] = 'true';
        $_SERVER['CMS_ADMIN_ENTRY_GATE_ENABLED'] = 'true';

        try {
            $siteAdmin = $this->createSiteOperator('site-setting-entry-cache-admin', true, 'site_admin');
            $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

            DB::table('site_settings')->updateOrInsert(
                ['site_id' => $siteId, 'setting_key' => 'security.admin_entry_path'],
                [
                    'setting_value' => 'old12',
                    'autoload' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            app(AdminEntryGate::class)->forgetEntryPathForSite($siteId);

            $this->get('/old12')->assertRedirect(route('login'));

            $this->actingAs($siteAdmin)
                ->withSession(['current_site_id' => $siteId])
                ->post(route('admin.settings.update'), [
                    'name' => '示例学校',
                    'filing_number' => '',
                    'contact_phone' => '010-12345678',
                    'contact_email' => '',
                    'address' => '示例地址 1 号',
                    'logo' => '',
                    'favicon' => '',
                    'seo_title' => '示例学校官网',
                    'seo_keywords' => '示例学校,校园',
                    'seo_description' => '示例学校官网描述',
                    'article_requires_review' => '0',
                    'article_share_enabled' => '0',
                    'attachment_share_enabled' => '0',
                    'site_frontend_enabled' => '1',
                    'admin_entry_path' => 'new12',
                ])
                ->assertRedirect(route('admin.settings.index'));

            $this->get('/old12')->assertNotFound();
            $this->get('/new12')->assertRedirect(route('login'));
        } finally {
            putenv('CMS_ADMIN_ENTRY_GATE_ENABLED');
            unset($_ENV['CMS_ADMIN_ENTRY_GATE_ENABLED'], $_SERVER['CMS_ADMIN_ENTRY_GATE_ENABLED']);
        }
    }

    public function test_site_admin_gets_reserved_path_message_for_blocked_admin_entry_path(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('site-setting-entry-reserved-admin', true, 'site_admin');
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
                'logo' => '',
                'favicon' => '',
                'seo_title' => '示例学校官网',
                'seo_keywords' => '示例学校,校园',
                'seo_description' => '示例学校官网描述',
                'article_requires_review' => '0',
                'article_share_enabled' => '0',
                'attachment_share_enabled' => '0',
                'site_frontend_enabled' => '1',
                'admin_entry_path' => 'login',
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors([
                'admin_entry_path' => '后台入口路径不能使用系统保留路径或常见扫描路径。',
            ]);
    }

    public function test_admin_entry_path_can_repeat_across_different_sites(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $otherSiteId = $this->createAdditionalSite('entry-repeat-site', '入口重复测试站点');

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $otherSiteId, 'setting_key' => 'security.admin_entry_path'],
            [
                'setting_value' => 'same12',
                'autoload' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        app(AdminEntryGate::class)->forgetEntryPathForSite($otherSiteId);

        $this->assertNull(app(AdminEntryGate::class)->validateEntryPath('same12', $siteId));
    }

    public function test_admin_entry_path_accepts_five_to_twenty_characters_only(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('site-setting-entry-length-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $payload = [
            'name' => '示例学校',
            'filing_number' => '',
            'contact_phone' => '010-12345678',
            'contact_email' => '',
            'address' => '示例地址 1 号',
            'logo' => '',
            'favicon' => '',
            'seo_title' => '示例学校官网',
            'seo_keywords' => '示例学校,校园',
            'seo_description' => '示例学校官网描述',
            'article_requires_review' => '0',
            'article_share_enabled' => '0',
            'attachment_share_enabled' => '0',
            'site_frontend_enabled' => '1',
        ];

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.settings.update'), $payload + [
                'admin_entry_path' => 'abc12',
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('status', '站点设置已更新。');

        $this->assertSame('abc12', DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'security.admin_entry_path')
            ->value('setting_value'));

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.settings.update'), $payload + [
                'admin_entry_path' => 'abc4',
            ])
            ->assertSessionHasErrors([
                'admin_entry_path' => '后台入口路径需为 5-20 位小写字母、数字或短横线，且不能以短横线开头或结尾。',
            ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.settings.update'), $payload + [
                'admin_entry_path' => 'abcde-12345-fghij201',
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('status', '站点设置已更新。');

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.settings.update'), $payload + [
                'admin_entry_path' => 'abcde-12345-fghij2012',
            ])
            ->assertSessionHasErrors([
                'admin_entry_path' => '后台入口路径需为 5-20 位小写字母、数字或短横线，且不能以短横线开头或结尾。',
            ]);
    }

    public function test_platform_admin_gets_reserved_path_message_when_domains_are_empty(): void
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
                'domains' => '',
                'contact_phone' => '010-12345678',
                'contact_email' => 'school@openai.com',
                'address' => '示例地址 1 号',
                'attachment_storage_limit_mb' => 512,
                'theme_ids' => [],
                'seo_title' => '示例学校官网',
                'seo_keywords' => '示例学校,校园',
                'seo_description' => '示例学校官网描述',
                'opened_at' => now()->format('Y-m-d'),
                'expires_at' => '',
                'remark' => '站点备注',
                'site_admin_ids' => [],
                'admin_entry_path' => 'login',
            ])
            ->assertRedirect(route('admin.platform.sites.edit', $siteId))
            ->assertSessionHasErrors([
                'admin_entry_path' => '后台入口路径不能使用系统保留路径或常见扫描路径。',
            ]);
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
            'code' => 'promo-guard-position',
            'name' => '图宣附件权限测试位',
            'display_mode' => 'multi',
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
            'code' => 'promo-link-guard-position',
            'name' => '图宣跳转地址测试位',
            'display_mode' => 'single',
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

    public function test_floating_promo_item_accepts_wander_animation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $operator = $this->createCustomSiteOperator('promo-floating-wander', $siteId, ['promo.manage']);

        $positionId = (int) DB::table('promo_positions')->insertGetId([
            'site_id' => $siteId,
            'code' => 'promo-floating-wander-position',
            'name' => '页面漂浮图宣位',
            'display_mode' => 'floating',
            'status' => 1,
            'remark' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $attachmentId = $this->createSiteAttachment($siteId, $operator->id, 'promo-floating-wander.jpg');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.promos.items.store', ['position' => $positionId]), [
                'attachment_id' => $attachmentId,
                'title' => '页面漂浮图',
                'subtitle' => '',
                'link_url' => '',
                'link_target' => '_self',
                'status' => '1',
                'floating_position' => 'right-bottom',
                'floating_animation' => 'wander',
                'floating_offset_x' => 24,
                'floating_offset_y' => 24,
                'floating_width' => 180,
                'floating_height' => '',
                'floating_z_index' => 120,
                'floating_show_on' => 'all',
                'floating_closable' => '1',
                'floating_remember_close' => '1',
                'floating_close_expire_hours' => 24,
            ])
            ->assertRedirect();

        $payload = json_decode((string) DB::table('promo_items')
            ->where('position_id', $positionId)
            ->value('display_payload'), true);

        $this->assertSame('wander', $payload['animation'] ?? null);
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

    public function test_restricted_content_operator_can_keep_legacy_up_cover_image_path(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $seedOperator = $this->createPlatformIdentity('content-legacy-cover-seeder');
        $channelId = $this->createSiteChannel($siteId, 'content-legacy-cover-channel', '旧封面栏目', $seedOperator->id);
        $operator = $this->createRestrictedContentOperator('content-legacy-cover-editor', $siteId, [$channelId]);

        $this->setAttachmentSharing($siteId, false, $operator->id);

        $contentId = (int) DB::table('contents')->insertGetId([
            'site_id' => $siteId,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '旧封面路径测试文章',
            'slug' => 'content-legacy-cover-article',
            'summary' => '用于旧封面路径保存校验',
            'cover_image' => '/Up/original.jpg',
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
                'title' => '旧封面路径测试文章',
                'summary' => '用于旧封面路径保存校验',
                'cover_image' => '/Up/original.jpg',
                'content' => '<p>更新正文</p>',
                'author' => 'Restricted Editor',
                'source' => '本站',
                'status' => 'draft',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('contents', [
            'id' => $contentId,
            'cover_image' => '/Up/original.jpg',
            'content' => '<p>更新正文</p>',
        ]);
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
                'service_agreement' => '1',
                'captcha' => $this->loginCaptcha(),
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => '账号已停用，如有疑问请联系站点管理员。',
            ]);

        $this->assertGuest();
    }

    public function test_login_requires_service_agreement_acceptance(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('service-agreement-login-user', true, 'editor');

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'ChangeMe123!',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'service_agreement' => '请先勾选服务协议后再登录。',
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
                'service_agreement' => '1',
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
                'service_agreement' => '1',
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
                    'service_agreement' => '1',
                    'captcha' => $this->loginCaptcha(),
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['username']);
        }

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'bad-password',
                'service_agreement' => '1',
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
                'service_agreement' => '1',
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
                'service_agreement' => '1',
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
                    'service_agreement' => '1',
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
                'service_agreement' => '1',
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
                    'service_agreement' => '1',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['username']);
        }

        $captcha = $this->loginCaptcha();

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'ChangeMe123!',
                'service_agreement' => '1',
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
                    'service_agreement' => '1',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['username']);
        }

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'ChangeMe123!',
                'service_agreement' => '1',
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
                    'service_agreement' => '1',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['username']);
        }

        while (RateLimiter::attempts($throttleKey) < 10) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $operator->username,
                    'password' => 'bad-password',
                    'service_agreement' => '1',
                    'captcha' => $this->loginCaptcha(),
                ])
                ->assertRedirect(route('login'));
        }

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'ChangeMe123!',
                'service_agreement' => '1',
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
                    'service_agreement' => '1',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['username']);
        }

        while (RateLimiter::attempts($throttleKey) < 10) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $operator->username,
                    'password' => 'bad-password',
                    'service_agreement' => '1',
                    'captcha' => $this->loginCaptcha(),
                ])
                ->assertRedirect(route('login'));
        }

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'ChangeMe123!',
                'service_agreement' => '1',
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
                'service_agreement' => '1',
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
                    'service_agreement' => '1',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['username']);
        }

        while (RateLimiter::attempts($throttleKey) < 10) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $operator->username,
                    'password' => 'bad-password',
                    'service_agreement' => '1',
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
                'service_agreement' => '1',
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
                    'service_agreement' => '1',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['username']);
        }

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $operator->username,
                'password' => 'ChangeMe123!',
                'service_agreement' => '1',
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
                'service_agreement' => '1',
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
                'service_agreement' => '1',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => '用户名或密码不正确。 再输错 9 次后将限制登录5分钟。',
            ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $secondOperator->username,
                'password' => 'bad-password',
                'service_agreement' => '1',
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
                    'service_agreement' => '1',
                ])
                ->assertRedirect(route('login'));
        }

        while (RateLimiter::attempts($firstThrottleKey) < 10) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $firstOperator->username,
                    'password' => 'bad-password',
                    'service_agreement' => '1',
                    'captcha' => $this->loginCaptcha(),
                ])
                ->assertRedirect(route('login'));
        }

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $secondOperator->username,
                'password' => 'bad-password',
                'service_agreement' => '1',
                'captcha' => $this->loginCaptcha(),
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => '用户名或密码不正确。 再输错 9 次后将限制登录5分钟。',
            ]);
    }

    public function test_platform_admin_login_uses_site_dashboard_on_site_domain_and_platform_dashboard_on_main_site(): void
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
                'service_agreement' => '1',
            ])
            ->assertRedirect('http://remote-login.test/login')
            ->assertSessionHasErrors([
                'username' => '当前账号无权登录该站点，请使用对应站点域名登录。',
            ]);

        $this->from('http://remote-login.test/login')
            ->post('http://remote-login.test/login', [
                'username' => $remoteOperator->username,
                'password' => 'ChangeMe123!',
                'service_agreement' => '1',
            ])
            ->assertRedirect(route('admin.site-dashboard'));

        Auth::logout();
        session()->flush();

        $this->from('http://remote-login.test/login')
            ->post('http://remote-login.test/login', [
                'username' => 'superadmin',
                'password' => 'ChangeMe123!',
                'service_agreement' => '1',
            ])
            ->assertRedirect(route('admin.site-dashboard'));

        $this->assertSame($remoteSiteId, (int) session('current_site_id'));

        $this->get('http://remote-login.test/admin')
            ->assertRedirect(route('admin.site-dashboard'));

        Auth::logout();
        session()->flush();

        $this->from('http://site.local/login')
            ->post('http://site.local/login', [
                'username' => 'superadmin',
                'password' => 'ChangeMe123!',
                'service_agreement' => '1',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->get('http://site.local/admin')
            ->assertOk();
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
            $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-captcha')->post(route('login.captcha.check'), ['captcha' => 'ABCD'])
                ->assertOk();
        }

        $this->withCookie(SiteSecurity::DEVICE_COOKIE_NAME, 'device-captcha')->post(route('login.captcha.check'), ['captcha' => 'ABCD'])
            ->assertForbidden();
    }

    public function test_frontend_page_rate_limit_counts_across_page_paths(): void
    {
        $this->seed(DatabaseSeeder::class);
        RateLimiter::clear($this->siteSecuritySiteWideRateKey());
        RateLimiter::clear($this->siteSecurityRateKeyForPath('/'));
        RateLimiter::clear($this->siteSecurityRateKeyForPath('/article/1'));
        RateLimiter::clear($this->siteSecurityRateKeyForPath('/cat/demo'));

        for ($i = 0; $i < 15; $i++) {
            $this->assertNotSame(403, $this->get('/?site=site')->getStatusCode());
            $this->assertNotSame(403, $this->get('/article/1?site=site')->getStatusCode());
        }

        $this->get('/cat/demo?site=site')->assertForbidden();
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
                'role_id' => 'platform:'.$platformRoleId,
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
                'role_id' => 'site:'.$uploaderRoleId,
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

    public function test_site_user_manageable_channels_follow_current_channel_tree_order(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('channel-order-site-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('channels')->where('site_id', $siteId)->delete();

        $firstTopId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => null,
            'name' => '第一栏目',
            'slug' => 'first-channel',
            'type' => 'list',
            'path' => '/first-channel',
            'depth' => 0,
            'sort' => 2,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $siteAdmin->id,
            'updated_by' => $siteAdmin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondTopId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            'parent_id' => null,
            'name' => '第二栏目',
            'slug' => 'second-channel',
            'type' => 'list',
            'path' => '/second-channel',
            'depth' => 0,
            'sort' => 1,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $siteAdmin->id,
            'updated_by' => $siteAdmin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('channels')->insert([
            [
                'site_id' => $siteId,
                'parent_id' => $firstTopId,
                'name' => '第一栏目-子二',
                'slug' => 'first-child-b',
                'type' => 'list',
                'path' => '/first-child-b',
                'depth' => 1,
                'sort' => 2,
                'status' => 1,
                'is_nav' => 1,
                'created_by' => $siteAdmin->id,
                'updated_by' => $siteAdmin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $siteId,
                'parent_id' => $firstTopId,
                'name' => '第一栏目-子一',
                'slug' => 'first-child-a',
                'type' => 'list',
                'path' => '/first-child-a',
                'depth' => 1,
                'sort' => 1,
                'status' => 1,
                'is_nav' => 1,
                'created_by' => $siteAdmin->id,
                'updated_by' => $siteAdmin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $siteId,
                'parent_id' => $secondTopId,
                'name' => '第二栏目-子一',
                'slug' => 'second-child-a',
                'type' => 'list',
                'path' => '/second-child-a',
                'depth' => 1,
                'sort' => 1,
                'status' => 1,
                'is_nav' => 1,
                'created_by' => $siteAdmin->id,
                'updated_by' => $siteAdmin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-users.create'))
            ->assertOk();

        $content = $response->getContent();

        $secondTopPos = strpos($content, '第二栏目');
        $secondChildPos = strpos($content, '第二栏目-子一');
        $firstTopPos = strpos($content, '第一栏目');
        $firstChildFirstPos = strpos($content, '第一栏目-子一');
        $firstChildSecondPos = strpos($content, '第一栏目-子二');

        $this->assertNotFalse($secondTopPos);
        $this->assertNotFalse($secondChildPos);
        $this->assertNotFalse($firstTopPos);
        $this->assertNotFalse($firstChildFirstPos);
        $this->assertNotFalse($firstChildSecondPos);

        $this->assertTrue($secondTopPos < $secondChildPos);
        $this->assertTrue($secondChildPos < $firstTopPos);
        $this->assertTrue($firstTopPos < $firstChildFirstPos);
        $this->assertTrue($firstChildFirstPos < $firstChildSecondPos);
    }

    public function test_regular_operator_editing_own_profile_does_not_see_return_to_operator_management(): void
    {
        $this->seed(DatabaseSeeder::class);

        $operator = $this->createSiteOperator('self-profile-operator', true, 'editor');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-users.edit', $operator->id))
            ->assertOk()
            ->assertDontSee('返回操作员管理');
    }

    public function test_site_role_management_requires_dedicated_permission(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $operator = $this->createCustomSiteOperator('site-user-only-manager', $siteId, ['site.user.manage']);

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-users.index'))
            ->assertOk();

        $this->actingAs($operator)
            ->withSession(['current_site_id' => $siteId])
            ->get(route('admin.site-roles.index'))
            ->assertForbidden();
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
                'role_id' => 'platform:'.$platformRoleId,
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

    public function test_site_security_site_admin_can_delete_single_event_record_and_release_runtime_block(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-event-delete-site-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $ip = '8.8.8.8';

        $eventId = (int) DB::table('site_security_events')->insertGetId([
            'site_id' => $siteId,
            'rule_code' => 'probe_abuse',
            'rule_name' => '扫描试探超限',
            'request_path' => '/scan-test',
            'request_method' => 'GET',
            'client_ip' => $ip,
            'ip_hash' => hash('sha256', $ip),
            'risk_level' => 'critical',
            'action' => 'temporary_block',
            'created_at' => now(),
        ]);

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $siteId,
            'client_ip' => $ip,
            'ip_hash' => hash('sha256', $ip),
            'hit_count' => 1,
            'high_risk_count' => 1,
            'last_rule_code' => 'probe_abuse',
            'last_request_path' => '/scan-test',
            'status' => 'blocked',
            'blocked_until' => now()->addMinutes(10),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        RateLimiter::hit('site-security-probe-block:'.$siteId.':'.sha1($ip), 60);
        RateLimiter::hit('site-security-reputation-block:'.$siteId.':'.sha1($ip), 60);
        RateLimiter::hit('site-security-probe:'.$siteId.':probe_abuse:'.sha1($ip), 60);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.security.events.delete'), [
                'event_id' => $eventId,
                'security_event_filter' => 'all',
                'security_event_page' => 1,
            ])
            ->assertRedirect(route('admin.security.index', [
                'security_modal' => 'events',
                'security_event_filter' => 'all',
                'security_event_page' => 1,
            ]))
            ->assertSessionHas('status', '已删除该条拦截记录，并同步清理对应自动封禁状态。');

        $this->assertDatabaseMissing('site_security_events', ['id' => $eventId]);
        $this->assertDatabaseMissing('site_security_ip_reputations', [
            'site_id' => $siteId,
            'client_ip' => $ip,
        ]);
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-probe-block:'.$siteId.':'.sha1($ip), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-reputation-block:'.$siteId.':'.sha1($ip), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-probe:'.$siteId.':probe_abuse:'.sha1($ip), 1));
    }

    public function test_site_security_site_admin_can_clear_filtered_event_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-event-clear-site-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');

        DB::table('site_security_events')->insert([
            [
                'site_id' => $siteId,
                'rule_code' => 'sql_injection',
                'rule_name' => 'SQL 注入拦截',
                'request_path' => '/delete-high',
                'request_method' => 'GET',
                'client_ip' => '8.8.8.8',
                'ip_hash' => hash('sha256', '8.8.8.8'),
                'risk_level' => 'high',
                'action' => 'block',
                'created_at' => now(),
            ],
            [
                'site_id' => $siteId,
                'rule_code' => 'bad_path',
                'rule_name' => '恶意扫描路径',
                'request_path' => '/keep-medium',
                'request_method' => 'GET',
                'client_ip' => '8.8.4.4',
                'ip_hash' => hash('sha256', '8.8.4.4'),
                'risk_level' => 'medium',
                'action' => 'block',
                'created_at' => now(),
            ],
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.security.events.clear'), [
                'security_event_filter' => 'high',
            ])
            ->assertRedirect(route('admin.security.index', [
                'security_modal' => 'events',
                'security_event_filter' => 'high',
                'security_event_page' => 1,
            ]));

        $this->assertDatabaseMissing('site_security_events', [
            'site_id' => $siteId,
            'request_path' => '/delete-high',
        ]);
        $this->assertDatabaseHas('site_security_events', [
            'site_id' => $siteId,
            'request_path' => '/keep-medium',
        ]);
    }

    public function test_site_security_site_admin_can_delete_single_suspicious_ip_record(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-delete-site-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $ip = '9.9.9.9';

        DB::table('site_security_events')->insert([
            'site_id' => $siteId,
            'rule_code' => 'bad_client',
            'rule_name' => '脚本扫描器拦截',
            'request_path' => '/ua-test',
            'request_method' => 'GET',
            'client_ip' => $ip,
            'ip_hash' => hash('sha256', $ip),
            'risk_level' => 'high',
            'action' => 'block',
            'created_at' => now(),
        ]);

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $siteId,
            'client_ip' => $ip,
            'ip_hash' => hash('sha256', $ip),
            'hit_count' => 1,
            'high_risk_count' => 1,
            'last_rule_code' => 'bad_client',
            'last_request_path' => '/ua-test',
            'status' => 'monitored',
            'blocked_until' => null,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        RateLimiter::hit('site-security-rate-block:'.$siteId.':'.sha1($ip), 60);
        RateLimiter::hit('site-security-reputation-block:'.$siteId.':'.sha1($ip), 60);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.security.ips.delete'), [
                'client_ip' => $ip,
                'security_ip_page' => 1,
            ])
            ->assertRedirect(route('admin.security.index', [
                'security_modal' => 'ips',
                'security_ip_page' => 1,
            ]))
            ->assertSessionHas('status', '已清除该 IP 的自动记录，并解除对应自动临时限制。');

        $this->assertDatabaseMissing('site_security_ip_reputations', [
            'site_id' => $siteId,
            'client_ip' => $ip,
        ]);
        $this->assertDatabaseMissing('site_security_events', [
            'site_id' => $siteId,
            'client_ip' => $ip,
        ]);
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-rate-block:'.$siteId.':'.sha1($ip), 1));
        $this->assertFalse(RateLimiter::tooManyAttempts('site-security-reputation-block:'.$siteId.':'.sha1($ip), 1));
    }

    public function test_site_security_clear_suspicious_ips_deletes_hash_only_events(): void
    {
        $this->seed(DatabaseSeeder::class);

        $siteAdmin = $this->createSiteOperator('security-ip-clear-hash-site-admin', true, 'site_admin');
        $siteId = (int) DB::table('sites')->where('site_key', 'site')->value('id');
        $ipHash = hash('sha256', '203.0.113.200');

        DB::table('site_security_events')->insert([
            'site_id' => $siteId,
            'rule_code' => 'bad_client',
            'rule_name' => '脚本扫描器拦截',
            'request_path' => '/hash-only-event',
            'request_method' => 'GET',
            'client_ip' => null,
            'ip_hash' => $ipHash,
            'risk_level' => 'high',
            'action' => 'block',
            'created_at' => now(),
        ]);

        DB::table('site_security_ip_reputations')->insert([
            'site_id' => $siteId,
            'client_ip' => null,
            'ip_hash' => $ipHash,
            'hit_count' => 1,
            'high_risk_count' => 1,
            'last_rule_code' => 'bad_client',
            'last_request_path' => '/hash-only-event',
            'status' => 'monitored',
            'blocked_until' => null,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($siteAdmin)
            ->withSession(['current_site_id' => $siteId])
            ->post(route('admin.security.ips.clear'))
            ->assertRedirect(route('admin.security.index', [
                'security_modal' => 'ips',
                'security_ip_page' => 1,
            ]));

        $this->assertDatabaseMissing('site_security_events', [
            'site_id' => $siteId,
            'request_path' => '/hash-only-event',
        ]);
        $this->assertDatabaseMissing('site_security_ip_reputations', [
            'site_id' => $siteId,
            'ip_hash' => $ipHash,
        ]);
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
        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $columns) {
            foreach ($columns as $columnIndex => $value) {
                $worksheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 1), $value);
            }
        }

        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $isXls = $extension === 'xls';
        $path = storage_path('app/testing-'.uniqid('payroll-', true).'.'.($isXls ? 'xls' : 'xlsx'));
        File::ensureDirectoryExists(dirname($path));

        $writer = $isXls ? new Xls($spreadsheet) : new Xlsx($spreadsheet);
        $writer->save($path);
        $this->temporaryPayrollUploads[] = $path;

        return new UploadedFile(
            $path,
            $filename,
            $isXls ? 'application/vnd.ms-excel' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    protected function createPayrollCsvUpload(array $rows, string $filename = 'payroll.csv'): UploadedFile
    {
        $path = storage_path('app/testing-'.uniqid('payroll-csv-', true).'.csv');
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'wb');
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
        $this->temporaryPayrollUploads[] = $path;

        return new UploadedFile(
            $path,
            $filename,
            'text/csv',
            null,
            true
        );
    }

    protected function writeLegacyImportSpreadsheet(string $path, array $rows): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);

        File::ensureDirectoryExists(dirname($path));

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $spreadsheet->disconnectWorksheets();
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
