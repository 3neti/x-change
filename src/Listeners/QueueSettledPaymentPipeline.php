<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Listeners;

use LBHurtado\XChange\Events\PaymentSettled;
use LBHurtado\XChange\Jobs\Payment\ProcessSettledPaymentJob;

final class QueueSettledPaymentPipeline
{
    public function handle(PaymentSettled $event): void
    {
        ProcessSettledPaymentJob::dispatch($event->processId);
    }
}
