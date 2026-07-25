<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Services\Treasury\MissingDisbursementPostingRepairService;
use Throwable;

final class RepairMissingDisbursementPostingsCommand extends Command
{
    protected $signature = 'x-change:treasury:repair-missing-disbursement-postings
        {--connection= : Explicit Treasury connection to inspect}
        {--reconciliation=* : Exact reconciliation IDs reported by the dry run}
        {--commit : Execute the guarded append-only repair}
        {--json : Emit a sanitized machine-readable result}';

    protected $description = 'Repair settled system disbursements missing append-only Treasury principal postings';

    public function handle(
        MissingDisbursementPostingRepairService $repair,
    ): int {
        $connection = trim((string) $this->option('connection'));
        $reconciliationIds = array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            (array) $this->option('reconciliation'),
        ));
        $committed = (bool) $this->option('commit');

        if ($connection === '') {
            return $this->failure(
                'An explicit --connection is required.',
                $committed,
            );
        }

        try {
            $result = $committed
                ? $repair->repair($connection, $reconciliationIds)
                : $repair->inspect($connection, $reconciliationIds);
        } catch (TreasuryConfigurationException $exception) {
            return $this->failure($exception->getMessage(), $committed);
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure(
                'The missing-disbursement repair could not be completed safely.',
                $committed,
            );
        }

        $payload = [
            ...$result->toArray(),
            'committed' => $committed,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Connection', 'Provider', 'Principal', 'Candidates', 'Repaired', 'Status'],
                [[
                    $payload['connection_reference'],
                    $payload['provider'],
                    $payload['principal_amount_minor'],
                    $payload['candidate_count'],
                    $payload['repaired_count'],
                    $payload['status'],
                ]],
            );

            if (! $committed && $payload['status'] === 'dry_run') {
                $this->components->warn(
                    'Review the reconciliation IDs, then repeat with each --reconciliation=<id> and --commit.',
                );
            }
        }

        return self::SUCCESS;
    }

    private function failure(string $message, bool $committed): int
    {
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'status' => 'rejected',
                'committed' => $committed,
                'message' => $message,
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->components->error($message);
        }

        return self::FAILURE;
    }
}
