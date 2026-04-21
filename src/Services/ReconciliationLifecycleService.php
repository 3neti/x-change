<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\ReconciliationLifecycleServiceContract;
use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;
use LBHurtado\XChange\Exceptions\ReconciliationNotFound;

class ReconciliationLifecycleService implements ReconciliationLifecycleServiceContract
{
    public function __construct(
        protected DisbursementReconciliationStoreContract $store,
        protected DisbursementReconciliationContract $reconciler,
    ) {}

    public function list(array $filters = []): array
    {
        $limit = isset($filters['limit']) && is_numeric($filters['limit'])
            ? (int) $filters['limit']
            : 50;

        $items = $this->store->getPending($limit);

        return collect($items)
            ->map(fn (DisbursementReconciliationData $item) => $this->toSummaryArray($item))
            ->values()
            ->all();
    }

    public function show(string $reconciliation): mixed
    {
        $record = $this->findOrFail($reconciliation);

        return $this->toDetailArray($record);
    }

    public function resolve(string $reconciliation, array $payload): mixed
    {
        $record = $this->findOrFail($reconciliation);

        $resolved = $this->reconciler->reconcile($record);

        return [
            'reconciliation_id' => (string) data_get($resolved, 'reconciliation_id', $record->id),
            'status' => (string) data_get($resolved, 'resolved_status', $record->status),
            'resolution' => (string) ($payload['resolution'] ?? 'resolved'),
            'resolved' => in_array((string) data_get($resolved, 'resolved_status'), ['succeeded', 'failed'], true),
            'notes' => $payload['notes'] ?? null,
            'messages' => [
                sprintf(
                    'Reconciliation %s processed from %s to %s.',
                    (string) data_get($resolved, 'reconciliation_id', $record->id),
                    (string) data_get($resolved, 'before_status', $record->status),
                    (string) data_get($resolved, 'resolved_status', $record->status),
                ),
            ],
        ];
    }

    protected function findOrFail(string $reconciliation): DisbursementReconciliationData
    {
        $id = (int) $reconciliation;

        $record = $this->store->findById($id);

        if ($record instanceof DisbursementReconciliationData) {
            return $record;
        }

        throw new ReconciliationNotFound($reconciliation);
    }

    protected function toSummaryArray(DisbursementReconciliationData $item): array
    {
        return [
            'id' => (string) $item->id,
            'reference' => $this->reference($item),
            'status' => (string) $item->status,
            'provider_status' => $this->providerStatus($item),
            'amount' => $this->amount($item),
            'currency' => $this->currency($item),
        ];
    }

    protected function toDetailArray(DisbursementReconciliationData $item): array
    {
        return [
            'id' => (string) $item->id,
            'reference' => $this->reference($item),
            'status' => (string) $item->status,
            'provider_status' => $this->providerStatus($item),
            'amount' => $this->amount($item),
            'currency' => $this->currency($item),
            'reason' => $item->review_reason,
            'resolved' => in_array((string) $item->status, ['succeeded', 'failed'], true),
            'resolved_at' => $this->resolvedAt($item),
        ];
    }

    protected function reference(DisbursementReconciliationData $item): string
    {
        return (string) ($item->provider_reference
            ?? $item->voucher_code
            ?? $item->id);
    }

    protected function providerStatus(DisbursementReconciliationData $item): string
    {
        $raw = is_array($item->raw_response) ? $item->raw_response : [];

        return (string) ($raw['status'] ?? $item->status);
    }

    protected function amount(DisbursementReconciliationData $item): float
    {
        return (float) ($item->amount ?? 0.0);
    }

    protected function currency(DisbursementReconciliationData $item): string
    {
        return (string) ($item->currency ?? 'PHP');
    }

    protected function resolvedAt(DisbursementReconciliationData $item): ?string
    {
        $value = $item->completed_at ?? null;

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
