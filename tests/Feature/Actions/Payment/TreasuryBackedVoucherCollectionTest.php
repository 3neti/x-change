<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Actions\Payment\CollectVoucherFunds;
use LBHurtado\XChange\Actions\Payment\CompleteVoucherCollection;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Data\Payment\ConfirmedVoucherCollectionData;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\PosSaleReference;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Execution\ValueFlowPayoutService;
use LBHurtado\XChange\Services\Treasury\TreasuryCompatibilityLedgerSynchronizer;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

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
    $journal = ExecutionJournalEntry::query()
        ->where('event_type', 'voucher.collection.completed')
        ->sole();

    expect(data_get($collection->meta, 'posting.sale_reference'))->toBe('POS-20260828-01HYYYYYYYYYYYYYYYYYYYYYYY')
        ->and(data_get($journal->references, 'metadata.sale_reference'))->toBe('POS-20260828-01HYYYYYYYYYYYYYYYYYYYYYYY')
        ->and(data_get($journal->payload, 'sale_reference'))->toBe('POS-20260828-01HYYYYYYYYYYYYYYYYYYYYYYY')
        ->and(json_encode($journal->toArray()))->not->toContain('09173011987');

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

it('uses the collection wallet uuid as the Treasury account reference', function (): void {
    $issuer = actingAsTestUser(0);
    $wallet = $issuer->wallet()->where('slug', 'platform')->sole();
    $accounts = Mockery::mock(FundingAccountCreditContract::class);
    $accounts->shouldReceive('resolve')
        ->once()
        ->with('wallet:'.$wallet->uuid)
        ->andReturn($wallet);
    $this->app->instance(FundingAccountCreditContract::class, $accounts);
    $voucher = treasuryBackedCollectionVoucher($issuer);

    app(CollectVoucherFunds::class)->collectConfirmed(
        $voucher,
        treasuryBackedCollectionResult($voucher),
        treasuryBackedCollectionPayload(),
    );

    expect(VoucherCollection::query()->count())->toBe(1);
});

it('credits the instructed Client Funds destination and leaves the issuer unchanged', function (): void {
    $issuer = actingAsTestUser(0);
    fundTestUserClientFunds($issuer, 100);
    $collector = User::query()->create([
        'name' => 'Collector',
        'email' => 'collector@example.test',
        'password' => 'not-a-login-credential',
    ]);
    $collector->forceFill(['mobile' => '09173011987'])->save();
    fundTestUserWallet($collector, 0);
    fundTestUserClientFunds($collector, 100);
    $voucher = treasuryBackedCollectionVoucher(
        $issuer,
        'account.mobile:09173011987.client_funds',
    );

    app(CollectVoucherFunds::class)->collectConfirmed(
        $voucher,
        treasuryBackedCollectionResult($voucher),
        treasuryBackedCollectionPayload(),
    );

    expect(treasuryClientFundsLedger($collector)->balanceInt)->toBe(10_100)
        ->and(treasuryClientFundsLedger($issuer)->balanceInt)->toBe(100)
        ->and(VoucherCollection::query()->sole()->treasury_operation_reference)->not->toBeNull();
});

it('routes an instructed payable collection through the voucher execution engine', function (): void {
    $issuer = actingAsTestUser(0);
    fundTestUserClientFunds($issuer, 100);
    $collector = User::query()->create([
        'name' => 'Collector',
        'email' => 'engine-collector@example.test',
        'password' => 'not-a-login-credential',
    ]);
    $collector->forceFill(['mobile' => '09173011987'])->save();
    fundTestUserWallet($collector, 0);
    fundTestUserClientFunds($collector, 100);
    $voucher = treasuryBackedCollectionVoucher(
        $issuer,
        'account.mobile:09173011987.client_funds',
    );
    $context = ExecutionContextData::fromRedemption(
        $voucher,
        new Contact,
        $voucher->code,
        [
            'operation' => 'collect',
            'amount_minor' => 10_000,
            'currency' => 'PHP',
            'provider' => 'netbank',
            'provider_reference' => 'NETBANK-ATTEMPT-ENGINE-1',
            'provider_transaction_id' => 'NETBANK-TRANSACTION-ENGINE-1',
            'idempotency_key' => 'value-flow-engine-1',
        ],
        ['execution_id' => 'value-flow-engine-1'],
    );

    $result = app(ExecutionEngine::class)->execute($context);

    expect($result->successful)->toBeTrue()
        ->and($result->driver)->toBe('payable_collection')
        ->and(treasuryClientFundsLedger($collector)->balanceInt)->toBe(10_100)
        ->and(treasuryClientFundsLedger($issuer)->balanceInt)->toBe(100);
});

