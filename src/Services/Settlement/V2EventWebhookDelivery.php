<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Feedback\DeliverAndJournalFeedback;
use LBHurtado\XChange\Models\V2EvidenceOutbox;
use LBHurtado\XFeedback\Data\FeedbackChannelData;
use LBHurtado\XFeedback\Data\FeedbackDeliveryData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackMessageData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;
use RuntimeException;

final readonly class V2EventWebhookDelivery
{
    public function __construct(private DeliverAndJournalFeedback $delivery) {}

    public function deliver(V2EvidenceOutbox $event): void
    {
        $voucher = Voucher::query()->findOrFail($event->voucher_id);
        $subscriptions = data_get($voucher->metadata, 'instructions.feedback.webhooks', []);
        if (! is_array($subscriptions)) {
            throw new RuntimeException('Invalid v2 webhook subscriptions.');
        }

        foreach ($subscriptions as $index => $subscription) {
            if (! is_array($subscription) || ! is_string($subscription['url'] ?? null) || ! is_array($subscription['events'] ?? null)) {
                throw new RuntimeException('Invalid v2 webhook subscription.');
            }
            if (! in_array($event->event_type, $subscription['events'], true)) {
                continue;
            }

            $id = hash('sha256', $event->event_key.'|'.$index.'|'.$subscription['url']);
            $options = [
                'url' => $subscription['url'],
                'headers' => (array) config('x-change.redemption.feedback.webhook.headers', []),
            ];
            $secret = config('x-change.redemption.feedback.webhook.secret');
            if (is_string($secret) && trim($secret) !== '') {
                $options['secret'] = $secret;
            }
            $intent = FeedbackIntentData::forEvent(
                key: $event->event_type,
                eventType: $event->event_type,
                message: new FeedbackMessageData(
                    title: 'Pay Code update',
                    body: 'A Pay Code event was recorded.',
                    variables: ['event_id' => $event->event_key, 'occurred_at' => $event->occurred_at->toISOString()],
                ),
                recipients: [new FeedbackRecipientData(type: 'voucher_webhook', id: $id)],
                channels: [new FeedbackChannelData(key: 'webhook', options: $options)],
                source: 'x-change.v2',
                correlationId: 'voucher:'.$event->voucher_id,
                causationId: $event->event_key,
                subjectType: 'pay_code',
                subjectId: (string) $voucher->code,
            );
            $result = $this->delivery->handle($intent, 'webhook', $id, true);
            if (! in_array($result->status, [FeedbackDeliveryData::StatusQueued, FeedbackDeliveryData::StatusSent, FeedbackDeliveryData::StatusDelivered], true)) {
                throw new RuntimeException('V2 webhook delivery requires attention.');
            }
        }
    }
}
