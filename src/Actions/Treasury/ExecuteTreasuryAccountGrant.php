<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Treasury;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Enums\TreasuryAccountGrantStatus;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\TreasuryAccountGrant;
use LBHurtado\XChange\Services\Treasury\TreasuryAccountGrantJournal;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;

final readonly class ExecuteTreasuryAccountGrant
{
    public function __construct(
        private TreasuryOperatorAuthority $authority,
        private SystemUserResolverContract $systemUsers,
        private TreasuryProvisioningService $systemPositions,
        private TreasuryAccountPortfolioProvisioningContract $accountPortfolios,
        private TreasuryPositionOperationContract $operations,
        private TreasuryAccountGrantJournal $journal,
    ) {}

    public function handle(TreasuryAccountGrant $grant, Model $operator): TreasuryAccountGrant
    {
        $this->authority->assertAllows($operator, TreasuryOperatorCapability::ExecuteAccountGrants);

        return DB::transaction(function () use ($grant, $operator): TreasuryAccountGrant {
            $locked = TreasuryAccountGrant::query()->with('recipient')->lockForUpdate()->findOrFail($grant->getKey());

            if ($locked->status === TreasuryAccountGrantStatus::Executed) {
                return $locked;
            }

            if ($locked->status !== TreasuryAccountGrantStatus::Approved || ! $locked->recipient instanceof Model) {
                throw new DomainException('Only an approved Account Grant with a valid recipient may be executed.');
            }

            $system = $this->systemUsers->resolve();

            if (! $system instanceof Model) {
                throw new DomainException('The System Principal is unavailable.');
            }

            $systemPortfolio = $this->systemPositions->provision([$locked->connection_reference]);
            $recipientPortfolio = $this->accountPortfolios->provision($locked->recipient, [$locked->connection_reference]);
            $source = $this->position($systemPortfolio->positions, TreasuryPositionPurpose::InstitutionOwnedFunds);
            $destination = $this->position($recipientPortfolio->positions, TreasuryPositionPurpose::ClientFunds);
            $operationReference = 'treasury-account-grant:'.$locked->reference;
            $allocation = $this->operations->allocate(new TreasuryPositionAllocationData(
                operationReference: $operationReference,
                sourcePositionReference: $source->positionReference,
                destinationPositionReference: $destination->positionReference,
                amountMinor: $locked->amount_minor,
                currency: $locked->currency,
                idempotencyKey: 'treasury-account-grant-key:'.$locked->reference,
                externalReference: 'treasury-account-grant-approval:'.$locked->reference,
                metadata: [
                    'domain' => 'treasury_account_grant',
                    'grant_reference' => $locked->reference,
                    'request_hash' => $locked->request_hash,
                    'test_allocation' => $locked->test_allocation,
                    'provider_call' => false,
                ],
            ));

            $locked->forceFill([
                'status' => TreasuryAccountGrantStatus::Executed,
                'source_position_reference' => $source->positionReference,
                'destination_position_reference' => $destination->positionReference,
                'operation_reference' => $allocation->operationReference,
                'executed_at' => now(),
            ])->save();
            $this->journal->record($locked, 'treasury.account_grant.execution_authorized', $operator);
            $this->journal->record($locked, 'treasury.account_grant.executed', 'system');

            return $locked->refresh();
        }, attempts: 3);
    }

    /**
     * @param  list<TreasuryPositionData>  $positions
     */
    private function position(array $positions, TreasuryPositionPurpose $purpose): TreasuryPositionData
    {
        $matches = array_values(array_filter(
            $positions,
            static fn (TreasuryPositionData $position): bool => $position->purpose === $purpose,
        ));

        if (count($matches) !== 1) {
            throw new DomainException("Exactly one {$purpose->value} Position is required.");
        }

        return $matches[0];
    }
}
