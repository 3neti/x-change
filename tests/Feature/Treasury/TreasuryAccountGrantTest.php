<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Actions\Treasury\ApproveTreasuryAccountGrant;
use LBHurtado\XChange\Actions\Treasury\ExecuteTreasuryAccountGrant;
use LBHurtado\XChange\Actions\Treasury\RequestTreasuryAccountGrant;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Enums\TreasuryAccountGrantStatus;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\TreasuryAccountGrant;
use LBHurtado\XChange\Models\TreasuryOperatorAuthorization;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

function authorizeTreasuryOperator(User $operator, TreasuryOperatorCapability ...$capabilities): void
{
    foreach ($capabilities as $capability) {
        TreasuryOperatorAuthorization::query()->create([
            'operator_type' => $operator->getMorphClass(),
            'operator_id' => $operator->getKey(),
            'capability' => $capability->value,
            'authorization_reference' => 'treasury-grant-test:'.$operator->getKey().':'.$capability->value,
            'valid_from' => now()->subMinute(),
        ]);
    }
}

it('moves institution-owned funds to recipient Client Funds exactly once without changing Provider Inventory', function (): void {
    $system = enableNetbankTreasuryForTests();
    $maker = actingAsTestUser();
    $checker = User::query()->create(['name' => 'Checker', 'email' => 'checker@example.test', 'password' => 'password']);
    $recipient = User::query()->create(['name' => 'Recipient', 'email' => 'recipient@example.test', 'password' => 'password']);
    authorizeTreasuryOperator(
        $maker,
        TreasuryOperatorCapability::RequestAccountGrants,
        TreasuryOperatorCapability::ApproveAccountGrants,
    );
    authorizeTreasuryOperator($checker, TreasuryOperatorCapability::ApproveAccountGrants, TreasuryOperatorCapability::ExecuteAccountGrants);
    app(TreasuryProvisioningService::class)->provision(['netbank-primary']);
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision($recipient, ['netbank-primary']);

    $positions = TreasuryPosition::query()->whereMorphedTo('principal', $system)->get()->keyBy('purpose');
    $clearing = $positions->get(TreasuryPositionPurpose::TreasuryClearing->value);
    $institutionOwned = $positions->get(TreasuryPositionPurpose::InstitutionOwnedFunds->value);
    $recipientClientFunds = TreasuryPosition::query()
        ->whereMorphedTo('principal', $recipient)
        ->where('purpose', TreasuryPositionPurpose::ClientFunds)
        ->sole();
    expect($clearing)->toBeInstanceOf(TreasuryPosition::class)
        ->and($institutionOwned)->toBeInstanceOf(TreasuryPosition::class);

    $operations = app(TreasuryPositionOperationContract::class);
    $recognition = $operations->recognize(new TreasuryPositionRecognitionData(
        operationReference: 'test:institution-owned:recognition',
        destinationPositionReference: $clearing->position_reference,
        amountMinor: 10_000_00,
        currency: 'PHP',
        idempotencyKey: 'test:institution-owned:recognition:key',
        externalReference: 'provider-statement:test-owned-funds',
    ));
    $operations->allocate(new TreasuryPositionAllocationData(
        operationReference: 'test:institution-owned:classification',
        sourcePositionReference: $clearing->position_reference,
        destinationPositionReference: $institutionOwned->position_reference,
        amountMinor: 10_000_00,
        currency: 'PHP',
        idempotencyKey: 'test:institution-owned:classification:key',
        externalReference: $recognition->operationReference,
    ));
    $inventoryBefore = (int) TreasuryInventory::query()->sum('balance_minor');

    $grant = app(RequestTreasuryAccountGrant::class)->handle(
        recipient: $recipient,
        amountMinor: 1_000_00,
        currency: 'PHP',
        connectionReference: 'netbank-primary',
        purpose: 'Beta testing allocation',
        idempotencyReference: 'grant:test:001',
        maker: $maker,
    );

    expect(fn () => app(ApproveTreasuryAccountGrant::class)->handle($grant, $maker))
        ->toThrow(DomainException::class, 'independent');

    app(ApproveTreasuryAccountGrant::class)->handle($grant, $checker);
    $executed = app(ExecuteTreasuryAccountGrant::class)->handle($grant, $checker);
    $replay = app(ExecuteTreasuryAccountGrant::class)->handle($grant, $checker);

    expect($executed->status)->toBe(TreasuryAccountGrantStatus::Executed)
        ->and($replay->operation_reference)->toBe($executed->operation_reference)
        ->and(Wallet::query()->findOrFail($institutionOwned->internal_ledger_id)->getBalanceIntAttribute())->toBe(9_000_00)
        ->and(Wallet::query()->findOrFail($recipientClientFunds->internal_ledger_id)->getBalanceIntAttribute())->toBe(1_000_00)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))->toBe($inventoryBefore)
        ->and(TreasuryAccountGrant::query()->count())->toBe(1)
        ->and(ExecutionJournalEntry::query()->where('event_type', 'treasury.account_grant.executed')->count())->toBe(1);
});

it('permits bounded Test Funds only outside production and when explicitly enabled', function (): void {
    enableNetbankTreasuryForTests();
    $maker = actingAsTestUser();
    $recipient = User::query()->create(['name' => 'Recipient', 'email' => 'test-recipient@example.test', 'password' => 'password']);
    authorizeTreasuryOperator($maker, TreasuryOperatorCapability::RequestAccountGrants);

    expect(fn () => app(RequestTreasuryAccountGrant::class)->handle(
        $recipient, 100_00, 'PHP', 'netbank-primary', 'Test funds', 'test-funds:disabled', $maker, true,
    ))->toThrow(DomainException::class, 'unavailable');

    config()->set('x-change.treasury_account_grants.test_allocations_enabled', true);
    config()->set('x-change.treasury_account_grants.test_max_amount_minor', 500_00);

    expect(fn () => app(RequestTreasuryAccountGrant::class)->handle(
        $recipient, 501_00, 'PHP', 'netbank-primary', 'Test funds', 'test-funds:over-limit', $maker, true,
    ))->toThrow(DomainException::class, 'per-grant limit');

    expect(app(RequestTreasuryAccountGrant::class)->handle(
        $recipient, 100_00, 'PHP', 'netbank-primary', 'Test funds', 'test-funds:allowed', $maker, true,
    )->test_allocation)->toBeTrue();
});

it('conceals Treasury Operations from ordinary Account holders and exposes it to named operators', function (): void {
    enableNetbankTreasuryForTests();
    $ordinary = actingAsTestUser();

    $this->get(route('x-change.cockpit.treasury.account-grants.index'))->assertNotFound();

    authorizeTreasuryOperator($ordinary, TreasuryOperatorCapability::ViewAccountGrants);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.treasury.account-grants.index'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/TreasuryOperations')
        ->assertJsonPath('props.treasury_account_grants.schema', 'x-change.cockpit.treasury-account-grants.v1')
        ->assertJsonPath('props.xchange.navigation.treasury_operations_visible', true);
});
