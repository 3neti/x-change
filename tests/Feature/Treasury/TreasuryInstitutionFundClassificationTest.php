<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Actions\Treasury\ApproveTreasuryInstitutionFundClassification;
use LBHurtado\XChange\Actions\Treasury\ExecuteTreasuryInstitutionFundClassification;
use LBHurtado\XChange\Actions\Treasury\RequestTreasuryInstitutionFundClassification;
use LBHurtado\XChange\Enums\TreasuryInstitutionFundClassificationStatus;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\TreasuryInstitutionFundClassification;
use LBHurtado\XChange\Models\TreasuryOperatorAuthorization;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

function authorizeInstitutionFundsOperator(User $operator, TreasuryOperatorCapability ...$capabilities): void
{
    foreach ($capabilities as $capability) {
        TreasuryOperatorAuthorization::query()->create([
            'operator_type' => $operator->getMorphClass(),
            'operator_id' => $operator->getKey(),
            'capability' => $capability->value,
            'authorization_reference' => 'institution-funds-test:'.$operator->getKey().':'.$capability->value,
            'valid_from' => now()->subMinute(),
        ]);
    }
}

it('classifies exact authoritative evidence as Institution-Owned Funds once without changing Provider Inventory', function (): void {
    $system = enableNetbankTreasuryForTests();
    $maker = actingAsTestUser();
    $checker = User::query()->create(['name' => 'Checker', 'email' => 'classification-checker@example.test', 'password' => 'password']);
    authorizeInstitutionFundsOperator(
        $maker,
        TreasuryOperatorCapability::RequestInstitutionFunds,
        TreasuryOperatorCapability::ApproveInstitutionFunds,
    );
    authorizeInstitutionFundsOperator(
        $checker,
        TreasuryOperatorCapability::ApproveInstitutionFunds,
        TreasuryOperatorCapability::ExecuteInstitutionFunds,
    );
    app(TreasuryProvisioningService::class)->provision(['netbank-primary']);
    $positions = TreasuryPosition::query()->whereMorphedTo('principal', $system)->get()->keyBy('purpose');
    $unattributed = $positions->get(TreasuryPositionPurpose::LegacyUnattributed->value);
    $institutionOwned = $positions->get(TreasuryPositionPurpose::InstitutionOwnedFunds->value);
    $evidence = app(TreasuryPositionOperationContract::class)->recognize(new TreasuryPositionRecognitionData(
        operationReference: 'opening-position-recognition:owner-funding-test',
        destinationPositionReference: $unattributed->position_reference,
        amountMinor: 1_000_00,
        currency: 'PHP',
        idempotencyKey: 'opening-position-recognition-key:owner-funding-test',
        externalReference: 'netbank-balance-observation:owner-funding-test',
        metadata: ['source' => 'provider_balance_reconciliation'],
    ));
    $inventoryBefore = (int) TreasuryInventory::query()->sum('balance_minor');

    $classification = app(RequestTreasuryInstitutionFundClassification::class)->handle(
        evidenceOperationReference: $evidence->operationReference,
        ownershipBasis: 'Shareholder owner-funding deposit approved for staging',
        idempotencyReference: 'institution-funds-request:test:001',
        maker: $maker,
    );

    expect(fn () => app(RequestTreasuryInstitutionFundClassification::class)->handle(
        evidenceOperationReference: $evidence->operationReference,
        ownershipBasis: 'A different ownership assertion',
        idempotencyReference: 'institution-funds-request:test:001',
        maker: $maker,
    ))->toThrow(DomainException::class, 'different input');

    expect(fn () => app(ApproveTreasuryInstitutionFundClassification::class)->handle($classification, $maker))
        ->toThrow(DomainException::class, 'independent');

    app(ApproveTreasuryInstitutionFundClassification::class)->handle($classification, $checker);
    $executed = app(ExecuteTreasuryInstitutionFundClassification::class)->handle($classification, $checker);
    $replay = app(ExecuteTreasuryInstitutionFundClassification::class)->handle($classification, $checker);

    expect($executed->status)->toBe(TreasuryInstitutionFundClassificationStatus::Executed)
        ->and($replay->operation_reference)->toBe($executed->operation_reference)
        ->and(Wallet::query()->findOrFail($unattributed->internal_ledger_id)->getBalanceIntAttribute())->toBe(0)
        ->and(Wallet::query()->findOrFail($institutionOwned->internal_ledger_id)->getBalanceIntAttribute())->toBe(1_000_00)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))->toBe($inventoryBefore)
        ->and(TreasuryInstitutionFundClassification::query()->count())->toBe(1)
        ->and(ExecutionJournalEntry::query()->where('event_type', 'treasury.institution_funds.classified')->count())->toBe(1);
});

