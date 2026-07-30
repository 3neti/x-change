<?php

declare(strict_types=1);

use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Jobs\Funding\SyncStandingFundingAddressJob;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingIntentJob;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingWebhookReceiptJob;
use LBHurtado\XChange\Jobs\Payment\VerifyPaymentAttemptJob;
use LBHurtado\XChange\Models\ExternalJobFailure;

it('bounds provider-facing jobs below the queue retry window', function (): void {
    config()->set('queue.connections.database.retry_after', 90);

    $jobs = [
        new VerifyFundingIntentJob(
            fundingIntentId: 1,
            providerCode: 'netbank',
            trigger: FundingVerificationTrigger::Schedule,
            actorId: 'schedule',
        ),
        new VerifyFundingWebhookReceiptJob(webhookReceiptId: 1),
        new SyncStandingFundingAddressJob(
            standingFundingAddressId: 1,
            providerCode: 'netbank',
            trigger: 'schedule',
        ),
        new VerifyPaymentAttemptJob(
            paymentAttemptId: 1,
            providerCode: 'netbank',
            trigger: PaymentVerificationTrigger::Schedule,
        ),
    ];

    foreach ($jobs as $job) {
        expect($job->timeout)
            ->toBe(60)
            ->toBeLessThan((int) config('queue.connections.database.retry_after'))
            ->and($job->failOnTimeout)->toBeTrue()
            ->and($job->tries)->toBe(5)
            ->and($job->queue)->toBe('x-change-funding');
    }
});

it('records sanitized append-only evidence when provider jobs fail terminally', function (): void {
    $jobs = [
        new VerifyFundingIntentJob(
            fundingIntentId: 11,
            providerCode: 'netbank',
            trigger: FundingVerificationTrigger::Schedule,
            actorId: 'schedule',
        ),
        new VerifyFundingWebhookReceiptJob(webhookReceiptId: 12),
        new SyncStandingFundingAddressJob(
            standingFundingAddressId: 13,
            providerCode: 'netbank',
            trigger: 'schedule',
        ),
        new VerifyPaymentAttemptJob(
            paymentAttemptId: 14,
            providerCode: 'netbank',
            trigger: PaymentVerificationTrigger::Schedule,
        ),
    ];

    foreach ($jobs as $job) {
        $job->failed(new RuntimeException('provider payload must not be persisted'));
    }

    expect(ExternalJobFailure::query()->count())->toBe(4)
        ->and(ExternalJobFailure::query()->pluck('failure_type')->unique()->all())
        ->toBe(['RuntimeException'])
        ->and(ExternalJobFailure::query()->whereNotNull('failed_at')->count())->toBe(4);

    $failure = ExternalJobFailure::query()->firstOrFail();

    expect(fn () => $failure->update(['trigger' => 'changed']))
        ->toThrow(LogicException::class, 'append-only')
        ->and(fn () => $failure->delete())
        ->toThrow(LogicException::class, 'append-only');
});
