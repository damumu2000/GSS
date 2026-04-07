<?php

use App\Modules\Payroll\Controllers\Admin\Site\PayrollBatchController;
use App\Modules\Payroll\Controllers\Admin\Site\PayrollEmployeeController;
use App\Modules\Payroll\Controllers\Admin\Site\PayrollSettingsController;
use App\Modules\Payroll\Controllers\Frontend\PayrollController;
use Illuminate\Support\Facades\Route;

Route::get('/payroll', [PayrollController::class, 'index'])->name('site.payroll.index');
Route::get('/payroll/wechat/redirect', [PayrollController::class, 'wechatRedirect'])->name('site.payroll.wechat.redirect');
Route::get('/payroll/wechat/callback', [PayrollController::class, 'wechatCallback'])->name('site.payroll.wechat.callback');
Route::get('/payroll/register', [PayrollController::class, 'register'])->name('site.payroll.register');
Route::post('/payroll/register', [PayrollController::class, 'storeRegistration'])->name('site.payroll.register.store');
Route::get('/payroll/password/unlock', [PayrollController::class, 'password'])->name('site.payroll.password');
Route::post('/payroll/password/unlock', [PayrollController::class, 'unlock'])->name('site.payroll.password.unlock');
Route::get('/payroll/password/manage', [PayrollController::class, 'passwordManage'])->name('site.payroll.password.manage');
Route::post('/payroll/password/manage', [PayrollController::class, 'passwordSave'])->name('site.payroll.password.save');
Route::post('/payroll/logout', [PayrollController::class, 'logout'])->name('site.payroll.logout');
Route::get('/payroll/{batch}/{type}', [PayrollController::class, 'detail'])
    ->whereIn('type', ['salary', 'performance'])
    ->name('site.payroll.show');

Route::middleware(['auth', 'admin.access'])
    ->prefix('admin/site/payroll')
    ->group(function (): void {
        Route::get('/settings', [PayrollSettingsController::class, 'edit'])->name('admin.payroll.settings');
        Route::post('/settings', [PayrollSettingsController::class, 'update'])->name('admin.payroll.settings.update');
        Route::get('/help', [PayrollSettingsController::class, 'help'])->name('admin.payroll.help');

        Route::get('/batches', [PayrollBatchController::class, 'index'])->name('admin.payroll.batches.index');
        Route::get('/batches/create', [PayrollBatchController::class, 'create'])->name('admin.payroll.batches.create');
        Route::post('/batches', [PayrollBatchController::class, 'store'])->name('admin.payroll.batches.store');
        Route::get('/batches/{batch}', [PayrollBatchController::class, 'edit'])->name('admin.payroll.batches.edit');
        Route::get('/batches/{batch}/export/{type}', [PayrollBatchController::class, 'export'])
            ->whereIn('type', ['salary', 'performance'])
            ->name('admin.payroll.batches.export');
        Route::post('/batches/{batch}', [PayrollBatchController::class, 'update'])->name('admin.payroll.batches.update');
        Route::post('/batches/{batch}/delete', [PayrollBatchController::class, 'destroy'])->name('admin.payroll.batches.destroy');

        Route::get('/employees', [PayrollEmployeeController::class, 'index'])->name('admin.payroll.employees.index');
        Route::post('/employees/{employee}/approve', [PayrollEmployeeController::class, 'approve'])->name('admin.payroll.employees.approve');
        Route::post('/employees/{employee}/toggle', [PayrollEmployeeController::class, 'toggle'])->name('admin.payroll.employees.toggle');
        Route::post('/employees/{employee}/reset-password', [PayrollEmployeeController::class, 'resetPassword'])->name('admin.payroll.employees.reset-password');
    });
