<?php

use App\Http\Controllers\AdminLoginController;
use App\Livewire\Dashboard;
use App\Livewire\GameConfig;
use App\Livewire\KycList;
use App\Livewire\Transactions;
use App\Livewire\Users;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes — public portal entry + admin panel
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => redirect('/admin/login'));

Route::get('/admin/login', [AdminLoginController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminLoginController::class, 'login']);

Route::middleware('auth')->group(function () {
    Route::post('/admin/logout', [AdminLoginController::class, 'logout']);

    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/', Dashboard::class);
        Route::get('/transactions', Transactions::class);
        Route::get('/kyc', KycList::class);
        Route::get('/users', Users::class);
        Route::get('/games', GameConfig::class);
        Route::get('/profile', fn () => view('admin.profile'));
    });
});