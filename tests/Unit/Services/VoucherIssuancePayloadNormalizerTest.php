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
        ->and(data_get($normalized, 'target_amount'))->toBeNull();
});
