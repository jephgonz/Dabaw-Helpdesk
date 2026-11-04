<?php

use App\Http\Controllers\AccomplishmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrowserNotificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\IdGeneratorController;
use App\Http\Controllers\MonthlyAllocationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'show'])->name('home');
Route::redirect('/login', '/')->name('login');
Route::get('/admin/login', [AuthController::class, 'show'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('admin.login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::redirect('/dashboard', '/');

Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'admin'])->name('dashboard');
    Route::get('/notifications', [BrowserNotificationController::class, 'poll'])->name('notifications.poll');
    Route::get('/notifications/settings', [BrowserNotificationController::class, 'settings'])->name('notifications.settings');
    Route::post('/notifications/settings', [BrowserNotificationController::class, 'updateSettings'])->name('notifications.settings.update');
    Route::get('/accomplishments', [AccomplishmentController::class, 'index'])->name('accomplishments');
    Route::post('/accomplishments', [AccomplishmentController::class, 'store'])->name('accomplishments.store');
    Route::get('/accomplishments/report', [AccomplishmentController::class, 'report'])->name('accomplishments.report');
    Route::post('/accomplishments/{accomplishment}/complete', [AccomplishmentController::class, 'complete'])->name('accomplishments.complete');
    Route::post('/accomplishments/{accomplishment}', [AccomplishmentController::class, 'update'])->name('accomplishments.update');
    Route::post('/accomplishments/{accomplishment}/delete', [AccomplishmentController::class, 'destroy'])->name('accomplishments.destroy');
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::post('/users/status', [UserController::class, 'setStatus'])->name('users.status');
    Route::get('/database', [DatabaseController::class, 'index'])->name('database');
    Route::post('/database/backup/preview', [DatabaseController::class, 'clonePreview'])->name('database.backup.preview');
    Route::post('/database/backup', [DatabaseController::class, 'cloneDatabase'])->name('database.backup');
    Route::get('/database/backup-snapshots/{backup}', [DatabaseController::class, 'downloadBackupSnapshot'])->name('database.backup-snapshot');
    Route::get('/database/export', [DatabaseController::class, 'export'])->name('database.export');
    Route::post('/database/import', [DatabaseController::class, 'import'])->name('database.import');
    Route::get('/database/metrics', [DatabaseController::class, 'metrics'])->name('database.metrics');
    Route::post('/database/query', [DatabaseController::class, 'query'])->name('database.query');
    Route::get('/database/table', [DatabaseController::class, 'table'])->name('database.table');
    Route::get('/database/record/create', [DatabaseController::class, 'createForm'])->name('database.record.create');
    Route::get('/database/record/edit', [DatabaseController::class, 'editForm'])->name('database.record.edit');
    Route::post('/database/record/save', [DatabaseController::class, 'saveRecord'])->name('database.record.save');
    Route::post('/database/record/delete', [DatabaseController::class, 'deleteRecord'])->name('database.record.delete');
    Route::get('/tools/id-generator', [IdGeneratorController::class, 'index'])->name('tools.id-generator');
    Route::post('/tools/id-generator', [IdGeneratorController::class, 'generate'])->name('tools.id-generator.generate');
    Route::get('/tools/id-generator/preview/{file}', [IdGeneratorController::class, 'preview'])->name('tools.id-generator.preview');
    Route::get('/tools/id-generator/download/{file}', [IdGeneratorController::class, 'download'])->name('tools.id-generator.download');
    Route::get('/tools/monthly-allocation', [MonthlyAllocationController::class, 'index'])->name('tools.monthly-allocation');
    Route::post('/tools/monthly-allocation/download', [MonthlyAllocationController::class, 'download'])
        ->middleware('throttle:3,1')
        ->name('tools.monthly-allocation.download');
});

Route::post('/profile', [ProfileController::class, 'update'])->middleware('admin')->name('profile.update');
