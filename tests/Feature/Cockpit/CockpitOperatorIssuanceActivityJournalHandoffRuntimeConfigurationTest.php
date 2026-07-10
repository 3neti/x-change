<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityJournalHandoff;

it('keeps durable activity journal handoff disabled by default configuration', function () {
    expect(config('x-change.cockpit.operator_issuance_activity.journal_handoff'))->toBeNull()
        ->and(app(CockpitOperatorIssuanceActivityJournalHandoffContract::class))
        ->toBeInstanceOf(NullCockpitOperatorIssuanceActivityJournalHandoff::class);
});

it('resolves a configured durable activity journal handoff service without calling x journal', function () {
    config()->set('x-change.cockpit.operator_issuance_activity.journal_handoff', FakeConfiguredCockpitActivityJournalHandoff::class);

    app()->forgetInstance(CockpitOperatorIssuanceActivityJournalHandoffContract::class);

    $handoff = app(CockpitOperatorIssuanceActivityJournalHandoffContract::class);

    expect($handoff)->toBeInstanceOf(FakeConfiguredCockpitActivityJournalHandoff::class);

    $result = $handoff->handoff(cockpitJournalHandoffActivity());

    expect($result->status)->toBe('configured-test-only')
        ->and($result->writes_journal)->toBeFalse()
        ->and($result->activity_id)->toBe('activity-journal-1')
        ->and($result->correlation_id)->toBe('corr-journal-1');
});

it('returns a not wired result from the null durable activity journal handoff runtime', function () {
    $result = app(CockpitOperatorIssuanceActivityJournalHandoffContract::class)
        ->handoff(cockpitJournalHandoffActivity());

    expect($result->status)->toBe('not_wired')
        ->and($result->writes_journal)->toBeFalse()
        ->and($result->activity_id)->toBe('activity-journal-1')
        ->and($result->correlation_id)->toBe('corr-journal-1');
});

function cockpitJournalHandoffActivity(): CockpitOperatorIssuanceActivityItemData
{
    return new CockpitOperatorIssuanceActivityItemData(
        id: 'activity-journal-1',
        code: 'PC-JOURNAL-1',
        amount: '25',
        currency: 'PHP',
        status: 'issued',
        issued_at: '2026-07-10T09:00:00+08:00',
        route: 'x-change.cockpit.quick-generate.store',
        correlation_id: 'corr-journal-1',
        idempotency_key: 'idem-journal-1',
        operator_id: 'operator-1',
        detail_href: '/x/cockpit/pay-codes/PC-JOURNAL-1',
    );
}

class FakeConfiguredCockpitActivityJournalHandoff implements CockpitOperatorIssuanceActivityJournalHandoffContract
{
    public function handoff(CockpitOperatorIssuanceActivityItemData $activity): CockpitOperatorIssuanceActivityJournalHandoffResultData
    {
        return new CockpitOperatorIssuanceActivityJournalHandoffResultData(
            status: 'configured-test-only',
            activity_id: $activity->id,
            correlation_id: $activity->correlation_id,
            writes_journal: false,
            source: self::class,
            reason: 'Configured handoff resolution test only; no x-journal runtime call is made.',
        );
    }
}
