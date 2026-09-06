<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Services\Treasury\VoucherCollectionTreasuryCorrectionService;
use Throwable;

final class CorrectVoucherCollectionTreasuryPostingCommand extends Command
{
    protected $signature = 'x-change:treasury:correct-voucher-collection
        {code : Exact Pay Code to inspect or correct}
        {--dry-run : Explicitly inspect without writing}
        {--commit : Execute the guarded append-only correction}
        {--json : Emit a sanitized machine-readable result}';

    protected $description = 'Correct one settled voucher collection missing its Treasury posting';

    public function handle(VoucherCollectionTreasuryCorrectionService $correction): int
    {
        $commit = (bool) $this->option('commit');

        if ($commit && (bool) $this->option('dry-run')) {
            return $this->failure('Choose either --dry-run or --commit, not both.', $commit);
        }

        try {
            $result = $commit
                ? $correction->correct((string) $this->argument('code'))
                : $correction->inspect((string) $this->argument('code'));
        } catch (TreasuryConfigurationException $exception) {
            return $this->failure($exception->getMessage(), $commit);
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure(
                'The voucher collection Treasury correction could not be completed safely.',
                $commit,
            );
        }

        $payload = $result->toArray();

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Pay Code', 'Collection', 'Amount', 'Wallet', 'Client Funds', 'Divergence', 'Status'],
                [[
                    $payload['voucher_code'],
                    $payload['collection_id'],
                    $payload['amount_minor'],
                    $payload['compatibility_balance_minor'],
                    $payload['client_funds_balance_minor'],
                    $payload['divergence_minor'],
                    $payload['status'],
                ]],
            );

            if (! $commit && $payload['status'] === 'ready') {
                $this->components->warn(
                    'Review the exact balances, then repeat this Pay Code with --commit.',
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
