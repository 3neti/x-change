<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Models\Wallet;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasurySettlementResource;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSettlement;

it('atomically recognizes verified net inventory and credits the Account once', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $observation = providerFundingObservationForSettlement();
    $intent = verifiedFundingIntentForSettlement($wallet, $observation);

    $first = app(SettleVerifiedFundingIntent::class)->handle($intent);
    $second = app(SettleVerifiedFundingIntent::class)->handle($intent->refresh());

    expect($second->is($first))->toBeTrue()
        ->and($intent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(24_950)
        ->and($first->gross_amount_minor)->toBe(25_000)
        ->and($first->fee_amount_minor)->toBe(50)
        ->and($first->net_amount_minor)->toBe(24_950)
        ->and($first->treasury_inventory_reference)->toBe('inventory:netbank:vca-cash')
        ->and(FundingSettlement::query()->count())->toBe(1)
        ->and(TreasurySettlementResource::query()->count())->toBe(1)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(24_950)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1)
        ->and(Transaction::query()->where('type', Transaction::TYPE_DEPOSIT)->count())->toBe(1);

    $transaction = Transaction::query()->findOrFail($first->wallet_transaction_id);

    expect($transaction->meta)->toMatchArray([
        'source' => 'verified_provider_funding',
        'funding_intent_reference' => $intent->reference,
        'provider' => 'netbank',
        'provider_transaction_id' => $observation->provider_transaction_id,
        'provider_observation_id' => $observation->getKey(),
        'treasury_operation_reference' => $first->treasury_operation_reference,
    ]);
});

it('rolls back Treasury recognition when the Account credit fails', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $observation = providerFundingObservationForSettlement();
    $intent = verifiedFundingIntentForSettlement($wallet, $observation);

    app()->instance(FundingAccountCreditContract::class, new class($wallet) implements FundingAccountCreditContract
    {
        public function __construct(private readonly Wallet $wallet) {}

        public function resolve(string $accountReference): object
        {
            return $this->wallet;
        }

        public function credit(object $account, int $amountMinor, array $metadata): object
        {
            throw new RuntimeException('simulated Account ledger failure');
        }
    });

    expect(fn () => app(SettleVerifiedFundingIntent::class)->handle($intent))
        ->toThrow(RuntimeException::class, 'simulated Account ledger failure');

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Verified)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(FundingSettlement::query()->count())->toBe(0)
        ->and(TreasurySettlementResource::query()->count())->toBe(0)
        ->and(TreasuryInventory::query()->count())->toBe(0)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(0);
});

it('rejects a verified intent when its authoritative evidence no longer matches', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $observation = providerFundingObservationForSettlement([
        'metadata' => ['destination_verified' => false],
    ]);
    $intent = verifiedFundingIntentForSettlement($wallet, $observation);

    expect(fn () => app(SettleVerifiedFundingIntent::class)->handle($intent))
        ->toThrow(FundingSettlementDenied::class, 'authoritative provider evidence no longer matches');

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Verified)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(FundingSettlement::query()->count())->toBe(0)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(0);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function providerFundingObservationForSettlement(array $overrides = []): ProviderFundingObservation
{
    $transactionId = 'NB-'.Str::upper(Str::random(12));

    return ProviderFundingObservation::query()->create(array_replace([
        'observation_key' => hash('sha256', $transactionId),
        'provider_code' => 'netbank',
        'provider_transaction_id' => $transactionId,
        'provider_operation_id' => 'OP-'.$transactionId,
        'request_id' => 'REQ-'.$transactionId,
        'funding_address' => '001234567890',
        'provider_account_reference' => 'corporate-vca',
        'gross_amount_minor' => 25_000,
        'fee_amount_minor' => 50,
        'net_amount_minor' => 24_950,
        'currency' => 'PHP',
        'provider_status' => 'settled',
        'occurred_at' => now()->subMinute(),
        'settled_at' => now(),
        'verification_source' => 'transaction_history',
        'payload_hash' => hash('sha256', 'payload-'.$transactionId),
        'metadata' => ['destination_verified' => true],
    ], $overrides));
}

function verifiedFundingIntentForSettlement(
    Wallet $wallet,
    ProviderFundingObservation $observation,
): FundingIntent {
    $idempotency = (string) Str::uuid();

    return FundingIntent::query()->create([
        'account_reference' => 'wallet:'.$wallet->uuid,
        'provider_code' => 'netbank',
        'expected_amount_minor' => 25_000,
        'currency' => 'PHP',
        'status' => FundingIntentStatus::Verified,
        'version' => 4,
        'idempotency_key_hash' => hash('sha256', $idempotency),
        'idempotency_fingerprint' => hash('sha256', 'fingerprint-'.$idempotency),
        'created_by_type' => 'test',
        'created_by_id' => 'operator-1',
        'provider_reference' => 'VCA-'.$wallet->uuid,
        'provider_request_id' => $observation->request_id,
        'funding_address_ciphertext' => $observation->funding_address,
        'funding_address_hash' => hash('sha256', (string) $observation->funding_address),
        'matched_observation_id' => $observation->getKey(),
        'provider_transaction_id' => $observation->provider_transaction_id,
        'instructions_created_at' => now()->subMinutes(2),
        'evidence_received_at' => now()->subMinute(),
        'verified_at' => now(),
        'expires_at' => now()->addMinutes(30),
        'metadata' => ['source' => 'test'],
    ]);
}
