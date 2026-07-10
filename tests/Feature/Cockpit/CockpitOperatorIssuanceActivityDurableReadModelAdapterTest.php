<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;

it('keeps operator issuance activity read models not wired when durable persistence is disabled', function () {
    $readModel = app(CockpitReadModelProviderContract::class)
        ->forOperatorIssuanceActivity(new CockpitReadModelQueryData);

    expect($readModel->status)->toBe('not_wired')
        ->and($readModel->authorized)->toBeFalse()
        ->and($readModel->items)->toBe([])
        ->and($readModel->presentations)->toBe([]);
});

it('hydrates operator issuance activity from the configured durable repository', function () {
    config()->set('x-change.cockpit.operator_issuance_activity.repository', DatabaseCockpitOperatorIssuanceActivityRepository::class);
    config()->set('x-change.cockpit.operator_issuance_activity.recorder', DatabaseCockpitOperatorIssuanceActivityRecorder::class);

    $repository = app(DatabaseCockpitOperatorIssuanceActivityRepository::class);

    $repository->record(new CockpitOperatorIssuanceActivityRecordData(
        activity_id: 'activity-durable-1',
        actor_id: 'operator-1',
        actor_label: 'Treasury Operations',
        source: 'cockpit.quick-generate',
        subject_type: 'pay_code',
        subject_reference: 'PC-DURABLE-1',
        status: 'issued',
        occurred_at: '2026-07-10T09:00:00+08:00',
        correlation_id: 'corr-durable-1',
        summary: 'Pay Code PC-DURABLE-1 issued',
        safe_context: [
            'amount' => '25',
            'currency' => 'PHP',
            'route' => 'x-change.cockpit.quick-generate.store',
            'detail_href' => '/x/cockpit/pay-codes/PC-DURABLE-1',
        ],
        journal_handoff_status: 'not_wired',
        action_handoff_status: 'not_wired',
        feedback_handoff_status: 'not_wired',
        metadata: [
            'source' => 'x-change.cockpit',
        ],
    ));

    $readModel = app(CockpitReadModelProviderContract::class)
        ->forOperatorIssuanceActivity(new CockpitReadModelQueryData(
            operatorId: 'operator-1',
            include: ['operator_issuance_activity'],
            correlationId: 'corr-durable-1',
        ));

    expect($readModel->status)->toBe('available')
        ->and($readModel->authorized)->toBeTrue()
        ->and($readModel->source)->toBe('durable-operator-issuance-activity-read-model')
        ->and($readModel->items)->toHaveCount(1)
        ->and($readModel->items[0]->id)->toBe('activity-durable-1')
        ->and($readModel->items[0]->code)->toBe('PC-DURABLE-1')
        ->and($readModel->items[0]->amount)->toBe('25')
        ->and($readModel->items[0]->currency)->toBe('PHP')
        ->and($readModel->items[0]->detail_href)->toBe('/x/cockpit/pay-codes/PC-DURABLE-1')
        ->and($readModel->presentations)->toHaveCount(1)
        ->and($readModel->presentations[0]->title)->toBe('Pay Code PC-DURABLE-1 issued')
        ->and($readModel->presentations[0]->handoffs)->toBe([
            'journal' => 'not_wired',
            'action' => 'not_wired',
            'feedback' => 'not_wired',
        ])
        ->and($readModel->redactions['raw_payloads_exposed'])->toBeFalse()
        ->and($readModel->redactions['moves_money'])->toBeFalse();
});
