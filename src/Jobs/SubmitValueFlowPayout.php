<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use LBHurtado\XChange\Services\Execution\ValueFlowPayoutService;

final class SubmitValueFlowPayout implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $reconciliationId) {}

    public function handle(ValueFlowPayoutService $payouts): void
    {
        $payouts->submit($this->reconciliationId);
    }
}
