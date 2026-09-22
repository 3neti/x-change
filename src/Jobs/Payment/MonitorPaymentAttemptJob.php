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
use LBHurtado\XChange\Actions\Payment\MonitorPaymentAttempt;
use LBHurtado\XChange\Models\PaymentAttempt;
use Throwable;

final class MonitorPaymentAttemptJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 120;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(
        public readonly int $paymentAttemptId,
        public readonly string $providerCode,
    ) {
        $this->onQueue(VerifyPaymentAttemptJob::Queue);
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->releaseAfter(5)
                ->expireAfter($this->uniqueFor)
                ->shared(),
            new RateLimited('x-change-payment-verification'),
        ];
    }

    public function uniqueId(): string
    {
        return 'payment-attempt:'.$this->paymentAttemptId;
    }

    public function handle(MonitorPaymentAttempt $monitor): void
    {
        $attempt = PaymentAttempt::query()->findOrFail($this->paymentAttemptId);

        if ($attempt->provider_code === $this->providerCode) {
            $monitor->handle($attempt);
        }
    }

    public function failed(Throwable $exception): void
    {
        app(RecordExternalJobFailure::class)->handle(
            jobType: class_basename(self::class),
            subjectType: 'payment_attempt',
            subjectId: $this->paymentAttemptId,
            failure: $exception,
            providerCode: $this->providerCode,
            trigger: 'schedule',
        );
    }
}
