<?php

declare(strict_types=1);

it('documents the Pay Code commercial waterfall and its remaining accounting gates', function () {
    $document = file_get_contents(
        __DIR__.'/../../../docs/architecture/TREASURY_INITIAL_STATE_AND_ACCOUNT_PORTFOLIOS.md',
    );

    expect($document)->toBeString()
        ->toContain('## Pay Code commercial waterfall')
        ->toContain('Account Client Funds → system Commercial Clearing')
        ->toContain('Partner Commission Payable')
        ->toContain('A reversal creates exact compensating Treasury movements')
        ->toContain('Percentage rules, caps, taxes, royalties')
        ->toContain('must still be wired into every production issue, cancel, expire, and claim path');
});
