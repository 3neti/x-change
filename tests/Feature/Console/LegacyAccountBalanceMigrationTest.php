<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Illuminate\Console\Command;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Services\Treasury\LegacyAccountBalanceMigrationService;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XChange\Tests\Fakes\User;

it('moves a legacy Account balance from reconciled unattributed funds exactly once', function () {
    $owner = legacyAccountOwner(500_00);
    recognizeUnattributedNetbankFunds(1_000_00);

    $first = app(LegacyAccountBalanceMigrationService::class)
        ->migrate($owner, 'netbank-primary');
    $second = app(LegacyAccountBalanceMigrationService::class)
        ->migrate($owner, 'netbank-primary');
    $legacyAccount = legacyPlatformLedger($owner);
    $clientFunds = treasuryClientFundsLedger($owner);
    $unattributed = treasuryPositionLedger(
        TreasuryPositionPurpose::LegacyUnattributed,
    );

    expect($first->status)->toBe('migrated')
        ->and($first->amountMinor)->toBe(500_00)
        ->and($first->allocationOperationReference)
        ->toStartWith('legacy-account-allocation:')
        ->and($second->status)->toBe('no_balance')
        ->and($legacyAccount->getBalanceIntAttribute())->toBe(0)
        ->and($clientFunds->getBalanceIntAttribute())->toBe(500_00)
        ->and($unattributed->getBalanceIntAttribute())->toBe(500_00)
        ->and(TreasuryPositionOperation::query()->count())->toBe(2);
});

it('refuses migration when provider-reconciled unattributed funds are insufficient', function () {
    $owner = legacyAccountOwner(500_00);
    recognizeUnattributedNetbankFunds(400_00);

    expect(fn () => app(LegacyAccountBalanceMigrationService::class)
        ->migrate($owner, 'netbank-primary'))
        ->toThrow(
            RuntimeException::class,
            'Legacy Account balance exceeds provider-reconciled unattributed funds.',
        );

    expect(legacyPlatformLedger($owner)->getBalanceIntAttribute())->toBe(500_00)
        ->and(treasuryClientFundsLedger($owner)->getBalanceIntAttribute())->toBe(0)
        ->and(treasuryPositionLedger(
            TreasuryPositionPurpose::LegacyUnattributed,
        )->getBalanceIntAttribute())->toBe(400_00);
});

it('requires an explicit connection and commit flag at the command boundary', function () {
    $owner = legacyAccountOwner(250_00);
    recognizeUnattributedNetbankFunds(250_00);

    $this->artisan('x-change:treasury:migrate-legacy-account', [
        'owner' => $owner->getKey(),
        '--connection' => 'netbank-primary',
    ])->assertExitCode(Command::FAILURE);

    $this->artisan('x-change:treasury:migrate-legacy-account', [
        'owner' => $owner->getKey(),
        '--connection' => 'netbank-primary',
        '--commit' => true,
        '--json' => true,
    ])
        ->expectsOutputToContain('"status":"migrated"')
        ->assertExitCode(Command::SUCCESS);

    expect(legacyPlatformLedger($owner)->getBalanceIntAttribute())->toBe(0)
        ->and(treasuryClientFundsLedger($owner)->getBalanceIntAttribute())->toBe(250_00);
});

function legacyAccountOwner(int $balanceMinor): User
{
    enableNetbankTreasuryForTests();
    $owner = User::query()->create([
        'name' => 'Legacy Account Owner',
        'email' => 'legacy-account+'.fake()->uuid().'@example.com',
        'password' => 'not-a-login-credential',
    ]);
    $account = $owner->wallet()->firstOrCreate([
        'slug' => 'platform',
    ], [
        'name' => 'Legacy Platform Account',
    ]);
    $account->deposit($balanceMinor, [
        'source' => 'legacy_test_fixture',
    ]);

    return $owner;
}

function recognizeUnattributedNetbankFunds(int $amountMinor): void
{
    $positions = app(TreasuryProvisioningService::class)
        ->provision(['netbank-primary'])
        ->positions;
    $unattributed = collect($positions)->sole(
        static fn ($position): bool => $position->purpose
            === TreasuryPositionPurpose::LegacyUnattributed,
    );

    app(TreasuryPositionOperationContract::class)->recognize(
        new TreasuryPositionRecognitionData(
            operationReference: 'test-opening-position-recognition:'.$amountMinor,
            destinationPositionReference: $unattributed->positionReference,
            amountMinor: $amountMinor,
            currency: 'PHP',
            idempotencyKey: 'test-opening-position-recognition-key:'.$amountMinor,
            externalReference: 'test-provider-balance:'.$amountMinor,
        ),
    );
}

function treasuryPositionLedger(TreasuryPositionPurpose $purpose): Wallet
{
    $position = TreasuryPosition::query()
        ->where('purpose', $purpose)
        ->sole();

    return Wallet::query()->findOrFail($position->internal_ledger_id);
}

function legacyPlatformLedger(User $owner): Wallet
{
    return $owner->wallet()->where('slug', 'platform')->sole();
}
