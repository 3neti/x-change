<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Payment;

use Illuminate\Console\Command;
use LBHurtado\XChange\Jobs\Payment\MonitorPaymentAttemptJob;
use LBHurtado\XChange\Models\PaymentAttempt;

final class MonitorOpenPaymentAttemptsCommand extends Command
{
    protected $signature = 'xchange:payments:monitor-open
        {--provider=netbank : Payment provider code}
        {--limit= : Maximum QR attempts to inspect in this run}';

    protected $description = 'Queue read-only monitoring of incoming provider payments for live QR attempts';

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
        $grace = max(0, (int) config('x-change.payment.monitoring.expiry_grace_seconds', 300));
        $queued = 0;

        PaymentAttempt::query()
            ->where('provider_code', $provider)
            ->whereNotNull('instructions_created_at')
            ->where('expires_at', '>', now()->subSeconds($grace))
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
