<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingIntentJob;
use LBHurtado\XChange\Models\FundingIntent;

beforeEach(function () {
    Queue::fake();
    config()->set('x-change.funding.providers.netbank.enabled', true);
    config()->set('x-change.funding.scheduled_verification_batch_size', 100);
    config()->set('x-change.funding.settlement_grace_seconds', 300);
});

it('queues only eligible enabled-provider intents within the configured batch', function () {
    scheduledFundingIntent(expiresAt: now()->addMinutes(20));
    scheduledFundingIntent(expiresAt: now()->addMinutes(10));
    scheduledFundingIntent(expiresAt: now()->addMinutes(30));
    scheduledFundingIntent(
        providerCode: 'paynamics_constellation',
        expiresAt: now()->addMinutes(5),
    );
    scheduledFundingIntent(
        status: FundingIntentStatus::Settled,
        expiresAt: now()->addMinutes(5),
    );
    config()->set('x-change.funding.scheduled_verification_batch_size', 2);

    $this->artisan('xchange:funding:verify-open', [
        '--provider' => 'netbank',
        '--limit' => 99,
    ])->assertSuccessful();

    Queue::assertPushed(VerifyFundingIntentJob::class, 2);
    Queue::assertPushed(
        VerifyFundingIntentJob::class,
        fn (VerifyFundingIntentJob $job): bool => $job->trigger === FundingVerificationTrigger::Schedule
            && $job->actorId === 'funding-scheduler'
            && $job->providerCode === 'netbank',
    );
});

it('does not queue checks for a disabled provider', function () {
    scheduledFundingIntent(expiresAt: now()->addMinutes(20));
    config()->set('x-change.funding.providers.netbank.enabled', false);

    $this->artisan('xchange:funding:verify-open')
        ->expectsOutputToContain('disabled')
        ->assertSuccessful();

    Queue::assertNotPushed(VerifyFundingIntentJob::class);
});

it('performs a final provider check after grace before expiring an intent', function () {
    $intent = scheduledFundingIntent(expiresAt: now()->subMinutes(10));

    $this->artisan('xchange:funding:verify-open')->assertSuccessful();

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::AwaitingFunds);
    Queue::assertPushed(
        VerifyFundingIntentJob::class,
        fn (VerifyFundingIntentJob $job): bool => $job->fundingIntentId === $intent->getKey(),
    );
});

it('expires only after a conclusive no-funds observation beyond grace', function () {
    $intent = scheduledFundingIntent(expiresAt: now()->subMinutes(10));
    scheduledFundingEvent(
        intent: $intent,
        eventType: 'provider_funds_not_observed',
        occurredAt: now()->subMinute(),
    );

    $this->artisan('xchange:funding:verify-open')->assertSuccessful();

    $intent = $intent->refresh()->load('events');

    expect($intent->status)->toBe(FundingIntentStatus::Expired)
        ->and($intent->expired_at)->not->toBeNull()
        ->and($intent->events->last()->event_type)
        ->toBe('funding_intent_expired_after_settlement_grace')
        ->and($intent->events->last()->metadata)->toMatchArray([
            'trigger' => 'schedule',
            'provider_code' => 'netbank',
            'settlement_grace_seconds' => 300,
        ]);
    Queue::assertNotPushed(
        VerifyFundingIntentJob::class,
        fn (VerifyFundingIntentJob $job): bool => $job->fundingIntentId === $intent->getKey(),
    );
});

it('keeps checking after provider outages and recovers verified settlement work', function () {
    $outageIntent = scheduledFundingIntent(expiresAt: now()->subMinutes(10));
    scheduledFundingEvent(
        intent: $outageIntent,
        eventType: 'provider_verification_unavailable',
        occurredAt: now()->subMinute(),
    );
    $verifiedIntent = scheduledFundingIntent(
        status: FundingIntentStatus::Verified,
        expiresAt: now()->subMinutes(10),
    );

    $this->artisan('xchange:funding:verify-open')->assertSuccessful();

    expect($outageIntent->refresh()->status)->toBe(FundingIntentStatus::AwaitingFunds)
        ->and($verifiedIntent->refresh()->status)->toBe(FundingIntentStatus::Verified);
    Queue::assertPushed(
        VerifyFundingIntentJob::class,
        fn (VerifyFundingIntentJob $job): bool => $job->fundingIntentId === $outageIntent->getKey(),
    );
    Queue::assertPushed(
        VerifyFundingIntentJob::class,
        fn (VerifyFundingIntentJob $job): bool => $job->fundingIntentId === $verifiedIntent->getKey(),
    );
});

it('registers the package-owned non-overlapping minute schedule', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'xchange:funding:verify-open:netbank');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(5)
        ->and($event->command)->toContain(
            'xchange:funding:verify-open --provider=netbank --limit=100',
        );
});

function scheduledFundingIntent(
    FundingIntentStatus $status = FundingIntentStatus::AwaitingFunds,
    string $providerCode = 'netbank',
    ?DateTimeInterface $expiresAt = null,
): FundingIntent {
    $intent = FundingIntent::query()->create([
        'account_reference' => 'wallet:'.Str::uuid(),
        'provider_code' => $providerCode,
        'expected_amount_minor' => 25_000,
        'currency' => 'PHP',
        'status' => $status,
        'version' => 1,
        'idempotency_key_hash' => hash('sha256', (string) Str::uuid()),
        'idempotency_fingerprint' => hash('sha256', (string) Str::uuid()),
        'created_by_type' => 'operator',
        'created_by_id' => '42',
        'expires_at' => $expiresAt ?? now()->addMinutes(30),
    ]);

    $intent->events()->create([
        'sequence' => 1,
        'event_type' => 'created',
        'from_status' => null,
        'to_status' => $status,
        'actor_type' => 'operator',
        'actor_id' => '42',
        'metadata' => [],
        'occurred_at' => now()->subHour(),
    ]);

    return $intent;
}

function scheduledFundingEvent(
    FundingIntent $intent,
    string $eventType,
    DateTimeInterface $occurredAt,
): void {
    $nextVersion = $intent->version + 1;

    $intent->events()->create([
        'sequence' => $nextVersion,
        'event_type' => $eventType,
        'from_status' => $intent->status,
        'to_status' => $intent->status,
        'actor_type' => 'system',
        'actor_id' => 'funding-scheduler',
        'metadata' => ['trigger' => 'schedule'],
        'occurred_at' => $occurredAt,
    ]);
    $intent->forceFill(['version' => $nextVersion])->saveQuietly();
}
