<?php

use App\Modules\Guestbook\Controllers\Admin\Site\GuestbookController as AdminGuestbookController;
use App\Modules\Guestbook\Controllers\Frontend\GuestbookController as FrontendGuestbookController;
use Illuminate\Support\Facades\Route;

Route::get('/guestbook', [FrontendGuestbookController::class, 'index'])->name('site.guestbook.index');
Route::get('/guestbook/create', [FrontendGuestbookController::class, 'create'])->name('site.guestbook.create');
Route::post('/guestbook', [FrontendGuestbookController::class, 'store'])->name('site.guestbook.store');
Route::get('/guestbook/captcha', [FrontendGuestbookController::class, 'captcha'])->name('site.guestbook.captcha');
Route::get('/guestbook/{displayNo}', [FrontendGuestbookController::class, 'detail'])
    ->whereNumber('displayNo')
    ->name('site.guestbook.show');

Route::middleware(['auth', 'admin.access'])
    ->prefix('admin/site/guestbook')
    ->group(function (): void {
        Route::get('/', [AdminGuestbookController::class, 'index'])->name('admin.guestbook.index');
        Route::get('/settings', [AdminGuestbookController::class, 'settings'])->name('admin.guestbook.settings');
        Route::post('/settings', [AdminGuestbookController::class, 'updateSettings'])->name('admin.guestbook.settings.update');
        Route::get('/messages/{message}', [AdminGuestbookController::class, 'show'])->name('admin.guestbook.show');
        Route::post('/messages/{message}', [AdminGuestbookController::class, 'update'])->name('admin.guestbook.update');
    });
