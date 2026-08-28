<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WithdrawalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes — mounted under /public/api (see bootstrap/app.php)
|--------------------------------------------------------------------------
*/

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('game/validate', [GameController::class, 'validateToken']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('password', [AuthController::class, 'changePassword']);

    Route::post('deposits', [DepositController::class, 'store']);
    Route::get('deposits', [DepositController::class, 'index']);

    Route::post('withdrawals', [WithdrawalController::class, 'store']);
    Route::get('withdrawals', [WithdrawalController::class, 'index']);
    Route::get('withdraw/requirements', [WithdrawalController::class, 'requirements']);

    Route::post('kyc', [KycController::class, 'store']);
    Route::get('kyc', [KycController::class, 'index']);

    Route::get('transactions', [TransactionController::class, 'index']);

    Route::get('games', [GameController::class, 'index']);
    Route::post('game/session', [GameController::class, 'session']);
});

// Game service calls are authenticated with a short-lived GAME token (GameAuth middleware).
Route::middleware('game')->group(function () {
    Route::post('game/join', [GameController::class, 'join']);
    Route::post('game/refund', [GameController::class, 'refund']);
    Route::post('game/result', [GameController::class, 'result']);
});