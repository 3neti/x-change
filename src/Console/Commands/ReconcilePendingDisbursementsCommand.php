<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Models\DisbursementReconciliation;

class ReconcilePendingDisbursementsCommand extends Command
{
    protected $signature = 'xchange:reconcile:pending
        {--limit=50 : Maximum number of records to process}
        {--json : Output JSON}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Reconcile pending or unresolved disbursements against the payout provider.';

    public function handle(DisbursementReconciliationContract $reconciler): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $rows = DisbursementReconciliation::query()
            ->where(function ($query) {
                $query->whereIn('status', ['pending', 'unknown'])
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('status', 'failed')
                            ->where('needs_review', true);
                    });
            })
            ->orderBy('attempted_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $results = [];
        $updated = 0;

        foreach ($rows as $row) {
            try {
                $result = $reconciler->reconcile($row->fresh());

                $results[] = [
                    'voucher_code' => $row->voucher_code,
                    'reconciliation_id' => $row->id,
                    'before_status' => $result['before_status'] ?? null,
                    'fetched_status' => $result['fetched_status'] ?? null,
                    'resolved_status' => $result['resolved_status'] ?? null,
                    'updated' => (bool) ($result['updated'] ?? false),
                ];

                if (($result['updated'] ?? false) === true) {
                    $updated++;
                }
            } catch (\Throwable $e) {
                $results[] = [
                    'voucher_code' => $row->voucher_code,
                    'reconciliation_id' => $row->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $payload = [
            'processed' => count($results),
            'updated' => $updated,
            'results' => $results,
        ];

        if ($this->option('json')) {
            $flags = JSON_UNESCAPED_SLASHES;
            if ($this->option('pretty')) {
                $flags |= JSON_PRETTY_PRINT;
            }

            $this->line(json_encode($payload, $flags));

            return self::SUCCESS;
        }

        $this->info("Processed: {$payload['processed']}");
        $this->info("Updated: {$payload['updated']}");

        foreach ($results as $result) {
            if (isset($result['error'])) {
                $this->warn(sprintf(
                    '%s [%s]: %s',
                    $result['voucher_code'],
                    $result['reconciliation_id'],
                    $result['error'],
                ));

                continue;
            }

            $this->line(sprintf(
                '%s [%s]: %s -> %s%s',
                $result['voucher_code'],
                $result['reconciliation_id'],
                $result['before_status'] ?? 'n/a',
                $result['resolved_status'] ?? 'n/a',
                ($result['updated'] ?? false) ? ' (updated)' : '',
            ));
        }

        return self::SUCCESS;
    }
}
