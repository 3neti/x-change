<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Services\Cockpit\XFeedbackCockpitOperatorIssuanceActivityFeedbackHandoff;
use LBHurtado\XFeedback\XFeedbackServiceProvider;

it('prepares x-feedback operator activity delivery plans without dispatching provider delivery', function () {
    $this->app->register(XFeedbackServiceProvider::class);

    $result = app(XFeedbackCockpitOperatorIssuanceActivityFeedbackHandoff::class)->handoff(
        new CockpitOperatorIssuanceActivityItemData(
            id: 'activity-x-feedback-1',
            code: 'PC-XFEEDBACK-1',
            amount: '25',
            currency: 'PHP',
            status: 'issued',
            issued_at: '2026-07-11T09:00:00+08:00',
            route: 'cockpit.quick-generate',
            correlation_id: 'corr-x-feedback-1',
            operator_id: 'operator-1',
            detail_href: '/x/cockpit/pay-codes/PC-XFEEDBACK-1',
        ),
    );

    expect($result->status)->toBe('planned')
        ->and($result->activity_id)->toBe('activity-x-feedback-1')
        ->and($result->correlation_id)->toBe('corr-x-feedback-1')
        ->and($result->feedback_intent_id)->toBe('cockpit.operator_issuance_activity.recorded')
        ->and($result->delivery_plan_id)->toStartWith('plan-')
        ->and($result->delivery_receipt_id)->toBeNull()
        ->and($result->feedback_required)->toBeFalse()
        ->and($result->sends_feedback)->toBeFalse()
        ->and($result->source)->toBe('x-feedback-cockpit-operator-issuance-activity-feedback-handoff')
        ->and($result->metadata['delivery_boundary'])->toBe('prepare_only')
        ->and($result->metadata['planned_deliveries'])->toBe(1)
        ->and($result->metadata['channels'])->toBe(['in_app'])
        ->and($result->metadata['plan_items'][0]['channel'])->toBe('in_app')
        ->and($result->metadata['plan_items'][0]['status'])->toBe('planned')
        ->and($result->metadata['composition'])->toBe([
            'presentation_only' => true,
            'delivery_only' => false,
            'sends_feedback' => false,
            'records_lifecycle' => false,
            'owns_lifecycle_truth' => false,
        ])
        ->and($result->metadata)->not->toHaveKey('raw_payload')
        ->and($result->metadata)->not->toHaveKey('provider_payload')
        ->and($result->metadata)->not->toHaveKey('wallet');
});
