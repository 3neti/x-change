<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;
use LBHurtado\XChange\Services\Cockpit\DurableCockpitOperatorIssuanceActivityReadModelProvider;

it('hydrates dashboard operator activity presentation with composed x-action handoff facts', function () {
    actingAsTestUser();

    CockpitOperatorIssuanceActivity::query()->create([
        'activity_id' => 'activity-x-action-readmodel',
        'actor_id' => (string) auth()->id(),
        'source' => 'cockpit.quick-generate',
        'subject_type' => 'pay_code',
        'subject_reference' => 'PC-XACTION-READMODEL',
        'status' => 'issued',
        'occurred_at' => now()->toAtomString(),
        'correlation_id' => 'corr-x-action-readmodel',
        'summary' => 'Pay Code PC-XACTION-READMODEL issued',
        'safe_context' => [
            'amount' => '25',
            'currency' => 'PHP',
            'route' => 'cockpit.quick-generate',
            'detail_href' => '/x/cockpit/pay-codes/PC-XACTION-READMODEL',
        ],
        'action_handoff_status' => 'composed',
        'metadata' => [
            'action_handoff' => [
                'status' => 'composed',
                'action_hint_id' => 'cockpit.pay-code.open',
                'action_run_id' => 'action-run-readmodel',
                'action_required' => false,
                'executes_action' => false,
                'source' => 'x-action-cockpit-operator-issuance-activity-action-handoff',
                'reason' => 'x-action composed presentation-only operator action hints for this Cockpit activity.',
                'metadata' => [
                    'event_or_state' => 'cockpit.operator_issuance_activity.recorded',
                    'actions' => [
                        [
                            'key' => 'cockpit.pay-code.open',
                            'label' => 'Open Pay Code',
                            'run_id' => 'action-run-readmodel',
                            'target' => [
                                'url' => '/x/cockpit/pay-codes/PC-XACTION-READMODEL',
                                'redirectable' => true,
                            ],
                        ],
                    ],
                    'composition' => [
                        'presentation_only' => true,
                        'executes_action' => false,
                    ],
                    'provider_payload' => 'must-not-render',
                ],
            ],
        ],
    ]);

    config()->set('x-change.cockpit.operator_issuance_activity.repository', 'database');

    app()->forgetInstance(CockpitOperatorIssuanceActivityRepositoryContract::class);
    app()->forgetInstance(CockpitReadModelProviderContract::class);
    app()->forgetInstance(DurableCockpitOperatorIssuanceActivityReadModelProvider::class);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.operator_issuance_activity_read_model.status', 'available')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.code', 'PC-XACTION-READMODEL')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.handoffs.action', 'composed')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.action_handoff.status', 'composed')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.action_handoff.action_hint_id', 'cockpit.pay-code.open')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.action_handoff.action_run_id', 'action-run-readmodel')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.action_handoff.executes_action', false)
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.action_handoff.metadata.event_or_state', 'cockpit.operator_issuance_activity.recorded')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.action_handoff.metadata.actions.0.key', 'cockpit.pay-code.open')
        ->assertJsonMissingPath('props.operator_issuance_activity_read_model.presentations.0.metadata.action_handoff.metadata.provider_payload');
});
