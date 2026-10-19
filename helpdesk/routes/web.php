<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\IdGeneratorController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TaskBoardController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'admin'])->name('dashboard');
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets');
    Route::get('/tickets/metrics', [TicketController::class, 'metrics'])->name('tickets.metrics');
    Route::post('/tickets/status', [TicketController::class, 'updateStatus'])->name('tickets.status');
    Route::get('/notices', [NoticeController::class, 'index'])->name('notices');
    Route::post('/notices', [NoticeController::class, 'store'])->name('notices.store');
    Route::post('/notices/delete', [NoticeController::class, 'destroy'])->name('notices.destroy');
    Route::get('/notices/{notice}/attachment', [NoticeController::class, 'attachment'])->name('notices.attachment');
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
});

Route::middleware('staff')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'staff'])->name('staff.dashboard');
    Route::get('/dashboard/requests', [DashboardController::class, 'staffRequests'])->name('staff.requests');
    Route::post('/dashboard/requests', [TicketController::class, 'submit'])->name('staff.tickets.submit');
    Route::get('/dashboard/notices/{notice}/attachment', [NoticeController::class, 'attachment'])->name('staff.notices.attachment');
});

Route::middleware('chat')->prefix('chat')->name('chat.')->group(function (): void {
    Route::get('/', [ChatController::class, 'index'])->name('index');
    Route::get('/state', [ChatController::class, 'state'])->name('state');
    Route::post('/conversations', [ChatController::class, 'createConversation'])->name('conversations');
    Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages'])->name('conversations.messages');
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send'])->name('conversations.messages.send');
});

Route::middleware('chat')->group(function (): void {
    Route::get('/tasks', [TaskBoardController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskBoardController::class, 'store'])->name('tasks.store');
    Route::post('/tasks/{task}/update', [TaskBoardController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}/status', [TaskBoardController::class, 'updateStatus'])->name('tasks.status');
    Route::delete('/tasks/{task}', [TaskBoardController::class, 'destroy'])->name('tasks.destroy');
});

Route::post('/profile', [ProfileController::class, 'update'])->middleware('chat')->name('profile.update');
