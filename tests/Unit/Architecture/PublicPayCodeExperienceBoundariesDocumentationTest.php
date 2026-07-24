<?php

declare(strict_types=1);

it('documents the public claim payment and topup boundaries', function () {
    $documentation = file_get_contents(
        dirname(__DIR__, 3).'/docs/architecture/PUBLIC_PAY_CODE_EXPERIENCE_BOUNDARIES.md',
    );

    expect($documentation)
        ->toContain('/x/claim/{code}')
        ->toContain('/x/pay/{code}')
        ->toContain('/x/cockpit/funding')
        ->toContain('machine-readable claim-experience')
        ->toContain('Account Funding Receipt')
        ->toContain('fresh pre-transaction validation token')
        ->toContain('Retrying the same payment attempt');
});

it('documents authoritative Payment Attempts and keeps them separate from Account funding', function () {
    $documentation = file_get_contents(
        dirname(__DIR__, 3).'/docs/PAY_CODE_PAYMENT_FLOW.md',
    );

    expect($documentation)
        ->toContain('Payment Attempt → Voucher Collection')
        ->toContain('never creates a Funding Intent, Account Funding Receipt')
        ->toContain('Check NetBank')
        ->toContain('provider status is `settled`')
        ->toContain('xchange:payments:verify-open')
        ->toContain('does not overwrite or invalidate an existing')
        ->toContain('long-lived Account Funding QR does not consume a new token')
        ->toContain('provider_instruction_failed')
        ->toContain('x-change/claim/Payment')
        ->toContain('1280×900 and 390×844');
});

it('documents the canonical claim start mutation and separate inward payment route', function () {
    $documentation = file_get_contents(
        dirname(__DIR__, 3).'/docs/CLAIM_FLOW_MAP.md',
    );

    expect($documentation)
        ->toContain('GET /x/claim/{code}')
        ->toContain('POST /x/claim/{code}/flows')
        ->toContain('GET /x/claim/{code}/experience')
        ->toContain('Inward collection is implemented separately at `/x/pay/{code}`');
});
