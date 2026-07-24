<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Jobs\Payment\VerifyPaymentAttemptJob;
use LBHurtado\XChange\Models\PaymentAttempt;

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

it('registers the package-owned non-overlapping payment verification schedule', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'xchange:payments:verify-open:netbank');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(5)
        ->and($event->command)->toContain(
            'xchange:payments:verify-open --provider=netbank --limit=100',
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
