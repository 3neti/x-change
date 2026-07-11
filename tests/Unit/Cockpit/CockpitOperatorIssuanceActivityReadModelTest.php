<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityPresentationData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Services\Cockpit\NullCockpitReadModelProvider;

it('defines an operator-safe issuance activity item contract', function () {
    $item = new CockpitOperatorIssuanceActivityItemData(
        id: 'activity-1',
        code: 'PC-1234',
        amount: '100.00',
        currency: 'PHP',
        status: 'issued',
        issued_at: '2026-07-10T09:00:00+08:00',
        route: 'cockpit.quick-generate',
        correlation_id: 'corr-1',
        idempotency_key: 'idem-1',
        operator_id: 'operator-1',
        detail_href: '/x/cockpit/pay-codes/PC-1234',
        metadata: [
            'source' => 'x-change.cockpit',
            'presentation_only' => true,
        ],
    );

    expect($item->toArray())->toBe([
        'id' => 'activity-1',
        'code' => 'PC-1234',
        'amount' => '100.00',
        'currency' => 'PHP',
        'status' => 'issued',
        'issued_at' => '2026-07-10T09:00:00+08:00',
        'route' => 'cockpit.quick-generate',
        'correlation_id' => 'corr-1',
        'idempotency_key' => 'idem-1',
        'operator_id' => 'operator-1',
        'detail_href' => '/x/cockpit/pay-codes/PC-1234',
        'metadata' => [
            'source' => 'x-change.cockpit',
            'presentation_only' => true,
        ],
    ])
        ->and($item->toArray())->not->toHaveKeys([
            'raw_payload',
            'provider_payload',
            'wallet',
            'balance',
            'account_number',
            'recipient_secret',
            'otp',
            'funding_source',
        ]);
});

it('defines the operator issuance activity read model with unavailable and empty states', function () {
    $readModel = new CockpitOperatorIssuanceActivityReadModelData;

    expect($readModel->schema)->toBe('x-change.cockpit.operator-issuance-activity.v1')
        ->and($readModel->status)->toBe('not_wired')
        ->and($readModel->authorized)->toBeFalse()
        ->and($readModel->source)->toBe('null-operator-issuance-activity-read-model')
        ->and($readModel->items)->toBe([])
        ->and($readModel->presentations)->toBe([])
        ->and($readModel->empty_state)->toBe([
            'title' => 'No operator issuance activity available',
            'description' => 'Activity recording is not wired yet. Quick Generate can still use the existing issuance path.',
        ])
        ->and($readModel->search_filters)->toBe([
            'schema' => 'x-change.cockpit.operator-issuance-activity-search-filter.v1',
            'status' => 'not_available',
            'read_only' => true,
            'search' => null,
            'statuses' => [],
            'handoff_statuses' => [],
            'available_statuses' => [],
            'available_handoff_statuses' => [],
        ])
        ->and($readModel->redactions)->toBe([
            'payloads' => 'activity-summary-only',
            'raw_payloads_exposed' => false,
            'provider_payloads_exposed' => false,
            'wallet_data_exposed' => false,
            'recipient_secrets_exposed' => false,
            'lifecycle_truth' => false,
            'writes_journal' => false,
            'executes_actions' => false,
            'sends_feedback' => false,
            'moves_money' => false,
        ]);
});

it('exposes operator issuance activity through the cockpit read model provider contract', function () {
    $provider = new NullCockpitReadModelProvider;

    expect($provider)->toBeInstanceOf(CockpitReadModelProviderContract::class);

    $readModel = $provider->forOperatorIssuanceActivity(new CockpitReadModelQueryData(
        operatorId: 'operator-1',
        include: ['operator_issuance_activity'],
        correlationId: 'corr-1',
    ));

    expect($readModel)
        ->toBeInstanceOf(CockpitOperatorIssuanceActivityReadModelData::class)
        ->and($readModel->status)->toBe('not_wired')
        ->and($readModel->items)->toBe([])
        ->and($readModel->redactions['lifecycle_truth'])->toBeFalse()
        ->and($readModel->redactions['writes_journal'])->toBeFalse()
        ->and($readModel->redactions['executes_actions'])->toBeFalse()
        ->and($readModel->redactions['sends_feedback'])->toBeFalse()
        ->and($readModel->redactions['moves_money'])->toBeFalse();
});

it('adopts presentation DTOs in the operator issuance activity read model', function () {
    $readModel = new CockpitOperatorIssuanceActivityReadModelData(
        status: 'available',
        authorized: true,
        source: 'x-change.cockpit.operator-issuance-activity.presenter',
        items: [
            new CockpitOperatorIssuanceActivityItemData(
                id: 'activity-1',
                code: 'PC-1234',
                amount: '100.00',
                currency: 'PHP',
                status: 'issued',
                issued_at: '2026-07-10T09:00:00+08:00',
                route: 'cockpit.quick-generate',
                correlation_id: 'corr-1',
                detail_href: '/x/cockpit/pay-codes/PC-1234',
            ),
        ],
        presentations: [
            new CockpitOperatorIssuanceActivityPresentationData(
                id: 'activity-1',
                code: 'PC-1234',
                title: 'Pay Code PC-1234 issued',
                subtitle: 'PHP 100.00 issued through Quick Generate',
                status: 'issued',
                detail_href: '/x/cockpit/pay-codes/PC-1234',
                correlation_id: 'corr-1',
            ),
        ],
    );

    expect($readModel->toArray())
        ->toHaveKey('presentations')
        ->and($readModel->toArray()['presentations'][0])->toBe([
            'schema' => 'x-change.cockpit.operator-issuance-activity-presentation.v1',
            'id' => 'activity-1',
            'code' => 'PC-1234',
            'title' => 'Pay Code PC-1234 issued',
            'subtitle' => 'PHP 100.00 issued through Quick Generate',
            'status' => 'issued',
            'detail_href' => '/x/cockpit/pay-codes/PC-1234',
            'correlation_id' => 'corr-1',
            'handoffs' => [
                'journal' => 'not_wired',
                'action' => 'not_wired',
                'feedback' => 'not_wired',
            ],
            'safety' => [
                'presentation_only' => true,
                'writes_journal' => false,
                'executes_actions' => false,
                'sends_feedback' => false,
                'moves_money' => false,
                'owns_lifecycle_truth' => false,
            ],
            'metadata' => [],
        ])
        ->and($readModel->toArray())->not->toHaveKeys([
            'raw_payload',
            'provider_payload',
            'wallet',
            'balance',
            'account_number',
            'recipient_secret',
            'otp',
            'funding_source',
        ]);
});
