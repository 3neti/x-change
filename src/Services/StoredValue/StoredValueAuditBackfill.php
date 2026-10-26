<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\StoredValue;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Models\StoredValueCollection;
use LBHurtado\XChange\Models\StoredValueHolderBinding;

final readonly class StoredValueAuditBackfill
{
    public function __construct(private StoredValueAuditJournal $journal) {}

    /** @return array{verified: int, created: int, unverified: int} */
    public function run(int $limit = 100, bool $commit = false): array
    {
        $result = ['verified' => 0, 'created' => 0, 'unverified' => 0];
        $remaining = max(1, $limit);

        $bindings = StoredValueHolderBinding::query()
            ->whereNotExists(static fn ($query) => $query->selectRaw('1')
                ->from('x_change_stored_value_audit_events as e')
                ->whereColumn('e.stored_value_holder_binding_id', 'x_change_stored_value_holder_bindings.id')
                ->where('e.event_type', 'stored_value.activated'))
            ->orderBy('id')
            ->limit($remaining)
            ->get();

        foreach ($bindings as $binding) {
            $allocationId = DB::table('treasury_allocations')
                ->where('allocation_reference', $binding->allocation_reference)
                ->value('id');
            $operation = DB::table('treasury_allocation_operations')
                ->where('operation_reference', $binding->activation_operation_reference)
                ->where('operation_type', 'activation')
                ->where('status', 'committed')
                ->first(['allocation_id', 'amount_minor', 'balance_after_minor', 'currency']);

            if ($allocationId === null || $operation === null || (int) $operation->allocation_id !== (int) $allocationId
                || (int) $operation->amount_minor !== (int) $operation->balance_after_minor
                || $operation->currency !== $binding->currency) {
                $result['unverified']++;

                continue;
            }

            $result['verified']++;
            if ($commit) {
                $this->journal->captureActivation($binding, (int) $operation->amount_minor);
                $result['created']++;
            }
        }

        $remaining -= $bindings->count();
        if ($remaining <= 0) {
            return $result;
        }

        $collections = StoredValueCollection::query()
            ->where('status', 'committed')
            ->whereNotExists(static fn ($query) => $query->selectRaw('1')
                ->from('x_change_stored_value_audit_events as e')
                ->whereColumn('e.stored_value_collection_id', 'x_change_stored_value_collections.id')
                ->where('e.event_type', 'stored_value.collection.committed'))
            ->with('binding')
            ->orderBy('id')
            ->limit($remaining)
            ->get();

        foreach ($collections as $collection) {
            if (! $this->hasMatchingDraw($collection)) {
                $result['unverified']++;

                continue;
            }

            $result['verified']++;
            if ($commit) {
                $this->journal->captureCollection($collection);
                $result['created']++;
            }
        }

        return $result;
    }

    private function hasMatchingDraw(StoredValueCollection $collection): bool
    {
        $binding = $collection->binding;
        if (! $binding instanceof StoredValueHolderBinding || $collection->balance_after_minor === null
            || $collection->treasury_operation_reference_hash === null) {
            return false;
        }

        $allocationId = DB::table('treasury_allocations')
            ->where('allocation_reference', $binding->allocation_reference)
            ->value('id');
        if ($allocationId === null) {
            return false;
        }

        $key = (string) (config('x-change.execution.stored_value.otp.hash_key') ?: config('app.key'));
        foreach (DB::table('treasury_allocation_operations')
            ->where('allocation_id', $allocationId)
            ->where('operation_type', 'draw')
            ->where('status', 'committed')
            ->cursor(['operation_reference', 'amount_minor', 'balance_after_minor', 'currency']) as $operation) {
            if (hash_equals($collection->treasury_operation_reference_hash, hash_hmac('sha256', $operation->operation_reference, $key))) {
                return (int) $operation->amount_minor === $collection->amount_minor
                    && (int) $operation->balance_after_minor === $collection->balance_after_minor
                    && $operation->currency === $collection->currency;
            }
        }

        return false;
    }
}
