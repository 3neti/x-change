<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Feedback;

use LBHurtado\XFeedback\Contracts\FeedbackChannelDriverContract;
use LBHurtado\XFeedback\Data\FeedbackChannelData;
use LBHurtado\XFeedback\Data\FeedbackChannelHealthData;
use LBHurtado\XFeedback\Data\FeedbackDeliveryData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;
use LBHurtado\XFeedback\Drivers\Concerns\BuildsBaselineDeliveryData;

final class QueuedEngageSparkSmsFeedbackChannelDriver implements FeedbackChannelDriverContract
{
    use BuildsBaselineDeliveryData;

    public function send(
        FeedbackIntentData $intent,
        FeedbackRecipientData $recipient,
        FeedbackChannelData $channel,
    ): FeedbackDeliveryData {
        return $this->queuedBaselineDelivery(
            driver: 'sms',
            intent: $intent,
            recipient: $recipient,
            channel: $channel,
            result: [
                'transport' => 'engagespark',
                'provider_status' => 'QUEUED',
                'queue' => (string) config(
                    'x-change.redemption.feedback.queue',
                    'x-change-feedback',
                ),
                'sender' => (string) (
                    $channel->options['sender']
                    ?? config('x-feedback.transports.sms.sender', 'XCHANGE')
                ),
            ],
        );
    }

    public function supports(
        FeedbackIntentData $intent,
        FeedbackRecipientData $recipient,
        FeedbackChannelData $channel,
    ): bool {
        return $channel->key === 'sms'
            && is_string($recipient->phone)
            && trim($recipient->phone) !== '';
    }

    public function health(): FeedbackChannelHealthData
    {
        return $this->availableHealth('sms');
    }
}
