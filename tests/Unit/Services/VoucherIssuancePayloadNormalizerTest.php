<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Enums\VoucherInputField;
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

it('normalizes nested voucher enum values at the issuance boundary', function (): void {
    $normalized = app(VoucherIssuancePayloadNormalizer::class)->normalize([
        'cash' => [
            'amount' => 100,
        ],
        'inputs' => [
            'fields' => [
                VoucherInputField::MOBILE,
                VoucherInputField::EMAIL,
            ],
        ],
    ]);

    expect(data_get($normalized, 'inputs.fields'))->toBe([
        'mobile',
        'email',
    ]);
});

it('compiles onboarding into explicit execution and required claim capabilities', function () {
    config()->set('x-change.onboarding.voucher.require_otp', true);

    $normalized = app(VoucherIssuancePayloadNormalizer::class)->normalize([
        'onboarding' => true,
        'cash' => [
            'amount' => 100,
            'validation' => [],
        ],
        'inputs' => [
            'fields' => ['signature'],
        ],
    ]);

    expect(data_get($normalized, 'inputs.fields'))->toBe([
        'signature',
        'name',
        'email',
        'mobile',
        'otp',
    ])->and(data_get($normalized, 'validation.otp'))->toBe([
        'required' => true,
        'on_failure' => 'block',
    ])->and(data_get($normalized, 'execution.driver'))
        ->toBe('onboarding_account_provisioning')
        ->and(data_get($normalized, 'execution.metadata.onboarding.workflow_key'))
        ->toBe('onboarding.account-provisioning.v1')
        ->and(data_get($normalized, 'execution.metadata.onboarding.mobile_verification_required'))
        ->toBeTrue();
});

it('allows the local onboarding OTP policy to omit OTP capabilities', function () {
    config()->set('x-change.onboarding.voucher.require_otp', false);

    $normalized = app(VoucherIssuancePayloadNormalizer::class)->normalize([
        'onboarding' => true,
        'cash' => ['amount' => 100],
        'inputs' => ['fields' => []],
    ]);

    expect(data_get($normalized, 'inputs.fields'))->toBe([
        'name',
        'email',
        'mobile',
    ])->and(data_get($normalized, 'validation.otp'))->toBeNull()
        ->and(data_get($normalized, 'execution.metadata.onboarding.mobile_verification_required'))
        ->toBeFalse();
});

it('forces OTP for a specifically restricted mobile despite the local bypass', function () {
    config()->set('x-change.onboarding.voucher.require_otp', false);

    $normalized = app(VoucherIssuancePayloadNormalizer::class)->normalize([
        'onboarding' => true,
        'cash' => [
            'amount' => 100,
            'validation' => ['mobile' => '09173011987'],
        ],
        'inputs' => ['fields' => []],
    ]);

    expect(data_get($normalized, 'inputs.fields'))->toContain('otp')
        ->and(data_get($normalized, 'validation.otp.required'))->toBeTrue()
        ->and(data_get($normalized, 'execution.metadata.onboarding.mobile_verification_required'))
        ->toBeTrue();
});

it('maps only required legacy onboarding and rejects incompatible execution', function () {
    $normalizer = app(VoucherIssuancePayloadNormalizer::class);
    $legacy = $normalizer->normalize([
        'cash' => ['amount' => 100],
        'inputs' => ['fields' => []],
        'claim' => [
            'onboarding' => ['mode' => 'required'],
        ],
    ]);
    $conditional = $normalizer->normalize([
        'cash' => ['amount' => 100],
        'inputs' => ['fields' => []],
        'claim' => [
            'onboarding' => ['mode' => 'if_required'],
        ],
    ]);

    expect(data_get($legacy, 'onboarding'))->toBeTrue()
        ->and(data_get($legacy, 'execution.driver'))->toBe('onboarding_account_provisioning')
        ->and(data_get($conditional, 'onboarding'))->toBeFalse()
        ->and(data_get($conditional, 'execution.driver'))->toBeNull()
        ->and(fn () => $normalizer->normalize([
            'onboarding' => true,
            'cash' => ['amount' => 100],
            'inputs' => ['fields' => []],
            'execution' => ['driver' => 'stored-value'],
        ]))->toThrow(
            InvalidArgumentException::class,
            'Onboarding Vouchers cannot use execution driver [stored-value].',
        );
});

it('compiles the typed Reusable Balance policy into execution-only stored value', function (): void {
    $normalizer = app(VoucherIssuancePayloadNormalizer::class);
    $input = [
        'cash' => [
            'amount' => '1000.00',
            'currency' => 'PHP',
        ],
        'stored_value' => [
            'enabled' => true,
            'replenishable' => false,
            'maximum_balance' => '1000.00',
            'otp_required_above' => '500.00',
        ],
    ];

    $normalized = $normalizer->normalize($input);
    $replayed = $normalizer->normalize($normalized);

    expect(data_get($normalized, 'execution'))->toBe([
        'schema' => 'voucher.execution.v1',
        'driver' => 'stored_value',
        'metadata' => [
            'stored_value' => [
                'initial_balance' => 100_000,
                'max_balance' => 100_000,
                'replenishable' => false,
                'otp_required_above' => 50_000,
            ],
            'post_redemption' => [
                'mode' => 'execution_only',
            ],
        ],
    ])->and(data_get($normalized, 'execution.metadata.stored_value.reference'))
        ->toBeNull()
        ->and($replayed)->toBe($normalized);
});

it('rejects stored value policy conflicts and direct generic-driver bypasses', function (): void {
    $normalizer = app(VoucherIssuancePayloadNormalizer::class);

    expect(fn () => $normalizer->normalize([
        'cash' => ['amount' => 100, 'currency' => 'PHP'],
        'execution' => ['driver' => 'stored_value'],
    ]))->toThrow(ValidationException::class)
        ->and(fn () => $normalizer->normalize([
            'cash' => [
                'amount' => 100,
                'currency' => 'PHP',
                'slice_mode' => 'open',
                'max_slices' => 3,
            ],
            'stored_value' => [
                'enabled' => true,
                'replenishable' => false,
                'maximum_balance' => 100,
            ],
        ]))->toThrow(ValidationException::class)
        ->and(fn () => $normalizer->normalize([
            'cash' => ['amount' => 100, 'currency' => 'PHP'],
            'stored_value' => [
                'enabled' => true,
                'replenishable' => true,
                'maximum_balance' => 200,
            ],
        ]))->toThrow(ValidationException::class)
        ->and(fn () => $normalizer->normalize([
            'cash' => ['amount' => 100, 'currency' => 'PHP'],
            'stored_value' => [
                'enabled' => true,
                'replenishable' => false,
                'maximum_balance' => 200,
            ],
        ]))->toThrow(ValidationException::class);
});
