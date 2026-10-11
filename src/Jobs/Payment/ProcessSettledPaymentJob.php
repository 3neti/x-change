<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Payment;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use LBHurtado\XChange\Actions\Payment\RunSettledPaymentPipeline;

final class ProcessSettledPaymentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly int $processId)
    {
        $this->onQueue('x-change-feedback');
    }

    public function handle(RunSettledPaymentPipeline $pipeline): void
    {
        $pipeline->handle($this->processId);
    }
}
