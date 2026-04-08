<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Http\Controllers\Onboarding\OnboardIssuerController;
use LBHurtado\XChange\Http\Controllers\Onboarding\OpenIssuerWalletController;
use LBHurtado\XChange\Http\Controllers\PayCode\EstimatePayCodeController;
use LBHurtado\XChange\Http\Controllers\PayCode\GeneratePayCodeController;
use LBHurtado\XChange\Http\Controllers\Redemption\LoadPayCodeRedemptionCompletionContextController;
use LBHurtado\XChange\Http\Controllers\Redemption\PreparePayCodeRedemptionFlowController;
use LBHurtado\XChange\Http\Controllers\Redemption\RedeemPayCodeController;

$prefix = trim((string) config('x-change.routes.api_prefix', 'api/x'), '/');
$version = trim((string) config('x-change.routes.api_version', 'v1'), '/');

Route::prefix($prefix.'/'.$version)->group(function (): void {
    Route::post('/pay-codes/estimate', EstimatePayCodeController::class)
        ->name('x-change.api.pay-codes.estimate');

    Route::post('/pay-codes', GeneratePayCodeController::class)
        ->name('x-change.api.pay-codes.generate');

    Route::post('/onboarding/issuers', OnboardIssuerController::class)
        ->name('x-change.api.onboarding.issuers');

    Route::post('/onboarding/wallets', OpenIssuerWalletController::class)
        ->name('x-change.api.onboarding.wallets');

    Route::post('/pay-codes/{code}/claim/start', PreparePayCodeRedemptionFlowController::class)
        ->name('xchange.api.pay-codes.claim.start');

    Route::post('/pay-codes/{code}/claim/complete', LoadPayCodeRedemptionCompletionContextController::class)
        ->name('xchange.api.pay-codes.claim.complete');

    Route::post('/pay-codes/{code}/redeem', RedeemPayCodeController::class)
        ->name('xchange.api.pay-codes.redeem');
});
