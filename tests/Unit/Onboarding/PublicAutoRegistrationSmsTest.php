<?php

declare(strict_types=1);

use LBHurtado\Contact\Models\Contact;
use LBHurtado\XChange\Actions\Feedback\DeliverAndJournalFeedback;
use LBHurtado\XChange\Actions\Onboarding\SendPublicAutoRegistrationSms;
use LBHurtado\XChange\Data\Feedback\JournaledFeedbackDeliveryResultData;
use LBHurtado\XChange\Models\PublicAutoRegistration;
use LBHurtado\XChange\Services\Feedback\QueuedEngageSparkSmsFeedbackChannelDriver;
use LBHurtado\XFeedback\Contracts\FeedbackChannelRegistryContract;
use LBHurtado\XFeedback\Data\FeedbackIntentData;

it('journals a queued account-ready sms for an activated public registration', function () {
    $registration = new PublicAutoRegistration([
        'reference' => '01PUBLICREGISTRATIONTEST',
        'metadata' => ['payer_mobile' => '09177210752'],
    ]);
    $registration->setRelation('contact', new Contact(['mobile' => '09177210752']));

    $channels = Mockery::mock(FeedbackChannelRegistryContract::class);
    $channels->expects('driver')
        ->with('sms')
        ->andReturn(new QueuedEngageSparkSmsFeedbackChannelDriver());
    app()->instance(FeedbackChannelRegistryContract::class, $channels);

    $feedback = Mockery::mock(DeliverAndJournalFeedback::class);
    $feedback->expects('handle')
        ->withArgs(fn (FeedbackIntentData $intent, string $channel, string $runReference, bool $send): bool => $intent->key === 'public.auto_registration.completed'
            && $channel === 'sms'
            && $runReference === 'public-auto-register:01PUBLICREGISTRATIONTEST'
            && $send)
        ->andReturn(new JournaledFeedbackDeliveryResultData(
            status: 'queued',
            channel: 'sms',
            maskedRoute: '••••0752',
            runReference: 'public-auto-register:01PUBLICREGISTRATIONTEST',
        ));
    app()->instance(DeliverAndJournalFeedback::class, $feedback);

    app(SendPublicAutoRegistrationSms::class)->handle($registration);

    expect(true)->toBeTrue();
});
