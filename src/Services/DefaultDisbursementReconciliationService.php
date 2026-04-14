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

        $updates = [
            'status' => $resolvedStatus,
            'last_checked_at' => now(),
            'raw_response' => $metadata !== [] ? $metadata : $fetched,
            'error_message' => null,
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
}
