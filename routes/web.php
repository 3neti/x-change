<?php

use App\Http\Controllers\{CheckWalletBalanceController, VoucherController};
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;
use App\Http\Controllers\Api\CutCheckController;
use App\Http\Controllers\Api\TokenController;
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

Route::middleware('auth:sanctum')->post('/token', [TokenController::class, 'store'])->name('token.store');

Route::middleware('auth:sanctum')->post(
    '/cut-check',
    [CutCheckController::class, 'store']
)->name('api.cut-check');

Route::resource('vouchers', VoucherController::class);

use App\Http\Controllers\Redeem\RedeemWizardController;
use App\Http\Middleware\Redeem\{
    CheckVoucherMiddleware,
    CheckMobileMiddleware,
    AddInputsMiddleware,
    SignTransactionMiddleware,
    RedeemVoucherMiddleware
};

Route::get('redeem', function () {
    return Inertia::render('Redeem/Start');
})->name('redeem');
use App\Http\Controllers\Redeem\RedeemPluginController;

Route::prefix('redeem/{voucher}')
    ->middleware(CheckVoucherMiddleware::class)
    ->group(function () {
        Route::get('mobile', [RedeemWizardController::class, 'mobile'])->name('redeem.mobile');

        Route::post('mobile', [RedeemWizardController::class, 'storeMobile']);

        foreach (config('x-change.redeem.plugins', []) as $key => $plugin) {
            if (!($plugin['enabled'] ?? false)) continue;

            $middlewares = $plugin['middleware'] ?? [];
            $middlewares = is_array($middlewares) ? $middlewares : [$middlewares];

            Route::get("$key/{plugin}", [RedeemWizardController::class, 'plugin'])
                ->middleware($middlewares)
                ->name($plugin['route'] ?? "redeem.$key");

            Route::post("$key/{plugin}", [RedeemWizardController::class, 'storePlugin'])
                ->middleware($middlewares) // 🔁 include POST middleware too if needed
                ->name("redeem.$key.store");
        }

//        foreach (config('x-change.redeem.plugins', []) as $key => $plugin) {
//            if (!($plugin['enabled'] ?? false)) continue;
//
//            Route::get($key . '/{plugin}', [RedeemWizardController::class, 'plugin'])
//                ->middleware($plugin['middleware'] ?? [])
//                ->name($plugin['route'] ?? "redeem.$key");
//
//            Route::post($key . '/{plugin}', [RedeemWizardController::class, 'storePlugin'])
//                ->name("redeem.$key.store");
//        }

        Route::get('finalize', [RedeemWizardController::class, 'finalize'])
            ->middleware([
                CheckVoucherMiddleware::class,
                CheckMobileMiddleware::class
            ])
            ->name('redeem.finalize');

        Route::get('success', [RedeemWizardController::class, 'success'])
            ->middleware([
                CheckVoucherMiddleware::class,
                CheckMobileMiddleware::class,
                RedeemVoucherMiddleware::class
            ])
            ->name('redeem.success');
    });
