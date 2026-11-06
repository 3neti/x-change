<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\SyncQueue;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Jobs\Funding\SyncStandingFundingAddressJob;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingIntentJob;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingWebhookReceiptJob;
use LBHurtado\XChange\Jobs\Payment\MonitorPaymentAttemptJob;
use LBHurtado\XChange\Jobs\Payment\ReconcileValueFlowPayoutJob;
use LBHurtado\XChange\Jobs\Payment\VerifyAdditionalQrPaymentJob;
use LBHurtado\XChange\Jobs\Payment\VerifyPaymentAttemptJob;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Providers\XChangeServiceProvider;

beforeEach(function (): void {
    Queue::fake();
    config()->set('x-change.funding.providers.netbank.enabled', true);
    config()->set('x-change.payment.attempts.scheduled_verification_enabled', true);
    config()->set('x-change.payment.attempts.scheduled_batch_size', 100);
    config()->set('x-change.payment.monitoring.scheduled_enabled', false);
});

it('queues only eligible enabled-provider Payment Attempts within the configured batch', function (): void {
    scheduledPaymentAttempt(expiresAt: now()->addMinutes(20));
    scheduledPaymentAttempt(expiresAt: now()->addMinutes(10));
    scheduledPaymentAttempt(expiresAt: now()->addMinutes(30));
    scheduledPaymentAttempt(status: PaymentAttemptStatus::Settled);
    config()->set('x-change.payment.attempts.scheduled_batch_size', 2);

    $this->artisan('xchange:payments:verify-open', [
        '--provider' => 'netbank',
        '--limit' => 99,
    ])->assertSuccessful();

    Queue::assertPushed(VerifyPaymentAttemptJob::class, 2);
    Queue::assertPushed(
        VerifyPaymentAttemptJob::class,
        fn (VerifyPaymentAttemptJob $job): bool => $job->trigger === PaymentVerificationTrigger::Schedule
            && $job->providerCode === 'netbank',
    );
});

it('does not queue Payment Attempts for a disabled provider', function (): void {
    scheduledPaymentAttempt();
    config()->set('x-change.funding.providers.netbank.enabled', false);

    $this->artisan('xchange:payments:verify-open')
        ->expectsOutputToContain('disabled')
        ->assertSuccessful();

    Queue::assertNotPushed(VerifyPaymentAttemptJob::class);
});

it('queues verified Payment Attempts for provider-free settlement retry', function (): void {
    $attempt = scheduledPaymentAttempt(status: PaymentAttemptStatus::Verified);

    $this->artisan('xchange:payments:verify-open')->assertSuccessful();

    Queue::assertPushed(
        VerifyPaymentAttemptJob::class,
        fn (VerifyPaymentAttemptJob $job): bool => $job->paymentAttemptId === $attempt->getKey(),
    );
});

it('prioritizes the least recently checked payment attempts', function (): void {
    $recent = scheduledPaymentAttempt(expiresAt: now()->addMinute());
    $older = scheduledPaymentAttempt(expiresAt: now()->addMinutes(10));
    $unverified = scheduledPaymentAttempt(expiresAt: now()->addMinutes(15));
    PaymentAttempt::query()->whereKey($recent->getKey())->update(['last_checked_at' => now()->subMinute()]);
    PaymentAttempt::query()->whereKey($older->getKey())->update(['last_checked_at' => now()->subMinutes(5)]);
    config()->set('x-change.payment.attempts.scheduled_batch_size', 2);

    $this->artisan('xchange:payments:verify-open')->assertSuccessful();

    Queue::assertPushed(VerifyPaymentAttemptJob::class, 2);
    Queue::assertPushed(VerifyPaymentAttemptJob::class, fn (VerifyPaymentAttemptJob $job): bool => $job->paymentAttemptId === $older->getKey());
    Queue::assertPushed(VerifyPaymentAttemptJob::class, fn (VerifyPaymentAttemptJob $job): bool => $job->paymentAttemptId === $unverified->getKey());
    Queue::assertNotPushed(VerifyPaymentAttemptJob::class, fn (VerifyPaymentAttemptJob $job): bool => $job->paymentAttemptId === $recent->getKey());
});

