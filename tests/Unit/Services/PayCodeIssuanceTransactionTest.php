<?php

declare(strict_types=1);

use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceBusy;
use LBHurtado\XChange\Services\PayCodeIssuanceTransaction;

it('retries the complete issuance transaction after database contention', function (): void {
    config()->set('x-change.pay_code_issuance.transaction_attempts', 3);
    config()->set('x-change.pay_code_issuance.sqlite_busy_timeout_milliseconds', 1234);

    $attempts = 0;

    $result = app(PayCodeIssuanceTransaction::class)->run(
        function () use (&$attempts): string {
            $attempts++;

            if ($attempts < 3) {
                throw new DeadlockException('database is locked');
            }

            return 'issued-once';
        },
    );

    $busyTimeout = DB::selectOne('PRAGMA busy_timeout');

    expect($result)->toBe('issued-once')
        ->and($attempts)->toBe(3)
        ->and((int) $busyTimeout->timeout)->toBe(1234);
});

it('returns a sanitized retryable exception after contention retries are exhausted', function (): void {
    config()->set('x-change.pay_code_issuance.transaction_attempts', 2);

    $attempts = 0;

    try {
        app(PayCodeIssuanceTransaction::class)->run(
            function () use (&$attempts): never {
                $attempts++;

                throw new DeadlockException(
                    'database is locked while inserting raw private voucher payload',
                );
            },
        );

        $this->fail('Expected PayCodeIssuanceBusy to be thrown.');
    } catch (PayCodeIssuanceBusy $exception) {
        expect($attempts)->toBe(2)
            ->and($exception->getMessage())->toBe(PayCodeIssuanceBusy::Message)
            ->and($exception->getMessage())->not->toContain('insert')
            ->and($exception->getMessage())->not->toContain('payload');
    }
});
