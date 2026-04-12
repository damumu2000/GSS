<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Site as SitePath;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ThemeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::query()->findOrFail((int) config('cms.super_admin_user_id', 1));
    }

    protected function demoSite(): object
    {
        return DB::table('sites')->where('site_key', 'site')->firstOrFail();
    }

    protected function createTheme(string $code, string $name): int
    {
        $themeId = (int) DB::table('themes')->insertGetId([
            'name' => $name,
            'code' => $code,
            'description' => $name.' 描述',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('theme_versions')->insert([
            'theme_id' => $themeId,
            'version' => '1.0.0',
            'package_path' => 'storage/app/theme_templates/'.$code,
            'manifest_json' => json_encode([
                'name' => $name,
                'code' => $code,
                'version' => '1.0.0',
            ], JSON_UNESCAPED_UNICODE),
            'is_current' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $themeId;
    }

    protected function activeThemeCode(object $site): string
    {
        return (string) DB::table('themes')->where('id', $site->default_theme_id)->value('code');
    }

    protected function createImageAttachment(object $site, string $filename): int
    {
        $path = 'site-media/'.$site->site_key.'/attachments/2026/03/'.$filename;

        return (int) DB::table('attachments')->insertGetId([
            'site_id' => $site->id,
            'origin_name' => $filename,
            'stored_name' => $filename,
            'disk' => 'site',
            'path' => $path,
            'url' => '/'.$path,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 1024,
            'uploaded_by' => $this->superAdmin()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createSiteOperator(string $username, string $roleCode = 'template_editor'): User
    {
        $user = User::query()->create([
            'username' => $username,
            'name' => ucfirst(str_replace('-', ' ', $username)),
            'email' => $username.'@example.com',
            'password' => 'ChangeMe123!',
            'status' => 1,
        ]);

        $site = $this->demoSite();
        $roleId = (int) DB::table('site_roles')->where('code', $roleCode)->value('id');

        DB::table('site_user_roles')->insert([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    protected function createChannel(object $site, array $overrides = []): int
    {
        return (int) DB::table('channels')->insertGetId(array_merge([
            'site_id' => $site->id,
            'parent_id' => null,
            'name' => '默认栏目',
            'slug' => 'default-channel',
            'type' => 'list',
            'status' => 1,
            'is_nav' => 1,
            'sort' => 0,
            'list_template' => 'list',
            'detail_template' => 'detail',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    protected function createPublishedContent(object $site, int $channelId, array $overrides = []): int
    {
        $contentId = (int) DB::table('contents')->insertGetId(array_merge([
            'site_id' => $site->id,
            'channel_id' => $channelId,
            'type' => 'article',
            'title' => '默认文章',
            'summary' => '默认摘要',
            'content' => '<p>默认内容</p>',
            'status' => 'published',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        DB::table('content_channels')->insert([
            'content_id' => $contentId,
            'channel_id' => $channelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $contentId;
    }

    protected function enableGuestbookModule(object $site, array $settings = []): void
    {
        $moduleId = (int) DB::table('modules')->where('code', 'guestbook')->value('id');

        if ($moduleId <= 0) {
            $moduleId = (int) DB::table('modules')->insertGetId([
                'name' => '留言板',
                'code' => 'guestbook',
                'version' => '1.0.0',
                'scope' => 'site',
                'author' => 'System',
                'site_entry_route' => 'admin.guestbook.index',
                'description' => '留言板模块',
                'status' => 1,
                'sort' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('modules')->where('id', $moduleId)->update([
                'status' => 1,
                'updated_at' => now(),
            ]);
        }

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $site->id, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()]
        );

        $payloads = array_merge([
            'module.guestbook.enabled' => '1',
            'module.guestbook.show_name' => '0',
            'module.guestbook.show_after_reply' => '1',
        ], $settings);

        foreach ($payloads as $key => $value) {
            DB::table('site_settings')->updateOrInsert(
                ['site_id' => $site->id, 'setting_key' => $key],
                [
                    'setting_value' => (string) $value,
                    'autoload' => 1,
                    'updated_by' => $this->superAdmin()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    protected function createGuestbookMessage(object $site, array $overrides = []): int
    {
        return (int) DB::table('module_guestbook_messages')->insertGetId(array_merge([
            'site_id' => $site->id,
            'display_no' => 1,
            'name' => '张三',
            'phone' => '13800138000',
            'content' => '这是默认留言内容，用于模板调用测试。',
            'original_content' => null,
            'status' => 'pending',
            'is_read' => 0,
            'read_at' => null,
            'reply_content' => null,
            'replied_at' => null,
            'replied_by' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    public function test_seeded_demo_site_binds_its_default_theme(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        $this->assertDatabaseHas('site_theme_bindings', [
            'site_id' => $site->id,
            'theme_id' => $site->default_theme_id,
        ]);
    }

    public function test_site_theme_management_only_lists_bound_themes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $unboundThemeId = $this->createTheme('campus_modern', 'Campus Modern');

        $response = $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.themes.index'));

        $response->assertOk()
            ->assertSee('School Fresh')
            ->assertDontSee('Campus Modern');

        $this->assertDatabaseMissing('site_theme_bindings', [
            'site_id' => $site->id,
            'theme_id' => $unboundThemeId,
        ]);
    }

    public function test_switching_to_an_unbound_theme_returns_not_found(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $this->createTheme('campus_modern', 'Campus Modern');

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.update'), [
                'theme_code' => 'campus_modern',
            ])
            ->assertNotFound();
    }

    public function test_frontend_shows_missing_theme_page_when_site_has_no_bound_themes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        DB::table('site_theme_bindings')->where('site_id', $site->id)->delete();
        DB::table('sites')->where('id', $site->id)->update([
            'default_theme_id' => null,
            'updated_at' => now(),
        ]);

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertStatus(503)
            ->assertSee('当前站点暂未绑定可用模板')
            ->assertSee('示例学校');
    }

    public function test_frontend_requires_an_explicitly_selected_bound_theme(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        DB::table('sites')->where('id', $site->id)->update([
            'default_theme_id' => null,
            'updated_at' => now(),
        ]);

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertStatus(503)
            ->assertSee('当前站点暂未绑定可用模板')
            ->assertSee('示例学校');
    }

    public function test_frontend_shows_friendly_page_when_theme_template_has_parse_error(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, "{% if site.name %}<div>broken</div>\n");

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertStatus(503)
            ->assertSee('页面暂时无法显示')
            ->assertSee('模板解析异常')
            ->assertSee('home.tpl');
    }

    public function test_frontend_theme_template_rejects_invalid_include_identifier(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, "{% include '../secret' %}\n");

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertStatus(503)
            ->assertSee('页面暂时无法显示')
            ->assertSee('模板解析异常')
            ->assertSee('模板标识不合法');
    }

    public function test_frontend_theme_template_rejects_circular_include(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, "{% include 'home' %}\n");

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertStatus(503)
            ->assertSee('页面暂时无法显示')
            ->assertSee('模板解析异常')
            ->assertSee('循环引用');
    }

    public function test_frontend_theme_template_rejects_invalid_named_argument_syntax(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, "{% set list = contentList limit=6 broken-token %}\n");

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertStatus(503)
            ->assertSee('页面暂时无法显示')
            ->assertSee('模板解析异常')
            ->assertSee('参数格式无效');
    }

    public function test_frontend_theme_template_rejects_unknown_named_argument(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, "{% set list = contentList limit=6 unknown='x' %}\n");

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertStatus(503)
            ->assertSee('页面暂时无法显示')
            ->assertSee('模板解析异常')
            ->assertSee('不支持以下参数：unknown');
    }

    public function test_frontend_theme_template_supports_current_channel_helpers_and_content_offset(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'list.tpl';

        $parentChannelId = $this->createChannel($site, [
            'name' => '新闻中心',
            'slug' => 'news-center',
        ]);
        $childChannelId = $this->createChannel($site, [
            'parent_id' => $parentChannelId,
            'name' => '校园新闻',
            'slug' => 'campus-news',
        ]);
        $this->createChannel($site, [
            'parent_id' => $parentChannelId,
            'name' => '通知快讯',
            'slug' => 'flash-notices',
        ]);

        $this->createPublishedContent($site, $childChannelId, [
            'title' => '第一条校园新闻',
            'published_at' => now()->subDay(),
        ]);
        $this->createPublishedContent($site, $childChannelId, [
            'title' => '第二条校园新闻',
            'published_at' => now(),
        ]);

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, <<<'TPL'
<section>
<div class="page-type">{{ current.page.type }}</div>
<div class="current-channel">{{ current.channel.name }}</div>
{% set parentChannel = channel id=current.channel.parent_id %}
<div class="parent-channel">{{ parentChannel.name }}</div>
{% set childChannels = children channel_id=parentChannel.id limit=5 %}
{% for item in childChannels %}
<span class="child-channel">{{ item.name }}</span>
{% endfor %}
{% set trail = breadcrumb channel_id=current.channel.id %}
{% for crumb in trail %}
<span class="crumb">{{ crumb.name }}</span>
{% endfor %}
{% set articles = contentList channel=current.channel.slug limit=1 offset=1 %}
{% for item in articles %}
<article class="offset-article">{{ item.title }}</article>
{% endfor %}
</section>
TPL);

        $this->get(route('site.channel', ['site' => $site->site_key, 'slug' => 'campus-news']))
            ->assertOk()
            ->assertSee('channel', false)
            ->assertSee('校园新闻')
            ->assertSee('新闻中心')
            ->assertSee('通知快讯')
            ->assertSee('第一条校园新闻')
            ->assertDontSee('第二条校园新闻');
    }

    public function test_frontend_theme_template_supports_content_lookup_and_format_helpers(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';
        $channelId = $this->createChannel($site, [
            'name' => '公告通知',
            'slug' => 'notice-board',
        ]);
        $articleId = $this->createPublishedContent($site, $channelId, [
            'title' => '模板参数使用演示',
            'summary' => '<p>模板<strong>摘要</strong>演示</p>',
            'published_at' => '2026-04-06 09:30:00',
        ]);

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, <<<TPL
<section>
<div class="site-name">{{ siteValue key='name' default='网站名称' }}</div>
<div class="site-key">{{ siteValue key='site_key' default='HIDDEN-SITE-KEY' }}</div>
<div class="site-phone">{{ siteValue key='contact_phone' default='--' }}</div>
{% set picked = content id={$articleId} %}
{% set plainSummary = plainText value=picked.summary %}
{% set shortTitle = truncate value=picked.title length=6 %}
{% set publishDate = formatDate value=picked.published_at format='m-d' %}
<div class="picked-title">{{ picked.title }}</div>
<div class="plain-summary">{{ plainSummary }}</div>
<div class="short-title">{{ shortTitle }}</div>
<div class="publish-date">{{ publishDate }}</div>
</section>
TPL);

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertOk()
            ->assertSee('css/site-content-render.css')
            ->assertSee('示例学校')
            ->assertSee('HIDDEN-SITE-KEY')
            ->assertSee('010-88886666')
            ->assertSee('模板参数使用演示')
            ->assertSee('模板摘要演示')
            ->assertSee('模板参数使用...')
            ->assertSee('04-06');
    }

    public function test_frontend_theme_template_supports_channel_shortcuts_and_advanced_content_filters(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'list.tpl';

        $parentChannelId = $this->createChannel($site, [
            'name' => '通知公告',
            'slug' => 'notices',
        ]);
        $currentChannelId = $this->createChannel($site, [
            'parent_id' => $parentChannelId,
            'name' => '校务公告',
            'slug' => 'school-notices',
        ]);
        $this->createChannel($site, [
            'parent_id' => $parentChannelId,
            'name' => '活动公告',
            'slug' => 'event-notices',
        ]);

        $this->createPublishedContent($site, $currentChannelId, [
            'title' => '三月公告',
            'summary' => '超出时间范围',
            'published_at' => '2026-03-18 10:00:00',
        ]);
        $this->createPublishedContent($site, $currentChannelId, [
            'title' => '四月公告甲',
            'summary' => '命中关键字',
            'published_at' => '2026-04-08 10:00:00',
        ]);
        $this->createPublishedContent($site, $currentChannelId, [
            'title' => '四月新闻',
            'summary' => '不命中关键字',
            'published_at' => '2026-04-10 10:00:00',
        ]);
        $this->createPublishedContent($site, $currentChannelId, [
            'title' => '四月公告乙',
            'summary' => '命中关键字',
            'published_at' => '2026-04-20 10:00:00',
        ]);

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, <<<'TPL'
<section>
{% set parentChannel = parent %}
{% set siblingChannels = siblings channel_id=current.channel.id limit=5 %}
<div class="parent-name">{{ parentChannel.name }}</div>
{% set currentChannel = channel slug=current.channel.slug %}
<div class="current-channel-name">{{ currentChannel.name }}</div>
<div class="current-channel-url">{{ linkTo type='channel' id=current.channel.id }}</div>
{% for item in siblingChannels %}
<span class="sibling">{{ item.name }}</span>
{% endfor %}
{% set filteredArticles = contentList channel_id=current.channel.id keyword='公告' limit=5 order_by='published_at' order_dir='asc' published_after='2026-04-01 00:00:00' published_before='2026-04-30 23:59:59' %}
{% for item in filteredArticles %}
<article class="filtered">{{ item.title }}</article>
{% endfor %}
</section>
TPL);

        $response = $this->get(route('site.channel', ['site' => $site->site_key, 'slug' => 'school-notices']));

        $response->assertOk()
            ->assertSee('通知公告')
            ->assertSee('校务公告')
            ->assertSee('/channel/school-notices', false)
            ->assertSee('活动公告')
            ->assertSeeInOrder(['四月公告甲', '四月公告乙'])
            ->assertDontSee('三月公告')
            ->assertDontSee('四月新闻');
    }

    public function test_frontend_theme_template_supports_relative_time_and_content_shortcuts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';
        $channelId = $this->createChannel($site, [
            'name' => '校园头条',
            'slug' => 'campus-headlines',
        ]);
        $articleId = $this->createPublishedContent($site, $channelId, [
            'title' => '第三批模板能力上线',
            'published_at' => now()->subHours(3),
        ]);

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, <<<TPL
<section>
{% set picked = content id={$articleId} %}
{% set pickedChannel = channel id={$channelId} %}
<div class="channel-slug">{{ pickedChannel.slug }}</div>
<div class="content-title">{{ picked.title }}</div>
<div class="content-url">{{ linkTo type='article' id={$articleId} }}</div>
<div class="relative-time">{{ timeAgo value=picked.published_at default='--' }}</div>
</section>
TPL);

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertOk()
            ->assertSee('campus-headlines')
            ->assertSee('第三批模板能力上线')
            ->assertSee('/article/'.$articleId, false)
            ->assertSee('小时前');
    }

    public function test_frontend_theme_template_supports_default_value_newlines_and_datetime_format(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';
        $channelId = $this->createChannel($site, [
            'name' => '使用帮助',
            'slug' => 'help-center',
        ]);
        $articleId = $this->createPublishedContent($site, $channelId, [
            'title' => '模板帮助说明',
            'summary' => "第一行说明\n第二行说明",
            'author' => '',
            'published_at' => '2026-04-07 09:15:00',
        ]);

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, <<<TPL
<section>
{% set picked = content id={$articleId} %}
<div class="author">{{ valueOr value=picked.author default='本站编辑' }}</div>
<div class="summary">{{{ textToHtml value=picked.summary }}}</div>
<div class="publish-datetime">{{ formatDate value=picked.published_at format='Y-m-d H:i' default='--' }}</div>
</section>
TPL);

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertOk()
            ->assertSee('本站编辑')
            ->assertSee('第一行说明<br>', false)
            ->assertSee('第二行说明')
            ->assertSee('2026-04-07 09:15');
    }

    public function test_frontend_theme_template_supports_include_and_exclude_ids(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';
        $channelId = $this->createChannel($site, [
            'name' => '精选文章',
            'slug' => 'featured-news',
        ]);
        $firstId = $this->createPublishedContent($site, $channelId, ['title' => '重点文章一']);
        $secondId = $this->createPublishedContent($site, $channelId, ['title' => '重点文章二']);
        $thirdId = $this->createPublishedContent($site, $channelId, ['title' => '普通文章三']);

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, <<<TPL
<section>
{% set focusArticles = contentList include_ids='{$firstId},{$secondId}' limit=5 order_by='id' order_dir='asc' %}
{% set restArticles = contentList channel='featured-news' exclude_ids='{$firstId},{$secondId}' limit=5 %}
{% for item in focusArticles %}
<span class="focus">{{ item.title }}</span>
{% endfor %}
{% for item in restArticles %}
<span class="rest">{{ item.title }}</span>
{% endfor %}
</section>
TPL);

        $response = $this->get(route('site.home', ['site' => $site->site_key]));

        $response->assertOk()
            ->assertSee('重点文章一')
            ->assertSee('重点文章二')
            ->assertSee('普通文章三')
            ->assertSeeInOrder(['重点文章一', '重点文章二', '普通文章三']);
    }

    public function test_frontend_theme_template_supports_has_image_author_source_and_random_filters(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';
        $channelId = $this->createChannel($site, [
            'name' => '校园聚焦',
            'slug' => 'campus-focus',
        ]);

        $this->createPublishedContent($site, $channelId, [
            'title' => '有图公告',
            'cover_image' => '/site-media/site/attachments/demo.jpg',
            'author' => '教务处',
            'source' => '官网',
        ]);
        $this->createPublishedContent($site, $channelId, [
            'title' => '无图公告',
            'cover_image' => '',
            'author' => '教务处',
            'source' => '官网',
        ]);
        $this->createPublishedContent($site, $channelId, [
            'title' => '其他来源公告',
            'cover_image' => '/site-media/site/attachments/other.jpg',
            'author' => '宣传部',
            'source' => '公众号',
        ]);

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, <<<'TPL'
<section>
{% set imageArticles = contentList channel='campus-focus' has_image=true limit=5 %}
{% set officeArticles = contentList channel='campus-focus' author='教务处' source='官网' limit=5 %}
{% set randomArticles = contentList channel='campus-focus' random=true limit=3 %}
{% for item in imageArticles %}
<span class="image">{{ item.title }}</span>
{% endfor %}
{% for item in officeArticles %}
<span class="office">{{ item.title }}</span>
{% endfor %}
{% for item in randomArticles %}
<span class="random">{{ item.title }}</span>
{% endfor %}
</section>
TPL);

        $response = $this->get(route('site.home', ['site' => $site->site_key]));

        $response->assertOk()
            ->assertSee('有图公告')
            ->assertSee('无图公告')
            ->assertSee('其他来源公告')
            ->assertDontSee('<span class="image">无图公告</span>', false)
            ->assertSee('<span class="office">有图公告</span>', false)
            ->assertSee('<span class="office">无图公告</span>', false)
            ->assertDontSee('<span class="office">其他来源公告</span>', false);
    }

    public function test_frontend_theme_template_supports_fields_projection(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';
        $channelId = $this->createChannel($site, [
            'name' => '字段投影',
            'slug' => 'field-projection',
        ]);
        $articleId = $this->createPublishedContent($site, $channelId, [
            'title' => '字段精简文章',
            'published_at' => '2026-04-07 13:45:00',
        ]);

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, <<<'TPL'
<section>
{% set liteArticles = contentList channel='field-projection' fields='title,url,published_at' limit=5 %}
{% for item in liteArticles %}
<span class="id">{{ item.id }}</span>
<span class="title">{{ item.title }}</span>
<span class="url">{{ item.url }}</span>
<span class="published">{{ item.published_at }}</span>
<span class="author">{{ valueOr value=item.author default='missing' }}</span>
{% endfor %}
</section>
TPL);

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertOk()
            ->assertSee((string) $articleId)
            ->assertSee('字段精简文章')
            ->assertSee('/article/'.$articleId, false)
            ->assertSee('2026-04-07 13:45:00')
            ->assertSee('missing');
    }

    public function test_frontend_theme_template_supports_channels_projection_and_keyword_filters(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';

        $firstId = $this->createChannel($site, [
            'name' => '公告中心',
            'slug' => 'notice-center',
        ]);
        $secondId = $this->createChannel($site, [
            'name' => '通知发布',
            'slug' => 'notice-publish',
        ]);
        $this->createChannel($site, [
            'name' => '校园新闻',
            'slug' => 'campus-news-home',
        ]);

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, <<<TPL
<section>
{% set pickedChannels = channels include_ids='{$firstId},{$secondId}' fields='name,url' limit=3 %}
{% set searchedChannels = channels keyword='公告' limit=6 %}
{% for item in pickedChannels %}
<span class="picked-name">{{ item.name }}</span>
<span class="picked-url">{{ item.url }}</span>
<span class="picked-type">{{ valueOr value=item.type default='missing' }}</span>
{% endfor %}
{% for item in searchedChannels %}
<span class="searched">{{ item.name }}</span>
{% endfor %}
</section>
TPL);

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertOk()
            ->assertSee('公告中心')
            ->assertSee('通知发布')
            ->assertSee('/channel/notice-center', false)
            ->assertSee('/channel/notice-publish', false)
            ->assertSee('missing')
            ->assertSee('<span class="searched">公告中心</span>', false)
            ->assertDontSee('<span class="searched">校园新闻</span>', false);
    }

    public function test_frontend_theme_template_supports_promos_projection_and_random(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';
        $attachmentA = $this->createImageAttachment($site, 'promo-a.jpg');
        $attachmentB = $this->createImageAttachment($site, 'promo-b.jpg');

        $positionId = (int) DB::table('promo_positions')->insertGetId([
            'site_id' => $site->id,
            'code' => 'home_banner',
            'name' => '首页横幅',
            'page_scope' => 'home',
            'display_mode' => 'banner',
            'scope_hash' => sha1('home|site|default'),
            'status' => 1,
            'allow_multiple' => 1,
            'max_items' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('promo_items')->insert([
            [
                'site_id' => $site->id,
                'position_id' => $positionId,
                'attachment_id' => $attachmentA,
                'title' => '图宣甲',
                'link_url' => '/a',
                'link_target' => '_self',
                'status' => 1,
                'sort' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $site->id,
                'position_id' => $positionId,
                'attachment_id' => $attachmentB,
                'title' => '图宣乙',
                'link_url' => '/b',
                'link_target' => '_blank',
                'status' => 1,
                'sort' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, <<<'TPL'
<section>
{% set litePromos = promos code='home_banner' fields='title,image_url,link_url' limit=3 %}
{% set randomPromos = promos code='home_banner' random=true limit=2 %}
{% for item in litePromos %}
<span class="promo-title">{{ item.title }}</span>
<span class="promo-image">{{ item.image_url }}</span>
<span class="promo-link">{{ item.link_url }}</span>
<span class="promo-target">{{ valueOr value=item.link_target default='missing' }}</span>
{% endfor %}
{% for item in randomPromos %}
<span class="random-promo">{{ item.title }}</span>
{% endfor %}
</section>
TPL);

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertOk()
            ->assertSee('图宣甲')
            ->assertSee('图宣乙')
            ->assertSee('/site-media/'.$site->site_key.'/attachments/2026/03/promo-a.jpg', false)
            ->assertSee('/a', false)
            ->assertSee('missing')
            ->assertSee('<span class="random-promo">', false);
    }

    public function test_frontend_theme_template_supports_navigation_stats_and_detail_helpers(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'detail.tpl';
        $channelId = $this->createChannel($site, [
            'name' => '通知公告',
            'slug' => 'notice-center',
        ]);

        $firstId = $this->createPublishedContent($site, $channelId, [
            'title' => '第一篇文章',
            'published_at' => '2026-04-01 09:00:00',
        ]);
        $secondId = $this->createPublishedContent($site, $channelId, [
            'title' => '第二篇文章',
            'published_at' => '2026-04-02 09:00:00',
        ]);
        $thirdId = $this->createPublishedContent($site, $channelId, [
            'title' => '第三篇文章',
            'published_at' => '2026-04-03 09:00:00',
        ]);

        File::put($templatePath, <<<'TPL'
{% set navItems = nav limit=20 %}
{% set firstNav = first navItems %}
{% set siteStats = stats %}
{% set previousItem = previous article %}
{% set nextItem = next article %}
{% set relatedItems = related article limit=2 %}
{% set firstRelated = first relatedItems %}

<div class="nav-first">{{ firstNav.name }}</div>
<div class="stats-articles">{{ siteStats.articles }}</div>
<div class="current-channel-id">{{ current.content.channel_id }}</div>
<div class="previous-title">{{ previousItem.title }}</div>
<div class="next-title">{{ nextItem.title }}</div>
<div class="first-related">{{ firstRelated.title }}</div>
{% for item in relatedItems %}
<span class="related-item">{{ item.title }}</span>
{% endfor %}
TPL);

        $response = $this->get(route('site.article', ['site' => $site->site_key, 'id' => $secondId]));

        $response->assertOk()
            ->assertSee('通知公告')
            ->assertSee('<div class="stats-articles">', false)
            ->assertSee('<div class="current-channel-id">'.$channelId.'</div>', false)
            ->assertSee('<div class="previous-title">第一篇文章</div>', false)
            ->assertSee('<div class="next-title">第三篇文章</div>', false)
            ->assertSee('<div class="first-related">第三篇文章</div>', false)
            ->assertSee('<span class="related-item">第三篇文章</span>', false)
            ->assertSee('<span class="related-item">第一篇文章</span>', false);
    }

    public function test_frontend_theme_template_supports_guestbook_messages_and_stats_with_public_rules(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';

        $this->enableGuestbookModule($site, [
            'module.guestbook.enabled' => '1',
            'module.guestbook.show_name' => '0',
            'module.guestbook.show_after_reply' => '1',
        ]);

        $this->createGuestbookMessage($site, [
            'display_no' => 12,
            'name' => '王小明',
            'content' => '这是一条待办理留言，不应该在前台模板里直接显示。',
            'status' => 'pending',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->createGuestbookMessage($site, [
            'display_no' => 25,
            'name' => '李老师',
            'content' => '这是一条已经办理完成的留言，会在首页模板里展示摘要。',
            'status' => 'replied',
            'reply_content' => '这是后台回复摘要内容，用于首页模板测试。',
            'replied_at' => now()->subDay(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, <<<'TPL'
{% set guestbook = guestbookStats %}
{% set messages = guestbookMessages limit=5 order='created_at_desc' %}

<div>留言板启用：{{ guestbook.enabled }}</div>
<div>公开留言：{{ guestbook.total }}</div>
<div>已办理：{{ guestbook.replied }}</div>
<div>待办理：{{ guestbook.pending }}</div>

{% for item in messages %}
<article>
    <h3>{{ item.display_no }} {{ item.name }}</h3>
    <p>{{ item.summary }}</p>
    <div>{{ item.reply_summary }}</div>
    <a href="{{ item.detail_url }}">查看</a>
</article>
{% endfor %}
TPL);

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertOk()
            ->assertSee('留言板启用：1', false)
            ->assertSee('公开留言：1', false)
            ->assertSee('已办理：1', false)
            ->assertSee('待办理：0', false)
            ->assertSee('00025', false)
            ->assertSee('李***', false)
            ->assertSee('这是后台回复摘要内容', false)
            ->assertSee('/guestbook/25?site=site', false)
            ->assertDontSee('王小明', false)
            ->assertDontSee('00012', false);
    }

    public function test_frontend_theme_template_guestbook_stats_show_disabled_message_when_module_is_closed(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';

        $this->enableGuestbookModule($site, [
            'module.guestbook.enabled' => '0',
        ]);

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, <<<'TPL'
{% set guestbook = guestbookStats %}
{% set messages = guestbookMessages limit=3 %}
<div>留言板启用：{{ guestbook.enabled }}</div>
<div>提示：{{ guestbook.message }}</div>
<div>数量：{{ guestbook.total }}</div>
{% for item in messages %}
<span>{{ item.display_no }}</span>
{% endfor %}
TPL);

        $this->get(route('site.home', ['site' => $site->site_key]))
            ->assertOk()
            ->assertSee('留言板启用：0', false)
            ->assertSee('提示：留言板模块已关闭', false)
            ->assertSee('数量：0', false)
            ->assertDontSee('00001', false);
    }

    public function test_theme_editor_redirects_back_when_no_theme_is_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        DB::table('sites')->where('id', $site->id)->update([
            'default_theme_id' => null,
            'updated_at' => now(),
        ]);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.themes.editor'))
            ->assertRedirect(route('admin.themes.index'));

        $this->followRedirects(
            $this->actingAs($this->superAdmin())
                ->withSession(['current_site_id' => $site->id])
                ->get(route('admin.themes.editor'))
        )
            ->assertOk()
            ->assertSee('当前站点尚未启用主题');
    }

    public function test_theme_editor_redirects_back_when_active_theme_has_no_template_files(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $missingThemeCode = 'theme_missing_templates_for_test';
        $themeId = $this->createTheme($missingThemeCode, '学校默认主题');

        DB::table('site_theme_bindings')->updateOrInsert(
            [
                'site_id' => $site->id,
                'theme_id' => $themeId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('sites')->where('id', $site->id)->update([
            'default_theme_id' => $themeId,
            'updated_at' => now(),
        ]);

        File::deleteDirectory(storage_path("app/theme_templates/{$missingThemeCode}"));
        File::deleteDirectory(SitePath::themeOverrideRoot($site->site_key, $missingThemeCode));

        $response = $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.themes.editor'));

        $response->assertRedirect(route('admin.themes.index'));

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('当前启用主题未提供可编辑的模板文件');
    }

    public function test_custom_site_templates_are_available_in_channel_template_options(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $this->activeThemeCode($site)).DIRECTORY_SEPARATOR.'list-news.tpl';

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, "{% include 'list' %}\n");

        $this->assertTrue(File::exists($templatePath));

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.channels.create'))
            ->assertOk()
            ->assertSee('list-news.tpl');
    }

    public function test_custom_templates_use_chinese_labels_in_editor_selector(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'list-news.tpl';

        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, "{% include 'list' %}\n");
        DB::table('site_theme_template_meta')->updateOrInsert(
            [
                'site_id' => $site->id,
                'theme_code' => $themeCode,
                'template_name' => 'list-news',
            ],
            [
                'title' => '校园新闻列表模板',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.themes.editor'))
            ->assertOk()
            ->assertSee('自定义列表模板（news）_校园新闻列表模板')
            ->assertSee('list-news.tpl');
    }

    public function test_site_can_create_a_custom_theme_template(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templateName = 'list-campus';
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.$templateName.'.tpl';

        File::delete($templatePath);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-create'), [
                'template_title' => '校园栏目列表模板',
                'template_prefix' => 'list',
                'template_suffix' => 'campus',
                'starter_template' => 'blank',
                'template_source' => "{% include 'list' %}\n",
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => $templateName]));

        $this->assertTrue(File::exists($templatePath));
        $this->assertSame("{% include 'list' %}", trim(File::get($templatePath)));
        $this->assertDatabaseHas('site_theme_template_meta', [
            'site_id' => $site->id,
            'theme_code' => $themeCode,
            'template_name' => $templateName,
            'title' => '校园栏目列表模板',
        ]);
        $this->assertDatabaseHas('operation_logs', [
            'site_id' => $site->id,
            'module' => 'theme',
            'action' => 'create_template',
        ]);
    }

    public function test_site_rejects_an_invalid_template_identifier(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'../bad-name.tpl';

        $this->from(route('admin.themes.editor'))
            ->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-create'), [
                'template_title' => '非法模板',
                'template_prefix' => 'list',
                'template_suffix' => '../bad-name',
                'starter_template' => 'blank',
            ])
            ->assertRedirect(route('admin.themes.editor'))
            ->assertSessionHasErrorsIn('createTemplate', [
                'template_suffix' => "模板标识格式不正确。\n允许填写：小写字母、数字、中划线（-）和下划线（_），且不能以符号开头或结尾。",
            ]);

        $this->assertFalse(File::exists($templatePath));
        $this->assertDatabaseMissing('operation_logs', [
            'site_id' => $site->id,
            'module' => 'theme',
            'action' => 'create_template',
        ]);
    }

    public function test_site_requires_template_identifier_when_creating_template(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        $this->from(route('admin.themes.editor'))
            ->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-create'), [
                'template_title' => '校园模板',
                'template_prefix' => 'list',
                'template_suffix' => '',
                'starter_template' => 'blank',
            ])
            ->assertRedirect(route('admin.themes.editor'))
            ->assertSessionHasErrorsIn('createTemplate', [
                'template_suffix' => "请先填写模板标识。\n允许填写：小写字母、数字、中划线（-）和下划线（_），且不能以符号开头或结尾。",
            ]);
    }

    public function test_site_allows_template_title_with_punctuation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'list-campus.tpl';

        File::delete($templatePath);

        $this->from(route('admin.themes.editor.template-create-form'))
            ->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-create'), [
                'template_title' => '校园新闻（A）',
                'template_prefix' => 'list',
                'template_suffix' => 'campus',
                'starter_template' => 'blank',
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'list-campus']));

        $this->assertTrue(File::exists($templatePath));
        $this->assertDatabaseHas('site_theme_template_meta', [
            'site_id' => $site->id,
            'theme_code' => $themeCode,
            'template_name' => 'list-campus',
            'title' => '校园新闻（A）',
        ]);
        $this->assertDatabaseHas('operation_logs', [
            'site_id' => $site->id,
            'module' => 'theme',
            'action' => 'create_template',
        ]);
    }

    public function test_site_can_update_template_title_while_saving_source(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.update'), [
                'template' => 'home',
                'template_title' => '学校首页模板',
                'template_source' => "<section>home</section>\n",
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']));

        $this->assertDatabaseHas('site_theme_template_meta', [
            'site_id' => $site->id,
            'theme_code' => $this->activeThemeCode($site),
            'template_name' => 'home',
            'title' => '学校首页模板',
        ]);
    }

    public function test_theme_editor_rejects_template_source_with_php_code_tag(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        $response = $this->from(route('admin.themes.editor', ['template' => 'home']))
            ->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.update'), [
                'template' => 'home',
                'template_title' => '首页模板',
                'template_source' => "<?php echo 'bad'; ?>",
            ]);

        $response
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']))
            ->assertSessionHasErrors(['template_source']);
    }

    public function test_site_can_update_template_title_with_punctuation_while_saving_source(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.update'), [
                'template' => 'home',
                'template_title' => '学校首页（新）',
                'template_source' => "<section>home</section>\n",
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']));

        $this->assertDatabaseHas('site_theme_template_meta', [
            'site_id' => $site->id,
            'theme_code' => $this->activeThemeCode($site),
            'template_name' => 'home',
            'title' => '学校首页（新）',
        ]);
    }

    public function test_template_editor_rejects_legacy_site_media_reference_when_saving_template_source(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->from(route('admin.themes.editor', ['template' => 'home']))
            ->post(route('admin.themes.editor.update'), [
                'template' => 'home',
                'template_title' => '学校首页模板',
                'template_source' => "<img src=\"/site-media/site/attachments/2026/03/theme-banner.jpg\" alt=\"banner\">\n",
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']))
            ->assertSessionHasErrors([
                'template_source' => '模板源码中不再支持站点资源，请改用当前主题的模板资源。',
            ]);
    }

    public function test_template_editor_rejects_legacy_site_media_plain_path_when_creating_template(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->from(route('admin.themes.editor'))
            ->post(route('admin.themes.editor.template-create'), [
                'template_prefix' => 'page',
                'template_suffix' => 'legacy-assets',
                'template_title' => '落地页',
                'template_source' => "{% set heroImage = '/site-media/site/attachments/2026/03/plain-path-banner.jpg' %}\n",
            ])
            ->assertRedirect(route('admin.themes.editor'))
            ->assertSessionHasErrorsIn('createTemplate', [
                'template_source' => '模板源码中不再支持站点资源，请改用当前主题的模板资源。',
            ]);
    }

    public function test_template_editor_rejects_legacy_absolute_site_media_reference_when_saving(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $attachmentId = $this->createImageAttachment($site, 'theme-forbidden-banner.jpg');
        $attachmentUrl = (string) DB::table('attachments')->where('id', $attachmentId)->value('url');

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->from(route('admin.themes.editor', ['template' => 'home']))
            ->post(route('admin.themes.editor.update'), [
                'template' => 'home',
                'template_title' => '首页',
                'template_source' => "<img src=\"{$attachmentUrl}\" alt=\"forbidden\">\n",
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']))
            ->assertSessionHasErrors([
                'template_source' => '模板源码中不再支持站点资源，请改用当前主题的模板资源。',
            ]);
    }

    public function test_template_editor_rejects_legacy_absolute_site_media_reference_when_creating_template(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $attachmentId = $this->createImageAttachment($site, 'theme-create-forbidden-banner.jpg');
        $attachmentUrl = (string) DB::table('attachments')->where('id', $attachmentId)->value('url');

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->from(route('admin.themes.editor'))
            ->post(route('admin.themes.editor.template-create'), [
                'template_prefix' => 'page',
                'template_suffix' => 'attachment-guard',
                'template_title' => '防越权',
                'starter_template' => '',
                'current_template' => 'home',
                'template_source' => "<img src=\"{$attachmentUrl}\" alt=\"forbidden\">\n",
            ])
            ->assertRedirect(route('admin.themes.editor'))
            ->assertSessionHasErrorsIn('createTemplate', [
                'template_source' => '模板源码中不再支持站点资源，请改用当前主题的模板资源。',
            ]);
    }

    public function test_template_editor_rejects_inline_script_style_and_event_markup(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->from(route('admin.themes.editor', ['template' => 'home']))
            ->post(route('admin.themes.editor.update'), [
                'template' => 'home',
                'template_title' => '首页',
                'template_source' => <<<'TPL'
<section style="color:red" onclick="alert(1)">
    <style>.hero{color:red;}</style>
    <script>alert('bad')</script>
</section>
TPL,
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']))
            ->assertSessionHasErrors(['template_source']);
    }

    public function test_site_can_reset_an_override_template_back_to_default(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';

        File::delete($templatePath);
        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, "<section>custom home</section>\n");

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-reset'), [
                'template' => 'home',
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']));

        $this->assertFalse(File::exists($templatePath));
        $this->assertDatabaseHas('operation_logs', [
            'site_id' => $site->id,
            'module' => 'theme',
            'action' => 'reset_template',
        ]);
    }

    public function test_site_can_rollback_a_template_to_previous_snapshot(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';

        File::delete($templatePath);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.update'), [
                'template' => 'home',
                'template_source' => "<section>first version</section>\n",
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']));

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.update'), [
                'template' => 'home',
                'template_source' => "<section>second version</section>\n",
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']));

        $this->assertSame('<section>second version</section>', trim(File::get($templatePath)));

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-rollback'), [
                'template' => 'home',
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']));

        $this->assertSame('<section>first version</section>', trim(File::get($templatePath)));
        $this->assertDatabaseHas('operation_logs', [
            'site_id' => $site->id,
            'module' => 'theme',
            'action' => 'rollback_template',
        ]);
        $this->assertDatabaseHas('site_theme_template_versions', [
            'site_id' => $site->id,
            'theme_code' => $themeCode,
            'template_name' => 'home',
        ]);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-rollback'), [
                'template' => 'home',
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']));

        $this->assertSame('<section>second version</section>', trim(File::get($templatePath)));
    }

    public function test_site_can_rollback_to_a_selected_history_version(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'home.tpl';

        File::delete($templatePath);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.update'), [
                'template' => 'home',
                'template_source' => "<section>custom history</section>\n",
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']));

        $defaultSnapshot = DB::table('site_theme_template_versions')
            ->where('site_id', $site->id)
            ->where('theme_code', $themeCode)
            ->where('template_name', 'home')
            ->where('source_type', 'default')
            ->orderBy('id')
            ->first();

        $this->assertNotNull($defaultSnapshot);
        $this->assertTrue(File::exists($templatePath));

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-rollback'), [
                'template' => 'home',
                'version_id' => $defaultSnapshot->id,
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']));

        $this->assertFalse(File::exists($templatePath));
    }

    public function test_site_can_delete_a_custom_theme_template(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $templateName = 'page-landing';
        $templatePath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.$templateName.'.tpl';
        $attachmentId = $this->createImageAttachment($site, 'landing-cover.jpg');

        File::delete($templatePath);
        File::ensureDirectoryExists(dirname($templatePath));
        File::put($templatePath, "<img src=\"/site-media/site/attachments/2026/03/landing-cover.jpg\" alt=\"landing\">\n");
        DB::table('site_theme_template_meta')->updateOrInsert(
            [
                'site_id' => $site->id,
                'theme_code' => $themeCode,
                'template_name' => $templateName,
            ],
            [
                'title' => '活动落地页模板',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $templateMetaId = (int) DB::table('site_theme_template_meta')
            ->where('site_id', $site->id)
            ->where('theme_code', $themeCode)
            ->where('template_name', $templateName)
            ->value('id');

        DB::table('attachment_relations')->insert([
            'attachment_id' => $attachmentId,
            'relation_type' => 'theme_template',
            'relation_id' => $templateMetaId,
            'usage_slot' => 'template_image',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('attachments')->where('id', $attachmentId)->update([
            'usage_count' => 1,
            'last_used_at' => now(),
        ]);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-delete'), [
                'template' => $templateName,
            ])
            ->assertRedirect(route('admin.themes.editor'));

        $this->assertFalse(File::exists($templatePath));
        $this->assertDatabaseMissing('site_theme_template_meta', [
            'site_id' => $site->id,
            'theme_code' => $themeCode,
            'template_name' => $templateName,
        ]);
        $this->assertDatabaseMissing('attachment_relations', [
            'attachment_id' => $attachmentId,
            'relation_type' => 'theme_template',
            'relation_id' => $templateMetaId,
        ]);
        $this->assertDatabaseHas('attachments', [
            'id' => $attachmentId,
            'usage_count' => 0,
        ]);
        $this->assertDatabaseHas('operation_logs', [
            'site_id' => $site->id,
            'module' => 'theme',
            'action' => 'delete_template',
        ]);
    }

    public function test_site_can_delete_a_template_snapshot(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.update'), [
                'template' => 'home',
                'template_title' => '学校首页模板',
                'template_source' => "<section>home snapshot</section>\n",
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']));

        $snapshotId = (int) DB::table('site_theme_template_versions')
            ->where('site_id', $site->id)
            ->where('theme_code', $themeCode)
            ->where('template_name', 'home')
            ->orderByDesc('id')
            ->value('id');

        $this->assertGreaterThan(0, $snapshotId);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-snapshot-delete'), [
                'template' => 'home',
                'version_id' => $snapshotId,
            ])
            ->assertRedirect(route('admin.themes.snapshots', ['template' => 'home']));

        $this->assertDatabaseMissing('site_theme_template_versions', [
            'id' => $snapshotId,
        ]);
        $this->assertDatabaseHas('operation_logs', [
            'site_id' => $site->id,
            'module' => 'theme',
            'action' => 'delete_template_snapshot',
        ]);
    }

    public function test_site_can_favorite_a_template_snapshot(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.update'), [
                'template' => 'home',
                'template_title' => '学校首页模板',
                'template_source' => "<section>home favorite</section>\n",
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']));

        $snapshotId = (int) DB::table('site_theme_template_versions')
            ->where('site_id', $site->id)
            ->where('theme_code', $themeCode)
            ->where('template_name', 'home')
            ->orderByDesc('id')
            ->value('id');

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-snapshot-favorite'), [
                'template' => 'home',
                'version_id' => $snapshotId,
            ])
            ->assertRedirect(route('admin.themes.snapshots', ['template' => 'home']));

        $this->assertDatabaseHas('site_theme_template_versions', [
            'id' => $snapshotId,
            'is_favorite' => 1,
        ]);
        $this->assertDatabaseHas('operation_logs', [
            'site_id' => $site->id,
            'module' => 'theme',
            'action' => 'favorite_template_snapshot',
        ]);
    }

    public function test_site_template_snapshots_are_limited_to_latest_five_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);

        for ($index = 1; $index <= 7; $index++) {
            $this->actingAs($this->superAdmin())
                ->withSession(['current_site_id' => $site->id])
                ->post(route('admin.themes.editor.update'), [
                    'template' => 'home',
                    'template_title' => '学校首页模板',
                    'template_source' => "<section>home version {$index}</section>\n",
                ])
                ->assertRedirect(route('admin.themes.editor', ['template' => 'home']));
        }

        $this->assertSame(5, DB::table('site_theme_template_versions')
            ->where('site_id', $site->id)
            ->where('theme_code', $themeCode)
            ->where('template_name', 'home')
            ->whereNull('consumed_at')
            ->count());
    }

    public function test_favorite_snapshot_counts_within_latest_five_limit(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);

        for ($index = 1; $index <= 3; $index++) {
            $this->actingAs($this->superAdmin())
                ->withSession(['current_site_id' => $site->id])
                ->post(route('admin.themes.editor.update'), [
                    'template' => 'home',
                    'template_title' => '学校首页模板',
                    'template_source' => "<section>home favorite seed {$index}</section>\n",
                ])
                ->assertRedirect(route('admin.themes.editor', ['template' => 'home']));
        }

        $favoriteSnapshotId = (int) DB::table('site_theme_template_versions')
            ->where('site_id', $site->id)
            ->where('theme_code', $themeCode)
            ->where('template_name', 'home')
            ->orderBy('id')
            ->value('id');

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-snapshot-favorite'), [
                'template' => 'home',
                'version_id' => $favoriteSnapshotId,
            ])
            ->assertRedirect(route('admin.themes.snapshots', ['template' => 'home']));

        for ($index = 4; $index <= 9; $index++) {
            $this->actingAs($this->superAdmin())
                ->withSession(['current_site_id' => $site->id])
                ->post(route('admin.themes.editor.update'), [
                    'template' => 'home',
                    'template_title' => '学校首页模板',
                    'template_source' => "<section>home favorite seed {$index}</section>\n",
                ])
                ->assertRedirect(route('admin.themes.editor', ['template' => 'home']));
        }

        $this->assertDatabaseHas('site_theme_template_versions', [
            'id' => $favoriteSnapshotId,
            'is_favorite' => 1,
        ]);
        $this->assertSame(1, DB::table('site_theme_template_versions')
            ->where('site_id', $site->id)
            ->where('theme_code', $themeCode)
            ->where('template_name', 'home')
            ->where('is_favorite', 1)
            ->count());
        $this->assertSame(5, DB::table('site_theme_template_versions')
            ->where('site_id', $site->id)
            ->where('theme_code', $themeCode)
            ->where('template_name', 'home')
            ->whereNull('consumed_at')
            ->count());
        $this->assertSame(4, DB::table('site_theme_template_versions')
            ->where('site_id', $site->id)
            ->where('theme_code', $themeCode)
            ->where('template_name', 'home')
            ->whereNull('consumed_at')
            ->where('is_favorite', 0)
            ->count());
    }

    public function test_theme_asset_route_serves_css_from_theme_directory_with_css_content_type(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        $response = $this->get(route('site.theme-asset', [
            'theme' => 'site',
            'path' => 'theme.css',
            'site' => $site->site_key,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/css; charset=utf-8');
        $response->assertSee(':root', false);
    }

    public function test_theme_editor_lists_theme_css_and_js_files(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.themes.editor'))
            ->assertOk()
            ->assertSee('theme.css')
            ->assertSee('主题全局样式')
            ->assertSee('打开模板资源')
            ->assertSee('模板资源')
            ->assertDontSee('data-open-template-attachment-library', false);
    }

    public function test_theme_asset_route_rejects_non_active_theme_assets(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeId = $this->createTheme('site-alt', '备用主题');
        $themeRoot = storage_path('app/theme_templates/site-alt');

        File::ensureDirectoryExists($themeRoot);
        File::put($themeRoot.DIRECTORY_SEPARATOR.'theme.css', ':root { color: #000; }');

        DB::table('site_theme_bindings')->insert([
            'site_id' => $site->id,
            'theme_id' => $themeId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('site.theme-asset', [
            'theme' => 'site-alt',
            'path' => 'theme.css',
            'site' => $site->site_key,
        ]))->assertNotFound();
    }

    public function test_theme_editor_rejects_missing_theme_style_reference(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->from(route('admin.themes.editor', ['template' => 'home']))
            ->post(route('admin.themes.editor.update'), [
                'template' => 'home',
                'template_title' => '首页模板',
                'template_source' => "{{ themeStyle path=\"missing.css\" }}\n<section>home</section>\n",
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']))
            ->assertSessionHasErrors(['template_source']);
    }

    public function test_site_can_create_and_update_theme_css_and_js_files(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $cssPath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'landing.css';
        $jsPath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'landing.js';

        File::delete($cssPath);
        File::delete($jsPath);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-create'), [
                'template_title' => '落地页样式',
                'template_prefix' => 'css',
                'template_suffix' => 'landing',
                'starter_template' => 'blank',
                'template_source' => ".landing-page {\n    color: #0f172a;\n}\n",
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'landing.css']));

        $this->assertTrue(File::exists($cssPath));
        $this->assertStringContainsString('.landing-page', File::get($cssPath));
        $this->assertDatabaseHas('site_theme_template_meta', [
            'site_id' => $site->id,
            'theme_code' => $themeCode,
            'template_name' => 'landing.css',
            'title' => '落地页样式',
        ]);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.template-create'), [
                'template_title' => '落地页脚本',
                'template_prefix' => 'js',
                'template_suffix' => 'landing',
                'starter_template' => 'blank',
                'template_source' => "window.landingThemeBoot = true;\n",
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'landing.js']));

        $this->assertTrue(File::exists($jsPath));

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.update'), [
                'template' => 'landing.js',
                'template_title' => '落地页脚本（新）',
                'template_source' => "window.landingThemeBoot = 'updated';\n",
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'landing.js']));

        $this->assertStringContainsString("'updated'", File::get($jsPath));
        $this->assertDatabaseHas('site_theme_template_meta', [
            'site_id' => $site->id,
            'theme_code' => $themeCode,
            'template_name' => 'landing.js',
            'title' => '落地页脚本（新）',
        ]);
    }


    public function test_site_can_upload_and_delete_theme_assets(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $assetPath = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'hero-banner.png';

        File::delete($assetPath);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.asset-upload'), [
                'template' => 'home',
                'asset' => UploadedFile::fake()->image('hero-banner.png', 1600, 900),
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home', 'open_assets' => 1]));

        $this->assertTrue(File::exists($assetPath));

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->get(route('admin.themes.editor', ['template' => 'home', 'open_assets' => 1]))
            ->assertOk()
            ->assertSee('v=', false)
            ->assertSee('站点自定义资源')
            ->assertDontSee('模板资源已用 0 B');

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.asset-delete'), [
                'template' => 'home',
                'asset_path' => 'assets/hero-banner.png',
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home', 'open_assets' => 1]));

        $this->assertFalse(File::exists($assetPath));
    }

    public function test_site_uploads_duplicate_theme_asset_names_with_incremented_filename(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();
        $themeCode = $this->activeThemeCode($site);
        $assetRoot = SitePath::themeOverrideRoot($site->site_key, $themeCode).DIRECTORY_SEPARATOR.'assets';
        $firstAssetPath = $assetRoot.DIRECTORY_SEPARATOR.'hero-banner.png';
        $secondAssetPath = $assetRoot.DIRECTORY_SEPARATOR.'hero-banner-2.png';

        File::delete($firstAssetPath);
        File::delete($secondAssetPath);

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.asset-upload'), [
                'template' => 'home',
                'asset' => UploadedFile::fake()->image('hero-banner.png', 1600, 900),
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home', 'open_assets' => 1]));

        $this->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.asset-upload'), [
                'template' => 'home',
                'asset' => UploadedFile::fake()->image('hero-banner.png', 1200, 800),
            ])
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home', 'open_assets' => 1]));

        $this->assertTrue(File::exists($firstAssetPath));
        $this->assertTrue(File::exists($secondAssetPath));

    }

    public function test_theme_asset_upload_respects_site_total_storage_limit(): void
    {
        $this->seed(DatabaseSeeder::class);

        $site = $this->demoSite();

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $site->id, 'setting_key' => 'attachment.storage_limit_mb'],
            ['setting_value' => '1', 'autoload' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        DB::table('attachments')->insert([
            'site_id' => $site->id,
            'origin_name' => 'existing.pdf',
            'stored_name' => 'existing.pdf',
            'disk' => 'site',
            'path' => 'web/site/media/attachments/existing.pdf',
            'url' => 'http://127.0.0.1:8000/site-media/site/attachments/existing.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 900 * 1024,
            'uploaded_by' => $this->superAdmin()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->from(route('admin.themes.editor', ['template' => 'home']))
            ->actingAs($this->superAdmin())
            ->withSession(['current_site_id' => $site->id])
            ->post(route('admin.themes.editor.asset-upload'), [
                'template' => 'home',
                'asset' => UploadedFile::fake()->create('theme-large.jpg', 200, 'image/jpeg'),
            ]);

        $response
            ->assertRedirect(route('admin.themes.editor', ['template' => 'home']))
            ->assertSessionHasErrorsIn('themeAssets', ['asset']);
    }
}
