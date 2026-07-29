<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Http\Controllers\Web\BalancePageController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimApprovalOtpController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimApprovalPageController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimCompleteController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimExperienceController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimPageController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimRedirectController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimShareCardController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimStartController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimSubmitController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimSuccessPageController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitAccountPageController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitAccountScenarioController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitCampaignWorksheetAuthorizationController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitCampaignWorksheetBankTransferDispatchController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitCampaignWorksheetBankTransferReconciliationController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitCampaignWorksheetController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitCampaignWorksheetExportController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitCampaignWorksheetFulfillmentController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitCampaignWorksheetImportController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitDashboardPageController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitDistributionWorkspacePageController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingDestinationController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingInstructionController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingIntentController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingLiquidityRefreshController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingPageController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingQrMerchantProfileController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingReconciliationApprovalController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingReconciliationRequestController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingRequestApprovalController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingRequestController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingRequestEvidenceController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingRequestReviewController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingRequestTransferCheckController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingVerificationCheckController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitNetbankStandingFundingAddressController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitPayCodeExplorerPageController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitPayCodeFundingClaimController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitPayCodeFundingInspectionController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitPayCodeTemplateStoreController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitPayCodeTemplateUpdateController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitQrPhFundingSimulationController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitQuickGenerateClaimPreviewController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitQuickGenerateClaimPreviewExportController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitQuickGenerateClaimPreviewFrameController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitQuickGenerateClaimPreviewShowController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitQuickGenerateMutationRouteShellController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitQuickGeneratePageController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitReviewedFundingPayCodeClaimController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitRiderArtworkPreviewController;
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
use LBHurtado\XChange\Http\Controllers\Web\Payment\PaymentAttemptController;
use LBHurtado\XChange\Http\Controllers\Web\Payment\PaymentPageController;
use LBHurtado\XChange\Http\Controllers\Web\Payment\PaymentVerificationCheckController;
use LBHurtado\XChange\Http\Middleware\RequireVerifiedMobile;
use LBHurtado\XChange\Http\Middleware\ShareCockpitHeaderReadModel;
use LBHurtado\XChange\Http\Middleware\ShareXChangeBranding;

$middleware = config('x-change.routes.web_middleware', ['web', 'auth']);

Route::get(
    'x/claim/{code}/share-card/{sha256}.png',
    ClaimShareCardController::class,
)->where('sha256', '[a-f0-9]{64}')
    ->middleware((array) config(
        'x-change.claim.share.public_image_middleware',
        ['throttle:60,1'],
    ))
    ->name('x-change.claim.share-card.artifact');

