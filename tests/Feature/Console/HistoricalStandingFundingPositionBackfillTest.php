<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Illuminate\Console\Command;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Enums\AccountFundingReceiptStatus;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Enums\FundingRecognitionMode;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Services\Treasury\HistoricalStandingFundingPositionBackfillService;
use LBHurtado\XChange\Tests\Fakes\User;

it('backfills verified historical standing funding exactly once', function () {
    [$owner, $legacyAccount, $receipt] = historicalStandingFundingReceipt(25_00);
    $originalCreditId = $receipt->wallet_transaction_id;
    $plan = app(HistoricalStandingFundingPositionBackfillService::class)
        ->inspect($owner, 'netbank-primary');

    expect($plan->candidateCount)->toBe(1)
        ->and($plan->amountMinor)->toBe(25_00);

    $this->artisan('x-change:treasury:backfill-standing-funding-positions', [
        'owner' => $owner->getKey(),
        '--connection' => 'netbank-primary',
        '--json' => true,
    ])
        ->expectsOutputToContain('"status":"dry_run"')
        ->assertExitCode(Command::SUCCESS);

    expect($legacyAccount->fresh()->getBalanceIntAttribute())->toBe(25_00)
        ->and(TreasuryPositionOperation::query()->count())->toBe(0);

    $this->artisan('x-change:treasury:backfill-standing-funding-positions', [
        'owner' => $owner->getKey(),
        '--connection' => 'netbank-primary',
        '--commit' => true,
        '--json' => true,
    ])
        ->expectsOutputToContain('"status":"backfilled"')
        ->assertExitCode(Command::SUCCESS);

    $receipt->refresh();
    $client = positionLedger($owner, TreasuryPositionPurpose::ClientFunds);
    $unattributed = positionLedger(null, TreasuryPositionPurpose::LegacyUnattributed);

    expect($legacyAccount->fresh()->getBalanceIntAttribute())->toBe(0)
        ->and($client->getBalanceIntAttribute())->toBe(25_00)
        ->and($unattributed->getBalanceIntAttribute())->toBe(0)
        ->and(TreasuryPositionOperation::query()->count())->toBe(2)
        ->and($receipt->wallet_transaction_id)->not->toBe($originalCreditId)
        ->and(data_get($receipt->metadata, 'legacy_wallet_credit_transaction_id'))
        ->toBe($originalCreditId)
        ->and(data_get($receipt->metadata, 'treasury_position_allocation_reference'))
        ->toStartWith('historical-position-allocation:');

    $this->artisan('x-change:treasury:backfill-standing-funding-positions', [
        'owner' => $owner->getKey(),
        '--connection' => 'netbank-primary',
        '--commit' => true,
        '--json' => true,
    ])
        ->expectsOutputToContain('"status":"no_candidates"')
        ->assertExitCode(Command::SUCCESS);

    expect($legacyAccount->fresh()->getBalanceIntAttribute())->toBe(0)
        ->and($client->fresh()->getBalanceIntAttribute())->toBe(25_00)
        ->and(TreasuryPositionOperation::query()->count())->toBe(2);
});

it('rejects a historical receipt whose legacy credit does not match', function () {
    [$owner, $legacyAccount] = historicalStandingFundingReceipt(
        receiptAmountMinor: 25_00,
        legacyCreditAmountMinor: 24_00,
    );

    $this->artisan('x-change:treasury:backfill-standing-funding-positions', [
        'owner' => $owner->getKey(),
        '--connection' => 'netbank-primary',
        '--commit' => true,
        '--json' => true,
    ])
        ->expectsOutputToContain('failed evidence validation')
        ->assertExitCode(Command::FAILURE);

    expect($legacyAccount->fresh()->getBalanceIntAttribute())->toBe(24_00)
        ->and(TreasuryPosition::query()->count())->toBe(0)
        ->and(TreasuryPositionOperation::query()->count())->toBe(0);
});

/**
 * @return array{User, Wallet, AccountFundingReceipt}
 */
