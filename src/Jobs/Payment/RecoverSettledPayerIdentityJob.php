<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Payment;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use LBHurtado\XChange\Actions\Payment\RecoverSettledPayerIdentity;
use Throwable;

final class RecoverSettledPayerIdentityJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 6;

    /** @var list<int> */
    public array $backoff = [30, 60, 120, 180, 300];

    public function __construct(public readonly int $processId)
    {
        $this->onQueue('x-change-feedback');
    }

    public function handle(RecoverSettledPayerIdentity $recovery): void
    {
        if (! $recovery->handle($this->processId) && $this->attempts() < $this->tries) {
            $this->release($this->backoff[min(max(0, $this->attempts() - 1), count($this->backoff) - 1)]);
        }
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['package:x-change', 'lane:feedback', 'job:RecoverSettledPayerIdentityJob'];
    }
}
