<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDerecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionPayoutRecoveryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReleaseData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Treasury\TreasuryPayCodeSettlementData;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\DisbursementRejectionTrustService;

final readonly class TreasuryPayCodeAccountingService
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private TreasuryPositionReadModelContract $positions,
        private TreasuryPositionOperationContract $positionOperations,
        private TreasuryInventoryOperationContract $inventoryOperations,
        private DisbursementRejectionTrustService $rejectionTrust,
    ) {}

    public function reserve(
        Model $accountOwner,
        Voucher $voucher,
        string $connectionReference,
        int $providerPrincipalMinor,
        string $currency,
    ): TreasuryPositionReservationData {
        $connection = $this->connection($connectionReference, $currency);
        $portfolio = $this->portfolios->provision(
            $accountOwner,
            [$connection->reference],
        );
        $source = $this->position(
            $portfolio->positions,
            TreasuryPositionPurpose::ClientFunds,
        );
        $destination = $this->position(
            $portfolio->positions,
            TreasuryPositionPurpose::PayCodeReserve,
        );
        $scope = $this->scope($connection, $voucher, $providerPrincipalMinor);

        $reservation = $this->positionOperations->reserve(
            new TreasuryPositionReservationData(
                operationReference: 'pay-code-position-reservation:'.$scope,
                sourcePositionReference: $source->positionReference,
                destinationPositionReference: $destination->positionReference,
                amountMinor: $providerPrincipalMinor,
                currency: $connection->currency,
                idempotencyKey: 'pay-code-position-reservation-key:'.$scope,
                externalReference: 'pay-code:'.$voucher->getKey(),
                metadata: [
                    'source' => 'x_change_pay_code_issuance',
                    'pay_code_id' => (int) $voucher->getKey(),
                    'pay_code' => (string) $voucher->code,
                    'provider' => $connection->provider,
                    'connection_reference' => $connection->reference,
                ],
            ),
        );

        $metadata = is_array($voucher->metadata) ? $voucher->metadata : [];
        data_set($metadata, 'treasury.pay_code_reservation', [
            'status' => 'reserved',
            'provider' => $connection->provider,
            'connection_reference' => $connection->reference,
            'operation_reference' => $reservation->operationReference,
            'amount_minor' => $providerPrincipalMinor,
            'currency' => $connection->currency,
        ]);
        $voucher->forceFill(['metadata' => $metadata])->saveQuietly();

        return $reservation;
    }

    public function reserveAccountFunding(
        Model $systemOwner,
        Voucher $voucher,
        string $connectionReference,
        int $providerPrincipalMinor,
        string $currency,
    ): TreasuryPositionReservationData {
        $connection = $this->connection($connectionReference, $currency);
        $positions = $this->accountPositions($systemOwner, $connection);
        $source = $this->position(
            $positions,
            TreasuryPositionPurpose::AccountFundingReserve,
        );
        $destination = $this->position(
            $positions,
            TreasuryPositionPurpose::PayCodeReserve,
        );
        $scope = $this->scope(
            $connection,
            $voucher,
            $providerPrincipalMinor,
        );
        $reservation = $this->positionOperations->reserveAccountFunding(
            new TreasuryPositionReservationData(
                operationReference: 'account-funding-position-reservation:'.$scope,
                sourcePositionReference: $source->positionReference,
                destinationPositionReference: $destination->positionReference,
                amountMinor: $providerPrincipalMinor,
                currency: $connection->currency,
                idempotencyKey: 'account-funding-position-reservation-key:'.$scope,
                externalReference: 'pay-code:'.$voucher->getKey(),
                metadata: [
                    'source' => 'x_change_system_account_funding_pay_code_issuance',
                    'pay_code_id' => (int) $voucher->getKey(),
                    'pay_code' => (string) $voucher->code,
                    'provider' => $connection->provider,
                    'connection_reference' => $connection->reference,
                    'source_position_purpose' => TreasuryPositionPurpose::AccountFundingReserve->value,
                ],
            ),
        );
        $metadata = is_array($voucher->metadata)
            ? $voucher->metadata
            : [];
        data_set($metadata, 'treasury.pay_code_reservation', [
            'status' => 'reserved',
            'provider' => $connection->provider,
            'connection_reference' => $connection->reference,
            'operation_reference' => $reservation->operationReference,
            'amount_minor' => $providerPrincipalMinor,
            'currency' => $connection->currency,
            'source_position_purpose' => TreasuryPositionPurpose::AccountFundingReserve->value,
        ]);
        $voucher->forceFill(['metadata' => $metadata])->saveQuietly();

        return $reservation;
    }

    public function release(
        Model $accountOwner,
        Voucher $voucher,
        string $connectionReference,
        int $providerPrincipalMinor,
        string $currency,
        string $reasonReference,
    ): TreasuryPositionReleaseData {
        $connection = $this->connection($connectionReference, $currency);
        $positions = $this->accountPositions($accountOwner, $connection);
        $source = $this->position(
            $positions,
            TreasuryPositionPurpose::PayCodeReserve,
        );
        $destination = $this->position(
            $positions,
            TreasuryPositionPurpose::ClientFunds,
        );
        $scope = hash('sha256', implode('|', [
            $this->scope($connection, $voucher, $providerPrincipalMinor),
            trim($reasonReference),
        ]));

        return $this->positionOperations->release(
            new TreasuryPositionReleaseData(
                operationReference: 'pay-code-position-release:'.$scope,
                sourcePositionReference: $source->positionReference,
                destinationPositionReference: $destination->positionReference,
                amountMinor: $providerPrincipalMinor,
                currency: $connection->currency,
                idempotencyKey: 'pay-code-position-release-key:'.$scope,
                externalReference: $reasonReference,
                metadata: [
                    'source' => 'x_change_pay_code_release',
                    'pay_code_id' => (int) $voucher->getKey(),
                    'pay_code' => (string) $voucher->code,
                    'provider' => $connection->provider,
                    'connection_reference' => $connection->reference,
                ],
            ),
        );
    }

    public function holdRejectedPayout(
        Model $accountOwner,
        Voucher $voucher,
        DisbursementReconciliation $reconciliation,
    ): TreasuryPositionPayoutRecoveryData {
        $reservation = (array) data_get(
            $voucher->metadata,
            'treasury.pay_code_reservation',
            [],
        );
        $amountMinor = (int) data_get($reservation, 'amount_minor', 0);
        $currency = mb_strtoupper((string) data_get($reservation, 'currency'));
        $connection = $this->connection(
            (string) data_get($reservation, 'connection_reference'),
            $currency,
        );
        $providerEvidenceReference = filled($reconciliation->provider_transaction_id)
            ? (string) $reconciliation->provider_transaction_id
            : 'reconciliation:'.$reconciliation->getKey();

        if (
            data_get($reservation, 'status') !== 'reserved'
            || ! $this->rejectionTrust->isTrusted($reconciliation)
            || (int) round(((float) $reconciliation->amount) * 100) !== $amountMinor
            || $amountMinor <= 0
        ) {
            throw new TreasuryConfigurationException(
                'The provider rejection does not support a beneficiary payout recovery hold.',
            );
        }

        $positions = $this->portfolios->provision(
            $accountOwner,
            [$connection->reference],
        )->positions;
        $reserve = $this->position(
            $positions,
            TreasuryPositionPurpose::PayCodeReserve,
        );
        $payable = $this->position(
            $positions,
            TreasuryPositionPurpose::BeneficiaryPayoutPayable,
        );
        $scope = hash('sha256', implode('|', [
            $connection->provider,
            $connection->reference,
            (string) $voucher->getKey(),
            (string) $reconciliation->getKey(),
            $providerEvidenceReference,
            (string) $amountMinor,
        ]));

        return $this->positionOperations->holdPayoutRecovery(
            new TreasuryPositionPayoutRecoveryData(
                operationReference: 'pay-code-payout-recovery-hold:'.$scope,
                sourcePositionReference: $reserve->positionReference,
                destinationPositionReference: $payable->positionReference,
                amountMinor: $amountMinor,
                currency: $connection->currency,
                idempotencyKey: 'pay-code-payout-recovery-hold-key:'.$scope,
                externalReference: $connection->provider.':'.$providerEvidenceReference,
                metadata: [
                    'source' => 'x_change_provider_disbursement_rejection',
                    'pay_code_id' => (int) $voucher->getKey(),
                    'disbursement_reconciliation_id' => (int) $reconciliation->getKey(),
                    'provider' => $connection->provider,
                    'connection_reference' => $connection->reference,
                    'provider_inventory_changed' => false,
                ],
            ),
        );
    }

    public function settle(
        Model $accountOwner,
        Voucher $voucher,
        DisbursementReconciliation $reconciliation,
        string $connectionReference,
        ?int $reservedPrincipalMinor = null,
    ): TreasuryPayCodeSettlementData {
        $currency = mb_strtoupper((string) $reconciliation->currency);
        $connection = $this->connection($connectionReference, $currency);
        [$beneficiaryAmountMinor, $configuredRailFeeMinor] =
            $this->settlementAmounts($voucher, $reconciliation, $connection);
        $providerPrincipalMinor = $beneficiaryAmountMinor;
        $settlementSourcePurpose = data_get(
            $voucher->metadata,
            'treasury.pay_code_reservation.status',
        ) === 'recovery_pending'
            ? TreasuryPositionPurpose::BeneficiaryPayoutPayable
            : TreasuryPositionPurpose::PayCodeReserve;
        $reserve = $this->position(
            $this->accountPositions($accountOwner, $connection),
            $settlementSourcePurpose,
        );
        $reservationScope = $this->scope(
            $connection,
            $voucher,
            $reservedPrincipalMinor ?? $providerPrincipalMinor,
        );
        $settlementScope = hash('sha256', implode('|', [
            $connection->provider,
            $connection->reference,
            (string) $voucher->getKey(),
            (string) $reconciliation->getKey(),
            (string) $reconciliation->provider_transaction_id,
            (string) $providerPrincipalMinor,
        ]));

        [$derecognition, $inventoryAdjustment] = DB::transaction(
            function () use (
                $connection,
                $configuredRailFeeMinor,
                $providerPrincipalMinor,
                $reconciliation,
                $reserve,
                $settlementScope,
                $voucher,
            ): array {
                $derecognition = $this->positionOperations->derecognize(
                    new TreasuryPositionDerecognitionData(
                        operationReference: 'pay-code-position-derecognition:'.$settlementScope,
                        sourcePositionReference: $reserve->positionReference,
                        amountMinor: $providerPrincipalMinor,
                        currency: $connection->currency,
                        idempotencyKey: 'pay-code-position-derecognition-key:'.$settlementScope,
                        externalReference: $connection->provider.':'.$reconciliation->provider_transaction_id,
                        metadata: [
                            'source' => 'x_change_provider_disbursement',
                            'pay_code_id' => (int) $voucher->getKey(),
                            'pay_code' => (string) $voucher->code,
                            'disbursement_reconciliation_id' => (int) $reconciliation->getKey(),
                            'provider' => $connection->provider,
                            'connection_reference' => $connection->reference,
                            'configured_rail_fee_minor' => $configuredRailFeeMinor,
                            'provider_inventory_outflow_minor' => $providerPrincipalMinor,
                        ],
                    ),
                );
                $inventoryAdjustment = $this->inventoryOperations->adjust(
                    new TreasuryInventoryAdjustmentData(
                        operationReference: 'pay-code-inventory-outflow:'.$settlementScope,
                        inventoryReference: $connection->inventoryReference,
                        deltaAmountMinor: -$providerPrincipalMinor,
                        currency: $connection->currency,
                        status: 'requested',
                        idempotencyKey: 'pay-code-inventory-outflow-key:'.$settlementScope,
                        effectiveAt: $reconciliation->completed_at?->toRfc3339String()
                            ?? now()->toRfc3339String(),
                        externalReference: $connection->provider.':'.$reconciliation->provider_transaction_id,
                        metadata: [
                            'source' => 'x_change_provider_disbursement',
                            'pay_code_id' => (int) $voucher->getKey(),
                            'pay_code' => (string) $voucher->code,
                            'disbursement_reconciliation_id' => (int) $reconciliation->getKey(),
                            'provider' => $connection->provider,
                            'connection_reference' => $connection->reference,
                            'configured_rail_fee_minor' => $configuredRailFeeMinor,
                            'provider_inventory_outflow_minor' => $providerPrincipalMinor,
                        ],
                    ),
                );

                return [$derecognition, $inventoryAdjustment];
            },
            attempts: 5,
        );

        return new TreasuryPayCodeSettlementData(
            reservationOperationReference: 'pay-code-position-reservation:'.$reservationScope,
            derecognitionOperationReference: $derecognition->operationReference,
            inventoryAdjustmentOperationReference: $inventoryAdjustment->operationReference,
            beneficiaryAmountMinor: $beneficiaryAmountMinor,
            providerInventoryOutflowMinor: $providerPrincipalMinor,
            configuredRailFeeMinor: $configuredRailFeeMinor,
            currency: $connection->currency,
        );
    }

    /**
     * @return array{int, int}
     */
    private function settlementAmounts(
        Voucher $voucher,
        DisbursementReconciliation $reconciliation,
        TreasuryProviderConnectionData $connection,
    ): array {
        $beneficiaryAmountMinor = (int) round(
            ((float) $reconciliation->amount) * 100,
        );
        $sliceNumber = filter_var(
            data_get($reconciliation->meta, 'slice_number'),
            FILTER_VALIDATE_INT,
            FILTER_NULL_ON_FAILURE,
        );
        $voucherClaim = $sliceNumber === null
            ? null
            : VoucherClaim::query()
                ->where('voucher_id', $voucher->getKey())
                ->where('claim_number', $sliceNumber)
                ->whereIn('status', ['succeeded', 'withdrawn', 'redeemed'])
                ->first();
        $evidenceAmountMinor = $sliceNumber === null
            ? (int) round(
                ((float) data_get(
                    $voucher->metadata,
                    'disbursement.amount',
                    -1,
                )) * 100,
            )
            : (int) ($voucherClaim?->requested_amount_minor ?? -1);
        $configuredRailFeeMinor = (int) data_get(
            $voucher->metadata,
            'disbursement.fee_amount',
            0,
        );
        $valid = (int) $reconciliation->voucher_id === (int) $voucher->getKey()
            && $reconciliation->provider === $connection->provider
            && $reconciliation->status === 'succeeded'
            && filled($reconciliation->provider_transaction_id)
            && $beneficiaryAmountMinor > 0
            && $evidenceAmountMinor === $beneficiaryAmountMinor
            && $configuredRailFeeMinor >= 0;

        if (! $valid) {
            throw new TreasuryConfigurationException(
                'The provider disbursement evidence does not support Treasury Pay Code settlement.',
            );
        }

        return [
            $beneficiaryAmountMinor,
            $configuredRailFeeMinor,
        ];
    }

    /**
     * @return list<TreasuryPositionData>
     */
    private function accountPositions(
        Model $accountOwner,
        TreasuryProviderConnectionData $connection,
    ): array {
        $principalReference = $this->principalReferences->resolve($accountOwner);

        return array_values(array_filter(
            $this->positions->forPrincipal($principalReference),
            static fn (TreasuryPositionData $position): bool => $position->status === 'active'
                && $position->provider === $connection->provider
                && $position->connectionReference === $connection->reference
                && $position->currency === $connection->currency,
        ));
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
            throw new TreasuryConfigurationException(
                "Treasury Pay Code accounting requires one {$purpose->value} Position.",
            );
        }

        return $matches[0];
    }

    private function connection(
        string $connectionReference,
        string $currency,
    ): TreasuryProviderConnectionData {
        $matches = $this->connections->active([trim($connectionReference)]);

        if (count($matches) !== 1 || $matches[0]->currency !== mb_strtoupper($currency)) {
            throw new TreasuryConfigurationException(
                'Treasury Pay Code accounting requires one matching active connection.',
            );
        }

        return $matches[0];
    }

    private function scope(
        TreasuryProviderConnectionData $connection,
        Voucher $voucher,
        int $providerPrincipalMinor,
    ): string {
        if ($providerPrincipalMinor <= 0 || ! $voucher->exists) {
            throw new TreasuryConfigurationException(
                'Treasury Pay Code accounting requires a persisted Pay Code and positive provider principal.',
            );
        }

        return hash('sha256', implode('|', [
            $connection->provider,
            $connection->reference,
            (string) $voucher->getKey(),
            (string) $providerPrincipalMinor,
            $connection->currency,
        ]));
    }
}
