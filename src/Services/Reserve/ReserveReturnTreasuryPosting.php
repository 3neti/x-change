<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryReclassificationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDerecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Models\ReserveTransfer;
use LBHurtado\XChange\Services\Banking\AssignedBankTreasuryConnection;
use LBHurtado\XChange\Services\Banking\SharedBankTreasuryConnection;
use LBHurtado\XChange\Services\Treasury\TreasuryCompatibilityLedgerSynchronizer;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use RuntimeException;

/** Posts a provider-settled terminal return from shared escrow to its originating account. */
final readonly class ReserveReturnTreasuryPosting
{
    public function __construct(
        private DurableReserveTransfer $transfers,
        private SharedBankTreasuryConnection $shared,
        private AssignedBankTreasuryConnection $assigned,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private TreasuryProvisioningService $systems,
        private TreasuryInventoryOperationContract $inventories,
        private TreasuryPositionOperationContract $positions,
        private AccountBalanceReadModelContract $balances,
        private WalletAccessContract $wallets,
        private TreasuryCompatibilityLedgerSynchronizer $synchronizer,
        private ReserveTransferJournal $journal,
    ) {}

    public function post(ReserveTransfer $return): ReserveTransfer
    {
        return DB::transaction(function () use ($return): ReserveTransfer {
            $locked = ReserveTransfer::query()->lockForUpdate()->findOrFail($return->getKey());
            if ($locked->status === 'returned') {
                return $locked;
            }
            $owner = $locked->owner;
            if ($locked->transfer_kind !== 'return' || $locked->status !== 'provider_settled'
                || ! $owner instanceof Model || ! is_int($locked->bank_account_link_id)
                || ! is_array($locked->treasury_binding_ciphertext)) {
                throw new RuntimeException('A settled terminal reserve return is required.');
            }
            $this->transfers->assertFrozen($locked);
            $this->transfers->assertHold($locked);
            $shared = $this->shared->resolveIfAccountsAssigned($locked->provider, $locked->currency);
            $assigned = $this->assigned->resolve($owner, $locked->bank_account_link_id);
            $binding = $locked->treasury_binding_ciphertext;
            if ($shared === null || $shared->reference !== $locked->source_treasury_connection
                || $assigned->reference !== $locked->reserve_treasury_connection
                || ($binding['source_inventory'] ?? null) !== $shared->inventoryReference
                || ($binding['reserve_inventory'] ?? null) !== $assigned->inventoryReference) {
                throw new RuntimeException('The frozen terminal return Treasury binding has changed.');
            }
            $hold = TreasuryPositionOperation::query()->with('destinationPosition')
                ->where('operation_reference', $locked->hold_operation_reference)
                ->firstOrFail();
            $sourceReserve = $hold->destinationPosition?->position_reference;
            $portfolio = $this->portfolios->provision($owner, [$assigned->reference]);
            $system = $this->systems->provision([$assigned->reference]);
            $client = collect($portfolio->positions)->first(fn ($p) => $p->purpose === TreasuryPositionPurpose::ClientFunds);
            $clearing = collect($system->positions)->first(fn ($p) => $p->purpose === TreasuryPositionPurpose::TreasuryClearing);
            if (! is_string($sourceReserve) || ! $client || ! $clearing) {
                throw new RuntimeException('The terminal return Treasury positions are unavailable.');
            }
            $scope = hash('sha256', $locked->reference);
            $external = 'reserve-transfer:'.$locked->reference;
            $this->inventories->reclassify(new TreasuryInventoryReclassificationData(
                operationReference: 'reserve-return-inventory:'.$scope,
                sourceInventoryReference: $shared->inventoryReference,
                destinationInventoryReference: $assigned->inventoryReference,
                amountMinor: $locked->amount_minor,
                currency: $locked->currency,
                status: 'requested',
                idempotencyKey: 'reserve-return-inventory-key:'.$scope,
                externalReference: $external,
            ));
            $this->positions->derecognize(new TreasuryPositionDerecognitionData(
                operationReference: 'reserve-return-out:'.$scope,
                sourcePositionReference: $sourceReserve,
                amountMinor: $locked->amount_minor,
                currency: $locked->currency,
                idempotencyKey: 'reserve-return-out-key:'.$scope,
                externalReference: $external,
            ));
            $this->positions->recognize(new TreasuryPositionRecognitionData(
                operationReference: 'reserve-return-in:'.$scope,
                destinationPositionReference: $clearing->positionReference,
                amountMinor: $locked->amount_minor,
                currency: $locked->currency,
                idempotencyKey: 'reserve-return-in-key:'.$scope,
                externalReference: $external,
            ));
            $this->positions->allocate(new TreasuryPositionAllocationData(
                operationReference: 'reserve-return-allocate:'.$scope,
                sourcePositionReference: $clearing->positionReference,
                destinationPositionReference: $client->positionReference,
                amountMinor: $locked->amount_minor,
                currency: $locked->currency,
                idempotencyKey: 'reserve-return-allocate-key:'.$scope,
                externalReference: $external,
            ));
            $wallet = $this->wallets->resolveForUser($owner);
            if (! $wallet instanceof Model || ! method_exists($wallet, 'deposit')) {
                throw new RuntimeException('The compatibility wallet is unavailable for the terminal return.');
            }
            $wallet = $wallet::query()->lockForUpdate()->findOrFail($wallet->getKey());
            if (method_exists($this->balances, 'forget')) {
                $this->balances->forget($owner, $locked->currency, $locked->provider);
            }
            $available = $this->balances->providerBalanceMinor($owner, $locked->provider, $locked->currency);
            if ($available === null) {
                throw new RuntimeException('The authoritative Client Funds balance is unavailable for the terminal return.');
            }
            $funding = FundingDecisionData::allowed(
                'local_ledger',
                $available,
                $locked->amount_minor,
                $locked->currency,
                meta: ['provider' => $locked->provider, 'topology' => 'ledger_pooled'],
            );
            if ((int) $this->wallets->getBalance($wallet) > $available) {
                $this->synchronizer->reconcileAfterIssuance($owner, $wallet, $funding);
            } else {
                $this->synchronizer->synchronize($owner, $wallet, $funding);
            }
            $locked->forceFill(['status' => 'returned', 'treasury_posted_at' => now()])->save();
            $this->journal->recordTreasuryPosting($locked);

            return $locked;
        }, attempts: 3);
    }
}
