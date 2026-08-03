<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

final readonly class CommercialAccountingJournalBackfill
{
    public function __construct(
        private CommercialAccountingJournal $journal,
    ) {}

    /**
     * @param  list<string>  $saleReferences
     * @return array<string, mixed>
     */
    public function inspect(array $saleReferences = []): array
    {
        return $this->report($saleReferences, false, null);
    }

    /**
     * @param  list<string>  $saleReferences
     * @return array<string, mixed>
     */
    public function backfill(
        array $saleReferences,
        string $authorizationReference,
    ): array {
        $authorizationReference = trim($authorizationReference);

        if ($authorizationReference === '') {
            throw new CommercialSaleConflict(
                'Commercial journal backfill requires an authorization reference.',
            );
        }

        return $this->report(
            $saleReferences,
            true,
            $authorizationReference,
        );
    }

    /**
     * @param  list<string>  $saleReferences
     * @return array<string, mixed>
     */
    private function report(
        array $saleReferences,
        bool $commit,
        ?string $authorizationReference,
    ): array {
        $rows = CommercialSale::query()
            ->with('allocations')
            ->when(
                $saleReferences !== [],
                fn ($query) => $query->whereIn('reference', $saleReferences),
            )
            ->orderBy('id')
            ->get()
            ->map(function (CommercialSale $sale) use (
                $authorizationReference,
                $commit,
            ): array {
                $inspection = $this->inspectSale($sale);

                if ($commit && $inspection['can_backfill']) {
                    DB::transaction(function () use ($sale): void {
                        $this->journal->recordSalePosted($sale);
                    }, attempts: 5);
                    $inspection = $this->inspectSale($sale->refresh('allocations'));
                }

                return [
                    ...$inspection,
                    'authorization_reference_recorded' => $commit
                        ? hash('sha256', (string) $authorizationReference)
                        : null,
                ];
            })
            ->values()
            ->all();

        $unknown = array_values(array_diff(
            array_unique($saleReferences),
            array_column($rows, 'commercial_sale_reference'),
        ));

        return [
            'schema' => 'x-change.commercial-accounting-journal-backfill.v1',
            'mode' => $commit ? 'commit' : 'preview',
            'sale_count' => count($rows),
            'backfilled_count' => collect($rows)
                ->where('journal_complete', true)
                ->count(),
            'review_required_count' => collect($rows)
                ->where('review_required', true)
                ->count(),
            'unknown_sale_references' => $unknown,
            'sales' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inspectSale(CommercialSale $sale): array
    {
        $correlationId = 'commercial-sale:'.$sale->reference;
        $accepted = $this->eventExists($correlationId, 'commercial.sale.accepted');
        $charged = $this->eventExists($correlationId, 'commercial.charge.posted');
        $allocationEvents = ExecutionJournalEntry::query()
            ->where('correlation_id', $correlationId)
            ->where('event_type', 'commercial.allocation.posted')
            ->count();
        $reversal = $this->eventExists($correlationId, 'commercial.sale.reversed');
        $baseJournalComplete = $accepted
            && $charged
            && $allocationEvents === $sale->allocations->count();
        $operationsComplete = filled($sale->charge_operation_reference)
            && $sale->allocations->every(
                static fn (CommercialAllocation $allocation): bool => $allocation->amount_minor === 0
                    || filled($allocation->treasury_operation_reference),
            );
        $v2Context = (int) data_get(
            $sale->snapshot,
            'accounting_context.schema_version',
        ) >= 2;
        $reviewReasons = [];

        if (! $v2Context) {
            $reviewReasons[] = 'immutable-accounting-context-not-v2';
        }

        if (! $operationsComplete) {
            $reviewReasons[] = 'treasury-operation-evidence-incomplete';
        }

        if ($sale->status === 'reversed' && ! $reversal) {
            $reviewReasons[] = 'reversal-reason-not-reconstructible';
        }

        return [
            'commercial_sale_reference' => $sale->reference,
            'status' => $sale->status,
            'accounting_context_schema_version' => (int) data_get(
                $sale->snapshot,
                'accounting_context.schema_version',
                1,
            ),
            'journal_complete' => $baseJournalComplete
                && ($sale->status !== 'reversed' || $reversal),
            'base_journal_complete' => $baseJournalComplete,
            'missing_base_event_count' => (int) ! $accepted
                + (int) ! $charged
                + max(0, $sale->allocations->count() - $allocationEvents),
            'can_backfill' => ! $baseJournalComplete && $operationsComplete,
            'review_required' => $reviewReasons !== [],
            'review_reasons' => $reviewReasons,
            'raw_provider_evidence_inferred' => false,
            'snapshot_rewritten' => false,
        ];
    }

    private function eventExists(string $correlationId, string $eventType): bool
    {
        return ExecutionJournalEntry::query()
            ->where('correlation_id', $correlationId)
            ->where('event_type', $eventType)
            ->exists();
    }
}
