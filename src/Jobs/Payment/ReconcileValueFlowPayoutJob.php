<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Payment;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use LBHurtado\XChange\Actions\Operations\RecordExternalJobFailure;
use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Queue\Concerns\HasSafeQueueTags;
use LBHurtado\XChange\Queue\Concerns\ProviderVerificationRetryPolicy;
use Throwable;

final class ReconcileValueFlowPayoutJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, HasSafeQueueTags, InteractsWithQueue, ProviderVerificationRetryPolicy, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 120;

    public array $backoff = [30, 120, 300, 900];

    public function __construct(public readonly int $payoutId, public readonly string $providerCode)
    {
        $this->onQueue(VerifyPaymentAttemptJob::Queue);
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'value-flow-status:'.$this->payoutId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->releaseAfter(5)->expireAfter($this->uniqueFor)->shared(),
            new RateLimited('x-change-provider-verification')];
    }

    public function handle(DisbursementReconciliationContract $reconciler): void
    {
        $payout = DisbursementReconciliation::query()->where('claim_type', 'value_flow')->find($this->payoutId);
        if ($payout === null || $payout->provider !== $this->providerCode || blank($payout->provider_transaction_id)
            || ! in_array($payout->status, ['pending', 'unknown', 'succeeded'], true)
            || $payout->internal_status === 'finalized') {
            return;
        }
        $reconciler->reconcile($payout);
    }

    public function failed(Throwable $exception): void
    {
        app(RecordExternalJobFailure::class)->handle(jobType: class_basename(self::class), subjectType: 'value_flow_payout', subjectId: $this->payoutId, failure: $exception, trigger: 'owner');
    }
}
