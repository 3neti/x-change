<?php

declare(strict_types=1);

it('documents receiver-side bank-transfer authority and exact-once posting', function () {
    $documentation = file_get_contents(
        __DIR__.'/../../../docs/architecture/FUNDING_ACCOUNT_MANAGEMENT.md',
    );

    expect($documentation)
        ->toContain('Provider-Verified Bank Transfer Funding')
        ->toContain('receiver-side provider history')
        ->toContain('x_change_provider_funding')
        ->toContain('screenshot or')
        ->toContain('arbitrary amount entry')
        ->toContain('provider_verified_auto')
        ->toContain('manual_dual_control')
        ->toContain('XCHANGE_FUNDING_BANK_TRANSFER_VERIFICATION_MODE');
});
