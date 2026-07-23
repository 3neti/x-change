<?php

declare(strict_types=1);

it('documents the QR Ph payer identity and onboarding boundaries in package-owned guides', function () {
    $architecture = file_get_contents(
        __DIR__.'/../../../docs/architecture/FUNDING_ACCOUNT_MANAGEMENT.md',
    );
    $catalog = file_get_contents(
        __DIR__.'/../../../docs/lifecycle-scenarios/catalog.md',
    );
    $demos = file_get_contents(
        __DIR__.'/../../../docs/lifecycle-scenarios/demo-scenarios.md',
    );

    expect($architecture)
        ->toContain('QR Ph Payer Identity')
        ->toContain('A webhook cannot create a user')
        ->toContain('Registration does not self-verify a mobile')
        ->toContain('qrph_funding_existing_mobile_demo')
        ->toContain('qrph_funding_unknown_mobile_onboarding_demo')
        ->and($catalog)
        ->toContain('no payment before identity resolution')
        ->toContain('OTP-gated mobile verification')
        ->and($demos)
        ->toContain('one atomic credit')
        ->toContain('same signed funding pipeline under one parent rollback boundary');
});
