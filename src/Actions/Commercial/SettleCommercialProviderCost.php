<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Commercial;

use Illuminate\Support\Facades\DB;
use JsonException;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionPayableSettlementData;
use LBHurtado\XChange\Data\Commercial\ProviderCostEvidenceData;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialProviderCostSettlement;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Services\Commercial\CommercialAccountingJournal;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

final readonly class SettleCommercialProviderCost
{
    public function __construct(
        private TreasuryPositionOperationContract $positionOperations,
        private TreasuryInventoryOperationContract $inventoryOperations,
        private TreasuryProviderConnectionCatalog $connections,
        private CommercialAccountingJournal $journal,
    ) {}

    /**
     * @throws JsonException
     */
    public function execute(
        ProviderCostEvidenceData $evidence,
    ): CommercialProviderCostSettlement {
        $payload = $evidence->toArray();
        $requestHash = hash(
            'sha256',
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );

        return DB::transaction(function () use ($evidence, $requestHash): CommercialProviderCostSettlement {
            $existing = CommercialProviderCostSettlement::query()
                ->where('idempotency_key', $evidence->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CommercialProviderCostSettlement) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw new CommercialSaleConflict(
                        'Provider cost idempotency key was reused with different evidence.',
                    );
                }

                return $existing;
            }

            $sale = CommercialSale::query()
                ->where('reference', $evidence->commercialSaleReference)
                ->lockForUpdate()
                ->firstOrFail();
            $allocation = CommercialAllocation::query()
                ->where('commercial_sale_id', $sale->getKey())
                ->where('category', 'provider_cost')
                ->lockForUpdate()
                ->sole();
            $settled = CommercialProviderCostSettlement::query()
                ->where('commercial_allocation_id', $allocation->getKey())
                ->where('status', 'settled')
                ->lockForUpdate()
                ->first();

            if ($settled instanceof CommercialProviderCostSettlement) {
                throw new CommercialSaleConflict(
                    'Provider cost allocation is already settled.',
                );
            }

            $context = (array) data_get($sale->snapshot, 'accounting_context', []);

            $this->assertEvidenceMatchesSale(
                $evidence,
                $sale,
                $context,
            );
            $connection = collect($this->connections->active([
                $evidence->connectionReference,
            ]))->sole();

            if ($connection->provider !== mb_strtolower($evidence->provider)
                || $connection->currency !== mb_strtoupper($evidence->currency)) {
                throw new CommercialSaleConflict(
                    'Provider cost evidence does not match the active Treasury connection.',
                );
            }

            $expectedAmountMinor = (int) $allocation->amount_minor;
            $varianceAmountMinor = $evidence->observedAmountMinor - $expectedAmountMinor;
            $status = $this->status(
                $evidence,
                $expectedAmountMinor,
            );
            $positionOperationReference = null;
            $inventoryOperationReference = null;

            if ($status === 'settled') {
                $scope = hash('sha256', implode('|', [
                    $sale->reference,
                    $allocation->policy_rule_reference,
                    $evidence->evidenceReference,
                    (string) $evidence->observedAmountMinor,
                ]));
                $positionOperation = $this->positionOperations->settlePayable(
                    new TreasuryPositionPayableSettlementData(
                        operationReference: 'provider-cost-position-settlement:'.$scope,
                        sourcePositionReference: $allocation->destination_position_reference,
                        amountMinor: $evidence->observedAmountMinor,
                        currency: mb_strtoupper($evidence->currency),
                        idempotencyKey: 'provider-cost-position-settlement-key:'.$scope,
                        externalReference: $evidence->evidenceReference,
                        metadata: [
                            'source' => 'x_change_provider_cost_settlement',
                            'commercial_sale_reference' => $sale->reference,
                            'commercial_allocation_id' => (int) $allocation->getKey(),
                            'provider' => mb_strtolower($evidence->provider),
                            'connection_reference' => $evidence->connectionReference,
                            'evidence_type' => $evidence->evidenceType,
                        ],
                    ),
                );
                $inventoryOperation = $this->inventoryOperations->adjust(
                    new TreasuryInventoryAdjustmentData(
                        operationReference: 'provider-cost-inventory-outflow:'.$scope,
                        inventoryReference: $connection->inventoryReference,
                        deltaAmountMinor: -$evidence->observedAmountMinor,
                        currency: mb_strtoupper($evidence->currency),
                        status: 'requested',
                        idempotencyKey: 'provider-cost-inventory-outflow-key:'.$scope,
                        effectiveAt: $evidence->observedAt,
                        externalReference: $evidence->evidenceReference,
                        metadata: [
                            'source' => 'x_change_provider_cost_settlement',
                            'commercial_sale_reference' => $sale->reference,
                            'provider' => mb_strtolower($evidence->provider),
                            'connection_reference' => $evidence->connectionReference,
                            'evidence_type' => $evidence->evidenceType,
                        ],
                    ),
                );
                $positionOperationReference = $positionOperation->operationReference;
                $inventoryOperationReference = $inventoryOperation->operationReference;
            }

            $settlement = CommercialProviderCostSettlement::query()->create([
                'commercial_sale_id' => $sale->getKey(),
                'commercial_allocation_id' => $allocation->getKey(),
                'idempotency_key' => $evidence->idempotencyKey,
                'request_hash' => $requestHash,
                'provider' => mb_strtolower($evidence->provider),
                'connection_reference' => $evidence->connectionReference,
                'evidence_type' => $evidence->evidenceType,
                'evidence_reference' => $evidence->evidenceReference,
                'cash_movement_observed' => $evidence->cashMovementObserved,
                'expected_amount_minor' => $expectedAmountMinor,
                'observed_amount_minor' => $evidence->observedAmountMinor,
                'variance_amount_minor' => $varianceAmountMinor,
                'currency' => mb_strtoupper($evidence->currency),
                'status' => $status,
                'position_operation_reference' => $positionOperationReference,
                'inventory_operation_reference' => $inventoryOperationReference,
                'metadata' => $evidence->metadata,
                'observed_at' => $evidence->observedAt,
                'settled_at' => $status === 'settled' ? now() : null,
            ]);

            $this->journal->recordProviderCostOutcome($settlement);

            return $settlement;
        }, attempts: 5);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function assertEvidenceMatchesSale(
        ProviderCostEvidenceData $evidence,
        CommercialSale $sale,
        array $context,
    ): void {
        $matches = (int) data_get($context, 'schema_version') >= 2
            && data_get($context, 'provider') === mb_strtolower($evidence->provider)
            && data_get($context, 'connection_reference') === $evidence->connectionReference
            && data_get($context, 'currency') === mb_strtoupper($evidence->currency)
            && $sale->currency === mb_strtoupper($evidence->currency);

        if (! $matches) {
            throw new CommercialSaleConflict(
                'Provider cost evidence does not match the immutable commercial accounting context.',
            );
        }
    }

    private function status(
        ProviderCostEvidenceData $evidence,
        int $expectedAmountMinor,
    ): string {
        if (! $evidence->cashMovementObserved) {
            return $evidence->evidenceType === 'invoice'
                ? 'invoice_pending'
                : 'not_observed';
        }

        if ($evidence->observedAmountMinor !== $expectedAmountMinor) {
            return 'review_required';
        }

        return 'settled';
    }
}
