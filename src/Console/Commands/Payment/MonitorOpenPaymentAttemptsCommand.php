<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Payment;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use LBHurtado\XChange\Jobs\Payment\MonitorPaymentAttemptJob;
use LBHurtado\XChange\Models\PaymentAttempt;

final class MonitorOpenPaymentAttemptsCommand extends Command
{
    protected $signature = 'xchange:payments:monitor-open
        {--provider=netbank : Payment provider code}
        {--limit= : Maximum QR attempts to inspect in this run}
        {--created-since= : Restrict to attempts created at or after this UTC timestamp}';

    protected $description = 'Queue incoming provider payment monitoring within the reconciliation window';

    public function handle(): int
    {
        $provider = strtolower(trim((string) $this->option('provider')));

        if ($provider === '' || ! (bool) config("x-change.funding.providers.{$provider}.enabled", false)) {
            $this->components->error('The payment provider is not enabled.');

            return self::INVALID;
        }

        $batchSize = max(1, (int) config('x-change.payment.monitoring.scheduled_batch_size', 50));
        $requested = $this->option('limit');
        $limit = $requested === null ? $batchSize : min($batchSize, max(1, (int) $requested));
        $days = max(1, (int) config('x-change.payment.attempts.late_reconciliation_days', 7));
        $interval = max(60, (int) config('x-change.payment.monitoring.minimum_interval_seconds', 300));
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
            ->whereNotNull('instructions_created_at')
            ->where('expires_at', '>', now()->subDays($days))
            ->where(fn ($query) => $query->whereNull('last_monitored_at')->orWhere('last_monitored_at', '<=', now()->subSeconds($interval)))
            ->orderByRaw('CASE WHEN last_monitored_at IS NULL THEN 0 ELSE 1 END')
            ->oldest('last_monitored_at')
            ->oldest('id')
            ->limit($limit)
            ->each(function (PaymentAttempt $attempt) use ($provider, &$queued): void {
                MonitorPaymentAttemptJob::dispatch((int) $attempt->getKey(), $provider);
                $queued++;
            });

        $this->components->info("Queued {$queued} payment monitoring check(s).");

        return self::SUCCESS;
    }
}
