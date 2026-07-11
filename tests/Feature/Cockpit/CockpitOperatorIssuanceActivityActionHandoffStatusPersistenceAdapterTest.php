<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityActionHandoffResultData;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityActionHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityActionHandoffStatusProjector;

it('keeps action handoff status projection non-persistent by default', function () {
    expect(app(CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract::class))
        ->toBeInstanceOf(NullCockpitOperatorIssuanceActivityActionHandoffStatusProjector::class);
});

it('persists safe action handoff status and metadata to durable activity rows', function () {
    CockpitOperatorIssuanceActivity::query()->create([
        'activity_id' => 'activity-action-projector-1',
        'actor_id' => 'operator-1',
        'subject_reference' => 'PC-ACTION-PROJECTOR',
        'action_handoff_status' => 'not_wired',
        'metadata' => [
            'existing' => 'kept',
        ],
    ]);

    $projection = app(DatabaseCockpitOperatorIssuanceActivityActionHandoffStatusProjector::class)->project(
        new CockpitOperatorIssuanceActivityActionHandoffResultData(
            status: 'composed',
            activity_id: 'activity-action-projector-1',
            correlation_id: 'corr-action-projector-1',
            action_hint_id: 'cockpit.pay-code.open',
            action_run_id: 'action-run-1',
            action_required: false,
            executes_action: false,
            source: 'x-action-cockpit-operator-issuance-activity-action-handoff',
            reason: 'x-action composed presentation-only operator action hints for this Cockpit activity.',
            metadata: [
                'event_or_state' => 'cockpit.operator_issuance_activity.recorded',
                'actions' => [
                    [
                        'key' => 'cockpit.pay-code.open',
                        'label' => 'Open Pay Code',
                        'run_id' => 'action-run-1',
                        'target' => [
                            'url' => '/x/cockpit/pay-codes/PC-ACTION-PROJECTOR',
                            'redirectable' => true,
                        ],
                        'provider_payload' => 'must-not-persist',
                    ],
                ],
                'composition' => [
                    'presentation_only' => true,
                    'executes_action' => false,
                ],
                'safe_diagnostics' => [
                    [
                        'action_key' => 'cockpit.pay-code.open',
                        'status' => 'included',
                        'reason' => 'included',
                    ],
                ],
                'provider_payload' => 'must-not-persist',
                'wallet' => 'must-not-persist',
            ],
        ),
    );

    $activity = CockpitOperatorIssuanceActivity::query()->where('activity_id', 'activity-action-projector-1')->sole();

    expect($projection->status)->toBe('persisted')
        ->and($projection->activity_id)->toBe('activity-action-projector-1')
        ->and($projection->action_handoff_status)->toBe('composed')
        ->and($projection->action_hint_id)->toBe('cockpit.pay-code.open')
        ->and($projection->action_run_id)->toBe('action-run-1')
        ->and($projection->persists_status)->toBeTrue()
        ->and($activity->action_handoff_status)->toBe('composed')
        ->and($activity->metadata['existing'])->toBe('kept')
        ->and($activity->metadata['action_handoff']['status'])->toBe('composed')
        ->and($activity->metadata['action_handoff']['action_hint_id'])->toBe('cockpit.pay-code.open')
        ->and($activity->metadata['action_handoff']['action_run_id'])->toBe('action-run-1')
        ->and($activity->metadata['action_handoff']['executes_action'])->toBeFalse()
        ->and($activity->metadata['action_handoff']['metadata']['event_or_state'])->toBe('cockpit.operator_issuance_activity.recorded')
        ->and($activity->metadata['action_handoff']['metadata']['actions'][0]['key'])->toBe('cockpit.pay-code.open')
        ->and($activity->metadata['action_handoff']['metadata'])->not->toHaveKey('provider_payload')
        ->and($activity->metadata['action_handoff']['metadata'])->not->toHaveKey('wallet')
        ->and($activity->metadata['action_handoff']['metadata']['actions'][0])->not->toHaveKey('provider_payload');
});
