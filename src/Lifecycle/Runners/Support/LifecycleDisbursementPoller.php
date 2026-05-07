<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners\Support;

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Lifecycle\Output\LifecycleOutputContract;

final class LifecycleDisbursementPoller
{
    public function poll(
        string $code,
        int $timeout,
        int $poll,
        ?int $maxPolls = null,
        bool $acceptPending = false,
        ?LifecycleOutputContract $output = null,
    ): array {
        $start = time();
        $attempt = 0;

        $last = [
            'voucher_code' => $code,
            'current_status' => 'unknown',
        ];

        do {
            $attempt++;

            Artisan::call('xchange:disbursement:check', [
                'code' => $code,
                '--sync' => true,
                '--json' => true,
            ]);

            $decoded = json_decode(trim(Artisan::output()), true);

            if (is_array($decoded)) {
                $last = $decoded;

                $status = $decoded['current_status'] ?? null;
                $providerTransactionId = $decoded['provider_transaction_id'] ?? null;
                $needsReview = (bool) ($decoded['needs_review'] ?? false);
                $provider = $decoded['provider'] ?? null;

                if ($output && ! $output->isJson()) {
                    $elapsed = time() - $start;
                    $maxPollsLabel = $maxPolls !== null ? (string) $maxPolls : '∞';

                    $output->line(sprintf(
                        '[poll %d/%s | %ss] status=%s provider_tx=%s needs_review=%s',
                        $attempt,
                        $maxPollsLabel,
                        $elapsed,
                        $status ?? 'unknown',
                        $providerTransactionId ?: 'n/a',
                        $needsReview ? 'yes' : 'no',
                    ));
                }

                if (in_array($status, ['succeeded', 'failed'], true)) {
                    return $decoded;
                }

                if (
                    $acceptPending
                    && $status === 'pending'
                    && ! $needsReview
                    && is_string($provider) && $provider !== ''
                    && is_string($providerTransactionId) && $providerTransactionId !== ''
                ) {
                    if ($output && ! $output->isJson()) {
                        $output->info('Trusted pending transaction accepted as good enough.');
                    }

                    return $decoded;
                }
            } elseif ($output && ! $output->isJson()) {
                $elapsed = time() - $start;

                $output->warn("[poll {$elapsed}s] unable to decode disbursement status response");
            }

            if ($maxPolls !== null && $attempt >= $maxPolls) {
                break;
            }

            if ((time() - $start) >= $timeout) {
                break;
            }

            sleep($poll);
        } while (true);

        $last['current_status'] = $last['current_status'] ?? 'timeout';
        $last['timed_out'] = true;
        $last['poll_attempts'] = $attempt;

        return $last;
    }
}
