<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Actions\Payment\ReduceProviderFundingTransactionEvidence;
use LBHurtado\XChange\Exceptions\IncompatibleProviderFundingEvidence;

it('reduces compatible immutable evidence versions to one canonical payment', function () {
    $pending = providerFundingEvidence(
        transactionId: 'provider-transaction-1',
        status: 'pending',
        payload: 'pending-v1',
        settledAt: null,
        operationId: null,
    );
    $settled = providerFundingEvidence(
        transactionId: 'provider-transaction-1',
        status: 'settled',
        payload: 'settled-v1',
        settledAt: '2026-09-23T03:38:39Z',
        operationId: 'operation-1',
    );
    $refined = providerFundingEvidence(
        transactionId: 'provider-transaction-1',
        status: 'settled',
        payload: 'settled-v2',
        settledAt: '2026-09-23T03:38:45Z',
        operationId: 'operation-1',
    );

    $result = app(ReduceProviderFundingTransactionEvidence::class)->handle([
        $refined,
        $pending,
        $settled,
    ]);

    expect($result->providerCode)->toBe('netbank')
        ->and($result->providerTransactionKey)->toHaveLength(64)
        ->and($result->providerOperationKey)->toHaveLength(64)
        ->and($result->grossAmountMinor)->toBe(537)
        ->and($result->netAmountMinor)->toBe(537)
        ->and($result->currency)->toBe('PHP')
        ->and($result->providerStatus)->toBe('settled')
        ->and($result->occurredAt?->toIso8601String())->toBe('2026-09-23T03:38:39+00:00')
        ->and($result->settledAt?->toIso8601String())->toBe('2026-09-23T03:38:45+00:00')
        ->and($result->settlementRail)->toBe('INSTAPAY')
        ->and($result->destinationVerified)->toBeTrue()
        ->and($result->canonicalObservationId)->toBe($refined->getKey())
        ->and($result->evidenceObservationIds)->toBe([
            $pending->getKey(),
            $settled->getKey(),
            $refined->getKey(),
        ])
        ->and($result->evidenceCount())->toBe(3)
        ->and($result->normalizationVersions)->toBe(['netbank-standing-credit-v2']);
});

it('fails closed when economic evidence changes', function (string $field, mixed $value) {
    $first = providerFundingEvidence(
        transactionId: 'provider-transaction-conflict',
        status: 'settled',
        payload: 'settled-original',
        settledAt: '2026-09-23T03:38:39Z',
    );
    $attributes = [
        'gross_amount_minor' => 537,
        'fee_amount_minor' => 0,
        'net_amount_minor' => 537,
        'currency' => 'PHP',
        'funding_address' => 'sha256:funding-address',
        'provider_account_reference' => 'sha256:provider-account',
        'occurred_at' => '2026-09-23T03:38:39Z',
    ];
    $attributes[$field] = $value;
    $conflicting = providerFundingEvidence(
        transactionId: 'provider-transaction-conflict',
        status: 'settled',
        payload: 'settled-conflict-'.$field,
        settledAt: '2026-09-23T03:38:45Z',
        overrides: $attributes,
    );

    expect(fn () => app(ReduceProviderFundingTransactionEvidence::class)->handle([
        $first,
        $conflicting,
    ]))->toThrow(IncompatibleProviderFundingEvidence::class, $field);
})->with([
    'gross amount' => ['gross_amount_minor', 538],
    'fee amount' => ['fee_amount_minor', 1],
    'net amount' => ['net_amount_minor', 536],
    'currency' => ['currency', 'USD'],
    'funding address' => ['funding_address', 'sha256:another-address'],
    'provider account' => ['provider_account_reference', 'sha256:another-account'],
    'occurrence time' => ['occurred_at', '2026-09-23T03:38:40Z'],
]);

it('fails closed on a settled status regression', function () {
    $settled = providerFundingEvidence(
        transactionId: 'provider-transaction-regression',
        status: 'settled',
        payload: 'settled',
        settledAt: '2026-09-23T03:38:39Z',
    );
    $pending = providerFundingEvidence(
        transactionId: 'provider-transaction-regression',
        status: 'pending',
        payload: 'regressed',
        settledAt: null,
    );

    expect(fn () => app(ReduceProviderFundingTransactionEvidence::class)->handle([
        $settled,
        $pending,
    ]))->toThrow(
        IncompatibleProviderFundingEvidence::class,
        'settled -> pending',
    );
});

