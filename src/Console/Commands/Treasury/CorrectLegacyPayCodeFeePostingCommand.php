<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Treasury\LegacyPayCodeFeePostingCorrectionService;
use Throwable;

final class CorrectLegacyPayCodeFeePostingCommand extends Command
{
    protected $signature = 'x-change:treasury:correct-pay-code-fee-posting
        {run : Durable lifecycle run record reference}
        {--commit : Execute the guarded append-only correction}
        {--json : Emit a machine-readable result}';

    protected $description = 'Correct a legacy live Pay Code posting that included a system fee in provider Inventory';

    public function handle(
        LegacyPayCodeFeePostingCorrectionService $correction,
    ): int {
        try {
            $result = (bool) $this->option('commit')
                ? $correction->correct((string) $this->argument('run'))
                : $correction->inspect((string) $this->argument('run'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $payload = [
            ...$result->toArray(),
            'committed' => (bool) $this->option('commit'),
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Run', 'Provider', 'Connection', 'Principal', 'Correction', 'Status'],
                [[
                    $payload['run_reference'],
                    $payload['provider'],
                    $payload['connection_reference'],
                    $payload['beneficiary_amount_minor'],
                    $payload['excess_fee_amount_minor'],
                    $payload['status'],
                ]],
            );
        }

        return self::SUCCESS;
    }
}
