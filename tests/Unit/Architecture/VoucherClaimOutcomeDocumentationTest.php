<?php

declare(strict_types=1);

it('documents Voucher claim outcomes and reviewed funding boundaries', function () {
    $protocol = file_get_contents(
        __DIR__.'/../../../docs/architecture/VOUCHER_CLAIM_OUTCOME_PROTOCOL.md',
    );
    $funding = file_get_contents(
        __DIR__.'/../../../docs/architecture/FUNDING_ACCOUNT_MANAGEMENT.md',
    );

    expect($protocol)
        ->toContain('`account_funding` is therefore a claim outcome')
        ->toContain('does not maintain an `AccountFundingCode`')
        ->toContain('one immutable outcome selection per Voucher')
        ->toContain('Compatibility inference is read-only')
        ->toContain('The Treasury reservation is the sole monetary')
        ->toContain('`pay_code_issued`')
        ->toContain('maximum execution cost across all offered outcomes')
        ->toContain('rejects issuance that combines')
        ->toContain('Account Funding makes zero provider calls')
        ->toContain('XCHANGE_REVIEWED_FUNDING_PAY_CODE_TTL_SECONDS')
        ->toContain('Implemented acceptance — 2026-07-25')
        ->toContain('`390 × 844`')
        ->toContain('no horizontal')
        ->toContain('application emitted no browser-console error')
        ->toContain('workflow rules, routes, read models, UI, tests, and documentation live in')
        ->and($funding)->toContain('VOUCHER_CLAIM_OUTCOME_PROTOCOL.md');
});
