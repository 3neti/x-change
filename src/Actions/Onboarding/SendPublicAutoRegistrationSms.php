<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Onboarding;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Actions\Feedback\DeliverAndJournalFeedback;
use LBHurtado\XChange\Models\PublicAutoRegistration;
use LBHurtado\XChange\Services\Feedback\QueuedEngageSparkSmsFeedbackChannelDriver;
use LBHurtado\XFeedback\Contracts\FeedbackChannelRegistryContract;
use LBHurtado\XFeedback\Data\FeedbackChannelData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackMessageData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;
use RuntimeException;

final readonly class SendPublicAutoRegistrationSms
{
    public function __construct(
        private DeliverAndJournalFeedback $feedback,
        private FeedbackChannelRegistryContract $channels,
    ) {}

    public function handle(PublicAutoRegistration $registration): void
    {
        if (! (bool) config('x-change.public_auto_register.sms.enabled', true)) {
            Log::info('Public auto-registration SMS is disabled.', [
                'registration_reference' => $registration->reference,
            ]);

            return;
        }

        $registration->loadMissing('contact', 'fundingIntent');
        $mobile = $registration->contact?->mobile
            ?? data_get($registration->metadata, 'payer_mobile');
        if (! is_string($mobile) || trim($mobile) === '') {
            Log::warning('Public auto-registration SMS requires a contact mobile number.', [
                'registration_reference' => $registration->reference,
            ]);

            return;
        }

        if (! $this->channels->driver('sms') instanceof QueuedEngageSparkSmsFeedbackChannelDriver) {
            throw new RuntimeException('Public auto-registration SMS requires the queued SMS driver.');
        }

        $loginUrl = Route::has('login') ? route('login', [], false) : '/login';
        $body = str_replace(
            ':url',
            url($loginUrl),
            (string) config(
                'x-change.public_auto_register.sms.body',
                'Your x-change account is ready. Sign in to your cockpit: :url',
            ),
        );
        $reference = 'public-auto-register:'.$registration->reference;

        $result = $this->feedback->handle(
            intent: FeedbackIntentData::forEvent(
                key: 'public.auto_registration.completed',
                eventType: 'public.auto_registration.sms.requested',
                message: new FeedbackMessageData(
                    title: (string) config(
                        'x-change.public_auto_register.sms.title',
                        'Your x-change account is ready',
                    ),
                    body: $body,
                ),
                recipients: [new FeedbackRecipientData(
                    type: 'public_auto_registration_payer',
                    id: $registration->reference,
                    phone: $mobile,
                )],
                channels: [new FeedbackChannelData(key: 'sms')],
                source: 'x-change.public-auto-register',
                correlationId: $reference,
                causationId: $registration->reference,
                subjectType: $registration::class,
                subjectId: $registration->reference,
                meta: [
                    'mobile_source' => 'registration_contact',
                    'identity_verified_by_x_change' => true,
                    'owns_lifecycle_truth' => false,
                ],
            ),
            channel: 'sms',
            runReference: $reference,
            send: true,
        );

        if (! in_array($result->status, ['queued', 'sent', 'delivered'], true)) {
            throw new RuntimeException('Public auto-registration SMS delivery was not accepted.');
        }
    }
}
