<?php

use App\Http\Controllers\{CheckWalletBalanceController, VoucherController};
use App\Http\Controllers\Voucher\{GenerateController, ViewController};
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;
use App\Http\Controllers\Api\CutCheckController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\LaravelData\DataCollection;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\Collection;
use Brick\Money\Money;

Route::get('/', fn () => Inertia::render('Welcome'));

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    Route::get('dashboard', function (Request $request) {
        $vouchers = Voucher::query()
            ->withOwner($request->user())
            ->withRedeemed()
            ->latest('redeemed_at')
            ->get()
        ;

        $redeemables = Voucher::query()
            ->withOwner($request->user())
            ->withRedeemable()
            ->latest('redeemed_at')
            ->get()
        ;
        $totalVouchers = $redeemables->count();

        $totalRedeemed = voucher_totals($vouchers);
        $totalRedeemables = voucher_totals($redeemables);
//// 1. Extract all non-null cash objects
//        $cashEntries = $redeemables
//            ->map(fn ($voucher) => $voucher->cash)
//            ->filter(); // remove nulls
//
//// 2. Group them by currency
//        $cashByCurrency = $cashEntries->groupBy(fn ($cash) => $cash->amount->getCurrency()->getCurrencyCode());
//
//// 3. Sum using Money::plus()
//        $totalAmounts = $cashByCurrency->map(function (Collection $group) {
//            return $group->reduce(
//                fn (Money $carry, $cash) => $carry->plus($cash->amount),
//                Money::zero($group->first()->amount->getCurrency())
//            );
//        });

        return Inertia::render('Dashboard', [
            'vouchers' => new DataCollection(VoucherData::class, $vouchers),
            'totalVouchers' => $totalVouchers,
            'totalRedeemables' => $totalRedeemables,
            'totalRedeemed' => $totalRedeemed,

        ]);
    })->name('dashboard');
    Route::get('load', function () {
        return Inertia::render('Load');
    })->name('load');

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
    });

Route::get('redeem/{voucher}/redirect', \App\Http\Controllers\SuccessRedirectController::class)
    ->name('redeem.redirect');

Route::post('calculate-cost', \App\Actions\CalculateCost::class)
    ->middleware( 'auth', 'web')
    ->name('calculate-cost');

Route::post('parse-instructions', \App\Actions\ParseInstructions::class)
    ->middleware( 'auth', 'web')
    ->name('parse-instructions');
