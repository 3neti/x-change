<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Commercial;

use Illuminate\Support\Facades\DB;
use JsonException;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionPayableSettlementData;
use LBHurtado\XChange\Data\Commercial\PartnerCommissionPayoutEvidenceData;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\PartnerCommissionPayout;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

final readonly class SettlePartnerCommissionPayout
{
    public function __construct(
        private TreasuryPositionOperationContract $positionOperations,
        private TreasuryInventoryOperationContract $inventoryOperations,
        private TreasuryProviderConnectionCatalog $connections,
    ) {}

    /**
     * @throws JsonException
     */
    public function execute(
        PartnerCommissionPayout $payout,
        PartnerCommissionPayoutEvidenceData $evidence,
    ): PartnerCommissionPayout {
        $settlementHash = hash(
            'sha256',
            json_encode($evidence->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );

        return DB::transaction(function () use (
            $evidence,
            $payout,
            $settlementHash,
        ): PartnerCommissionPayout {
            $locked = PartnerCommissionPayout::query()
                ->whereKey($payout->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'settled') {
                if ($locked->settlement_idempotency_key !== $evidence->idempotencyKey
                    || ! hash_equals((string) $locked->settlement_hash, $settlementHash)) {
                    throw new CommercialSaleConflict(
                        'Partner commission settlement was replayed with different evidence.',
                    );
                }

                return $locked;
            }

            if ($locked->status !== 'approved') {
                throw new CommercialSaleConflict(
                    'Partner commission payout requires independent approval before settlement.',
                );
            }

            if ($locked->provider !== mb_strtolower($evidence->provider)
                || $locked->connection_reference !== $evidence->connectionReference
                || $locked->currency !== mb_strtoupper($evidence->currency)
                || $locked->amount_minor !== $evidence->amountMinor) {
                throw new CommercialSaleConflict(
                    'Partner commission payout evidence does not match the approved payable.',
                );
            }

            $connection = collect($this->connections->active([
                $locked->connection_reference,
            ]))->sole();

            if ($connection->provider !== $locked->provider
                || $connection->currency !== $locked->currency) {
                throw new CommercialSaleConflict(
                    'Partner commission payout does not match the active Treasury connection.',
                );
            }

            $scope = hash('sha256', implode('|', [
                (string) $locked->getKey(),
                $evidence->evidenceReference,
                (string) $evidence->amountMinor,
            ]));
            $positionOperation = $this->positionOperations->settlePayable(
                new TreasuryPositionPayableSettlementData(
                    operationReference: 'partner-commission-position-settlement:'.$scope,
                    sourcePositionReference: $locked->position_reference,
                    amountMinor: $evidence->amountMinor,
                    currency: mb_strtoupper($evidence->currency),
                    idempotencyKey: 'partner-commission-position-settlement-key:'.$scope,
                    externalReference: $evidence->evidenceReference,
                    metadata: [
                        'source' => 'x_change_partner_commission_payout',
                        'partner_reference' => $locked->partner_reference,
                        'commercial_sale_id' => (int) $locked->commercial_sale_id,
                    ],
                ),
            );
            $inventoryOperation = $this->inventoryOperations->adjust(
                new TreasuryInventoryAdjustmentData(
                    operationReference: 'partner-commission-inventory-outflow:'.$scope,
                    inventoryReference: $connection->inventoryReference,
                    deltaAmountMinor: -$evidence->amountMinor,
                    currency: mb_strtoupper($evidence->currency),
                    status: 'requested',
                    idempotencyKey: 'partner-commission-inventory-outflow-key:'.$scope,
                    effectiveAt: $evidence->observedAt,
                    externalReference: $evidence->evidenceReference,
                    metadata: [
                        'source' => 'x_change_partner_commission_payout',
                        'partner_reference' => $locked->partner_reference,
                        'commercial_sale_id' => (int) $locked->commercial_sale_id,
                    ],
                ),
            );

            PartnerCommissionPayout::query()
                ->whereKey($locked->getKey())
                ->update([
                    'status' => 'settled',
                    'settlement_idempotency_key' => $evidence->idempotencyKey,
                    'settlement_hash' => $settlementHash,
                    'evidence_reference' => $evidence->evidenceReference,
                    'position_operation_reference' => $positionOperation->operationReference,
                    'inventory_operation_reference' => $inventoryOperation->operationReference,
                    'settled_at' => now(),
                    'updated_at' => now(),
                ]);

            return $locked->fresh();
        }, attempts: 5);
    }
}
