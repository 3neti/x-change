<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Treasury;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Enums\TreasuryInstitutionFundClassificationStatus;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\TreasuryInstitutionFundClassification;
use LBHurtado\XChange\Services\Treasury\TreasuryInstitutionFundClassificationJournal;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;

final readonly class ExecuteTreasuryInstitutionFundClassification
{
    public function __construct(
        private TreasuryOperatorAuthority $authority,
        private SystemUserResolverContract $systemUsers,
        private TreasuryProvisioningService $positions,
        private TreasuryPositionOperationContract $operations,
        private TreasuryInstitutionFundClassificationJournal $journal,
    ) {}

    public function handle(
        TreasuryInstitutionFundClassification $classification,
        Model $operator,
    ): TreasuryInstitutionFundClassification {
        $this->authority->assertAllows($operator, TreasuryOperatorCapability::ExecuteInstitutionFunds);

        return DB::transaction(function () use ($classification, $operator): TreasuryInstitutionFundClassification {
            $locked = TreasuryInstitutionFundClassification::query()
                ->lockForUpdate()
                ->findOrFail($classification->getKey());

            if ($locked->status === TreasuryInstitutionFundClassificationStatus::Executed) {
                return $locked;
            }

            if ($locked->status !== TreasuryInstitutionFundClassificationStatus::Approved) {
                throw new DomainException('Only an approved Institution-Owned Funds classification may be executed.');
            }

            $evidence = TreasuryPositionOperation::query()
                ->with('destinationPosition')
                ->where('operation_reference', $locked->evidence_operation_reference)
                ->lockForUpdate()
                ->sole();
            $source = $evidence->destinationPosition;
            $system = $this->systemUsers->resolve();

            if (! $source instanceof TreasuryPosition
                || ! $system instanceof Model
                || $evidence->operation_type !== TreasuryPositionOperationType::Recognition
                || $source->purpose !== TreasuryPositionPurpose::LegacyUnattributed
                || $source->principal_type !== $system->getMorphClass()
                || (string) $source->principal_id !== (string) $system->getKey()
                || $evidence->amount_minor !== $locked->amount_minor
                || $evidence->currency !== $locked->currency
                || $source->connection_reference !== $locked->connection_reference) {
                throw new DomainException('The approved provider evidence no longer matches the classification envelope.');
            }

            $portfolio = $this->positions->provision([$locked->connection_reference]);
            $destination = collect($portfolio->positions)->sole(
                static fn ($position): bool => $position->purpose === TreasuryPositionPurpose::InstitutionOwnedFunds,
            );
            $inventoryBefore = (int) TreasuryInventory::query()->sum('balance_minor');
            $allocation = $this->operations->allocate(new TreasuryPositionAllocationData(
                operationReference: 'treasury-institution-funds:'.$locked->reference,
                sourcePositionReference: $source->position_reference,
                destinationPositionReference: $destination->positionReference,
                amountMinor: $locked->amount_minor,
                currency: $locked->currency,
                idempotencyKey: 'treasury-institution-funds-key:'.$locked->reference,
                externalReference: $locked->evidence_operation_reference,
                metadata: [
                    'domain' => 'treasury_institution_funds',
                    'classification_reference' => $locked->reference,
                    'evidence_reference' => $locked->evidence_reference,
                    'request_hash' => $locked->request_hash,
                    'provider_call' => false,
                ],
            ));

            if ($inventoryBefore !== (int) TreasuryInventory::query()->sum('balance_minor')) {
                throw new DomainException('Institution-Owned Funds classification must not change Provider Inventory.');
            }

            $locked->forceFill([
                'status' => TreasuryInstitutionFundClassificationStatus::Executed,
                'source_position_reference' => $source->position_reference,
                'destination_position_reference' => $destination->positionReference,
                'operation_reference' => $allocation->operationReference,
                'executed_at' => now(),
            ])->save();
            $this->journal->record($locked, 'treasury.institution_funds.execution_authorized', $operator);
            $this->journal->record($locked, 'treasury.institution_funds.classified', 'system');

            return $locked->refresh();
        }, attempts: 3);
    }
}
