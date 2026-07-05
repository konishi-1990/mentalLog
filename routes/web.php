<?php

use App\Http\Controllers\Admin\ChecklistOptionController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CheckItemController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 分析
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // ログ CRUD
    Route::resource('logs', LogController::class);

    // ○×項目マスタ（ユーザ毎）
    Route::put('check-items/reorder', [CheckItemController::class, 'reorder'])->name('check-items.reorder');
    Route::resource('check-items', CheckItemController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['check-items' => 'checkItem']);
});

// 管理者機能
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', AdminUserController::class)->except('show');
    Route::resource('checklist', ChecklistOptionController::class)
        ->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/auth.php';
