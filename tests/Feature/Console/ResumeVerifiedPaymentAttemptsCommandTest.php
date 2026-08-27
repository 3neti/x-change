<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryInventoryOperationType;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Actions\Payment\VerifyPaymentAttempt;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Models\ExternalJobFailure;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\Payment\VerifiedPaymentSettlementRecoveryService;
use LBHurtado\XChange\Services\Treasury\TreasuryInventoryRegistrationService;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function (): void {
    config()->set('x-change.onboarding.issuer_model', User::class);
    enableNetbankTreasuryForTests();
    $this->settlementRecoveryAdapter = new FakeFundingProviderAdapter;
    $this->app->instance(FakeFundingProviderAdapter::class, $this->settlementRecoveryAdapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
});

it('dry-runs and commits exact verified attempts without provider calls', function (): void {
    $first = verifiedSettlementRecoveryFixture(10_000);
    $second = verifiedSettlementRecoveryFixture(5_000);
    $references = [$first['attempt']->reference, $second['attempt']->reference];

    $this->artisan('x-change:payments:resume-verified', [
        '--attempt' => $references,
        '--dry-run' => true,
        '--json' => true,
    ])->assertSuccessful();

    expect(VoucherCollection::query()->count())->toBe(0)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(0)
        ->and(TreasuryPositionOperation::query()->count())->toBe(0)
        ->and($this->settlementRecoveryAdapter->lastVerification)->toBeNull();

    $this->artisan('x-change:payments:resume-verified', [
        '--attempt' => $references,
        '--commit' => true,
        '--json' => true,
    ])->assertSuccessful();

    expect(PaymentAttempt::query()->whereIn('reference', $references)
        ->where('status', PaymentAttemptStatus::Settled)->count())->toBe(2)
        ->and(VoucherCollection::query()->count())->toBe(2)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(15_000)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(2)
        ->and(TreasuryPositionOperation::query()->count())->toBe(4)
        ->and(treasuryClientFundsLedger($first['owner'])->getBalanceIntAttribute())->toBe(10_000)
        ->and(treasuryClientFundsLedger($second['owner'])->getBalanceIntAttribute())->toBe(5_000)
        ->and((int) $first['wallet']->refresh()->balanceInt)->toBe(0)
        ->and((int) $second['wallet']->refresh()->balanceInt)->toBe(0)
        ->and($this->settlementRecoveryAdapter->lastVerification)->toBeNull();

    $this->artisan('x-change:payments:resume-verified', [
        '--attempt' => $references,
        '--commit' => true,
        '--json' => true,
    ])->assertSuccessful();

    expect(VoucherCollection::query()->count())->toBe(2)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(2)
        ->and(TreasuryPositionOperation::query()->count())->toBe(4);
});

it('settles an already verified attempt before applying expiry logic', function (): void {
    $fixture = verifiedSettlementRecoveryFixture(10_000, now()->subHour());

    $settled = app(VerifyPaymentAttempt::class)->handle(
        $fixture['attempt'],
        PaymentVerificationTrigger::Schedule,
    );

    expect($settled->status)->toBe(PaymentAttemptStatus::Settled)
        ->and($settled->expired_at)->toBeNull()
        ->and($this->settlementRecoveryAdapter->lastVerification)->toBeNull();
});

it('rejects recovery when exact authoritative observation evidence is missing', function (): void {
    $fixture = verifiedSettlementRecoveryFixture(10_000);
    DB::table('x_change_payment_attempts')
        ->where('id', $fixture['attempt']->getKey())
        ->update(['matched_observation_id' => null]);

    $this->artisan('x-change:payments:resume-verified', [
        '--attempt' => [$fixture['attempt']->reference],
        '--commit' => true,
        '--json' => true,
    ])->assertFailed();

    expect(PaymentAttempt::query()->findOrFail($fixture['attempt']->getKey())->status)
        ->toBe(PaymentAttemptStatus::Verified)
        ->and(VoucherCollection::query()->count())->toBe(0)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(0)
        ->and(TreasuryPositionOperation::query()->count())->toBe(0);
});

it('records sanitized durable evidence when verified settlement fails immediately', function (): void {
    $fixture = verifiedSettlementRecoveryFixture(10_000);
    $allocation = Mockery::mock(VerifiedTreasuryFundingAllocationContract::class);
    $allocation->shouldReceive('allocate')
        ->once()
        ->andThrow(new RuntimeException('sensitive provider response'));
    $this->app->instance(VerifiedTreasuryFundingAllocationContract::class, $allocation);

    expect(fn () => app(VerifyPaymentAttempt::class)->handle(
        $fixture['attempt'],
        PaymentVerificationTrigger::Payer,
    ))->toThrow(RuntimeException::class, 'sensitive provider response');

    $failure = ExternalJobFailure::query()->sole();
    $encoded = json_encode($failure->metadata, JSON_THROW_ON_ERROR);

    expect($failure->job_type)->toBe('VerifyPaymentAttemptSettlement')
        ->and($failure->metadata)->toMatchArray([
            'payment_attempt_reference' => $fixture['attempt']->reference,
            'voucher_reference' => 'voucher:'.$fixture['voucher']->getKey(),
            'provider' => 'netbank',
            'failure_type' => 'RuntimeException',
            'state' => 'verified',
            'retry_eligible' => true,
            'provider_calls' => false,
            'provider_inventory_changed' => false,
            'treasury_position_changed' => false,
            'voucher_collection_changed' => false,
            'compatibility_wallet_changed' => false,
        ])
        ->and($encoded)->not->toContain(
            $fixture['observation']->provider_transaction_id,
            'sensitive provider response',
        )
        ->and($fixture['attempt']->refresh()->status)->toBe(PaymentAttemptStatus::Verified)
        ->and(VoucherCollection::query()->count())->toBe(0);
});

