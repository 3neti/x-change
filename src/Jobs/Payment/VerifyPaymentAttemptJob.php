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
use LBHurtado\XChange\Actions\Payment\VerifyPaymentAttempt;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Models\PaymentAttempt;

class VerifyPaymentAttemptJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public int $uniqueFor;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(
        public readonly int $paymentAttemptId,
        public readonly string $providerCode,
        public readonly PaymentVerificationTrigger $trigger,
    ) {
        $this->uniqueFor = (int) config('x-change.payment.attempts.verification_lock_seconds', 120);
    }

    /**
     * @return list<object>
     */
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

    public function handle(VerifyPaymentAttempt $verify): void
    {
        $attempt = PaymentAttempt::query()->findOrFail($this->paymentAttemptId);

        if ($attempt->provider_code !== $this->providerCode) {
            return;
        }

        $verify->handle($attempt, $this->trigger);
    }
}
