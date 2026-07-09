<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityFeedbackHandoffContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityFeedbackHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityFeedbackHandoff;

it('defines a feedback handoff result that does not send feedback by default', function () {
    $result = new CockpitOperatorIssuanceActivityFeedbackHandoffResultData(
        activity_id: 'activity-1',
        correlation_id: 'corr-1',
    );

    expect($result->toArray())->toBe([
        'schema' => 'x-change.cockpit.operator-issuance-activity-feedback-handoff.v1',
        'status' => 'not_wired',
        'activity_id' => 'activity-1',
        'correlation_id' => 'corr-1',
        'feedback_intent_id' => null,
        'delivery_plan_id' => null,
        'delivery_receipt_id' => null,
        'feedback_required' => false,
        'sends_feedback' => false,
        'source' => 'null-cockpit-operator-issuance-activity-feedback-handoff',
        'reason' => 'x-feedback handoff is not wired. Cockpit does not send notifications in this boundary.',
        'metadata' => [],
    ]);
});

it('binds a null feedback handoff boundary by default', function () {
    expect(app(CockpitOperatorIssuanceActivityFeedbackHandoffContract::class))
        ->toBeInstanceOf(NullCockpitOperatorIssuanceActivityFeedbackHandoff::class);
});

it('hands operator activity to the null feedback boundary without sending notifications', function () {
    $handoff = app(CockpitOperatorIssuanceActivityFeedbackHandoffContract::class);

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
        ->toBeInstanceOf(CockpitOperatorIssuanceActivityFeedbackHandoffResultData::class)
        ->and($result->status)->toBe('not_wired')
        ->and($result->activity_id)->toBe('activity-1')
        ->and($result->correlation_id)->toBe('corr-1')
        ->and($result->feedback_intent_id)->toBeNull()
        ->and($result->delivery_plan_id)->toBeNull()
        ->and($result->delivery_receipt_id)->toBeNull()
        ->and($result->feedback_required)->toBeFalse()
        ->and($result->sends_feedback)->toBeFalse();
});
