<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryInventoryOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Treasury\LegacyPayCodeFeeCorrectionData;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Models\LifecycleMoneyRun;

final readonly class LegacyPayCodeFeePostingCorrectionService
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private TreasuryInventoryPositionReadModelContract $inventories,
        private TreasuryPositionReadModelContract $positions,
        private TreasuryInventoryOperationContract $inventoryOperations,
        private TreasuryPositionOperationContract $positionOperations,
        private TreasuryLifecycleAccountingSnapshot $accounting,
    ) {}

    public function inspect(string $runReference): LegacyPayCodeFeeCorrectionData
    {
        [$run, $connection, $beneficiaryAmountMinor, $excessFeeAmountMinor] =
            $this->validate($runReference);
        [
            $inventoryReference,
            $positionRecognitionReference,
            $positionAllocationReference,
        ] = $this->correctionReferences(
            $run,
            $beneficiaryAmountMinor,
            $excessFeeAmountMinor,
        );

        return $this->result(
            status: $this->correctionStatus(
                $inventoryReference,
                $positionRecognitionReference,
                $positionAllocationReference,
            ),
            run: $run,
            connection: $connection,
            beneficiaryAmountMinor: $beneficiaryAmountMinor,
            excessFeeAmountMinor: $excessFeeAmountMinor,
            inventoryReference: $inventoryReference,
            positionRecognitionReference: $positionRecognitionReference,
            positionAllocationReference: $positionAllocationReference,
        );
    }

    public function correct(string $runReference): LegacyPayCodeFeeCorrectionData
    {
        $lock = Cache::lock(
            'x-change:treasury:legacy-pay-code-fee-correction:'.hash(
                'sha256',
                trim($runReference),
            ),
            max(1, (int) config('x-change.treasury.migration_lock_seconds', 60)),
        );

        return $lock->block(
            max(0, (int) config('x-change.treasury.migration_lock_wait_seconds', 5)),
            function () use ($runReference): LegacyPayCodeFeeCorrectionData {
                return DB::transaction(function () use (
                    $runReference,
                ): LegacyPayCodeFeeCorrectionData {
                    $run = LifecycleMoneyRun::query()
                        ->lockForUpdate()
                        ->where('reference', trim($runReference))
                        ->firstOrFail();
                    [$run, $connection, $beneficiaryAmountMinor, $excessFeeAmountMinor] =
                        $this->validateRun($run);
                    [
                        $inventoryReference,
                        $positionRecognitionReference,
                        $positionAllocationReference,
                    ] =
                        $this->correctionReferences(
                            $run,
                            $beneficiaryAmountMinor,
                            $excessFeeAmountMinor,
                        );
                    $status = $this->correctionStatus(
                        $inventoryReference,
                        $positionRecognitionReference,
                        $positionAllocationReference,
                    );

                    if ($status === 'dry_run') {
                        $owner = $this->owner($run);
                        $clientFunds = $this->clientFundsPosition(
                            $owner,
                            $connection,
                        );
                        $clearing = $this->systemPosition(
                            $connection,
                            TreasuryPositionPurpose::TreasuryClearing,
                        );
                        $scope = hash('sha256', implode('|', [
                            $run->reference,
                            (string) $run->voucher_id,
                            (string) $beneficiaryAmountMinor,
                            (string) $excessFeeAmountMinor,
                        ]));
                        $inventoryCorrection = $this->inventoryOperations
                            ->recognize(new TreasuryInventoryRecognitionData(
                                operationReference: $inventoryReference,
                                inventoryReference: $connection->inventoryReference,
                                settlementResourceReference: $connection->settlementResourceReference,
                                amountMinor: $excessFeeAmountMinor,
                                currency: $connection->currency,
                                status: 'requested',
                                idempotencyKey: 'pay-code-fee-boundary-inventory-correction-key:'.$scope,
                                externalReference: (string) data_get(
                                    $run->result_summary,
                                    'treasury_settlement.inventory_adjustment_operation_reference',
                                ),
                                metadata: $this->metadata(
                                    $run,
                                    $connection,
                                    $beneficiaryAmountMinor,
                                    $excessFeeAmountMinor,
                                ),
                            ));
                        $positionRecognition = $this->positionOperations
                            ->recognize(new TreasuryPositionRecognitionData(
                                operationReference: $positionRecognitionReference,
                                destinationPositionReference: $clearing->positionReference,
                                amountMinor: $excessFeeAmountMinor,
                                currency: $connection->currency,
                                idempotencyKey: 'pay-code-fee-boundary-position-recognition-key:'.$scope,
                                externalReference: $inventoryCorrection->operationReference,
                                metadata: $this->metadata(
                                    $run,
                                    $connection,
                                    $beneficiaryAmountMinor,
                                    $excessFeeAmountMinor,
                                ),
                            ));
                        $positionAllocation = $this->positionOperations
                            ->allocate(new TreasuryPositionAllocationData(
                                operationReference: $positionAllocationReference,
                                sourcePositionReference: $clearing->positionReference,
                                destinationPositionReference: $clientFunds->positionReference,
                                amountMinor: $excessFeeAmountMinor,
                                currency: $connection->currency,
                                idempotencyKey: 'pay-code-fee-boundary-position-allocation-key:'.$scope,
                                externalReference: $positionRecognition->operationReference,
                                metadata: $this->metadata(
                                    $run,
                                    $connection,
                                    $beneficiaryAmountMinor,
                                    $excessFeeAmountMinor,
                                ),
                            ));

                        $inventoryReference = $inventoryCorrection->operationReference;
                        $positionRecognitionReference =
                            $positionRecognition->operationReference;
                        $positionAllocationReference =
                            $positionAllocation->operationReference;
                        $status = 'corrected';
                    } else {
                        $status = 'already_corrected';
                    }

                    $owner = $this->owner($run);
                    $this->assertInternalControl($connection);
                    $run->forceFill([
                        'result_summary' => $this->normalizedSummary(
                            $run,
                            $owner,
                            $beneficiaryAmountMinor,
                            $excessFeeAmountMinor,
                            $inventoryReference,
                            $positionRecognitionReference,
                            $positionAllocationReference,
                        ),
                    ])->save();

                    return $this->result(
                        status: $status,
                        run: $run,
                        connection: $connection,
                        beneficiaryAmountMinor: $beneficiaryAmountMinor,
                        excessFeeAmountMinor: $excessFeeAmountMinor,
                        inventoryReference: $inventoryReference,
                        positionRecognitionReference: $positionRecognitionReference,
                        positionAllocationReference: $positionAllocationReference,
                    );
                }, attempts: 5);
            },
        );
    }

    /**
     * @return array{LifecycleMoneyRun, TreasuryProviderConnectionData, int, int}
     */
    private function validate(string $runReference): array
    {
        $run = LifecycleMoneyRun::query()
            ->where('reference', trim($runReference))
            ->firstOrFail();

        return $this->validateRun($run);
    }

    /**
     * @return array{LifecycleMoneyRun, TreasuryProviderConnectionData, int, int}
     */
    private function validateRun(LifecycleMoneyRun $run): array
    {
        $summary = $run->result_summary;
        $beneficiaryAmountMinor = (int) (
            data_get($summary, 'treasury_settlement.beneficiary_amount_minor')
            ?? 0
        );
        $excessFeeAmountMinor = (int) (
            data_get($summary, 'treasury_settlement.provider_fee_amount_minor')
            ?? data_get(
                $summary,
                'treasury_settlement.configured_rail_fee_minor',
                0,
            )
        );
        $legacyProviderOutflowMinor = (int) data_get(
            $summary,
            'treasury_settlement.provider_outflow_minor',
            $beneficiaryAmountMinor + $excessFeeAmountMinor,
        );
        $connection = $this->connection($run);
        $providerObservation = collect((array) data_get(
            $summary,
            'accounting.after_claim.connections',
            [],
        ))
            ->first(
                static fn (mixed $snapshot): bool => is_array($snapshot)
                    && data_get($snapshot, 'reference') === $connection->reference
                    && data_get($snapshot, 'provider') === $connection->provider,
            );
        $providerObservationDifferenceMinor = (int) data_get(
            $providerObservation,
            'provider_observation.difference_minor',
            $excessFeeAmountMinor,
        );
        $owner = $this->owner($run);
        $principalReference = $this->principalReferences->resolve($owner);
        $positionOperation = TreasuryPositionOperation::query()
            ->with('sourcePosition')
            ->where(
                'operation_reference',
                data_get(
                    $summary,
                    'treasury_settlement.derecognition_operation_reference',
                ),
            )
            ->first();
        $inventoryOperation = TreasuryInventoryOperation::query()
            ->with('sourceInventory')
            ->where(
                'operation_reference',
                data_get(
                    $summary,
                    'treasury_settlement.inventory_adjustment_operation_reference',
                ),
            )
            ->first();
        $valid = $run->scenario_key === 'treasury_live_basic_cash'
            && $run->voucher_id !== null
            && $run->provider_code === $connection->provider
            && $run->currency === $connection->currency
            && is_array($summary)
            && data_get($summary, 'provider_transfer_succeeded') === true
            && $beneficiaryAmountMinor > 0
            && $excessFeeAmountMinor > 0
            && $legacyProviderOutflowMinor
                === $beneficiaryAmountMinor + $excessFeeAmountMinor
            && $providerObservationDifferenceMinor === $excessFeeAmountMinor
            && $positionOperation?->operation_type
                === TreasuryPositionOperationType::Derecognition
            && $positionOperation->amount_minor === $legacyProviderOutflowMinor
            && $positionOperation->currency === $connection->currency
            && $positionOperation->sourcePosition?->purpose
                === TreasuryPositionPurpose::PayCodeReserve
            && $positionOperation->sourcePosition?->principal_reference
                === $principalReference
            && $inventoryOperation?->operation_type
                === TreasuryInventoryOperationType::Adjustment
            && $inventoryOperation->amount_minor === $legacyProviderOutflowMinor
            && $inventoryOperation->currency === $connection->currency
            && $inventoryOperation->sourceInventory?->inventory_reference
                === $connection->inventoryReference;

        if (! $valid) {
            throw new TreasuryConfigurationException(
                "Lifecycle run [{$run->reference}] is not an eligible legacy fee posting.",
            );
        }

        $this->assertInternalControl($connection);

        return [
            $run,
            $connection,
            $beneficiaryAmountMinor,
            $excessFeeAmountMinor,
        ];
    }

    private function connection(
        LifecycleMoneyRun $run,
    ): TreasuryProviderConnectionData {
        $reference = collect((array) data_get(
            $run->result_summary,
            'accounting.after_claim.connections',
            [],
        ))
            ->first(
                fn (mixed $connection): bool => is_array($connection)
                    && data_get($connection, 'provider') === $run->provider_code,
            );
        $matches = $this->connections->active([
            (string) data_get($reference, 'reference'),
        ]);

        if (count($matches) !== 1) {
            throw new TreasuryConfigurationException(
                'Legacy Pay Code fee correction requires one active Treasury connection.',
            );
        }

        return $matches[0];
    }

    private function owner(LifecycleMoneyRun $run): Model
    {
        $owner = $run->issuer()->first();

        if (! $owner instanceof Model) {
            throw new TreasuryConfigurationException(
                "Lifecycle run [{$run->reference}] has no Account owner.",
            );
        }

        return $owner;
    }

    private function clientFundsPosition(
        Model $owner,
        TreasuryProviderConnectionData $connection,
    ): TreasuryPositionData {
        $principalReference = $this->principalReferences->resolve($owner);
        $matches = array_values(array_filter(
            $this->positions->forPrincipal($principalReference),
            static fn (TreasuryPositionData $position): bool => $position->status === 'active'
                && $position->provider === $connection->provider
                && $position->connectionReference === $connection->reference
                && $position->currency === $connection->currency
                && $position->purpose === TreasuryPositionPurpose::ClientFunds,
        ));

        if (count($matches) !== 1) {
            throw new TreasuryConfigurationException(
                'Legacy Pay Code fee correction requires one Client Funds Position.',
            );
        }

        return $matches[0];
    }

    private function systemPosition(
        TreasuryProviderConnectionData $connection,
        TreasuryPositionPurpose $purpose,
    ): TreasuryPositionData {
        $matches = array_values(array_filter(
            $this->positions->forConnection(
                $connection->provider,
                $connection->reference,
                $connection->currency,
            ),
            static fn (TreasuryPositionData $position): bool => $position->status === 'active'
                && $position->purpose === $purpose,
        ));

        if (count($matches) !== 1) {
            throw new TreasuryConfigurationException(
                "Legacy Pay Code fee correction requires one {$purpose->value} Position.",
            );
        }

        return $matches[0];
    }

    private function assertInternalControl(
        TreasuryProviderConnectionData $connection,
    ): void {
        $inventory = $this->inventories->find($connection->inventoryReference);
        $positionBalanceMinor = array_sum(array_map(
            static fn (TreasuryPositionData $position): int => $position->balanceMinor,
            array_values(array_filter(
                $this->positions->forConnection(
                    $connection->provider,
                    $connection->reference,
                    $connection->currency,
                ),
                static fn (TreasuryPositionData $position): bool => $position->status === 'active',
            )),
        ));

        if (
            $inventory === null
            || $inventory->balanceMinor !== $positionBalanceMinor
        ) {
            throw new TreasuryConfigurationException(
                'Legacy Pay Code fee correction requires balanced Inventory and Positions.',
            );
        }
    }

    /**
     * @return array{string, string, string}
     */
    private function correctionReferences(
        LifecycleMoneyRun $run,
        int $beneficiaryAmountMinor,
        int $excessFeeAmountMinor,
    ): array {
        $scope = hash('sha256', implode('|', [
            $run->reference,
            (string) $run->voucher_id,
            (string) $beneficiaryAmountMinor,
            (string) $excessFeeAmountMinor,
        ]));

        return [
            'pay-code-fee-boundary-inventory-correction:'.$scope,
            'pay-code-fee-boundary-position-recognition:'.$scope,
            'pay-code-fee-boundary-position-allocation:'.$scope,
        ];
    }

    private function correctionStatus(
        string $inventoryReference,
        string $positionRecognitionReference,
        string $positionAllocationReference,
    ): string {
        $operationsExist = [
            $this->inventories->operationExists($inventoryReference),
            $this->positions->operationExists($positionRecognitionReference),
            $this->positions->operationExists($positionAllocationReference),
        ];

        if (count(array_unique($operationsExist)) !== 1) {
            throw new TreasuryConfigurationException(
                'Legacy Pay Code fee correction is partially applied.',
            );
        }

        return $operationsExist[0] ? 'already_corrected' : 'dry_run';
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(
        LifecycleMoneyRun $run,
        TreasuryProviderConnectionData $connection,
        int $beneficiaryAmountMinor,
        int $excessFeeAmountMinor,
    ): array {
        return [
            'source' => 'legacy_pay_code_fee_boundary_correction',
            'lifecycle_run_reference' => $run->reference,
            'pay_code_id' => $run->voucher_id,
            'provider' => $connection->provider,
            'connection_reference' => $connection->reference,
            'provider_principal_amount_minor' => $beneficiaryAmountMinor,
            'excess_fee_amount_minor' => $excessFeeAmountMinor,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedSummary(
        LifecycleMoneyRun $run,
        Model $owner,
        int $beneficiaryAmountMinor,
        int $excessFeeAmountMinor,
        string $inventoryReference,
        string $positionRecognitionReference,
        string $positionAllocationReference,
    ): array {
        $summary = $run->result_summary;
        $settlement = (array) data_get(
            $summary,
            'treasury_settlement',
            [],
        );
        $legacyBeforeMinor = (int) data_get(
            $summary,
            'accounting.before_issuance.account.legacy_compatibility_balance_minor',
            0,
        );
        $legacyAfterMinor = (int) data_get(
            $summary,
            'accounting.after_issuance.account.legacy_compatibility_balance_minor',
            $legacyBeforeMinor,
        );
        $senderSystemChargeMinor = max(
            0,
            $legacyBeforeMinor - $legacyAfterMinor - $beneficiaryAmountMinor,
        );
        unset(
            $settlement['provider_fee_amount_minor'],
            $settlement['provider_outflow_minor'],
        );

        return [
            ...$summary,
            'treasury_settlement' => [
                ...$settlement,
                'provider_inventory_outflow_minor' => $beneficiaryAmountMinor,
                'configured_rail_fee_minor' => $excessFeeAmountMinor,
                'sender_system_charge_minor' => $senderSystemChargeMinor,
                'sender_system_charge_status' => 'legacy_compatibility_ledger',
                'legacy_fee_correction' => [
                    'inventory_operation_reference' => $inventoryReference,
                    'position_recognition_operation_reference' => $positionRecognitionReference,
                    'position_allocation_operation_reference' => $positionAllocationReference,
                    'excess_fee_amount_minor' => $excessFeeAmountMinor,
                ],
            ],
            'accounting' => [
                ...((array) data_get($summary, 'accounting', [])),
                'after_claim' => $this->accounting->capture($owner),
            ],
            'accounting_boundary' => [
                ...((array) data_get(
                    $summary,
                    'accounting_boundary',
                    [],
                )),
                'pay_code_escrow_and_fees' => 'provider_principal_reserved_with_legacy_compatibility_mirror',
                'outbound_treasury_posting' => 'provider_principal_only',
                'sender_system_charge' => 'legacy_compatibility_ledger',
            ],
        ];
    }

    private function result(
        string $status,
        LifecycleMoneyRun $run,
        TreasuryProviderConnectionData $connection,
        int $beneficiaryAmountMinor,
        int $excessFeeAmountMinor,
        string $inventoryReference,
        string $positionRecognitionReference,
        string $positionAllocationReference,
    ): LegacyPayCodeFeeCorrectionData {
        return new LegacyPayCodeFeeCorrectionData(
            status: $status,
            runReference: $run->reference,
            voucherId: (int) $run->voucher_id,
            connectionReference: $connection->reference,
            provider: $connection->provider,
            currency: $connection->currency,
            beneficiaryAmountMinor: $beneficiaryAmountMinor,
            excessFeeAmountMinor: $excessFeeAmountMinor,
            inventoryCorrectionReference: $inventoryReference,
            positionRecognitionReference: $positionRecognitionReference,
            positionAllocationReference: $positionAllocationReference,
        );
    }
}
