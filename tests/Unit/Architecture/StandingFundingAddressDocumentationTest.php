<?php

declare(strict_types=1);

it('documents the Standing Funding Address protocol and operator controls in package guides', function () {
    $protocol = file_get_contents(
        __DIR__.'/../../../docs/architecture/STANDING_FUNDING_ADDRESS_PROTOCOL.md',
    );
    $architecture = file_get_contents(
        __DIR__.'/../../../docs/architecture/FUNDING_ACCOUNT_MANAGEMENT.md',
    );
    $runbook = file_get_contents(
        __DIR__.'/../../../docs/ui-cockpit/ACCOUNT_MANAGEMENT_RUNBOOK.md',
    );

    expect($protocol)
        ->toContain('provider + exact destination address → one purpose + one Account')
        ->toContain('"purpose": "account_funding"')
        ->toContain('"purpose": "funding_intent"')
        ->toContain('"purpose": "payment"')
        ->toContain('payer mobile, mobile prefix, amount, time window')
        ->toContain('observe_only')
        ->toContain('supervised')
        ->toContain('automatic')
        ->toContain('XCHANGE_STANDING_FUNDING_CREDITABLE_STATUSES')
        ->toContain('cannot create another Inventory recognition or Account credit')
        ->toContain('xchange:funding:sync-standing --provider=netbank --limit=100')
        ->toContain('NETBANK_FUNDING_CORPORATE_ACCOUNT_NAME')
        ->toContain('NETBANK_FUNDING_VCA_ALIAS_TOKEN')
        ->toContain('The alias token is a provider-issued credential')
        ->toContain('Browser Acceptance')
        ->toContain('Paynamics and Future Providers')
        ->toContain('No automated test or Codex workflow initiates a real-money payment')
        ->and($architecture)
        ->toContain('immutable Account Funding Address binding')
        ->toContain('STANDING_FUNDING_ADDRESS_PROTOCOL.md')
        ->and($runbook)
        ->toContain('Approve verified credit')
        ->toContain('previously applied receipts were not applied again')
        ->toContain('never enters an amount, transaction ID, payer mobile, or destination');
});
