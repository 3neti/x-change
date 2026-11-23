<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Data\Providers\AccountTransferRequestData;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\BankingAccountSelectorContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Models\ReserveTransfer;
use LBHurtado\XChange\Services\Banking\AssignedBankTreasuryConnection;
use LBHurtado\XChange\Services\Banking\SharedBankTreasuryConnection;
use LBHurtado\XChange\Services\CheckProviderSourceAccountReadiness;
use RuntimeException;

final readonly class ReserveTransferHold
{
    public function __construct(
        private BankingAccountSelectorContract $selector,
        private AssignedBankTreasuryConnection $assigned,
        private SharedBankTreasuryConnection $shared,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private TreasuryPositionOperationContract $positions,
        private CheckProviderSourceAccountReadiness $readiness,
        private DurableReserveTransfer $transfers,
        private ReserveTransferJournal $journal,
    ) {}

    public function hold(ReserveTransfer $transfer, Model $owner, int $linkId): ReserveTransfer
    {
        $current = $transfer->fresh();
        if ($current->status === 'held' && $current->owner_type === $owner->getMorphClass()
            && (string) $current->owner_id === (string) $owner->getKey() && $current->bank_account_link_id === $linkId) {
            return $current;
        }
        if ($current->status !== 'prepared' || $current->provider !== 'netbank') {
            throw new RuntimeException('The transfer cannot be held.');
        }
        $this->selector->select($owner, 'netbank', 'disbursement', $linkId);
        $assignment = BankAccountAssignment::query()->whereMorphedTo('owner', $owner)
            ->where('provider_account_link_id', $linkId)->first();
        $details = $assignment?->details_ciphertext;
        $account = is_array($details) ? ($details['provider_account_number'] ?? $details['account_number'] ?? null) : null;
        if (! is_string($account) || trim($account) === '') {
            throw new RuntimeException('The selected source account is unavailable.');
        }
        $observation = $this->readiness->handle($current->amount_minor, 'netbank', $account);
        if (($observation['ready'] ?? false) !== true || ($observation['checked'] ?? false) !== true
            || ($observation['currency'] ?? null) !== $current->currency
            || ! is_int($observation['available_balance_minor'] ?? null)
            || $observation['available_balance_minor'] < $current->amount_minor) {
            throw new RuntimeException('The bank cannot confirm enough available funds for this transfer.');
        }

        return DB::transaction(function () use ($transfer, $owner, $linkId, $account): ReserveTransfer {
            $locked = ReserveTransfer::query()->lockForUpdate()->findOrFail($transfer->getKey());
            if ($locked->status === 'held' && $locked->owner_type === $owner->getMorphClass()
                && (string) $locked->owner_id === (string) $owner->getKey() && $locked->bank_account_link_id === $linkId) {
                return $locked;
            }
            if ($locked->status !== 'prepared' || $locked->provider !== 'netbank') {
                throw new RuntimeException('The transfer cannot be held.');
            }
            $this->transfers->assertFrozen($locked);
            $this->selector->select($owner, 'netbank', 'disbursement', $linkId);
            $assignment = BankAccountAssignment::query()->whereMorphedTo('owner', $owner)
                ->where('provider_account_link_id', $linkId)->lockForUpdate()->first();
            $request = AccountTransferRequestData::from($locked->request_ciphertext);
            $details = $assignment?->details_ciphertext;
            $lockedAccount = is_array($details) ? ($details['provider_account_number'] ?? $details['account_number'] ?? '') : '';
            if (! is_string($lockedAccount) || ! hash_equals($account, $lockedAccount)) {
                throw new RuntimeException('The selected source account changed while being checked.');
            }
            $customer = is_array($details) ? ($details['customer_id'] ?? '') : '';
            $platformAccount = (string) config('omnipay.gateways.netbank.options.sourceAccountNumber', '');
            $platformFundingAccount = (string) config('payment-gateway.netbank.funding.corporate_account_number', '');
            $platformCustomer = (string) config('omnipay.gateways.netbank.options.senderCustomerId', '');
            if (! $assignment || ! is_string($account) || ! is_string($customer)
                || preg_replace('/\D+/', '', $account) === '' || preg_replace('/\D+/', '', $platformAccount) === ''
                || trim($platformCustomer) === ''
                || preg_replace('/\D+/', '', $platformAccount) !== preg_replace('/\D+/', '', $platformFundingAccount)
                || preg_replace('/\D+/', '', $request->sourceAccountNumber) !== preg_replace('/\D+/', '', $account)
                || preg_replace('/\D+/', '', $request->destinationAccountNumber) !== preg_replace('/\D+/', '', $platformAccount)
                || ! hash_equals($customer, $request->sourceCustomerId)) {
                throw new RuntimeException('The transfer does not match the assigned account and platform reserve.');
            }
            $source = $this->assigned->resolve($owner, $linkId);
            $reserve = $this->shared->resolveIfAccountsAssigned('netbank', $locked->currency);
            if ($reserve === null || $source->reference !== $locked->source_treasury_connection
                || $reserve->reference !== $locked->reserve_treasury_connection) {
                throw new RuntimeException('The frozen Treasury connections do not match.');
            }
            $portfolio = $this->portfolios->provision($owner, [$source->reference]);
            $client = collect($portfolio->positions)->first(fn ($p) => $p->purpose === TreasuryPositionPurpose::ClientFunds);
            $hold = collect($portfolio->positions)->first(fn ($p) => $p->purpose === TreasuryPositionPurpose::PayCodeReserve);
            if (! $client || ! $hold) {
                throw new RuntimeException('The owner Treasury positions are unavailable.');
            }
            $scope = hash('sha256', $locked->reference);
            $operation = $this->positions->reserve(new TreasuryPositionReservationData(
                operationReference: 'reserve-transfer-hold:'.$scope,
                sourcePositionReference: $client->positionReference,
                destinationPositionReference: $hold->positionReference,
                amountMinor: $locked->amount_minor,
                currency: $locked->currency,
                idempotencyKey: 'reserve-transfer-hold-key:'.$scope,
                externalReference: 'reserve-transfer:'.$locked->reference,
                metadata: ['source' => 'reserve_transfer_hold'],
            ));
            $locked->forceFill(['status' => 'held', 'owner_type' => $owner->getMorphClass(), 'owner_id' => (string) $owner->getKey(),
                'bank_account_link_id' => $linkId, 'hold_operation_reference' => $operation->operationReference, 'held_at' => now(),
                'treasury_binding_ciphertext' => [
                    'source_inventory' => $source->inventoryReference, 'source_resource' => $source->settlementResourceReference,
                    'reserve_inventory' => $reserve->inventoryReference, 'reserve_resource' => $reserve->settlementResourceReference,
                    'reserve_payout_provider' => 'netbank', 'reserve_payout_account' => $platformAccount,
                    'reserve_payout_customer' => $platformCustomer,
                ]])->save();
            $this->journal->recordHold($locked);

            return $locked;
        }, attempts: 3);
    }
}
