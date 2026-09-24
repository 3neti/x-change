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
use LBHurtado\XChange\Actions\Settlement\CompleteAutomaticDemonstrationPolicy;
use LBHurtado\XChange\Models\CompletionClaimEvidenceProjection;
use LBHurtado\XChange\Services\Settlement\AutomaticDemonstrationPolicy;
use Throwable;

final class CompleteAutomaticDemonstrationPolicyJob implements ShouldBeUnique, ShouldQueue
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

    public function __construct(public readonly string $projectionReference)
    {
        $this->onQueue((string) config('x-change.redemption.feedback.queue', 'x-change-feedback'));
    }

    public function uniqueId(): string
    {
        return 'automatic-demo-policy:'.$this->projectionReference;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->releaseAfter(10)->expireAfter(120)];
    }

    public function handle(CompleteAutomaticDemonstrationPolicy $complete, AutomaticDemonstrationPolicy $policy): void
    {
        $projection = CompletionClaimEvidenceProjection::query()->where('reference', $this->projectionReference)->firstOrFail();
        if ($policy->enabledFor($projection)) {
            $complete->handle($projection);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Automatic demonstration policy completion retries exhausted.', [
            'projection_reference' => $this->projectionReference,
            'failure_class' => $exception === null ? null : $exception::class,
        ]);
    }
}
