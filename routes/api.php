<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

// Public Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected Financial & User routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Wallet & Transactions
    Route::get('/wallet', [WalletController::class, 'getWallet']);
    Route::get('/transactions', [WalletController::class, 'getTransactions']);

    // Critical financial operations with Rate Limiting and Idempotency Guard
    Route::middleware(['throttle:financial-tx', EnsureIdempotency::class])->group(function () {
        Route::post('/wallet/topup', [WalletController::class, 'topUp']);
        Route::post('/wallet/transfer', [WalletController::class, 'transfer']);
    });
});
