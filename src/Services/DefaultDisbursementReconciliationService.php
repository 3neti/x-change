<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Facades\Event;
use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\DisbursementStatusFetcherContract;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;
use LBHurtado\XChange\Events\DisbursementConfirmed;
use LBHurtado\XChange\Models\DisbursementReconciliation;

class DefaultDisbursementReconciliationService implements DisbursementReconciliationContract
{
    public function __construct(
        protected DisbursementReconciliationStoreContract $store,
        protected DisbursementStatusFetcherContract $fetcher,
        protected DisbursementStatusResolverContract $resolver,
    ) {}

    public function reconcile(DisbursementReconciliation|DisbursementReconciliationData $reconciliation): array
    {
        $model = $reconciliation instanceof DisbursementReconciliation
            ? $reconciliation
            : DisbursementReconciliation::query()->findOrFail($reconciliation->id);

        $beforeStatus = (string) $model->status;

        $fetched = $this->fetcher->fetch(
            DisbursementReconciliationData::from($model->toArray())
        );

        $metadata = $this->extractMetadata($fetched);
        $fetchedStatus = $fetched['status'] ?? null;
        $resolvedStatus = $this->resolver->resolveFromFetchedStatus($fetchedStatus, $metadata);

        $trustsFailure = $this->shouldTrustFailedStatus($fetchedStatus, $metadata);

        $needsReview = (bool) $model->needs_review;
        $reviewReason = $model->review_reason;
        $errorMessage = null;

        if ($resolvedStatus === 'failed' && ! $trustsFailure) {
            if ($beforeStatus === 'pending') {
                $resolvedStatus = 'pending';
            } else {
                $resolvedStatus = 'unknown';
            }

            $needsReview = true;
            $reviewReason = 'Low-confidence failed status from provider';
            $errorMessage = 'Provider returned an untrusted failed status with incomplete metadata.';
        }

        $updates = [
            'status' => $resolvedStatus,
            'last_checked_at' => now(),
            'raw_response' => $metadata !== [] ? $metadata : $fetched,
            'error_message' => $errorMessage,
            'needs_review' => $needsReview,
            'review_reason' => $reviewReason,
        ];

        if ($resolvedStatus === 'succeeded' && ! $model->completed_at) {
            $updates['completed_at'] = now();
        }

        if (in_array($resolvedStatus, ['succeeded', 'failed'], true)) {
            $updates['next_retry_at'] = null;
        }

        $model->fill($updates);
        $model->save();

        if ($beforeStatus !== 'succeeded' && $resolvedStatus === 'succeeded') {
            Event::dispatch(new DisbursementConfirmed($model->fresh()));
        }

        return [
            'updated' => $beforeStatus !== $resolvedStatus,
            'before_status' => $beforeStatus,
            'fetched_status' => $fetchedStatus,
            'resolved_status' => $resolvedStatus,
            'reconciliation_id' => $model->id,
            'raw' => $metadata !== [] ? $metadata : $fetched,
            'needs_review' => $needsReview,
            'review_reason' => $reviewReason,
            'trusted_failure' => $trustsFailure,
        ];
    }

    /**
     * @param  array<string, mixed>  $fetched
     * @return array<string, mixed>
     */
    protected function extractMetadata(array $fetched): array
    {
        $metadata = $fetched['raw'] ?? $fetched['metadata'] ?? null;

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * A provider-reported failure is only trusted when the payload contains
     * enough transaction detail to be considered authoritative.
     *
     * @param  array<string, mixed>  $metadata
     */
    protected function shouldTrustFailedStatus(mixed $fetchedStatus, array $metadata): bool
    {
        if (! is_string($fetchedStatus) || strtolower($fetchedStatus) !== 'failed') {
            return true;
        }

        if ($metadata === []) {
            return false;
        }

        $hasOperationId = data_get($metadata, 'operation_id') !== null;
        $hasReferenceNumber = data_get($metadata, 'reference_number') !== null;
        $hasStatusDetails = ! empty(data_get($metadata, 'status_details', []));
        $hasDestinationBank = data_get($metadata, 'destination_account.bank_code') !== null;
        $hasDestinationAccount = data_get($metadata, 'destination_account.account_number') !== null;
        $hasRail = data_get($metadata, 'settlement_rail') !== null;

        return $hasOperationId
            || $hasReferenceNumber
            || $hasStatusDetails
            || ($hasDestinationBank && $hasDestinationAccount)
            || $hasRail;
    }
}
