<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Payment\HandleVoucherPaymentWebhook;
use LBHurtado\XChange\Models\VoucherCollection;

it('handles webhook and credits collectible voucher wallet', function () {
    $issuer = actingAsTestUser(1_000_000);

    $wallet = $issuer->wallet;
    $balanceBefore = (float) $wallet->balanceFloat;

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) $issuer->id,
            ],
        ],
    ));

    $result = app(HandleVoucherPaymentWebhook::class)->handle('manual', [
        'voucher_code' => $voucher->code,
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider_reference' => 'REF-WEBHOOK-ACTION-1',
        'provider_transaction_id' => 'TXN-WEBHOOK-ACTION-1',
        'event_id' => 'evt-action-1',
        'payer' => [
            'name' => 'Juan Dela Cruz',
            'mobile' => '09171234567',
        ],
    ]);

    expect($result->status)->toBe('collected')
        ->and($result->voucher_code)->toBe($voucher->code)
        ->and((float) $wallet->fresh()->balanceFloat)->toBe($balanceBefore + 100.00)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1);

    $collection = VoucherCollection::query()
        ->where('voucher_id', $voucher->id)
        ->first();

    expect(data_get($collection->meta, 'payload.meta.source'))->toBe('webhook')
        ->and($collection->idempotency_key)->toBe('evt-action-1');
});

it('replays duplicate webhook without double crediting wallet', function () {
    $issuer = actingAsTestUser(1_000_000);

    $wallet = $issuer->wallet;
    $balanceBefore = (float) $wallet->balanceFloat;

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) $issuer->id,
            ],
        ],
    ));

    $payload = [
        'voucher_code' => $voucher->code,
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider_reference' => 'REF-WEBHOOK-ACTION-2',
        'provider_transaction_id' => 'TXN-WEBHOOK-ACTION-2',
        'event_id' => 'evt-action-2',
    ];

    $first = app(HandleVoucherPaymentWebhook::class)->handle('manual', $payload);
    $second = app(HandleVoucherPaymentWebhook::class)->handle('manual', $payload);

    expect($first->status)->toBe('collected')
        ->and($second->status)->toBe('collected')
        ->and(data_get($second->meta, 'replayed'))->toBeTrue()
        ->and((float) $wallet->fresh()->balanceFloat)->toBe($balanceBefore + 100.00)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1);
});

it('fails when webhook payload has no voucher code', function () {
    app(HandleVoucherPaymentWebhook::class)->handle('manual', [
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
    ]);
})->throws(RuntimeException::class);
