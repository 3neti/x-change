<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Data\Providers\AccountTransferRequestData;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryReclassificationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDerecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Models\ReserveTransfer;
use LBHurtado\XChange\Services\Banking\AssignedBankTreasuryConnection;
use LBHurtado\XChange\Services\Banking\SharedBankTreasuryConnection;
use LBHurtado\XChange\Services\Treasury\TreasuryInventoryRegistrationService;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use RuntimeException;

final readonly class ReserveTransferTreasuryPosting
{
    public function __construct(
        private DurableReserveTransfer $transfers,
        private AssignedBankTreasuryConnection $assigned,
        private SharedBankTreasuryConnection $shared,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private TreasuryProvisioningService $systems,
        private TreasuryInventoryRegistrationService $inventoryRegistration,
        private TreasuryInventoryOperationContract $inventories,
        private TreasuryPositionOperationContract $positions,
        private ReserveTransferJournal $journal,
    ) {}

    public function post(ReserveTransfer $transfer): ReserveTransfer
    {
        return DB::transaction(function () use ($transfer): ReserveTransfer {
            $locked = ReserveTransfer::query()->lockForUpdate()->findOrFail($transfer->getKey());
            if (in_array($locked->status, ['treasury_posted', 'issued'], true)) {
                return $locked;
            }
            if ($locked->status !== 'provider_settled' || ! is_string($locked->provider_transaction_id)
                || ! is_array($locked->bank_evidence_ciphertext) || ! is_array($locked->treasury_binding_ciphertext)
                || ! is_int($locked->bank_account_link_id) || ! ($owner = $locked->owner) instanceof Model) {
                throw new RuntimeException('A verified bank settlement and frozen account are required.');
            }
            $this->transfers->assertFrozen($locked);
            $this->transfers->assertHold($locked);
            app(ReserveIssuanceSnapshot::class)->assertFeeHold($locked);
            $source = $this->assigned->resolve($owner, $locked->bank_account_link_id);
            $reserve = $this->shared->resolveIfAccountsAssigned($locked->provider, $locked->currency);
            $binding = $locked->treasury_binding_ciphertext;
            $request = AccountTransferRequestData::from($locked->request_ciphertext);
            if ($reserve === null || $source->reference !== $locked->source_treasury_connection
                || $reserve->reference !== $locked->reserve_treasury_connection
                || ($binding['source_inventory'] ?? null) !== $source->inventoryReference
                || ($binding['source_resource'] ?? null) !== $source->settlementResourceReference
                || ($binding['reserve_inventory'] ?? null) !== $reserve->inventoryReference
                || ($binding['reserve_resource'] ?? null) !== $reserve->settlementResourceReference
                || ($binding['reserve_payout_provider'] ?? null) !== $locked->provider
                || ! is_string($binding['reserve_payout_account'] ?? null)
                || preg_replace('/\D+/', '', $binding['reserve_payout_account']) === ''
                || preg_replace('/\D+/', '', $binding['reserve_payout_account']) !== preg_replace('/\D+/', '', $request->destinationAccountNumber)
                || ! is_string($binding['reserve_payout_customer'] ?? null)
                || trim($binding['reserve_payout_customer']) === '') {
                throw new RuntimeException('The frozen Treasury binding has changed.');
            }
            $hold = TreasuryPositionOperation::query()->with('destinationPosition')->where('operation_reference', $locked->hold_operation_reference)->firstOrFail();
            $sourceReserve = $hold->destinationPosition->position_reference;
            $portfolio = $this->portfolios->provision($owner, [$reserve->reference]);
            $system = $this->systems->provision([$reserve->reference]);
            $client = collect($portfolio->positions)->first(fn ($p) => $p->purpose === TreasuryPositionPurpose::ClientFunds);
            $reserved = collect($portfolio->positions)->first(fn ($p) => $p->purpose === TreasuryPositionPurpose::PayCodeReserve);
            $clearing = collect($system->positions)->first(fn ($p) => $p->purpose === TreasuryPositionPurpose::TreasuryClearing);
            if (! $client || ! $reserved || ! $clearing) {
                throw new RuntimeException('The reserve Treasury positions are unavailable.');
            }
            $this->inventoryRegistration->ensure(new TreasuryInventoryData(
                inventoryReference: $reserve->inventoryReference, resourceType: $reserve->settlementResourceType,
                currency: $locked->currency, capacityMinor: 0, status: 'requested',
                idempotencyKey: 'register:'.$reserve->inventoryReference, externalReference: $reserve->settlementResourceReference,
            ));
            $scope = hash('sha256', $locked->reference);
            $external = 'reserve-transfer:'.$locked->reference;
            $this->inventories->reclassify(new TreasuryInventoryReclassificationData(
                operationReference: 'reserve-transfer-inventory:'.$scope, sourceInventoryReference: $source->inventoryReference,
                destinationInventoryReference: $reserve->inventoryReference, amountMinor: $locked->amount_minor,
                currency: $locked->currency, status: 'requested', idempotencyKey: 'reserve-transfer-inventory-key:'.$scope,
                externalReference: $external,
            ));
            $this->positions->derecognize(new TreasuryPositionDerecognitionData(
                operationReference: 'reserve-transfer-out:'.$scope, sourcePositionReference: $sourceReserve,
                amountMinor: $locked->amount_minor, currency: $locked->currency, idempotencyKey: 'reserve-transfer-out-key:'.$scope,
                externalReference: $external,
            ));
            $this->positions->recognize(new TreasuryPositionRecognitionData(
                operationReference: 'reserve-transfer-in:'.$scope, destinationPositionReference: $clearing->positionReference,
                amountMinor: $locked->amount_minor, currency: $locked->currency, idempotencyKey: 'reserve-transfer-in-key:'.$scope,
                externalReference: $external,
            ));
            $this->positions->allocate(new TreasuryPositionAllocationData(
                operationReference: 'reserve-transfer-allocate:'.$scope, sourcePositionReference: $clearing->positionReference,
                destinationPositionReference: $client->positionReference, amountMinor: $locked->amount_minor,
                currency: $locked->currency, idempotencyKey: 'reserve-transfer-allocate-key:'.$scope, externalReference: $external,
            ));
            $this->positions->reserve(new TreasuryPositionReservationData(
                operationReference: 'reserve-transfer-reserve:'.$scope, sourcePositionReference: $client->positionReference,
                destinationPositionReference: $reserved->positionReference, amountMinor: $locked->amount_minor,
                currency: $locked->currency, idempotencyKey: 'reserve-transfer-reserve-key:'.$scope, externalReference: $external,
            ));
            $locked->forceFill(['status' => 'treasury_posted', 'treasury_posted_at' => now()])->save();
            $this->journal->recordTreasuryPosting($locked);

            return $locked;
        }, attempts: 3);
    }
}
