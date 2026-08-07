<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Commercial;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionPayableSettlementData;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\PartnerCommissionPayoutBatchStatus;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;
use LBHurtado\XChange\Services\Commercial\CommercialAccountingJournal;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use Throwable;

final readonly class ReconcilePartnerCommissionPayoutBatch
{
    public function __construct(
        private CommercialOperatorAuthorityContract $authority,
        private SystemUserResolverContract $systemPrincipal,
        private PayoutProvider $payouts,
        private TreasuryPositionOperationContract $positionOperations,
        private TreasuryInventoryOperationContract $inventoryOperations,
        private TreasuryProviderConnectionCatalog $connections,
        private CommercialAccountingJournal $journal,
    ) {}

    public function execute(Model $operator, PartnerCommissionPayoutBatch $batch): PartnerCommissionPayoutBatch
    {
        $this->authorize($operator);

        return $this->reconcile(
            $batch,
            $operator->getMorphClass().':'.$operator->getKey(),
            'commercial_payout_executor',
        );
    }

    public function executeAutomatically(PartnerCommissionPayoutBatch $batch): PartnerCommissionPayoutBatch
    {
        return $this->reconcile(
            $batch,
            'x-change',
            'commercial_payout_reconciliation',
        );
    }

    private function reconcile(
        PartnerCommissionPayoutBatch $batch,
        string $actorId,
        string $actorType,
    ): PartnerCommissionPayoutBatch {
        $batch = $batch->fresh();

        if ($batch->status === PartnerCommissionPayoutBatchStatus::Settled
            || $batch->status === PartnerCommissionPayoutBatchStatus::Rejected) {
            return $batch;
        }

        if ($batch->status !== PartnerCommissionPayoutBatchStatus::Pending
            || $batch->provider_transaction_id === null) {
            throw new CommercialSaleConflict('Commission payout has no provider transaction to reconcile.');
        }

        try {
            $result = $this->payouts->checkStatus($batch->provider_transaction_id);
        } catch (Throwable $exception) {
            $batch->forceFill([
                'metadata' => [
                    ...$batch->metadata,
                    'last_reconciliation_failure' => [
                        'exception' => $exception::class,
                        'recorded_at' => now()->toIso8601String(),
                    ],
                ],
            ])->save();

            $batch = $batch->refresh();
            $this->journal->recordPartnerPayoutBatch(
                $batch,
                $actorId,
                $actorType,
            );

            return $batch;
        }

        if ($result->status === PayoutStatus::FAILED) {
            $batch->forceFill([
                'status' => PartnerCommissionPayoutBatchStatus::Rejected,
                'rejected_at' => now(),
                'metadata' => [
                    ...$batch->metadata,
                    'provider_reconciliation' => [
                        'status' => $result->status->value,
                        'reason' => data_get($result->metadata, 'rejection_reason'),
                        'recorded_at' => now()->toIso8601String(),
                    ],
                ],
            ])->save();

            $batch = $batch->refresh();
            $this->journal->recordPartnerPayoutBatch(
                $batch,
                $actorId,
                $actorType,
            );

            return $batch;
        }

        if ($result->status !== PayoutStatus::COMPLETED) {
            return $batch;
        }

        return DB::transaction(function () use ($actorId, $actorType, $batch, $result): PartnerCommissionPayoutBatch {
            $locked = PartnerCommissionPayoutBatch::query()->lockForUpdate()->findOrFail($batch->getKey());

            if ($locked->status === PartnerCommissionPayoutBatchStatus::Settled) {
                return $locked;
            }

            if ($locked->status !== PartnerCommissionPayoutBatchStatus::Pending) {
                throw new CommercialSaleConflict('Commission payout changed while provider status was checked.');
            }

            $connection = collect($this->connections->active([$locked->connection_reference]))->sole();

            if ($connection->provider !== $locked->provider || $connection->currency !== $locked->currency) {
                throw new CommercialSaleConflict('Commission payout does not match the active Treasury connection.');
            }

            $scope = hash('sha256', $locked->reference.'|'.$result->transaction_id.'|'.$locked->amount_minor);
            $positionOperation = $this->positionOperations->settlePayable(new TreasuryPositionPayableSettlementData(
                operationReference: 'partner-commission-batch-position-settlement:'.$scope,
                sourcePositionReference: $locked->position_reference,
                amountMinor: $locked->amount_minor,
                currency: $locked->currency,
                idempotencyKey: 'partner-commission-batch-position-settlement-key:'.$scope,
                externalReference: $result->transaction_id,
                metadata: [
                    'source' => 'x_change_partner_commission_batch',
                    'batch_reference' => $locked->reference,
                    'partner_reference' => $locked->partner_reference,
                ],
            ));
            $inventoryOperation = $this->inventoryOperations->adjust(new TreasuryInventoryAdjustmentData(
                operationReference: 'partner-commission-batch-inventory-outflow:'.$scope,
                inventoryReference: $connection->inventoryReference,
                deltaAmountMinor: -$locked->amount_minor,
                currency: $locked->currency,
                status: 'requested',
                idempotencyKey: 'partner-commission-batch-inventory-outflow-key:'.$scope,
                effectiveAt: now()->toIso8601String(),
                externalReference: $result->transaction_id,
                metadata: [
                    'source' => 'x_change_partner_commission_batch',
                    'batch_reference' => $locked->reference,
                    'partner_reference' => $locked->partner_reference,
                ],
            ));

            $locked->forceFill([
                'status' => PartnerCommissionPayoutBatchStatus::Settled,
                'evidence_reference' => $result->transaction_id,
                'position_operation_reference' => $positionOperation->operationReference,
                'inventory_operation_reference' => $inventoryOperation->operationReference,
                'settled_at' => now(),
            ])->save();

            $settled = $locked->refresh();
            $this->journal->recordPartnerPayoutBatch(
                $settled,
                $actorId,
                $actorType,
            );

            return $settled;
        }, attempts: 5);
    }

    private function authorize(Model $operator): void
    {
        if ($operator->is($this->systemPrincipal->resolve())
            || ! $this->authority->allows($operator, CommercialOperatorCapability::ExecuteCommissionPayouts)) {
            throw new AuthorizationException('Operator lacks commission payout execution authority.');
        }
    }
}
