<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Payment;

use Illuminate\Console\Command;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Jobs\Payment\VerifyPaymentAttemptJob;
use LBHurtado\XChange\Models\PaymentAttempt;

class VerifyOpenPaymentAttemptsCommand extends Command
{
    protected $signature = 'xchange:payments:verify-open
        {--provider=netbank : Payment provider code}
        {--limit= : Maximum attempts to inspect in this run}';

    protected $description = 'Queue authoritative provider verification for open Payment Attempts';

    public function handle(): int
    {
        $provider = strtolower(trim((string) $this->option('provider')));

        if ($provider === '' || ! config()->has("x-change.funding.providers.{$provider}")) {
            $this->components->error('The requested payment provider is not configured.');

            return self::INVALID;
        }

        if (! (bool) config("x-change.funding.providers.{$provider}.enabled", false)) {
            $this->components->info("Payment provider [{$provider}] is disabled; no checks were queued.");

            return self::SUCCESS;
        }

        $configuredLimit = max(
            1,
            (int) config('x-change.payment.attempts.scheduled_batch_size', 100),
        );
        $requestedLimit = $this->option('limit');
        $limit = $requestedLimit === null
            ? $configuredLimit
            : min($configuredLimit, max(1, (int) $requestedLimit));
        $queued = 0;

        PaymentAttempt::query()
            ->where('provider_code', $provider)
            ->whereIn('status', [
                PaymentAttemptStatus::AwaitingPayment,
                PaymentAttemptStatus::Verifying,
                PaymentAttemptStatus::Verified,
            ])
            ->oldest('expires_at')
            ->oldest('id')
            ->limit($limit)
            ->each(function (PaymentAttempt $attempt) use ($provider, &$queued): void {
                VerifyPaymentAttemptJob::dispatch(
                    paymentAttemptId: (int) $attempt->getKey(),
                    providerCode: $provider,
                    trigger: PaymentVerificationTrigger::Schedule,
                );
                $queued++;
            });

        $this->components->info("Queued {$queued} payment verification check(s).");

        return self::SUCCESS;
    }
}
