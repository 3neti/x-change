<?php

declare(strict_types=1);

use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Actions\Payment\CollectVoucherFunds;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Treasury\TreasuryCompatibilityLedgerSynchronizer;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function (): void {
    config()->set('x-change.onboarding.issuer_model', User::class);
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
});

it('recognizes a Treasury-backed collection without directly crediting its compatibility wallet', function (): void {
    $issuer = actingAsTestUser(0);
    $wallet = $issuer->wallet()->where('slug', 'platform')->sole();
    $voucher = treasuryBackedCollectionVoucher($issuer);
    $result = treasuryBackedCollectionResult($voucher);
    $payload = treasuryBackedCollectionPayload();

    $first = app(CollectVoucherFunds::class)->collectConfirmed(
        $voucher,
        $result,
        $payload,
    );
    $second = app(CollectVoucherFunds::class)->collectConfirmed(
        $voucher,
        $result,
        $payload,
    );
    $collection = VoucherCollection::query()->sole();

    expect($second->meta['replayed'] ?? false)->toBeTrue()
        ->and($second->meta['collection_id'] ?? null)->toBe($first->meta['collection_id'] ?? null)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(treasuryClientFundsLedger($issuer)->getBalanceIntAttribute())->toBe(10_000)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(10_000)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1)
        ->and(TreasuryPositionOperation::query()->count())->toBe(2)
        ->and($collection->wallet_transaction_id)->toBeNull()
        ->and($collection->treasury_operation_reference)->not->toBeNull()
        ->and(data_get($collection->meta, 'posting.provider_inventory_changed'))->toBeTrue()
        ->and(data_get($collection->meta, 'posting.treasury_position_allocation_reference'))->not->toBeNull();

    app(TreasuryCompatibilityLedgerSynchronizer::class)->synchronize(
        $issuer,
        $wallet,
        FundingDecisionData::allowed(
            authority: 'local_ledger',
            availableMinor: 10_000,
            requiredMinor: 1,
            currency: 'PHP',
            meta: [
                'provider' => 'netbank',
                'topology' => 'ledger_pooled',
            ],
        ),
    );

    expect((int) $wallet->refresh()->balanceInt)->toBe(10_000)
        ->and(treasuryClientFundsLedger($issuer)->getBalanceIntAttribute())->toBe(10_000)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(10_000);
});

/**
 * @param  User  $issuer
 */
function treasuryBackedCollectionVoucher($issuer): Voucher
{
    return GenerateVouchers::run(validVoucherInstructions(
        amount: 0,
        overrides: [
            'voucher_type' => 'payable',
            'target_amount' => 100,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) $issuer->getKey(),
                'collection_wallet_id' => (string) $issuer->wallet()->where('slug', 'platform')->sole()->getKey(),
            ],
        ],
    ))->sole();
}

function treasuryBackedCollectionResult(Voucher $voucher): VoucherPaymentResultData
{
    return new VoucherPaymentResultData(
        voucher_code: (string) $voucher->code,
        status: 'succeeded',
        amount: 100,
        currency: 'PHP',
        provider: 'netbank',
        provider_reference: 'NETBANK-ATTEMPT-COLLECTION-1',
        provider_transaction_id: 'NETBANK-TRANSACTION-COLLECTION-1',
    );
}

/**
 * @return array<string, mixed>
 */
function treasuryBackedCollectionPayload(): array
{
    return [
        'amount' => 100,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider' => 'netbank',
        'provider_reference' => 'NETBANK-ATTEMPT-COLLECTION-1',
        'provider_transaction_id' => 'NETBANK-TRANSACTION-COLLECTION-1',
        'idempotency_key' => 'treasury-backed-collection-1',
    ];
}
