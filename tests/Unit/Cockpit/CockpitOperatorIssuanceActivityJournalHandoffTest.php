<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityJournalHandoff;

it('defines a journal handoff result that does not write journal truth by default', function () {
    $result = new CockpitOperatorIssuanceActivityJournalHandoffResultData(
        activity_id: 'activity-1',
        correlation_id: 'corr-1',
    );

    expect($result->toArray())->toBe([
        'schema' => 'x-change.cockpit.operator-issuance-activity-journal-handoff.v1',
        'status' => 'not_wired',
        'activity_id' => 'activity-1',
        'correlation_id' => 'corr-1',
        'journal_entry_id' => null,
        'writes_journal' => false,
        'source' => 'null-cockpit-operator-issuance-activity-journal-handoff',
        'reason' => 'x-journal handoff is not wired. Cockpit activity remains operational evidence only.',
        'metadata' => [],
    ]);
});

it('binds a null journal handoff boundary by default', function () {
    expect(app(CockpitOperatorIssuanceActivityJournalHandoffContract::class))
        ->toBeInstanceOf(NullCockpitOperatorIssuanceActivityJournalHandoff::class);
});

it('hands operator activity to the null journal boundary without writing journal entries', function () {
    $handoff = app(CockpitOperatorIssuanceActivityJournalHandoffContract::class);

    $result = $handoff->handoff(new CockpitOperatorIssuanceActivityItemData(
        id: 'activity-1',
        code: 'PC-1234',
        amount: '25',
        currency: 'PHP',
        status: 'issued',
        issued_at: '2026-07-10T09:00:00+00:00',
        route: 'x-change.cockpit.quick-generate.store',
        correlation_id: 'corr-1',
        idempotency_key: 'idem-1',
        operator_id: 'operator-1',
        detail_href: '/x/cockpit/pay-codes/PC-1234',
    ));

    expect($result)
        ->toBeInstanceOf(CockpitOperatorIssuanceActivityJournalHandoffResultData::class)
        ->and($result->status)->toBe('not_wired')
        ->and($result->activity_id)->toBe('activity-1')
        ->and($result->correlation_id)->toBe('corr-1')
        ->and($result->journal_entry_id)->toBeNull()
        ->and($result->writes_journal)->toBeFalse();
});