Route::get('x/claim/{code}/share-card.png', ClaimShareCardController::class)
    ->middleware((array) config(
        'x-change.claim.share.public_image_middleware',
        ['throttle:60,1'],
    ))
    ->name('x-change.claim.share-card');

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
        Route::get('campaigns', [CockpitCampaignWorksheetController::class, 'index'])
            ->name('x-change.cockpit.campaigns.index');
        Route::post('campaigns', [CockpitCampaignWorksheetController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('x-change.cockpit.campaigns.store');
        Route::get('campaigns/{worksheet}', [CockpitCampaignWorksheetController::class, 'show'])
            ->name('x-change.cockpit.campaigns.show');
        Route::post('campaigns/{worksheet}/rows', [CockpitCampaignWorksheetController::class, 'addRow'])
            ->middleware('throttle:60,1')
            ->name('x-change.cockpit.campaigns.rows.store');
        Route::post('campaigns/{worksheet}/authorizations', [CockpitCampaignWorksheetAuthorizationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('x-change.cockpit.campaigns.authorizations.store');
        Route::post('campaigns/{worksheet}/fulfillments/pay-codes', [CockpitCampaignWorksheetFulfillmentController::class, 'store'])
            ->middleware('throttle:3,1')
            ->name('x-change.cockpit.campaigns.fulfillments.pay-codes.store');
        Route::get('campaigns/{worksheet}/exports/pay-codes.csv', CockpitCampaignWorksheetExportController::class)
            ->middleware('throttle:12,1')
            ->name('x-change.cockpit.campaigns.exports.pay-codes');
        Route::post('campaigns/{worksheet}/fulfillments/bank-transfers', [CockpitCampaignWorksheetBankTransferDispatchController::class, 'store'])
            ->middleware('throttle:2,1')
            ->name('x-change.cockpit.campaigns.fulfillments.bank-transfers.store');
        Route::post('campaigns/{worksheet}/fulfillments/bank-transfers/reconciliations', [CockpitCampaignWorksheetBankTransferReconciliationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('x-change.cockpit.campaigns.fulfillments.bank-transfers.reconciliations.store');
        Route::post('campaigns/{worksheet}/imports', [CockpitCampaignWorksheetImportController::class, 'stage'])
            ->middleware('throttle:12,1')
            ->name('x-change.cockpit.campaigns.imports.store');
        Route::post('campaigns/{worksheet}/imports/{import}/apply', [CockpitCampaignWorksheetImportController::class, 'apply'])
            ->middleware('throttle:12,1')
            ->name('x-change.cockpit.campaigns.imports.apply');
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
            Route::patch(
                'accounts/funding-qr-merchant-profile',
                CockpitFundingQrMerchantProfileController::class,
            )->name('x-change.cockpit.accounts.funding-qr-merchant-profile.update');
        });
        Route::get('funding', CockpitFundingPageController::class)->name('x-change.cockpit.funding.index');
        Route::post(
            'funding/liquidity-refreshes',
            CockpitFundingLiquidityRefreshController::class,
        )->middleware((array) config('x-change.funding.liquidity_refresh.middleware', []))
            ->name('x-change.cockpit.funding.liquidity-refreshes.store');
        Route::post(
            'funding/requests',
            CockpitFundingRequestController::class,
        )->middleware((array) config('x-change.funding.requests.create_middleware', []))
            ->name('x-change.cockpit.funding.requests.store');
        Route::post(
            'funding/requests/{fundingRequest:reference}/transfer-checks',
            CockpitFundingRequestTransferCheckController::class,
        )->middleware((array) config(
            'x-change.funding.requests.bank_transfer.check_middleware',
            ['throttle:6,1'],
        ))->name(
            'x-change.cockpit.funding.requests.transfer-checks.store',
        );
        Route::get(
            'funding/requests/{fundingRequest:reference}/evidence/{attachment}',
            CockpitFundingRequestEvidenceController::class,
        )->middleware((array) config('x-change.funding.requests.review_middleware', []))
            ->name('x-change.cockpit.funding.requests.evidence.show');
        Route::post(
            'funding/requests/{fundingRequest:reference}/reviews',
            CockpitFundingRequestReviewController::class,
        )->middleware((array) config('x-change.funding.requests.review_middleware', []))
            ->name('x-change.cockpit.funding.requests.reviews.store');
        Route::post(
            'funding/requests/{fundingRequest:reference}/approvals',
            CockpitFundingRequestApprovalController::class,
        )->middleware((array) config('x-change.funding.requests.approval_middleware', []))
            ->name('x-change.cockpit.funding.requests.approvals.store');
        Route::post(
            'funding/requests/{fundingRequest:reference}/pay-code-claims',
            CockpitReviewedFundingPayCodeClaimController::class,
        )->middleware((array) config('x-change.funding.requests.claim_middleware', []))
            ->name('x-change.cockpit.funding.requests.pay-code-claims.store');
        Route::post(
            'funding/pay-code-inspections',
            CockpitPayCodeFundingInspectionController::class,
        )->middleware((array) config('x-change.funding.pay_code_claims.inspect_middleware', []))
            ->name('x-change.cockpit.funding.pay-code-inspections.store');
        Route::post(
            'funding/pay-code-claims',
            CockpitPayCodeFundingClaimController::class,
        )->middleware((array) config('x-change.funding.pay_code_claims.claim_middleware', []))
            ->name('x-change.cockpit.funding.pay-code-claims.store');
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
            ->group(function (): void {
                Route::post('/', 'store')
                    ->middleware((array) config(
                        'x-change.funding.standing_addresses.instruction_middleware',
                        [],
                    ))
                    ->name('x-change.cockpit.funding.standing-addresses.netbank.store');
                Route::post('history-checks', 'history')
                    ->middleware((array) config(
                        'x-change.funding.standing_addresses.middleware',
                        [],
                    ))
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
        Route::post(
            'quick-generate/claim-previews',
            CockpitQuickGenerateClaimPreviewController::class,
        )->middleware('throttle:6,1')->name('x-change.cockpit.quick-generate.claim-previews.store');
        Route::get(
            'quick-generate/claim-previews/{claimPreviewArtifact:reference}',
            CockpitQuickGenerateClaimPreviewShowController::class,
        )->name('x-change.cockpit.quick-generate.claim-previews.show');
        Route::get(
            'quick-generate/claim-previews/{claimPreviewArtifact:reference}/frames/{step}',
            CockpitQuickGenerateClaimPreviewFrameController::class,
        )->where('step', '[a-z0-9][a-z0-9-]*')
            ->name('x-change.cockpit.quick-generate.claim-previews.frames.show');
        Route::get(
            'quick-generate/claim-previews/{claimPreviewArtifact:reference}/exports/{format}',
            CockpitQuickGenerateClaimPreviewExportController::class,
        )->whereIn('format', ['pdf', 'html'])
            ->name('x-change.cockpit.quick-generate.claim-previews.exports.show');
        Route::post(
            'quick-generate/artwork-previews',
            CockpitRiderArtworkPreviewController::class,
        )->middleware((array) config(
            'x-change.cockpit.quick_generate.url_artwork.middleware',
            ['throttle:12,1'],
        ))->name('x-change.cockpit.quick-generate.artwork-previews.store');
        Route::post('pay-code-templates', CockpitPayCodeTemplateStoreController::class)
            ->middleware('throttle:20,1')
            ->name('x-change.cockpit.pay-code-templates.store');
        Route::patch(
            'pay-code-templates/{template:reference}',
            CockpitPayCodeTemplateUpdateController::class,
        )->middleware('throttle:20,1')
            ->name('x-change.cockpit.pay-code-templates.update');
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
    Route::get('pay/{code}', PaymentPageController::class)
        ->middleware((array) config('x-change.payment.attempts.public_read_middleware', []))
        ->name('x-change.pay.show');
    Route::post('pay/{code}/attempts', PaymentAttemptController::class)
        ->middleware((array) config('x-change.payment.attempts.public_start_middleware', []))
        ->name('x-change.pay.attempts.store');
    Route::post(
        'pay/{code}/attempts/{attempt:reference}/checks',
        PaymentVerificationCheckController::class,
    )->middleware((array) config('x-change.payment.attempts.public_check_middleware', []))
        ->name('x-change.pay.attempts.checks.store');
    Route::get('claim', ClaimStartController::class)->name('x-change.claim.start');
    Route::post('claim', ClaimStartController::class)->name('x-change.claim.start.submit');
    Route::get('claim/{code}', ClaimPageController::class)
        ->middleware((array) config('x-change.claim.public_read_middleware', []))
        ->name('x-change.claim.show');
    Route::get('claim/{code}/experience', ClaimExperienceController::class)
        ->middleware((array) config('x-change.claim.public_read_middleware', []))
        ->name('x-change.claim.experience');
    Route::post('claim/{code}/flows', ClaimStartController::class)
        ->middleware((array) config('x-change.claim.public_start_middleware', []))
        ->name('x-change.claim.flows.store');
    Route::post('claim/{code}/complete', ClaimCompleteController::class)
        ->withoutMiddleware([VerifyCsrfToken::class])
        ->middleware((array) config('x-change.claim.public_callback_middleware', []))
        ->name('x-change.claim.complete');
    Route::post('claim/{code}/submit', ClaimSubmitController::class)
        ->middleware((array) config('x-change.claim.public_submit_middleware', []))
        ->name('x-change.claim.submit');
    Route::get('claim/{code}/success', ClaimSuccessPageController::class)->name('x-change.claim.success');
    Route::get('claim/{code}/redirect', ClaimRedirectController::class)->name('x-change.claim.redirect');
    Route::get('claim/{code}/approval', ClaimApprovalPageController::class)
        ->name('x-change.claim.approval');
    Route::post('claim/{code}/approval/otp', ClaimApprovalOtpController::class)
        ->name('x-change.claim.approval.otp');
});
