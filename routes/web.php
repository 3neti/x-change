<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Http\Controllers\Web\BalancePageController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimApprovalOtpController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimApprovalPageController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimCompleteController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimExperienceController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimRedirectController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimStartController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimSubmitController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimSuccessPageController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitAccountPageController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitAccountScenarioController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitDashboardPageController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitDistributionWorkspacePageController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingDestinationController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingInstructionController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingIntentController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingPageController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingReconciliationApprovalController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingReconciliationRequestController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingVerificationCheckController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitNetbankStandingFundingAddressController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitNetbankTokenRotationController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitPayCodeExplorerPageController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitQrPhFundingSimulationController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitQuickGenerateMutationRouteShellController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitQuickGeneratePageController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitRuntimeProfilePageController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitStandingFundingReceiptApprovalController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitVoucherDetailPageController;
use LBHurtado\XChange\Http\Controllers\Web\DashboardPageController;
use LBHurtado\XChange\Http\Controllers\Web\LinkPaynamicsWalletController;
use LBHurtado\XChange\Http\Controllers\Web\Onboarding\MobileVerificationChallengeController;
use LBHurtado\XChange\Http\Controllers\Web\Onboarding\MobileVerificationPageController;
use LBHurtado\XChange\Http\Controllers\Web\Onboarding\MobileVerificationSubmissionController;
use LBHurtado\XChange\Http\Controllers\Web\PayCodeCreatePageController;
use LBHurtado\XChange\Http\Controllers\Web\PayCodeIndexPageController;
use LBHurtado\XChange\Http\Controllers\Web\PayCodeShowPageController;
use LBHurtado\XChange\Http\Middleware\RequireVerifiedMobile;
use LBHurtado\XChange\Http\Middleware\ShareCockpitHeaderReadModel;
use LBHurtado\XChange\Http\Middleware\ShareXChangeBranding;

$middleware = config('x-change.routes.web_middleware', ['web', 'auth']);