it('keeps the issuer destination default when the payable execution instruction omits value flow', function (): void {
    $issuer = actingAsTestUser(0);
    fundTestUserClientFunds($issuer, 100);
    $voucher = treasuryBackedCollectionVoucher($issuer);
    $metadata = (array) $voucher->metadata;
    data_set($metadata, 'instructions.execution.driver', 'payable_collection');
    $voucher->forceFill(['metadata' => $metadata])->saveQuietly();
    $context = ExecutionContextData::fromRedemption(
        $voucher,
        new Contact,
        $voucher->code,
        [
            'operation' => 'collect',
            'amount_minor' => 10_000,
            'currency' => 'PHP',
            'provider' => 'netbank',
            'provider_reference' => 'NETBANK-ATTEMPT-DEFAULT-1',
            'provider_transaction_id' => 'NETBANK-TRANSACTION-DEFAULT-1',
            'idempotency_key' => 'value-flow-default-1',
        ],
        ['execution_id' => 'value-flow-default-1'],
    );

    $result = app(ExecutionEngine::class)->execute($context);

    expect($result->successful)->toBeTrue()
        ->and(treasuryClientFundsLedger($issuer)->balanceInt)->toBe(10_100);
});

it('does not collect from an explicit payer address without provider account evidence', function (): void {
    $issuer = actingAsTestUser(0);
    $voucher = treasuryBackedCollectionVoucher($issuer);
    $metadata = (array) $voucher->metadata;
    data_set(
        $metadata,
        'instructions.execution.metadata.value_flow.source',
        'instapay.gcash.mobile:09179999999',
    );
    $voucher->forceFill(['metadata' => $metadata])->saveQuietly();

    expect(fn () => app(CollectVoucherFunds::class)->collectConfirmed(
        $voucher,
        treasuryBackedCollectionResult($voucher),
        treasuryBackedCollectionPayload(),
    ))->toThrow(RuntimeException::class, 'provider-verified payer account evidence');

    expect(VoucherCollection::query()->count())->toBe(0);
});

it('rejects explicit routing on a collection driver that cannot interpret it', function (): void {
    $issuer = actingAsTestUser(0);
    $voucher = treasuryBackedCollectionVoucher(
        $issuer,
        'account.mobile:09173011987.client_funds',
    );

    expect(fn () => app(CompleteVoucherCollection::class)->handle(
        $voucher,
        new ConfirmedVoucherCollectionData(
            amountMinor: 10_000,
            currency: 'PHP',
            executionDriver: 'x_change_account_funding',
            authority: 'authoritative_provider',
            authorityReference: 'provider-payment-1',
            idempotencyKey: 'unsupported-value-flow-driver-1',
        ),
    ))->toThrow(RuntimeException::class, 'does not support explicit value flow addresses');

    expect(VoucherCollection::query()->count())->toBe(0);
});

/**
 * @param  User  $issuer
 */
function treasuryBackedCollectionVoucher($issuer, ?string $destinationAddress = null): Voucher
{
    $voucher = GenerateVouchers::run(validVoucherInstructions(
        amount: 0,
        overrides: [
            'voucher_type' => 'payable',
            'target_amount' => 100,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) $issuer->getKey(),
                'collection_wallet_id' => (string) $issuer->wallet()->where('slug', 'platform')->sole()->getKey(),
            ],
            ...($destinationAddress === null ? [] : [
                'execution' => [
                    'driver' => 'payable_collection',
                    'metadata' => [
                        'value_flow' => ['destination' => $destinationAddress],
                    ],
                ],
            ]),
        ],
    ))->sole();

    PosSaleReference::query()->create([
        'voucher_id' => $voucher->getKey(),
        'sale_reference' => 'POS-20260828-01HYYYYYYYYYYYYYYYYYYYYYYY',
        'order_reference' => 'ORDER-42',
        'purpose' => 'Snacks',
        'operator_type' => $issuer->getMorphClass(),
        'operator_id' => $issuer->getKey(),
    ]);

    return $voucher;
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

it('reserves a verified payable collection for its fixed external destination exactly once', function (): void {
    Queue::fake();
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    config()->set('omnipay.gateways.netbank.options.rails.INSTAPAY', ['enabled' => true, 'min_amount' => 100, 'max_amount' => 5_000_000]);
    $issuer = actingAsTestUser(0);
    $voucher = treasuryBackedCollectionVoucher($issuer, 'instapay.gcash.mobile:09179999999');
    $result = treasuryBackedCollectionResult($voucher);
    $payload = treasuryBackedCollectionPayload();
    app(CollectVoucherFunds::class)->collectConfirmed($voucher, $result, $payload);
    app(CollectVoucherFunds::class)->collectConfirmed($voucher, $result, $payload);
    $payout = DisbursementReconciliation::query()->sole();
    $collection = VoucherCollection::query()->sole();
    expect($payout->claim_type)->toBe('value_flow')
        ->and($payout->status)->toBe('intent')
        ->and((float) $payout->amount)->toBe(100.0)
        ->and(data_get($collection->meta, 'posting.payout_reference'))->toBe($payout->provider_reference)
        ->and(treasuryClientFundsLedger($issuer)->balanceInt)->toBe(0)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(10_000);
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(provider: 'netbank');
    app(ValueFlowPayoutService::class)->submit($payout->id);
    app(ValueFlowPayoutService::class)->submit($payout->id);
    expect($provider->lastRequest?->account_number)->toBe('09179999999')
        ->and($provider->disburseCallCount)->toBe(1)
        ->and($payout->fresh()->internal_status)->toBe('finalized')
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(0);
});