function historicalStandingFundingReceipt(
    int $receiptAmountMinor,
    ?int $legacyCreditAmountMinor = null,
): array {
    enableNetbankTreasuryForTests();
    $owner = User::query()->create([
        'name' => 'Historical Funding Owner',
        'email' => 'historical-funding+'.fake()->uuid().'@example.com',
        'password' => 'not-a-login-credential',
    ]);
    $account = $owner->wallet()->firstOrCreate([
        'slug' => 'platform',
    ], [
        'name' => 'Legacy Platform Account',
    ]);
    $credit = $account->deposit(
        $legacyCreditAmountMinor ?? $receiptAmountMinor,
        ['source' => 'legacy_standing_funding_settlement'],
    );
    $address = StandingFundingAddress::query()->create([
        'binding_key' => hash('sha256', (string) str()->uuid()),
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
        'account_reference' => 'wallet:'.$account->uuid,
        'provider_code' => 'netbank',
        'purpose' => FundingAddressPurpose::AccountFunding,
        'recognition_mode' => FundingRecognitionMode::Automatic,
        'status' => FundingAddressStatus::Active,
        'provider_reference' => 'provider-address:test',
        'funding_address_ciphertext' => 'test-funding-address',
        'funding_address_hash' => hash('sha256', 'test-funding-address'),
        'currency' => 'PHP',
        'activated_at' => now()->subMinute(),
    ]);
    $observation = ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', (string) str()->uuid()),
        'provider_code' => 'netbank',
        'provider_transaction_id' => 'historical-'.str()->ulid(),
        'funding_address' => 'sha256:'.$address->funding_address_hash,
        'gross_amount_minor' => $receiptAmountMinor,
        'fee_amount_minor' => 0,
        'net_amount_minor' => $receiptAmountMinor,
        'currency' => 'PHP',
        'provider_status' => 'settled',
        'occurred_at' => now(),
        'settled_at' => now(),
        'verification_source' => 'provider_history',
        'payload_hash' => hash('sha256', (string) str()->uuid()),
        'metadata' => ['destination_verified' => true],
    ]);
    $treasury = app(TreasuryInventoryOperationContract::class);
    $treasury->registerInventory(new TreasuryInventoryData(
        inventoryReference: 'inventory:netbank:vca-cash',
        resourceType: 'cash_at_bank',
        currency: 'PHP',
        capacityMinor: 0,
        status: 'requested',
        idempotencyKey: 'register:inventory:netbank:vca-cash',
        externalReference: 'resource:netbank:corporate-vca',
    ));
    $inventoryRecognition = $treasury->recognize(
        new TreasuryInventoryRecognitionData(
            operationReference: 'funding-recognition:'.hash('sha256', $observation->provider_transaction_id),
            inventoryReference: 'inventory:netbank:vca-cash',
            settlementResourceReference: 'resource:netbank:corporate-vca',
            amountMinor: $receiptAmountMinor,
            currency: 'PHP',
            status: 'requested',
            idempotencyKey: 'funding-recognition-key:'.hash('sha256', $observation->provider_transaction_id),
            externalReference: 'netbank:'.$observation->provider_transaction_id,
        ),
    );
    $receipt = AccountFundingReceipt::query()->create([
        'standing_funding_address_id' => $address->getKey(),
        'provider_funding_observation_id' => $observation->getKey(),
        'provider_transaction_key' => hash('sha256', $observation->provider_transaction_id),
        'provider_code' => 'netbank',
        'account_reference' => 'wallet:'.$account->uuid,
        'purpose' => FundingAddressPurpose::AccountFunding,
        'recognition_mode_snapshot' => FundingRecognitionMode::Automatic,
        'status' => AccountFundingReceiptStatus::Settled,
        'gross_amount_minor' => $receiptAmountMinor,
        'fee_amount_minor' => 0,
        'net_amount_minor' => $receiptAmountMinor,
        'currency' => 'PHP',
        'treasury_inventory_reference' => 'inventory:netbank:vca-cash',
        'treasury_operation_reference' => $inventoryRecognition->operationReference,
        'wallet_transaction_id' => $credit->getKey(),
        'wallet_transaction_uuid' => $credit->uuid,
        'observed_at' => now(),
        'verified_at' => now(),
        'settled_at' => now(),
    ]);

    return [$owner, $account, $receipt];
}

function positionLedger(
    ?User $owner,
    TreasuryPositionPurpose $purpose,
): Wallet {
    $query = TreasuryPosition::query()->where('purpose', $purpose);

    if ($owner !== null) {
        $query->whereMorphedTo('principal', $owner);
    }

    $position = $query->sole();

    return Wallet::query()->findOrFail($position->internal_ledger_id);
}
