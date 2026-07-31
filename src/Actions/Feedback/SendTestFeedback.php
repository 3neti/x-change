<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Feedback;

use InvalidArgumentException;
use LBHurtado\XChange\Data\Feedback\JournaledFeedbackDeliveryResultData;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use LBHurtado\XFeedback\Data\FeedbackChannelData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackMessageData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;

final readonly class SendTestFeedback
{
    public function __construct(
        private DeliverAndJournalFeedback $delivery,
    ) {}

    public function handle(
        string $channel,
        string $route,
        string $message,
        string $runReference,
        bool $send,
        string $title = 'X-Change feedback delivery test',
    ): JournaledFeedbackDeliveryResultData {
        if (! in_array($channel, ['email', 'sms'], true)) {
            throw new InvalidArgumentException("Unsupported feedback test channel [{$channel}].");
        }

        $route = trim($route);

        if ($channel === 'sms') {
            $route = MobileNumber::normalize($route) ?? '';
        }

        if ($route === '') {
            throw new InvalidArgumentException('A feedback destination is required.');
        }

        $recipientId = 'route:'.hash('sha256', mb_strtolower($route));
        $recipient = new FeedbackRecipientData(
            type: 'feedback_test',
            id: $recipientId,
            email: $channel === 'email' ? $route : null,
            phone: $channel === 'sms' ? $route : null,
        );

        return $this->delivery->handle(
            intent: FeedbackIntentData::forEvent(
                key: 'feedback.test.direct',
                eventType: 'feedback.test.requested',
                message: new FeedbackMessageData(
                    title: $title,
                    body: $message,
                    summary: $title,
                    meta: [
                        'provider_delivery' => $send,
                        'test_delivery' => true,
                    ],
                ),
                recipients: [$recipient],
                channels: [new FeedbackChannelData(key: $channel)],
                source: 'x-change.feedback.test',
                correlationId: $runReference,
                causationId: 'artisan',
                subjectType: 'feedback_test',
                subjectId: $runReference,
                meta: [
                    'delivery_only' => true,
                    'owns_lifecycle_truth' => false,
                ],
            ),
            channel: $channel,
            runReference: $runReference,
            send: $send,
        );
    }
}
