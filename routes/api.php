<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\RedeemVoucherController;

Route::post('redeem‐voucher', [RedeemVoucherController::class, 'store'])
    ->name('redeem‐voucher.store');
