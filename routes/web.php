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

Route::prefix('redeem/{voucher}')
    ->middleware(CheckVoucherMiddleware::class) // ✅ Applies to all steps
    ->group(function () {
        Route::get('mobile', [RedeemWizardController::class, 'mobile'])
            ->name('redeem.mobile');

        Route::post('mobile', [RedeemWizardController::class, 'storeMobile']);

        Route::get('inputs', [RedeemWizardController::class, 'inputs'])
            ->middleware(CheckMobileMiddleware::class)
            ->name('redeem.inputs');

        Route::post('inputs', [RedeemWizardController::class, 'storeInputs']);

        Route::get('signature', [RedeemWizardController::class, 'signature'])
            ->middleware(AddInputsMiddleware::class)
            ->name('redeem.signature');

        Route::post('signature', [RedeemWizardController::class, 'storeSignature']);

        Route::get('finalize', [RedeemWizardController::class, 'finalize'])
            ->middleware(CheckMobileMiddleware::class)
//            ->middleware(SignTransactionMiddleware::class)
            ->name('redeem.finalize');

        Route::get('success', [RedeemWizardController::class, 'success'])
//            ->middleware(CheckMobileMiddleware::class)
            ->middleware(RedeemVoucherMiddleware::class)
            ->name('redeem.success');
    });
