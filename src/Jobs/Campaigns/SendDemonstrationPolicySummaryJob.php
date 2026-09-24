<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Campaigns;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Actions\Campaigns\SendDemonstrationPolicySummarySms;
use LBHurtado\XChange\Models\PolicyCompletionOutcome;
use Throwable;

final class SendDemonstrationPolicySummaryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly string $outcomeReference)
    {
        $this->onQueue((string) config('x-change.redemption.feedback.queue', 'x-change-feedback'));
    }

    public function uniqueId(): string
    {
        return 'campaign-demo-policy:'.$this->outcomeReference;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->releaseAfter(10)->expireAfter(120)];
    }

    public function handle(SendDemonstrationPolicySummarySms $sms): void
    {
        $sms->handle(PolicyCompletionOutcome::query()->where('reference', $this->outcomeReference)->firstOrFail());
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Demonstration policy summary notification retries exhausted.', [
            'outcome_reference' => $this->outcomeReference,
            'failure_class' => $exception === null ? null : $exception::class,
        ]);
    }
}
