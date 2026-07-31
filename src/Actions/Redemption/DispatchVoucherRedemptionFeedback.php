<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Feedback\DeliverAndJournalFeedback;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XFeedback\Data\FeedbackChannelData;
use LBHurtado\XFeedback\Data\FeedbackDeliveryData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackMessageData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;
use RuntimeException;

final readonly class DispatchVoucherRedemptionFeedback
{
    private const IntentKey = 'voucher.redemption.recorded';

    public function __construct(
        private DeliverAndJournalFeedback $delivery,
    ) {}

    public function handle(int $voucherClaimId): void
    {
        $claim = VoucherClaim::query()
            ->with('voucher')
            ->findOrFail($voucherClaimId);

        if (! $this->isEligible($claim) || ! $claim->voucher instanceof Voucher) {
            return;
        }

        $retryableChannels = [];

        foreach ($this->routes($claim->voucher) as $channel => $route) {
            $result = $this->delivery->handle(
                intent: $this->intent($claim, $channel, $route),
                channel: $channel,
                runReference: sprintf(
                    'voucher-redemption:%s:%s',
                    $claim->getKey(),
                    $channel,
                ),
                send: true,
            );

            if ($result->status === FeedbackDeliveryData::StatusFailedRetryable) {
                $retryableChannels[] = $channel;
            }
        }

        if ($retryableChannels !== []) {
            throw new RuntimeException(sprintf(
                'Redemption feedback channels require retry: %s.',
                implode(', ', array_unique($retryableChannels)),
            ));
        }
    }

    private function isEligible(VoucherClaim $claim): bool
    {
        if (! (bool) config('x-change.redemption.feedback.enabled', true)) {
            return false;
        }

        return in_array(
            $claim->status,
            (array) config('x-change.redemption.feedback.terminal_claim_statuses', [
                'succeeded',
                'redeemed',
                'withdrawn',
            ]),
            true,
        );
    }

    /**
     * @return array<string, string>
     */
    private function routes(Voucher $voucher): array
    {
        $feedback = $this->feedbackInstructions($voucher);
        $routes = [
            'email' => $this->stringValue($feedback['email'] ?? null),
            'sms' => $this->stringValue($feedback['mobile'] ?? null),
            'webhook' => $this->stringValue($feedback['webhook'] ?? null),
        ];

        return array_filter(
            $routes,
            static fn (?string $route): bool => $route !== null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function feedbackInstructions(Voucher $voucher): array
    {
        $feedback = data_get($voucher->metadata, 'instructions.feedback');

        if (is_array($feedback)) {
            return $feedback;
        }

        return $voucher->instructions->feedback->toArray();
    }

    private function intent(VoucherClaim $claim, string $channel, string $route): FeedbackIntentData
    {
        $voucher = $claim->voucher;

        return FeedbackIntentData::forEvent(
            key: self::IntentKey,
            eventType: self::IntentKey,
            message: new FeedbackMessageData(
                title: 'Pay Code redeemed',
                body: sprintf('Pay Code %s was redeemed successfully.', $voucher->code),
                summary: sprintf('Pay Code %s redeemed', $voucher->code),
                variables: [
                    'voucher_code' => $voucher->code,
                    'claim_id' => $claim->getKey(),
                    'claim_status' => $claim->status,
                ],
                meta: [
                    'provider_delivery' => true,
                    'lifecycle_truth_owner' => 'x-change',
                ],
            ),
            recipients: [
                new FeedbackRecipientData(
                    type: 'voucher_feedback_destination',
                    id: sprintf('%s:%s', $claim->getKey(), $channel),
                    email: $channel === 'email' ? $route : null,
                    phone: $channel === 'sms' ? $route : null,
                ),
            ],
            channels: [
                new FeedbackChannelData(
                    key: $channel,
                    options: $this->channelOptions($channel, $route),
                ),
            ],
            source: 'x-change.redemption',
            correlationId: (string) $voucher->code,
            causationId: (string) $claim->getKey(),
            subjectType: 'pay_code',
            subjectId: (string) $voucher->code,
            meta: [
                'claim_id' => $claim->getKey(),
                'claim_status' => $claim->status,
                'delivery_only' => true,
                'owns_lifecycle_truth' => false,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function channelOptions(string $channel, string $route): array
    {
        if ($channel !== 'webhook') {
            return [];
        }

        $options = [
            'url' => $route,
            'headers' => (array) config('x-change.redemption.feedback.webhook.headers', []),
        ];
        $secret = $this->stringValue(config('x-change.redemption.feedback.webhook.secret'));

        if ($secret !== null) {
            $options['secret'] = $secret;
        }

        return $options;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
