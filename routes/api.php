<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// routes/api.php
use App\Http\Controllers\WalletBalanceController;

Route::middleware('auth:sanctum')
    ->get('/wallet/balance', [WalletBalanceController::class, 'show'])
    ->name('api.wallet.balance');
