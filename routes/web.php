<?php

use App\Http\Controllers\Admin\AdminEntryController;
use App\Http\Controllers\Admin\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Admin\Platform\DatabaseController as PlatformDatabaseController;
use App\Http\Controllers\Admin\Platform\ModuleController as PlatformModuleController;
use App\Http\Controllers\Admin\Platform\OperationLogController as PlatformOperationLogController;
use App\Http\Controllers\Admin\Platform\PlatformRoleController;
use App\Http\Controllers\Admin\Platform\PlatformSiteController;
use App\Http\Controllers\Admin\Platform\PlatformUserController;
use App\Http\Controllers\Admin\Platform\SystemCheckController;
use App\Http\Controllers\Admin\Platform\SystemSettingController;
use App\Http\Controllers\Admin\Site\AttachmentController;
use App\Http\Controllers\Admin\Site\ArticleReviewController;
use App\Http\Controllers\Admin\Site\ChannelController;
use App\Http\Controllers\Admin\Site\ContentController;
use App\Http\Controllers\Admin\Site\DashboardController as SiteDashboardController;
use App\Http\Controllers\Admin\Site\ModuleController as SiteModuleController;
use App\Http\Controllers\Admin\Site\OperationLogController as SiteOperationLogController;
use App\Http\Controllers\Admin\Site\PromoController;
use App\Http\Controllers\Admin\Site\PromoItemController;
use App\Http\Controllers\Admin\Site\RecycleBinController;
use App\Http\Controllers\Admin\Site\RoleController as SiteRoleController;
use App\Http\Controllers\Admin\Site\SettingController as SiteSettingController;
use App\Http\Controllers\Admin\Site\SecurityController as SiteSecurityController;
use App\Http\Controllers\Admin\Site\ThemeController;
use App\Http\Controllers\Admin\Site\UserController as SiteUserController;
use App\Http\Controllers\Admin\SiteContextController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SiteMediaController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'show'])->name('site.home');
Route::get('/cat/{slug}', [SiteController::class, 'channel'])->name('site.channel');
Route::get('/article/{id}', [SiteController::class, 'article'])->name('site.article');
Route::get('/page/{id}', [SiteController::class, 'page'])->name('site.page');
Route::get('/theme-assets/{theme}/{path}', [SiteController::class, 'themeAsset'])
    ->where('path', '.*')
    ->name('site.theme-asset');
