<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Disbursement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\PayoutDestinationRevision;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Treasury\PayCodePayoutCorrectionJournal;
use RuntimeException;

final readonly class RestoreUnsubmittedPayoutCorrection
{
    public function __construct(
        private PayCodePayoutCorrectionJournal $journal,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(
        string $code,
        Model $restoredBy,
        string $evidenceReference,
        bool $confirmedProviderDidNotAccept,
        ?int $reconciliationId = null,
    ): array {
        if (! $confirmedProviderDidNotAccept) {
            throw new RuntimeException(
                'Explicit confirmation that the provider did not accept the payout is required.',
            );
        }

        $evidenceReference = trim($evidenceReference);

        if ($evidenceReference === '') {
            throw new RuntimeException('An independent evidence reference is required.');
        }

        $normalizedCode = mb_strtoupper(trim($code));
        $lock = Cache::lock('x-change:payout-correction-restoration:'.$normalizedCode, 120);

        if (! $lock->get()) {
            throw new RuntimeException('Another payout correction restoration is already in progress.');
        }

        try {
            return DB::transaction(function () use (
                $evidenceReference,
                $normalizedCode,
                $reconciliationId,
                $restoredBy,
            ): array {
                $voucher = Voucher::query()
                    ->where('code', $normalizedCode)
                    ->lockForUpdate()
                    ->firstOrFail();
                $reconciliationQuery = DisbursementReconciliation::query()
                    ->where('voucher_id', $voucher->getKey())
                    ->where('claim_type', 'payout_recovery');

                if ($reconciliationId !== null) {
                    $reconciliationQuery->whereKey($reconciliationId);
                }

                $reconciliation = $reconciliationQuery
                    ->latest('id')
                    ->lockForUpdate()
                    ->firstOrFail();
                $claim = VoucherClaim::query()
                    ->where('voucher_id', $voucher->getKey())
                    ->latest('id')
                    ->lockForUpdate()
                    ->firstOrFail();
                $revision = PayoutDestinationRevision::query()
                    ->where('voucher_id', $voucher->getKey())
                    ->where('reference', data_get(
                        $reconciliation->meta,
                        'destination_revision_reference',
                    ))
                    ->firstOrFail();

                if ($this->alreadyRestored($reconciliation, $claim)) {
                    return $this->payload($voucher, $reconciliation, $claim, true);
                }

                $this->assertEligible($voucher, $reconciliation, $claim);

                $reconciliationMeta = (array) $reconciliation->meta;
                data_set($reconciliationMeta, 'provider_submission_accepted', false);
                data_set($reconciliationMeta, 'failure_phase', 'pre_provider');
                data_set($reconciliationMeta, 'restoration_evidence_reference', $evidenceReference);
                data_set($reconciliationMeta, 'restored_by_type', $restoredBy->getMorphClass());
                data_set($reconciliationMeta, 'restored_by_id', (string) $restoredBy->getKey());
                $reconciliation->forceFill([
                    'status' => 'failed',
                    'internal_status' => 'submission_failed',
                    'completed_at' => now(),
                    'needs_review' => false,
                    'review_reason' => null,
                    'error_message' => 'The payout submission was verified as not accepted by the provider.',
                    'meta' => $reconciliationMeta,
                ])->save();

                $claimMeta = (array) $claim->meta;
                data_set($claimMeta, 'disbursement.status', 'submission_failed');
                data_set($claimMeta, 'disbursement.reconciliation_id', $reconciliation->getKey());
                $claim->forceFill([
                    'status' => 'payout_rejected',
                    'disbursed_amount_minor' => 0,
                    'failure_message' => 'The previous correction did not reach the provider. Review the destination and retry.',
                    'meta' => $claimMeta,
                ])->save();

                $voucherMetadata = (array) $voucher->metadata;
                data_set($voucherMetadata, 'disbursement.status', 'rejected');
                data_set($voucherMetadata, 'disbursement.transaction_id', null);
                data_set($voucherMetadata, 'disbursement.requires_reconciliation', false);
                data_set($voucherMetadata, 'disbursement.requires_recovery', true);
                data_set(
                    $voucherMetadata,
                    'disbursement.rejection_reason',
                    $reconciliation->error_message,
                );
                $voucher->forceFill(['metadata' => $voucherMetadata])->saveQuietly();

                $this->journal->recordSubmissionRestoration(
                    $voucher,
                    $revision,
                    $reconciliation,
                    $restoredBy,
                    $evidenceReference,
                );

                return $this->payload($voucher, $reconciliation, $claim, false);
            }, attempts: 5);
        } finally {
            $lock->release();
        }
    }

    private function assertEligible(
        Voucher $voucher,
        DisbursementReconciliation $reconciliation,
        VoucherClaim $claim,
    ): void {
        if (
            data_get($voucher->metadata, 'treasury.pay_code_reservation.status') !== 'recovery_pending'
            || $reconciliation->provider_transaction_id !== null
            || $reconciliation->status !== 'unknown'
            || ! $reconciliation->needs_review
            || $claim->status !== 'payout_retry_pending'
        ) {
            throw new RuntimeException(
                'The payout correction is not eligible for unsubmitted-attempt restoration.',
            );
        }
    }

    private function alreadyRestored(
        DisbursementReconciliation $reconciliation,
        VoucherClaim $claim,
    ): bool {
        return $reconciliation->status === 'failed'
            && $reconciliation->internal_status === 'submission_failed'
            && data_get($reconciliation->meta, 'provider_submission_accepted') === false
            && $claim->status === 'payout_rejected';
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        Voucher $voucher,
        DisbursementReconciliation $reconciliation,
        VoucherClaim $claim,
        bool $replayed,
    ): array {
        return [
            'schema' => 'x-change.unsubmitted-payout-correction-restoration.v1',
            'success' => true,
            'pay_code' => (string) $voucher->code,
            'reconciliation_id' => (int) $reconciliation->getKey(),
            'reconciliation_status' => (string) $reconciliation->status,
            'internal_status' => (string) $reconciliation->internal_status,
            'claim_status' => (string) $claim->status,
            'reservation_status' => data_get(
                $voucher->refresh()->metadata,
                'treasury.pay_code_reservation.status',
            ),
            'provider_submission_accepted' => false,
            'provider_call_performed' => false,
            'treasury_changed' => false,
            'replayed' => $replayed,
        ];
    }
}
