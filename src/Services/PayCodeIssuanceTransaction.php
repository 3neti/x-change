<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceBusy;

final class PayCodeIssuanceTransaction
{
    public function run(Closure $callback): mixed
    {
        $connection = DB::connection();
        $attempts = $this->transactionAttempts();

        $this->configureSqliteBusyTimeout($connection);

        try {
            return $connection->transaction($callback, $attempts);
        } catch (DeadlockException) {
            Log::warning('Pay Code issuance exhausted its database concurrency retries.', [
                'connection' => $connection->getName(),
                'driver' => $connection->getDriverName(),
                'attempts' => $attempts,
            ]);

            throw new PayCodeIssuanceBusy;
        }
    }

    private function transactionAttempts(): int
    {
        return max(1, min(
            10,
            (int) config('x-change.pay_code_issuance.transaction_attempts', 5),
        ));
    }

    private function configureSqliteBusyTimeout(ConnectionInterface $connection): void
    {
        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $milliseconds = max(0, min(
            30000,
            (int) config(
                'x-change.pay_code_issuance.sqlite_busy_timeout_milliseconds',
                5000,
            ),
        ));

        $connection->unprepared('PRAGMA busy_timeout = '.$milliseconds);
    }
}
