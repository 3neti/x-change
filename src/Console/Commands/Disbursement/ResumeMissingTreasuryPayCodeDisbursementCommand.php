<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Disbursement;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Disbursement\ResumeMissingTreasuryPayCodeDisbursement;
use Throwable;

final class ResumeMissingTreasuryPayCodeDisbursementCommand extends Command
{
    protected $signature = 'xchange:disbursement:resume-missing-treasury
        {code : Pay Code}
        {--confirm-no-provider-transfer : Confirm provider evidence shows no prior transfer}
        {--json : Output JSON}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Guardedly submit a redeemed Treasury Pay Code whose provider attempt was never created.';

    public function handle(ResumeMissingTreasuryPayCodeDisbursement $resume): int
    {
        try {
            $payload = $resume->handle(
                (string) $this->argument('code'),
                (bool) $this->option('confirm-no-provider-transfer'),
            );
        } catch (Throwable $exception) {
            $payload = [
                'schema' => 'x-change.missing-treasury-disbursement-recovery.v1',
                'success' => false,
                'pay_code' => mb_strtoupper(trim((string) $this->argument('code'))),
                'message' => $exception->getMessage(),
            ];
        }

        if ($this->option('json')) {
            $flags = JSON_UNESCAPED_SLASHES;

            if ($this->option('pretty')) {
                $flags |= JSON_PRETTY_PRINT;
            }

            $this->line((string) json_encode($payload, $flags));
        } elseif ($payload['success']) {
            $this->components->info('Missing Treasury Pay Code disbursement submitted.');
        } else {
            $this->components->error((string) ($payload['message'] ?? 'Recovery failed.'));
        }

        return $payload['success'] ? self::SUCCESS : self::FAILURE;
    }
}
