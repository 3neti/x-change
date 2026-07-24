<?php

declare(strict_types=1);

it('documents the position-backed live payout and replay boundary', function () {
    $packageRoot = dirname(__DIR__, 3);
    $architecture = file_get_contents(
        $packageRoot.'/docs/architecture/TREASURY_INITIAL_STATE_AND_ACCOUNT_PORTFOLIOS.md',
    );
    $catalog = file_get_contents(
        $packageRoot.'/docs/lifecycle-scenarios/catalog.md',
    );

    expect($architecture)
        ->toContain('Pay Code Reserve Position')
        ->toContain('reserves the beneficiary principal')
        ->toContain("sender's system charge is a separate economic leg")
        ->toContain('provider_sync_pending')
        ->toContain('x-change:treasury:backfill-standing-funding-positions')
        ->toContain('x-change:treasury:correct-pay-code-fee-posting')
        ->toContain('already_corrected')
        ->toContain('never repeats the payout')
        ->and($catalog)
        ->toContain('treasury_settlement')
        ->toContain('reserves only the beneficiary principal')
        ->toContain('rerunning the same command checks the provider balance again')
        ->toContain('It is not permission to submit a new run reference');
});