it('fails closed on undocumented adverse provider status evolution', function (string $status) {
    $settled = providerFundingEvidence(
        transactionId: 'provider-transaction-adverse-'.$status,
        status: 'settled',
        payload: 'settled',
        settledAt: '2026-09-23T03:38:39Z',
    );
    $adverse = providerFundingEvidence(
        transactionId: 'provider-transaction-adverse-'.$status,
        status: $status,
        payload: $status,
        settledAt: '2026-09-23T03:38:39Z',
    );

    expect(fn () => app(ReduceProviderFundingTransactionEvidence::class)->handle([
        $settled,
        $adverse,
    ]))->toThrow(IncompatibleProviderFundingEvidence::class);
})->with([
    'reversed',
    'refunded',
    'charged back' => 'charged_back',
    'returned',
]);

it('fails closed when settlement time regresses', function () {
    $first = providerFundingEvidence(
        transactionId: 'provider-transaction-time-regression',
        status: 'settled',
        payload: 'settled-later',
        settledAt: '2026-09-23T03:38:45Z',
    );
    $regressed = providerFundingEvidence(
        transactionId: 'provider-transaction-time-regression',
        status: 'settled',
        payload: 'settled-earlier',
        settledAt: '2026-09-23T03:38:39Z',
    );

    expect(fn () => app(ReduceProviderFundingTransactionEvidence::class)->handle([
        $first,
        $regressed,
    ]))->toThrow(
        IncompatibleProviderFundingEvidence::class,
        'settled_at_regression',
    );
});

it('fails closed when destination verification changes', function () {
    $first = providerFundingEvidence(
        transactionId: 'provider-transaction-destination-conflict',
        status: 'settled',
        payload: 'destination-verified',
        settledAt: '2026-09-23T03:38:39Z',
    );
    $conflicting = providerFundingEvidence(
        transactionId: 'provider-transaction-destination-conflict',
        status: 'settled',
        payload: 'destination-unverified',
        settledAt: '2026-09-23T03:38:45Z',
        overrides: [
            'metadata' => [
                'settlement_rail' => 'INSTAPAY',
                'destination_verified' => false,
                'normalization_version' => 'netbank-standing-credit-v2',
            ],
        ],
    );

    expect(fn () => app(ReduceProviderFundingTransactionEvidence::class)->handle([
        $first,
        $conflicting,
    ]))->toThrow(
        IncompatibleProviderFundingEvidence::class,
        'destination_verified',
    );
});

it('loads all durable versions for one provider transaction', function () {
    $first = providerFundingEvidence(
        transactionId: 'provider-transaction-query',
        status: 'settled',
        payload: 'settled-v1',
        settledAt: '2026-09-23T03:38:39Z',
    );
    providerFundingEvidence(
        transactionId: 'provider-transaction-query',
        status: 'settled',
        payload: 'settled-v2',
        settledAt: '2026-09-23T03:38:45Z',
    );
    providerFundingEvidence(
        transactionId: 'another-provider-transaction',
        status: 'settled',
        payload: 'unrelated',
        settledAt: '2026-09-23T03:40:00Z',
    );

    $result = app(ReduceProviderFundingTransactionEvidence::class)->forObservation($first);

    expect($result->evidenceCount())->toBe(2)
        ->and($result->settledAt?->toIso8601String())->toBe('2026-09-23T03:38:45+00:00');
});

/**
 * @param  array<string, mixed>  $overrides
 */
function providerFundingEvidence(
    string $transactionId,
    string $status,
    string $payload,
    ?string $settledAt,
    ?string $operationId = 'operation-1',
    array $overrides = [],
): ProviderFundingObservation {
    $attributes = array_replace([
        'observation_key' => hash('sha256', $transactionId.'|'.$payload),
        'provider_code' => 'netbank',
        'provider_transaction_id' => $transactionId,
        'provider_operation_id' => $operationId,
        'request_id' => null,
        'funding_address' => 'sha256:funding-address',
        'provider_account_reference' => 'sha256:provider-account',
        'gross_amount_minor' => 537,
        'fee_amount_minor' => 0,
        'net_amount_minor' => 537,
        'currency' => 'PHP',
        'provider_status' => $status,
        'occurred_at' => CarbonImmutable::parse('2026-09-23T03:38:39Z'),
        'settled_at' => $settledAt === null ? null : CarbonImmutable::parse($settledAt),
        'verification_source' => 'netbank-vca-transaction-history',
        'payload_hash' => hash('sha256', $payload),
        'metadata' => [
            'settlement_rail' => 'INSTAPAY',
            'destination_verified' => true,
            'normalization_version' => 'netbank-standing-credit-v2',
        ],
    ], $overrides);

    return ProviderFundingObservation::query()->create($attributes);
}
