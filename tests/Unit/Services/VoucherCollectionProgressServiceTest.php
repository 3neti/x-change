<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\RecordVoucherCollection;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;

it('computes partial collection progress', function () {
    $voucher = issueCollectibleVoucherForProgressTest(100.00);

    recordVoucherCollectionForProgressTest($voucher, 40.00);

    $progress = app(VoucherCollectionProgressService::class)->compute($voucher);

    expect($progress->targetAmount())->toBe(100.00)
        ->and($progress->collectedTotal())->toBe(40.00)
        ->and($progress->remaining())->toBe(60.00)
        ->and($progress->is_fully_collected)->toBeFalse()
        ->and($progress->is_overpaid)->toBeFalse();
});

it('marks fully collected progress', function () {
    $voucher = issueCollectibleVoucherForProgressTest(100.00);

    recordVoucherCollectionForProgressTest($voucher, 100.00);

    $progress = app(VoucherCollectionProgressService::class)->compute($voucher);

    expect($progress->targetAmount())->toBe(100.00)
        ->and($progress->collectedTotal())->toBe(100.00)
        ->and($progress->remaining())->toBe(0.00)
        ->and($progress->is_fully_collected)->toBeTrue()
        ->and($progress->is_overpaid)->toBeFalse();
});

it('detects overpayment', function () {
    $voucher = issueCollectibleVoucherForProgressTest(100.00);

    recordVoucherCollectionForProgressTest($voucher, 120.00);

    $progress = app(VoucherCollectionProgressService::class)->compute($voucher);

    expect($progress->targetAmount())->toBe(100.00)
        ->and($progress->collectedTotal())->toBe(120.00)
        ->and($progress->remaining())->toBe(0.00)
        ->and($progress->is_fully_collected)->toBeTrue()
        ->and($progress->is_overpaid)->toBeTrue()
        ->and($progress->overpaidAmount())->toBe(20.00);
});

it('ignores failed collection rows when computing progress', function () {
    $voucher = issueCollectibleVoucherForProgressTest(100.00);

    recordVoucherCollectionForProgressTest($voucher, 40.00, status: 'failed');
    recordVoucherCollectionForProgressTest($voucher, 25.00, status: 'collected');

    $progress = app(VoucherCollectionProgressService::class)->compute($voucher);

    expect($progress->collectedTotal())->toBe(25.00)
        ->and($progress->remaining())->toBe(75.00)
        ->and($progress->is_fully_collected)->toBeFalse();
});

it('persists collection progress summary on voucher metadata', function () {
    $voucher = issueCollectibleVoucherForProgressTest(100.00);

    recordVoucherCollectionForProgressTest($voucher, 100.00);

    app(VoucherCollectionProgressService::class)->persistSummary($voucher);

    $voucher = $voucher->fresh();

    expect(data_get($voucher->metadata, 'collection_progress.target_amount_minor'))->toBe(10000)
        ->and(data_get($voucher->metadata, 'collection_progress.collected_total_minor'))->toBe(10000)
        ->and(data_get($voucher->metadata, 'collection_progress.remaining_to_collect_minor'))->toBe(0)
        ->and(data_get($voucher->metadata, 'collection_progress.is_fully_collected'))->toBeTrue()
        ->and(data_get($voucher->metadata, 'collection_progress.is_overpaid'))->toBeFalse();
});

it('optionally persists auto-close metadata when fully collected', function () {
    config()->set('x-change.payment.auto_close_collectible_vouchers', true);

    $voucher = issueCollectibleVoucherForProgressTest(100.00);

    recordVoucherCollectionForProgressTest($voucher, 100.00);

    app(VoucherCollectionProgressService::class)->persistSummary($voucher);

    $voucher = $voucher->fresh();

    expect(data_get($voucher->metadata, 'collection.closed_at'))->not->toBeNull()
        ->and(data_get($voucher->metadata, 'collection.closed_reason'))->toBe('target_amount_collected');
});

function issueCollectibleVoucherForProgressTest(float $targetAmount = 100.00): Voucher
{
    return issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => $targetAmount,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) optional(auth()->user())->id,
            ],
        ],
    ));
}

function recordVoucherCollectionForProgressTest(
    Voucher $voucher,
    float $amount,
    string $status = 'collected',
): void {
    app(RecordVoucherCollection::class)->handle(
        voucher: $voucher,
        result: new VoucherPaymentResultData(
            voucher_code: $voucher->code,
            status: $status,
            amount: $amount,
            currency: 'PHP',
            provider: 'manual',
            provider_reference: 'REF-PROGRESS-'.str()->uuid()->toString(),
            provider_transaction_id: 'TXN-PROGRESS-'.str()->uuid()->toString(),
        ),
        payload: [
            'amount' => $amount,
            'currency' => 'PHP',
            'status' => $status === 'collected' ? 'succeeded' : $status,
            'provider' => 'manual',
        ],
    );
}
