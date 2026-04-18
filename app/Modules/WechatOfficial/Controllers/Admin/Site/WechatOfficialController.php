<?php

namespace App\Modules\WechatOfficial\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Modules\WechatOfficial\Support\WechatOfficialApi;
use App\Modules\WechatOfficial\Support\WechatOfficialArticleService;
use App\Modules\WechatOfficial\Support\WechatOfficialLogger;
use App\Modules\WechatOfficial\Support\WechatOfficialMaterialService;
use App\Modules\WechatOfficial\Support\WechatOfficialModule;
use App\Modules\WechatOfficial\Support\WechatOfficialMenuService;
use App\Modules\WechatOfficial\Support\WechatOfficialSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class WechatOfficialController extends Controller
{
    public function __construct(
        protected WechatOfficialModule $wechatOfficialModule,
        protected WechatOfficialSettings $wechatOfficialSettings,
        protected WechatOfficialMenuService $wechatOfficialMenuService,
        protected WechatOfficialArticleService $wechatOfficialArticleService,
        protected WechatOfficialMaterialService $wechatOfficialMaterialService,
        protected WechatOfficialApi $wechatOfficialApi,
        protected WechatOfficialLogger $wechatOfficialLogger
    ) {
    }

    public function index(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'wechat_official.view');

        if (! $this->wechatOfficialModule->activeForSite($siteId)) {
            return redirect()
                ->route('admin.wechat-official.settings')
                ->withErrors(['module' => '微信公众号模块当前已关闭，请先在配置页重新启用。']);
        }

        return redirect()->route('admin.wechat-official.articles');
    }

    public function settings(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'wechat_official.setting');

        return view('wechat_official::admin.settings', $this->pageData($request, 'settings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'wechat_official.setting');
        $this->resolveModuleOrAbort((int) $currentSite->id);
        $currentSettings = $this->wechatOfficialSettings->forSite((int) $currentSite->id);

        $request->merge([
            'official_name' => trim((string) $request->input('official_name')),
            'app_id' => trim((string) $request->input('app_id')),
            'app_secret' => trim((string) $request->input('app_secret')),
            'token' => trim((string) $request->input('token')),
            'encoding_aes_key' => trim((string) $request->input('encoding_aes_key')),
        ]);

        $validated = Validator::make(
            $request->all(),
            [
                'enabled' => ['nullable', 'boolean'],
                'official_name' => ['required', 'string', 'max:80'],
                'app_id' => ['required', 'string', 'max:100'],
                'app_secret' => ['nullable', 'string', 'max:150'],
                'token' => ['nullable', 'string', 'max:150'],
                'encoding_aes_key' => ['nullable', 'string', 'size:43'],
            ],
            [
                'official_name.required' => '请填写公众号名称。',
                'app_id.required' => '请填写 AppID。',
                'encoding_aes_key.size' => 'EncodingAESKey 长度必须为 43 位。',
            ]
        )->validate();

        $this->wechatOfficialSettings->saveForSite((int) $currentSite->id, [
            'enabled' => $request->boolean('enabled'),
            'official_name' => $validated['official_name'],
            'app_id' => $validated['app_id'],
            'app_secret' => ($validated['app_secret'] ?? '') !== ''
                ? $validated['app_secret']
                : ($currentSettings['app_secret'] ?? ''),
            'token' => ($validated['token'] ?? '') !== ''
                ? $validated['token']
                : ($currentSettings['token'] ?? ''),
            'encoding_aes_key' => ($validated['encoding_aes_key'] ?? '') !== ''
                ? $validated['encoding_aes_key']
                : ($currentSettings['encoding_aes_key'] ?? ''),
        ], (int) $request->user()->id);

        return redirect()
            ->route('admin.wechat-official.settings')
            ->with('status', '微信公众号模块配置已保存。');
    }

    public function checkSettings(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'wechat_official.setting');
        $this->resolveModuleOrAbort($siteId);
        $currentSettings = $this->wechatOfficialSettings->forSite($siteId);

        $request->merge([
            'official_name' => trim((string) $request->input('official_name')),
            'app_id' => trim((string) $request->input('app_id')),
            'app_secret' => trim((string) $request->input('app_secret')),
            'token' => trim((string) $request->input('token')),
            'encoding_aes_key' => trim((string) $request->input('encoding_aes_key')),
        ]);

        $validated = Validator::make(
            $request->all(),
            [
                'enabled' => ['nullable', 'boolean'],
                'official_name' => ['required', 'string', 'max:80'],
                'app_id' => ['required', 'string', 'max:100'],
                'app_secret' => ['nullable', 'string', 'max:150'],
                'token' => ['nullable', 'string', 'max:150'],
                'encoding_aes_key' => ['nullable', 'string', 'size:43'],
            ],
            [
                'official_name.required' => '请填写公众号名称。',
                'app_id.required' => '请填写 AppID。',
                'encoding_aes_key.size' => 'EncodingAESKey 长度必须为 43 位。',
            ]
        )->validate();

        $settings = [
            'enabled' => $request->boolean('enabled'),
            'official_name' => $validated['official_name'],
            'app_id' => $validated['app_id'],
            'app_secret' => ($validated['app_secret'] ?? '') !== ''
                ? $validated['app_secret']
                : ($currentSettings['app_secret'] ?? ''),
            'token' => ($validated['token'] ?? '') !== ''
                ? $validated['token']
                : ($currentSettings['token'] ?? ''),
            'encoding_aes_key' => ($validated['encoding_aes_key'] ?? '') !== ''
                ? $validated['encoding_aes_key']
                : ($currentSettings['encoding_aes_key'] ?? ''),
        ];

        try {
            $message = $this->wechatOfficialApi->checkConnection($siteId, $settings, (int) $request->user()->id);
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.wechat-official.settings')
                ->withInput($request->except(['app_secret', 'token', 'encoding_aes_key']))
                ->withErrors(['wechat_settings_check' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.wechat-official.settings')
            ->with('status', $message);
    }

    public function menus(Request $request): View|RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'wechat_official.menu');
        $redirect = $this->ensureActiveModuleOrRedirectResponse($siteId);
        if ($redirect) {
            return $redirect;
        }

        $menuGroups = $this->wechatOfficialMenuService->groupedForSite($siteId);
        $topMenus = $this->wechatOfficialMenuService->topLevelForSite($siteId);
        $settings = $this->wechatOfficialSettings->forSite($siteId);

        return view('wechat_official::admin.menus', array_merge($this->pageData($request, 'menus'), [
            'wechatMenuGroups' => $menuGroups,
            'wechatTopMenus' => $topMenus,
            'wechatMenuTypes' => WechatOfficialMenuService::TYPES,
            'wechatMenuSyncReady' => $settings['app_id'] !== '' && $settings['app_secret'] !== '',
            'wechatOfficialName' => trim((string) ($settings['official_name'] ?? '')) !== '' ? trim((string) $settings['official_name']) : '微信公众号',
        ]));
    }

    public function storeMenu(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'wechat_official.menu');
        $redirect = $this->ensureActiveModuleOrRedirectResponse($siteId);
        if ($redirect) {
            return $redirect;
        }
        $this->resolveModuleOrAbort($siteId);

        $validated = $this->validateMenuRequest($request, $siteId);
        $this->wechatOfficialMenuService->validateBusinessRules($siteId, $validated);

        DB::table('module_wechat_official_menus')->insert([
            'site_id' => $siteId,
            'parent_id' => $validated['level'] === 2 ? (int) $validated['parent_id'] : null,
            'level' => (int) $validated['level'],
            'sort' => (int) ($validated['sort'] ?? 0),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'key' => $validated['type'] === 'click' ? ($validated['key'] ?? '') : '',
            'url' => $validated['type'] === 'view' ? ($validated['url'] ?? '') : '',
            'media_id' => $validated['type'] === 'media_id' ? ($validated['media_id'] ?? '') : '',
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->wechatOfficialLogger->record(
            $siteId,
            'menu_create',
            'success',
            '公众号菜单已新增。',
            [
                'name' => $validated['name'],
                'level' => (int) $validated['level'],
                'type' => $validated['type'],
            ],
            [],
            (int) $request->user()->id,
            'admin'
        );

        return redirect()->route('admin.wechat-official.menus')->with('status', '公众号菜单已新增。');
    }

    public function updateMenu(Request $request, int $menu): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'wechat_official.menu');
        $redirect = $this->ensureActiveModuleOrRedirectResponse($siteId);
        if ($redirect) {
            return $redirect;
        }
        $this->resolveModuleOrAbort($siteId);

        $menuRow = DB::table('module_wechat_official_menus')
            ->where('site_id', $siteId)
            ->where('id', $menu)
            ->first();
        abort_unless($menuRow, 404);

        $validated = $this->validateMenuRequest($request, $siteId, $menu);
        $this->wechatOfficialMenuService->validateBusinessRules($siteId, $validated, $menu);

        DB::table('module_wechat_official_menus')
            ->where('site_id', $siteId)
            ->where('id', $menu)
            ->update([
                'parent_id' => $validated['level'] === 2 ? (int) $validated['parent_id'] : null,
                'level' => (int) $validated['level'],
                'sort' => (int) ($validated['sort'] ?? 0),
                'name' => $validated['name'],
                'type' => $validated['type'],
                'key' => $validated['type'] === 'click' ? ($validated['key'] ?? '') : '',
                'url' => $validated['type'] === 'view' ? ($validated['url'] ?? '') : '',
                'media_id' => $validated['type'] === 'media_id' ? ($validated['media_id'] ?? '') : '',
                'updated_at' => now(),
            ]);

        $this->wechatOfficialLogger->record(
            $siteId,
            'menu_update',
            'success',
            '公众号菜单已更新。',
            [
                'menu_id' => $menu,
                'name' => $validated['name'],
                'level' => (int) $validated['level'],
                'type' => $validated['type'],
            ],
            [],
            (int) $request->user()->id,
            'admin'
        );

        return redirect()->route('admin.wechat-official.menus')->with('status', '公众号菜单已更新。');
    }

    public function destroyMenu(Request $request, int $menu): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'wechat_official.menu');
        $redirect = $this->ensureActiveModuleOrRedirectResponse($siteId);
        if ($redirect) {
            return $redirect;
        }
        $this->resolveModuleOrAbort($siteId);

        DB::table('module_wechat_official_menus')
            ->where('site_id', $siteId)
            ->where(function ($query) use ($menu): void {
                $query->where('id', $menu)->orWhere('parent_id', $menu);
            })
            ->delete();

        $this->wechatOfficialLogger->record(
            $siteId,
            'menu_delete',
            'success',
            '公众号菜单已删除。',
            ['menu_id' => $menu],
            [],
            (int) $request->user()->id,
            'admin'
        );

        return redirect()->route('admin.wechat-official.menus')->with('status', '公众号菜单已删除。');
    }

    public function syncMenus(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'wechat_official.menu');
        $redirect = $this->ensureActiveModuleOrRedirectResponse($siteId);
        if ($redirect) {
            return $redirect;
        }
        $this->resolveModuleOrAbort($siteId);
        $settings = $this->wechatOfficialSettings->forSite($siteId);
        $buttons = $this->wechatOfficialMenuService->buildWechatButtons($siteId);

        if ($buttons === []) {
            return redirect()->route('admin.wechat-official.menus')->withErrors(['menu_sync' => '请先至少创建一个可同步的公众号菜单。']);
        }

        try {
            $this->wechatOfficialApi->syncMenus($siteId, $settings, $buttons, (int) $request->user()->id);
        } catch (Throwable $exception) {
            return redirect()->route('admin.wechat-official.menus')->withErrors(['menu_sync' => $exception->getMessage()]);
        }

        return redirect()->route('admin.wechat-official.menus')->with('status', '公众号菜单已同步到微信。');
    }

    public function pullMenus(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'wechat_official.menu');
        $redirect = $this->ensureActiveModuleOrRedirectResponse($siteId);
        if ($redirect) {
            return $redirect;
        }
        $this->resolveModuleOrAbort($siteId);
        $settings = $this->wechatOfficialSettings->forSite($siteId);

        try {
            $buttons = $this->wechatOfficialApi->pullMenus($siteId, $settings, (int) $request->user()->id);
            $normalizedMenus = $this->normalizePulledWechatMenus($buttons);
            $this->replaceSiteMenusFromWechat($siteId, $normalizedMenus, (int) $request->user()->id);
        } catch (Throwable $exception) {
            return redirect()->route('admin.wechat-official.menus')->withErrors(['menu_pull' => $exception->getMessage()]);
        }

        return redirect()->route('admin.wechat-official.menus')->with('status', '公众号菜单已同步到当前页面。');
    }

    public function articles(Request $request): View|RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'wechat_official.publish');
        $redirect = $this->ensureActiveModuleOrRedirectResponse($siteId);
        if ($redirect) {
            return $redirect;
        }

        $keyword = trim((string) $request->query('keyword', ''));
        $pushStatus = trim((string) $request->query('push_status', ''));
        $articles = $this->wechatOfficialArticleService->paginatedPublishedArticles($siteId, $keyword, $pushStatus);
        $recentPushes = $this->wechatOfficialArticleService->recentPushes($siteId);
        $settings = $this->wechatOfficialSettings->forSite($siteId);
        $syncReady = $settings['enabled']
            && trim((string) ($settings['app_id'] ?? '')) !== ''
            && trim((string) ($settings['app_secret'] ?? '')) !== '';

        return view('wechat_official::admin.articles', array_merge($this->pageData($request, 'articles'), [
            'wechatArticles' => $articles,
            'wechatRecentPushes' => $recentPushes,
            'wechatArticleKeyword' => $keyword,
            'wechatArticlePushStatus' => $pushStatus,
            'wechatArticleSyncReady' => $syncReady,
        ]));
    }

    public function syncArticleDraft(Request $request, int $content): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'wechat_official.publish');
        $redirect = $this->ensureActiveModuleOrRedirectResponse($siteId);
        if ($redirect) {
            return $redirect;
        }
        $this->resolveModuleOrAbort($siteId);

        $settings = $this->wechatOfficialSettings->forSite($siteId);
        if (! $settings['enabled']) {
            return redirect()
                ->route('admin.wechat-official.articles')
                ->withErrors(['article_sync' => '公众号文章推送当前不可用，请先在公众号配置中完善基础参数。']);
        }

        $validated = Validator::make(
            [
                'thumb_media_id' => trim((string) $request->input('thumb_media_id')),
                'content_source_url' => trim((string) $request->input('content_source_url')),
            ],
            [
                'thumb_media_id' => ['nullable', 'string', 'max:120'],
                'content_source_url' => ['nullable', 'url', 'max:1000'],
            ],
            [
                'content_source_url.url' => '原文链接必须为完整有效的 URL。',
            ]
        )->validate();

        $contentTitle = '';
        $thumbMediaId = trim((string) ($validated['thumb_media_id'] ?? ''));
        if ($thumbMediaId === '') {
            $thumbMediaId = $this->wechatOfficialArticleService->recommendedThumbMediaId($siteId, $content);
        }

        if ($thumbMediaId === '') {
            return redirect()
                ->route('admin.wechat-official.articles')
                ->withErrors(['article_sync' => '请填写封面素材 MediaID，或先将文章封面图同步到公众号素材库。']);
        }

        try {
            $draftPayload = $this->wechatOfficialArticleService->buildDraftPayload(
                $siteId,
                $content,
                $thumbMediaId,
                $validated['content_source_url'] !== '' ? $validated['content_source_url'] : null
            );

            $contentTitle = trim((string) $draftPayload['draft']['title']);
            $draftMediaId = $this->wechatOfficialApi->createDraft(
                $siteId,
                $settings,
                $draftPayload['draft'],
                (int) $request->user()->id
            );

            $this->storeArticlePushRecord($siteId, $content, $contentTitle, [
                'status' => 'draft_ready',
                'draft_media_id' => $draftMediaId,
                'error_message' => null,
                'updated_by' => (int) $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            $this->storeArticlePushRecord($siteId, $content, $contentTitle, [
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'updated_by' => (int) $request->user()->id,
            ]);

            return redirect()
                ->route('admin.wechat-official.articles')
                ->withErrors(['article_sync' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.wechat-official.articles')
            ->with('status', '公众号草稿已生成，可继续在微信后台进行审核与发布。');
    }

    public function publishArticle(Request $request, int $content): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'wechat_official.publish');
        $redirect = $this->ensureActiveModuleOrRedirectResponse($siteId);
        if ($redirect) {
            return $redirect;
        }
        $this->resolveModuleOrAbort($siteId);

        $settings = $this->wechatOfficialSettings->forSite($siteId);
        if (! $settings['enabled']) {
            return redirect()
                ->route('admin.wechat-official.articles')
                ->withErrors(['article_publish' => '公众号文章推送当前不可用，请先在公众号配置中完善基础参数。']);
        }

        $record = DB::table('module_wechat_official_article_pushes')
            ->where('site_id', $siteId)
            ->where('content_id', $content)
            ->orderByDesc('id')
            ->first([
                'id',
                'title',
                'draft_media_id',
            ]);

        if (! $record || trim((string) ($record->draft_media_id ?? '')) === '') {
            return redirect()
                ->route('admin.wechat-official.articles')
                ->withErrors(['article_publish' => '请先生成公众号草稿后再执行发布。']);
        }

        try {
            $publishId = $this->wechatOfficialApi->publishDraft(
                $siteId,
                $settings,
                trim((string) $record->draft_media_id),
                (int) $request->user()->id
            );

            $this->storeArticlePushRecord($siteId, $content, trim((string) ($record->title ?? '')), [
                'status' => 'publishing',
                'publish_id' => $publishId,
                'error_message' => null,
                'published_at' => now(),
                'updated_by' => (int) $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            $this->storeArticlePushRecord($siteId, $content, trim((string) ($record->title ?? '')), [
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'updated_by' => (int) $request->user()->id,
            ]);

            return redirect()
                ->route('admin.wechat-official.articles')
                ->withErrors(['article_publish' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.wechat-official.articles')
            ->with('status', '公众号发布任务已提交，请稍后在微信后台确认结果。');
    }

    public function queryArticlePublishStatus(Request $request, int $content): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'wechat_official.publish');
        $redirect = $this->ensureActiveModuleOrRedirectResponse($siteId);
        if ($redirect) {
            return $redirect;
        }
        $this->resolveModuleOrAbort($siteId);

        $settings = $this->wechatOfficialSettings->forSite($siteId);
        $record = DB::table('module_wechat_official_article_pushes')
            ->where('site_id', $siteId)
            ->where('content_id', $content)
            ->orderByDesc('id')
            ->first([
                'id',
                'title',
                'draft_media_id',
                'publish_id',
            ]);

        if (! $record || trim((string) ($record->publish_id ?? '')) === '') {
            return redirect()
                ->route('admin.wechat-official.articles')
                ->withErrors(['article_publish_query' => '当前文章还没有可查询的发布任务。']);
        }

        try {
            $result = $this->wechatOfficialApi->queryPublishStatus(
                $siteId,
                $settings,
                trim((string) $record->publish_id),
                (int) $request->user()->id
            );

            $this->storeArticlePushRecord($siteId, $content, trim((string) ($record->title ?? '')), [
                'status' => $result['status'],
                'draft_media_id' => trim((string) ($record->draft_media_id ?? '')),
                'publish_id' => trim((string) ($record->publish_id ?? '')),
                'error_message' => $result['status'] === 'publish_failed' ? $result['message'] : null,
                'published_at' => $result['status'] === 'published' ? now() : null,
                'updated_by' => (int) $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.wechat-official.articles')
                ->withErrors(['article_publish_query' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.wechat-official.articles')
            ->with('status', $result['message']);
    }

    public function materials(Request $request): View|RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'wechat_official.manage');
        $redirect = $this->ensureActiveModuleOrRedirectResponse($siteId);
        if ($redirect) {
            return $redirect;
        }

        $keyword = trim((string) $request->query('keyword', ''));
        $syncStatus = trim((string) $request->query('sync_status', ''));
        $attachments = $this->wechatOfficialMaterialService->paginatedImageAttachments($siteId, $keyword, $syncStatus);
        $recentMaterials = $this->wechatOfficialMaterialService->recentMaterials($siteId);
        $settings = $this->wechatOfficialSettings->forSite($siteId);
        $syncReady = $settings['enabled']
            && trim((string) ($settings['app_id'] ?? '')) !== ''
            && trim((string) ($settings['app_secret'] ?? '')) !== '';

        return view('wechat_official::admin.materials', array_merge($this->pageData($request, 'materials'), [
            'wechatMaterialAttachments' => $attachments,
            'wechatRecentMaterials' => $recentMaterials,
            'wechatMaterialKeyword' => $keyword,
            'wechatMaterialSyncStatus' => $syncStatus,
            'wechatMaterialSyncReady' => $syncReady,
        ]));
    }

    public function syncMaterial(Request $request, int $attachment): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'wechat_official.manage');
        $redirect = $this->ensureActiveModuleOrRedirectResponse($siteId);
        if ($redirect) {
            return $redirect;
        }
        $this->resolveModuleOrAbort($siteId);

        $settings = $this->wechatOfficialSettings->forSite($siteId);
        if (! $settings['enabled']) {
            return redirect()
                ->route('admin.wechat-official.materials')
                ->withErrors(['material_sync' => '公众号素材同步当前不可用，请先在公众号配置中完善基础参数。']);
        }

        try {
            $material = $this->wechatOfficialMaterialService->resolveImageAttachment($siteId, $attachment);
            $result = $this->wechatOfficialApi->syncImageMaterial(
                $siteId,
                $settings,
                $material->absolute_path,
                (string) $material->attachment->origin_name,
                (int) $request->user()->id
            );

            $this->storeMaterialRecord($siteId, (int) $material->attachment->id, (string) $material->attachment->origin_name, [
                'type' => 'image',
                'wechat_media_id' => $result['media_id'],
                'wechat_url' => $result['url'],
                'file_path' => (string) $material->attachment->path,
                'file_size' => (int) ($material->attachment->size ?? 0),
                'synced_at' => now(),
            ]);
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.wechat-official.materials')
                ->withErrors(['material_sync' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.wechat-official.materials')
            ->with('status', '公众号图片素材已同步。');
    }

    public function logs(Request $request): View|RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $this->authorizeSite($request, $siteId, 'wechat_official.manage');
        $redirect = $this->ensureActiveModuleOrRedirectResponse($siteId);
        if ($redirect) {
            return $redirect;
        }

        $logs = DB::table('module_wechat_official_logs')
            ->where('site_id', $siteId)
            ->orderByDesc('id')
            ->paginate(20);

        return view('wechat_official::admin.logs', array_merge($this->pageData($request, 'logs'), [
            'wechatOfficialLogs' => $logs,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function pageData(Request $request, string $activeTab): array
    {
        $currentSite = $this->currentSite($request);
        $siteId = (int) $currentSite->id;
        $module = $this->resolveModuleOrAbort($siteId);
        $settings = $this->wechatOfficialSettings->forSite($siteId);

        return [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'module' => $module,
            'settings' => $settings,
            'wechatOfficialModuleEnabled' => (bool) ($settings['enabled'] ?? false),
            'activeWechatOfficialTab' => $activeTab,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateMenuRequest(Request $request, int $siteId, int $ignoreMenuId = 0): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'key' => trim((string) $request->input('key')),
            'url' => trim((string) $request->input('url')),
            'media_id' => trim((string) $request->input('media_id')),
            'sort' => (int) $request->input('sort', 0),
            'level' => (int) $request->input('level', 1),
            'parent_id' => $request->filled('parent_id') ? (int) $request->input('parent_id') : null,
        ]);

        return Validator::make(
            $request->all(),
            [
                'name' => ['required', 'string', 'max:60'],
                'level' => ['required', 'integer', Rule::in([1, 2])],
                'parent_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('module_wechat_official_menus', 'id')->where(fn ($query) => $query->where('site_id', $siteId)),
                ],
                'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
                'type' => ['required', Rule::in(array_keys(WechatOfficialMenuService::TYPES))],
                'key' => ['nullable', 'string', 'max:120'],
                'url' => ['nullable', 'url', 'max:1000'],
                'media_id' => ['nullable', 'string', 'max:120'],
            ],
            [
                'name.required' => '请填写菜单名称。',
                'level.required' => '请选择菜单层级。',
                'type.required' => '请选择菜单类型。',
                'url.url' => '访问链接必须为完整有效的 URL。',
            ]
        )->after(function ($validator) use ($request, $ignoreMenuId): void {
            $type = (string) $request->input('type', '');

            if ($type === 'view' && trim((string) $request->input('url')) === '') {
                $validator->errors()->add('url', '访问链接类型必须填写 URL。');
            }

            if ($type === 'click' && trim((string) $request->input('key')) === '') {
                $validator->errors()->add('key', '点击事件类型必须填写事件 KEY。');
            }

            if ($type === 'media_id' && trim((string) $request->input('media_id')) === '') {
                $validator->errors()->add('media_id', '下发素材类型必须填写素材 MediaID。');
            }

            if ($ignoreMenuId > 0 && (int) $request->input('parent_id', 0) === $ignoreMenuId) {
                $validator->errors()->add('parent_id', '菜单不能选择自己作为上级菜单。');
            }
        })->validate();
    }

    /**
     * @param array<int, array<string, mixed>> $buttons
     * @return array<int, array<string, mixed>>
     */
    protected function normalizePulledWechatMenus(array $buttons): array
    {
        $normalized = [];

        foreach (array_values($buttons) as $topIndex => $button) {
            if (! is_array($button)) {
                continue;
            }

            $parentName = trim((string) ($button['name'] ?? ''));
            if ($parentName === '') {
                continue;
            }

            $children = $button['sub_button']['list'] ?? $button['sub_button'] ?? [];
            $children = is_array($children) ? array_values(array_filter($children, 'is_array')) : [];

            $parent = [
                'level' => 1,
                'sort' => $topIndex,
                'name' => $parentName,
                'type' => 'view',
                'key' => '',
                'url' => '',
                'media_id' => '',
                'children' => [],
            ];

            if ($children !== []) {
                foreach ($children as $childIndex => $child) {
                    $parent['children'][] = $this->normalizePulledWechatButton($child, 2, $childIndex);
                }
            } else {
                $buttonData = $this->normalizePulledWechatButton($button, 1, $topIndex);
                $parent['type'] = $buttonData['type'];
                $parent['key'] = $buttonData['key'];
                $parent['url'] = $buttonData['url'];
                $parent['media_id'] = $buttonData['media_id'];
            }

            $normalized[] = $parent;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $button
     * @return array<string, mixed>
     */
    protected function normalizePulledWechatButton(array $button, int $level, int $sort): array
    {
        $name = trim((string) ($button['name'] ?? ''));
        $type = trim((string) ($button['type'] ?? 'view'));
        $url = trim((string) ($button['url'] ?? ''));
        $key = trim((string) ($button['key'] ?? ''));
        $mediaId = trim((string) (($button['media_id'] ?? '') ?: ($button['article_id'] ?? '')));

        return match ($type) {
            'click' => [
                'level' => $level,
                'sort' => $sort,
                'name' => $name,
                'type' => 'click',
                'key' => $key,
                'url' => '',
                'media_id' => '',
            ],
            'media_id', 'view_limited', 'article_id', 'article_view_limited' => [
                'level' => $level,
                'sort' => $sort,
                'name' => $name,
                'type' => 'media_id',
                'key' => '',
                'url' => '',
                'media_id' => $mediaId,
            ],
            'view', 'miniprogram' => [
                'level' => $level,
                'sort' => $sort,
                'name' => $name,
                'type' => 'view',
                'key' => '',
                'url' => $url,
                'media_id' => '',
            ],
            default => throw new \RuntimeException('公众号菜单中存在当前系统暂不支持的菜单类型：'.$type),
        };
    }

    /**
     * @param array<int, array<string, mixed>> $menus
     */
    protected function replaceSiteMenusFromWechat(int $siteId, array $menus, int $userId): void
    {
        DB::transaction(function () use ($siteId, $menus): void {
            DB::table('module_wechat_official_menus')
                ->where('site_id', $siteId)
                ->delete();

            foreach ($menus as $parent) {
                $parentId = (int) DB::table('module_wechat_official_menus')->insertGetId([
                    'site_id' => $siteId,
                    'parent_id' => null,
                    'level' => 1,
                    'sort' => (int) ($parent['sort'] ?? 0),
                    'name' => (string) ($parent['name'] ?? ''),
                    'type' => (string) ($parent['type'] ?? 'view'),
                    'key' => (string) ($parent['key'] ?? ''),
                    'url' => (string) ($parent['url'] ?? ''),
                    'media_id' => (string) ($parent['media_id'] ?? ''),
                    'is_enabled' => 1,
                    'last_synced_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach (($parent['children'] ?? []) as $child) {
                    DB::table('module_wechat_official_menus')->insert([
                        'site_id' => $siteId,
                        'parent_id' => $parentId,
                        'level' => 2,
                        'sort' => (int) ($child['sort'] ?? 0),
                        'name' => (string) ($child['name'] ?? ''),
                        'type' => (string) ($child['type'] ?? 'view'),
                        'key' => (string) ($child['key'] ?? ''),
                        'url' => (string) ($child['url'] ?? ''),
                        'media_id' => (string) ($child['media_id'] ?? ''),
                        'is_enabled' => 1,
                        'last_synced_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        $this->wechatOfficialLogger->record(
            $siteId,
            'menu_pull_apply',
            'success',
            '公众号菜单已覆盖到当前页面。',
            ['menu_count' => count($menus)],
            [],
            $userId,
            'admin'
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveModuleOrAbort(int $siteId): array
    {
        $module = $this->wechatOfficialModule->boundForSite($siteId);
        abort_unless(is_array($module), 404);

        return $module;
    }

    protected function ensureActiveModuleOrRedirectResponse(int $siteId): ?RedirectResponse
    {
        if ($this->wechatOfficialModule->activeForSite($siteId)) {
            return null;
        }

        return redirect()
            ->route('admin.wechat-official.settings')
            ->withErrors(['module' => '微信公众号模块当前已关闭，请先在配置页重新启用。']);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function storeArticlePushRecord(int $siteId, int $contentId, string $title, array $attributes): void
    {
        $currentRecord = DB::table('module_wechat_official_article_pushes')
            ->where('site_id', $siteId)
            ->where('content_id', $contentId)
            ->orderByDesc('id')
            ->first();

        $payload = array_merge([
            'site_id' => $siteId,
            'content_id' => $contentId,
            'title' => $title,
            'status' => 'draft',
            'draft_media_id' => '',
            'publish_id' => '',
            'error_message' => null,
            'published_at' => null,
            'created_by' => (int) ($attributes['updated_by'] ?? 0),
            'updated_by' => (int) ($attributes['updated_by'] ?? 0),
            'updated_at' => now(),
        ], $attributes);

        if ($currentRecord) {
            $payload['draft_media_id'] = array_key_exists('draft_media_id', $attributes)
                ? (string) $payload['draft_media_id']
                : (string) ($currentRecord->draft_media_id ?? '');
            $payload['publish_id'] = array_key_exists('publish_id', $attributes)
                ? (string) $payload['publish_id']
                : (string) ($currentRecord->publish_id ?? '');
            $payload['error_message'] = array_key_exists('error_message', $attributes)
                ? $payload['error_message']
                : ($currentRecord->error_message ?? null);
            $payload['published_at'] = array_key_exists('published_at', $attributes)
                ? $payload['published_at']
                : ($currentRecord->published_at ?? null);
            unset($payload['site_id'], $payload['content_id'], $payload['created_by']);
            DB::table('module_wechat_official_article_pushes')
                ->where('id', (int) $currentRecord->id)
                ->update($payload);

            return;
        }

        $payload['created_at'] = now();
        DB::table('module_wechat_official_article_pushes')->insert($payload);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function storeMaterialRecord(int $siteId, int $attachmentId, string $title, array $attributes): void
    {
        $currentRecordId = DB::table('module_wechat_official_materials')
            ->where('site_id', $siteId)
            ->where('attachment_id', $attachmentId)
            ->orderByDesc('id')
            ->value('id');

        $payload = array_merge([
            'site_id' => $siteId,
            'attachment_id' => $attachmentId,
            'title' => $title,
            'type' => 'image',
            'wechat_media_id' => '',
            'wechat_url' => '',
            'file_path' => '',
            'file_size' => 0,
            'synced_at' => null,
            'updated_at' => now(),
        ], $attributes);

        if ($currentRecordId) {
            unset($payload['site_id'], $payload['attachment_id']);
            DB::table('module_wechat_official_materials')
                ->where('id', $currentRecordId)
                ->update($payload);

            return;
        }

        $payload['created_at'] = now();
        DB::table('module_wechat_official_materials')->insert($payload);
    }
}
