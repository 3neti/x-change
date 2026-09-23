<?php

declare(strict_types=1);

it('locks the campaign QR Ph architecture and current compass checkpoint', function () {
    $packageRoot = dirname(__DIR__, 3);
    $plan = file_get_contents(
        $packageRoot.'/docs/architecture/campaign-qr-ph/CAMPAIGN_QR_PH_PLAN.md',
    );
    $compass = file_get_contents(
        $packageRoot.'/docs/architecture/campaign-qr-ph/CAMPAIGN_QR_PH_COMPASS.md',
    );

    expect($plan)
        ->toContain('| Endpoint | Web link or ordinary QR |')
        ->toContain('Campaign QR Ph')
        ->toContain('ProvisionalCoverage')
        ->toContain('Coverage and the first envelope version commit atomically')
        ->toContain('aui.personal-accident.provisional-cover@1.0.0')
        ->toContain('payment.*`: system-managed')
        ->toContain('coverage.*`: system-managed')
        ->toContain('applicant.*`: claimant-managed')
        ->toContain('policy.*`: insurer/integration-managed')
        ->toContain('No live payment is authorized by this document')
        ->and($compass)
        ->toContain('Reusable multi-payment characterization complete')
        ->toContain('provider-neutral payer identity DTO')
        ->toContain('no Account Funding receipt is created')
        ->toContain('01M3656EXH0FWZGJKBPGXCNT8J')
        ->toContain('pre-payment provider synchronization returned zero observations')
        ->toContain('Gate 1 live characterization report')
        ->toContain('two distinct')
        ->toContain('three rows')
        ->toContain('ReduceProviderFundingTransactionEvidence')
        ->toContain('CanonicalProviderFundingTransactionData')
        ->toContain('exactly two settled transactions')
        ->toContain('provider-reported')
        ->toContain('payer name, source account, and institution code')
        ->toContain('explicit payer mobile')
        ->toContain('is absent')
        ->toContain('additive encrypted payer-evidence schema')
        ->toContain('providerVerified=false')
        ->toContain('bounded multi-page iterator')
        ->toContain('netbank-account-hmac-v2');
});
