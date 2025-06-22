<?php

use App\Http\Controllers\{CheckWalletBalanceController, VoucherController};
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'));

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('wallet/balance', CheckWalletBalanceController::class)
        ->name('wallet.balance');
    Route::get('wallet/add-funds', LBHurtado\PaymentGateway\Http\Controllers\GenerateController::class)
        ->name('wallet.add-funds');
});

use App\Http\Controllers\Api\TokenController;

Route::middleware('auth:sanctum')->post('/token', [TokenController::class, 'store'])->name('token.store');

use App\Http\Controllers\Api\CutCheckController;

Route::middleware('auth:sanctum')->post(
    '/cut-check',
    [CutCheckController::class, 'store']
)->name('api.cut-check');

use App\Http\Controllers\RedeemVoucherController;

Route::get('redeem', [RedeemVoucherController::class, 'create'])
    ->name('redeem.create');

Route::post('redeem', [RedeemVoucherController::class, 'store'])
    ->name('redeem.store');

Route::resource('vouchers', VoucherController::class);
