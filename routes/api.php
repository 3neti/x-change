<?php

use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// routes/api.php
use App\Http\Controllers\WalletBalanceController;

Route::middleware('auth:sanctum')
    ->get('/wallet/balance', [WalletBalanceController::class, 'show'])
    ->name('api.wallet.balance');

//Route::middleware(['auth:sanctum'])
//    ->get('qr-code', [WalletController::class, 'generate'])
//    ->name('wallet.qr-code');

