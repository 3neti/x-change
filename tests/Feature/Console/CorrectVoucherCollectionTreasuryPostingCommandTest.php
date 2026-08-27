<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Models\Wallet;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Treasury\TreasuryInventoryRegistrationService;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XChange\Services\Treasury\VoucherCollectionTreasuryCorrectionService;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

beforeEach(function (): void {
    enableNetbankTreasuryForTests();
});

it('dry-runs then corrects one settled collection without changing its wallet transaction', function (): void {
    $fixture = missingVoucherCollectionTreasuryPostingFixture();
    $transaction = $fixture['transaction'];
    $rawTransaction = $transaction->fresh()->getRawOriginal();
    $inventoryOperationsBefore = TreasuryInventoryOperation::query()->count();
    $positionOperationsBefore = TreasuryPositionOperation::query()->count();

    $this->artisan('x-change:treasury:correct-voucher-collection', [
        'code' => $fixture['voucher']->code,
        '--dry-run' => true,
        '--json' => true,
    ])->assertSuccessful();

    expect(TreasuryInventoryOperation::query()->count())->toBe($inventoryOperationsBefore)
        ->and(TreasuryPositionOperation::query()->count())->toBe($positionOperationsBefore)
        ->and((int) $fixture['wallet']->refresh()->balanceInt)->toBe(10_000)
        ->and(treasuryClientFundsLedger($fixture['owner'])->getBalanceIntAttribute())->toBe(0);

    $plan = app(VoucherCollectionTreasuryCorrectionService::class)
        ->inspect((string) $fixture['voucher']->code);
    expect($plan->toArray())->toMatchArray([
        'status' => 'ready',
        'amount_minor' => 10_000,
        'compatibility_balance_minor' => 10_000,
        'client_funds_balance_minor' => 0,
        'divergence_minor' => 10_000,
    ]);

    $this->artisan('x-change:treasury:correct-voucher-collection', [
        'code' => $fixture['voucher']->code,
        '--commit' => true,
        '--json' => true,
    ])->assertSuccessful();
    expect(TreasuryInventoryOperation::query()->pluck('operation_reference')->all())
        ->toContain($plan->operationReferences['inventory_recognition'])
        ->and(TreasuryPositionOperation::query()->pluck('operation_reference')->all())
        ->toContain(
            $plan->operationReferences['position_recognition'],
            $plan->operationReferences['position_allocation'],
        )
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'voucher.collection.treasury_corrected')
            ->count())->toBe(1)
        ->and((int) $fixture['wallet']->refresh()->balanceInt)->toBe(10_000);
    $replayPlan = app(VoucherCollectionTreasuryCorrectionService::class)
        ->inspect((string) $fixture['voucher']->code);
    expect($replayPlan->toArray())->toMatchArray([
        'status' => 'already_corrected',
        'compatibility_balance_minor' => 10_000,
        'client_funds_balance_minor' => 10_000,
        'divergence_minor' => 0,
    ]);
    $this->artisan('x-change:treasury:correct-voucher-collection', [
        'code' => $fixture['voucher']->code,
        '--commit' => true,
        '--json' => true,
    ])->assertSuccessful();

    expect((int) $fixture['wallet']->refresh()->balanceInt)->toBe(10_000)
        ->and(treasuryClientFundsLedger($fixture['owner'])->getBalanceIntAttribute())->toBe(10_000)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(10_000)
        ->and(TreasuryInventoryOperation::query()->count())->toBe($inventoryOperationsBefore + 1)
        ->and(TreasuryPositionOperation::query()->count())->toBe($positionOperationsBefore + 2)
        ->and($transaction->fresh()->getRawOriginal())->toBe($rawTransaction)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'voucher.collection.treasury_corrected')
            ->count())->toBe(1);
});

/**
 * @return array{owner: User, wallet: Wallet, voucher: Voucher, transaction: Transaction}
 */
