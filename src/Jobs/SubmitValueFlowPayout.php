<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use LBHurtado\XChange\Queue\Concerns\HasSafeQueueTags;
use LBHurtado\XChange\Services\Execution\ValueFlowPayoutService;

final class SubmitValueFlowPayout implements ShouldQueue
{
    use HasSafeQueueTags;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $reconciliationId)
    {
        $this->onQueue('x-change-funding');
    }

    public function handle(ValueFlowPayoutService $payouts): void
    {
        $payouts->submit($this->reconciliationId);
    }
}
