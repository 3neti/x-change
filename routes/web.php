<?php

use App\Http\Controllers\{CheckWalletBalanceController, DashboardController, LoadController, VoucherController};
use App\Http\Controllers\Voucher\{GenerateController, ViewController};
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;
use App\Http\Controllers\Api\CutCheckController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'));

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('load', LoadController::class)->name('load');
    Route::get('generate', [GenerateController::class, 'create'])->name('disburse');
    Route::post('generate', [GenerateController::class, 'store'])->name('disburse.store');
    Route::get('view', ViewController::class)->name('view');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('wallet/balance', CheckWalletBalanceController::class)
        ->name('wallet.balance');
    Route::get('wallet/add-funds', LBHurtado\PaymentGateway\Http\Controllers\GenerateController::class)
        ->name('wallet.add-funds');
});

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
    EnsurePayeeMobileMiddleware,
    EnsurePayeeSecretMiddleware,
    RedeemVoucherMiddleware
};
use App\Actions\VerifyMobile;

Route::get('redeem', function () {
    return Inertia::render('Redeem/Start');
})->name('redeem');

Route::prefix('redeem/{voucher}')
    ->middleware(CheckVoucherMiddleware::class)
    ->group(function () {
        Route::get('wallet', [RedeemWizardController::class, 'wallet'])->name('redeem.wallet');

        Route::post('wallet', [RedeemWizardController::class, 'storeWallet']);

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
                EnsurePayeeMobileMiddleware::class,
                EnsurePayeeSecretMiddleware::class,
                RedeemVoucherMiddleware::class
            ])
            ->name('redeem.success');

        Route::post('verify-mobile', VerifyMobile::class)->name('redeem.verify-mobile');
    });

Route::get('redeem/{voucher}/redirect', \App\Http\Controllers\SuccessRedirectController::class)
    ->name('redeem.redirect');

Route::post('calculate-cost', \App\Actions\CalculateCost::class)
    ->middleware( 'auth', 'web')
    ->name('calculate-cost');

Route::post('parse-instructions', \App\Actions\ParseInstructions::class)
    ->middleware( 'auth', 'web')
    ->name('parse-instructions');

use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Foundation\Inspiring;
use App\Data\MessageData;
use Illuminate\Support\Str;

Route::get('test/{voucher}', function (Voucher $voucher) {
    $from = $voucher->owner->name;
    $to = $voucher->input('name')  ?? $voucher->contact->mobile;
    $instruction_message = $voucher->instructions->rider->message;
    if (! $message = MessageData::tryFrom($instruction_message)?->withWrappedBody()) {
        $subject = 'Quote';
        $title = '';
        [$body, $from] = str(Inspiring::quotes()->random())->explode('-');
        $body = Str::wordWrap($body, characters: 40, break: "\n");
        $closing = 'Ayus!';

        $message = MessageData::from(compact('subject','title', 'body', 'closing'));
    }

    $response = Inertia::render('Redeem/Success', [
        'voucher' => $voucher->getData(),
        'from' => $from,
        'to' => $to,
        'message' => $message,
        'redirectTimeout' => config('x-change.redeem.success.redirect_timeout'),
    ]);

    return $response;
});
