<?php

declare(strict_types=1);

it('documents the Pay Code commercial waterfall and its remaining accounting gates', function () {
    $document = file_get_contents(
        __DIR__.'/../../../docs/architecture/TREASURY_INITIAL_STATE_AND_ACCOUNT_PORTFOLIOS.md',
    );
    $cockpitCompass = file_get_contents(
        __DIR__.'/../../../docs/ui-cockpit/COMPASS.md',
    );
    $settlementCompass = file_get_contents(
        __DIR__.'/../../../docs/architecture/SETTLEMENT_OS_COMPASS.md',
    );

    expect($document)->toBeString()
        ->toContain('## Pay Code commercial waterfall')
        ->toContain('Account Client Funds → system Commercial Clearing')
        ->toContain('Partner Commission Payable')
        ->toContain('A reversal creates exact compensating Treasury movements')
        ->toContain('Percentage rules, caps, taxes, royalties')
        ->toContain('must still be wired into every production issue, cancel, expire, and claim path')
        ->and($cockpitCompass)
        ->toContain('Quick Generate Treasury Movement Preview / Result Explanation')
        ->toContain('Before issuance')
        ->toContain('After issuance')
        ->toContain('After claim')
        ->toContain('Never hardcode the example amounts from a lifecycle run')
        ->toContain('No Quick Generate runtime or UI behavior is authorized by this entry')
        ->and($settlementCompass)
        ->toContain('Quick Generate Treasury Movement Preview / Result Explanation')
        ->toContain('projected and realized facts must remain distinct')
        ->toContain('Cockpit must consume sanitized read models rather than post accounting operations');
});
