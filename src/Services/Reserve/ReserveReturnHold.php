<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Data\Providers\AccountTransferRequestData;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Models\ReserveTransfer;
use LBHurtado\XChange\Services\Banking\AssignedBankTreasuryConnection;
use LBHurtado\XChange\Services\Banking\SharedBankTreasuryConnection;
use LBHurtado\XChange\Services\CheckProviderSourceAccountReadiness;
use RuntimeException;

/** Holds a terminal Pay Code return in shared escrow before the bank request is sent. */
final readonly class ReserveReturnHold
{
    public function __construct(
        private AssignedBankTreasuryConnection $assigned,
        private SharedBankTreasuryConnection $shared,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private TreasuryPositionOperationContract $positions,
        private CheckProviderSourceAccountReadiness $readiness,
        private DurableReserveTransfer $transfers,
        private ReserveTransferJournal $journal,
    ) {}

    public function hold(ReserveTransfer $return, Model $owner): ReserveTransfer
    {
        $current = $return->fresh();
        if ($current->status === 'held') {
            return $current;
        }
        if ($current->transfer_kind !== 'return' || $current->status !== 'prepared' || ! is_int($current->bank_account_link_id)) {
            throw new RuntimeException('The reserve return cannot be held.');
        }
        $request = AccountTransferRequestData::from($current->request_ciphertext);
        $observation = $this->readiness->handle($current->amount_minor, 'netbank', $request->sourceAccountNumber);
        if (($observation['ready'] ?? false) !== true || ($observation['checked'] ?? false) !== true
            || ($observation['currency'] ?? null) !== $current->currency
            || ! is_int($observation['available_balance_minor'] ?? null)
            || $observation['available_balance_minor'] < $current->amount_minor) {
            throw new RuntimeException('The platform bank account cannot confirm enough available funds for this return.');
        }

        return DB::transaction(function () use ($return, $owner): ReserveTransfer {
            $locked = ReserveTransfer::query()->lockForUpdate()->findOrFail($return->getKey());
            if ($locked->status === 'held') {
                return $locked;
            }
            if ($locked->transfer_kind !== 'return' || $locked->status !== 'prepared'
                || ! is_int($locked->bank_account_link_id)) {
                throw new RuntimeException('The reserve return cannot be held.');
            }
            $this->transfers->assertFrozen($locked);
            $request = AccountTransferRequestData::from($locked->request_ciphertext);
            $shared = $this->shared->resolveIfAccountsAssigned('netbank', $locked->currency);
            $assigned = $this->assigned->resolve($owner, $locked->bank_account_link_id);
            if ($shared === null || $shared->reference !== $locked->source_treasury_connection
                || $assigned->reference !== $locked->reserve_treasury_connection) {
                throw new RuntimeException('The frozen Treasury connections do not match the return.');
            }
            $platformAccount = (string) config('omnipay.gateways.netbank.options.sourceAccountNumber');
            if (preg_replace('/\D+/', '', $platformAccount) === ''
                || preg_replace('/\D+/', '', $platformAccount) !== preg_replace('/\D+/', '', $request->sourceAccountNumber)) {
                throw new RuntimeException('The return source does not match the platform account.');
            }
            $portfolio = $this->portfolios->provision($owner, [$shared->reference]);
            $client = collect($portfolio->positions)->first(fn ($p) => $p->purpose === TreasuryPositionPurpose::ClientFunds);
            $reserve = collect($portfolio->positions)->first(fn ($p) => $p->purpose === TreasuryPositionPurpose::PayCodeReserve);
            if (! $client || ! $reserve) {
                throw new RuntimeException('The shared Treasury positions are unavailable.');
            }
            $scope = hash('sha256', $locked->reference);
            $operation = $this->positions->reserve(new TreasuryPositionReservationData(
                operationReference: 'reserve-transfer-hold:'.$scope,
                sourcePositionReference: $client->positionReference,
                destinationPositionReference: $reserve->positionReference,
                amountMinor: $locked->amount_minor,
                currency: $locked->currency,
                idempotencyKey: 'reserve-transfer-hold-key:'.$scope,
                externalReference: 'reserve-transfer:'.$locked->reference,
                metadata: ['source' => 'reserve_return_hold'],
            ));
            $locked->forceFill([
                'status' => 'held',
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => (string) $owner->getKey(),
                'hold_operation_reference' => $operation->operationReference,
                'held_at' => now(),
                'treasury_binding_ciphertext' => [
                    'source_inventory' => $shared->inventoryReference,
                    'source_resource' => $shared->settlementResourceReference,
                    'reserve_inventory' => $assigned->inventoryReference,
                    'reserve_resource' => $assigned->settlementResourceReference,
                ],
            ])->save();
            $this->journal->recordHold($locked);

            return $locked;
        }, attempts: 3);
    }
}
