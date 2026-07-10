<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;

it('does not mutate durable activity rows through the null journal handoff status projector', function () {
    $activity = CockpitOperatorIssuanceActivity::query()->create([
        'activity_id' => 'activity-projector-1',
        'subject_reference' => 'PC-PROJECTOR-1',
        'status' => 'issued',
        'severity' => 'info',
        'occurred_at' => '2026-07-10T09:00:00+08:00',
        'journal_handoff_status' => 'not_wired',
        'action_handoff_status' => 'not_wired',
        'feedback_handoff_status' => 'not_wired',
        'safe_context' => [
            'code' => 'PC-PROJECTOR-1',
        ],
        'metadata' => [
            'source' => 'test',
        ],
    ]);

    $projection = app(CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class)
        ->project(new CockpitOperatorIssuanceActivityJournalHandoffResultData(
            status: 'recorded',
            activity_id: 'activity-projector-1',
            correlation_id: 'corr-projector-1',
            journal_entry_id: 'journal-projector-1',
            writes_journal: true,
        ));

    $activity->refresh();

    expect($projection->persists_status)->toBeFalse()
        ->and($projection->status)->toBe('not_persisted')
        ->and($activity->journal_handoff_status)->toBe('not_wired')
        ->and($activity->action_handoff_status)->toBe('not_wired')
        ->and($activity->feedback_handoff_status)->toBe('not_wired')
        ->and($activity->metadata)->toBe([
            'source' => 'test',
        ]);
});
