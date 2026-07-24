<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Data\Treasury\VerifiedTreasuryFundingAllocationData;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;

final readonly class VerifiedTreasuryFundingAllocationService implements VerifiedTreasuryFundingAllocationContract
{
    public function __construct(
        private FundingAccountCreditContract $accounts,
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryProvisioningService $systemPositions,
        private TreasuryAccountPortfolioProvisioningContract $accountPortfolios,
        private TreasuryPositionOperationContract $operations,
    ) {}

    public function allocate(
        string $accountReference,
        string $provider,
        int $amountMinor,
        string $currency,
        string $evidenceReference,
        array $metadata = [],
    ): VerifiedTreasuryFundingAllocationData {
        $provider = mb_strtolower(trim($provider));
        $currency = mb_strtoupper(trim($currency));

        if ($amountMinor <= 0 || trim($evidenceReference) === '') {
            throw FundingSettlementDenied::because(
                'the verified Treasury allocation request is invalid',
            );
        }

        $connection = $this->connection($provider, $currency);
        $account = $this->accounts->resolve($accountReference);
        $owner = data_get($account, 'holder');

        if (! $owner instanceof Model) {
            throw FundingSettlementDenied::because(
                'the Account owner could not be resolved for Treasury allocation',
            );
        }

        $system = $this->systemPositions->provision([$connection->reference]);
        $portfolio = $this->accountPortfolios->provision($owner, [$connection->reference]);
        $source = $this->position(
            $system->positions,
            TreasuryPositionPurpose::TreasuryClearing,
        );
        $destination = $this->position(
            $portfolio->positions,
            TreasuryPositionPurpose::ClientFunds,
        );
        $operationScope = hash('sha256', implode('|', [
            $provider,
            $connection->reference,
            $currency,
            $evidenceReference,
        ]));
        $recognitionReference = 'position-recognition:'.$operationScope;
        $allocationReference = 'position-allocation:'.$operationScope;
        $recognition = $this->operations->recognize(
            new TreasuryPositionRecognitionData(
                operationReference: $recognitionReference,
                destinationPositionReference: $source->positionReference,
                amountMinor: $amountMinor,
                currency: $currency,
                idempotencyKey: 'position-recognition-key:'.$operationScope,
                externalReference: $evidenceReference,
                metadata: $metadata,
            ),
        );
        $allocation = $this->operations->allocate(
            new TreasuryPositionAllocationData(
                operationReference: $allocationReference,
                sourcePositionReference: $source->positionReference,
                destinationPositionReference: $destination->positionReference,
                amountMinor: $amountMinor,
                currency: $currency,
                idempotencyKey: 'position-allocation-key:'.$operationScope,
                externalReference: $recognition->operationReference,
                metadata: $metadata,
            ),
        );

        if (
            $allocation->destinationTransactionId === null
            || $allocation->destinationTransactionUuid === null
            || $allocation->transferId === null
            || $allocation->transferUuid === null
        ) {
            throw FundingSettlementDenied::because(
                'the Treasury allocation did not return committed ledger references',
            );
        }

        return new VerifiedTreasuryFundingAllocationData(
            sourcePositionReference: $source->positionReference,
            destinationPositionReference: $destination->positionReference,
            recognitionOperationReference: $recognition->operationReference,
            allocationOperationReference: $allocation->operationReference,
            amountMinor: $allocation->amountMinor,
            currency: $allocation->currency,
            destinationTransactionId: $allocation->destinationTransactionId,
            destinationTransactionUuid: $allocation->destinationTransactionUuid,
            transferId: $allocation->transferId,
            transferUuid: $allocation->transferUuid,
        );
    }

    private function connection(
        string $provider,
        string $currency,
    ): TreasuryProviderConnectionData {
        $connections = array_values(array_filter(
            $this->connections->active(),
            static fn ($connection): bool => $connection->provider === $provider
                && $connection->currency === $currency,
        ));

        if (count($connections) !== 1) {
            throw FundingSettlementDenied::because(
                'exactly one active Treasury provider connection is required',
            );
        }

        return $connections[0];
    }

    /**
     * @param  list<TreasuryPositionData>  $positions
     */
    private function position(
        array $positions,
        TreasuryPositionPurpose $purpose,
    ): TreasuryPositionData {
        $matches = array_values(array_filter(
            $positions,
            static fn (TreasuryPositionData $position): bool => $position->purpose === $purpose,
        ));

        if (count($matches) !== 1) {
            throw FundingSettlementDenied::because(
                "exactly one {$purpose->value} Treasury Position is required",
            );
        }

        return $matches[0];
    }
}
