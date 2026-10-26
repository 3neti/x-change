<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\StoredValue;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ReconcileStoredValueAuditCommand extends Command
{
    protected $signature = 'x-change:stored-value:reconcile-audit {--json}';

    protected $description = 'Read-only reconciliation of stored-value lifecycle facts and x-journal delivery';

    public function handle(): int
    {
        $events = DB::table('x_change_stored_value_audit_events');
        $report = [
            'activated_bindings_without_outbox' => DB::table('x_change_stored_value_holder_bindings as b')
                ->where('b.status', 'active')
                ->whereNotExists(static fn ($query) => $query->selectRaw('1')
                    ->from('x_change_stored_value_audit_events as e')
                    ->whereColumn('e.stored_value_holder_binding_id', 'b.id')
                    ->where('e.event_type', 'stored_value.activated'))
                ->count(),
            'committed_collections_without_outbox' => DB::table('x_change_stored_value_collections as c')
                ->where('c.status', 'committed')
                ->whereNotExists(static fn ($query) => $query->selectRaw('1')
                    ->from('x_change_stored_value_audit_events as e')
                    ->whereColumn('e.stored_value_collection_id', 'c.id')
                    ->where('e.event_type', 'stored_value.collection.committed'))
                ->count(),
            'pending_delivery' => (clone $events)->where('journal_status', 'pending')->count(),
            'delivered_without_journal' => DB::table('x_change_stored_value_audit_events as e')
                ->where('e.journal_status', 'delivered')
                ->whereNotExists(static fn ($query) => $query->selectRaw('1')
                    ->from('execution_journal_entries as j')
                    ->whereColumn('j.source_event_id', 'e.event_id'))
                ->count(),
            'collection_amount_mismatches' => DB::table('x_change_stored_value_collections as c')
                ->join('x_change_stored_value_audit_events as e', 'e.stored_value_collection_id', '=', 'c.id')
                ->where(static fn ($query) => $query
                    ->whereColumn('c.amount_minor', '!=', 'e.amount_minor')
                    ->orWhereColumn('c.balance_after_minor', '!=', 'e.balance_after_minor'))
                ->count(),
            'activation_amount_mismatches' => DB::table('x_change_stored_value_holder_bindings as b')
                ->join('x_change_stored_value_audit_events as e', 'e.stored_value_holder_binding_id', '=', 'b.id')
                ->where('e.event_type', 'stored_value.activated')
                ->join('treasury_allocation_operations as o', 'o.operation_reference', '=', 'b.activation_operation_reference')
                ->where(static fn ($query) => $query
                    ->whereColumn('o.amount_minor', '!=', 'e.amount_minor')
                    ->orWhereColumn('o.balance_after_minor', '!=', 'e.balance_after_minor'))
                ->count(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Count'], collect($report)->map(fn (int $count, string $key): array => [$key, $count])->values()->all());
        }

        return array_sum($report) === 0 ? self::SUCCESS : self::FAILURE;
    }
}
