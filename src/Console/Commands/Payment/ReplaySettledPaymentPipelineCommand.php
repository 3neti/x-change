<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Payment;

use Illuminate\Console\Command;
use LBHurtado\XChange\Jobs\Payment\ProcessSettledPaymentJob;
use LBHurtado\XChange\Models\SettledPaymentProcess;

final class ReplaySettledPaymentPipelineCommand extends Command
{
    protected $signature = 'xchange:payment-feedback:replay {--limit=100}';

    protected $description = 'Requeue pending settled payment contact and feedback work';

    public function handle(): int
    {
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
