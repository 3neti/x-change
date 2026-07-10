<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffStatusProjectionData;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityJournalHandoffStatusProjector;

it('defines a journal handoff status projection result without persistence by default', function () {
    $projection = new CockpitOperatorIssuanceActivityJournalHandoffStatusProjectionData(
        activity_id: 'activity-1',
        correlation_id: 'corr-1',
        journal_handoff_status: 'recorded',
        journal_entry_id: 'journal-1',
    );

    expect($projection->toArray())->toBe([
        'schema' => 'x-change.cockpit.operator-issuance-activity-journal-handoff-status-projection.v1',
        'status' => 'not_persisted',
        'activity_id' => 'activity-1',
        'correlation_id' => 'corr-1',
        'journal_handoff_status' => 'recorded',
        'journal_entry_id' => 'journal-1',
        'persists_status' => false,
        'source' => 'null-cockpit-operator-issuance-activity-journal-handoff-status-projector',
        'reason' => 'Journal handoff status projection is not wired. Durable activity status remains unchanged.',
        'metadata' => [],
    ]);
});

it('binds a null journal handoff status projector by default', function () {
    expect(app(CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class))
        ->toBeInstanceOf(NullCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class);
});

it('projects journal handoff result metadata without persisting durable activity status', function () {
    $projection = app(CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class)
        ->project(new CockpitOperatorIssuanceActivityJournalHandoffResultData(
            status: 'recorded',
            activity_id: 'activity-1',
            correlation_id: 'corr-1',
            journal_entry_id: 'journal-1',
            writes_journal: true,
            source: 'x-journal-execution-journal-recorder',
            reason: 'Cockpit durable activity was handed off to x-journal.',
            metadata: [
                'reference_number' => 'XJ-0001',
                'event_type' => 'cockpit.operator_issuance_activity.recorded',
            ],
        ));

    expect($projection)
        ->toBeInstanceOf(CockpitOperatorIssuanceActivityJournalHandoffStatusProjectionData::class)
        ->and($projection->status)->toBe('not_persisted')
        ->and($projection->activity_id)->toBe('activity-1')
        ->and($projection->correlation_id)->toBe('corr-1')
        ->and($projection->journal_handoff_status)->toBe('recorded')
        ->and($projection->journal_entry_id)->toBe('journal-1')
        ->and($projection->persists_status)->toBeFalse()
        ->and($projection->metadata)->toBe([
            'handoff_source' => 'x-journal-execution-journal-recorder',
            'handoff_writes_journal' => true,
            'handoff_reason' => 'Cockpit durable activity was handed off to x-journal.',
            'handoff_metadata' => [
                'reference_number' => 'XJ-0001',
                'event_type' => 'cockpit.operator_issuance_activity.recorded',
            ],
        ]);
});