it('fails closed when partial Treasury evidence already exists', function (): void {
    $fixture = verifiedSettlementRecoveryFixture(10_000);
    $plan = app(VerifiedPaymentSettlementRecoveryService::class)
        ->inspect([$fixture['attempt']->reference])[0];
    app(TreasuryInventoryRegistrationService::class)->ensure(
        new TreasuryInventoryData(
            inventoryReference: 'inventory:netbank:vca-cash',
            resourceType: 'cash_at_bank',
            currency: 'PHP',
            capacityMinor: 0,
            status: 'requested',
            idempotencyKey: 'register:inventory:netbank:vca-cash',
            externalReference: 'resource:netbank:corporate-vca',
            metadata: ['provider' => 'netbank'],
        ),
    );
    TreasuryInventoryOperation::query()->create([
        'operation_reference' => $plan->operationReferences['inventory_recognition'],
        'idempotency_key' => 'partial-recovery-evidence',
        'request_hash' => hash('sha256', 'partial-recovery-evidence'),
        'operation_type' => TreasuryInventoryOperationType::Recognition,
        'destination_inventory_id' => TreasuryInventory::query()
            ->where('inventory_reference', 'inventory:netbank:vca-cash')
            ->sole()
            ->getKey(),
        'amount_minor' => 10_000,
        'currency' => 'PHP',
        'effective_at' => now(),
    ]);

    $this->artisan('x-change:payments:resume-verified', [
        '--attempt' => [$fixture['attempt']->reference],
        '--commit' => true,
        '--json' => true,
    ])->assertFailed();

    expect($fixture['attempt']->refresh()->status)->toBe(PaymentAttemptStatus::Verified)
        ->and(VoucherCollection::query()->count())->toBe(0)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1)
        ->and(TreasuryPositionOperation::query()->count())->toBe(0);
});

/**
 * @return array{owner: User, wallet: object, voucher: Voucher, observation: ProviderFundingObservation, attempt: PaymentAttempt}
 */
function verifiedSettlementRecoveryFixture(
    int $amountMinor,
    mixed $expiresAt = null,
): array {
    $owner = actingAsTestUser(0);
    $wallet = $owner->wallet()->where('slug', 'platform')->sole();
    $voucher = GenerateVouchers::run(validVoucherInstructions(0, 'INSTAPAY', [
        'voucher_type' => 'payable',
        'target_amount' => $amountMinor / 100,
        'metadata' => [
            'flow_type' => 'collectible',
            'issuer_id' => (string) $owner->getKey(),
            'collection_wallet_id' => (string) $wallet->getKey(),
        ],
    ]))->sole();
    $providerTransactionId = 'RECOVERY-'.Str::ulid();
    $observation = ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', $providerTransactionId),
        'provider_code' => 'netbank',
        'provider_transaction_id' => $providerTransactionId,
        'provider_operation_id' => 'OP-'.$providerTransactionId,
        'request_id' => 'REQ-'.$providerTransactionId,
        'funding_address' => 'sha256:'.hash('sha256', $providerTransactionId),
        'provider_account_reference' => 'corporate-vca',
        'gross_amount_minor' => $amountMinor,
        'fee_amount_minor' => 0,
        'net_amount_minor' => $amountMinor,
        'currency' => 'PHP',
        'provider_status' => 'settled',
        'occurred_at' => now()->subMinute(),
        'settled_at' => now()->subMinute(),
        'verification_source' => 'transaction_history',
        'payload_hash' => hash('sha256', 'payload-'.$providerTransactionId),
        'metadata' => ['destination_verified' => true],
    ]);
    $attempt = PaymentAttempt::query()->create([
        'reference' => (string) Str::ulid(),
        'voucher_id' => $voucher->getKey(),
        'provider_code' => 'netbank',
        'expected_amount_minor' => $amountMinor,
        'currency' => 'PHP',
        'status' => PaymentAttemptStatus::Verified,
        'version' => 3,
        'session_key_hash' => hash('sha256', 'session-'.$providerTransactionId),
        'idempotency_key_hash' => hash('sha256', 'idempotency-'.$providerTransactionId),
        'idempotency_fingerprint' => hash('sha256', 'fingerprint-'.$providerTransactionId),
        'matched_observation_id' => $observation->getKey(),
        'provider_transaction_id' => $providerTransactionId,
        'verified_at' => now()->subMinute(),
        'expires_at' => $expiresAt ?? now()->addHour(),
    ]);

    return compact('owner', 'wallet', 'voucher', 'observation', 'attempt');
}
