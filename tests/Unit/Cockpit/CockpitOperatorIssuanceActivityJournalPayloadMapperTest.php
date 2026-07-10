<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalPayloadData;
use LBHurtado\XChange\Services\Cockpit\CockpitOperatorIssuanceActivityJournalPayloadMapper;

it('maps operator issuance activity into a journal ready payload without x journal runtime dependencies', function () {
    $payload = app(CockpitOperatorIssuanceActivityJournalPayloadMapper::class)
        ->map(cockpitJournalPayloadActivity());

    expect($payload)->toBeInstanceOf(CockpitOperatorIssuanceActivityJournalPayloadData::class)
        ->and($payload->schema)->toBe('x-change.cockpit.operator-issuance-activity-journal-payload.v1')
        ->and($payload->event_name)->toBe('cockpit.operator_issuance_activity.recorded')
        ->and($payload->domain)->toBe('cockpit')
        ->and($payload->idempotency_key)->toBe(hash('sha256', 'cockpit.operator_issuance_activity|activity-map-1|corr-map-1|PC-MAP-1|operator-1'))
        ->and($payload->actor)->toBe([
            'type' => 'operator',
            'id' => 'operator-1',
        ])
        ->and($payload->subject)->toBe([
            'type' => 'pay_code',
            'reference' => 'PC-MAP-1',
        ])
        ->and($payload->references)->toBe([
            'activity_id' => 'activity-map-1',
            'correlation_id' => 'corr-map-1',
            'causation_id' => 'activity-map-1',
        ])
        ->and($payload->payload)->toBe([
            'activity_id' => 'activity-map-1',
            'code' => 'PC-MAP-1',
            'amount' => '25',
            'currency' => 'PHP',
            'status' => 'issued',
            'issued_at' => '2026-07-10T09:00:00+08:00',
            'route' => 'x-change.cockpit.quick-generate.store',
            'detail_href' => '/x/cockpit/pay-codes/PC-MAP-1',
        ]);
});

it('excludes raw provider wallet recipient secret otp and funding source data from the journal payload', function () {
    $payload = app(CockpitOperatorIssuanceActivityJournalPayloadMapper::class)
        ->map(cockpitJournalPayloadActivity(metadata: [
            'raw_payload' => ['secret' => 'value'],
            'provider_payload' => ['authorization' => 'Bearer token'],
            'wallet' => ['balance' => 100],
            'recipient_secret' => 'secret',
            'otp' => '123456',
            'funding_source' => 'wallet',
            'safe_note' => 'allowed',
        ]));

    expect($payload->metadata)->toBe([
        'source' => 'x-change.cockpit',
        'safe_note' => 'allowed',
        'redactions' => [
            'raw_payloads_exposed' => false,
            'provider_payloads_exposed' => false,
            'wallet_data_exposed' => false,
            'recipient_secrets_exposed' => false,
            'otp_exposed' => false,
            'funding_source_exposed' => false,
        ],
    ])
        ->and($payload->toArray())->not->toHaveKeys([
            'raw_payload',
            'provider_payload',
            'wallet',
            'recipient_secret',
            'otp',
            'funding_source',
        ]);
});

it('falls back to activity id when correlation and operator details are absent', function () {
    $payload = app(CockpitOperatorIssuanceActivityJournalPayloadMapper::class)
        ->map(cockpitJournalPayloadActivity(correlationId: null, operatorId: null));

    expect($payload->idempotency_key)->toBe(hash('sha256', 'cockpit.operator_issuance_activity|activity-map-1||PC-MAP-1|'))
        ->and($payload->actor)->toBe([
            'type' => 'operator',
            'id' => null,
        ])
        ->and($payload->references)->toBe([
            'activity_id' => 'activity-map-1',
            'correlation_id' => null,
            'causation_id' => 'activity-map-1',
        ]);
});

function cockpitJournalPayloadActivity(
    ?string $correlationId = 'corr-map-1',
    ?string $operatorId = 'operator-1',
    array $metadata = ['source' => 'x-change.cockpit'],
): CockpitOperatorIssuanceActivityItemData {
    return new CockpitOperatorIssuanceActivityItemData(
        id: 'activity-map-1',
        code: 'PC-MAP-1',
        amount: '25',
        currency: 'PHP',
        status: 'issued',
        issued_at: '2026-07-10T09:00:00+08:00',
        route: 'x-change.cockpit.quick-generate.store',
        correlation_id: $correlationId,
        idempotency_key: 'raw-idempotency-key',
        operator_id: $operatorId,
        detail_href: '/x/cockpit/pay-codes/PC-MAP-1',
        metadata: $metadata,
    );
}
