<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityJournalHandoffStatusProjector;

it('keeps the journal handoff status projector null by default', function () {
    expect(config('x-change.cockpit.operator_issuance_activity.journal_handoff_status_projector'))->toBeNull()
        ->and(app(CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class))
        ->toBeInstanceOf(NullCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class);
});

it('resolves the database journal handoff status projector when explicitly configured', function () {
    config()->set(
        'x-change.cockpit.operator_issuance_activity.journal_handoff_status_projector',
        DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class,
    );

    app()->forgetInstance(CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class);

    expect(app(CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class))
        ->toBeInstanceOf(DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class);
});

it('persists journal handoff status and safe metadata onto an existing durable activity row', function () {
    $activity = CockpitOperatorIssuanceActivity::query()->create([
        'activity_id' => 'activity-status-1',
        'actor_id' => 'operator-1',
        'subject_reference' => 'PC-STATUS-1',
        'status' => 'issued',
        'severity' => 'info',
        'occurred_at' => '2026-07-10T09:00:00+08:00',
        'journal_handoff_status' => 'not_wired',
        'action_handoff_status' => 'not_wired',
        'feedback_handoff_status' => 'not_wired',
        'safe_context' => [
            'code' => 'PC-STATUS-1',
        ],
        'metadata' => [
            'source' => 'test',
            'journal_handoff' => [
                'status' => 'older',
            ],
        ],
    ]);

    $projection = app(DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class)
        ->project(new CockpitOperatorIssuanceActivityJournalHandoffResultData(
            status: 'recorded',
            activity_id: 'activity-status-1',
            correlation_id: 'corr-status-1',
            journal_entry_id: 'journal-status-1',
            writes_journal: true,
            source: 'x-journal-execution-journal-recorder',
            reason: 'Cockpit durable activity was handed off to x-journal.',
            metadata: [
                'reference_number' => 'XJ-0001',
                'event_type' => 'cockpit.operator_issuance_activity.recorded',
                'idempotency_key' => 'safe-hash',
                'raw_payload' => ['must' => 'not persist'],
                'provider_payload' => ['must' => 'not persist'],
            ],
        ));

    $activity->refresh();

    expect($projection->status)->toBe('persisted')
        ->and($projection->persists_status)->toBeTrue()
        ->and($projection->journal_handoff_status)->toBe('recorded')
        ->and($projection->journal_entry_id)->toBe('journal-status-1')
        ->and($activity->journal_handoff_status)->toBe('recorded')
        ->and($activity->action_handoff_status)->toBe('not_wired')
        ->and($activity->feedback_handoff_status)->toBe('not_wired')
        ->and($activity->safe_context)->toBe([
            'code' => 'PC-STATUS-1',
        ])
        ->and($activity->metadata)->toBe([
            'source' => 'test',
            'journal_handoff' => [
                'status' => 'recorded',
                'journal_entry_id' => 'journal-status-1',
                'writes_journal' => true,
                'source' => 'x-journal-execution-journal-recorder',
                'reason' => 'Cockpit durable activity was handed off to x-journal.',
                'metadata' => [
                    'reference_number' => 'XJ-0001',
                    'event_type' => 'cockpit.operator_issuance_activity.recorded',
                    'idempotency_key' => 'safe-hash',
                ],
            ],
        ]);
});

it('no ops when the durable activity row cannot be found', function () {
    $projection = app(DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class)
        ->project(new CockpitOperatorIssuanceActivityJournalHandoffResultData(
            status: 'recorded',
            activity_id: 'missing-activity',
            correlation_id: 'corr-missing',
            journal_entry_id: 'journal-missing',
            writes_journal: true,
        ));

    expect(CockpitOperatorIssuanceActivity::query()->count())->toBe(0)
        ->and($projection->status)->toBe('missing_activity')
        ->and($projection->activity_id)->toBe('missing-activity')
        ->and($projection->correlation_id)->toBe('corr-missing')
        ->and($projection->journal_handoff_status)->toBe('recorded')
        ->and($projection->journal_entry_id)->toBe('journal-missing')
        ->and($projection->persists_status)->toBeFalse();
});

it('no ops when the handoff result has no activity id', function () {
    $projection = app(DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class)
        ->project(new CockpitOperatorIssuanceActivityJournalHandoffResultData(
            status: 'failed_non_blocking',
            activity_id: null,
            correlation_id: 'corr-no-activity',
            writes_journal: false,
        ));

    expect($projection->status)->toBe('missing_activity_id')
        ->and($projection->activity_id)->toBeNull()
        ->and($projection->correlation_id)->toBe('corr-no-activity')
        ->and($projection->journal_handoff_status)->toBe('failed_non_blocking')
        ->and($projection->persists_status)->toBeFalse();
});
