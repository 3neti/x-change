<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Payment;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Payment\VerifiedPaymentSettlementRecoveryService;
use Throwable;

final class ResumeVerifiedPaymentAttemptsCommand extends Command
{
    protected $signature = 'x-change:payments:resume-verified
        {--attempt=* : Exact Payment Attempt reference to inspect or settle}
        {--dry-run : Explicitly inspect without writing}
        {--commit : Settle the exact verified attempts}
        {--json : Emit sanitized machine-readable results}';

    protected $description = 'Resume exact verified Payment Attempts without calling the provider';

    public function handle(VerifiedPaymentSettlementRecoveryService $recovery): int
    {
        $commit = (bool) $this->option('commit');
        $dryRun = (bool) $this->option('dry-run');
        $references = array_values((array) $this->option('attempt'));

        if ($commit && $dryRun) {
            return $this->failure('Choose either --dry-run or --commit, not both.', $commit);
        }

        if ($commit && $references === []) {
            return $this->failure('--commit requires at least one exact --attempt reference.', true);
        }

        try {
            $results = $commit
                ? $recovery->recover($references)
                : $recovery->inspect($references);
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure(
                'Verified Payment Attempt recovery was rejected by its safety checks.',
                $commit,
            );
        }

        $payload = array_map(
            static fn ($result): array => $result->toArray(),
            $results,
        );

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'mode' => $commit ? 'commit' : 'dry_run',
                'writes_performed' => $commit,
                'provider_calls' => false,
                'results' => $payload,
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Attempt', 'Voucher', 'Amount', 'Currency', 'Status'],
                array_map(static fn (array $result): array => [
                    $result['attempt_reference'],
                    $result['voucher_reference'],
                    $result['amount_minor'],
                    $result['currency'],
                    $result['status'],
                ], $payload),
            );
        }

        return self::SUCCESS;
    }

    private function failure(string $message, bool $committed): int
    {
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'status' => 'rejected',
                'committed' => $committed,
                'provider_calls' => false,
                'message' => $message,
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->components->error($message);
        }

        return self::FAILURE;
    }
}
