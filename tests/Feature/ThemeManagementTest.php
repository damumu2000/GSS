<?php

namespace Tests\Feature;

use App\Support\ThemeTags;
use App\Support\ThemeTemplateEngine;
use App\Models\User;
use App\Support\Site as SitePath;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ThemeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function createEditableTemplateWorkspace(object $site, int $userId): string
    {
        $templateKey = 'test-workbench';

        $siteTemplateId = (int) DB::table('site_templates')->insertGetId([
            'site_id' => $site->id,
            'name' => '测试工作模板',
            'template_key' => $templateKey,
            'status' => 1,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateRoot = SitePath::siteTemplateRoot($site->site_key, $templateKey);
        $legacyTemplateRoot = storage_path('app/theme_templates');

        File::ensureDirectoryExists(dirname($templateRoot));

        if (File::isDirectory($templateRoot)) {
            File::deleteDirectory($templateRoot);
        }

        File::copyDirectory($legacyTemplateRoot, $templateRoot);

        DB::table('sites')->where('id', $site->id)->update([
            'active_site_template_id' => $siteTemplateId,
        ]);

        return $templateKey;
    }

    protected function superAdmin(): User
    {
        return User::query()->findOrFail((int) config('cms.super_admin_user_id', 1));
    }

    protected function demoSite(): object
    {
        return DB::table('sites')->where('site_key', 'site')->firstOrFail();
    }

    protected function activeTemplateKey(object $site): string
    {
        $templateKey = (string) DB::table('site_templates')
            ->where('site_id', $site->id)
            ->where('id', (int) $site->active_site_template_id)
            ->value('template_key');

        return $templateKey;
    }

    protected function activeTemplateId(object $site): int
    {
        return (int) DB::table('sites')
            ->where('id', $site->id)
            ->value('active_site_template_id');
    }

    public function test_seeded_demo_site_has_active_default_template(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        $this->assertNotNull($site->active_site_template_id);
        $this->assertDatabaseHas('site_templates', [
            'site_id' => $site->id,
            'id' => $site->active_site_template_id,
            'template_key' => 'default',
        ]);
    }

    public function test_frontend_shows_missing_template_page_when_site_has_no_active_template(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        DB::table('sites')->where('id', $site->id)->update(['active_site_template_id' => null]);

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertStatus(503)
            ->assertSee('当前站点暂未启用可用模板');
    }

    public function test_theme_editor_redirects_back_when_no_template_is_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        DB::table('sites')->where('id', $site->id)->update(['active_site_template_id' => null]);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.themes.editor'))
            ->assertRedirect(route('admin.themes.index'));
    }

    public function test_theme_editor_lists_template_directory_files_from_active_template(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.themes.editor'))
            ->assertOk()
            ->assertSee('list.css')
            ->assertSee('list.js')
            ->assertSee('foot.tpl');
    }

    public function test_site_can_create_update_delete_and_rollback_custom_template(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->createEditableTemplateWorkspace($site, $this->superAdmin()->id);
        $activeTemplateId = $this->activeTemplateId($site);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-create'), [
                'template_prefix' => 'list',
                'template_suffix' => 'campus',
                'template_title' => '校园列表',
                'template_source' => '<div>campus list</div>',
            ])
            ->assertRedirect(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'list-campus']));

        $this->assertDatabaseHas('site_template_meta', [
            'site_template_id' => DB::table('site_templates')->where('site_id', $site->id)->where('template_key', $themeCode)->value('id'),
            'template_name' => 'list-campus',
            'title' => '校园列表',
        ]);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.update'), [
                'template' => 'home',
                'template_title' => '首页模板',
                'template_source' => '<div>version one</div>',
            ])
            ->assertRedirect(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'home']));

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.update'), [
                'template' => 'home',
                'template_title' => '首页模板',
                'template_source' => '<div>version two</div>',
            ])
            ->assertRedirect(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'home']));

        $latestSnapshotId = (int) DB::table('site_template_versions')
            ->where('site_template_id', DB::table('site_templates')->where('site_id', $site->id)->where('template_key', $themeCode)->value('id'))
            ->where('template_name', 'home')
            ->orderByDesc('id')
            ->value('id');

        $this->assertTrue($latestSnapshotId > 0);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-snapshot-favorite'), [
                'template' => 'home',
                'version_id' => $latestSnapshotId,
            ])
            ->assertRedirect(route('admin.themes.snapshots', ['site_template_id' => $activeTemplateId, 'template' => 'home']));

        $this->assertDatabaseHas('site_template_versions', [
            'id' => $latestSnapshotId,
            'is_favorite' => 1,
        ]);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-rollback'), [
                'template' => 'home',
                'version_id' => $latestSnapshotId,
            ])
            ->assertRedirect(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'home']));

        $homePath = SitePath::siteTemplateRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';
        $this->assertStringContainsString('version one', File::get($homePath));

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-delete'), [
                'template' => 'list-campus',
            ])
            ->assertRedirect(route('admin.themes.editor', ['site_template_id' => $activeTemplateId]));

        $this->assertDatabaseMissing('site_template_meta', [
            'site_template_id' => DB::table('site_templates')->where('site_id', $site->id)->where('template_key', $themeCode)->value('id'),
            'template_name' => 'list-campus',
        ]);
    }

    public function test_theme_editor_rejects_php_and_inline_script_markup(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $activeTemplateId = $this->activeTemplateId($site);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->from(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'home']))
            ->post(route('admin.themes.editor.update'), [
                'template' => 'home',
                'template_title' => '首页模板',
                'template_source' => '<?php echo 1; ?>',
            ])
            ->assertRedirect(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'home']))
            ->assertSessionHasErrors('template_source');

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->from(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'home']))
            ->post(route('admin.themes.editor.update'), [
                'template' => 'home',
                'template_title' => '首页模板',
                'template_source' => '<script>alert(1)</script>',
            ])
            ->assertRedirect(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'home']))
            ->assertSessionHasErrors('template_source');
    }

    public function test_theme_asset_route_serves_assets_from_active_template(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeTemplateKey($site);

        $response = $this->withServerVariables(['HTTP_HOST' => '127.0.0.1'])
            ->get(route('site.theme-asset', [
                'theme' => $themeCode,
                'path' => 'list.css',
                'site' => $site->site_key,
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/css; charset=utf-8');
    }

    public function test_theme_asset_route_serves_assets_by_bound_domain_without_site_query(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeTemplateKey($site);

        DB::table('site_domains')->insert([
            'site_id' => $site->id,
            'domain' => 'site.test',
            'status' => 1,
            'is_primary' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get('http://site.test'.route('site.theme-asset', [
            'theme' => $themeCode,
            'path' => 'list.css',
        ], false));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/css; charset=utf-8');
    }

    public function test_theme_asset_directive_omits_site_query_on_bound_domain(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeTemplateKey($site);
        $engine = new ThemeTemplateEngine($site->site_key, $themeCode, new ThemeTags($site, collect(), collect()));
        $method = new \ReflectionMethod($engine, 'resolveThemeAssetDirective');
        $method->setAccessible(true);

        app()->instance('request', Request::create('http://127.0.0.1/theme-assets/'.$themeCode.'/list.css', 'GET'));
        $localUrl = $method->invoke($engine, 'list.css');

        app()->instance('request', Request::create('https://site.test/theme-assets/'.$themeCode.'/list.css', 'GET', [], [], [], ['HTTP_HOST' => 'site.test', 'HTTPS' => 'on']));
        $domainUrl = $method->invoke($engine, 'list.css');

        $this->assertStringContainsString('site='.$site->site_key, $localUrl);
        $this->assertStringNotContainsString('site='.$site->site_key, $domainUrl);
    }

    public function test_detail_template_can_render_current_content_html_payload(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->createEditableTemplateWorkspace($site, $this->superAdmin()->id);
        $templateRoot = SitePath::siteTemplateRoot($site->site_key, $themeCode);

        File::put(
            $templateRoot.DIRECTORY_SEPARATOR.'detail.tpl',
            "{{ current.content.title }}|{{{ current.content.content_html }}}|{{ current.content.author }}|{{ current.content.published_at | formatDate('Y-m-d', '--') }}"
        );

        $channelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $site->id,
            'name' => '文档测试栏目',
            'slug' => 'docs-current-content',
            'type' => 'list',
            'status' => 1,
            'is_nav' => 1,
            'detail_template' => 'detail',
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $articleId = (int) DB::table('contents')->insertGetId([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '当前内容测试',
            'summary' => '测试摘要',
            'content' => '<p><strong>正文内容</strong></p>',
            'author' => '测试作者',
            'status' => 'published',
            'audit_status' => 'approved',
            'published_at' => '2026-05-15 09:30:00',
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('content_channels')->insert([
            'content_id' => $articleId,
            'channel_id' => $channelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('site.article', ['id' => $articleId, 'site' => $site->site_key]))
            ->assertOk()
            ->assertSee('当前内容测试')
            ->assertSee('<strong>正文内容</strong>', false)
            ->assertSee('测试作者')
            ->assertSee('2026-05-15');
    }

    public function test_mobile_template_is_used_when_available_and_falls_back_when_missing(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->createEditableTemplateWorkspace($site, $this->superAdmin()->id);
        $templateRoot = SitePath::siteTemplateRoot($site->site_key, $themeCode);

        File::put(
            $templateRoot.DIRECTORY_SEPARATOR.'home.tpl',
            'PC {{ current.page.template_name }} {{ current.page.device }} {{ current.page.is_mobile }} {{ siteValue(key=\'home_url\') }}'
        );
        File::put(
            $templateRoot.DIRECTORY_SEPARATOR.'m-home.tpl',
            'MOBILE {{ current.page.template_name }} {{ current.page.device }} {{ current.page.is_mobile }} {{ siteValue(key=\'home_url\') }}'
        );

        $this->get(route('site.home', ['site' => $site->site_key, 'device' => 'mobile']))
            ->assertOk()
            ->assertSee('MOBILE m-home mobile 1')
            ->assertSee('device=mobile', false);

        $this->get(route('site.home', ['site' => $site->site_key, 'device' => 'pc']))
            ->assertOk()
            ->assertSee('PC home pc')
            ->assertDontSee('MOBILE');

        File::delete($templateRoot.DIRECTORY_SEPARATOR.'m-home.tpl');

        $this->get(route('site.home', ['site' => $site->site_key, 'device' => 'mobile']))
            ->assertOk()
            ->assertSee('PC home mobile 1')
            ->assertDontSee('MOBILE');
    }

    public function test_site_can_upload_and_delete_template_assets(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $activeTemplateId = $this->activeTemplateId($site);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.asset-upload'), [
                'template' => 'home',
                'asset' => UploadedFile::fake()->image('hero-banner.png', 1200, 800),
            ])
            ->assertRedirect(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'home', 'open_assets' => 1]));

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'home', 'open_assets' => 1]))
            ->assertOk()
            ->assertSee('hero-banner.png');

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.asset-delete'), [
                'template' => 'home',
                'asset_path' => 'assets/hero-banner.png',
            ])
            ->assertRedirect(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'home', 'open_assets' => 1]));
    }
}
