<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Data\Providers\AccountTransferRequestData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReleaseData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Models\ReserveTransfer;
use LBHurtado\XChange\Models\VoucherBankSource;
use LBHurtado\XChange\Services\Accounts\AccountLifecycle;
use LBHurtado\XChange\Services\Commercial\CommercialBillingPolicy;
use LBHurtado\XChange\Services\Commercial\CommercialCustomerChargingGuard;
use LBHurtado\XChange\Services\Commercial\PayCodeCommercialSaleService;
use LBHurtado\XChange\Services\Configuration\InstructionCapabilityIssuanceGuard;
use LBHurtado\XChange\Services\Treasury\TreasuryCompatibilityLedgerSynchronizer;
use RuntimeException;

final readonly class IssueReserveBackedPayCode
{
    public function __construct(
        private PayCodeIssuanceContract $issuance,
        private DurableReserveTransfer $transfers,
        private ReserveIssuanceSnapshot $snapshots,
        private AccountBalanceReadModelContract $balances,
        private TreasuryPositionOperationContract $positions,
        private PayCodeCommercialSaleService $sales,
        private CommercialBillingPolicy $billing,
        private AccountLifecycle $lifecycle,
        private InstructionCapabilityIssuanceGuard $capabilities,
        private CommercialCustomerChargingGuard $charging,
        private WalletAccessContract $wallets,
        private TreasuryCompatibilityLedgerSynchronizer $synchronizer,
        private ReserveTransferJournal $journal,
    ) {}

    public function issue(ReserveTransfer $transfer, Model $owner): Voucher
    {
        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('Reserve-backed issuance must own its database transaction.');
        }

        return DB::transaction(function () use ($transfer, $owner): Voucher {
            $locked = ReserveTransfer::query()->lockForUpdate()->findOrFail($transfer->getKey());
            if ($locked->owner_type !== $owner->getMorphClass() || (string) $locked->owner_id !== (string) $owner->getKey()) {
                throw new RuntimeException('The reserve belongs to another account.');
            }
            if ($locked->status === 'issued' && $locked->voucher_id !== null) {
                return Voucher::query()->whereKey($locked->voucher_id)->whereMorphedTo('owner', $owner)->firstOrFail();
            }
            if ($locked->status !== 'treasury_posted' || $locked->voucher_id !== null || ! $locked->treasury_posted_at) {
                throw new RuntimeException('The reserve is not ready for Pay Code issuance.');
            }
            if (! $this->lifecycle->lockAndIsActive($owner)) {
                throw new RuntimeException('The account is suspended.');
            }
            $snapshot = $locked->issuance_snapshot_ciphertext;
            if (! is_array($snapshot) || ! is_array($snapshot['instructions'] ?? null) || ! is_array($snapshot['estimate'] ?? null)) {
                throw new RuntimeException('The frozen issuance is unavailable.');
            }
            $input = $snapshot['instructions'];
            $estimate = new PricingEstimateData(...$snapshot['estimate']);
            $this->capabilities->ensureAvailable($input);
            $this->charging->ensureAuthorized($locked->fee_minor);
            if ($estimate->billing_mode !== $this->billing->mode()->value || ($locked->fee_minor > 0 && (! $this->billing->isBillable() || ! config('x-change.commercial.enabled', true)))) {
                throw new RuntimeException('The accepted billing mode is unavailable.');
            }
            $binding = $locked->treasury_binding_ciphertext;
            $request = AccountTransferRequestData::from($locked->request_ciphertext);
            if (! is_array($binding) || ($binding['reserve_payout_provider'] ?? null) !== $locked->provider
                || ! is_string($binding['reserve_payout_account'] ?? null) || preg_replace('/\D+/', '', $binding['reserve_payout_account']) === ''
                || preg_replace('/\D+/', '', $binding['reserve_payout_account']) !== preg_replace('/\D+/', '', $request->destinationAccountNumber)
                || ! is_string($binding['reserve_payout_customer'] ?? null) || trim($binding['reserve_payout_customer']) === '') {
                throw new RuntimeException('The frozen reserve payout source is invalid.');
            }
            $scope = hash('sha256', $locked->reference);
            $issued = [];
            $voucher = $this->projectReservedPrincipal($locked, $owner, function () use ($locked, $owner, $input, $binding, $scope, &$issued): Voucher {
                $issued = $this->issuance->issue($owner, $input);
                $voucher = Voucher::query()->whereKey($issued['voucher_id'] ?? null)->whereMorphedTo('owner', $owner)->lockForUpdate()->first();
                if (! $voucher || (int) round(((float) ($issued['amount'] ?? 0)) * 100) !== $locked->amount_minor
                    || (int) round(((float) $voucher->instructions->cash->amount) * 100) !== $locked->amount_minor
                    || $voucher->instructions->cash->currency !== $locked->currency
                    || ($issued['currency'] ?? null) !== $locked->currency || ($issued['code'] ?? null) !== $voucher->code) {
                    throw new RuntimeException('The issued Pay Code does not match the reserved instructions.');
                }
                $metadata = is_array($voucher->metadata) ? $voucher->metadata : [];
                if (data_get($metadata, 'treasury.pay_code_reservation') !== null || VoucherBankSource::query()->where('voucher_id', $voucher->getKey())->exists()) {
                    throw new RuntimeException('The Pay Code already has a reserve or payout source.');
                }
                data_set($metadata, 'treasury.pay_code_reservation', [
                    'status' => 'reserved', 'provider' => $locked->provider,
                    'connection_reference' => $locked->reserve_treasury_connection,
                    'operation_reference' => 'reserve-transfer-reserve:'.$scope,
                    'amount_minor' => $locked->amount_minor, 'currency' => $locked->currency,
                    'reserve_transfer_reference' => $locked->reference,
                ]);
                $voucher->forceFill(['metadata' => $metadata])->saveQuietly();
                VoucherBankSource::query()->create(['voucher_id' => $voucher->getKey(), 'source_ciphertext' => [
                    'provider' => $locked->provider, 'account_number' => $binding['reserve_payout_account'],
                    'customer_id' => $binding['reserve_payout_customer'], 'mode' => 'platform_reserve',
                    'treasury_connection_reference' => $locked->reserve_treasury_connection,
                    'treasury_inventory_reference' => $binding['reserve_inventory'],
                    'treasury_resource_reference' => $binding['reserve_resource'],
                ]]);
                $locked->forceFill(['status' => 'issued', 'voucher_id' => $voucher->getKey(), 'issued_at' => now()])->save();

                return $voucher;
            });
            if ($locked->fee_minor > 0) {
                $feeHold = TreasuryPositionOperation::query()->with(['sourcePosition', 'destinationPosition'])
                    ->where('operation_reference', $locked->fee_hold_operation_reference)->firstOrFail();
                $this->positions->release(new TreasuryPositionReleaseData(
                    operationReference: 'reserve-transfer-fee-issuance-release:'.$scope,
                    sourcePositionReference: $feeHold->destinationPosition->position_reference,
                    destinationPositionReference: $feeHold->sourcePosition->position_reference,
                    amountMinor: $locked->fee_minor, currency: $locked->currency,
                    idempotencyKey: 'reserve-transfer-fee-issuance-release-key:'.$scope,
                    externalReference: 'reserve-transfer-fee:'.$locked->reference,
                ));
            }
            if ($this->billing->isBillable()) {
                $sale = $this->sales->post($owner, $input, $issued, $locked->provider, $estimate, $locked->source_treasury_connection);
                if (($sale['commercial_status'] ?? null) !== 'posted' || ($sale['total_minor'] ?? null) !== $locked->fee_minor) {
                    throw new RuntimeException('The commercial sale did not post the frozen issuance fee.');
                }
            }
            $funding = FundingDecisionData::allowed('local_ledger', 0, $locked->amount_minor, $locked->currency,
                meta: ['provider' => $locked->provider, 'topology' => 'ledger_pooled']);
            $this->synchronizer->reconcileAfterIssuance($owner, $this->wallets->resolveForUser($owner), $funding);
            $this->journal->recordIssuance($locked);

            return $voucher;
        }, attempts: 3);
    }

    private function projectReservedPrincipal(ReserveTransfer $transfer, Model $owner, Closure $issue): mixed
    {
        $locked = ReserveTransfer::query()->lockForUpdate()->findOrFail($transfer->getKey());
        if ($locked->status !== 'treasury_posted' || $locked->owner_type !== $owner->getMorphClass()
            || (string) $locked->owner_id !== (string) $owner->getKey() || ! $locked->treasury_posted_at) {
            throw new RuntimeException('A posted reserve belonging to this account is required.');
        }
        $this->transfers->assertFrozen($locked);
        $this->transfers->assertHold($locked);
        $this->snapshots->assertFeeHold($locked);
        $scope = hash('sha256', $locked->reference);
        $reservation = TreasuryPositionOperation::query()->with(['sourcePosition', 'destinationPosition'])
            ->where('operation_reference', 'reserve-transfer-reserve:'.$scope)->first();
        $position = $reservation?->destinationPosition;
        $source = $reservation?->sourcePosition;
        if (! $reservation || ! $position || ! $source || $reservation->operation_type !== TreasuryPositionOperationType::Reservation
            || $reservation->status !== 'committed' || $reservation->amount_minor !== $locked->amount_minor
            || $reservation->currency !== $locked->currency || $reservation->external_reference !== 'reserve-transfer:'.$locked->reference
            || $source->purpose !== TreasuryPositionPurpose::ClientFunds
            || $source->connection_reference !== $locked->reserve_treasury_connection
            || $source->principal_type !== $locked->owner_type || (string) $source->principal_id !== (string) $locked->owner_id
            || $position->purpose !== TreasuryPositionPurpose::PayCodeReserve
            || $position->connection_reference !== $locked->reserve_treasury_connection
            || $position->principal_type !== $locked->owner_type || (string) $position->principal_id !== (string) $locked->owner_id) {
            throw new RuntimeException('The posted Pay Code reserve is invalid.');
        }
        $wallet = $this->wallets->resolveForUser($owner);
        if (! $wallet instanceof Model || ! method_exists($wallet, 'deposit')) {
            throw new RuntimeException('The compatibility wallet is unavailable.');
        }
        $wallet = $wallet::query()->lockForUpdate()->findOrFail($wallet->getKey());
        if ($wallet->getConnection()->getName() !== DB::connection()->getName()) {
            throw new RuntimeException('The compatibility wallet must use the issuance database connection.');
        }
        if (method_exists($this->balances, 'forget')) {
            $this->balances->forget($owner, $locked->currency, $locked->provider);
        }
        $available = $this->balances->providerBalanceMinor($owner, $locked->provider, $locked->currency);
        if ($available === null) {
            throw new RuntimeException('The authoritative Client Funds balance is unavailable.');
        }
        $funding = FundingDecisionData::allowed('local_ledger', $available, $locked->amount_minor, $locked->currency,
            meta: ['provider' => $locked->provider, 'topology' => 'ledger_pooled']);
        $balance = (int) $this->wallets->getBalance($wallet);
        if ($balance > $available) {
            $this->synchronizer->reconcileAfterIssuance($owner, $wallet, $funding);
        } else {
            $this->synchronizer->synchronize($owner, $wallet, $funding);
        }
        $wallet->refresh();
        $before = (int) $this->wallets->getBalance($wallet);
        if ($before !== $available) {
            throw new RuntimeException('The compatibility wallet does not match Client Funds.');
        }
        $locked->forceFill(['status' => 'issuing'])->save();
        $this->synchronizer->projectReservedPrincipal($wallet, $locked->amount_minor, $locked->reference);
        $result = $issue();
        $wallet->refresh();
        $issued = $locked->fresh();
        if ($issued->status !== 'issued' || ! is_int($issued->voucher_id) || $issued->voucher_id <= 0) {
            throw new RuntimeException('The reserve was not bound to a Pay Code.');
        }
        if ((int) $this->wallets->getBalance($wallet) !== $before) {
            throw new RuntimeException('Pay Code issuance did not consume exactly the reserved principal.');
        }

        return $result;
    }
}
