<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectorContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityFeedbackHandoffResultData;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector;

it('keeps feedback handoff status projection non-persistent by default', function () {
    expect(app(CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectorContract::class))
        ->toBeInstanceOf(NullCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector::class);
});

it('persists safe feedback handoff status and metadata to durable activity rows', function () {
    CockpitOperatorIssuanceActivity::query()->create([
        'activity_id' => 'activity-feedback-projector-1',
        'actor_id' => 'operator-1',
        'subject_reference' => 'PC-FEEDBACK-PROJECTOR',
        'feedback_handoff_status' => 'not_wired',
        'metadata' => [
            'existing' => 'kept',
        ],
    ]);

    $projection = app(DatabaseCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector::class)->project(
        new CockpitOperatorIssuanceActivityFeedbackHandoffResultData(
            status: 'planned',
            activity_id: 'activity-feedback-projector-1',
            correlation_id: 'corr-feedback-projector-1',
            feedback_intent_id: 'cockpit.operator_issuance_activity.recorded',
            delivery_plan_id: 'plan-feedback-1',
            feedback_required: false,
            sends_feedback: false,
            source: 'x-feedback-cockpit-operator-issuance-activity-feedback-handoff',
            reason: 'x-feedback prepared an operator activity delivery plan without dispatching provider delivery.',
            metadata: [
                'intent_key' => 'cockpit.operator_issuance_activity.recorded',
                'event_type' => 'cockpit.operator_issuance_activity.recorded',
                'delivery_boundary' => 'prepare_only',
                'planned_deliveries' => 1,
                'channels' => ['in_app'],
                'plan_items' => [
                    [
                        'intent_key' => 'cockpit.operator_issuance_activity.recorded',
                        'recipient_type' => 'operator',
                        'recipient_id' => 'operator-1',
                        'channel' => 'in_app',
                        'status' => 'planned',
                        'priority' => 100,
                        'provider_payload' => 'must-not-persist',
                    ],
                ],
                'composition' => [
                    'presentation_only' => true,
                    'sends_feedback' => false,
                ],
                'raw_payload' => 'must-not-persist',
                'provider_payload' => 'must-not-persist',
                'wallet' => 'must-not-persist',
            ],
        ),
    );

    $activity = CockpitOperatorIssuanceActivity::query()->where('activity_id', 'activity-feedback-projector-1')->sole();

    expect($projection->status)->toBe('persisted')
        ->and($projection->activity_id)->toBe('activity-feedback-projector-1')
        ->and($projection->feedback_handoff_status)->toBe('planned')
        ->and($projection->feedback_intent_id)->toBe('cockpit.operator_issuance_activity.recorded')
        ->and($projection->delivery_plan_id)->toBe('plan-feedback-1')
        ->and($projection->delivery_receipt_id)->toBeNull()
        ->and($projection->persists_status)->toBeTrue()
        ->and($activity->feedback_handoff_status)->toBe('planned')
        ->and($activity->metadata['existing'])->toBe('kept')
        ->and($activity->metadata['feedback_handoff']['status'])->toBe('planned')
        ->and($activity->metadata['feedback_handoff']['feedback_intent_id'])->toBe('cockpit.operator_issuance_activity.recorded')
        ->and($activity->metadata['feedback_handoff']['delivery_plan_id'])->toBe('plan-feedback-1')
        ->and($activity->metadata['feedback_handoff']['sends_feedback'])->toBeFalse()
        ->and($activity->metadata['feedback_handoff']['metadata']['intent_key'])->toBe('cockpit.operator_issuance_activity.recorded')
        ->and($activity->metadata['feedback_handoff']['metadata']['channels'])->toBe(['in_app'])
        ->and($activity->metadata['feedback_handoff']['metadata']['plan_items'][0]['channel'])->toBe('in_app')
        ->and($activity->metadata['feedback_handoff']['metadata'])->not->toHaveKey('raw_payload')
        ->and($activity->metadata['feedback_handoff']['metadata'])->not->toHaveKey('provider_payload')
        ->and($activity->metadata['feedback_handoff']['metadata'])->not->toHaveKey('wallet')
        ->and($activity->metadata['feedback_handoff']['metadata']['plan_items'][0])->not->toHaveKey('provider_payload');
});

it('no ops when the durable activity row cannot be found', function () {
    $projection = app(DatabaseCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector::class)
        ->project(new CockpitOperatorIssuanceActivityFeedbackHandoffResultData(
            status: 'planned',
            activity_id: 'missing-activity',
            correlation_id: 'corr-missing',
            feedback_intent_id: 'cockpit.operator_issuance_activity.recorded',
            delivery_plan_id: 'plan-missing',
        ));

    expect(CockpitOperatorIssuanceActivity::query()->count())->toBe(0)
        ->and($projection->status)->toBe('missing_activity')
        ->and($projection->activity_id)->toBe('missing-activity')
        ->and($projection->correlation_id)->toBe('corr-missing')
        ->and($projection->feedback_handoff_status)->toBe('planned')
        ->and($projection->feedback_intent_id)->toBe('cockpit.operator_issuance_activity.recorded')
        ->and($projection->delivery_plan_id)->toBe('plan-missing')
        ->and($projection->persists_status)->toBeFalse();
});
