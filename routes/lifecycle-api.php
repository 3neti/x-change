<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Http\Controllers\Lifecycle\Claims\ApproveVoucherClaimController;
use LBHurtado\XChange\Http\Controllers\Lifecycle\Claims\VerifyVoucherClaimOtpController;
use LBHurtado\XChange\Http\Controllers\Lifecycle\Settlements\StartSettlementLifecycleController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Claims\CompleteVoucherClaimController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Claims\ShowVoucherClaimStatusController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Claims\StartVoucherClaimController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Claims\SubmitVoucherClaimController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Events\ListEventsController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Events\ShowEventController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Events\ShowIdempotencyKeyController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Issuers\CreateIssuerController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Issuers\CreateIssuerWalletController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Pricelist\EstimateVoucherController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Pricelist\ListPricelistItemsController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Pricelist\ShowPricelistController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Reconciliations\ListReconciliationsController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Reconciliations\ResolveReconciliationController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Reconciliations\ShowReconciliationController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Users\CreateUserController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Users\ShowUserController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Users\ShowUserKycController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Users\SubmitUserKycController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Vouchers\CancelVoucherController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Vouchers\CreateVoucherController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Vouchers\ListVouchersController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Vouchers\ShowVoucherByCodeController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Vouchers\ShowVoucherController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Vouchers\ShowVoucherStatusController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Wallets\CreateWalletTopUpController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Wallets\ListWalletLedgerController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Wallets\ShowWalletBalanceController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Wallets\ShowWalletController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Withdrawals\CreateVoucherWithdrawalController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Withdrawals\ListVoucherWithdrawalsController;
use LBHurtado\XChange\Lifecycle\Http\Controllers\Withdrawals\ShowVoucherWithdrawalController;

Route::prefix('api/x/v1')->as('api.x.v1.')->group(function (): void {
    Route::post('/settlements/{voucher}/start', StartSettlementLifecycleController::class)
        ->name('settlements.start');

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

        Route::get('/', ListVouchersController::class)->name('vouchers.index');
        Route::get('{voucher}', ShowVoucherController::class)->name('vouchers.show');
        Route::get('code/{code}', ShowVoucherByCodeController::class)->name('vouchers.code.show');
        Route::get('{voucher}/status', ShowVoucherStatusController::class)->name('vouchers.status.show');
        Route::post('{voucher}/cancel', CancelVoucherController::class)->name('vouchers.cancel');

        Route::post('code/{code}/claim/start', StartVoucherClaimController::class)->name('vouchers.claim.start');
        Route::post('code/{code}/claim/submit', SubmitVoucherClaimController::class)->name('vouchers.claim.submit');
        Route::post('code/{code}/claim/complete', CompleteVoucherClaimController::class)->name('vouchers.claim.complete');
        Route::get('code/{code}/claim/status', ShowVoucherClaimStatusController::class)->name('vouchers.claim.status.show');

        Route::post('code/{code}/claim/approve', ApproveVoucherClaimController::class)
            ->name('vouchers.claim.approve');
        Route::post('code/{code}/claim/otp/verify', VerifyVoucherClaimOtpController::class)
            ->name('vouchers.claim.otp.verify');
    });

    Route::prefix('reconciliations')->group(function (): void {
        Route::get('/', ListReconciliationsController::class)->name('reconciliations.index');
        Route::get('{reconciliation}', ShowReconciliationController::class)->name('reconciliations.show');
        Route::post('{reconciliation}/resolve', ResolveReconciliationController::class)->name('reconciliations.resolve');
    });

    Route::prefix('events')->group(function (): void {
        Route::get('/', ListEventsController::class)->name('events.index');
        Route::get('{event}', ShowEventController::class)->name('events.show');
        Route::get('idempotency/{key}', ShowIdempotencyKeyController::class)->name('events.idempotency.show');
    });

    Route::prefix('withdrawals')->group(function (): void {
        Route::post('/', CreateVoucherWithdrawalController::class)->name('withdrawals.store');
        Route::get('/', ListVoucherWithdrawalsController::class)->name('withdrawals.index');
        Route::get('{withdrawal}', ShowVoucherWithdrawalController::class)->name('withdrawals.show');
    });

    Route::prefix('users')->group(function (): void {
        Route::post('/', CreateUserController::class)->name('users.store');
        Route::get('{user}', ShowUserController::class)->name('users.show');
        Route::post('{user}/kyc', SubmitUserKycController::class)->name('users.kyc.submit');
        Route::get('{user}/kyc', ShowUserKycController::class)->name('users.kyc.show');
    });
});
