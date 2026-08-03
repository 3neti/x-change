<?php

declare(strict_types=1);

it('documents the provider-neutral Treasury initial state and operating controls', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $document = file_get_contents(
        $packageRoot.'/docs/architecture/TREASURY_INITIAL_STATE_AND_ACCOUNT_PORTFOLIOS.md',
    );

    expect($document)
        ->toContain('Treasury Clearing Position')
        ->toContain('Legacy Unattributed Position')
        ->toContain('Client Funds Position')
        ->toContain('x-change:treasury:preflight')
        ->toContain('x-change:treasury:provision')
        ->toContain('x-change:treasury:reconcile-opening')
        ->toContain('x-change:treasury:repair-missing-disbursement-postings')
        ->toContain('x-change:treasury:migrate-legacy-account')
        ->toContain('x-change:treasury:simulate-deposit')
        ->toContain('never debits Client Funds')
        ->toContain('already_repaired')
        ->toContain('No form, command, webhook body, or operator can directly set Internal Balance.')
        ->toContain('max(0, min(Client Funds, Provider Liquidity - Outstanding Pay Codes))')
        ->toContain('x-legal remains advisory');
});
