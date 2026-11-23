<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionOperationType;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Contracts\RiderSplashArtworkSnapshotterContract;
use LBHurtado\XChange\Models\ReserveTransfer;
use LBHurtado\XChange\Services\Accounts\AccountLifecycle;
use LBHurtado\XChange\Services\Claim\ClaimEvidenceRequirements;
use LBHurtado\XChange\Services\Commercial\CommercialCustomerChargingGuard;
use LBHurtado\XChange\Services\Commercial\CommercialPricingAcceptanceGuard;
use LBHurtado\XChange\Services\Configuration\InstructionCapabilityIssuanceGuard;
use LBHurtado\XChange\Services\VoucherIssuancePayloadNormalizer;
use RuntimeException;

/** Freezes the single issuance and its server-calculated price before bank submission. */
final readonly class ReserveIssuanceSnapshot
{
    public function __construct(
        private VoucherIssuancePayloadNormalizer $normalizer,
        private EstimatePayCodeCost $estimator,
        private TreasuryPositionOperationContract $positions,
    ) {}

    /** @param array<string, mixed> $input */
    public function bind(ReserveTransfer $transfer, Model $owner, array $input): ReserveTransfer
    {
        $transfer = $transfer->fresh();
        app(DurableReserveTransfer::class)->assertFrozen($transfer);
        $input = $this->normalizer->normalize($input);
        app(InstructionCapabilityIssuanceGuard::class)->ensureAvailable($input);
        $input = app(ClaimEvidenceRequirements::class)->snapshot($input);
        $amount = data_get($input, 'cash.amount');
        if (! is_numeric($amount) || (float) $amount <= 0 || (float) $amount > PHP_INT_MAX / 100
            || abs((float) $amount * 100 - round((float) $amount * 100)) > 0.000001
            || (int) round((float) $amount * 100) !== $transfer->amount_minor
            || data_get($input, 'cash.currency', 'PHP') !== $transfer->currency
            || ! in_array(data_get($input, 'voucher_type'), [null, VoucherType::REDEEMABLE->value], true)
            || ! is_numeric(data_get($input, 'count', 1)) || (float) data_get($input, 'count', 1) !== 1.0
            || (data_get($input, 'issuer_id') !== null && (string) data_get($input, 'issuer_id') !== (string) $owner->getKey())
            || (data_get($input, 'metadata.issuer_id') !== null && (string) data_get($input, 'metadata.issuer_id') !== (string) $owner->getKey())) {
            throw new RuntimeException('The issuance does not match the reserved transfer.');
        }
        $input = app(RiderSplashArtworkSnapshotterContract::class)->prepare($input);
        $estimate = $this->estimator->handle($input);
        if ($estimate->currency !== $transfer->currency || $estimate->customer_charge_minor < 0) {
            throw new RuntimeException('The issuance price does not match the reserved transfer.');
        }
        app(CommercialCustomerChargingGuard::class)->ensureAuthorized($estimate->customer_charge_minor);
        if (data_get($input, 'execution.schema') === 'voucher.execution.v2' && is_array(data_get($input, 'pricing'))) {
            app(CommercialPricingAcceptanceGuard::class)->assertV2Decision(data_get($input, 'pricing'), $estimate);
        }
        if (is_array(data_get($input, '_pricing'))) {
            app(CommercialPricingAcceptanceGuard::class)->assertExpected(data_get($input, '_pricing'), $estimate);
        }
        $snapshot = [
            'reference' => $transfer->reference,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'account_link_id' => $transfer->bank_account_link_id,
            'instructions' => $input,
            'estimate' => $estimate->toArray(),
        ];
        $fingerprint = $this->fingerprint($snapshot);

        return DB::transaction(function () use ($transfer, $owner, $snapshot, $fingerprint): ReserveTransfer {
            $locked = ReserveTransfer::query()->lockForUpdate()->findOrFail($transfer->getKey());
            app(DurableReserveTransfer::class)->assertFrozen($locked);
            if (! app(AccountLifecycle::class)->lockAndIsActive($owner)) {
                throw new RuntimeException('The account is suspended.');
            }
            if ($locked->amount_minor !== $transfer->amount_minor || $locked->currency !== $transfer->currency
                || $locked->status !== 'held' || $locked->owner_type !== $owner->getMorphClass()
                || (string) $locked->owner_id !== (string) $owner->getKey()
                || $locked->bank_account_link_id !== $snapshot['account_link_id']) {
                throw new RuntimeException('The issuance owner and held account do not match.');
            }
            if ($locked->issuance_snapshot_fingerprint !== null) {
                if (! hash_equals($locked->issuance_snapshot_fingerprint, $fingerprint)) {
                    throw new RuntimeException('The transfer is already bound to different issuance instructions or pricing.');
                }
                $this->assertFrozen($locked);

                return $locked;
            }
            $this->assertPrincipalHold($locked);
            $fee = (int) $snapshot['estimate']['customer_charge_minor'];
            $feeReference = null;
            if ($fee > 0) {
                $principalHold = TreasuryPositionOperation::query()->with(['sourcePosition', 'destinationPosition'])
                    ->where('operation_reference', $locked->hold_operation_reference)->firstOrFail();
                $scope = hash('sha256', $locked->reference);
                $feeHold = $this->positions->reserve(new TreasuryPositionReservationData(
                    operationReference: 'reserve-transfer-fee-hold:'.$scope,
                    sourcePositionReference: $principalHold->sourcePosition->position_reference,
                    destinationPositionReference: $principalHold->destinationPosition->position_reference,
                    amountMinor: $fee,
                    currency: $locked->currency,
                    idempotencyKey: 'reserve-transfer-fee-hold-key:'.$scope,
                    externalReference: 'reserve-transfer-fee:'.$locked->reference,
                    metadata: ['source' => 'reserve_transfer_fee_hold'],
                ));
                $feeReference = $feeHold->operationReference;
            }
            $locked->forceFill([
                'issuance_snapshot_ciphertext' => $snapshot,
                'issuance_snapshot_fingerprint' => $fingerprint,
                'fee_minor' => $fee,
                'fee_hold_operation_reference' => $feeReference,
            ])->save();

            return $locked;
        }, attempts: 3);
    }

    public function assertFrozen(ReserveTransfer $transfer): void
    {
        $snapshot = $transfer->issuance_snapshot_ciphertext;
        if (! is_array($snapshot) || ! is_string($transfer->issuance_snapshot_fingerprint)
            || ! hash_equals($transfer->issuance_snapshot_fingerprint, $this->fingerprint($snapshot))
            || ($snapshot['reference'] ?? null) !== $transfer->reference
            || ($snapshot['owner_type'] ?? null) !== $transfer->owner_type
            || (string) ($snapshot['owner_id'] ?? '') !== (string) $transfer->owner_id
            || ($snapshot['account_link_id'] ?? null) !== $transfer->bank_account_link_id
            || ($snapshot['estimate']['customer_charge_minor'] ?? null) !== $transfer->fee_minor) {
            throw new RuntimeException('A matching frozen issuance is required before bank submission.');
        }
    }

    public function assertFeeHold(ReserveTransfer $transfer): void
    {
        $this->assertFrozen($transfer);
        if ($transfer->fee_minor === 0 && $transfer->fee_hold_operation_reference === null) {
            return;
        }
        $this->assertPrincipalHold($transfer);
        $principal = TreasuryPositionOperation::query()->where('operation_reference', $transfer->hold_operation_reference)->firstOrFail();
        $fee = TreasuryPositionOperation::query()->where('operation_reference', $transfer->fee_hold_operation_reference)->first();
        if ($transfer->fee_minor <= 0 || ! $fee
            || $fee->operation_reference !== 'reserve-transfer-fee-hold:'.hash('sha256', $transfer->reference)
            || $fee->operation_type !== TreasuryPositionOperationType::Reservation || $fee->status !== 'committed'
            || $fee->amount_minor !== $transfer->fee_minor || $fee->currency !== $transfer->currency
            || $fee->external_reference !== 'reserve-transfer-fee:'.$transfer->reference
            || $fee->source_position_id !== $principal->source_position_id
            || $fee->destination_position_id !== $principal->destination_position_id) {
            throw new RuntimeException('The frozen issuance fee hold is invalid.');
        }
    }

    private function assertPrincipalHold(ReserveTransfer $transfer): void
    {
        app(DurableReserveTransfer::class)->assertHold($transfer);
    }

    /** @param array<string, mixed> $snapshot */
    private function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}
