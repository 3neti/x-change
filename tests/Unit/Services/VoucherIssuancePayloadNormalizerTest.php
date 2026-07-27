<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\VoucherIssuancePayloadNormalizer;

it('moves collectible cash amount into target amount and zeroes cash amount', function () {
    $input = [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
        ],
        'metadata' => [
            'flow_type' => 'collectible',
        ],
    ];

    $normalized = app(VoucherIssuancePayloadNormalizer::class)->normalize($input);

    expect(data_get($normalized, 'cash.amount'))->toBe(0)
        ->and(data_get($normalized, 'target_amount'))->toBe(100);
});

it('does not mutate disbursable cash amount', function () {
    $input = [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
        ],
        'metadata' => [
            'flow_type' => 'disbursable',
        ],
    ];

    $normalized = app(VoucherIssuancePayloadNormalizer::class)->normalize($input);

    expect(data_get($normalized, 'cash.amount'))->toBe(100)
        ->and(data_get($normalized, 'target_amount'))->toBeNull()
        ->and(data_get($normalized, 'cash.validation'))->toBe([]);
});

it('preserves configured cash validation while normalizing legacy empty values', function () {
    $normalizer = app(VoucherIssuancePayloadNormalizer::class);

    $configured = $normalizer->normalize([
        'cash' => [
            'amount' => 100,
            'validation' => ['country' => 'PH'],
        ],
    ]);
    $legacy = $normalizer->normalize([
        'cash' => [
            'amount' => 100,
            'validation' => null,
        ],
    ]);

    expect(data_get($configured, 'cash.validation'))->toBe(['country' => 'PH'])
        ->and(data_get($legacy, 'cash.validation'))->toBe([]);
});
