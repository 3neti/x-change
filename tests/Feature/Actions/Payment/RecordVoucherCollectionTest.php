<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Payment\RecordVoucherCollection;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Models\VoucherCollection;

it('records collection row after successful payment confirmation', function () {
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

    $collection = app(RecordVoucherCollection::class)->handle(
        voucher: $voucher,
        result: new VoucherPaymentResultData(
            voucher_code: $voucher->code,
            status: 'collected',
            amount: 100.00,
            currency: 'PHP',
            provider: 'manual',
            provider_reference: 'REF-123',
            provider_transaction_id: 'TXN-123',
            payer: [
                'name' => 'Juan Dela Cruz',
                'mobile' => '09171234567',
            ],
        ),
        payload: [
            'amount' => 100.00,
            'idempotency_key' => 'idem-123',
        ],
    );

    expect($collection)->toBeInstanceOf(VoucherCollection::class)
        ->and($collection->voucher_id)->toBe($voucher->id)
        ->and($collection->collection_number)->toBe(1)
        ->and($collection->status)->toBe('collected')
        ->and($collection->requested_amount_minor)->toBe(10000)
        ->and($collection->collected_amount_minor)->toBe(10000)
        ->and($collection->provider_reference)->toBe('REF-123')
        ->and($collection->provider_transaction_id)->toBe('TXN-123')
        ->and($collection->payer_mobile)->toBe('09171234567')
        ->and($collection->payer_name)->toBe('Juan Dela Cruz')
        ->and($collection->idempotency_key)->toBe('idem-123')
        ->and($collection->isSucceeded())->toBeTrue();
});

it('records failed collection attempt', function () {
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

    $collection = app(RecordVoucherCollection::class)->handle(
        voucher: $voucher,
        result: new VoucherPaymentResultData(
            voucher_code: $voucher->code,
            status: 'failed',
            amount: 100.00,
            currency: 'PHP',
            provider: 'manual',
            provider_reference: 'REF-FAILED',
            provider_transaction_id: 'TXN-FAILED',
            messages: ['Payment failed.'],
        ),
        payload: [
            'amount' => 100.00,
        ],
    );

    expect($collection->status)->toBe('failed')
        ->and($collection->requested_amount_minor)->toBe(10000)
        ->and($collection->collected_amount_minor)->toBe(0)
        ->and($collection->completed_at)->toBeNull()
        ->and($collection->failure_message)->toBe('Payment failed.')
        ->and($collection->isFailed())->toBeTrue();
});

it('increments collection number per voucher', function () {
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

    $action = app(RecordVoucherCollection::class);

    $first = $action->handle(
        $voucher,
        new VoucherPaymentResultData(
            voucher_code: $voucher->code,
            status: 'collected',
            amount: 50.00,
        ),
        ['amount' => 50.00],
    );

    $second = $action->handle(
        $voucher,
        new VoucherPaymentResultData(
            voucher_code: $voucher->code,
            status: 'collected',
            amount: 25.00,
        ),
        ['amount' => 25.00],
    );

    expect($first->collection_number)->toBe(1)
        ->and($second->collection_number)->toBe(2);
});

it('exposes amount helpers', function () {
    $collection = new VoucherCollection([
        'requested_amount_minor' => 12345,
        'collected_amount_minor' => 10000,
    ]);

    expect($collection->requestedAmount())->toBe(123.45)
        ->and($collection->collectedAmount())->toBe(100.00);
});
