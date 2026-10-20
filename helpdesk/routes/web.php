<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\IdGeneratorController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MonthlyAllocationController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::redirect('/login', '/')->name('login');
Route::get('/admin/login', [AuthController::class, 'show'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('admin.login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/reports', [TicketController::class, 'submitPublic'])->middleware('throttle:5,1')->name('reports.submit');
Route::get('/notices/{notice}/attachment', [NoticeController::class, 'attachment'])->name('notices.attachment');
Route::redirect('/dashboard', '/');
Route::redirect('/dashboard/requests', '/#report');

Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'admin'])->name('dashboard');
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets');
    Route::get('/tickets/metrics', [TicketController::class, 'metrics'])->name('tickets.metrics');
    Route::post('/tickets/status', [TicketController::class, 'updateStatus'])->name('tickets.status');
    Route::get('/notices', [NoticeController::class, 'index'])->name('notices');
    Route::post('/notices', [NoticeController::class, 'store'])->name('notices.store');
    Route::post('/notices/delete', [NoticeController::class, 'destroy'])->name('notices.destroy');
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::post('/users/status', [UserController::class, 'setStatus'])->name('users.status');
    Route::get('/database', [DatabaseController::class, 'index'])->name('database');
    Route::get('/database/export', [DatabaseController::class, 'export'])->name('database.export');
    Route::post('/database/import', [DatabaseController::class, 'import'])->name('database.import');
    Route::get('/database/metrics', [DatabaseController::class, 'metrics'])->name('database.metrics');
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
