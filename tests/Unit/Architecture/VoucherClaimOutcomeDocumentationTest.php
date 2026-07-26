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
        ->toContain('requester owns an ordinary `PAYABLE` Voucher')
        ->toContain('system Treasury pays the exact target once')
        ->toContain('`CompleteVoucherCollection`')
        ->toContain('php artisan x-change:funding:issue-pay-code FUND-XXXX')
        ->toContain('Account owner is not offered a claim action')
        ->toContain('XCHANGE_FUNDING_REQUEST_EVIDENCE_DISK')
        ->toContain('`pay_code_issued`')
        ->toContain('maximum execution cost across all offered outcomes')
        ->toContain('rejects issuance that combines')
        ->toContain('Account Funding makes zero provider calls')
        ->toContain('**Recipient receives**')
        ->toContain('`/x/cockpit/funding?mode=pay_code`')
        ->toContain('The Pay Code is never placed in the URL')
        ->toContain('Quick Generate handoff acceptance — 2026-07-26')
        ->toContain('disabled payout rail')
        ->toContain('open-slice')
        ->toContain('XCHANGE_REVIEWED_FUNDING_PAY_CODE_TTL_SECONDS')
        ->toContain('Implemented acceptance — 2026-07-25')
        ->toContain('`390 × 844`')
        ->toContain('no horizontal')
        ->toContain('application emitted no browser-console error')
        ->toContain('workflow rules, routes, read models, UI, tests, and documentation live in')
        ->and($funding)->toContain('VOUCHER_CLAIM_OUTCOME_PROTOCOL.md');
});
