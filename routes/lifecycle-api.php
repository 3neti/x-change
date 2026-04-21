<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Claims\CompleteVoucherClaimController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Claims\ShowVoucherClaimStatusController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Claims\StartVoucherClaimController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Claims\SubmitVoucherClaimController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Issuers\CreateIssuerController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Issuers\CreateIssuerWalletController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Pricelist\EstimateVoucherController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Pricelist\ListPricelistItemsController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Pricelist\ShowPricelistController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Reconciliations\ShowReconciliationController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Vouchers\CreateVoucherController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Wallets\CreateWalletTopUpController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Wallets\ListWalletLedgerController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Wallets\ShowWalletBalanceController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Wallets\ShowWalletController;

Route::prefix('api/x/v1')->as('api.x.v1.')->group(function (): void {
    Route::prefix('issuers')->group(function (): void {
        Route::post('/', CreateIssuerController::class)->name('issuers.store');
        Route::post('{issuer}/wallets', CreateIssuerWalletController::class)->name('issuers.wallets.store');
    });

    Route::prefix('wallets')->group(function (): void {
        Route::get('{wallet}', ShowWalletController::class)->name('wallets.show');
        Route::get('{wallet}/balance', ShowWalletBalanceController::class)->name('wallets.balance.show');
        Route::get('{wallet}/ledger', ListWalletLedgerController::class)->name('wallets.ledger.index');
        Route::post('{wallet}/top-ups', CreateWalletTopUpController::class)->name('wallets.topups.store');
    });

    Route::prefix('pricelist')->group(function (): void {
        Route::get('/', ShowPricelistController::class)->name('pricelist.show');
        Route::get('/items', ListPricelistItemsController::class)->name('pricelist.items.index');
    });

    Route::prefix('vouchers')->group(function (): void {
        Route::post('estimate', EstimateVoucherController::class)->name('vouchers.estimate');
        Route::post('/', CreateVoucherController::class)->name('vouchers.store');
        Route::post('code/{code}/claim/start', StartVoucherClaimController::class)->name('vouchers.claim.start');
        Route::post('code/{code}/claim/submit', SubmitVoucherClaimController::class)->name('vouchers.claim.submit');
        Route::post('code/{code}/claim/complete', CompleteVoucherClaimController::class)->name('vouchers.claim.complete');
        Route::get('code/{code}/claim/status', ShowVoucherClaimStatusController::class)->name('vouchers.claim.status.show');
    });

    Route::prefix('reconciliations')->group(function (): void {
        Route::get('{reconciliation}', ShowReconciliationController::class)->name('reconciliations.show');
    });
});
