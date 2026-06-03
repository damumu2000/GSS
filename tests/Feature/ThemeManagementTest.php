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

    public function test_theme_editor_rejects_save_when_site_context_changed_after_page_open(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->createEditableTemplateWorkspace($site, $this->superAdmin()->id);
        $activeTemplateId = $this->activeTemplateId($site);

        $otherSiteId = (int) DB::table('sites')->insertGetId([
            'name' => '第二测试站点',
            'site_key' => 'theme-context-switch-site',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherSiteTemplateId = (int) DB::table('site_templates')->insertGetId([
            'site_id' => $otherSiteId,
            'name' => '第二测试模板',
            'template_key' => 'other-theme',
            'status' => 1,
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sites')->where('id', $otherSiteId)->update([
            'active_site_template_id' => $otherSiteTemplateId,
        ]);

        $homePath = SitePath::siteTemplateRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';
        $before = File::exists($homePath) ? File::get($homePath) : null;

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $otherSiteId])
            ->from(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'home']))
            ->post(route('admin.themes.editor.update'), [
                'editor_site_id' => $site->id,
                'site_template_id' => $activeTemplateId,
                'template' => 'home',
                'template_title' => '首页模板',
                'template_source' => '<div>stale save should fail</div>',
            ])
            ->assertRedirect(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'home']))
            ->assertSessionHasErrors('theme');

        $after = File::exists($homePath) ? File::get($homePath) : null;
        $this->assertSame($before, $after);
    }

    public function test_theme_snapshots_compare_view_opens_successfully(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $this->createEditableTemplateWorkspace($site, $this->superAdmin()->id);
        $activeTemplateId = $this->activeTemplateId($site);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.update'), [
                'editor_site_id' => $site->id,
                'site_template_id' => $activeTemplateId,
                'template' => 'home',
                'template_title' => '首页模板',
                'template_source' => '<div>compare version</div>',
            ])
            ->assertRedirect();

        $latestSnapshotId = (int) DB::table('site_template_versions')
            ->where('site_template_id', $activeTemplateId)
            ->where('template_name', 'home')
            ->orderByDesc('id')
            ->value('id');

        $this->assertTrue($latestSnapshotId > 0);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.themes.snapshots', [
                'site_template_id' => $activeTemplateId,
                'template' => 'home',
                'version' => $latestSnapshotId,
            ]))
            ->assertOk()
            ->assertSee('模板对比');
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

    public function test_theme_asset_route_can_serve_non_active_theme_static_asset_for_same_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $templateRoot = SitePath::siteTemplateRoot($site->site_key, 'seasonal');

        File::ensureDirectoryExists($templateRoot.DIRECTORY_SEPARATOR.'assets');
        File::put($templateRoot.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'special.json', '{"theme":"seasonal"}');

        $response = $this->get(route('site.theme-asset', [
            'theme' => 'seasonal',
            'path' => 'assets/special.json',
            'site' => $site->site_key,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function test_theme_asset_route_rejects_template_source_and_hidden_or_traversal_paths(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeTemplateKey($site);
        $templateRoot = SitePath::siteTemplateRoot($site->site_key, $themeCode);

        File::ensureDirectoryExists($templateRoot.DIRECTORY_SEPARATOR.'assets');
        File::put($templateRoot.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'.hidden.css', 'body{display:none;}');

        $this->get(route('site.theme-asset', [
            'theme' => $themeCode,
            'path' => 'home.tpl',
            'site' => $site->site_key,
        ]))->assertNotFound();

        $this->get(route('site.theme-asset', [
            'theme' => $themeCode,
            'path' => 'assets/.hidden.css',
            'site' => $site->site_key,
        ]))->assertNotFound();

        $this->get(route('site.theme-asset', [
            'theme' => $themeCode,
            'path' => '../list.css',
            'site' => $site->site_key,
        ]))->assertStatus(403);
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

    public function test_page_channel_uses_highest_sorted_page_as_default_content(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->createEditableTemplateWorkspace($site, $this->superAdmin()->id);
        $templateRoot = SitePath::siteTemplateRoot($site->site_key, $themeCode);

        File::put(
            $templateRoot.DIRECTORY_SEPARATOR.'page.tpl',
            '<!DOCTYPE html><html><head><title>Page</title></head><body>{{ current.content.title }}</body></html>'
        );

        $channelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $site->id,
            'name' => '单页排序栏目',
            'slug' => 'sorted-page-channel',
            'type' => 'page',
            'status' => 1,
            'is_nav' => 1,
            'detail_template' => 'page',
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $olderHighSortPageId = (int) DB::table('contents')->insertGetId([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'page',
            'title' => '后台排序第一的单页',
            'content' => '<p>排序第一</p>',
            'status' => 'published',
            'audit_status' => 'approved',
            'sort' => 90,
            'published_at' => now()->subDays(2),
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $newerLowSortPageId = (int) DB::table('contents')->insertGetId([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'page',
            'title' => '更新时间更新但排序靠后的单页',
            'content' => '<p>排序靠后</p>',
            'status' => 'published',
            'audit_status' => 'approved',
            'sort' => 10,
            'published_at' => now(),
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('content_channels')->insert([
            [
                'content_id' => $olderHighSortPageId,
                'channel_id' => $channelId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'content_id' => $newerLowSortPageId,
                'channel_id' => $channelId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->get(route('site.channel', ['slug' => 'sorted-page-channel', 'site' => $site->site_key]))
            ->assertOk()
            ->assertSee('后台排序第一的单页')
            ->assertDontSee('更新时间更新但排序靠后的单页');
    }

    public function test_page_channel_default_content_respects_top_priority(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->createEditableTemplateWorkspace($site, $this->superAdmin()->id);
        $templateRoot = SitePath::siteTemplateRoot($site->site_key, $themeCode);

        File::put(
            $templateRoot.DIRECTORY_SEPARATOR.'page.tpl',
            '<!DOCTYPE html><html><head><title>Page</title></head><body>{{ current.content.title }}</body></html>'
        );

        $channelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $site->id,
            'name' => '置顶单页栏目',
            'slug' => 'top-page-channel',
            'type' => 'page',
            'status' => 1,
            'is_nav' => 1,
            'detail_template' => 'page',
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $topLowSortPageId = (int) DB::table('contents')->insertGetId([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'page',
            'title' => '置顶单页优先显示',
            'content' => '<p>置顶</p>',
            'status' => 'published',
            'audit_status' => 'approved',
            'is_top' => 1,
            'sort' => 10,
            'published_at' => now()->subDays(2),
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $normalHighSortPageId = (int) DB::table('contents')->insertGetId([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'page',
            'title' => '普通高排序单页不优先',
            'content' => '<p>高排序</p>',
            'status' => 'published',
            'audit_status' => 'approved',
            'is_top' => 0,
            'sort' => 90,
            'published_at' => now(),
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('content_channels')->insert([
            [
                'content_id' => $topLowSortPageId,
                'channel_id' => $channelId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'content_id' => $normalHighSortPageId,
                'channel_id' => $channelId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->get(route('site.channel', ['slug' => 'top-page-channel', 'site' => $site->site_key]))
            ->assertOk()
            ->assertSee('置顶单页优先显示')
            ->assertDontSee('普通高排序单页不优先');
    }

    public function test_admin_and_frontend_article_lists_share_content_priority_order(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $channelId = (int) DB::table('channels')
            ->where('site_id', $site->id)
            ->where('type', 'list')
            ->orderBy('id')
            ->value('id');

        DB::table('contents')->where('site_id', $site->id)->where('type', 'article')->delete();

        $normalHighSortId = (int) DB::table('contents')->insertGetId([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '非置顶高排序',
            'status' => 'published',
            'audit_status' => 'approved',
            'is_top' => 0,
            'sort' => 100,
            'published_at' => now(),
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $topLowSortId = (int) DB::table('contents')->insertGetId([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '置顶低排序',
            'status' => 'published',
            'audit_status' => 'approved',
            'is_top' => 1,
            'sort' => 1,
            'published_at' => now()->subDay(),
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $topHighSortId = (int) DB::table('contents')->insertGetId([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '置顶高排序',
            'status' => 'published',
            'audit_status' => 'approved',
            'is_top' => 1,
            'sort' => 2,
            'published_at' => now()->subDays(2),
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $expectedIds = [$topHighSortId, $topLowSortId, $normalHighSortId];

        $adminOrderedIds = DB::table('contents')
            ->where('site_id', $site->id)
            ->where('type', 'article')
            ->whereNull('deleted_at')
            ->orderByDesc('is_top')
            ->orderByDesc('sort')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $frontendOrderedIds = (new ThemeTags($site, collect(), collect()))
            ->contentList(['site_wide' => true, 'limit' => 3])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame($expectedIds, $adminOrderedIds);
        $this->assertSame($expectedIds, $frontendOrderedIds);
    }

    public function test_article_reorder_rejects_crossing_top_and_normal_groups(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $channelId = (int) DB::table('channels')
            ->where('site_id', $site->id)
            ->where('type', 'list')
            ->orderBy('id')
            ->value('id');

        DB::table('contents')->where('site_id', $site->id)->where('type', 'article')->delete();

        $topId = (int) DB::table('contents')->insertGetId([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '置顶文章',
            'status' => 'published',
            'audit_status' => 'approved',
            'is_top' => 1,
            'sort' => 10,
            'published_at' => now()->subDay(),
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $normalId = (int) DB::table('contents')->insertGetId([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '普通文章',
            'status' => 'published',
            'audit_status' => 'approved',
            'is_top' => 0,
            'sort' => 99,
            'published_at' => now(),
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->postJson(route('admin.articles.reorder'), ['ordered_ids' => [$normalId, $topId]])
            ->assertStatus(422)
            ->assertJsonPath('message', '排序保存失败：置顶内容和普通内容不能互相拖动，请先调整置顶状态后再排序。');
    }

    public function test_article_reorder_persists_when_existing_sort_values_are_zero(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $channelId = (int) DB::table('channels')
            ->where('site_id', $site->id)
            ->where('type', 'list')
            ->orderBy('id')
            ->value('id');

        DB::table('contents')->where('site_id', $site->id)->where('type', 'article')->delete();

        $oldestId = (int) DB::table('contents')->insertGetId([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '历史文章排第一',
            'status' => 'published',
            'audit_status' => 'approved',
            'is_top' => 0,
            'sort' => 0,
            'published_at' => now()->subDays(3),
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);
        $middleId = (int) DB::table('contents')->insertGetId([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '中间文章排第二',
            'status' => 'published',
            'audit_status' => 'approved',
            'is_top' => 0,
            'sort' => 0,
            'published_at' => now()->subDays(2),
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        $newestId = (int) DB::table('contents')->insertGetId([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '最新文章排第三',
            'status' => 'published',
            'audit_status' => 'approved',
            'is_top' => 0,
            'sort' => 0,
            'published_at' => now()->subDay(),
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $expectedIds = [$oldestId, $middleId, $newestId];

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->postJson(route('admin.articles.reorder'), ['ordered_ids' => $expectedIds])
            ->assertOk()
            ->assertJsonPath('message', '文章排序已保存。');

        $adminOrderedIds = DB::table('contents')
            ->where('site_id', $site->id)
            ->where('type', 'article')
            ->whereNull('deleted_at')
            ->orderByDesc('is_top')
            ->orderByDesc('sort')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $frontendOrderedIds = (new ThemeTags($site, collect(), collect()))
            ->contentList(['site_wide' => true, 'limit' => 3])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame($expectedIds, $adminOrderedIds);
        $this->assertSame($expectedIds, $frontendOrderedIds);
    }

    public function test_article_reorder_visible_subset_stays_in_original_scope_positions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $channelId = (int) DB::table('channels')
            ->where('site_id', $site->id)
            ->where('type', 'list')
            ->orderBy('id')
            ->value('id');

        DB::table('contents')->where('site_id', $site->id)->where('type', 'article')->delete();

        $ids = [];
        foreach ([50, 40, 30, 20, 10] as $index => $sort) {
            $ids[] = (int) DB::table('contents')->insertGetId([
                'site_id' => $site->id,
                'channel_id' => $channelId,
                'type' => 'article',
                'title' => '排序文章 '.($index + 1),
                'status' => 'published',
                'audit_status' => 'approved',
                'is_top' => 0,
                'sort' => $sort,
                'published_at' => now()->subDays($index),
                'created_by' => $this->superAdmin()->id,
                'updated_by' => $this->superAdmin()->id,
                'created_at' => now()->subDays($index),
                'updated_at' => now()->subDays($index),
            ]);
        }

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->postJson(route('admin.articles.reorder'), ['ordered_ids' => [$ids[3], $ids[2]]])
            ->assertOk()
            ->assertJsonPath('message', '文章排序已保存。');

        $orderedIds = DB::table('contents')
            ->where('site_id', $site->id)
            ->where('type', 'article')
            ->whereNull('deleted_at')
            ->orderByDesc('is_top')
            ->orderByDesc('sort')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame([$ids[0], $ids[1], $ids[3], $ids[2], $ids[4]], $orderedIds);
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
        \App\Support\FrontendPageCache::flushSite((int) $site->id);

        $this->get(route('site.home', ['site' => $site->site_key, 'device' => 'mobile']))
            ->assertOk()
            ->assertSee('PC home mobile 1')
            ->assertDontSee('MOBILE');
    }

    public function test_shared_content_styles_are_not_injected_on_homepage(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->createEditableTemplateWorkspace($site, $this->superAdmin()->id);
        $templateRoot = SitePath::siteTemplateRoot($site->site_key, $themeCode);

        File::put(
            $templateRoot.DIRECTORY_SEPARATOR.'home.tpl',
            '<!DOCTYPE html><html><head><title>Home</title></head><body>HOME</body></html>'
        );

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertOk()
            ->assertSee('HOME')
            ->assertDontSee('site-content-render.css', false)
            ->assertDontSee('site-content-render.js', false);
    }

    public function test_shared_content_assets_are_injected_before_head_close_on_inner_pages_only(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->createEditableTemplateWorkspace($site, $this->superAdmin()->id);
        $templateRoot = SitePath::siteTemplateRoot($site->site_key, $themeCode);

        File::put(
            $templateRoot.DIRECTORY_SEPARATOR.'list.tpl',
            '<!DOCTYPE html><html><head><title>List</title></head><body>LIST {{ current.channel.name }}</body></html>'
        );

        DB::table('channels')->insert([
            'site_id' => $site->id,
            'parent_id' => null,
            'name' => '列表栏目',
            'slug' => 'shared-style-list',
            'type' => 'list',
            'list_template' => 'list',
            'path' => '/shared-style-list',
            'depth' => 0,
            'sort' => 0,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('site.channel', ['slug' => 'shared-style-list', 'site' => $site->site_key]))
            ->assertOk()
            ->assertSee('LIST 列表栏目')
            ->assertSee('site-content-render.css?v=', false)
            ->assertSee('site-content-render.js?v=', false);

        $html = $response->getContent();
        $this->assertLessThan(
            stripos($html, '</head>'),
            strpos($html, 'site-content-render.css'),
        );
        $this->assertLessThan(
            stripos($html, '</head>'),
            strpos($html, 'site-content-render.js'),
        );
    }

    public function test_shared_content_assets_are_prepended_when_template_has_no_head(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->createEditableTemplateWorkspace($site, $this->superAdmin()->id);
        $templateRoot = SitePath::siteTemplateRoot($site->site_key, $themeCode);

        File::put(
            $templateRoot.DIRECTORY_SEPARATOR.'list.tpl',
            '<main>NO HEAD {{ current.channel.name }}</main>'
        );

        DB::table('channels')->insert([
            'site_id' => $site->id,
            'parent_id' => null,
            'name' => '缺少 Head 栏目',
            'slug' => 'shared-style-no-head',
            'type' => 'list',
            'list_template' => 'list',
            'path' => '/shared-style-no-head',
            'depth' => 0,
            'sort' => 0,
            'status' => 1,
            'is_nav' => 1,
            'created_by' => $this->superAdmin()->id,
            'updated_by' => $this->superAdmin()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('site.channel', ['slug' => 'shared-style-no-head', 'site' => $site->site_key]))
            ->assertOk()
            ->assertSee('NO HEAD 缺少 Head 栏目')
            ->assertSee('site-content-render.css?v=', false)
            ->assertSee('site-content-render.js?v=', false);

        $html = $response->getContent();
        $this->assertSame(0, strpos($html, '<link rel="stylesheet"'));
    }

    public function test_site_can_create_fixed_mobile_home_template_from_editor(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->createEditableTemplateWorkspace($site, $this->superAdmin()->id);
        $activeTemplateId = $this->activeTemplateId($site);
        $templateRoot = SitePath::siteTemplateRoot($site->site_key, $themeCode);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.themes.editor.template-create-form', ['site_template_id' => $activeTemplateId]))
            ->assertOk()
            ->assertSee('手机版首页')
            ->assertDontSee('手机列表模板');

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-create'), [
                'template_prefix' => 'mobile-home',
                'template_suffix' => '',
                'template_title' => '手机首页',
                'template_source' => '<main>mobile home</main>',
            ])
            ->assertRedirect(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'm-home']));

        $this->assertFileExists($templateRoot.DIRECTORY_SEPARATOR.'m-home.tpl');
        $this->assertSame('<main>mobile home</main>', File::get($templateRoot.DIRECTORY_SEPARATOR.'m-home.tpl'));
        $this->assertDatabaseHas('site_template_meta', [
            'site_template_id' => DB::table('site_templates')->where('site_id', $site->id)->where('template_key', $themeCode)->value('id'),
            'template_name' => 'm-home',
            'title' => '手机首页',
        ]);
    }

    public function test_mobile_variant_templates_are_hidden_from_binding_selects(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->createEditableTemplateWorkspace($site, $this->superAdmin()->id);
        $templateRoot = SitePath::siteTemplateRoot($site->site_key, $themeCode);

        File::put($templateRoot.DIRECTORY_SEPARATOR.'m-list.tpl', '<main>mobile list</main>');
        File::put($templateRoot.DIRECTORY_SEPARATOR.'list-m-list.tpl', '<main>wrong mobile list</main>');
        File::put($templateRoot.DIRECTORY_SEPARATOR.'m-detail.tpl', '<main>mobile detail</main>');
        File::put($templateRoot.DIRECTORY_SEPARATOR.'detail-m-detail.tpl', '<main>wrong mobile detail</main>');
        File::put($templateRoot.DIRECTORY_SEPARATOR.'m-page.tpl', '<main>mobile page</main>');
        File::put($templateRoot.DIRECTORY_SEPARATOR.'page-m-page.tpl', '<main>wrong mobile page</main>');

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.channels.create'))
            ->assertOk()
            ->assertSee('list.tpl')
            ->assertDontSee('m-list.tpl')
            ->assertDontSee('list-m-list.tpl')
            ->assertDontSee('m-detail.tpl')
            ->assertDontSee('detail-m-detail.tpl')
            ->assertDontSee('m-page.tpl')
            ->assertDontSee('page-m-page.tpl');

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.articles.create'))
            ->assertOk()
            ->assertSee('detail.tpl')
            ->assertDontSee('m-detail.tpl')
            ->assertDontSee('detail-m-detail.tpl');

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.pages.create'))
            ->assertOk()
            ->assertSee('page.tpl')
            ->assertDontSee('m-page.tpl')
            ->assertDontSee('page-m-page.tpl');
    }

    public function test_site_can_create_direct_mobile_template_name_from_editor(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->createEditableTemplateWorkspace($site, $this->superAdmin()->id);
        $activeTemplateId = $this->activeTemplateId($site);
        $templateRoot = SitePath::siteTemplateRoot($site->site_key, $themeCode);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-create'), [
                'template_prefix' => 'custom',
                'template_suffix' => 'm-list',
                'template_title' => '手机列表',
                'template_source' => '<main>mobile list</main>',
            ])
            ->assertRedirect(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'm-list']));

        $this->assertFileExists($templateRoot.DIRECTORY_SEPARATOR.'m-list.tpl');
        $this->assertSame('<main>mobile list</main>', File::get($templateRoot.DIRECTORY_SEPARATOR.'m-list.tpl'));

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.channels.create'))
            ->assertOk()
            ->assertDontSee('m-list.tpl');
    }

    public function test_site_can_upload_and_delete_template_assets(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $activeTemplateId = $this->activeTemplateId($site);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.asset-upload'), [
                'site_template_id' => $activeTemplateId,
                'template' => 'home',
                'open_assets_mode' => 'insert',
                'asset' => UploadedFile::fake()->image('hero-banner.png', 1200, 800),
            ])
            ->assertRedirect(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'home', 'open_assets' => 1, 'open_assets_mode' => 'insert']));

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'home', 'open_assets' => 1, 'open_assets_mode' => 'insert']))
            ->assertOk()
            ->assertSee('hero-banner.png');

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.asset-delete'), [
                'site_template_id' => $activeTemplateId,
                'template' => 'home',
                'open_assets_mode' => 'insert',
                'asset_path' => 'assets/hero-banner.png',
            ])
            ->assertRedirect(route('admin.themes.editor', ['site_template_id' => $activeTemplateId, 'template' => 'home', 'open_assets' => 1, 'open_assets_mode' => 'insert']));
    }
}
