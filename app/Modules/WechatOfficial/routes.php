<?php

use App\Modules\WechatOfficial\Controllers\Admin\Site\WechatOfficialController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin.access', 'module.admin.active:wechat_official'])
    ->prefix('admin/site/wechat-official')
    ->group(function (): void {
        Route::get('/', [WechatOfficialController::class, 'index'])->name('admin.wechat-official.index');
        Route::get('/settings', [WechatOfficialController::class, 'settings'])->name('admin.wechat-official.settings');
        Route::post('/settings', [WechatOfficialController::class, 'updateSettings'])->name('admin.wechat-official.settings.update');
        Route::post('/settings/check', [WechatOfficialController::class, 'checkSettings'])->name('admin.wechat-official.settings.check');
        Route::get('/menus', [WechatOfficialController::class, 'menus'])->name('admin.wechat-official.menus');
        Route::post('/menus', [WechatOfficialController::class, 'storeMenu'])->name('admin.wechat-official.menus.store');
        Route::post('/menus/{menu}', [WechatOfficialController::class, 'updateMenu'])->whereNumber('menu')->name('admin.wechat-official.menus.update');
        Route::post('/menus/{menu}/delete', [WechatOfficialController::class, 'destroyMenu'])->whereNumber('menu')->name('admin.wechat-official.menus.destroy');
        Route::post('/menus/pull', [WechatOfficialController::class, 'pullMenus'])->name('admin.wechat-official.menus.pull');
        Route::post('/menus/sync', [WechatOfficialController::class, 'syncMenus'])->name('admin.wechat-official.menus.sync');
        Route::get('/articles', [WechatOfficialController::class, 'articles'])->name('admin.wechat-official.articles');
        Route::post('/articles/{content}/draft', [WechatOfficialController::class, 'syncArticleDraft'])->whereNumber('content')->name('admin.wechat-official.articles.draft');
        Route::post('/articles/{content}/publish', [WechatOfficialController::class, 'publishArticle'])->whereNumber('content')->name('admin.wechat-official.articles.publish');
        Route::post('/articles/{content}/publish-status', [WechatOfficialController::class, 'queryArticlePublishStatus'])->whereNumber('content')->name('admin.wechat-official.articles.publish-status');
        Route::get('/materials', [WechatOfficialController::class, 'materials'])->name('admin.wechat-official.materials');
        Route::post('/materials/{attachment}/sync', [WechatOfficialController::class, 'syncMaterial'])->whereNumber('attachment')->name('admin.wechat-official.materials.sync');
        Route::get('/logs', [WechatOfficialController::class, 'logs'])->name('admin.wechat-official.logs');
    });