it('rejects evidence that was not produced by authoritative provider balance reconciliation', function (): void {
    $system = enableNetbankTreasuryForTests();
    $maker = actingAsTestUser();
    authorizeInstitutionFundsOperator($maker, TreasuryOperatorCapability::RequestInstitutionFunds);
    app(TreasuryProvisioningService::class)->provision(['netbank-primary']);
    $unattributed = TreasuryPosition::query()
        ->whereMorphedTo('principal', $system)
        ->where('purpose', TreasuryPositionPurpose::LegacyUnattributed)
        ->sole();
    $evidence = app(TreasuryPositionOperationContract::class)->recognize(new TreasuryPositionRecognitionData(
        operationReference: 'manual-position-recognition:untrusted',
        destinationPositionReference: $unattributed->position_reference,
        amountMinor: 500_00,
        currency: 'PHP',
        idempotencyKey: 'manual-position-recognition-key:untrusted',
        externalReference: 'manual:untrusted',
    ));

    expect(fn () => app(RequestTreasuryInstitutionFundClassification::class)->handle(
        $evidence->operationReference,
        'Untrusted manual assertion',
        'institution-funds-request:untrusted',
        $maker,
    ))->toThrow(DomainException::class, 'authoritative provider-balance evidence');
});

it('exposes only unclassified authoritative evidence to authorized operators', function (): void {
    $system = enableNetbankTreasuryForTests();
    $maker = actingAsTestUser();
    authorizeInstitutionFundsOperator(
        $maker,
        TreasuryOperatorCapability::ViewInstitutionFunds,
        TreasuryOperatorCapability::RequestInstitutionFunds,
    );
    app(TreasuryProvisioningService::class)->provision(['netbank-primary']);
    $unattributed = TreasuryPosition::query()
        ->whereMorphedTo('principal', $system)
        ->where('purpose', TreasuryPositionPurpose::LegacyUnattributed)
        ->sole();
    app(TreasuryPositionOperationContract::class)->recognize(new TreasuryPositionRecognitionData(
        operationReference: 'opening-position-recognition:visible-owner-funding',
        destinationPositionReference: $unattributed->position_reference,
        amountMinor: 250_00,
        currency: 'PHP',
        idempotencyKey: 'opening-position-recognition-key:visible-owner-funding',
        externalReference: 'provider-evidence:visible-owner-funding',
        metadata: ['source' => 'provider_balance_reconciliation'],
    ));

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.treasury.account-grants.index'))
        ->assertOk()
        ->assertJsonPath('props.treasury_institution_funds.schema', 'x-change.cockpit.treasury-institution-funds.v1')
        ->assertJsonPath('props.treasury_institution_funds.candidates.0.amount_minor', 250_00)
        ->assertJsonPath('props.treasury_institution_funds.candidates.0.evidence_reference', 'provider-evidence:visible-owner-funding')
        ->assertJsonPath('props.treasury_account_grants.recipients', [])
        ->assertJsonPath('props.xchange.navigation.treasury_operations_visible', true)
        ->assertJsonMissingPath('props.treasury_institution_funds.candidates.0.metadata');
});
