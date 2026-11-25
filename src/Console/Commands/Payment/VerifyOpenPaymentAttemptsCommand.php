<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Payment;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Jobs\Payment\VerifyPaymentAttemptJob;
use LBHurtado\XChange\Models\PaymentAttempt;

class VerifyOpenPaymentAttemptsCommand extends Command
{
    protected $signature = 'xchange:payments:verify-open
        {--provider=netbank : Payment provider code}
        {--limit= : Maximum attempts to inspect in this run}
        {--created-since= : Restrict to attempts created at or after this UTC timestamp}';

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
        $since = $this->option('created-since');
        $createdSince = null;
        if ($since !== null) {
            try {
                if (! is_string($since) || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', $since) !== 1) {
                    throw new \InvalidArgumentException;
                }
                $createdSince = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $since, 'UTC');
                if ($createdSince === false || $createdSince->format('Y-m-d\TH:i:s\Z') !== $since) {
                    throw new \InvalidArgumentException;
                }
            } catch (\Throwable) {
                $this->components->error('The creation cutoff must be a valid UTC timestamp.');

                return self::INVALID;
            }
        }

        $queued = 0;

        PaymentAttempt::query()
            ->where('provider_code', $provider)
            ->when($createdSince !== null, fn ($query) => $query->where('created_at', '>=', $createdSince))
            ->where(function ($query): void {
                $query->whereIn('status', [
                    PaymentAttemptStatus::AwaitingPayment,
                    PaymentAttemptStatus::Verifying,
                    PaymentAttemptStatus::Verified,
                ])->orWhere(function ($late): void {
                    $late->where('status', PaymentAttemptStatus::Expired)
                        ->whereNotNull('instructions_created_at')
                        ->where('expires_at', '>=', now()->subDays(max(1, (int) config('x-change.payment.attempts.late_reconciliation_days', 7))))
                        ->where(function ($checked): void {
                            $checked->whereNull('last_checked_at')
                                ->orWhere('last_checked_at', '<=', now()->subMinutes(max(1, (int) config('x-change.payment.attempts.late_reconciliation_interval_minutes', 15))));
                        });
                });
            })
            ->orderByRaw('case when last_checked_at is null then 0 else 1 end')
            ->oldest('last_checked_at')
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
