<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Bavix\Wallet\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
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
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Banking\AssignedFundingTreasuryConnection;
use LBHurtado\XChange\Services\Execution\VoucherCollectionWalletSelector;
use LBHurtado\XChange\Services\Funding\SharedFundingTreasuryConnection;
use LBHurtado\XChange\Services\Payment\SpecificPaymentAttribution;
use LBHurtado\XChange\Support\Funding\QrPhFundingSimulatorGuard;

final readonly class VerifiedTreasuryFundingAllocationService implements VerifiedTreasuryFundingAllocationContract
{
    public function __construct(
        private FundingAccountCreditContract $accounts,
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryProvisioningService $systemPositions,
        private TreasuryAccountPortfolioProvisioningContract $accountPortfolios,
        private TreasuryPositionOperationContract $operations,
        private QrPhFundingSimulatorGuard $simulator,
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

        if ($provider === 'qrph_simulator') {
            return $this->allocateSimulation(
                $accountReference,
                $amountMinor,
                $currency,
                $evidenceReference,
                $metadata,
            );
        }

        $account = $this->accounts->resolve($accountReference);
        $owner = data_get($account, 'holder');

        if (! $owner instanceof Model) {
            throw FundingSettlementDenied::because(
                'the Account owner could not be resolved for Treasury allocation',
            );
        }

        $destinationSnapshot = $metadata['funding_destination_snapshot'] ?? null;
        unset($metadata['funding_destination_snapshot']);
        if ($destinationSnapshot !== null) {
            if (! is_array($destinationSnapshot)) {
                throw FundingSettlementDenied::because('the funding destination snapshot is invalid');
            }
            if (data_get($destinationSnapshot, 'mode') === 'shared') {
                $connection = app(SharedFundingTreasuryConnection::class)->resolve($provider, $currency, $destinationSnapshot);
            } else {
                $bankOwner = $owner;
                if (($metadata['source'] ?? null) === 'verified_voucher_collection') {
                    $attempt = PaymentAttempt::query()->with('voucher.owner')
                        ->where('provider_code', $provider)
                        ->where('provider_transaction_id', $metadata['provider_transaction_id'] ?? null)
                        ->where('status', PaymentAttemptStatus::Verified->value)->first();
                    if (! $attempt && is_string($metadata['payment_attempt_reference'] ?? null)
                        && is_string($metadata['provider_transaction_id'] ?? null)) {
                        $observed = ObservedPaymentTransaction::query()->with('paymentAttempt.voucher.owner')
                            ->where('provider_code', $provider)
                            ->where('provider_transaction_hash', hash('sha256', $metadata['provider_transaction_id']))->first();
                        $candidate = $observed?->paymentAttempt;
                        $observation = ProviderFundingObservation::query()->find($metadata['provider_observation_id'] ?? null);
                        if ($candidate !== null && $observed !== null && $observation !== null
                            && $candidate->status === PaymentAttemptStatus::Settled
                            && $candidate->reference === $metadata['payment_attempt_reference']
                            && app(SpecificPaymentAttribution::class)->matches($candidate, $observed, $observation)) {
                            $attempt = $candidate;
                        }
                    }
                    if (! $attempt || ! $attempt->voucher->owner instanceof Model
                        || (string) $attempt->voucher->code !== (string) ($metadata['voucher_code'] ?? '')
                        || $attempt->destination_snapshot_ciphertext !== $destinationSnapshot) {
                        throw FundingSettlementDenied::because('the collection does not match its frozen bank account');
                    }
                    $bankOwner = $attempt->voucher->owner;
                    $selected = app(VoucherCollectionWalletSelector::class)->resolve(
                        $attempt->voucher, $provider, $currency, data_get($destinationSnapshot, 'metadata.treasury_connection_reference'),
                    );
                    if ((string) $selected->uuid !== substr($accountReference, 7)) {
                        throw FundingSettlementDenied::because('the allocation does not match the voucher destination');
                    }
                }
                $connection = app(AssignedFundingTreasuryConnection::class)->resolve($bankOwner, $provider, $currency, $destinationSnapshot);
            }
            $metadata['treasury_connection_reference'] = $connection->reference;
        } else {
            $connection = $this->connection($provider, $currency);
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

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function allocateSimulation(
        string $accountReference,
        int $amountMinor,
        string $currency,
        string $evidenceReference,
        array $metadata,
    ): VerifiedTreasuryFundingAllocationData {
        $this->simulator->assertAvailable();
        $scope = hash('sha256', $evidenceReference);
        $account = $this->accounts->resolve($accountReference);

        if (data_get($metadata, 'funding_intent_purpose') === 'on_demand_issuance') {
            $owner = data_get($account, 'holder');

            if (! $owner instanceof Model) {
                throw FundingSettlementDenied::because(
                    'the simulated on-demand Account owner could not be resolved',
                );
            }

            $portfolio = $this->accountPortfolios->provision($owner, [
                $connectionReference = (string) config(
                    'x-change.funding.requests.bank_transfer.connection_reference',
                    'netbank-primary',
                ),
            ]);
            $system = $this->systemPositions->provision([$connectionReference]);
            $source = $this->position(
                $system->positions,
                TreasuryPositionPurpose::TreasuryClearing,
            );
            $destination = $this->position(
                $portfolio->positions,
                TreasuryPositionPurpose::ClientFunds,
            );
            $recognition = $this->operations->recognize(
                new TreasuryPositionRecognitionData(
                    operationReference: 'simulation-recognition:'.$scope,
                    destinationPositionReference: $source->positionReference,
                    amountMinor: $amountMinor,
                    currency: $currency,
                    idempotencyKey: 'simulation-recognition-key:'.$scope,
                    externalReference: $evidenceReference,
                    metadata: [
                        ...$metadata,
                        'simulation_only' => true,
                    ],
                ),
            );
            $allocation = $this->operations->allocate(
                new TreasuryPositionAllocationData(
                    operationReference: 'simulation-allocation:'.$scope,
                    sourcePositionReference: $source->positionReference,
                    destinationPositionReference: $destination->positionReference,
                    amountMinor: $amountMinor,
                    currency: $currency,
                    idempotencyKey: 'simulation-allocation-key:'.$scope,
                    externalReference: $recognition->operationReference,
                    metadata: [
                        ...$metadata,
                        'simulation_only' => true,
                    ],
                ),
            );

            if ($allocation->destinationTransactionId === null
                || $allocation->destinationTransactionUuid === null) {
                throw FundingSettlementDenied::because(
                    'the simulated on-demand Treasury allocation did not return committed ledger references',
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

        $transaction = $this->accounts->credit($account, $amountMinor, [
            ...$metadata,
            'simulation_only' => true,
            'simulation_operation_reference' => 'simulation-allocation:'.$scope,
        ]);

        if (! $transaction instanceof Transaction) {
            throw FundingSettlementDenied::because(
                'the simulated Account allocation did not return a ledger transaction',
            );
        }

        return new VerifiedTreasuryFundingAllocationData(
            sourcePositionReference: 'simulation:qrph:local-clearing',
            destinationPositionReference: $accountReference,
            recognitionOperationReference: 'simulation-recognition:'.$scope,
            allocationOperationReference: 'simulation-allocation:'.$scope,
            amountMinor: $amountMinor,
            currency: $currency,
            destinationTransactionId: (int) $transaction->getKey(),
            destinationTransactionUuid: (string) $transaction->uuid,
            transferId: null,
            transferUuid: null,
            positionBased: false,
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