it('schedules bounded late reconciliation without repeatedly checking recent attempts', function (): void {
    $eligible = scheduledPaymentAttempt(status: PaymentAttemptStatus::Expired, expiresAt: now()->subDay());
    $recent = scheduledPaymentAttempt(status: PaymentAttemptStatus::Expired, expiresAt: now()->subDay());
    $old = scheduledPaymentAttempt(status: PaymentAttemptStatus::Expired, expiresAt: now()->subDays(8));
    PaymentAttempt::query()->whereKey($eligible->getKey())->update(['instructions_created_at' => now()->subDays(2)]);
    PaymentAttempt::query()->whereKey($recent->getKey())->update(['instructions_created_at' => now()->subDays(2), 'last_checked_at' => now()->subMinute()]);
    PaymentAttempt::query()->whereKey($old->getKey())->update(['instructions_created_at' => now()->subDays(9)]);

    $this->artisan('xchange:payments:verify-open')->assertSuccessful();

    Queue::assertPushed(VerifyPaymentAttemptJob::class, 1);
    Queue::assertPushed(VerifyPaymentAttemptJob::class, fn (VerifyPaymentAttemptJob $job): bool => $job->paymentAttemptId === $eligible->getKey());
});

it('registers the package-owned non-overlapping payment verification schedule', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'xchange:payments:verify-open:netbank');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->expiresAt)->toBe(5)
        ->and($event->command)->toContain(
            'xchange:payments:verify-open --provider=netbank --limit=100',
        );
});

it('queues only QR attempts inside the bounded monitoring window including settled attempts', function (): void {
    $live = scheduledPaymentAttempt(status: PaymentAttemptStatus::Settled);
    $expired = scheduledPaymentAttempt(expiresAt: now()->subDays(8));
    scheduledPaymentAttempt();
    PaymentAttempt::query()->whereKey($live->getKey())->update(['instructions_created_at' => now()]);
    PaymentAttempt::query()->whereKey($expired->getKey())->update(['instructions_created_at' => now()->subDays(9)]);

    $this->artisan('xchange:payments:monitor-open', ['--limit' => 50])->assertSuccessful();

    Queue::assertPushed(MonitorPaymentAttemptJob::class, 1);
    Queue::assertPushed(
        MonitorPaymentAttemptJob::class,
        fn (MonitorPaymentAttemptJob $job): bool => $job->paymentAttemptId === $live->getKey(),
    );
});

it('registers monitoring by default and respects the host setting', function (): void {
    $schedule = app(Schedule::class);
    $count = fn (): int => collect($schedule->events())
        ->filter(fn ($event): bool => $event->description === 'xchange:payments:monitor-open:netbank')->count();
    expect($count())->toBe(1);
    config()->set('x-change.payment.monitoring.scheduled_enabled', false);
    (new XChangeServiceProvider($this->app))->boot();
    expect($count())->toBe(1);
    config()->set('x-change.payment.monitoring.scheduled_enabled', true);
    $provider = new XChangeServiceProvider($this->app);
    $provider->boot();
    $enabled = collect($schedule->events())
        ->first(fn ($event): bool => $event->description === 'xchange:payments:monitor-open:netbank');

    expect($enabled)->not->toBeNull()
        ->and($count())->toBe(2)
        ->and($enabled->withoutOverlapping)->toBeTrue()
        ->and($enabled->onOneServer)->toBeTrue();
});

it('gives unpolled QR attempts priority within a bounded monitoring batch', function (): void {
    $polled = scheduledPaymentAttempt();
    $unpolled = scheduledPaymentAttempt();
    PaymentAttempt::query()->whereKey($polled->getKey())->update([
        'instructions_created_at' => now(),
        'last_monitored_at' => now()->subMinute(),
    ]);
    PaymentAttempt::query()->whereKey($unpolled->getKey())->update(['instructions_created_at' => now()]);

    $this->artisan('xchange:payments:monitor-open', ['--limit' => 1])->assertSuccessful();

    Queue::assertPushed(MonitorPaymentAttemptJob::class, 1);
    Queue::assertPushed(
        MonitorPaymentAttemptJob::class,
        fn (MonitorPaymentAttemptJob $job): bool => $job->paymentAttemptId === $unpolled->getKey(),
    );
});

