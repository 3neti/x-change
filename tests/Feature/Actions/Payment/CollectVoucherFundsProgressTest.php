<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\CollectVoucherFunds;
use LBHurtado\XChange\Models\VoucherCollection;

it('updates progress after successful collection', function () {
    actingAsTestUser(1_000_000);

    $voucher = issueCollectibleVoucherForFundsProgressTest(100.00);

    collectVoucherFundsForProgressTest($voucher, 40.00, 'REF-FUNDS-PROGRESS-1');

    $voucher = $voucher->fresh();

    expect(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1)
        ->and(data_get($voucher->metadata, 'collection_progress.target_amount_minor'))->toBe(10000)
        ->and(data_get($voucher->metadata, 'collection_progress.collected_total_minor'))->toBe(4000)
        ->and(data_get($voucher->metadata, 'collection_progress.remaining_to_collect_minor'))->toBe(6000)
        ->and(data_get($voucher->metadata, 'collection_progress.is_fully_collected'))->toBeFalse()
        ->and(data_get($voucher->metadata, 'collection_progress.is_overpaid'))->toBeFalse();
});

it('accumulates progress across multiple successful collections', function () {
    actingAsTestUser(1_000_000);

    $voucher = issueCollectibleVoucherForFundsProgressTest(100.00);

    collectVoucherFundsForProgressTest($voucher, 40.00, 'REF-FUNDS-PROGRESS-2A');
    collectVoucherFundsForProgressTest($voucher, 25.00, 'REF-FUNDS-PROGRESS-2B');

    $voucher = $voucher->fresh();

    expect(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(2)
        ->and(data_get($voucher->metadata, 'collection_progress.collected_total_minor'))->toBe(6500)
        ->and(data_get($voucher->metadata, 'collection_progress.remaining_to_collect_minor'))->toBe(3500)
        ->and(data_get($voucher->metadata, 'collection_progress.is_fully_collected'))->toBeFalse();
});

it('marks progress as fully collected when target amount is reached', function () {
    actingAsTestUser(1_000_000);

    $voucher = issueCollectibleVoucherForFundsProgressTest(100.00);

    collectVoucherFundsForProgressTest($voucher, 100.00, 'REF-FUNDS-PROGRESS-3');

    $voucher = $voucher->fresh();

    expect(data_get($voucher->metadata, 'collection_progress.collected_total_minor'))->toBe(10000)
        ->and(data_get($voucher->metadata, 'collection_progress.remaining_to_collect_minor'))->toBe(0)
        ->and(data_get($voucher->metadata, 'collection_progress.is_fully_collected'))->toBeTrue()
        ->and(data_get($voucher->metadata, 'collection_progress.is_overpaid'))->toBeFalse();
});

it('records overpayment progress when collected amount exceeds target amount', function () {
    actingAsTestUser(1_000_000);

    $voucher = issueCollectibleVoucherForFundsProgressTest(100.00);

    collectVoucherFundsForProgressTest($voucher, 120.00, 'REF-FUNDS-PROGRESS-4');

    $voucher = $voucher->fresh();

    expect(data_get($voucher->metadata, 'collection_progress.collected_total_minor'))->toBe(12000)
        ->and(data_get($voucher->metadata, 'collection_progress.remaining_to_collect_minor'))->toBe(0)
        ->and(data_get($voucher->metadata, 'collection_progress.is_fully_collected'))->toBeTrue()
        ->and(data_get($voucher->metadata, 'collection_progress.is_overpaid'))->toBeTrue()
        ->and(data_get($voucher->metadata, 'collection_progress.overpaid_amount_minor'))->toBe(2000);
});

it('does not update progress after failed collection confirmation', function () {
    actingAsTestUser(1_000_000);

    $voucher = issueCollectibleVoucherForFundsProgressTest(100.00);

    app(CollectVoucherFunds::class)->handle($voucher, [
        'amount' => 40.00,
        'currency' => 'PHP',
        'status' => 'failed',
        'provider' => 'manual',
        'provider_reference' => 'REF-FUNDS-PROGRESS-FAILED',
        'provider_transaction_id' => 'TXN-FUNDS-PROGRESS-FAILED',
    ]);

    $voucher = $voucher->fresh();

    expect(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1)
        ->and(data_get($voucher->metadata, 'collection_progress'))->toBeNull();
});

it('optionally auto-closes collectible voucher when target amount is reached', function () {
    config()->set('x-change.payment.auto_close_collectible_vouchers', true);

    actingAsTestUser(1_000_000);

    $voucher = issueCollectibleVoucherForFundsProgressTest(100.00);

    collectVoucherFundsForProgressTest($voucher, 100.00, 'REF-FUNDS-PROGRESS-5');

    $voucher = $voucher->fresh();

    expect(data_get($voucher->metadata, 'collection_progress.is_fully_collected'))->toBeTrue()
        ->and(data_get($voucher->metadata, 'collection.closed_at'))->not->toBeNull()
        ->and(data_get($voucher->metadata, 'collection.closed_reason'))->toBe('target_amount_collected');
});

function issueCollectibleVoucherForFundsProgressTest(float $targetAmount = 100.00): Voucher
{
    $user = actingAsTestUser(1_000_000);

    return issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => $targetAmount,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) $user->id,
                'collection_wallet_id' => $user->wallet->id,

            ],
        ],
    ));
}

function collectVoucherFundsForProgressTest(
    Voucher $voucher,
    float $amount,
    string $providerReference,
): void {
    app(CollectVoucherFunds::class)->handle($voucher, [
        'amount' => $amount,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider' => 'manual',
        'provider_reference' => $providerReference,
        'provider_transaction_id' => str_replace('REF-', 'TXN-', $providerReference),
    ]);
}
