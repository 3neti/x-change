<?php

declare(strict_types=1);

it('documents the Account Funding Code control and accounting boundary', function () {
    $protocol = file_get_contents(
        __DIR__.'/../../../docs/architecture/ACCOUNT_FUNDING_CODE_PROTOCOL.md',
    );
    $funding = file_get_contents(
        __DIR__.'/../../../docs/architecture/FUNDING_ACCOUNT_MANAGEMENT.md',
    );

    expect($protocol)
        ->toContain('It is not a payout Pay Code.')
        ->toContain('browser supplies only a requested value')
        ->toContain('maker cannot approve their own backing review')
        ->toContain('already owns enough recognized Client Funds')
        ->toContain('No payout provider is invoked.')
        ->toContain('File uploads are deliberately disabled')
        ->toContain('XCHANGE_FUNDING_REQUEST_REVIEWER_IDS')
        ->toContain('workflow rules, routes, read models, UI, tests, and documentation remain in the')
        ->and($funding)->toContain('ACCOUNT_FUNDING_CODE_PROTOCOL.md');
});
