<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Events\DisbursementRejected;
use LBHurtado\XChange\Jobs\Redemption\DispatchVoucherRedemptionFeedbackJob;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Treasury\PayCodeDisbursementRejectionJournal;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;
use Throwable;

final readonly class HandleRejectedDisbursement
{
    public function __construct(
        private TreasuryPayCodeAccountingService $accounting,
        private PayCodeDisbursementRejectionJournal $journal,
    ) {}

    public function handle(DisbursementRejected $event): void
    {
        $reconciliation = $event->reconciliation->fresh();

        if (
            $reconciliation->status !== 'failed'
            || ! filled($reconciliation->provider_transaction_id)
            || $reconciliation->needs_review
        ) {
            return;
        }

        $voucher = Voucher::query()->find($reconciliation->voucher_id);

        if (! $voucher instanceof Voucher) {
            return;
        }

        $reservationStatus = data_get(
            $voucher->metadata,
            'treasury.pay_code_reservation.status',
        );

        if (! in_array($reservationStatus, ['reserved', 'recovery_pending'], true)) {
            return;
        }

        $owner = $voucher->owner;

        if (! $owner instanceof Model) {
            Log::error('[XChange] Rejected disbursement has no Pay Code owner.', [
                'voucher_code' => $voucher->code,
                'reconciliation_id' => $reconciliation->getKey(),
            ]);

            return;
        }

        $recoveryOperationReference = (string) data_get(
            $voucher->metadata,
            'treasury.pay_code_reservation.recovery_operation_reference',
        );

        DB::transaction(function () use (
            $owner,
            $reconciliation,
            &$recoveryOperationReference,
            $reservationStatus,
            $voucher,
        ): void {
            if ($reservationStatus === 'reserved') {
                $recovery = $this->accounting->holdRejectedPayout(
                    accountOwner: $owner,
                    voucher: $voucher,
                    reconciliation: $reconciliation,
                );
                $recoveryOperationReference = $recovery->operationReference;
            }

            $metadata = (array) $voucher->refresh()->metadata;
            data_set($metadata, 'treasury.pay_code_reservation.status', 'recovery_pending');
            data_set(
                $metadata,
                'treasury.pay_code_reservation.recovery_operation_reference',
                $recoveryOperationReference,
            );
            data_set(
                $metadata,
                'treasury.pay_code_reservation.recovery_opened_at',
                data_get($metadata, 'treasury.pay_code_reservation.recovery_opened_at')
                    ?? now()->toIso8601String(),
            );
            data_set(
                $metadata,
                'treasury.pay_code_reservation.rejection_reconciliation_id',
                (int) $reconciliation->getKey(),
            );
            data_set($metadata, 'disbursement.status', 'rejected');
            data_set($metadata, 'disbursement.requires_reconciliation', false);
            data_set($metadata, 'disbursement.requires_recovery', true);
            data_set($metadata, 'disbursement.rejection_reason', $reconciliation->error_message);
            $voucher->forceFill(['metadata' => $metadata])->saveQuietly();

            $this->convergeReadModels($voucher, $reconciliation);

            $journalRecorded = $this->recordRejectionJournal(
                $voucher,
                $reconciliation,
                $recoveryOperationReference,
            );
            $reconciliation->forceFill([
                'internal_status' => $journalRecorded
                    ? 'recovery_opened'
                    : 'journal_pending',
            ])->save();
        }, attempts: 5);

        $this->queueRejectionFeedback($voucher, $reconciliation);
    }

    private function convergeReadModels(
        Voucher $voucher,
        DisbursementReconciliation $reconciliation,
    ): void {
        $claim = VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->latest('id')
            ->first();

        if ($claim instanceof VoucherClaim) {
            $meta = (array) $claim->meta;
            data_set($meta, 'disbursement.status', 'rejected');
            data_set($meta, 'disbursement.requires_recovery', true);
            data_set($meta, 'disbursement.reconciliation_id', $reconciliation->getKey());
            $claim->forceFill([
                'status' => 'payout_rejected',
                'disbursed_amount_minor' => 0,
                'completed_at' => $reconciliation->completed_at ?? now(),
                'failure_message' => $reconciliation->error_message
                    ?: 'The receiving institution rejected the payout destination.',
                'meta' => $meta,
            ])->save();
        }

        $fulfillment = CampaignWorksheetFulfillment::query()
            ->where('pay_code', $voucher->code)
            ->first();

        if (! $fulfillment instanceof CampaignWorksheetFulfillment) {
            return;
        }

        $metadata = (array) $fulfillment->metadata;
        $metadata['settlement'] = [
            'status' => 'rejected',
            'amount_minor' => (int) round(((float) $reconciliation->amount) * 100),
            'currency' => (string) $reconciliation->currency,
            'provider' => $reconciliation->provider,
            'settlement_rail' => $reconciliation->settlement_rail,
            'rejection_reason' => $reconciliation->error_message,
            'requires_recovery' => true,
            'completed_at' => $reconciliation->completed_at?->toIso8601String(),
        ];
        $fulfillment->forceFill([
            'status' => 'recovery_required',
            'provider_transfer_reference' => $reconciliation->provider_transaction_id,
            'metadata' => $metadata,
        ])->save();
    }

    private function recordRejectionJournal(
        Voucher $voucher,
        DisbursementReconciliation $reconciliation,
        string $recoveryOperationReference,
    ): bool {
        try {
            $this->journal->record(
                $voucher,
                $reconciliation,
                $recoveryOperationReference,
            );

            return true;
        } catch (Throwable $exception) {
            Log::error('[XChange] Rejected disbursement journal handoff is pending.', [
                'voucher_code' => $voucher->code,
                'reconciliation_id' => $reconciliation->getKey(),
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    private function queueRejectionFeedback(
        Voucher $voucher,
        DisbursementReconciliation $reconciliation,
    ): void {
        $claim = VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->latest('id')
            ->first();

        if ($claim instanceof VoucherClaim) {
            DispatchVoucherRedemptionFeedbackJob::dispatch(
                $claim->getKey(),
                'provider-rejected:'.(string) $reconciliation->provider_transaction_id,
            )->afterCommit();
        }
    }
}
