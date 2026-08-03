<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\Treasury\PayCodeDisbursementSettlementJournal;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

final class BackfillDisbursementSettlementJournalCommand extends Command
{
    protected $signature = 'x-change:treasury:backfill-disbursement-settlement-journal
        {--code=* : Pay Code to inspect; repeat for multiple Pay Codes}
        {--reconciliation=* : Reconciliation ID to inspect; repeat for multiple records}
        {--limit=100 : Maximum number of eligible records to inspect}
        {--commit : Append missing settlement journal entries}
        {--json : Output JSON}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Guardedly append missing x-journal events for finalized provider disbursements';

    public function handle(PayCodeDisbursementSettlementJournal $journal): int
    {
        $codes = $this->stringOptions('code');
        $reconciliationIds = $this->integerOptions('reconciliation');
        $commit = (bool) $this->option('commit');

        if ($commit && $codes === [] && $reconciliationIds === []) {
            return $this->render([
                'status' => 'rejected',
                'message' => 'Commit mode requires at least one explicit --code or --reconciliation.',
                'committed' => false,
            ], self::FAILURE);
        }

        $query = DisbursementReconciliation::query()
            ->where('status', 'succeeded')
            ->where('internal_status', 'finalized')
            ->orderBy('id');

        if ($codes !== [] || $reconciliationIds !== []) {
            $query->where(function ($query) use ($codes, $reconciliationIds): void {
                if ($codes !== []) {
                    $query->whereIn('voucher_code', $codes);
                }

                if ($reconciliationIds !== []) {
                    $method = $codes === [] ? 'whereIn' : 'orWhereIn';
                    $query->{$method}('id', $reconciliationIds);
                }
            });
        }

        $records = $query
            ->limit(max(1, (int) $this->option('limit')))
            ->get();
        $candidates = [];
        $recorded = 0;

        foreach ($records as $reconciliation) {
            $voucher = Voucher::query()->find($reconciliation->voucher_id);

            if (! $voucher instanceof Voucher) {
                continue;
            }

            $idempotencyKey = 'x-change:pay-code:disbursement-settled:'
                .$voucher->getKey().':'.$reconciliation->getKey();
            $exists = ExecutionJournalEntry::query()
                ->where('idempotency_key', $idempotencyKey)
                ->exists();

            $candidates[] = [
                'pay_code' => (string) $voucher->code,
                'reconciliation_id' => (int) $reconciliation->getKey(),
                'status' => $exists ? 'already_recorded' : 'missing',
            ];

            if ($commit && ! $exists) {
                $journal->record($voucher, $reconciliation);
                $recorded++;
            }
        }

        return $this->render([
            'schema' => 'x-change.disbursement-settlement-journal-backfill.v1',
            'status' => $commit ? 'completed' : 'preview',
            'candidate_count' => count($candidates),
            'missing_count' => collect($candidates)
                ->where('status', 'missing')
                ->count(),
            'recorded_count' => $recorded,
            'candidates' => $candidates,
            'committed' => $commit,
            'provider_calls' => false,
            'treasury_changed' => false,
        ], self::SUCCESS);
    }

    /**
     * @return list<string>
     */
    private function stringOptions(string $name): array
    {
        return collect((array) $this->option($name))
            ->filter(fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '')
            ->map(fn (mixed $value): string => trim((string) $value))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function integerOptions(string $name): array
    {
        return collect((array) $this->option($name))
            ->filter(fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_INT) !== false)
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function render(array $payload, int $exitCode): int
    {
        if ($this->option('json')) {
            $flags = JSON_UNESCAPED_SLASHES;

            if ($this->option('pretty')) {
                $flags |= JSON_PRETTY_PRINT;
            }

            $this->line((string) json_encode($payload, $flags));

            return $exitCode;
        }

        $this->components->info(
            $payload['status'] === 'preview'
                ? 'Disbursement settlement journal backfill preview'
                : 'Disbursement settlement journal backfill '.$payload['status'],
        );

        if (isset($payload['message'])) {
            $this->components->error((string) $payload['message']);
        }

        foreach ($payload['candidates'] ?? [] as $candidate) {
            $this->line(sprintf(
                '%s [%s]: %s',
                $candidate['pay_code'],
                $candidate['reconciliation_id'],
                $candidate['status'],
            ));
        }

        return $exitCode;
    }
}
