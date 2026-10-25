<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\XChange\Actions\Onboarding\SendPublicAutoRegistrationSms;
use LBHurtado\XChange\Jobs\Feedback\DeliverQueuedFeedbackSmsJob;
use LBHurtado\XChange\Models\PublicAutoRegistration;
use LBHurtado\XFeedback\Contracts\FeedbackChannelDriverContract;
use LBHurtado\XFeedback\Contracts\FeedbackChannelRegistryContract;
use LBHurtado\XFeedback\Models\FeedbackDeliveryRecord;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('journals a queued account-ready sms without calling the provider', function () {
    Bus::fake([DeliverQueuedFeedbackSmsJob::class]);
    config()->set('x-change.public_auto_register.sms.enabled', true);
    config()->set('x-feedback.transports.sms.driver', 'engagespark');
    $registration = new PublicAutoRegistration([
        'reference' => '01PUBLICREGISTRATIONTEST',
        'metadata' => ['payer_mobile' => '09177210752'],
    ]);
    $registration->setRelation('contact', new Contact(['mobile' => '09177210752']));
    $registration->setRelation('fundingIntent', null);

    app(SendPublicAutoRegistrationSms::class)->handle($registration);

    Bus::assertDispatched(DeliverQueuedFeedbackSmsJob::class, fn (DeliverQueuedFeedbackSmsJob $job): bool => str_contains($job->message, '/login'));
    expect(FeedbackDeliveryRecord::query()->sole()->status)->toBe('queued')
        ->and(ExecutionJournalEntry::query()->orderBy('id')->pluck('event_type')->all())->toBe([
            'feedback.created',
            'feedback.queued',
        ]);
});

it('does not create sms delivery when the host has not opted in', function () {
    Bus::fake([DeliverQueuedFeedbackSmsJob::class]);
    config()->set('x-change.public_auto_register.sms.enabled', false);

    app(SendPublicAutoRegistrationSms::class)->handle(
        new PublicAutoRegistration(['reference' => '01PUBLICREGISTRATIONTEST']),
    );

    Bus::assertNotDispatched(DeliverQueuedFeedbackSmsJob::class);
    expect(FeedbackDeliveryRecord::query()->count())->toBe(0);
});

it('skips account-ready sms when the activated registration has no usable mobile', function () {
    Bus::fake([DeliverQueuedFeedbackSmsJob::class]);
    config()->set('x-change.public_auto_register.sms.enabled', true);
    $registration = new PublicAutoRegistration(['reference' => '01PUBLICREGISTRATIONTEST']);
    $registration->setRelation('contact', null);
    $registration->setRelation('fundingIntent', null);

    app(SendPublicAutoRegistrationSms::class)->handle($registration);

    Bus::assertNotDispatched(DeliverQueuedFeedbackSmsJob::class);
    expect(FeedbackDeliveryRecord::query()->count())->toBe(0);
});

it('rejects an enabled sms path without the queued sms driver', function () {
    config()->set('x-change.public_auto_register.sms.enabled', true);

    $registration = new PublicAutoRegistration(['reference' => '01PUBLICREGISTRATIONTEST']);
    $registration->setRelation('contact', new Contact(['mobile' => '09177210752']));
    $registration->setRelation('fundingIntent', null);

    $channels = Mockery::mock(FeedbackChannelRegistryContract::class);
    $channels->expects('driver')->with('sms')->andReturn(Mockery::mock(FeedbackChannelDriverContract::class));
    app()->instance(FeedbackChannelRegistryContract::class, $channels);

    expect(fn () => app(SendPublicAutoRegistrationSms::class)->handle($registration))
        ->toThrow(RuntimeException::class, 'Public auto-registration SMS requires the queued SMS driver.');
});
