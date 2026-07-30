<?php

declare(strict_types=1);

use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Jobs\Funding\SyncStandingFundingAddressJob;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingIntentJob;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingWebhookReceiptJob;
use LBHurtado\XChange\Jobs\Payment\VerifyPaymentAttemptJob;

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
            ->and($job->tries)->toBe(5);
    }
});
