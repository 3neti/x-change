<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Jobs\Payment\MonitorPaymentAttemptJob;
use LBHurtado\XChange\Jobs\Payment\VerifyPaymentAttemptJob;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Providers\XChangeServiceProvider;

beforeEach(function (): void {
    Queue::fake();
    config()->set('x-change.funding.providers.netbank.enabled', true);
    config()->set('x-change.payment.attempts.scheduled_verification_enabled', true);
    config()->set('x-change.payment.attempts.scheduled_batch_size', 100);
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
    $expired = scheduledPaymentAttempt(expiresAt: now()->subMinutes(10));
    scheduledPaymentAttempt();
    PaymentAttempt::query()->whereKey($live->getKey())->update(['instructions_created_at' => now()]);
    PaymentAttempt::query()->whereKey($expired->getKey())->update(['instructions_created_at' => now()->subMinutes(20)]);

    $this->artisan('xchange:payments:monitor-open', ['--limit' => 50])->assertSuccessful();

    Queue::assertPushed(MonitorPaymentAttemptJob::class, 1);
    Queue::assertPushed(
        MonitorPaymentAttemptJob::class,
        fn (MonitorPaymentAttemptJob $job): bool => $job->paymentAttemptId === $live->getKey(),
    );
});

it('registers monitoring only when explicitly enabled', function (): void {
    $disabled = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'xchange:payments:monitor-open:netbank');

    expect($disabled)->toBeNull();

    config()->set('x-change.payment.monitoring.scheduled_enabled', true);
    $provider = new XChangeServiceProvider($this->app);
    $provider->boot();
    $enabled = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'xchange:payments:monitor-open:netbank');

    expect($enabled)->not->toBeNull()
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