// Authenticated operator routes
Route::prefix('x')->middleware([...$middleware, ShareXChangeBranding::class])->group(function (): void {
    Route::get('onboarding/mobile/verify', MobileVerificationPageController::class)
        ->name('x-change.onboarding.mobile-verification.show');
    Route::post('onboarding/mobile/challenge', MobileVerificationChallengeController::class)
        ->middleware('throttle:3,1')
        ->name('x-change.onboarding.mobile-verification.challenge');
    Route::post('onboarding/mobile/verify', MobileVerificationSubmissionController::class)
        ->middleware('throttle:6,1')
        ->name('x-change.onboarding.mobile-verification.verify');

    Route::get('dashboard', DashboardPageController::class)->name('x-change.dashboard');

    Route::prefix('cockpit')->middleware(ShareCockpitHeaderReadModel::class)->group(function (): void {
        Route::get('/', CockpitDashboardPageController::class)->name('x-change.cockpit.dashboard');
        Route::get('accounts', CockpitAccountPageController::class)
            ->middleware('verified')
            ->name('x-change.cockpit.accounts.index');
        Route::post(
            'accounts/scenarios/funding-destinations',
            CockpitAccountScenarioController::class,
        )->middleware((array) config('x-change.cockpit.account_scenario.middleware', []))
            ->name('x-change.cockpit.accounts.scenarios.funding-destinations.store');
        Route::middleware((array) config('x-change.cockpit.account_mutation_middleware', []))->group(function (): void {
            Route::patch(
                'accounts/providers/{provider}/funding-destination',
                CockpitFundingDestinationController::class,
            )->whereIn('provider', ['netbank', 'paynamics'])
                ->name('x-change.cockpit.accounts.providers.funding-destination.update');
            Route::post(
                'accounts/providers/netbank/token-rotation',
                CockpitNetbankTokenRotationController::class,
            )->name('x-change.cockpit.accounts.providers.netbank.token-rotation.store');
        });
        Route::get('funding', CockpitFundingPageController::class)->name('x-change.cockpit.funding.index');
        Route::post('funding/intents', CockpitFundingIntentController::class)->name('x-change.cockpit.funding.intents.store');
        Route::get(
            'funding/intents/{intent:reference}/instructions',
            CockpitFundingInstructionController::class,
        )->middleware((array) config('x-change.funding.instruction_access_middleware', []))
            ->name('x-change.cockpit.funding.intents.instructions.show');
        Route::post(
            'funding/intents/{intent:reference}/verification-checks',
            CockpitFundingVerificationCheckController::class,
        )->middleware((array) config('x-change.funding.manual_check_middleware', []))
            ->name('x-change.cockpit.funding.intents.verification-checks.store');
        Route::controller(CockpitNetbankStandingFundingAddressController::class)
            ->prefix('funding/standing-addresses/netbank')
            ->middleware((array) config('x-change.funding.standing_addresses.middleware', []))
            ->group(function (): void {
                Route::post('/', 'store')
                    ->name('x-change.cockpit.funding.standing-addresses.netbank.store');
                Route::post('history-checks', 'history')
                    ->name('x-change.cockpit.funding.standing-addresses.netbank.history-checks.store');
            });
        Route::post(
            'funding/standing-addresses/netbank/receipts/{receipt:reference}/approve',
            CockpitStandingFundingReceiptApprovalController::class,
        )->middleware((array) config('x-change.funding.standing_addresses.middleware', []))
            ->name('x-change.cockpit.funding.standing-addresses.netbank.receipts.approve');
        Route::post(
            'funding/scenarios/qrph',
            CockpitQrPhFundingSimulationController::class,
        )->middleware([
            ...(array) config('x-change.cockpit.qrph_funding_simulation.middleware', []),
            RequireVerifiedMobile::class,
        ])
            ->name('x-change.cockpit.funding.scenarios.qrph.store');
        Route::post(
            'funding/suspense/{case:reference}/reconciliation-requests',
            CockpitFundingReconciliationRequestController::class,
        )->name('x-change.cockpit.funding.suspense.reconciliation-requests.store');
        Route::post(
            'funding/reconciliations/{reconciliationRequest:reference}/approve',
            CockpitFundingReconciliationApprovalController::class,
        )->name('x-change.cockpit.funding.reconciliations.approve');
        Route::get('quick-generate', CockpitQuickGeneratePageController::class)->name('x-change.cockpit.quick-generate');
        Route::post('quick-generate', CockpitQuickGenerateMutationRouteShellController::class)->name('x-change.cockpit.quick-generate.store');
        Route::get('diagnostics/runtime-profile', CockpitRuntimeProfilePageController::class)->name('x-change.cockpit.diagnostics.runtime-profile');
        Route::prefix('pay-codes')->group(function (): void {
            Route::get('/', CockpitPayCodeExplorerPageController::class)->name('x-change.cockpit.pay-codes.index');
            Route::get('{code}/distribution', CockpitDistributionWorkspacePageController::class)->name('x-change.cockpit.pay-codes.distribution');
            Route::get('{code}', CockpitVoucherDetailPageController::class)->name('x-change.cockpit.pay-codes.show');
        });
    });

    Route::prefix('pay-codes')->group(function (): void {
        Route::get('/', PayCodeIndexPageController::class)->name('x-change.pay-codes.index');
        Route::get('create', PayCodeCreatePageController::class)->name('x-change.pay-codes.create');
        Route::get('{code}/approval', ClaimApprovalPageController::class)->name('x-change.pay-codes.approval');
        Route::get('{code}', PayCodeShowPageController::class)->name('x-change.pay-codes.show');
    });

    Route::get('balances', BalancePageController::class)->name('x-change.balances.index');
    Route::post('provider-wallets/paynamics', LinkPaynamicsWalletController::class)
        ->name('x-change.provider-wallets.paynamics.store');
});

// Public claim routes (no auth required)
Route::prefix('x')->middleware(['web', ShareXChangeBranding::class])->group(function (): void {
    Route::get('claim', ClaimStartController::class)->name('x-change.claim.start');
    Route::post('claim', ClaimStartController::class)->name('x-change.claim.start.submit');
    Route::get('claim/{code}/experience', ClaimExperienceController::class)
        ->name('x-change.claim.experience');
    Route::post('claim/{code}/complete', ClaimCompleteController::class)
        ->withoutMiddleware([VerifyCsrfToken::class])
        ->name('x-change.claim.complete');
    Route::post('claim/{code}/submit', ClaimSubmitController::class)->name('x-change.claim.submit');
    Route::get('claim/{code}/success', ClaimSuccessPageController::class)->name('x-change.claim.success');
    Route::get('claim/{code}/redirect', ClaimRedirectController::class)->name('x-change.claim.redirect');
    Route::get('claim/{code}/approval', ClaimApprovalPageController::class)
        ->name('x-change.claim.approval');
    Route::post('claim/{code}/approval/otp', ClaimApprovalOtpController::class)
        ->name('x-change.claim.approval.otp');
});
