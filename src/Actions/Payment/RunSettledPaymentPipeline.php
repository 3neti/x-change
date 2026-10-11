<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Pipeline\Pipeline;
use LBHurtado\XChange\Models\SettledPaymentProcess;

final readonly class RunSettledPaymentPipeline
{
    public function __construct(private Pipeline $pipeline) {}

    public function handle(int $processId): void
    {
        $process = SettledPaymentProcess::query()->findOrFail($processId);

        if ($process->status === 'completed') {
            return;
        }

        $pipes = (array) config('x-change.payment_feedback.pipes', []);

        $this->pipeline->send($process)->through($pipes)->thenReturn();
        $process->forceFill(['status' => 'completed', 'processed_at' => now()])->save();
    }
}