Route::get('/site-media/{siteKey}/{path}', [SiteMediaController::class, 'show'])
    ->where('siteKey', '[a-z0-9][a-z0-9\-]*')
    ->where('path', '.*')
    ->name('site.media');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->middleware('html.minify')->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'admin.access'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/admin/content-preview/article/{content}', [SiteController::class, 'previewArticle'])->name('admin.content-preview.article');
    Route::get('/admin/content-preview/page/{content}', [SiteController::class, 'previewPage'])->name('admin.content-preview.page');

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', AdminEntryController::class)->name('entry');
        Route::post('/site-context', [SiteContextController::class, 'update'])->name('site-context.update');
        Route::get('/dashboard', PlatformDashboardController::class)
            ->middleware('platform.only')
            ->name('dashboard');
        Route::get('/site-dashboard', fn () => redirect()->route('admin.site-dashboard'))->name('site-dashboard.legacy');
        Route::get('/logs', [PlatformOperationLogController::class, 'index'])
            ->middleware('platform.only')
            ->name('logs.index');

        Route::prefix('platform')->name('platform.')->middleware('platform.only')->group(function (): void {
            Route::get('/dashboard', PlatformDashboardController::class)->name('dashboard');
            Route::get('/logs', [PlatformOperationLogController::class, 'index'])->name('logs.index');
            Route::get('/sites', [PlatformSiteController::class, 'index'])->name('sites.index');
            Route::get('/sites/create', [PlatformSiteController::class, 'create'])->name('sites.create');
            Route::post('/sites', [PlatformSiteController::class, 'store'])->name('sites.store');
            Route::post('/sites/media-upload', [PlatformSiteController::class, 'mediaUpload'])->name('sites.media-upload');
            Route::get('/sites/{site}', [PlatformSiteController::class, 'edit'])->name('sites.edit');
            Route::post('/sites/{site}', [PlatformSiteController::class, 'update'])->name('sites.update');
            Route::get('/sites/{site}/modules', [PlatformSiteController::class, 'modules'])->name('sites.modules');
            Route::post('/sites/{site}/modules/add', [PlatformSiteController::class, 'addModule'])->name('sites.modules.add');
            Route::post('/sites/{site}/modules/{module}/update', [PlatformSiteController::class, 'updateModuleBinding'])->whereNumber('module')->name('sites.modules.update');
            Route::post('/sites/{site}/modules/{module}/remove', [PlatformSiteController::class, 'removeModule'])->whereNumber('module')->name('sites.modules.remove');
            Route::get('/modules', [PlatformModuleController::class, 'index'])->name('modules.index');
            Route::get('/modules/{module}', [PlatformModuleController::class, 'show'])->name('modules.show');
            Route::post('/modules/{module}/toggle', [PlatformModuleController::class, 'toggle'])->name('modules.toggle');
            Route::get('/database', [PlatformDatabaseController::class, 'index'])->name('database.index');
            Route::get('/database/{table}', [PlatformDatabaseController::class, 'show'])->name('database.show');
            Route::get('/settings', [SystemSettingController::class, 'index'])->name('settings.index');
            Route::post('/settings', [SystemSettingController::class, 'update'])->name('settings.update');
            Route::get('/system-checks', [SystemCheckController::class, 'index'])->name('system-checks.index');
            Route::post('/system-checks/static-vendors/{asset}/upgrade', [SystemCheckController::class, 'upgradeStaticVendor'])->name('system-checks.static-vendors.upgrade');
            Route::post('/system-checks/cache/{action}/clear', [SystemCheckController::class, 'clearCache'])->name('system-checks.cache.clear');
            Route::get('/users', [PlatformUserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [PlatformUserController::class, 'create'])->name('users.create');
            Route::post('/users', [PlatformUserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}', [PlatformUserController::class, 'edit'])->name('users.edit');
            Route::post('/users/{user}', [PlatformUserController::class, 'update'])->name('users.update');
            Route::post('/users/{user}/delete', [PlatformUserController::class, 'destroy'])->name('users.destroy');
            Route::get('/roles', [PlatformRoleController::class, 'index'])->name('roles.index');
            Route::get('/roles/create', [PlatformRoleController::class, 'create'])->name('roles.create');
            Route::post('/roles', [PlatformRoleController::class, 'store'])->name('roles.store');
            Route::get('/roles/{role}', [PlatformRoleController::class, 'edit'])->name('roles.edit');
            Route::post('/roles/{role}', [PlatformRoleController::class, 'update'])->name('roles.update');
            Route::post('/roles/{role}/delete', [PlatformRoleController::class, 'destroy'])->name('roles.destroy');
        });

        Route::prefix('site')->group(function (): void {
            Route::get('/dashboard', SiteDashboardController::class)->name('site-dashboard');
            Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
            Route::get('/channels/create', [ChannelController::class, 'create'])->name('channels.create');
            Route::get('/channels/slugify', [ChannelController::class, 'slugify'])->name('channels.slugify');
            Route::post('/channels', [ChannelController::class, 'store'])->name('channels.store');
            Route::post('/channels/bulk', [ChannelController::class, 'bulk'])->name('channels.bulk');
            Route::post('/channels/reorder', [ChannelController::class, 'reorder'])->name('channels.reorder');
            Route::get('/channels/{channel}', [ChannelController::class, 'edit'])->name('channels.edit');
            Route::post('/channels/{channel}', [ChannelController::class, 'update'])->name('channels.update');
            Route::post('/channels/{channel}/delete', [ChannelController::class, 'destroy'])->name('channels.destroy');
            Route::get('/promos', [PromoController::class, 'index'])->name('promos.index');
            Route::get('/promos/create', [PromoController::class, 'create'])->name('promos.create');
            Route::post('/promos', [PromoController::class, 'store'])->name('promos.store');
            Route::post('/promos/{position}/toggle', [PromoController::class, 'toggle'])->name('promos.toggle');
            Route::get('/promos/{position}/items', [PromoItemController::class, 'index'])->name('promos.items.index');
            Route::post('/promos/{position}/items', [PromoItemController::class, 'store'])->name('promos.items.store');
            Route::post('/promos/{position}/items/quick-store', [PromoItemController::class, 'quickStore'])->name('promos.items.quick-store');
            Route::post('/promos/{position}/items/reorder', [PromoItemController::class, 'reorder'])->name('promos.items.reorder');
            Route::post('/promos/{position}/items/{item}/duplicate', [PromoItemController::class, 'duplicate'])->name('promos.items.duplicate');
            Route::post('/promos/{position}/items/{item}/toggle', [PromoItemController::class, 'toggle'])->name('promos.items.toggle');
            Route::post('/promos/{position}/items/{item}/move', [PromoItemController::class, 'move'])->name('promos.items.move');
            Route::get('/promos/{position}/items/{item}', function () {
                abort(404);
            })->name('promos.items.redirect');
            Route::post('/promos/{position}/items/{item}', [PromoItemController::class, 'update'])->name('promos.items.update');
            Route::post('/promos/{position}/items/{item}/quick-update', [PromoItemController::class, 'quickUpdate'])->name('promos.items.quick-update');
            Route::post('/promos/{position}/items/{item}/replace-image', [PromoItemController::class, 'replaceImage'])->name('promos.items.replace-image');
            Route::post('/promos/{position}/items/{item}/delete', [PromoItemController::class, 'destroy'])->name('promos.items.destroy');
            Route::get('/promos/{position}', [PromoController::class, 'edit'])->name('promos.edit');
            Route::post('/promos/{position}', [PromoController::class, 'update'])->name('promos.update');
            Route::post('/promos/{position}/delete', [PromoController::class, 'destroy'])->name('promos.destroy');
            Route::get('/pages', [ContentController::class, 'index'])->defaults('type', 'page')->name('pages.index');
            Route::get('/pages/create', [ContentController::class, 'create'])->defaults('type', 'page')->name('pages.create');
            Route::post('/pages', [ContentController::class, 'store'])->defaults('type', 'page')->name('pages.store');
            Route::post('/pages/bulk', [ContentController::class, 'bulk'])->defaults('type', 'page')->name('pages.bulk');
            Route::get('/pages/{content}', [ContentController::class, 'edit'])->defaults('type', 'page')->name('pages.edit');
            Route::post('/pages/{content}', [ContentController::class, 'update'])->defaults('type', 'page')->name('pages.update');
            Route::post('/pages/{content}/delete', [ContentController::class, 'destroy'])->defaults('type', 'page')->name('pages.destroy');
            Route::get('/articles', [ContentController::class, 'index'])->defaults('type', 'article')->name('articles.index');
            Route::get('/articles/create', [ContentController::class, 'create'])->defaults('type', 'article')->name('articles.create');
            Route::post('/articles', [ContentController::class, 'store'])->defaults('type', 'article')->name('articles.store');
            Route::post('/articles/bulk', [ContentController::class, 'bulk'])->defaults('type', 'article')->name('articles.bulk');
            Route::post('/articles/reorder', [ContentController::class, 'reorder'])->defaults('type', 'article')->name('articles.reorder');
            Route::post('/articles/resolve-bilibili', [ContentController::class, 'resolveBilibiliVideo'])->name('articles.resolve-bilibili');
            Route::get('/articles/{content}', [ContentController::class, 'edit'])->defaults('type', 'article')->name('articles.edit');
            Route::post('/articles/{content}', [ContentController::class, 'update'])->defaults('type', 'article')->name('articles.update');
            Route::post('/articles/{content}/delete', [ContentController::class, 'destroy'])->defaults('type', 'article')->name('articles.destroy');
            Route::get('/article-reviews', [ArticleReviewController::class, 'index'])->name('article-reviews.index');
            Route::post('/article-reviews/bulk-approve', [ArticleReviewController::class, 'bulkApprove'])->name('article-reviews.bulk-approve');
            Route::post('/article-reviews/{content}/approve', [ArticleReviewController::class, 'approve'])->name('article-reviews.approve');
            Route::post('/article-reviews/{content}/reject', [ArticleReviewController::class, 'reject'])->name('article-reviews.reject');
            Route::get('/attachments', [AttachmentController::class, 'index'])->name('attachments.index');
            Route::get('/attachments/library-feed', [AttachmentController::class, 'libraryFeed'])->name('attachments.library-feed');
            Route::post('/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
            Route::post('/attachments/image-upload', [AttachmentController::class, 'imageUpload'])->name('attachments.image-upload');
            Route::post('/attachments/library-upload', [AttachmentController::class, 'libraryUpload'])->name('attachments.library-upload');
            Route::post('/attachments/{attachment}/replace', [AttachmentController::class, 'replace'])->name('attachments.replace');
            Route::get('/attachments/{attachment}/usages', [AttachmentController::class, 'usages'])->name('attachments.usages');
            Route::post('/attachments/bulk', [AttachmentController::class, 'bulk'])->name('attachments.bulk');
            Route::post('/attachments/{attachment}/delete', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
            Route::get('/modules/{module}', [SiteModuleController::class, 'show'])->name('site-modules.show');
            Route::get('/themes', [ThemeController::class, 'index'])->name('themes.index');
            Route::post('/themes', [ThemeController::class, 'update'])->name('themes.update');
            Route::post('/themes/store', [ThemeController::class, 'store'])->name('themes.store');
            Route::post('/themes/{siteTemplate}/delete', [ThemeController::class, 'destroy'])->whereNumber('siteTemplate')->name('themes.destroy');
            Route::post('/themes/orphans/delete', [ThemeController::class, 'destroyOrphan'])->name('themes.destroy-orphan');
            Route::get('/themes/editor', [ThemeController::class, 'editor'])->name('themes.editor');
            Route::get('/themes/editor/snapshots', [ThemeController::class, 'snapshots'])->name('themes.snapshots');
            Route::get('/themes/editor/template-create', [ThemeController::class, 'createTemplateForm'])->name('themes.editor.template-create-form');
            Route::post('/themes/editor', [ThemeController::class, 'updateEditor'])->name('themes.editor.update');
            Route::post('/themes/editor/template-create', [ThemeController::class, 'createTemplate'])->name('themes.editor.template-create');
            Route::post('/themes/editor/template-delete', [ThemeController::class, 'deleteTemplate'])->name('themes.editor.template-delete');
            Route::post('/themes/editor/template-rollback', [ThemeController::class, 'rollbackTemplate'])->name('themes.editor.template-rollback');
            Route::post('/themes/editor/template-snapshot-delete', [ThemeController::class, 'deleteSnapshot'])->name('themes.editor.template-snapshot-delete');
            Route::post('/themes/editor/template-snapshot-favorite', [ThemeController::class, 'toggleSnapshotFavorite'])->name('themes.editor.template-snapshot-favorite');
            Route::post('/themes/editor/asset-upload', [ThemeController::class, 'uploadAsset'])->name('themes.editor.asset-upload');
            Route::post('/themes/editor/asset-delete', [ThemeController::class, 'deleteAsset'])->name('themes.editor.asset-delete');
            Route::get('/settings', [SiteSettingController::class, 'index'])->name('settings.index');
            Route::post('/settings', [SiteSettingController::class, 'update'])->name('settings.update');
            Route::post('/settings/media-upload', [SiteSettingController::class, 'mediaUpload'])->name('settings.media-upload');
            Route::get('/security', [SiteSecurityController::class, 'index'])->name('security.index');
            Route::get('/users', [SiteUserController::class, 'index'])->name('site-users.index');
            Route::get('/users/create', [SiteUserController::class, 'create'])->name('site-users.create');
            Route::post('/users', [SiteUserController::class, 'store'])->name('site-users.store');
            Route::get('/users/{user}', [SiteUserController::class, 'edit'])->name('site-users.edit');
            Route::post('/users/{user}', [SiteUserController::class, 'update'])->name('site-users.update');
            Route::post('/users/{user}/delete', [SiteUserController::class, 'destroy'])->name('site-users.destroy');
            Route::get('/roles', [SiteRoleController::class, 'index'])->name('site-roles.index');
            Route::get('/roles/create', [SiteRoleController::class, 'create'])->name('site-roles.create');
            Route::post('/roles', [SiteRoleController::class, 'store'])->name('site-roles.store');
            Route::get('/roles/{role}', [SiteRoleController::class, 'edit'])->name('site-roles.edit');
            Route::post('/roles/{role}', [SiteRoleController::class, 'update'])->name('site-roles.update');
            Route::post('/roles/{role}/delete', [SiteRoleController::class, 'destroy'])->name('site-roles.destroy');
            Route::get('/recycle-bin', [RecycleBinController::class, 'index'])->name('recycle-bin.index');
            Route::post('/recycle-bin/bulk', [RecycleBinController::class, 'bulk'])->name('recycle-bin.bulk');
            Route::post('/recycle-bin/empty', [RecycleBinController::class, 'empty'])->name('recycle-bin.empty');
            Route::post('/recycle-bin/{content}/restore', [RecycleBinController::class, 'restore'])->name('recycle-bin.restore');
            Route::post('/recycle-bin/{content}/delete', [RecycleBinController::class, 'destroy'])->name('recycle-bin.destroy');
            Route::get('/logs', [SiteOperationLogController::class, 'index'])->name('site-logs.index');
        });
    });
});

$moduleRoot = app_path('Modules');

if (File::isDirectory($moduleRoot)) {
    foreach (File::directories($moduleRoot) as $modulePath) {
        $routesFile = $modulePath.'/routes.php';

        if (File::exists($routesFile)) {
            require $routesFile;
        }
    }
}
