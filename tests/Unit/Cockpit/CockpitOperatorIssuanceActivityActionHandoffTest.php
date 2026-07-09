<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityActionHandoffContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityActionHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityActionHandoff;

it('defines an action handoff result that does not execute actions by default', function () {
    $result = new CockpitOperatorIssuanceActivityActionHandoffResultData(
        activity_id: 'activity-1',
        correlation_id: 'corr-1',
    );

    expect($result->toArray())->toBe([
        'schema' => 'x-change.cockpit.operator-issuance-activity-action-handoff.v1',
        'status' => 'not_wired',
        'activity_id' => 'activity-1',
        'correlation_id' => 'corr-1',
        'action_hint_id' => null,
        'action_run_id' => null,
        'action_required' => false,
        'executes_action' => false,
        'source' => 'null-cockpit-operator-issuance-activity-action-handoff',
        'reason' => 'x-action handoff is not wired. Cockpit does not execute workflow actions in this boundary.',
        'metadata' => [],
    ]);
});

it('binds a null action handoff boundary by default', function () {
    expect(app(CockpitOperatorIssuanceActivityActionHandoffContract::class))
        ->toBeInstanceOf(NullCockpitOperatorIssuanceActivityActionHandoff::class);
});

it('hands operator activity to the null action boundary without executing actions', function () {
    $handoff = app(CockpitOperatorIssuanceActivityActionHandoffContract::class);

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
        ->toBeInstanceOf(CockpitOperatorIssuanceActivityActionHandoffResultData::class)
        ->and($result->status)->toBe('not_wired')
        ->and($result->activity_id)->toBe('activity-1')
        ->and($result->correlation_id)->toBe('corr-1')
        ->and($result->action_hint_id)->toBeNull()
        ->and($result->action_run_id)->toBeNull()
        ->and($result->action_required)->toBeFalse()
        ->and($result->executes_action)->toBeFalse();
});
