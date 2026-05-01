<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Payment\CollectVoucherFunds;
use LBHurtado\XChange\Exceptions\VoucherCollectionConflict;
use LBHurtado\XChange\Models\VoucherCollection;

it('replays same idempotency key and same payload without double crediting wallet', function () {
    $user = actingAsTestUser();
    $wallet = $user->wallet;
    $balanceBefore = (float) $wallet->balanceFloat;

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
            ],
        ],
    ));

    $payload = [
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider' => 'manual',
        'provider_reference' => 'REF-IDEM-1',
        'provider_transaction_id' => 'TXN-IDEM-1',
        'idempotency_key' => 'idem-collection-1',
        'payer' => [
            'name' => 'Juan Dela Cruz',
            'mobile' => '09171234567',
        ],
    ];

    $first = app(CollectVoucherFunds::class)->handle($voucher, $payload);
    $second = app(CollectVoucherFunds::class)->handle($voucher, $payload);

    expect($first->status)->toBe('collected')
        ->and($second->status)->toBe('collected')
        ->and(data_get($second->meta, 'replayed'))->toBeTrue()
        ->and((float) $wallet->fresh()->balanceFloat)->toBe($balanceBefore + 100.00)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1);
});

it('throws conflict when same idempotency key is reused with different payload', function () {
    $user = actingAsTestUser();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
            ],
        ],
    ));

    app(CollectVoucherFunds::class)->handle($voucher, [
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider' => 'manual',
        'provider_reference' => 'REF-IDEM-2',
        'provider_transaction_id' => 'TXN-IDEM-2',
        'idempotency_key' => 'idem-collection-2',
    ]);

    app(CollectVoucherFunds::class)->handle($voucher, [
        'amount' => 99.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider' => 'manual',
        'provider_reference' => 'REF-IDEM-2',
        'provider_transaction_id' => 'TXN-IDEM-2',
        'idempotency_key' => 'idem-collection-2',
    ]);
})->throws(VoucherCollectionConflict::class);

it('replays same provider reference without double crediting wallet', function () {
    $user = actingAsTestUser();
    $wallet = $user->wallet;
    $balanceBefore = (float) $wallet->balanceFloat;

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
            ],
        ],
    ));

    $payload = [
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider' => 'manual',
        'provider_reference' => 'REF-PROVIDER-1',
        'provider_transaction_id' => 'TXN-PROVIDER-1',
    ];

    $first = app(CollectVoucherFunds::class)->handle($voucher, $payload);
    $second = app(CollectVoucherFunds::class)->handle($voucher, $payload);

    expect($first->status)->toBe('collected')
        ->and($second->status)->toBe('collected')
        ->and(data_get($second->meta, 'replayed'))->toBeTrue()
        ->and((float) $wallet->fresh()->balanceFloat)->toBe($balanceBefore + 100.00)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1);
});

it('throws conflict when provider reference is reused with different payload', function () {
    $user = actingAsTestUser();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
            ],
        ],
    ));

    app(CollectVoucherFunds::class)->handle($voucher, [
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider' => 'manual',
        'provider_reference' => 'REF-PROVIDER-2',
        'provider_transaction_id' => 'TXN-PROVIDER-2',
    ]);

    app(CollectVoucherFunds::class)->handle($voucher, [
        'amount' => 120.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider' => 'manual',
        'provider_reference' => 'REF-PROVIDER-2',
        'provider_transaction_id' => 'TXN-PROVIDER-2',
    ]);
})->throws(VoucherCollectionConflict::class);