function scheduledPaymentAttempt(
    PaymentAttemptStatus $status = PaymentAttemptStatus::AwaitingPayment,
    ?DateTimeInterface $expiresAt = null,
): PaymentAttempt {
    $user = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) $user->id,
                'collection_wallet_id' => $user->wallet->id,
            ],
        ],
    ));

    return PaymentAttempt::query()->create([
        'voucher_id' => $voucher->getKey(),
        'provider_code' => 'netbank',
        'expected_amount_minor' => 10_000,
        'currency' => 'PHP',
        'status' => $status,
        'version' => 1,
        'session_key_hash' => hash('sha256', (string) Str::uuid()),
        'idempotency_key_hash' => hash('sha256', (string) Str::uuid()),
        'idempotency_fingerprint' => hash('sha256', (string) Str::uuid()),
        'expires_at' => $expiresAt ?? now()->addMinutes(15),
    ]);
}
it('shares a provider rate limit between funding and payment verification jobs', function (): void {
    config()->set('x-change.funding.verification_provider_rate_limit_per_minute', 1);
    config()->set('x-change.payment.attempts.verification_provider_rate_limit_per_minute', 2);
    $key = md5('x-change-provider-verificationnetbank');
    RateLimiter::clear($key);

    $funding = (new VerifyFundingIntentJob(
        1, 'netbank', FundingVerificationTrigger::Schedule, 'test',
    ))->withFakeQueueInteractions();
    $payment = (new VerifyPaymentAttemptJob(
        1, 'NETBANK', PaymentVerificationTrigger::Schedule,
    ))->withFakeQueueInteractions();
    $fundingRan = false;
    $paymentRan = false;
    $additional = (new VerifyAdditionalQrPaymentJob(1, 'netbank'))->withFakeQueueInteractions();
    $payout = (new ReconcileValueFlowPayoutJob(1, 'netbank'))->withFakeQueueInteractions();
    $additionalRan = false;
    $payoutRan = false;
    $funding->middleware()[1]->handle($funding, function () use (&$fundingRan): void {
        $fundingRan = true;
    });
    $payment->middleware()[1]->handle($payment, function () use (&$paymentRan): void {
        $paymentRan = true;
    });
    $additional->middleware()[1]->handle($additional, function () use (&$additionalRan): void {
        $additionalRan = true;
    });
    $payout->middleware()[1]->handle($payout, function () use (&$payoutRan): void {
        $payoutRan = true;
    });

    expect($fundingRan)->toBeTrue()->and($paymentRan)->toBeFalse()->and($additionalRan)->toBeFalse()->and($payoutRan)->toBeFalse();
    $payment->assertReleased();
    $additional->assertReleased();
    $payout->assertReleased();
    RateLimiter::clear($key);
});

it('keeps rate-limited provider checks retryable without hiding repeated exceptions', function (): void {
    $queue = new class extends SyncQueue
    {
        public function payload(object $job): array
        {
            return $this->createPayloadArray($job, 'test');
        }
    };
    $jobs = [
        new VerifyFundingIntentJob(1, 'netbank', FundingVerificationTrigger::Schedule, 'test'),
        new SyncStandingFundingAddressJob(1, 'netbank', 'schedule'),
        new VerifyFundingWebhookReceiptJob(1),
        new VerifyPaymentAttemptJob(1, 'netbank', PaymentVerificationTrigger::Schedule),
        new MonitorPaymentAttemptJob(1, 'netbank'),
        new VerifyAdditionalQrPaymentJob(1, 'netbank'),
        new ReconcileValueFlowPayoutJob(1, 'netbank'),
    ];

    foreach ($jobs as $job) {
        $payload = $queue->payload($job);
        expect($payload['retryUntil'])->toBeGreaterThan(now()->addHours(23)->timestamp)
            ->and($payload['maxExceptions'])->toBe(5);
    }
});