function missingVoucherCollectionTreasuryPostingFixture(): array
{
    $owner = actingAsTestUser(0);
    $wallet = $owner->wallet()->where('slug', 'platform')->sole();
    app(TreasuryProvisioningService::class)->provision(['netbank-primary']);
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $owner,
        ['netbank-primary'],
    );
    app(TreasuryInventoryRegistrationService::class)->ensure(new TreasuryInventoryData(
        inventoryReference: 'inventory:netbank:vca-cash',
        resourceType: 'cash_at_bank',
        currency: 'PHP',
        capacityMinor: 0,
        status: 'requested',
        idempotencyKey: 'register:inventory:netbank:vca-cash',
        externalReference: 'resource:netbank:corporate-vca',
        metadata: ['provider' => 'netbank'],
    ));
    $voucher = GenerateVouchers::run(validVoucherInstructions(0, 'INSTAPAY', [
        'voucher_type' => 'payable',
        'target_amount' => 100,
        'metadata' => [
            'issuer_id' => (string) $owner->getKey(),
            'collection_wallet_id' => (string) $wallet->getKey(),
        ],
    ]))->sole();
    $transaction = $wallet->deposit(10_000, [
        'reason' => 'voucher_collection',
        'voucher_code' => $voucher->code,
        'provider' => 'netbank',
        'provider_reference' => 'RHHF-ATTEMPT',
        'provider_transaction_id' => 'RHHF-TRANSACTION',
    ], true);
    $collection = VoucherCollection::query()->create([
        'voucher_id' => $voucher->getKey(),
        'collection_number' => 1,
        'status' => 'collected',
        'requested_amount_minor' => 10_000,
        'collected_amount_minor' => 10_000,
        'currency' => 'PHP',
        'provider' => 'netbank',
        'provider_reference' => 'RHHF-ATTEMPT',
        'provider_transaction_id' => 'RHHF-TRANSACTION',
        'wallet_transaction_id' => $transaction->getKey(),
        'idempotency_key' => 'payment-attempt:RHHF-ATTEMPT',
        'idempotency_fingerprint' => hash('sha256', 'RHHF-COLLECTION'),
        'execution_driver' => 'provider_wallet',
        'attempted_at' => now()->subMinute(),
        'completed_at' => now(),
        'meta' => [],
    ]);
    $observation = ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', 'RHHF-TRANSACTION'),
        'provider_code' => 'netbank',
        'provider_transaction_id' => 'RHHF-TRANSACTION',
        'provider_operation_id' => 'OP-RHHF-TRANSACTION',
        'request_id' => 'REQ-RHHF-TRANSACTION',
        'funding_address' => '001234567890',
        'provider_account_reference' => 'corporate-vca',
        'gross_amount_minor' => 10_000,
        'fee_amount_minor' => 0,
        'net_amount_minor' => 10_000,
        'currency' => 'PHP',
        'provider_status' => 'settled',
        'occurred_at' => now()->subMinute(),
        'settled_at' => now(),
        'verification_source' => 'transaction_history',
        'payload_hash' => hash('sha256', 'RHHF-PAYLOAD'),
        'metadata' => ['destination_verified' => true],
    ]);
    PaymentAttempt::query()->create([
        'reference' => '01RHHFATTEMPT000000000000',
        'voucher_id' => $voucher->getKey(),
        'provider_code' => 'netbank',
        'expected_amount_minor' => 10_000,
        'currency' => 'PHP',
        'status' => PaymentAttemptStatus::Settled,
        'version' => 4,
        'session_key_hash' => hash('sha256', 'RHHF-SESSION'),
        'idempotency_key_hash' => hash('sha256', 'RHHF-IDEMPOTENCY'),
        'idempotency_fingerprint' => hash('sha256', 'RHHF-FINGERPRINT'),
        'matched_observation_id' => $observation->getKey(),
        'voucher_collection_id' => $collection->getKey(),
        'provider_transaction_id' => 'RHHF-TRANSACTION',
        'verified_at' => now()->subMinute(),
        'settled_at' => now(),
        'expires_at' => now()->addHour(),
    ]);

    return compact('owner', 'wallet', 'voucher', 'transaction');
}
