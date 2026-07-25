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
        ->toContain('caller-supplied and mandatory')
        ->toContain('must not silently generate a reference and continue')
        ->toContain('two-step preparation flow')
        ->toContain('₱75, ₱50, and ₱25')
        ->toContain('three provider transfers totalling ₱150')
        ->toContain('accounting.after_claims')
        ->toContain('single ₱150 issuance reservation')
        ->and($catalog)
        ->toContain('treasury_settlement')
        ->toContain('reserves the full ₱150 beneficiary principal')
        ->toContain('rerunning the same command checks the provider balance again')
        ->toContain('Omitting `--run-reference` fails before issuance')
        ->toContain('could submit the same economic payment twice')
        ->toContain('exits before any provider call')
        ->toContain('It is not permission to submit a new run reference')
        ->toContain('divisible_open_three_slices_enforced_interval')
        ->toContain('₱75, ₱50, and ₱25')
        ->toContain('treasury_settlement.settlements')
        ->toContain('not presented as provider cash movement or multiplied by the three claims');
});
