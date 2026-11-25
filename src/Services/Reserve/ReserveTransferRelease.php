<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReleaseData;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Models\ReserveTransfer;
use RuntimeException;

final readonly class ReserveTransferRelease
{
    public function __construct(
        private DurableReserveTransfer $transfers,
        private TreasuryPositionOperationContract $positions,
        private ReserveTransferJournal $journal,
    ) {}

    public function release(ReserveTransfer $transfer): ReserveTransfer
    {
        return DB::transaction(function () use ($transfer): ReserveTransfer {
            $locked = ReserveTransfer::query()->lockForUpdate()->findOrFail($transfer->getKey());
            if ($locked->status === 'released') {
                return $locked;
            }
            if (! (($locked->status === 'held' && $locked->submitted_at === null)
                || ($locked->status === 'rejected' && is_array($locked->bank_evidence_ciphertext)))) {
                throw new RuntimeException('The transfer hold cannot be released while the bank outcome is pending or uncertain.');
            }
            $this->transfers->assertFrozen($locked);
            $this->transfers->assertHold($locked);
            if ($locked->issuance_snapshot_fingerprint !== null) {
                app(ReserveIssuanceSnapshot::class)->assertFeeHold($locked);
            } elseif ($locked->fee_minor !== 0 || $locked->fee_hold_operation_reference !== null) {
                throw new RuntimeException('The frozen issuance fee hold is invalid.');
            }
            $originalStatus = $locked->status;
            $hold = TreasuryPositionOperation::query()->with(['sourcePosition', 'destinationPosition'])
                ->where('operation_reference', $locked->hold_operation_reference)->firstOrFail();
            $scope = hash('sha256', $locked->reference);
            $this->positions->release(new TreasuryPositionReleaseData(
                operationReference: 'reserve-transfer-release:'.$scope,
                sourcePositionReference: $hold->destinationPosition->position_reference,
                destinationPositionReference: $hold->sourcePosition->position_reference,
                amountMinor: $locked->amount_minor, currency: $locked->currency,
                idempotencyKey: 'reserve-transfer-release-key:'.$scope,
                externalReference: 'reserve-transfer:'.$locked->reference,
            ));
            if ($locked->fee_minor > 0) {
                $this->positions->release(new TreasuryPositionReleaseData(
                    operationReference: 'reserve-transfer-fee-release:'.$scope,
                    sourcePositionReference: $hold->destinationPosition->position_reference,
                    destinationPositionReference: $hold->sourcePosition->position_reference,
                    amountMinor: $locked->fee_minor, currency: $locked->currency,
                    idempotencyKey: 'reserve-transfer-fee-release-key:'.$scope,
                    externalReference: 'reserve-transfer-fee:'.$locked->reference,
                ));
            }
            $locked->forceFill(['status' => 'released'])->save();
            $this->journal->recordHoldRelease($locked, $originalStatus === 'held' ? 'cancelled_before_submission' : 'provider_rejected');

            return $locked;
        }, attempts: 3);
    }
}
