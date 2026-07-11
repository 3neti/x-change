<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;
use LBHurtado\XChange\Services\Cockpit\DurableCockpitOperatorIssuanceActivityReadModelProvider;

it('hydrates dashboard operator activity presentation with planned x-feedback handoff facts', function () {
    actingAsTestUser();

    CockpitOperatorIssuanceActivity::query()->create([
        'activity_id' => 'activity-x-feedback-readmodel',
        'actor_id' => (string) auth()->id(),
        'source' => 'cockpit.quick-generate',
        'subject_type' => 'pay_code',
        'subject_reference' => 'PC-XFEEDBACK-READMODEL',
        'status' => 'issued',
        'occurred_at' => now()->toAtomString(),
        'correlation_id' => 'corr-x-feedback-readmodel',
        'summary' => 'Pay Code PC-XFEEDBACK-READMODEL issued',
        'safe_context' => [
            'amount' => '25',
            'currency' => 'PHP',
            'route' => 'cockpit.quick-generate',
            'detail_href' => '/x/cockpit/pay-codes/PC-XFEEDBACK-READMODEL',
        ],
        'feedback_handoff_status' => 'planned',
        'metadata' => [
            'feedback_handoff' => [
                'status' => 'planned',
                'feedback_intent_id' => 'cockpit.operator_issuance_activity.recorded',
                'delivery_plan_id' => 'plan-feedback-readmodel',
                'delivery_receipt_id' => null,
                'feedback_required' => false,
                'sends_feedback' => false,
                'source' => 'x-feedback-cockpit-operator-issuance-activity-feedback-handoff',
                'reason' => 'x-feedback prepared an operator activity delivery plan without dispatching provider delivery.',
                'metadata' => [
                    'intent_key' => 'cockpit.operator_issuance_activity.recorded',
                    'event_type' => 'cockpit.operator_issuance_activity.recorded',
                    'delivery_boundary' => 'prepare_only',
                    'planned_deliveries' => 1,
                    'channels' => ['in_app'],
                    'plan_items' => [
                        [
                            'intent_key' => 'cockpit.operator_issuance_activity.recorded',
                            'recipient_type' => 'operator',
                            'recipient_id' => (string) auth()->id(),
                            'channel' => 'in_app',
                            'status' => 'planned',
                            'priority' => 100,
                            'provider_payload' => 'must-not-render',
                        ],
                    ],
                    'composition' => [
                        'presentation_only' => true,
                        'sends_feedback' => false,
                        'owns_lifecycle_truth' => false,
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
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.code', 'PC-XFEEDBACK-READMODEL')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.handoffs.feedback', 'planned')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.feedback_handoff.status', 'planned')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.feedback_handoff.feedback_intent_id', 'cockpit.operator_issuance_activity.recorded')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.feedback_handoff.delivery_plan_id', 'plan-feedback-readmodel')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.feedback_handoff.sends_feedback', false)
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.feedback_handoff.metadata.delivery_boundary', 'prepare_only')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.feedback_handoff.metadata.channels.0', 'in_app')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.feedback_handoff.metadata.plan_items.0.channel', 'in_app')
        ->assertJsonMissingPath('props.operator_issuance_activity_read_model.presentations.0.metadata.feedback_handoff.metadata.provider_payload')
        ->assertJsonMissingPath('props.operator_issuance_activity_read_model.presentations.0.metadata.feedback_handoff.metadata.plan_items.0.provider_payload');
});
