<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Disbursement;

use Illuminate\Console\Command;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Actions\Disbursement\RestoreUnsubmittedPayoutCorrection;
use Throwable;

final class RestoreUnsubmittedPayoutCorrectionCommand extends Command
{
    protected $signature = 'xchange:disbursement:restore-unsubmitted-correction
        {code : Pay Code}
        {--reconciliation= : Exact payout-recovery reconciliation ID}
        {--evidence-reference= : Independent evidence proving the provider did not accept the submission}
        {--confirm-provider-not-submitted : Confirm no provider operation exists}
        {--commit : Apply the append-only restoration}
        {--json : Output JSON}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Guardedly restore a payout correction that was never accepted by the provider.';

    public function handle(
        RestoreUnsubmittedPayoutCorrection $restore,
        SystemUserResolverContract $systemUsers,
    ): int {
        if (! $this->option('commit')) {
            return $this->render([
                'schema' => 'x-change.unsubmitted-payout-correction-restoration.v1',
                'success' => false,
                'mode' => 'preview',
                'pay_code' => mb_strtoupper(trim((string) $this->argument('code'))),
                'message' => 'No changes were made. Pass --commit after verifying provider evidence.',
            ]);
        }

        try {
            $payload = $restore->handle(
                code: (string) $this->argument('code'),
                restoredBy: $systemUsers->resolve(),
                evidenceReference: (string) $this->option('evidence-reference'),
                confirmedProviderDidNotAccept: (bool) $this->option('confirm-provider-not-submitted'),
                reconciliationId: $this->reconciliationId(),
            );
            $payload['mode'] = 'commit';
        } catch (Throwable $exception) {
            $payload = [
                'schema' => 'x-change.unsubmitted-payout-correction-restoration.v1',
                'success' => false,
                'mode' => 'commit',
                'pay_code' => mb_strtoupper(trim((string) $this->argument('code'))),
                'message' => $exception->getMessage(),
            ];
        }

        return $this->render($payload);
    }

    private function reconciliationId(): ?int
    {
        $value = $this->option('reconciliation');

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function render(array $payload): int
    {
        if ($this->option('json')) {
            $flags = JSON_UNESCAPED_SLASHES;

            if ($this->option('pretty')) {
                $flags |= JSON_PRETTY_PRINT;
            }

            $this->line((string) json_encode($payload, $flags));
        } elseif ($payload['success']) {
            $this->components->info('Payout correction restored for explicit retry.');
        } else {
            $this->components->error((string) $payload['message']);
        }

        return $payload['success'] ? self::SUCCESS : self::FAILURE;
    }
}
