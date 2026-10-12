<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Payment;

use Illuminate\Console\Command;
use LBHurtado\XChange\Jobs\Payment\ProcessSettledPaymentJob;
use LBHurtado\XChange\Jobs\Payment\RecoverSettledPayerIdentityJob;
use LBHurtado\XChange\Models\SettledPaymentProcess;

final class ReplaySettledPaymentPipelineCommand extends Command
{
    protected $signature = 'xchange:payment-feedback:replay
        {--limit=100 : Maximum pending processes to queue}
        {--recover-payer= : One completed process with skipped SMS to recheck against NetBank}';

    protected $description = 'Requeue pending settled payment contact and feedback work';

    public function handle(): int
    {
        if ($this->option('recover-payer') !== null) {
            $process = SettledPaymentProcess::query()->find((int) $this->option('recover-payer'));

            if ($process === null || ! $process->sms_enabled_snapshot
                || $process->sms_status !== 'skipped' || $process->contact_id !== null
                || $process->payment_type !== 'funding_settlement') {
                $this->components->error('No eligible settled payment process was found.');

                return self::INVALID;
            }

            RecoverSettledPayerIdentityJob::dispatch((int) $process->getKey());
            $this->components->info('Queued one settled payer identity check.');

            return self::SUCCESS;
        }

        $limit = max(1, min(1000, (int) $this->option('limit')));
        $ids = SettledPaymentProcess::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            ProcessSettledPaymentJob::dispatch((int) $id);
        }

        $this->info("Queued {$ids->count()} settled payment processes.");

        return self::SUCCESS;
    }
}
