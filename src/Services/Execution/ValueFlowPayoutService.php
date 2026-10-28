<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Bavix\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDerecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionPayoutRecoveryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\SettlementRailCapabilityRegistryContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Jobs\SubmitValueFlowPayout;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\DisbursementRejectionTrustService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use RuntimeException;
use Throwable;

final readonly class ValueFlowPayoutService
{
    public function __construct(
        private VoucherValueFlowPolicy $flows,
        private ValueFlowAddressResolver $addresses,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private TreasuryPositionReadModelContract $positions,
        private TreasuryPositionOperationContract $operations,
        private TreasuryInventoryOperationContract $inventory,
        private TreasuryProviderConnectionCatalog $connections,
        private ProviderRuntimeSettingsResolverContract $runtime,
        private PayoutProvider $provider,
        private DisbursementStatusResolverContract $statuses,
        private DisbursementRejectionTrustService $rejectionTrust,
        private SettlementRailCapabilityRegistryContract $rails,
    ) {}

    public function isExternal(Voucher $voucher): bool
    {
        return str_starts_with($this->flows->destination($voucher) ?? '', 'instapay.');
    }

    /** The issuer's position is a transient bridge; its credit is reserved in the same transaction. */
    public function bridge(Voucher $voucher, string $connection, string $currency): TreasuryPositionData
    {
        $this->assertSharedConnection($voucher);
        if (! $voucher->owner instanceof Model) {
            throw new RuntimeException('The voucher has no issuing Account.');
        }
        $portfolio = $this->portfolios->provision($voucher->owner, [$connection]);

        return $this->position($portfolio->positions, $connection, $currency, TreasuryPositionPurpose::ClientFunds);
    }

    /** Must be called in the transaction that credits the bridge position. */
    public function prepare(Voucher $voucher, TreasuryPositionData $source, int $amount, string $identity): DisbursementReconciliation
    {
        if (DB::transactionLevel() === 0 || $amount <= 0 || $identity === '') {
            throw new RuntimeException('Value flow payout requires an atomic funding transaction and a stable identity.');
        }

        $this->assertSharedConnection($voucher);
        Voucher::query()->whereKey($voucher->getKey())->lockForUpdate()->firstOrFail();
        $address = $this->flows->destination($voucher);
        $destination = $this->addresses->instapayDestination($address ?? '');
        $connection = collect($this->connections->active([$source->connectionReference]))->sole();
        if ($connection->currency !== $source->currency || $connection->provider !== $this->runtime->provider()) {
            throw new RuntimeException('The value flow payout provider must match the funding connection.');
        }

        $reference = 'vf-'.hash('sha256', $voucher->getKey().'|'.$identity);
        $fingerprint = hash('sha256', json_encode([$address, $amount, $source->positionReference, $source->currency], JSON_THROW_ON_ERROR));
        $existing = DisbursementReconciliation::query()
            ->where('voucher_id', $voucher->getKey())->where('claim_type', 'value_flow')
            ->where('provider_reference', $reference)->first();
        if ($existing !== null) {
            if (! hash_equals((string) data_get($existing->meta, 'fingerprint'), $fingerprint)) {
                throw new RuntimeException('Value flow payout identity conflicts with its original instruction.');
            }

            return $existing;
        }

        $this->rails->assertSupports(new PayoutRequestData(
            reference: $reference, amount: $amount / 100, account_number: $destination->accountNumber,
            bank_code: $destination->bankCode, settlement_rail: 'INSTAPAY', currency: $source->currency,
        ));
        $positions = $this->positions->forPrincipal($source->principalReference);
        $reserve = $this->position($positions, $source->connectionReference, $source->currency, TreasuryPositionPurpose::PayCodeReserve);
        $recovery = $this->position($positions, $source->connectionReference, $source->currency, TreasuryPositionPurpose::BeneficiaryPayoutPayable);
        $this->operations->reserve(new TreasuryPositionReservationData(
            operationReference: $reference.':reserve',
            sourcePositionReference: $source->positionReference,
            destinationPositionReference: $reserve->positionReference,
            amountMinor: $amount,
            currency: $source->currency,
            idempotencyKey: $reference.':reserve',
            externalReference: $identity,
            metadata: ['source' => 'x_change_value_flow', 'voucher_id' => $voucher->getKey()],
        ));
        $payout = DisbursementReconciliation::query()->create([
            'voucher_id' => $voucher->getKey(), 'voucher_code' => $voucher->code,
            'claim_type' => 'value_flow', 'provider' => $connection->provider,
            'provider_reference' => $reference, 'amount' => $amount / 100, 'currency' => $source->currency,
            'bank_code' => $destination->bankCode,
            'account_number_masked' => str_repeat('*', max(0, strlen($destination->accountNumber) - 4)).substr($destination->accountNumber, -4),
            'settlement_rail' => 'INSTAPAY', 'status' => 'intent', 'internal_status' => 'reserved',
            'attempt_count' => 0,
            'meta' => [
                'flow' => 'value_flow', 'identity' => $identity, 'fingerprint' => $fingerprint,
                'account_ciphertext' => Crypt::encryptString($destination->accountNumber),
                'amount_minor' => $amount, 'reserve_position' => $reserve->positionReference,
                'recovery_position' => $recovery->positionReference,
                'connection_reference' => $connection->reference, 'inventory_reference' => $connection->inventoryReference,
            ],
        ]);
        DB::afterCommit(function () use ($payout): void {
            try {
                SubmitValueFlowPayout::dispatch((int) $payout->getKey());
            } catch (Throwable $exception) {
                report($exception);
            }
        });

        return $payout;
    }

    public function submit(int $id): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('Provider submission must run after the funding transaction commits.');
        }

        $request = DB::transaction(function () use ($id): ?PayoutRequestData {
            $row = DisbursementReconciliation::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($row->claim_type !== 'value_flow' || $row->status !== 'intent') {
                return null;
            }
            if ($row->provider !== $this->runtime->provider()) {
                throw new RuntimeException('The configured payout provider differs from the reserved payout.');
            }
            $request = new PayoutRequestData(
                reference: $row->provider_reference, amount: (int) data_get($row->meta, 'amount_minor') / 100,
                account_number: Crypt::decryptString(data_get($row->meta, 'account_ciphertext')),
                bank_code: $row->bank_code, settlement_rail: $row->settlement_rail, currency: $row->currency,
                external_id: (string) $row->voucher_id, external_code: $row->voucher_code,
            );
            $this->rails->assertSupports($request);
            $row->forceFill(['status' => 'unknown', 'attempt_count' => 1, 'attempted_at' => now(),
                'needs_review' => true, 'review_reason' => 'Provider submission outcome awaiting evidence.'])->save();

            return $request;
        });
        if ($request === null) {
            return;
        }

        try {
            $response = $this->provider->disburse($request);
        } catch (Throwable) {
            return;
        }

        DB::transaction(function () use ($id, $response): void {
            $row = DisbursementReconciliation::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $status = $this->statuses->resolveFromGatewayResponse($response);
            $providerMatches = $response->provider === $row->provider;
            if (! $providerMatches || ($status === 'succeeded' && blank($response->transaction_id))) {
                $status = 'unknown';
            }
            $meta = $row->meta;
            $meta['provider_response'] = ['received' => $providerMatches, 'status' => $status];
            $row->forceFill([
                'status' => $status, 'provider_transaction_id' => $providerMatches ? $response->transaction_id : null,
                'transaction_uuid' => $response->uuid,
                'needs_review' => $status === 'unknown',
                'review_reason' => $status === 'unknown' ? 'Provider outcome requires reconciliation.' : null,
                'completed_at' => in_array($status, ['succeeded', 'failed'], true) ? now() : null,
                'meta' => $meta,
            ])->save();
        });
        $this->converge(DisbursementReconciliation::query()->findOrFail($id));
    }

    public function converge(DisbursementReconciliation $payout): void
    {
        DB::transaction(function () use ($payout): void {
            $row = DisbursementReconciliation::query()->whereKey($payout->getKey())->lockForUpdate()->firstOrFail();
            if ($row->claim_type !== 'value_flow' || $row->internal_status === 'finalized') {
                return;
            }
            $reference = $row->provider_reference;
            $meta = $row->meta;
            $amount = (int) $meta['amount_minor'];
            if ($row->status === 'succeeded' && filled($row->provider_transaction_id) && ! $row->needs_review) {
                $source = $row->internal_status === 'recovery_opened' ? $meta['recovery_position'] : $meta['reserve_position'];
                $this->operations->derecognize(new TreasuryPositionDerecognitionData(
                    operationReference: $reference.':settle', sourcePositionReference: $source,
                    amountMinor: $amount, currency: $row->currency, idempotencyKey: $reference.':settle',
                    externalReference: $row->provider.':'.$row->provider_transaction_id,
                    metadata: ['source' => 'x_change_value_flow'],
                ));
                $this->inventory->adjust(new TreasuryInventoryAdjustmentData(
                    operationReference: $reference.':outflow', inventoryReference: $meta['inventory_reference'],
                    deltaAmountMinor: -$amount, currency: $row->currency, status: 'requested',
                    idempotencyKey: $reference.':outflow', effectiveAt: now()->toRfc3339String(),
                    externalReference: $row->provider.':'.$row->provider_transaction_id,
                    metadata: ['source' => 'x_change_value_flow'],
                ));
                $row->forceFill(['internal_status' => 'finalized'])->save();
            } elseif ($this->rejectionTrust->isTrusted($row) && $row->internal_status === 'reserved') {
                $this->operations->holdPayoutRecovery(new TreasuryPositionPayoutRecoveryData(
                    operationReference: $reference.':recovery', sourcePositionReference: $meta['reserve_position'],
                    destinationPositionReference: $meta['recovery_position'], amountMinor: $amount, currency: $row->currency,
                    idempotencyKey: $reference.':recovery', externalReference: $row->provider.':'.$row->provider_transaction_id,
                    metadata: ['source' => 'x_change_value_flow'],
                ));
                $row->forceFill(['internal_status' => 'recovery_opened'])->save();
            }
        }, attempts: 5);
    }

    private function assertSharedConnection(Voucher $voucher): void
    {
        foreach ([$voucher, new DisbursementReconciliation, new TreasuryPosition,
            new TreasuryAllocation, new Wallet] as $model) {
            if ($model->getConnection()->getName() !== DB::connection()->getName()) {
                throw new RuntimeException('Value flow funding and payout intent must share one database transaction.');
            }
        }
    }

    /** @param list<TreasuryPositionData> $positions */
    private function position(array $positions, string $connection, string $currency, TreasuryPositionPurpose $purpose): TreasuryPositionData
    {
        $matches = array_values(array_filter($positions, static fn (TreasuryPositionData $position): bool => $position->connectionReference === $connection && $position->currency === $currency
            && $position->purpose === $purpose && $position->status === 'active'));
        if (count($matches) !== 1) {
            throw new RuntimeException('Value flow requires a unique active Treasury position.');
        }

        return $matches[0];
    }
}
